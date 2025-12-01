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
            <th>Linked Patches</th>
            <th>Locked</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT * FROM Exams";
        $stmt = sqlsrv_query($con3, $sql);

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // fetch linked patch names
            $patchNames = [];
            $mstmt = sqlsrv_query($con3, "IF OBJECT_ID('dbo.Exam_Patches','U') IS NULL SELECT 0 as cnt ELSE SELECT p.Patch_Name FROM dbo.Exam_Patches ep JOIN dbo.Patches p ON ep.Patch_ID = p.Patch_ID WHERE ep.Exam_ID = ?", [$row['Exam_ID']]);
            if ($mstmt) {
                while ($mpr = sqlsrv_fetch_array($mstmt, SQLSRV_FETCH_ASSOC)) {
                    if (isset($mpr['Patch_Name'])) $patchNames[] = $mpr['Patch_Name'];
                }
                sqlsrv_free_stmt($mstmt);
            }

            $patchDisplay = empty($patchNames) ? 'None' : htmlspecialchars(implode(', ', $patchNames));

            // check if exam locked
            $lockStmt = sqlsrv_query($con3, "IF OBJECT_ID('dbo.Exam_Access','U') IS NULL SELECT 0 as cnt ELSE SELECT COUNT(*) as cnt FROM dbo.Exam_Access WHERE Exam_ID = ?", [$row['Exam_ID']]);
            $locked = false;
            if ($lockStmt) {
                $lr = sqlsrv_fetch_array($lockStmt, SQLSRV_FETCH_ASSOC);
                $locked = ((int)($lr['cnt'] ?? 0)) > 0;
                sqlsrv_free_stmt($lockStmt);
            }

            $lockedText = $locked ? 'Yes' : 'No';

            echo "<tr>
                    <td>{$row['Exam_ID']}</td>
                    <td>{$row['Exam_Title']}</td>
                    <td>{$row['Description']}</td>
                    <td>{$patchDisplay}</td>
                    <td>{$lockedText}</td>
                    <td>
                        <a href='add_exam.php?edit={$row['Exam_ID']}' class='btn btn-warning btn-sm'>Edit</a>
                        <a href='delete.php?type=exam&id={$row['Exam_ID']}' class='btn btn-danger btn-sm' onclick='return confirm("Delete this exam?")'>Delete</a>
                    </td>
                </tr>";
        }
        ?>
    </tbody>
</table>

<a href="dashboard.php" class="btn btn-secondary mt-3">Back</a>

</body>
</html>
