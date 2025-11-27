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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">

<div class="flex">
    <!-- Sidebar -->
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <main class="flex-1">
        <div class="max-w-6xl mx-auto px-6 py-8">
            <div class="bg-white rounded-2xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h2 class="text-2xl font-bold text-indigo-600">📘 Exam Result Details</h2>
                </div>
                <div class="px-8 py-8">

        <?php
        // 🧠 Fetch exam info
        $exam_sql = "SELECT Exam_Title, Description FROM dbo.Exams WHERE Exam_ID = ?";
        $exam_stmt = sqlsrv_query($con3, $exam_sql, [$exam_id]);

        if ($exam_stmt && ($exam = sqlsrv_fetch_array($exam_stmt, SQLSRV_FETCH_ASSOC))) {
            echo "<h4 class='text-lg font-semibold text-slate-800'>" . htmlspecialchars($exam['Exam_Title']) . "</h4>";
            echo "<p class='text-sm text-slate-600 mt-2'>" . htmlspecialchars($exam['Description']) . "</p>";
        } else {
            echo "<p class='text-red-600'>Exam not found.</p>";
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
            echo "<div class='text-slate-500 mt-4'>No exact attempt found for this exam and time.</div>";
        } else {
            $answers_id = $attempt['Answers_ID'];
            $date_taken_fmt = $attempt['Date_Taken']->format('Y-m-d H:i:s');
            echo "<p class='mt-4 text-sm'><strong>Attempt Date:</strong> " . htmlspecialchars($date_taken_fmt) . "</p>";

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
                echo "<div class='bg-red-50 border border-red-200 rounded p-4 text-red-700 mt-4'>Error loading results:<br><pre>" . print_r(sqlsrv_errors(), true) . "</pre></div>";
            } else {
                echo "<div class='mt-6 overflow-x-auto'>
                        <table class='min-w-full divide-y divide-slate-200'>
                        <thead class='bg-slate-50'>
                            <tr>
                                <th class='px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider'>#</th>
                                <th class='px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider'>Question</th>
                                <th class='px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider'>Type</th>
                                <th class='px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider'>Your Answer</th>
                                <th class='px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider'>Correct Answer(s)</th>
                                <th class='px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider'>Score</th>
                                <th class='px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider'>Image</th>
                            </tr>
                        </thead>
                        <tbody class='bg-white divide-y divide-slate-100'>";

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
                        $resultText = "<span class='".($isCorrect ? "text-emerald-600 font-bold" : "text-red-600 font-bold")."'>{$matched} / {$total} correct</span>";

                        $userAnsDisplay = htmlspecialchars($userAns);
                        $correctAnsDisplay = htmlspecialchars(implode(", ", $corrects));
                    } else {
                        // Simple match
                        $userNorm = normalizeText($userAns);
                        $correctNorms = array_map('normalizeText', $corrects);
                        $isCorrect = in_array($userNorm, $correctNorms);
                        $resultText = $isCorrect ? "<span class='text-emerald-600 font-bold'>Correct</span>" : "<span class='text-red-600 font-bold'>Wrong</span>";

                        $userAnsDisplay = htmlspecialchars($userAns);
                        $correctAnsDisplay = htmlspecialchars(implode(", ", $corrects));
                    }

                    echo "<tr class='hover:bg-slate-50'>
                            <td class='px-6 py-4 text-sm font-medium text-slate-800'>{$i}</td>
                            <td class='px-6 py-4 text-sm text-slate-700'>{$question}</td>
                            <td class='px-6 py-4 text-sm text-slate-600'>{$type}</td>
                            <td class='px-6 py-4 text-sm text-slate-700'>{$userAnsDisplay}</td>
                            <td class='px-6 py-4 text-sm font-semibold text-indigo-600'>{$correctAnsDisplay}</td>
                            <td class='px-6 py-4 text-sm'>{$resultText}</td>
                            <td class='px-6 py-4 text-sm'>";
                    if ($image) {
                        echo "<img src='uploads/" . htmlspecialchars($image) . "' alt='Question Image' class='max-w-[100px] rounded'>";
                    } else {
                        echo "<span class='text-slate-500'>No image</span>";
                    }
                    echo "</td></tr>";

                    $i++;
                }

                if (!$hasRows) {
                    echo "<tr><td colspan='7' class='px-6 py-4 text-center text-slate-500'>No answers found for this attempt.</td></tr>";
                }

                echo "</tbody></table></div>";
                sqlsrv_free_stmt($stmt);
            }
        }

        if ($exam_stmt) sqlsrv_free_stmt($exam_stmt);
        ?>

                    <div class="mt-6 text-center">
                        <a href="exam_taken.php" class="inline-flex items-center px-6 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-700 transition">← Back to Exams Taken</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
