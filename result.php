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
    <title>Exam Result - <?= $examTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="margin:0; display:flex; background-color:#f4f6f9; font-family:Arial,sans-serif;">

    <!-- Sidebar -->
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <div style="flex:1; padding:40px;">
        <div class="card shadow p-4" style="max-width:600px; margin:auto;">
            <h3 class="mb-3 text-center text-success">🧾 Exam Result</h3>

            <h4 class="text-center mb-4"><?= $examTitle ?></h4>

            <p class="fs-5 text-center"><strong>Score:</strong> <?= "{$score} / {$total}" ?></p>
            <p class="fs-5 text-center"><strong>Percentage:</strong> <?= "{$percentage}%" ?></p>
            <p class="fs-6 text-center text-muted"><strong>Date Taken:</strong> <?= $dateTaken ?></p>

            <?php if ($passed): ?>
                <div class="alert alert-success text-center">
                    🎉 Congratulations! You passed the exam.
                </div>
            <?php else: ?>
                <div class="alert alert-danger text-center">
                    💪 Don’t give up! You can try again and improve next time.
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="exams_taken.php" class="btn btn-primary px-4">⬅ Back to Exams</a>
            </div>
        </div>
    </div>

</body>
</html>
