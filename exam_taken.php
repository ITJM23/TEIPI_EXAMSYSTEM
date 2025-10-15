<?php
include "includes/sessions.php";
include "includes/db.php";

// Get emp_id from cookie
$emp_id = $_COOKIE['EIMS_emp_Id'] ?? '';

if (empty($emp_id)) {
    die("<div style='color:red; padding:20px;'>Session expired or employee ID missing. Please log in again.</div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exams Taken</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="margin:0; display:flex; background-color:#f4f6f9; font-family:Arial,sans-serif;">

    <!-- Sidebar -->
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <div style="flex:1; padding:40px;">
        <div class="card shadow p-4">
            <h2 class="mb-4 text-primary">🧾 Exams You've Taken</h2>

            <table class="table table-hover align-middle table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Exam Title</th>
                        <th>Score</th>
                        <th>Date Taken</th>
                        <th>Status</th>
                        <th style="width:160px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Query: Get all completed exams by this employee
                    $sql = "
                        SELECT 
                            e.Exam_Title, 
                            r.Score, 
                            r.TotalQuestions,
                            r.Date_Completed,
                            r.Exam_ID,
                            r.Result_ID
                        FROM dbo.Results AS r
                        INNER JOIN dbo.Exams AS e ON e.Exam_ID = r.Exam_ID
                        WHERE r.Emp_ID = ?
                        ORDER BY r.Date_Completed DESC
                    ";

                    $stmt = sqlsrv_query($con3, $sql, [$emp_id]);

                    if ($stmt === false) {
                        echo "<tr><td colspan='5' class='text-danger'>Error loading exam results:<br><pre>" . print_r(sqlsrv_errors(), true) . "</pre></td></tr>";
                    } else {
                        $hasRows = false;

                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $hasRows = true;

                            $examTitle = htmlspecialchars($row['Exam_Title']);
                            $score = (int)($row['Score'] ?? 0);
                            $total = (int)($row['TotalQuestions'] ?? 0);
                            $dateTaken = ($row['Date_Completed'] instanceof DateTime)
                                ? $row['Date_Completed']->format('Y-m-d H:i')
                                : 'N/A';

                            // Compute status
                            $percentage = $total > 0 ? ($score / $total) * 100 : 0;
                            $status = $percentage >= 75 ? "Passed" : "Failed";
                            $statusColor = $status === "Passed" ? "success" : "danger";

                            // Build clean link
                            $viewUrl = "view_result.php?" . http_build_query([
                                'exam_id'    => $row['Exam_ID'],
                                'result_id'  => $row['Result_ID'],
                                'date_taken' => ($row['Date_Completed'] instanceof DateTime)
                                    ? $row['Date_Completed']->format('Y-m-d H:i:s')
                                    : ''
                            ]);

                            echo "
                            <tr>
                                <td>{$examTitle}</td>
                                <td>{$score} / {$total}</td>
                                <td>{$dateTaken}</td>
                                <td><span class='badge bg-{$statusColor}'>{$status}</span></td>
                                <td><a href='{$viewUrl}' class='btn btn-sm btn-primary'>View Result</a></td>
                            </tr>";
                        }

                        if (!$hasRows) {
                            echo "<tr><td colspan='5' class='text-center text-muted'>You haven’t taken any exams yet.</td></tr>";
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
