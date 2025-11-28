<?php
include "../includes/sessions.php";
include "../includes/db.php";

// Verify admin access
$emp_id = $_COOKIE['EIMS_emp_Id'] ?? '';
if (empty($emp_id)) {
    die("<div class='bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg'>Session expired. Please log in again.</div>");
}

// Handle certificate creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $cert_name = trim($_POST['cert_name'] ?? '');
        $cert_desc = trim($_POST['cert_desc'] ?? '');
        
        if (!empty($cert_name)) {
            $sql = "INSERT INTO dbo.Certificates (Certificate_Name, Certificate_Description) VALUES (?, ?)";
            $stmt = sqlsrv_query($con3, $sql, [$cert_name, $cert_desc]);
            
            if ($stmt === false) {
                $error_msg = "Error creating certificate.";
            } else {
                $success_msg = "Certificate created successfully!";
                sqlsrv_free_stmt($stmt);
            }
        } else {
            $error_msg = "Certificate name is required.";
        }
    }
    
    if ($_POST['action'] === 'assign') {
        $cert_id = intval($_POST['cert_id'] ?? 0);
        $emp_id_assign = trim($_POST['emp_id_assign'] ?? '');
        
        if ($cert_id > 0 && !empty($emp_id_assign)) {
            $sql = "INSERT INTO dbo.Employee_Certificates (Emp_ID, Certificate_ID, Date_Earned) VALUES (?, ?, GETDATE())";
            $stmt = sqlsrv_query($con3, $sql, [$emp_id_assign, $cert_id]);
            
            if ($stmt === false) {
                $error_msg = "Error assigning certificate.";
            } else {
                $success_msg = "Certificate assigned successfully!";
                sqlsrv_free_stmt($stmt);
            }
        } else {
            $error_msg = "Please select a certificate and enter employee ID.";
        }
    }
    
    if ($_POST['action'] === 'delete') {
        $cert_id = intval($_POST['cert_id'] ?? 0);
        
        if ($cert_id > 0) {
            // Delete related employee certificates first
            $sql_del_emp = "DELETE FROM dbo.Employee_Certificates WHERE Certificate_ID = ?";
            sqlsrv_query($con3, $sql_del_emp, [$cert_id]);
            
            // Delete certificate
            $sql = "DELETE FROM dbo.Certificates WHERE Certificate_ID = ?";
            $stmt = sqlsrv_query($con3, $sql, [$cert_id]);
            
            if ($stmt === false) {
                $error_msg = "Error deleting certificate.";
            } else {
                $success_msg = "Certificate deleted successfully!";
                sqlsrv_free_stmt($stmt);
            }
        }
    }
}

// Fetch all certificates
$sql_certs = "SELECT Certificate_ID, Certificate_Name, Certificate_Description FROM dbo.Certificates ORDER BY Certificate_Name";
$stmt_certs = sqlsrv_query($con3, $sql_certs);
$certificates = [];

if ($stmt_certs) {
    while ($row = sqlsrv_fetch_array($stmt_certs, SQLSRV_FETCH_ASSOC)) {
        $certificates[] = $row;
    }
    sqlsrv_free_stmt($stmt_certs);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Certificates</title>
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

    <!-- Create Certificate Form -->
    <section class="bg-white rounded-2xl shadow overflow-hidden mb-8">
        <div class="px-6 py-4 border-b bg-gradient-to-r from-indigo-50 to-blue-50">
            <h2 class="text-lg font-semibold text-slate-800">➕ Create New Certificate</h2>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Certificate Name *</label>
                    <input type="text" name="cert_name" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g., PHP Mastery">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="cert_desc" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="Certificate details..."></textarea>
                </div>
                <div class="pt-4">
                    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">Create Certificate</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Assign Certificate Form -->
    <section class="bg-white rounded-2xl shadow overflow-hidden mb-8">
        <div class="px-6 py-4 border-b bg-gradient-to-r from-emerald-50 to-teal-50">
            <h2 class="text-lg font-semibold text-slate-800">👤 Assign Certificate to Employee</h2>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="assign">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Certificate *</label>
                        <select name="cert_id" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="">-- Select Certificate --</option>
                            <?php foreach ($certificates as $cert): ?>
                                <option value="<?php echo $cert['Certificate_ID']; ?>"><?php echo htmlspecialchars($cert['Certificate_Name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Employee ID *</label>
                        <input type="text" name="emp_id_assign" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g., EMP001">
                    </div>
                </div>
                <div class="pt-4">
                    <button type="submit" class="px-6 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition">Assign Certificate</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Certificates List -->
    <section class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-slate-800">📋 All Certificates</h2>
            <p class="text-sm text-slate-500 mt-1"><?php echo count($certificates); ?> certificate(s) created</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Certificate Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-100">
                    <?php if (empty($certificates)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-slate-500">No certificates created yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($certificates as $cert): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm font-medium text-slate-800"><?php echo htmlspecialchars($cert['Certificate_Name']); ?></td>
                                <td class="px-6 py-4 text-sm text-slate-600"><?php echo htmlspecialchars($cert['Certificate_Description'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 text-center">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this certificate?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="cert_id" value="<?php echo $cert['Certificate_ID']; ?>">
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
