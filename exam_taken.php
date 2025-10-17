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

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS (Bootstrap 5 integration) -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        body {
            margin: 0;
            display: flex;
            background-color: #f4f6f9;
            font-family: Arial, sans-serif;
        }
        .card {
            border-radius: 15px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.3em 0.8em;
        }
        .dataTables_filter input {
            border-radius: 8px;
            padding: 6px 10px;
            border: 1px solid #ccc;
        }
        .dataTables_length select {
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <div style="flex:1; padding:40px;">
        <div class="card shadow p-4">
            <h2 class="mb-4 text-primary fw-bold">
                🧾 Exams You've Taken
            </h2>

            <div class="table-responsive">
                <table id="examTable" class="table table-hover align-middle table-bordered">
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
                            echo "<tr><td colspan='5' class='text-danger text-center'>Error loading exam results.</td></tr>";
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

                                $percentage = $total > 0 ? ($score / $total) * 100 : 0;
                                $status = $percentage >= 75 ? "Passed" : "Failed";
                                $statusColor = $status === "Passed" ? "success" : "danger";

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
                                    <td>
                                        <a href='{$viewUrl}' class='btn btn-sm btn-primary'>
                                            View Result
                                        </a>
                                    </td>
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
    </div>

    <!-- jQuery + DataTables + Bootstrap 5 JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#examTable').DataTable({
                pageLength: 10,
                order: [[2, 'desc']],
                language: {
                    search: "🔍 Search:",
                    lengthMenu: "Show _MENU_ exams per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ exams",
                    infoEmpty: "No exams found",
                    zeroRecords: "No matching exams found"
                }
            });
        });
    </script>

</body>
</html>
