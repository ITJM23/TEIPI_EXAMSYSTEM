<?php
include "../includes/sessions.php";
include "../includes/db.php";

// Verify admin access
$emp_id = $_COOKIE['EIMS_emp_Id'] ?? '';
if (empty($emp_id)) {
    die("<div class='bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg'>Session expired. Please log in again.</div>");
}

// Handle patch creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $patch_name = trim($_POST['patch_name'] ?? '');
        $patch_desc = trim($_POST['patch_desc'] ?? '');
        
        if (!empty($patch_name)) {
            $sql = "INSERT INTO dbo.Patches (Patch_Name, Patch_Description) VALUES (?, ?)";
            $stmt = sqlsrv_query($con3, $sql, [$patch_name, $patch_desc]);
            
            if ($stmt === false) {
                $error_msg = "Error creating patch.";
            } else {
                $success_msg = "Patch created successfully!";
                sqlsrv_free_stmt($stmt);
            }
        } else {
            $error_msg = "Patch name is required.";
        }
    }
    
    if ($_POST['action'] === 'assign') {
        $patch_id = intval($_POST['patch_id'] ?? 0);
        $exam_id = intval($_POST['exam_id'] ?? 0);
        
        if ($patch_id > 0 && $exam_id > 0) {
            $sql = "INSERT INTO dbo.Exam_Patches (Exam_ID, Patch_ID) VALUES (?, ?)";
            $stmt = sqlsrv_query($con3, $sql, [$exam_id, $patch_id]);
            
            if ($stmt === false) {
                $error_msg = "Error assigning patch to exam.";
            } else {
                $success_msg = "Patch assigned to exam successfully!";
                sqlsrv_free_stmt($stmt);
            }
        } else {
            $error_msg = "Please select a patch and an exam.";
        }
    }
    
    if ($_POST['action'] === 'delete') {
        $patch_id = intval($_POST['patch_id'] ?? 0);
        
        if ($patch_id > 0) {
            // Delete related exam patches first
            $sql_del_exam = "DELETE FROM dbo.Exam_Patches WHERE Patch_ID = ?";
            sqlsrv_query($con3, $sql_del_exam, [$patch_id]);
            
            // Delete patch
            $sql = "DELETE FROM dbo.Patches WHERE Patch_ID = ?";
            $stmt = sqlsrv_query($con3, $sql, [$patch_id]);
            
            if ($stmt === false) {
                $error_msg = "Error deleting patch.";
            } else {
                $success_msg = "Patch deleted successfully!";
                sqlsrv_free_stmt($stmt);
            }
        }
    }
}

// Fetch all patches
$sql_patches = "SELECT Patch_ID, Patch_Name, Patch_Description FROM dbo.Patches ORDER BY Patch_Name";
$stmt_patches = sqlsrv_query($con3, $sql_patches);
$patches = [];

if ($stmt_patches) {
    while ($row = sqlsrv_fetch_array($stmt_patches, SQLSRV_FETCH_ASSOC)) {
        $patches[] = $row;
    }
    sqlsrv_free_stmt($stmt_patches);
}

// Fetch all exams
$sql_exams = "SELECT Exam_ID, Exam_Name FROM dbo.Exams ORDER BY Exam_Name";
$stmt_exams = sqlsrv_query($con3, $sql_exams);
$exams = [];

if ($stmt_exams) {
    while ($row = sqlsrv_fetch_array($stmt_exams, SQLSRV_FETCH_ASSOC)) {
        $exams[] = $row;
    }
    sqlsrv_free_stmt($stmt_exams);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patches</title>
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

    <!-- Messages -->
    <?php if (isset($success_msg)): ?>
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg">
            ✓ <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_msg)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">
            ✗ <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <!-- Create Patch Form -->
    <section class="bg-white rounded-2xl shadow overflow-hidden mb-8">
        <div class="px-6 py-4 border-b bg-gradient-to-r from-indigo-50 to-blue-50">
            <h2 class="text-lg font-semibold text-slate-800">➕ Create New Patch</h2>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Patch Name *</label>
                    <input type="text" name="patch_name" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g., Security Update v1.0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="patch_desc" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="Patch details and changes..."></textarea>
                </div>
                <div class="pt-4">
                    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">Create Patch</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Assign Patch to Exam Form -->
    <section class="bg-white rounded-2xl shadow overflow-hidden mb-8">
        <div class="px-6 py-4 border-b bg-gradient-to-r from-emerald-50 to-teal-50">
            <h2 class="text-lg font-semibold text-slate-800">📝 Assign Patch to Exam</h2>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="assign">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Patch *</label>
                        <select name="patch_id" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="">-- Select Patch --</option>
                            <?php foreach ($patches as $patch): ?>
                                <option value="<?php echo $patch['Patch_ID']; ?>"><?php echo htmlspecialchars($patch['Patch_Name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Exam *</label>
                        <select name="exam_id" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="">-- Select Exam --</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['Exam_ID']; ?>"><?php echo htmlspecialchars($exam['Exam_Name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="pt-4">
                    <button type="submit" class="px-6 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition">Assign Patch</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Patches List -->
    <section class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-slate-800">📋 All Patches</h2>
            <p class="text-sm text-slate-500 mt-1"><?php echo count($patches); ?> patch(es) created</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Patch Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-100">
                    <?php if (empty($patches)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-slate-500">No patches created yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($patches as $patch): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm font-medium text-slate-800"><?php echo htmlspecialchars($patch['Patch_Name']); ?></td>
                                <td class="px-6 py-4 text-sm text-slate-600"><?php echo htmlspecialchars($patch['Patch_Description'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 text-center">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this patch?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="patch_id" value="<?php echo $patch['Patch_ID']; ?>">
                                        <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded transition">Delete</button>
                                    </form>
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
