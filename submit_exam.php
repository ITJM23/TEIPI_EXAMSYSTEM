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

// 1️⃣ Record the exam attempt in Answers table
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

// 2️⃣ Evaluate each answer and save details
$total_questions = count($answers);
$correct = 0;

foreach ($answers as $question_id => $user_answer) {
    // Fetch the correct answer + question type
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

    // Use strcasecmp for enumeration (case-insensitive)
    if (strcasecmp($question_type, 'Enumeration') === 0) {
        $isCorrect = (strcasecmp($user_answer, $correct_answer) === 0) ? 1 : 0;
    } else {
        $isCorrect = ($user_answer === $correct_answer) ? 1 : 0;
    }

    if ($isCorrect) $correct++;

    // Insert into AnswerDetails
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

// 3️⃣ Save final result summary
$sql = "
    INSERT INTO teipiexam.dbo.Results (Emp_ID, Exam_ID, Score, TotalQuestions, Date_Completed)
    VALUES (?, ?, ?, ?, GETDATE())
";
$params = [$emp_id, $exam_id, $correct, $total_questions];
$result_stmt = sqlsrv_query($con3, $sql, $params);

if ($result_stmt === false) {
    die("<pre style='color:red;'>Error inserting into Results:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

// 4️⃣ Redirect to result page
header("Location: result.php?exam_id=$exam_id&score=$correct&total=$total_questions");
exit;
?>
