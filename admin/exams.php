<?php
include "../includes/sessions.php";
include "../includes/db.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Exams</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4 bg-light">

<h2>Exams</h2>
<a href="add_exam.php" class="btn btn-primary mb-3">+ Add New Exam</a>

<table class="table table-bordered bg-white">
    <thead>
        <tr>
            <th>Exam ID</th>
            <th>Title</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT * FROM Exams";
        $stmt = sqlsrv_query($con3, $sql);

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo "<tr>
                    <td>{$row['Exam_ID']}</td>
                    <td>{$row['Exam_Title']}</td>
                    <td>{$row['Description']}</td>
                    <td>
                        <a href='add_exam.php?edit={$row['Exam_ID']}' class='btn btn-warning btn-sm'>Edit</a>
                        <a href='delete.php?type=exam&id={$row['Exam_ID']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this exam?\")'>Delete</a>
                    </td>
                </tr>";
        }
        ?>
    </tbody>
</table>

<a href="dashboard.php" class="btn btn-secondary mt-3">Back</a>

</body>
</html>
