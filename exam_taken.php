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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exam Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">

    <div class="flex">
        <!-- Sidebar -->
        <?php include "sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1">
            <div class="max-w-6xl mx-auto px-6 py-8">
                <header class="mb-8">
                    <h1 class="text-3xl font-bold text-slate-800">🧾 My Exam Results</h1>
                    <p class="text-sm text-slate-500 mt-1">Review your completed exams and scores</p>
                </header>

                <section class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h2 class="text-lg font-semibold text-slate-800">Exams You've Taken</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Exam Title</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date Taken</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-100">
                        <?php
                        $sql = "
                            SELECT 
                                e.Exam_Title, 
                                r.Score, 
                                r.TotalQuestions,
                                r.Date_Completed,
                                r.Exam_ID,
                                r.Result_ID,
                                ISNULL(es.PassingRate, 75) AS PassingRate
                            FROM dbo.Results AS r
                            INNER JOIN dbo.Exams AS e ON e.Exam_ID = r.Exam_ID
                            LEFT JOIN dbo.Exam_Settings es ON r.Exam_ID = es.Exam_ID
                            WHERE r.Emp_ID = ?
                            ORDER BY r.Date_Completed DESC
                        ";

                        $stmt = sqlsrv_query($con3, $sql, [$emp_id]);

                        if ($stmt === false) {
                            echo "<tr><td colspan='5' class='px-6 py-4 text-center text-red-600'>Error loading exam results.</td></tr>";
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
                                $passingRateRow = isset($row['PassingRate']) ? intval($row['PassingRate']) : 75;
                                $status = $percentage >= $passingRateRow ? "Passed" : "Failed";
                                $statusBgColor = $status === "Passed" ? "emerald-100" : "red-100";
                                $statusTextColor = $status === "Passed" ? "emerald-700" : "red-700";

                                $viewUrl = "view_result.php?" . http_build_query([
                                    'exam_id'    => $row['Exam_ID'],
                                    'result_id'  => $row['Result_ID'],
                                    'date_taken' => ($row['Date_Completed'] instanceof DateTime)
                                        ? $row['Date_Completed']->format('Y-m-d H:i:s')
                                        : ''
                                ]);

                                echo "
                                <tr class='hover:bg-slate-50 transition'>
                                    <td class='px-6 py-4 text-sm font-medium text-slate-800'>{$examTitle}</td>
                                    <td class='px-6 py-4 text-sm text-slate-600 text-center'><span class='font-semibold'>{$score}</span> / {$total}<br><span class='text-xs text-slate-500'>Pass: {$passingRateRow}%</span></td>
                                    <td class='px-6 py-4 text-sm text-slate-600'>{$dateTaken}</td>
                                    <td class='px-6 py-4 text-center'>
                                        <span class='inline-block px-3 py-1 text-xs font-medium rounded-full bg-{$statusBgColor} text-{$statusTextColor}'>{$status}</span>
                                    </td>
                                    <td class='px-6 py-4 text-center'>
                                        <a href='{$viewUrl}' class='inline-flex items-center px-3 py-2 text-xs font-medium text-indigo-700 bg-indigo-100 rounded hover:bg-indigo-200 transition'>
                                            View Result
                                        </a>
                                    </td>
                                </tr>";
                            }

                            if (!$hasRows) {
                                echo "<tr><td colspan='5' class='px-6 py-4 text-center text-slate-500'>You haven't taken any exams yet.</td></tr>";
                            }

                            sqlsrv_free_stmt($stmt);
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
            </div>
        </main>
    </div>

</body>
</html>
