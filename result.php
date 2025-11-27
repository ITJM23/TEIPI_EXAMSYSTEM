<?php
include "includes/sessions.php";
include "includes/db.php"; // database connection

// Validate query parameters
$result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;
$exam_id   = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Get emp_id from session/cookie
$emp_id = $_COOKIE['EIMS_emp_Id'] ?? '';

if (empty($emp_id)) {
    die("<div style='color:red; padding:20px;'>Session expired or employee ID missing. Please log in again.</div>");
}

if ($result_id === 0 || $exam_id === 0) {
    die("<div style='color:red; padding:20px;'>Invalid or missing result information.</div>");
}

// Fetch result details
$sql = "
    SELECT 
        e.Exam_Title,
        r.Score,
        r.TotalQuestions,
        r.Date_Completed
    FROM dbo.Results AS r
    INNER JOIN dbo.Exams AS e ON e.Exam_ID = r.Exam_ID
    WHERE r.Result_ID = ? AND r.Exam_ID = ? AND r.Emp_ID = ?
";

$stmt = sqlsrv_query($con3, $sql, [$result_id, $exam_id, $emp_id]);

if ($stmt === false) {
    die("<div style='color:red; padding:20px;'>Error loading result.<br><pre>" . print_r(sqlsrv_errors(), true) . "</pre></div>");
}

$result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$result) {
    die("<div style='color:red; padding:20px;'>Result not found or you don’t have permission to view it.</div>");
}

// Process result data
$examTitle = htmlspecialchars($result['Exam_Title']);
$score = (int)($result['Score'] ?? 0);
$total = (int)($result['TotalQuestions'] ?? 0);
$dateTaken = ($result['Date_Completed'] instanceof DateTime)
    ? $result['Date_Completed']->format('Y-m-d H:i')
    : 'N/A';
$percentage = $total > 0 ? round(($score / $total) * 100, 2) : 0;
$passed = $percentage >= 75;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Result - <?= $examTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">

    <div class="flex">
        <!-- Sidebar -->
        <?php include "sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1">
            <div class="max-w-2xl mx-auto px-6 py-8">
                <div class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-2xl font-bold text-emerald-600">🧾 Exam Result</h3>
                    </div>
                    <div class="px-8 py-8">
                        <h4 class="text-xl font-semibold text-center text-slate-800 mb-6"><?= $examTitle ?></h4>

                        <div class="space-y-4 mb-8">
                            <div class="text-center py-4 bg-slate-50 rounded-lg">
                                <p class="text-sm text-slate-600 mb-1">Score</p>
                                <p class="text-3xl font-bold text-indigo-600"><?= "{$score} / {$total}" ?></p>
                            </div>
                            <div class="text-center py-4 bg-slate-50 rounded-lg">
                                <p class="text-sm text-slate-600 mb-1">Percentage</p>
                                <p class="text-3xl font-bold text-indigo-600"><?= "{$percentage}%" ?></p>
                            </div>
                            <p class="text-center text-sm text-slate-500"><strong>Date Taken:</strong> <?= $dateTaken ?></p>
                        </div>

                        <?php if ($passed): ?>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-center mb-6">
                                <p class="text-emerald-800 font-medium">🎉 Congratulations! You passed the exam.</p>
                            </div>
                        <?php else: ?>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center mb-6">
                                <p class="text-red-800 font-medium">💪 Don't give up! You can try again and improve next time.</p>
                            </div>
                        <?php endif; ?>

                        <div class="text-center">
                            <a href="exam_list.php" class="inline-flex items-center px-6 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">⬅ Back to Exams</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
