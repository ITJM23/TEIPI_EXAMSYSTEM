<?php
include "../includes/sessions.php";
include "../includes/db.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Questions</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4 bg-light">

<h2>Questions</h2>
<a href="add_question.php" class="btn btn-primary mb-3">+ Add Question</a>

<table class="table table-bordered bg-white">
    <thead>
        <tr>
            <th>Question ID</th>
            <th>Exam</th>
            <th>Question</th>
            <th>Type</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT q.*, e.Exam_Title FROM Questions q
                LEFT JOIN Exams e ON q.Exam_ID = e.Exam_ID";
        $stmt = sqlsrv_query($con3, $sql);

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo "<tr>
                    <td>{$row['Question_ID']}</td>
                    <td>{$row['Exam_Title']}</td>
                    <td>{$row['Question']}</td>
                    <td>{$row['Question_type']}</td>
                    <td>
                        <a href='add_question.php?edit={$row['Question_ID']}' class='btn btn-warning btn-sm'>Edit</a>
                        <a href='delete.php?type=question&id={$row['Question_ID']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this question?\")'>Delete</a>
                    </td>
                </tr>";
        }
        ?>
    </tbody>
</table>

<a href="dashboard.php" class="btn btn-secondary mt-3">Back</a>

</body>
</html>
