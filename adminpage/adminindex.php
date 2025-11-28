<?php
// adminindex.php — Main Admin Dashboard (Exam List)

include "../includes/sessions.php";
include "../includes/db.php";

date_default_timezone_set('Asia/Manila');

// ---------- Handle Delete Exam ----------
if (isset($_GET['delete_exam'])) {
    $exam_id = intval($_GET['delete_exam']);
    $deleteDetails = [
        "DELETE FROM dbo.Incorrect_Answers WHERE Question_ID IN (SELECT Question_ID FROM dbo.Questions WHERE Exam_ID = ?)",
        "DELETE FROM dbo.Correct_Answers WHERE Question_ID IN (SELECT Question_ID FROM dbo.Questions WHERE Exam_ID = ?)",
        "DELETE FROM dbo.AnswerDetails WHERE Answers_ID IN (SELECT Answers_ID FROM dbo.Answers WHERE Exam_ID = ?)",
        "DELETE FROM dbo.Answers WHERE Exam_ID = ?",
        "DELETE FROM dbo.Results WHERE Exam_ID = ?",
        "DELETE FROM dbo.Questions WHERE Exam_ID = ?",
        "DELETE FROM dbo.Exams WHERE Exam_ID = ?"
    ];
    $ok = true;
    foreach ($deleteDetails as $sql) {
        $stmt = sqlsrv_query($con3, $sql, [$exam_id]);
        if ($stmt === false) {
            $ok = false;
            echo "<pre style='color:red;'>Error deleting:\n" . print_r(sqlsrv_errors(), true) . "</pre>";
            break;
        }
    }
    if ($ok) {
        echo "<script>alert('Exam deleted successfully.'); window.location='adminindex.php';</script>";
        exit;
    }
}

$messages = [];
$errors = [];

// ---------- Fetch Exams ----------
$exams = [];
$e_sql = "SELECT Exam_ID, Exam_Title, Description FROM teipiexam.dbo.Exams ORDER BY Exam_ID DESC";
$e_stmt = sqlsrv_query($con3, $e_sql);
if ($e_stmt) {
    while ($er = sqlsrv_fetch_array($e_stmt, SQLSRV_FETCH_ASSOC)) $exams[] = $er;
    sqlsrv_free_stmt($e_stmt);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — Exams</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">
<div class="max-w-7xl mx-auto px-6 py-8">
    <!-- Header -->
    <header class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Admin Dashboard</h1>
            <p class="text-sm text-slate-500">Manage exams and questions</p>
        </div>
        <nav class="flex items-center gap-2">
            <a href="adminindex.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Dashboard</a>
            <a href="patches.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Patches</a>
            <a href="certificates.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Certificates</a>
            <a href="employee_scores.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Scores</a>
            <a href="../logout.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg">Logout</a>
        </nav>
    </header>

    <!-- Messages & Errors -->
    <?php foreach ($messages as $m): ?>
    <div class="mb-4 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700">
        <p class="text-sm"><?php echo htmlspecialchars($m); ?></p>
    </div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
    <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700">
        <p class="text-sm font-medium">Error</p>
        <pre class="text-xs overflow-auto mt-1"><?php echo htmlspecialchars($err); ?></pre>
    </div>
    <?php endforeach; ?>

    <!-- Stats Card -->
    <section class="mb-8 bg-white rounded-2xl shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500">Total Exams</p>
                <p class="text-3xl font-bold text-slate-800"><?php echo count($exams); ?></p>
            </div>
            <div class="p-4 bg-indigo-50 rounded-xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="bg-white rounded-2xl shadow overflow-hidden">
        <!-- Section Header with Actions -->
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Manage Exams</h2>
            <div class="flex items-center gap-2">
                <a href="add_exam.php" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Exam
                </a>
                <a href="add_question.php" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Question
                </a>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-100">
                    <?php if (empty($exams)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-slate-500">
                            <p class="text-sm">No exams yet.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($exams as $ex): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($ex['Exam_ID']); ?></td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-800"><?php echo htmlspecialchars($ex['Exam_Title']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate"><?php echo htmlspecialchars($ex['Description']); ?></td>
                            <td class="px-6 py-4 text-right text-sm space-x-1">
                                <a href="edit_exam.php?exam_id=<?php echo urlencode($ex['Exam_ID']); ?>" class="inline-flex items-center px-3 py-1 text-xs font-medium rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200">Edit</a>
                                <a href="adminindex.php?delete_exam=<?php echo $ex['Exam_ID']; ?>" onclick="return confirm('Delete this exam and all its questions?');" class="inline-flex items-center px-3 py-1 text-xs font-medium rounded bg-red-100 text-red-700 hover:bg-red-200">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>