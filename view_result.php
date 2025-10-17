<?php
include "includes/sessions.php";
include "includes/db.php";

$exam_id   = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$date_taken = isset($_GET['date_taken']) ? $_GET['date_taken'] : '';
$emp_id    = $_COOKIE['EIMS_emp_Id'] ?? '';

if ($exam_id <= 0 || empty($emp_id) || empty($date_taken)) {
    die("<div style='color:red;'>Invalid or missing Exam ID / Employee ID / Date.</div>");
}

// Normalization helper
function normalizeText($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[.,;:()\-]/u', ' ', $text);
    $text = preg_replace('/[^a-z0-9\s]/u', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
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

        // 🧠 Find attempt (date filter)
        $attempt_sql = "
            SELECT TOP 1 Answers_ID, Date_Taken
            FROM dbo.Answers
            WHERE Exam_ID = ? AND Emp_ID = ?
              AND ABS(DATEDIFF(SECOND, Date_Taken, ?)) <= 3
            ORDER BY Date_Taken DESC
        ";
        $params = [$exam_id, $emp_id, $date_taken];
        $attempt_stmt = sqlsrv_query($con3, $attempt_sql, $params);
        $attempt = sqlsrv_fetch_array($attempt_stmt, SQLSRV_FETCH_ASSOC);

        if (!$attempt) {
            echo "<div class='text-muted'>No exact attempt found for this exam and time.</div>";
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
                    ad.IsCorrect
                FROM dbo.AnswerDetails ad
                INNER JOIN dbo.Questions q 
                    ON ad.Question_ID = q.Question_ID
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
                            <th>Correct Answer(s)</th>
                            <th>Score</th>
                            <th>Image</th>
                        </tr>
                      </thead>
                      <tbody>";

                $i = 1;
                $hasRows = false;

                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $hasRows = true;
                    $qid = $row['Question_ID'];
                    $question = htmlspecialchars($row['Question'] ?? '');
                    $type = htmlspecialchars($row['Question_type'] ?? '');
                    $userAns = trim($row['User_Answer'] ?? '');
                    $image = $row['Question_Pic'] ?: $row['question_image'];

                    // Fetch all correct answers
                    $correct_sql = "SELECT Correct_Answer FROM dbo.Correct_Answers WHERE Question_ID = ?";
                    $corr_stmt = sqlsrv_query($con3, $correct_sql, [$qid]);
                    $corrects = [];
                    while ($cr = sqlsrv_fetch_array($corr_stmt, SQLSRV_FETCH_ASSOC)) {
                        $corrects[] = trim($cr['Correct_Answer']);
                    }
                    sqlsrv_free_stmt($corr_stmt);

                    // Prepare comparison display
                    $normalized_corrects = array_map('normalizeText', $corrects);
                    $normalized_user = normalizeText($userAns);
                    $isCorrect = false;
                    $resultText = "";

                    if (strcasecmp($type, 'Enumeration') === 0) {
                        // Split answers
                        $user_items = preg_split('/[,;\n]+/', $userAns);
                        $user_items = array_filter(array_map('normalizeText', $user_items));
                        $correct_items = array_filter(array_map('normalizeText', $corrects));

                        $matched = 0;
                        foreach ($correct_items as $ca) {
                            foreach ($user_items as $ui) {
                                if ($ui === $ca || strpos($ui, $ca) !== false || strpos($ca, $ui) !== false) {
                                    $matched++;
                                    break;
                                }
                            }
                        }

                        $total = count($correct_items);
                        $isCorrect = ($matched === $total);
                        $resultText = "<span class='".($isCorrect ? "text-success" : "text-danger")." fw-bold'>{$matched} / {$total} correct</span>";

                        $userAnsDisplay = htmlspecialchars($userAns);
                        $correctAnsDisplay = htmlspecialchars(implode(", ", $corrects));
                    } else {
                        // Simple match
                        $userNorm = normalizeText($userAns);
                        $correctNorms = array_map('normalizeText', $corrects);
                        $isCorrect = in_array($userNorm, $correctNorms);
                        $resultText = $isCorrect ? "<span class='text-success fw-bold'>Correct</span>" : "<span class='text-danger fw-bold'>Wrong</span>";

                        $userAnsDisplay = htmlspecialchars($userAns);
                        $correctAnsDisplay = htmlspecialchars(implode(", ", $corrects));
                    }

                    echo "<tr>
                            <td>{$i}</td>
                            <td>{$question}</td>
                            <td>{$type}</td>
                            <td>{$userAnsDisplay}</td>
                            <td class='text-primary fw-semibold'>{$correctAnsDisplay}</td>
                            <td>{$resultText}</td>
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
                    echo "<tr><td colspan='7' class='text-center text-muted'>No answers found for this attempt.</td></tr>";
                }

                echo "</tbody></table>";
                sqlsrv_free_stmt($stmt);
            }
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
