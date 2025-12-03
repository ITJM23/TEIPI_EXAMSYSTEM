<?php
include "../includes/sessions.php";
include "../includes/db.php";

$okMsg = '';
$errMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $doPatches = isset($_POST['recalc_patches']);
  $doCerts = isset($_POST['recalc_certs']);

  if ($doPatches) {
    // Ensure columns
    @sqlsrv_query($con3, "IF COL_LENGTH('dbo.Employee_Patches','Expiration_Date') IS NULL ALTER TABLE dbo.Employee_Patches ADD Expiration_Date DATETIME NULL");
    @sqlsrv_query($con3, "IF COL_LENGTH('dbo.Patches','Expiration_Days') IS NULL ALTER TABLE dbo.Patches ADD Expiration_Days INT NULL");

    // Recompute Expiration_Date for all Employee_Patches based on current Patches policy
    $sql = "UPDATE ep
            SET ep.Expiration_Date = CASE
              WHEN ISNULL(p.is_Unlimited,0)=1 THEN NULL
              WHEN p.Expiration_Days IS NOT NULL THEN DATEADD(DAY, p.Expiration_Days, ISNULL(ep.Date_Earned, GETDATE()))
              WHEN p.Expiration_Date IS NOT NULL THEN p.Expiration_Date
              ELSE ep.Expiration_Date
            END
            FROM dbo.Employee_Patches ep
            JOIN dbo.Patches p ON ep.Patch_ID = p.Patch_ID";
    $res = sqlsrv_query($con3, $sql);
    if ($res === false) { $errMsg .= 'Failed to recalc patches. '; } else { $okMsg .= 'Patches recalculated. '; }
  }

  if ($doCerts) {
    // Ensure columns
    @sqlsrv_query($con3, "IF COL_LENGTH('dbo.Employee_Certificates','Expiration_Date') IS NULL ALTER TABLE dbo.Employee_Certificates ADD Expiration_Date DATETIME NULL");
    @sqlsrv_query($con3, "IF COL_LENGTH('dbo.Certificates','Expiration_Days') IS NULL ALTER TABLE dbo.Certificates ADD Expiration_Days INT NULL");

    // Recompute Expiration_Date for all Employee_Certificates based on current Certificates policy
    $sql = "UPDATE ec
            SET ec.Expiration_Date = CASE
              WHEN ISNULL(c.is_Unlimited,0)=1 THEN NULL
              WHEN c.Expiration_Days IS NOT NULL THEN DATEADD(DAY, c.Expiration_Days, ISNULL(ec.Date_Earned, GETDATE()))
              WHEN c.Expiration_Date IS NOT NULL THEN c.Expiration_Date
              ELSE ec.Expiration_Date
            END
            FROM dbo.Employee_Certificates ec
            JOIN dbo.Certificates c ON ec.Certificate_ID = c.Certificate_ID";
    $res = sqlsrv_query($con3, $sql);
    if ($res === false) { $errMsg .= 'Failed to recalc certificates.'; } else { $okMsg .= 'Certificates recalculated.'; }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>Recalculate Expirations</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50">
<?php include 'admin_navbar.php'; ?>
  <div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Recalculate Expiration Dates</h1>
    <?php if (!empty($okMsg)): ?><div class="mb-3 p-3 bg-emerald-50 text-emerald-700 rounded"><?php echo htmlspecialchars($okMsg); ?></div><?php endif; ?>
    <?php if (!empty($errMsg)): ?><div class="mb-3 p-3 bg-red-50 text-red-700 rounded"><?php echo htmlspecialchars($errMsg); ?></div><?php endif; ?>
    <p class="text-slate-600 mb-4">Use this after changing patch/certificate policies to apply the new rules to existing employee awards.</p>
    <form method="POST" class="space-y-3">
      <label class="flex items-center gap-2">
        <input type="checkbox" name="recalc_patches" />
        <span>Recalculate Employee Patches</span>
      </label>
      <label class="flex items-center gap-2">
        <input type="checkbox" name="recalc_certs" />
        <span>Recalculate Employee Certificates</span>
      </label>
      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Run</button>
    </form>
  </div>
</body>
</html>
