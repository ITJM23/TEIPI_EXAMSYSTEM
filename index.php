<?php
include "includes/sessions.php";
include "includes/db.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">User Dashboard</h1>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">Logout</a>
        </div>

        <!-- Welcome Card -->
        <div class="bg-white p-6 rounded-xl shadow-md mb-8">
            <h2 class="text-xl font-semibold text-gray-700 mb-2">
                Welcome back, <?php echo htmlspecialchars($username); ?>!
            </h2>
            <p class="text-gray-500">
                Your Employee ID:
                <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($emp_id); ?></span>
            </p>
            <p class="text-gray-500">
                User Level:
                <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($user_lvl); ?></span>
            </p>
        </div>

        <!-- Dashboard Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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

            <div class="bg-blue-500 text-white p-6 rounded-xl shadow-md">
                <h3 class="text-lg font-semibold mb-1">Total Exams</h3>
                <p class="text-3xl font-bold"><?php echo $total_exams; ?></p>
            </div>

            <div class="bg-green-500 text-white p-6 rounded-xl shadow-md">
                <h3 class="text-lg font-semibold mb-1">Completed Exams</h3>
                <p class="text-3xl font-bold"><?php echo $completed_exams; ?></p>
            </div>

            <div class="bg-yellow-500 text-white p-6 rounded-xl shadow-md">
                <h3 class="text-lg font-semibold mb-1">Average Score</h3>
                <p class="text-3xl font-bold"><?php echo $avg_score; ?>%</p>
            </div>

            <div class="bg-purple-500 text-white p-6 rounded-xl shadow-md">
                <h3 class="text-lg font-semibold mb-1">Pending Exams</h3>
                <p class="text-3xl font-bold"><?php echo max(0, $total_exams - $completed_exams); ?></p>
            </div>
        </div>

        <!-- Available Exams -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Available Exams</h2>
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-200 text-gray-700 text-left">
                        <th class="p-3 border-b">Exam Title</th>
                        <th class="p-3 border-b">Description</th>
                        <th class="p-3 border-b">Date Created</th>
                        <th class="p-3 border-b text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Fetch available exams (not yet taken)
                $query_exams = "
                    SELECT e.Exam_ID, e.Exam_Title, e.Description, e.Date_Created
                    FROM dbo.Exams e
                    WHERE e.Exam_ID NOT IN (SELECT Exam_ID FROM dbo.Results WHERE emp_id = ?)
                    ORDER BY e.Date_Created DESC
                ";

                $stmt_exams = sqlsrv_query($con3, $query_exams, [$emp_id]);

                if ($stmt_exams === false) {
                    echo "<tr><td colspan='4' class='p-3 text-red-600'>Error fetching exams: " . print_r(sqlsrv_errors(), true) . "</td></tr>";
                } else {
                    $hasExams = false;
                    while ($exam = sqlsrv_fetch_array($stmt_exams, SQLSRV_FETCH_ASSOC)) {
                        $hasExams = true;
                        echo "<tr class='hover:bg-gray-50'>
                                <td class='p-3 border-b text-gray-700'>" . htmlspecialchars($exam['Exam_Title']) . "</td>
                                <td class='p-3 border-b text-gray-500'>" . htmlspecialchars($exam['Description']) . "</td>
                                <td class='p-3 border-b text-gray-500'>" . 
                                    ($exam['Date_Created'] ? $exam['Date_Created']->format('Y-m-d') : 'N/A') . 
                                "</td>
                                <td class='p-3 border-b text-center'>
                                    <a href='take_exam.php?exam_id=" . urlencode($exam['Exam_ID']) . "' 
                                       class='bg-blue-500 hover:bg-blue-600 text-white px-4 py-1 rounded-lg text-sm'>
                                       Take Exam
                                    </a>
                                </td>
                              </tr>";
                    }

                    if (!$hasExams) {
                        echo "<tr><td colspan='4' class='p-4 text-center text-gray-500'>
                              No available exams at the moment.
                              </td></tr>";
                    }
                }
                ?>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <footer class="mt-8 text-center text-sm text-gray-500">
            © <?php echo date("Y"); ?> IS TEAM | Credit sa mga gwapong IS TEAM
        </footer>
    </div>
</body>
</html>
