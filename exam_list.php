<?php
include "includes/sessions.php";
include "includes/db.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="margin: 0; display: flex; font-family: Arial, sans-serif; background-color: #f4f6f9;">

    <!-- Sidebar -->
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <div style="flex: 1; padding: 40px;">
        <div class="card shadow p-4">
            <h2 class="mb-4 text-primary">📚 Available Exams</h2>

            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Exam Title</th>
                        <th>Description</th>
                        <th style="width: 150px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT Exam_ID, Exam_Title, Description FROM teipiexam.dbo.Exams";
                    $stmt = sqlsrv_query($con3, $sql);

                    if ($stmt === false) {
                        echo "<tr><td colspan='3' class='text-danger'>Error loading exams.</td></tr>";
                    } else {
                        $hasRows = false;
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $hasRows = true;
                            echo "<tr>
                                    <td>" . htmlspecialchars($row['Exam_Title']) . "</td>
                                    <td>" . htmlspecialchars($row['Description']) . "</td>
                                    <td>
                                        <a href='take_exam.php?exam_id=" . urlencode($row['Exam_ID']) . "' class='btn btn-sm btn-success'>Take Exam</a>
                                    </td>
                                </tr>";
                        }

                        if (!$hasRows) {
                            echo "<tr><td colspan='3' class='text-center text-muted'>No exams available.</td></tr>";
                        }
                        sqlsrv_free_stmt($stmt);
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
