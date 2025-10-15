<?php
include "../includes/sessions.php";
include "../includes/db.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4 bg-light">

<h2>Admin Dashboard</h2>
<hr>
<a href="exams.php" class="btn btn-primary me-2">Manage Exams</a>
<a href="questions.php" class="btn btn-secondary">Manage Questions</a>
<a href="../exam_list.php" class="btn btn-success float-end">Go to User Exams</a>

</body>
</html>
