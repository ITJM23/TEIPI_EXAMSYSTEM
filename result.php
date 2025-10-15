<?php
include "includes/sessions.php";
include "includes/db.php"; // include if sidebar needs DB connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Result</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="margin: 0; display: flex; font-family: Arial, sans-serif; background-color: #f4f6f9;">

    <!-- Sidebar -->
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <div style="flex: 1; padding: 40px;">
        <div class="card shadow p-4" style="max-width: 600px; margin: auto;">
            <h3 class="mb-3 text-center text-success">🎉 Exam Completed!</h3>
            
            <?php 
            $score = isset($_GET['score']) ? htmlspecialchars($_GET['score']) : 0;
            $total = isset($_GET['total']) ? htmlspecialchars($_GET['total']) : 0;
            $percentage = $total > 0 ? round(($score / $total) * 100, 2) : 0;
            ?>

            <p class="fs-5 text-center"><strong>Score:</strong> <?= $score . " / " . $total; ?></p>
            <p class="fs-5 text-center">
                <strong>Percentage:</strong> <?= $percentage; ?>%
            </p>

            <?php if ($percentage >= 75): ?>
                <div class="alert alert-success text-center">Congratulations! You passed the exam. 🎓</div>
            <?php else: ?>
                <div class="alert alert-danger text-center">Better luck next time! Keep practicing. 💪</div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="exam_list.php" class="btn btn-primary px-4">⬅ Back to Exams</a>
            </div>
        </div>
    </div>

</body>
</html>
