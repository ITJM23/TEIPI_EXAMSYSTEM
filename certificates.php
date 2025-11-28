<?php
include "includes/sessions.php";
include "includes/db.php";

// Verify user login
$emp_id = $_COOKIE['EIMS_emp_Id'] ?? '';
if (empty($emp_id)) {
    header("Location: login.php");
    exit;
}

// Fetch employee certificates
$sql = "SELECT c.Certificate_ID, c.Certificate_Name, c.Certificate_Description, ec.Date_Earned
        FROM dbo.Employee_Certificates ec
        JOIN dbo.Certificates c ON ec.Certificate_ID = c.Certificate_ID
        WHERE ec.Emp_ID = ?
        ORDER BY ec.Date_Earned DESC";
$stmt = sqlsrv_query($con3, $sql, [$emp_id]);
$certificates = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $certificates[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">
  <div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Mobile toggle button -->
      <button id="sidebarToggle" class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>

      <!-- Header -->
      <header class="bg-white border-b border-slate-200 px-6 py-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-slate-800">🏅 My Certificates</h1>
            <p class="text-sm text-slate-500 mt-1">View your earned certificates</p>
          </div>
          <a href="index.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">← Back to Dashboard</a>
        </div>
      </header>

      <!-- Content -->
      <main class="flex-1 overflow-auto">
        <div class="max-w-7xl mx-auto px-6 py-8">
          <?php if (empty($certificates)): ?>
            <div class="bg-white rounded-2xl shadow p-12 text-center">
              <svg class="w-16 h-16 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <h3 class="text-lg font-semibold text-slate-600 mb-2">No Certificates Yet</h3>
              <p class="text-slate-500">Complete exams and assignments to earn certificates.</p>
            </div>
          <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <?php foreach ($certificates as $cert): ?>
                <div class="bg-white rounded-2xl shadow hover:shadow-lg transition overflow-hidden border-l-4 border-indigo-600">
                  <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                      <div class="text-4xl">🏆</div>
                      <span class="inline-block px-3 py-1 text-xs font-semibold text-indigo-600 bg-indigo-50 rounded-full">Certificate</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2"><?php echo htmlspecialchars($cert['Certificate_Name']); ?></h3>
                    <p class="text-sm text-slate-600 mb-4"><?php echo htmlspecialchars($cert['Certificate_Description'] ?? 'Professional certificate'); ?></p>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                      <span>Earned on:</span>
                      <span class="font-medium">
                        <?php 
                          $date = $cert['Date_Earned'];
                          if ($date instanceof DateTime) {
                            echo $date->format('M d, Y');
                          } else {
                            echo htmlspecialchars($date);
                          }
                        ?>
                      </span>
                    </div>
                  </div>
                  <div class="bg-indigo-50 px-6 py-3">
                    <button class="w-full px-4 py-2 text-sm font-medium text-indigo-600 hover:text-indigo-700 transition">View Details →</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
              <div class="bg-white rounded-xl shadow p-6 border-l-4 border-emerald-500">
                <div class="flex items-center">
                  <div class="text-3xl mr-4">🎯</div>
                  <div>
                    <p class="text-sm text-slate-500">Total Certificates</p>
                    <p class="text-2xl font-bold text-slate-800"><?php echo count($certificates); ?></p>
                  </div>
                </div>
              </div>
              <div class="bg-white rounded-xl shadow p-6 border-l-4 border-blue-500">
                <div class="flex items-center">
                  <div class="text-3xl mr-4">📅</div>
                  <div>
                    <p class="text-sm text-slate-500">Latest Earned</p>
                    <p class="text-lg font-bold text-slate-800">
                      <?php 
                        if (!empty($certificates)) {
                          $latest = $certificates[0]['Date_Earned'];
                          if ($latest instanceof DateTime) {
                            echo $latest->format('M d, Y');
                          } else {
                            echo htmlspecialchars($latest);
                          }
                        } else {
                          echo 'N/A';
                        }
                      ?>
                    </p>
                  </div>
                </div>
              </div>
              <div class="bg-white rounded-xl shadow p-6 border-l-4 border-indigo-500">
                <div class="flex items-center">
                  <div class="text-3xl mr-4">🌟</div>
                  <div>
                    <p class="text-sm text-slate-500">Achievement Rate</p>
                    <p class="text-2xl font-bold text-slate-800">
                      <?php
                        // Fetch total available certificates for percentage
                        $sql_total = "SELECT COUNT(*) as total FROM dbo.Certificates";
                        $stmt_total = sqlsrv_query($con3, $sql_total);
                        $total_certs = 0;
                        if ($stmt_total) {
                          $row_total = sqlsrv_fetch_array($stmt_total, SQLSRV_FETCH_ASSOC);
                          $total_certs = $row_total['total'];
                          sqlsrv_free_stmt($stmt_total);
                        }
                        
                        if ($total_certs > 0) {
                          $percentage = round((count($certificates) / $total_certs) * 100);
                          echo $percentage . '%';
                        } else {
                          echo '0%';
                        }
                      ?>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>

  <script>
    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('[id="sidebar"]') || document.querySelector('aside');
    
    if (sidebarToggle && sidebar) {
      sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
      });
    }
  </script>

</body>
</html>
