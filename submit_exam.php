<?php
include "includes/sessions.php";
include "includes/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

// 🧾 Get basic info
$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
$answers = $_POST['answer'] ?? [];
$emp_id  = $emp_id ?? ($_COOKIE['EIMS_emp_Id'] ?? '');

if ($exam_id <= 0 || empty($answers) || empty($emp_id)) {
    die("<div style='color:red;'>Missing exam, employee, or answers data.</div>");
}

// 🧾 Record attempt (Answers table)
$sql = "
    INSERT INTO teipiexam.dbo.Answers (Emp_ID, Exam_ID, Date_Taken)
    OUTPUT INSERTED.Answers_ID
    VALUES (?, ?, GETDATE())
";
$params = [$emp_id, $exam_id];
$stmt = sqlsrv_query($con3, $sql, $params);
if ($stmt === false) {
    die("<pre style='color:red;'>Error inserting Answers:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$answers_id = $row['Answers_ID'] ?? null;
if (!$answers_id) {
    die("<div style='color:red;'>Failed to record exam attempt.</div>");
}

function normalizeAnswer($text) {
    // Convert to lowercase
    $text = mb_strtolower($text, 'UTF-8');

    // Convert ± to + (treat them as equivalent)
    $text = str_replace('±', '+', $text);

    // Remove punctuation except +, -, ., and space
    $text = preg_replace('/[,:;()]/u', ' ', $text);
    $text = preg_replace('/[^a-z0-9\+\-\.\s]/u', '', $text);

    // Remove ALL spaces (so it's not sensitive to spaces)
    $text = preg_replace('/\s+/', '', $text);

    // Trim again in case
    return trim($text);
}


/* 🧮 Evaluation */
$total_questions = 0;
$total_score = 0;

foreach ($answers as $question_id => $user_answer) {

    // Handle multiple inputs (enumeration)
    $user_answers = is_array($user_answer) ? $user_answer : [$user_answer];
    $user_answers = array_map('trim', $user_answers);
    $user_answers = array_filter($user_answers, fn($a) => $a !== '');

    // Fetch question type & correct answers
    $q_sql = "
        SELECT q.Question_Type, ca.Correct_Answer
        FROM teipiexam.dbo.Questions q
        LEFT JOIN teipiexam.dbo.Correct_Answers ca ON q.Question_ID = ca.Question_ID
        WHERE q.Question_ID = ?
    ";
    $q_stmt = sqlsrv_query($con3, $q_sql, [$question_id]);
    if ($q_stmt === false) {
        echo "<pre style='color:orange;'>Query failed for Question_ID $question_id:\n" . print_r(sqlsrv_errors(), true) . "</pre>";
        continue;
    }

    $correct_answers = [];
    $question_type = 'Text';
    while ($r = sqlsrv_fetch_array($q_stmt, SQLSRV_FETCH_ASSOC)) {
        $question_type = $r['Question_Type'];
        if (!empty($r['Correct_Answer'])) {
            $correct_answers[] = normalizeAnswer($r['Correct_Answer']);
        }
    }
    sqlsrv_free_stmt($q_stmt);

    if (empty($correct_answers)) {
        echo "<div style='color:orange;'>No correct answers found for Question_ID $question_id.</div>";
        continue;
    }

    $isCorrect = 0;
    $earned_points = 0;

    // 🧩 ENUMERATION CHECKING — each correct answer = 1 point
    if (strcasecmp($question_type, 'Enumeration') === 0) {
        $correct_remaining = $correct_answers;

        foreach ($user_answers as $ua) {
            $n_user = normalizeAnswer($ua);
            foreach ($correct_remaining as $idx => $ca) {
                // Flexible matching: allow containment or equal match
                if (strpos($n_user, $ca) !== false || strpos($ca, $n_user) !== false) {
                    $earned_points++;
                    unset($correct_remaining[$idx]);
                    break;
                }
            }
        }

        // Add enumeration points
        $total_score += $earned_points;
        $total_questions += count($correct_answers);
        $isCorrect = ($earned_points > 0) ? 1 : 0;
    }

    // 🧩 OTHER QUESTION TYPES (MCQ / Identification / True/False)
    else {
        $n_user = normalizeAnswer(implode(' ', $user_answers));
        foreach ($correct_answers as $ca) {
            if (strpos($n_user, $ca) !== false || strpos($ca, $n_user) !== false) {
                $isCorrect = 1;
                $total_score++;
                break;
            }
        }
        $total_questions++;
    }

    // 📝 Save user's combined answer text
    $combined_answer = implode(', ', $user_answers);
    $insert_sql = "
        INSERT INTO teipiexam.dbo.AnswerDetails (Answers_ID, Question_ID, User_Answer, IsCorrect)
        VALUES (?, ?, ?, ?)
    ";
    $params = [$answers_id, $question_id, $combined_answer, $isCorrect];
    sqlsrv_query($con3, $insert_sql, $params);
}

/* ✅ Cap score */
if ($total_score > $total_questions) {
    $total_score = $total_questions;
}

/* 🏁 Save results */
$sql = "
    INSERT INTO teipiexam.dbo.Results (Emp_ID, Exam_ID, Score, TotalQuestions, Date_Completed)
    OUTPUT INSERTED.Result_ID
    VALUES (?, ?, ?, ?, GETDATE())
";
$params = [$emp_id, $exam_id, $total_score, $total_questions];
$result_stmt = sqlsrv_query($con3, $sql, $params);
if ($result_stmt === false) {
    die("<pre style='color:red;'>Error inserting into Results:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$row = sqlsrv_fetch_array($result_stmt, SQLSRV_FETCH_ASSOC);
$result_id = $row['Result_ID'] ?? 0;

if ($result_id <= 0) {
    die("<div style='color:red;'>Failed to retrieve inserted Result_ID.</div>");
}

/* 🎁 Grant patches if exam passed (>=75%) */
$passing_percentage = ($total_questions > 0) ? ($total_score / $total_questions) * 100 : 0;
if ($passing_percentage >= 75) {
    // Ensure Employee_Patches table exists
    $create_table = "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'dbo.Employee_Patches') AND type in (N'U')) 
        CREATE TABLE dbo.Employee_Patches (
            Emp_ID NVARCHAR(MAX) NOT NULL,
            Patch_ID INT NOT NULL,
            Date_Earned DATETIME DEFAULT GETDATE(),
            PRIMARY KEY (Emp_ID, Patch_ID)
        )";
    @sqlsrv_query($con3, $create_table);
    
    // Get patches linked to this exam
    $patch_sql = "SELECT Patch_ID FROM dbo.Exam_Patches WHERE Exam_ID = ?";
    $patch_stmt = sqlsrv_query($con3, $patch_sql, [$exam_id]);
    
    if ($patch_stmt) {
        while ($patch_row = sqlsrv_fetch_array($patch_stmt, SQLSRV_FETCH_ASSOC)) {
            $patch_id = $patch_row['Patch_ID'];
            
            // Insert into Employee_Patches (ignore if already exists due to PRIMARY KEY)
            $grant_sql = "
                INSERT INTO dbo.Employee_Patches (Emp_ID, Patch_ID, Date_Earned)
                VALUES (?, ?, GETDATE())
            ";
            @sqlsrv_query($con3, $grant_sql, [$emp_id, $patch_id]);
        }
        sqlsrv_free_stmt($patch_stmt);
    }
}

/* ✅ Redirect properly */
header("Location: result.php?result_id=$result_id&exam_id=$exam_id");
exit;
?>
