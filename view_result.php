<?php
include "includes/sessions.php";
include "includes/db.php";

$exam_id   = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;
$date_taken = isset($_GET['date_taken']) ? $_GET['date_taken'] : '';
$emp_id    = $_COOKIE['EIMS_emp_Id'] ?? '';

if ($exam_id <= 0 || empty($emp_id) || empty($date_taken)) {
    die("<div style='color:red;'>Invalid or missing Exam ID / Employee ID / Date.</div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="container">
    <div class="card shadow p-4">
        <h2 class="mb-4 text-primary">📘 Exam Result Details</h2>

        <?php
        // 🧠 Fetch exam info
        $exam_sql = "SELECT Exam_Title, Description FROM dbo.Exams WHERE Exam_ID = ?";
        $exam_stmt = sqlsrv_query($con3, $exam_sql, [$exam_id]);

        if ($exam_stmt && ($exam = sqlsrv_fetch_array($exam_stmt, SQLSRV_FETCH_ASSOC))) {
            echo "<h4>" . htmlspecialchars($exam['Exam_Title']) . "</h4>";
            echo "<p class='text-muted'>" . htmlspecialchars($exam['Description']) . "</p>";
        } else {
            echo "<p class='text-danger'>Exam not found.</p>";
        }

        // 🧠 Find attempt - precise filter using datetime (not just date)
        $attempt_sql = "
            SELECT TOP 1 Answers_ID, Date_Taken
            FROM dbo.Answers
            WHERE Exam_ID = ? AND Emp_ID = ?
              AND ABS(DATEDIFF(SECOND, Date_Taken, ?)) <= 3  -- match within 3 seconds tolerance
            ORDER BY Date_Taken DESC
        ";

        $params = [$exam_id, $emp_id, $date_taken];
        $attempt_stmt = sqlsrv_query($con3, $attempt_sql, $params);
        $attempt = sqlsrv_fetch_array($attempt_stmt, SQLSRV_FETCH_ASSOC);

        if (!$attempt) {
            echo "<div class='text-muted'>No exact attempt found for this exam and time.</div>";
            echo "<pre>Debug info:
Exam_ID = {$exam_id}
Emp_ID = {$emp_id}
Date_Taken = {$date_taken}</pre>";
        } else {
            $answers_id = $attempt['Answers_ID'];
            $date_taken_fmt = $attempt['Date_Taken']->format('Y-m-d H:i:s');
            echo "<p><strong>Attempt Date:</strong> " . htmlspecialchars($date_taken_fmt) . "</p>";

            // 🧠 Fetch questions and answers
            $sql = "
                SELECT 
                    q.Question_ID,
                    q.Question,
                    q.Question_type,
                    q.Question_Pic,
                    q.question_image,
                    ad.User_Answer,
                    ca.Correct_answer,
                    ad.IsCorrect
                FROM dbo.AnswerDetails ad
                INNER JOIN dbo.Questions q 
                    ON ad.Question_ID = q.Question_ID
                LEFT JOIN dbo.Correct_Answers ca 
                    ON q.Question_ID = ca.Question_ID
                WHERE ad.Answers_ID = ?
                ORDER BY q.Question_ID
            ";

            $stmt = sqlsrv_query($con3, $sql, [$answers_id]);

            if ($stmt === false) {
                echo "<div class='text-danger'>Error loading results:<br><pre>" . print_r(sqlsrv_errors(), true) . "</pre></div>";
            } else {
                echo "<table class='table table-bordered mt-3 align-middle'>";
                echo "<thead class='table-dark'>
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Your Answer</th>
                            <th>Correct Answer</th>
                            <th>Image</th>
                        </tr>
                      </thead>
                      <tbody>";

                $hasRows = false;
                $i = 1;

                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $hasRows = true;
                    $question   = htmlspecialchars($row['Question'] ?? '');
                    $type       = htmlspecialchars($row['Question_type'] ?? '');
                    $userAns    = htmlspecialchars($row['User_Answer'] ?? '');
                    $correctAns = htmlspecialchars($row['Correct_answer'] ?? '');
                    $image      = $row['Question_Pic'] ?: $row['question_image'];

                    $isCorrect = (strcasecmp(trim($userAns), trim($correctAns)) === 0);
                    $answerClass = $isCorrect ? "text-success fw-bold" : "text-danger fw-bold";

                    echo "<tr>
                            <td>{$i}</td>
                            <td>{$question}</td>
                            <td>{$type}</td>
                            <td class='{$answerClass}'>{$userAns}</td>
                            <td><span class='text-primary fw-semibold'>{$correctAns}</span></td>
                            <td>";
                    if ($image) {
                        echo "<img src='uploads/" . htmlspecialchars($image) . "' alt='Question Image' style='max-width:100px; border-radius:6px;'>";
                    } else {
                        echo "<span class='text-muted'>No image</span>";
                    }
                    echo "</td></tr>";
                    $i++;
                }

                if (!$hasRows) {
                    echo "<tr><td colspan='6' class='text-center text-muted'>No answers found for this attempt.</td></tr>";
                }

                echo "</tbody></table>";
                sqlsrv_free_stmt($stmt);
            }

            sqlsrv_free_stmt($attempt_stmt);
        }

        if ($exam_stmt) sqlsrv_free_stmt($exam_stmt);
        ?>

        <div class="mt-3">
            <a href="exam_taken.php" class="btn btn-secondary">← Back to Exams Taken</a>
        </div>
    </div>
</div>

</body>
</html>
