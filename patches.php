<?php
include "includes/sessions.php";
include "includes/db.php";

// Verify user login
$emp_id = $_COOKIE['EIMS_emp_Id'] ?? '';
if (empty($emp_id)) {
    header("Location: login.php");
    exit;
}

// Ensure Employee_Patches table exists
$create_table = "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'dbo.Employee_Patches') AND type in (N'U')) 
    CREATE TABLE dbo.Employee_Patches (
        Emp_ID NVARCHAR(MAX) NOT NULL,
        Patch_ID INT NOT NULL,
        Date_Earned DATETIME DEFAULT GETDATE(),
        PRIMARY KEY (Emp_ID, Patch_ID)
    )";
@sqlsrv_query($con3, $create_table);

// Fetch patches earned by this employee
// Patches are earned when employee passes an exam linked to that patch
$sql = "
    SELECT DISTINCT 
        p.Patch_ID, 
        p.Patch_Name, 
        p.Patch_Description,
        ep.Date_Earned,
        (SELECT COUNT(*) FROM dbo.Exam_Patches WHERE Patch_ID = p.Patch_ID) AS total_exams,
        (SELECT COUNT(*) FROM dbo.Exam_Patches ep2 
         INNER JOIN dbo.Results r ON ep2.Exam_ID = r.Exam_ID 
         WHERE ep2.Patch_ID = p.Patch_ID AND r.Emp_ID = ? AND r.Score >= (r.TotalQuestions * 0.75)) AS exams_passed
    FROM dbo.Employee_Patches ep
    INNER JOIN dbo.Patches p ON ep.Patch_ID = p.Patch_ID
    WHERE ep.Emp_ID = ?
    ORDER BY ep.Date_Earned DESC
";
$stmt = sqlsrv_query($con3, $sql, [$emp_id, $emp_id]);
$patches = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $patches[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Fetch available patches (not yet earned) that employee can work toward
$sql_available = "
    SELECT DISTINCT
        p.Patch_ID,
        p.Patch_Name,
        p.Patch_Description,
        (SELECT COUNT(*) FROM dbo.Exam_Patches WHERE Patch_ID = p.Patch_ID) AS total_exams,
        (SELECT COUNT(*) FROM dbo.Exam_Patches ep2 
         INNER JOIN dbo.Results r ON ep2.Exam_ID = r.Exam_ID 
         WHERE ep2.Patch_ID = p.Patch_ID AND r.Emp_ID = ? AND r.Score >= (r.TotalQuestions * 0.75)) AS exams_passed
    FROM dbo.Patches p
    WHERE p.Patch_ID NOT IN (SELECT Patch_ID FROM dbo.Employee_Patches WHERE Emp_ID = ?)
    ORDER BY p.Patch_Name
";
$stmt_avail = sqlsrv_query($con3, $sql_available, [$emp_id, $emp_id]);
$available_patches = [];

if ($stmt_avail) {
    while ($row = sqlsrv_fetch_array($stmt_avail, SQLSRV_FETCH_ASSOC)) {
        $available_patches[] = $row;
    }
    sqlsrv_free_stmt($stmt_avail);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patches</title>
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
            <h1 class="text-3xl font-bold text-slate-800">🔧 My Patches</h1>
            <p class="text-sm text-slate-500 mt-1">Track your completed patches and progress</p>
          </div>
          <a href="index.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">← Back to Dashboard</a>
        </div>
      </header>

      <!-- Content -->
      <main class="flex-1 overflow-auto">
        <div class="max-w-7xl mx-auto px-6 py-8">
          
          <?php
          // Fetch expiring patches (10 days or less)
          $expiring_patches = [];
          $sql_exp = "
              SELECT p.Patch_Name, ep.Expiration_Date, DATEDIFF(DAY, GETDATE(), ep.Expiration_Date) as days_left
              FROM dbo.Employee_Patches ep
              JOIN dbo.Patches p ON ep.Patch_ID = p.Patch_ID
              WHERE ep.Emp_ID = ? 
              AND ep.Expiration_Date IS NOT NULL
              AND DATEDIFF(DAY, GETDATE(), ep.Expiration_Date) BETWEEN 0 AND 10
              ORDER BY ep.Expiration_Date ASC
          ";
          $stmt_exp = @sqlsrv_query($con3, $sql_exp, [$emp_id]);
          if ($stmt_exp) {
              while ($r = sqlsrv_fetch_array($stmt_exp, SQLSRV_FETCH_ASSOC)) {
                  $expiring_patches[] = $r;
              }
              sqlsrv_free_stmt($stmt_exp);
          }
          ?>

          <!-- Expiration Notifications -->
          <?php if (!empty($expiring_patches)): ?>
          <section class="mb-8 animate-pulse">
              <div class="bg-red-100 border-2 border-red-600 rounded-xl p-5 shadow-lg">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                          </svg>
                      </div>
                      <div class="ml-3 flex-1">
                          <h3 class="text-lg font-bold text-red-800 uppercase">⚠️ Patches Expiring Soon</h3>
                          <div class="mt-2 text-sm text-red-800">
                              <ul class="list-disc list-inside space-y-1 bg-white bg-opacity-50 rounded p-3">
                                  <?php foreach ($expiring_patches as $ep): ?>
                                      <li class="font-medium">
                                          <strong class="text-red-900"><?php echo htmlspecialchars($ep['Patch_Name']); ?></strong> 
                                          - expires in <span class="font-bold text-red-900"><?php echo (int)$ep['days_left']; ?> day<?php echo ((int)$ep['days_left'] !== 1) ? 's' : ''; ?></span>
                                          (<?php echo $ep['Expiration_Date'] instanceof DateTime ? $ep['Expiration_Date']->format('M d, Y') : htmlspecialchars($ep['Expiration_Date']); ?>)
                                      </li>
                                  <?php endforeach; ?>
                              </ul>
                              <p class="mt-4 text-sm font-bold bg-red-200 p-2 rounded">
                                  💡 <em>ACTION REQUIRED: Renew by retaking the associated exams before they expire!</em>
                              </p>
                          </div>
                      </div>
                  </div>
              </div>
          </section>
          <?php endif; ?>

          <!-- Earned Patches -->
          <div>
            <h2 class="text-2xl font-bold text-slate-800 mb-6">✅ Earned Patches</h2>
            <?php if (empty($patches)): ?>
              <div class="bg-white rounded-2xl shadow p-12 text-center mb-8">
                <svg class="w-16 h-16 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-slate-600 mb-2">No Patches Earned Yet</h3>
                <p class="text-slate-500">Complete exams to earn patches!</p>
              </div>
            <?php else: ?>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
                <?php foreach ($patches as $patch): ?>
                  <div class="bg-white rounded-2xl shadow hover:shadow-lg transition overflow-hidden border-l-4 border-emerald-600">
                    <div class="p-6">
                      <div class="flex items-start justify-between mb-4">
                        <div class="text-4xl">🔧</div>
                        <span class="inline-block px-3 py-1 text-xs font-semibold text-emerald-600 bg-emerald-50 rounded-full">Earned</span>
                      </div>
                      <h3 class="text-lg font-bold text-slate-800 mb-2"><?php echo htmlspecialchars($patch['Patch_Name']); ?></h3>
                      <p class="text-sm text-slate-600 mb-4"><?php echo htmlspecialchars($patch['Patch_Description'] ?? 'Skill patch'); ?></p>
                      
                      <!-- Progress bar -->
                      <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                          <span class="text-xs font-medium text-slate-600">Exams Completed</span>
                          <span class="text-xs font-bold text-emerald-600"><?php echo $patch['exams_passed']; ?>/<?php echo $patch['total_exams']; ?></span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                          <div class="bg-emerald-600 h-2 rounded-full" style="width: <?php echo ($patch['total_exams'] > 0) ? (($patch['exams_passed'] / $patch['total_exams']) * 100) : 0; ?>%"></div>
                        </div>
                      </div>

                      <div class="space-y-1 text-sm text-slate-600">
                        <div class="flex items-center justify-between">
                          <span>Earned on:</span>
                          <span class="font-medium">
                            <?php 
                              $date = $patch['Date_Earned'];
                              if ($date instanceof DateTime) {
                                echo $date->format('M d, Y');
                              } else {
                                echo htmlspecialchars($date);
                              }
                            ?>
                          </span>
                        </div>
                        <?php 
                          $expired = false; $daysLeft = null; 
                          $hasExp = isset($patch['Expiration_Date']) && $patch['Expiration_Date'] !== null;
                          if ($hasExp) {
                            $dt = $patch['Expiration_Date'];
                            if ($dt instanceof DateTime) {
                              $daysLeft = (int)date_diff(new DateTime(), $dt)->format('%r%a');
                              $expired = ($daysLeft < 0);
                            }
                          }
                        ?>
                        <div class="flex items-center justify-between <?php echo ($hasExp && $expired) ? 'text-red-600' : 'text-slate-600'; ?>">
                          <span>Expires:</span>
                          <span class="font-medium">
                            <?php 
                              if ($hasExp) {
                                $exp = $patch['Expiration_Date'];
                                if ($exp instanceof DateTime) {
                                  echo $exp->format('M d, Y');
                                } else {
                                  echo htmlspecialchars($exp);
                                }
                              } else {
                                echo 'No expiration';
                              }
                            ?>
                            <?php if ($hasExp && isset($daysLeft) && $daysLeft !== null): ?>
                              <span class="ml-2 inline-block px-2 py-0.5 rounded-full text-xs <?php echo ($daysLeft <= 10 && $daysLeft >= 0) ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-600'; ?>">
                                <?php echo $daysLeft >= 0 ? ($daysLeft . ' days left') : (abs($daysLeft) . ' days past'); ?>
                              </span>
                            <?php endif; ?>
                          </span>
                        </div>
                        <?php 
                          // Policy source from Patches
                          $policy = '';
                          $pinfo = sqlsrv_query($con3, "SELECT is_Unlimited, Expiration_Days, Expiration_Date FROM dbo.Patches WHERE Patch_ID = ?", [$patch['Patch_ID']]);
                          if ($pinfo) {
                            $pi = sqlsrv_fetch_array($pinfo, SQLSRV_FETCH_ASSOC);
                            sqlsrv_free_stmt($pinfo);
                            if ($pi) {
                              if ((int)($pi['is_Unlimited'] ?? 0) === 1) {
                                $policy = 'Unlimited';
                              } elseif (isset($pi['Expiration_Days']) && $pi['Expiration_Days'] !== null) {
                                $policy = (int)$pi['Expiration_Days'] . ' days policy';
                              } elseif (isset($pi['Expiration_Date']) && $pi['Expiration_Date'] !== null) {
                                $d = $pi['Expiration_Date'];
                                $policy = 'Expires by ' . ($d instanceof DateTime ? $d->format('Y-m-d') : htmlspecialchars($d));
                              }
                            }
                          }
                        ?>
                        <?php if (!empty($policy)): ?>
                        <div class="flex items-center justify-between text-xs text-slate-500">
                          <span>Policy:</span>
                          <span><?php echo htmlspecialchars($policy); ?></span>
                        </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Available Patches -->
          <div>
            <h2 class="text-2xl font-bold text-slate-800 mb-6">🎯 Available Patches</h2>
            <?php if (empty($available_patches)): ?>
              <div class="bg-white rounded-2xl shadow p-12 text-center">
                <svg class="w-16 h-16 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-slate-600 mb-2">All Patches Earned!</h3>
                <p class="text-slate-500">You've completed all available patches.</p>
              </div>
            <?php else: ?>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($available_patches as $patch): ?>
                  <div class="bg-white rounded-2xl shadow hover:shadow-lg transition overflow-hidden border-l-4 border-slate-300">
                    <div class="p-6">
                      <div class="flex items-start justify-between mb-4">
                        <div class="text-4xl">🎯</div>
                        <span class="inline-block px-3 py-1 text-xs font-semibold text-slate-600 bg-slate-100 rounded-full">In Progress</span>
                      </div>
                      <h3 class="text-lg font-bold text-slate-800 mb-2"><?php echo htmlspecialchars($patch['Patch_Name']); ?></h3>
                      <p class="text-sm text-slate-600 mb-4"><?php echo htmlspecialchars($patch['Patch_Description'] ?? 'Skill patch'); ?></p>
                      
                      <!-- Progress bar -->
                      <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                          <span class="text-xs font-medium text-slate-600">Exams Completed</span>
                          <span class="text-xs font-bold text-indigo-600"><?php echo $patch['exams_passed']; ?>/<?php echo $patch['total_exams']; ?></span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                          <div class="bg-indigo-600 h-2 rounded-full" style="width: <?php echo ($patch['total_exams'] > 0) ? (($patch['exams_passed'] / $patch['total_exams']) * 100) : 0; ?>%"></div>
                        </div>
                      </div>

                      <p class="text-sm text-slate-500">
                        Complete <strong class="text-slate-700"><?php echo $patch['total_exams'] - $patch['exams_passed']; ?> more exam<?php echo ($patch['total_exams'] - $patch['exams_passed']) !== 1 ? 's' : ''; ?></strong> to earn this patch.
                      </p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

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
