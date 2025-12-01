<?php
include "includes/sessions.php";
include "includes/db.php";

// Verify user login
$emp_id = $_COOKIE['EIMS_emp_Id'] ?? '';
if (empty($emp_id)) {
    header("Location: login.php");
    exit;
}

// --- Helper functions for eligibility checks (available to both POST and display) ---
function employeeHasPassedExam($con, $emp, $examId) {
  $sql = "SELECT TOP 1 Score, TotalQuestions FROM dbo.Results WHERE Emp_ID = ? AND Exam_ID = ? ORDER BY Date_Completed DESC";
  $stmt = sqlsrv_query($con, $sql, [$emp, $examId]);
  if ($stmt) {
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if ($r && isset($r['TotalQuestions']) && $r['TotalQuestions'] > 0) {
      $score = (int)$r['Score'];
      $total = (int)$r['TotalQuestions'];
      $pct = ($total>0) ? ($score / $total) * 100 : 0;
      return $pct >= 75; // passing threshold
    }
  }
  return false;
}

function isPatchCompleted($con, $emp, $patchId) {
  // Prefer Employee_Patches table if it exists
  $chkSql = "IF OBJECT_ID('dbo.Employee_Patches','U') IS NULL SELECT 0 as cnt ELSE SELECT COUNT(*) as cnt FROM dbo.Employee_Patches WHERE Emp_ID = ? AND Patch_ID = ?";
  $chkStmt = sqlsrv_query($con, $chkSql, [$emp, $patchId]);
  if ($chkStmt) {
    $r = sqlsrv_fetch_array($chkStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($chkStmt);
    $cnt = (int)($r['cnt'] ?? 0);
    if ($cnt > 0) return true;
  }

  // Fallback: check that the employee has passed all exams linked to this patch
  $sql = "SELECT Exam_ID FROM dbo.Exam_Patches WHERE Patch_ID = ?";
  $stmt = sqlsrv_query($con, $sql, [$patchId]);
  $exams = [];
  if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
      $exams[] = $row['Exam_ID'];
    }
    sqlsrv_free_stmt($stmt);
  }
  if (empty($exams)) return false;
  foreach ($exams as $eid) {
    if (!employeeHasPassedExam($con, $emp, $eid)) return false;
  }
  return true;
}

function isCertificateEligible($con, $emp, $certId) {
  $sql2 = "IF OBJECT_ID('dbo.Certificate_Patches','U') IS NULL SELECT 0 as cnt ELSE SELECT Patch_ID FROM dbo.Certificate_Patches WHERE Certificate_ID = ?";
  $stmt2 = sqlsrv_query($con, $sql2, [$certId]);
  $patches = [];
  if ($stmt2) {
    while ($r = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
      if (isset($r['Patch_ID'])) $patches[] = $r['Patch_ID'];
    }
    sqlsrv_free_stmt($stmt2);
  }
  if (empty($patches)) return true;
  foreach ($patches as $pid) {
    if (!isPatchCompleted($con, $emp, $pid)) return false;
  }
  return true;
}

// Handle claim request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim') {
  $claim_cert_id = intval($_POST['cert_id'] ?? 0);
  if ($claim_cert_id > 0) {
    if (isCertificateEligible($con3, $emp_id, $claim_cert_id)) {
      // ensure not already assigned
      $chk = sqlsrv_query($con3, "SELECT COUNT(*) as cnt FROM dbo.Employee_Certificates WHERE Emp_ID = ? AND Certificate_ID = ?", [$emp_id, $claim_cert_id]);
      $already = 0;
      if ($chk) {
        $rr = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC);
        $already = (int)($rr['cnt'] ?? 0);
        sqlsrv_free_stmt($chk);
      }
      if ($already === 0) {
        $ins = sqlsrv_query($con3, "INSERT INTO dbo.Employee_Certificates (Emp_ID, Certificate_ID, Date_Earned) VALUES (?, ?, GETDATE())", [$emp_id, $claim_cert_id]);
        if ($ins === false) {
          $claim_error = "Error claiming certificate.";
        } else {
          $claim_success = "Certificate claimed — congratulations!";
        }
      } else {
        $claim_error = "You already have this certificate.";
      }
    } else {
      $claim_error = "You do not meet the required patches to claim this certificate.";
    }
  }
}

// Fetch all certificates for eligibility checks
$all_sql = "SELECT Certificate_ID, Certificate_Name, Certificate_Description FROM dbo.Certificates ORDER BY Certificate_Name";
$all_stmt = sqlsrv_query($con3, $all_sql);
$all_certificates = [];
if ($all_stmt) {
  while ($r = sqlsrv_fetch_array($all_stmt, SQLSRV_FETCH_ASSOC)) {
    $all_certificates[] = $r;
  }
  sqlsrv_free_stmt($all_stmt);
}

// Fetch earned certificate ids for this employee
$earned_ids = [];
$sql_earned = "SELECT Certificate_ID FROM dbo.Employee_Certificates WHERE Emp_ID = ?";
$stmt_earned = sqlsrv_query($con3, $sql_earned, [$emp_id]);
if ($stmt_earned) {
  while ($r = sqlsrv_fetch_array($stmt_earned, SQLSRV_FETCH_ASSOC)) {
    $earned_ids[] = $r['Certificate_ID'];
  }
  sqlsrv_free_stmt($stmt_earned);
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
          <?php if (!empty($claim_success)): ?>
            <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg"><?php echo htmlspecialchars($claim_success); ?></div>
          <?php endif; ?>
          <?php if (!empty($claim_error)): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?php echo htmlspecialchars($claim_error); ?></div>
          <?php endif; ?>

          <?php if (empty($all_certificates) && empty($certificates)): ?>
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
                <?php
                  // fetch required patch names for this certificate (if any)
                  $reqPatchNames = [];
                  $mapQ = sqlsrv_query($con3, "IF OBJECT_ID('dbo.Certificate_Patches','U') IS NULL SELECT 0 as cnt ELSE SELECT p.Patch_Name FROM dbo.Certificate_Patches cp JOIN dbo.Patches p ON cp.Patch_ID = p.Patch_ID WHERE cp.Certificate_ID = ?", [$cert['Certificate_ID']]);
                  if ($mapQ) {
                    while ($mp = sqlsrv_fetch_array($mapQ, SQLSRV_FETCH_ASSOC)) {
                      if (isset($mp['Patch_Name'])) $reqPatchNames[] = $mp['Patch_Name'];
                    }
                    sqlsrv_free_stmt($mapQ);
                  }
                ?>
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
                    <div class="w-full">
                      <?php if (!empty($reqPatchNames)): ?>
                        <div class="text-sm text-slate-600 mb-2">Required patches: <strong><?php echo htmlspecialchars(implode(', ', $reqPatchNames)); ?></strong></div>
                      <?php endif; ?>
                      <button class="w-full px-4 py-2 text-sm font-medium text-indigo-600 hover:text-indigo-700 transition">View Details →</button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Available to Claim -->
            <div class="mt-10">
              <h2 class="text-xl font-semibold mb-4">Available Certificates</h2>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($all_certificates as $cert): ?>
                  <?php if (in_array($cert['Certificate_ID'], $earned_ids)) continue; ?>
                  <?php
                    // collect required patch names
                    $reqPatchNames = [];
                    $ps = [];
                    $mapStmt = sqlsrv_query($con3, "IF OBJECT_ID('dbo.Certificate_Patches','U') IS NULL SELECT 0 as cnt ELSE SELECT cp.Patch_ID, p.Patch_Name FROM dbo.Certificate_Patches cp JOIN dbo.Patches p ON cp.Patch_ID = p.Patch_ID WHERE cp.Certificate_ID = ?", [$cert['Certificate_ID']]);
                    if ($mapStmt) {
                      while ($rr = sqlsrv_fetch_array($mapStmt, SQLSRV_FETCH_ASSOC)) {
                        if (isset($rr['Patch_ID']) && $rr['Patch_ID'] > 0) {
                          $ps[] = $rr['Patch_ID'];
                          if (isset($rr['Patch_Name'])) $reqPatchNames[] = $rr['Patch_Name'];
                        }
                      }
                      sqlsrv_free_stmt($mapStmt);
                    }
                    // Use centralized eligibility check (requires all patches completed)
                    $eligible = isCertificateEligible($con3, $emp_id, $cert['Certificate_ID']);
                  ?>
                  <div class="bg-white rounded-2xl shadow hover:shadow-lg transition overflow-hidden border-l-4 <?php echo $eligible ? 'border-emerald-500' : 'border-slate-200'; ?>">
                    <div class="p-6">
                      <h3 class="text-lg font-bold text-slate-800 mb-2"><?php echo htmlspecialchars($cert['Certificate_Name']); ?></h3>
                      <p class="text-sm text-slate-600 mb-4"><?php echo htmlspecialchars($cert['Certificate_Description'] ?? ''); ?></p>
                      <?php if (!empty($reqPatchNames)): ?>
                        <p class="text-sm text-slate-500 mb-3">Required patches: <strong><?php echo htmlspecialchars(implode(', ', $reqPatchNames)); ?></strong></p>
                      <?php endif; ?>
                      <?php if ($eligible): ?>
                        <form method="POST">
                          <input type="hidden" name="action" value="claim">
                          <input type="hidden" name="cert_id" value="<?php echo $cert['Certificate_ID']; ?>">
                          <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg">Claim Certificate</button>
                        </form>
                      <?php else: ?>
                        <div class="text-sm text-slate-500">Not yet eligible — complete required patches.</div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
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
