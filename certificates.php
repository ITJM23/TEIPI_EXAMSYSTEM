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
  $sql = "SELECT TOP 1 r.Score, r.TotalQuestions, COALESCE(es.PassingRate, 75) AS PassingRate
          FROM dbo.Results r
          LEFT JOIN dbo.Exam_Settings es ON es.Exam_ID = r.Exam_ID
          WHERE r.Emp_ID = ? AND r.Exam_ID = ?
          ORDER BY r.Date_Completed DESC";
  $stmt = sqlsrv_query($con, $sql, [$emp, $examId]);
  if ($stmt) {
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if ($r && isset($r['TotalQuestions']) && $r['TotalQuestions'] > 0) {
      $score = (int)$r['Score'];
      $total = (int)$r['TotalQuestions'];
      $pct = ($total>0) ? ($score / $total) * 100 : 0;
      $thr = (float)($r['PassingRate'] ?? 75);
      return $pct >= $thr;
    }
  }
  return false;
}

function isPatchCompleted($con, $emp, $patchId) {
  // Check Employee_Patches table - ONLY return true if patch exists and is NOT expired
  // No fallback to exam results - patch must be explicitly in Employee_Patches table
  $chkSql = "IF OBJECT_ID('dbo.Employee_Patches','U') IS NULL 
             SELECT 0 as cnt 
             ELSE 
             SELECT COUNT(*) as cnt 
             FROM dbo.Employee_Patches 
             WHERE Emp_ID = ? AND Patch_ID = ? 
             AND (Expiration_Date IS NULL OR Expiration_Date >= GETDATE())";
  $chkStmt = sqlsrv_query($con, $chkSql, [$emp, $patchId]);
  if ($chkStmt) {
    $r = sqlsrv_fetch_array($chkStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($chkStmt);
    $cnt = (int)($r['cnt'] ?? 0);
    return $cnt > 0;
  }
  
  return false;
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
      // Check if already assigned (including expired ones)
      $chk = sqlsrv_query($con3, "SELECT Certificate_ID, Expiration_Date FROM dbo.Employee_Certificates WHERE Emp_ID = ? AND Certificate_ID = ?", [$emp_id, $claim_cert_id]);
      $existing = null;
      if ($chk) {
        $existing = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($chk);
      }
      
      $isExpired = false;
      if ($existing && $existing['Expiration_Date']) {
        $expDate = $existing['Expiration_Date'];
        if ($expDate instanceof DateTime) {
          $isExpired = $expDate < new DateTime();
        }
      }
      
      if ($existing && !$isExpired) {
        $claim_error = "You already have this certificate.";
      } else {
        // Ensure Employee_Certificates has Expiration_Date
        @sqlsrv_query($con3, "IF COL_LENGTH('dbo.Employee_Certificates','Expiration_Date') IS NULL ALTER TABLE dbo.Employee_Certificates ADD Expiration_Date DATETIME NULL");
        // Ensure Certificates has Expiration_Days for duration support
        @sqlsrv_query($con3, "IF COL_LENGTH('dbo.Certificates','Expiration_Days') IS NULL ALTER TABLE dbo.Certificates ADD Expiration_Days INT NULL");
        
        if ($isExpired) {
          // Update existing expired certificate
          $upd = sqlsrv_query($con3, 
            "UPDATE ec
             SET ec.Date_Earned = GETDATE(), 
                 ec.Expiration_Date = CASE 
                   WHEN ISNULL(c.is_Unlimited,0)=1 THEN NULL
                   WHEN c.Expiration_Days IS NOT NULL THEN DATEADD(DAY, c.Expiration_Days, GETDATE())
                   WHEN c.Expiration_Date IS NOT NULL THEN c.Expiration_Date
                   ELSE NULL END
             FROM dbo.Employee_Certificates ec
             JOIN dbo.Certificates c ON c.Certificate_ID = ec.Certificate_ID
             WHERE ec.Emp_ID = ? AND ec.Certificate_ID = ?",
            [$emp_id, $claim_cert_id]
          );
          if ($upd === false) {
            $claim_error = "Error reclaiming certificate.";
          } else {
            $claim_success = "Certificate reclaimed — congratulations!";
          }
        } else {
          // Insert new certificate
          $ins = sqlsrv_query($con3, 
            "INSERT INTO dbo.Employee_Certificates (Emp_ID, Certificate_ID, Date_Earned, Expiration_Date)
             SELECT ?, ?, GETDATE(), CASE 
               WHEN ISNULL(c.is_Unlimited,0)=1 THEN NULL
               WHEN c.Expiration_Days IS NOT NULL THEN DATEADD(DAY, c.Expiration_Days, GETDATE())
               WHEN c.Expiration_Date IS NOT NULL THEN c.Expiration_Date
               ELSE NULL END
             FROM dbo.Certificates c WHERE c.Certificate_ID = ?",
            [$emp_id, $claim_cert_id, $claim_cert_id]
          );
          if ($ins === false) {
            $claim_error = "Error claiming certificate.";
          } else {
            $claim_success = "Certificate claimed — congratulations!";
          }
        }
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

// Fetch earned certificate ids for this employee (non-expired only)
$earned_ids = [];
$sql_earned = "SELECT Certificate_ID FROM dbo.Employee_Certificates WHERE Emp_ID = ? AND (Expiration_Date IS NULL OR Expiration_Date >= GETDATE())";
$stmt_earned = sqlsrv_query($con3, $sql_earned, [$emp_id]);
if ($stmt_earned) {
  while ($r = sqlsrv_fetch_array($stmt_earned, SQLSRV_FETCH_ASSOC)) {
    $earned_ids[] = $r['Certificate_ID'];
  }
  sqlsrv_free_stmt($stmt_earned);
}

// Fetch active (non-expired) employee certificates
$sql = "SELECT c.Certificate_ID, c.Certificate_Name, c.Certificate_Description, ec.Date_Earned, ec.Expiration_Date
  FROM dbo.Employee_Certificates ec
  JOIN dbo.Certificates c ON ec.Certificate_ID = c.Certificate_ID
  WHERE ec.Emp_ID = ?
  AND (ec.Expiration_Date IS NULL OR ec.Expiration_Date >= GETDATE())
  ORDER BY ec.Date_Earned DESC";
$stmt = sqlsrv_query($con3, $sql, [$emp_id]);
$certificates = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $certificates[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Fetch expired certificates
$sql_expired = "SELECT c.Certificate_ID, c.Certificate_Name, c.Certificate_Description, ec.Date_Earned, ec.Expiration_Date
  FROM dbo.Employee_Certificates ec
  JOIN dbo.Certificates c ON ec.Certificate_ID = c.Certificate_ID
  WHERE ec.Emp_ID = ?
  AND ec.Expiration_Date IS NOT NULL 
  AND ec.Expiration_Date < GETDATE()
  ORDER BY ec.Expiration_Date DESC";
$stmt_expired = sqlsrv_query($con3, $sql_expired, [$emp_id]);
$expired_certificates = [];

if ($stmt_expired) {
    while ($row = sqlsrv_fetch_array($stmt_expired, SQLSRV_FETCH_ASSOC)) {
        $expired_certificates[] = $row;
    }
    sqlsrv_free_stmt($stmt_expired);
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

          <?php
          // Fetch expiring certificates (10 days or less)
          $expiring_certs = [];
          $sql_exp = "
              SELECT c.Certificate_Name, ec.Expiration_Date, DATEDIFF(DAY, GETDATE(), ec.Expiration_Date) as days_left
              FROM dbo.Employee_Certificates ec
              JOIN dbo.Certificates c ON ec.Certificate_ID = c.Certificate_ID
              WHERE ec.Emp_ID = ? 
              AND ec.Expiration_Date IS NOT NULL
              AND DATEDIFF(DAY, GETDATE(), ec.Expiration_Date) BETWEEN 0 AND 10
              ORDER BY ec.Expiration_Date ASC
          ";
          $stmt_exp = @sqlsrv_query($con3, $sql_exp, [$emp_id]);
          if ($stmt_exp) {
              while ($r = sqlsrv_fetch_array($stmt_exp, SQLSRV_FETCH_ASSOC)) {
                  $expiring_certs[] = $r;
              }
              sqlsrv_free_stmt($stmt_exp);
          }
          ?>

          <!-- Expiration Notifications -->
          <?php if (!empty($expiring_certs)): ?>
          <section class="mb-8 animate-pulse">
              <div class="bg-red-100 border-2 border-red-600 rounded-xl p-5 shadow-lg">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                          </svg>
                      </div>
                      <div class="ml-3 flex-1">
                          <h3 class="text-lg font-bold text-red-800 uppercase">⚠️ Certificates Expiring Soon</h3>
                          <div class="mt-2 text-sm text-red-800">
                              <ul class="list-disc list-inside space-y-1 bg-white bg-opacity-50 rounded p-3">
                                  <?php foreach ($expiring_certs as $ec): ?>
                                      <li class="font-medium">
                                          <strong class="text-red-900"><?php echo htmlspecialchars($ec['Certificate_Name']); ?></strong> 
                                          - expires in <span class="font-bold text-red-900"><?php echo (int)$ec['days_left']; ?> day<?php echo ((int)$ec['days_left'] !== 1) ? 's' : ''; ?></span>
                                          (<?php echo $ec['Expiration_Date'] instanceof DateTime ? $ec['Expiration_Date']->format('M d, Y') : htmlspecialchars($ec['Expiration_Date']); ?>)
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
                    <div class="space-y-1 text-sm text-slate-600">
                      <div class="flex items-center justify-between">
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
                      <div class="flex items-center justify-between">
                        <span>Expires:</span>
                        <span class="font-medium">
                          <?php 
                            $exp = $cert['Expiration_Date'] ?? null;
                            $hasExp = $exp && ($exp instanceof DateTime || !empty($exp));
                            if ($hasExp) {
                              $expDate = $exp instanceof DateTime ? $exp : (is_array($exp) && isset($exp['date']) ? new DateTime($exp['date']) : new DateTime($exp));
                              echo $expDate->format('M d, Y');
                            } else {
                              echo 'No expiration';
                            }
                          ?>
                        </span>
                      </div>
                      <?php 
                        if ($hasExp) {
                          $now = new DateTime('now');
                          $diffDays = (int)$now->diff($expDate)->format('%r%a');
                          if ($diffDays >= 0) {
                            echo '<span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-slate-100 text-slate-700">'.htmlspecialchars($diffDays).' days left</span>';
                          } else {
                            echo '<span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">Expired '.htmlspecialchars(abs($diffDays)).' days ago</span>';
                          }
                        }
                      ?>
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

            <!-- Expired Certificates (Reclaim Available) -->
            <?php if (!empty($expired_certificates)): ?>
            <div class="mt-10">
              <h2 class="text-xl font-semibold mb-4 text-red-600">⚠️ Expired Certificates</h2>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($expired_certificates as $cert): ?>
                  <?php
                    // Check if eligible to reclaim (all required patches must be non-expired)
                    $eligible = isCertificateEligible($con3, $emp_id, $cert['Certificate_ID']);
                    
                    // Get required patch names
                    $reqPatchNames = [];
                    $mapStmt = sqlsrv_query($con3, "IF OBJECT_ID('dbo.Certificate_Patches','U') IS NULL SELECT 0 as cnt ELSE SELECT cp.Patch_ID, p.Patch_Name FROM dbo.Certificate_Patches cp JOIN dbo.Patches p ON cp.Patch_ID = p.Patch_ID WHERE cp.Certificate_ID = ?", [$cert['Certificate_ID']]);
                    if ($mapStmt) {
                      while ($rr = sqlsrv_fetch_array($mapStmt, SQLSRV_FETCH_ASSOC)) {
                        if (isset($rr['Patch_Name'])) $reqPatchNames[] = $rr['Patch_Name'];
                      }
                      sqlsrv_free_stmt($mapStmt);
                    }
                  ?>
                  <div class="bg-white rounded-2xl shadow hover:shadow-lg transition overflow-hidden border-l-4 border-red-500">
                    <div class="p-6">
                      <div class="flex items-start justify-between mb-4">
                        <div class="text-4xl">🏅</div>
                        <span class="inline-block px-3 py-1 text-xs font-semibold text-red-600 bg-red-50 rounded-full">EXPIRED</span>
                      </div>
                      <h3 class="text-lg font-bold text-slate-800 mb-2"><?php echo htmlspecialchars($cert['Certificate_Name']); ?></h3>
                      <p class="text-sm text-slate-600 mb-4"><?php echo htmlspecialchars($cert['Certificate_Description'] ?? 'Professional certification'); ?></p>
                      
                      <div class="space-y-1 text-sm text-slate-600 mb-4">
                        <div class="flex items-center justify-between">
                          <span>Originally Earned:</span>
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
                        <div class="flex items-center justify-between text-red-600">
                          <span>Expired On:</span>
                          <span class="font-medium">
                            <?php 
                              $exp = $cert['Expiration_Date'];
                              if ($exp instanceof DateTime) {
                                echo $exp->format('M d, Y');
                              } else {
                                echo htmlspecialchars($exp);
                              }
                            ?>
                          </span>
                        </div>
                      </div>
                      
                      <?php if (!empty($reqPatchNames)): ?>
                        <div class="text-sm text-slate-600 mb-3">
                          <span class="font-semibold">Required patches:</span><br>
                          <span class="text-xs"><?php echo htmlspecialchars(implode(', ', $reqPatchNames)); ?></span>
                        </div>
                      <?php endif; ?>
                      
                      <form method="POST" action="">
                        <input type="hidden" name="action" value="claim">
                        <input type="hidden" name="cert_id" value="<?php echo $cert['Certificate_ID']; ?>">
                        <?php if ($eligible): ?>
                          <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold transition">
                            ✓ Reclaim Certificate
                          </button>
                        <?php else: ?>
                          <button type="button" disabled class="w-full px-4 py-2 bg-slate-300 text-slate-500 rounded-lg font-semibold cursor-not-allowed">
                            🔒 Patches Required
                          </button>
                          <p class="text-xs text-red-600 mt-2 text-center">Complete all required patches to reclaim</p>
                        <?php endif; ?>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

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
