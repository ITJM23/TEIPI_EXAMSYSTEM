<?php
include "includes/sessions.php";
include "includes/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

// Get exam and employee info
$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
$answers = $_POST['answer'] ?? [];
$emp_id  = $emp_id ?? ($_COOKIE['EIMS_emp_Id'] ?? ''); // from sessions.php or cookie

if ($exam_id <= 0 || empty($answers) || empty($emp_id)) {
    die("<div style='color:red;'>Missing exam, employee, or answers data.</div>");
}

// 🧾 Record the exam attempt in Answers table
$sql = "
    INSERT INTO teipiexam.dbo.Answers (Emp_ID, Exam_ID, Date_Taken)
    OUTPUT INSERTED.Answers_ID
    VALUES (?, ?, GETDATE())
";
$params = [$emp_id, $exam_id];
$stmt = sqlsrv_query($con3, $sql, $params);

if ($stmt === false) {
    die("<pre style='color:red;'>Error inserting into Answers:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row || !isset($row['Answers_ID'])) {
    die("<div style='color:red;'>Failed to record exam attempt.</div>");
}
$answers_id = $row['Answers_ID'];

/* 🧠 Normalization function for more forgiving matching */
function normalizeAnswer($text) {
    // Convert to lowercase
    $text = mb_strtolower($text, 'UTF-8');

    // Replace punctuation with spaces
    $text = preg_replace('/[.,;:()\-]/u', ' ', $text);

    // Remove any non-alphanumeric characters except spaces
    $text = preg_replace('/[^a-z0-9\s]/u', '', $text);

    // Collapse multiple spaces
    $text = preg_replace('/\s+/', ' ', $text);

    // Trim
    return trim($text);
}

/* 🧮 Evaluate each answer */
$total_questions = count($answers);
$correct = 0;

foreach ($answers as $question_id => $user_answer) {
    // Fetch correct answer & type
    $q_sql = "
        SELECT q.Question_Type, ca.Correct_Answer
        FROM teipiexam.dbo.Questions q
        INNER JOIN teipiexam.dbo.Correct_Answers ca
        ON q.Question_ID = ca.Question_ID
        WHERE q.Question_ID = ?
    ";
    $q_stmt = sqlsrv_query($con3, $q_sql, [$question_id]);

    if ($q_stmt === false) {
        echo "<pre style='color:orange;'>Query failed for Question_ID {$question_id}:\n" . print_r(sqlsrv_errors(), true) . "</pre>";
        continue;
    }

    $q_row = sqlsrv_fetch_array($q_stmt, SQLSRV_FETCH_ASSOC);
    if (!$q_row) {
        echo "<div style='color:orange;'>No correct answer found for Question_ID {$question_id}.</div>";
        continue;
    }

    $question_type  = trim($q_row['Question_Type']);
    $correct_answer = trim($q_row['Correct_Answer'] ?? '');
    $user_answer    = trim($user_answer);

    /* 🧠 Apply normalization for Enumeration and Fill-in questions */
    if (strcasecmp($question_type, 'Enumeration') === 0 || strcasecmp($question_type, 'Identification') === 0) {
        $normalized_user    = normalizeAnswer($user_answer);
        $normalized_correct = normalizeAnswer($correct_answer);

        // Split into words (treating order as unimportant)
        $user_items    = array_filter(explode(' ', $normalized_user));
        $correct_items = array_filter(explode(' ', $normalized_correct));

        sort($user_items);
        sort($correct_items);

        // Compare sets
        $isCorrect = (implode(' ', $user_items) === implode(' ', $correct_items)) ? 1 : 0;
    } else {
        // Other question types (e.g. Multiple Choice)
        $normalized_user    = normalizeAnswer($user_answer);
        $normalized_correct = normalizeAnswer($correct_answer);
        $isCorrect = ($normalized_user === $normalized_correct) ? 1 : 0;
    }

    if ($isCorrect) $correct++;

    // 📝 Insert answer details
    $insert_sql = "
        INSERT INTO teipiexam.dbo.AnswerDetails (Answers_ID, Question_ID, User_Answer, IsCorrect)
        VALUES (?, ?, ?, ?)
    ";
    $params = [$answers_id, $question_id, $user_answer, $isCorrect];
    $insert_stmt = sqlsrv_query($con3, $insert_sql, $params);

    if ($insert_stmt === false) {
        echo "<pre style='color:red;'>Failed to insert AnswerDetails for Question_ID {$question_id}:\n" . print_r(sqlsrv_errors(), true) . "</pre>";
    }

    sqlsrv_free_stmt($q_stmt);
}

/* 🏁 Save final result summary */
$sql = "
    INSERT INTO teipiexam.dbo.Results (Emp_ID, Exam_ID, Score, TotalQuestions, Date_Completed)
    VALUES (?, ?, ?, ?, GETDATE())
";
$params = [$emp_id, $exam_id, $correct, $total_questions];
$result_stmt = sqlsrv_query($con3, $sql, $params);

if ($result_stmt === false) {
    die("<pre style='color:red;'>Error inserting into Results:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

/* ✅ Redirect to results page */
header("Location: result.php?exam_id=$exam_id&score=$correct&total=$total_questions");
exit;
?>
