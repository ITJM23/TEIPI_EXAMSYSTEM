<?php
include "includes/sessions.php";
include "includes/db.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>User Dashboard</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            /* small helper to ensure table cells wrap nicely */
            .truncate-2 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <?php include "sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1">
            <div class="max-w-7xl mx-auto px-6 py-8">
                <header class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800">Dashboard</h1>
                        <p class="text-sm text-slate-500">Welcome back, <?php echo htmlspecialchars($username); ?> • Employee ID: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($emp_id); ?></span></p>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="../logout.php" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                            <!-- logout icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 10a1 1 0 011-1h8a1 1 0 110 2H4a1 1 0 01-1-1z"/></svg>
                            Logout
                        </a>
                    </div>
                </header>

                <!-- Stats -->
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <?php
                    $query_total_exams = "SELECT COUNT(*) AS total_exams FROM dbo.Exams";
                    $query_completed = "SELECT COUNT(*) AS completed_exams FROM dbo.Results WHERE emp_id = ?";
                    $query_average = "SELECT AVG(score) AS avg_score FROM dbo.Results WHERE emp_id = ?";

                    $total_exams = 0;
                    $completed_exams = 0;
                    $avg_score = 0;

                    $stmt1 = sqlsrv_query($con3, $query_total_exams);
                    if ($stmt1 && ($row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC))) {
                            $total_exams = $row['total_exams'];
                    }

                    $stmt2 = sqlsrv_query($con3, $query_completed, [$emp_id]);
                    if ($stmt2 && ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC))) {
                            $completed_exams = $row['completed_exams'];
                    }

                    $stmt3 = sqlsrv_query($con3, $query_average, [$emp_id]);
                    if ($stmt3 && ($row = sqlsrv_fetch_array($stmt3, SQLSRV_FETCH_ASSOC))) {
                            $avg_score = $row['avg_score'] ? round($row['avg_score'], 2) : 0;
                    }
                    ?>

                    <div class="bg-white rounded-2xl p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-slate-500">Total Exams</p>
                                <p class="mt-1 text-2xl font-bold text-slate-800"><?php echo $total_exams; ?></p>
                            </div>
                            <div class="p-3 bg-indigo-50 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h2a2 2 0 012 2v6m-6 0h6"/></svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-slate-500">Completed</p>
                                <p class="mt-1 text-2xl font-bold text-slate-800"><?php echo $completed_exams; ?></p>
                            </div>
                            <div class="p-3 bg-green-50 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-slate-500">Average Score</p>
                                <p class="mt-1 text-2xl font-bold text-slate-800"><?php echo $avg_score; ?>%</p>
                            </div>
                            <div class="p-3 bg-yellow-50 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927C9.34 2.21 10.66 2.21 10.951 2.927l1.286 3.471a1 1 0 00.95.69h3.646c.969 0 1.371 1.24.588 1.81l-2.953 2.15a1 1 0 00-.364 1.118l1.286 3.471c.291.717-.588 1.31-1.199.81L10 15.347l-2.49 1.83c-.611.5-1.49-.093-1.199-.81l1.286-3.471a1 1 0 00-.364-1.118L4.28 8.898c-.783-.57-.38-1.81.588-1.81h3.646a1 1 0 00.95-.69l1.286-3.471z"/></svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-slate-500">Pending</p>
                                <p class="mt-1 text-2xl font-bold text-slate-800"><?php echo max(0, $total_exams - $completed_exams); ?></p>
                            </div>
                            <div class="p-3 bg-purple-50 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 100 12A6 6 0 0010 2z"/></svg>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Available Exams -->
                <section class="bg-white rounded-2xl shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-800">Available Exams</h2>
                        <span class="text-sm text-slate-500">Latest exams available for you</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Title</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-100">
                                <?php
                                $query_exams = "
                                        SELECT e.Exam_ID, e.Exam_Title, e.Description, e.Date_Created
                                        FROM dbo.Exams e
                                        WHERE e.Exam_ID NOT IN (SELECT Exam_ID FROM dbo.Results WHERE emp_id = ?)
                                        ORDER BY e.Date_Created DESC
                                ";

                                $stmt_exams = sqlsrv_query($con3, $query_exams, [$emp_id]);

                                if ($stmt_exams === false) {
                                        echo "<tr><td colspan='4' class='px-4 py-6 text-red-600'>Error fetching exams: " . htmlspecialchars(print_r(sqlsrv_errors(), true)) . "</td></tr>";
                                } else {
                                        $hasExams = false;
                                        while ($exam = sqlsrv_fetch_array($stmt_exams, SQLSRV_FETCH_ASSOC)) {
                                                $hasExams = true;
                                                $title = htmlspecialchars($exam['Exam_Title']);
                                                $desc = htmlspecialchars($exam['Description']);
                                                $date = $exam['Date_Created'] ? $exam['Date_Created']->format('Y-m-d') : 'N/A';
                                                $id = urlencode($exam['Exam_ID']);

                                                echo "<tr class='hover:bg-slate-50'>
                                                                <td class='px-4 py-4 text-slate-700'>" . $title . "</td>
                                                                <td class='px-4 py-4 text-slate-500 truncate-2'>" . $desc . "</td>
                                                                <td class='px-4 py-4 text-slate-500'>" . $date . "</td>
                                                                <td class='px-4 py-4 text-center'>
                                                                    <a href='take_exam.php?exam_id=" . $id . "' class='inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm'>
                                                                        <svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-4 w-4\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 6v6l4 2\"/></svg>
                                                                        Take
                                                                    </a>
                                                                </td>
                                                            </tr>";
                                        }

                                        if (!$hasExams) {
                                                echo "<tr><td colspan='4' class='px-4 py-6 text-center text-slate-500'>No available exams at the moment.</td></tr>";
                                        }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Footer -->
                <footer class="mt-8 text-center text-sm text-slate-500">
                        © <?php echo date("Y"); ?> IS TEAM | Credit sa mga gwapong IS TEAM
                </footer>
            </div>
        </main>
    </div>
</body>
</html>
