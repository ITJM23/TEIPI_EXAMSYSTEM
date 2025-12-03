<?php
include "../includes/sessions.php";
include "../includes/db.php";

// Verify admin access
$emp_id = $_COOKIE['EIMS_emp_Id'] ?? '';
if (empty($emp_id)) {
    die("<div class='bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg'>Session expired. Please log in again.</div>");
}

// Fetch employees from emp_info (used in sidebar)
$employees = [];
$emp_sql = "SELECT Emp_Id AS Emp_ID, Fname, Lname FROM teipi_emp3.teipi_emp3.emp_info ORDER BY Fname, Lname";
$stmt_emp = @sqlsrv_query($con3, $emp_sql);
if ($stmt_emp) {
  while ($r = sqlsrv_fetch_array($stmt_emp, SQLSRV_FETCH_ASSOC)) {
    // build a display name
    $r['Emp_Name'] = trim(($r['Fname'] ?? '') . ' ' . ($r['Lname'] ?? '')) ?: ($r['Emp_ID'] ?? '');
    $employees[] = $r;
  }
  sqlsrv_free_stmt($stmt_emp);
}

// Helper to get earned patches for an employee
function getEmployeePatches($con, $emp) {
    $out = [];
    $sql = "IF OBJECT_ID('dbo.Employee_Patches','U') IS NULL SELECT 0 as cnt ELSE SELECT ep.Patch_ID, p.Patch_Name, ep.Date_Earned FROM dbo.Employee_Patches ep JOIN dbo.Patches p ON ep.Patch_ID = p.Patch_ID WHERE ep.Emp_ID = ? ORDER BY ep.Date_Earned DESC";
    $stmt = @sqlsrv_query($con, $sql, [$emp]);
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (isset($r['Patch_ID'])) $out[] = $r;
        }
        sqlsrv_free_stmt($stmt);
    }
    return $out;
}

// Helper to get earned certificates for an employee
function getEmployeeCertificates($con, $emp) {
    $out = [];
    $sql = "IF OBJECT_ID('dbo.Employee_Certificates','U') IS NULL SELECT 0 as cnt ELSE SELECT ec.Certificate_ID, c.Certificate_Name, ec.Date_Earned FROM dbo.Employee_Certificates ec JOIN dbo.Certificates c ON ec.Certificate_ID = c.Certificate_ID WHERE ec.Emp_ID = ? ORDER BY ec.Date_Earned DESC";
    $stmt = @sqlsrv_query($con, $sql, [$emp]);
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (isset($r['Certificate_ID'])) $out[] = $r;
        }
        sqlsrv_free_stmt($stmt);
    }
    return $out;
}

// Render page
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Employee Progress</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">
<?php include 'admin_navbar.php'; ?>
  <div class="max-w-7xl mx-auto px-6 py-8">
    <header class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Admin Dashboard</h1>
            <p class="text-sm text-slate-500">Manage exams and questions</p>
        </div>
        <nav class="flex items-center gap-2">
            <a href="adminindex.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Dashboard</a>
            <a href="patches.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Patches</a>
            <a href="certificates.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Certificates</a>
            <a href="employee_progress.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Employee Progress</a>
            <a href="employee_scores.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Scores</a>
            <a href="../logout.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg">Logout</a>
        </nav>
    </header>

    <?php if (empty($employees)): ?>
      <div class="bg-white p-6 rounded shadow">No employees found in `emp_info`. Please ensure your employees table exists or add employees.</div>
    <?php else: ?>
      <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table id="employeeTable" class="min-w-full divide-y divide-slate-200" style="width:100%">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Employee ID</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Earned Patches</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Earned Certificates</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp): ?>
              <?php $eid = $emp['Emp_ID'];
                $patches = getEmployeePatches($con3, $eid);
                $certs = getEmployeeCertificates($con3, $eid);
              ?>
              <tr>
                <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($eid); ?></td>
                <td class="px-6 py-4 text-sm font-medium text-slate-800"><?php echo htmlspecialchars($emp['Emp_Name'] ?? $eid); ?></td>
                <td class="px-6 py-4 text-sm text-slate-600">
                  <?php if (empty($patches)): ?>
                    <span class="text-xs text-slate-400">None</span>
                  <?php else: ?>
                    <?php foreach ($patches as $p): ?>
                      <span class="inline-block bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full text-xs mr-1 mb-1"><?php echo htmlspecialchars($p['Patch_Name']); ?></span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600">
                  <?php if (empty($certs)): ?>
                    <span class="text-xs text-slate-400">None</span>
                  <?php else: ?>
                    <?php foreach ($certs as $c): ?>
                      <span class="inline-block bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded-full text-xs mr-1 mb-1"><?php echo htmlspecialchars($c['Certificate_Name']); ?></span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
  $(document).ready(function(){
    $('#employeeTable').DataTable({
      pageLength: 25,
      responsive: true,
      autoWidth: false,
      lengthMenu: [10,25,50,100],
      language: {searchPlaceholder: 'Search employees, patches, certificates...'},
      columnDefs: [ { orderable: false, targets: [2,3] } ]
    });
  });
</script>
