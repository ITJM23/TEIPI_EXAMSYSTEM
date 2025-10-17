<?php
include "includes/sessions.php";
include "includes/db.php";

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($exam_id <= 0) {
    die("<div style='color:red; padding:20px;'>Invalid or missing Exam ID.</div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Exam</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4 bg-light">

<div class="container">
    <div class="card shadow p-4">
        <h3 class="mb-4 text-primary">🧠 Taking Exam #<?php echo htmlspecialchars($exam_id); ?></h3>

        <form action="submit_exam.php" method="POST">
            <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($exam_id); ?>">

            <?php
            // Fetch questions for this exam
            $sql = "SELECT * FROM teipiexam.dbo.Questions WHERE Exam_ID = ?";
            $params = [$exam_id];
            $stmt = sqlsrv_query($con3, $sql, $params);

            if ($stmt === false) {
                echo "<div class='alert alert-danger'>Error loading questions.<br><pre>" . print_r(sqlsrv_errors(), true) . "</pre></div>";
            } else {
                $qnum = 1;
                $hasQuestions = false;

                while ($q = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $hasQuestions = true;
                    $qid = $q['Question_ID'];
                    $question_text = htmlspecialchars($q['Question']);
                    $question_type = strtolower(trim($q['Question_type'] ?? ''));

                    echo "<div class='mb-4 p-3 border rounded bg-white'>";
                    echo "<h5>{$qnum}. {$question_text}</h5>";
                    echo "<input type='hidden' name='question_id[]' value='{$qid}'>";

                    // Display image if available
                    if (!empty($q['Question_Pic']) || !empty($q['question_image'])) {
                        $img = htmlspecialchars($q['Question_Pic'] ?: $q['question_image']);
                        echo "
                            <div class='text-center my-3'>
                                <img src='uploads/{$img}' 
                                    alt='Question Image' 
                                    class='img-fluid border rounded shadow-sm' 
                                    style='max-width:600px; height:auto;'>
                            </div>
                        ";
                    }

                    // Multiple Choice
                    if ($question_type === 'multiple_choice') {
                        $choices = [];

                        // Get incorrect answers
                        $sqlChoices = "SELECT Incorrect_answer FROM teipiexam.dbo.Incorrect_Answers WHERE Question_ID = ?";
                        $stmtC = sqlsrv_query($con3, $sqlChoices, [$qid]);
                        while ($c = sqlsrv_fetch_array($stmtC, SQLSRV_FETCH_ASSOC)) {
                            $choices[] = $c['Incorrect_answer'];
                        }

                        // Get correct answer
                        $sqlCorrect = "SELECT Correct_answer FROM teipiexam.dbo.Correct_Answers WHERE Question_ID = ?";
                        $stmtCorr = sqlsrv_query($con3, $sqlCorrect, [$qid]);
                        while ($corr = sqlsrv_fetch_array($stmtCorr, SQLSRV_FETCH_ASSOC)) {
                            if (!empty($corr['Correct_answer'])) {
                                $choices[] = $corr['Correct_answer'];
                            }
                        }

                        shuffle($choices);

                        foreach ($choices as $opt) {
                            $optSafe = htmlspecialchars($opt);
                            echo "<div><input type='radio' name='answer[{$qid}]' value='{$optSafe}' required> {$optSafe}</div>";
                        }
                    }

                    // True or False
                    elseif ($question_type === 'true_false') {
                        echo "<div><input type='radio' name='answer[{$qid}]' value='True' required> True</div>";
                        echo "<div><input type='radio' name='answer[{$qid}]' value='False' required> False</div>";
                    }

                    // Enumeration
                    elseif ($question_type === 'enumeration') {
                        // Get how many correct answers are defined for this question
                        $sqlEnumCount = "SELECT COUNT(*) AS AnswerCount FROM teipiexam.dbo.Correct_Answers WHERE Question_ID = ?";
                        $stmtEnum = sqlsrv_query($con3, $sqlEnumCount, [$qid]);
                        $rowEnum = sqlsrv_fetch_array($stmtEnum, SQLSRV_FETCH_ASSOC);
                        $count = intval($rowEnum['AnswerCount'] ?? 1);
                        if ($count < 1) $count = 1;

                        echo "<div class='mt-2'>";
                        echo "<label class='form-label'>Provide {$count} answer(s):</label>";
                        for ($i = 1; $i <= $count; $i++) {
                            echo "<input type='text' class='form-control mb-2' name='answer[{$qid}][]' placeholder='Answer #{$i}' required>";
                        }
                        echo "</div>";
                    }

                    else {
                        echo "<div class='text-danger'>⚠ Unknown question type: {$question_type}</div>";
                    }

                    echo "</div>";
                    $qnum++;
                }

                if (!$hasQuestions) {
                    echo "<div class='alert alert-warning'>No questions found for this exam.</div>";
                }

                sqlsrv_free_stmt($stmt);
            }
            ?>

            <div class="mt-4">
                <button type="submit" class="btn btn-success btn-lg">✅ Submit Exam</button>
                <a href="exam_taken.php" class="btn btn-secondary btn-lg">← Back</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
