<?php
include "includes/sessions.php";
include "includes/db.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Exams</title>
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
                    <h1 class="text-3xl font-bold text-slate-800">📚 Available Exams</h1>
                    <p class="text-sm text-slate-500 mt-1">Take an exam to test your knowledge</p>
                </header>

                <section class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h2 class="text-lg font-semibold text-slate-800">Exam List</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Title</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-100">
                                <?php
                                $sql = "SELECT Exam_ID, Exam_Title, Description FROM teipiexam.dbo.Exams";
                                $stmt = sqlsrv_query($con3, $sql);

                                if ($stmt === false) {
                                    echo "<tr><td colspan='3' class='px-6 py-4 text-center text-red-600'>Error loading exams.</td></tr>";
                                } else {
                                    $hasRows = false;
                                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                        $hasRows = true;
                                        $examId = urlencode($row['Exam_ID']);
                                        $title = htmlspecialchars($row['Exam_Title']);
                                        $desc = htmlspecialchars($row['Description']);
                                        echo "<tr class='hover:bg-slate-50'>
                                            <td class='px-6 py-4 text-sm font-medium text-slate-800'>{$title}</td>
                                            <td class='px-6 py-4 text-sm text-slate-600 max-w-md truncate'>{$desc}</td>
                                            <td class='px-6 py-4 text-center'>
                                                <a href='take_exam.php?exam_id={$examId}' class='inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700'>Take Exam</a>
                                            </td>
                                        </tr>";
                                    }

                                    if (!$hasRows) {
                                        echo "<tr><td colspan='3' class='px-6 py-6 text-center text-slate-500'>No exams available.</td></tr>";
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
