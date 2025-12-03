<?php
include "../includes/sessions.php";
include "../includes/db.php";



// Ensure table columns exist
@sqlsrv_query($con3, "IF COL_LENGTH('dbo.Patches','Expiration_Date') IS NULL ALTER TABLE dbo.Patches ADD Expiration_Date DATETIME NULL");
@sqlsrv_query($con3, "IF COL_LENGTH('dbo.Patches','Expiration_Days') IS NULL ALTER TABLE dbo.Patches ADD Expiration_Days INT NULL");
@sqlsrv_query($con3, "IF COL_LENGTH('dbo.Patches','is_Unlimited') IS NULL ALTER TABLE dbo.Patches ADD is_Unlimited BIT DEFAULT 0");

// Handle updates
$update_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patch_id'])) {
    $pid = (int)($_POST['patch_id']);
    $isUnlimited = isset($_POST['is_unlimited']) ? 1 : 0;
    $expType = $_POST['exp_type'] ?? 'none'; // none | days | date
    $expValue = trim($_POST['exp_value'] ?? '');

    if ($isUnlimited) {
        $sql = "UPDATE dbo.Patches SET is_Unlimited = 1, Expiration_Date = NULL WHERE Patch_ID = ?";
        $ok = sqlsrv_query($con3, $sql, [$pid]);
    } else {
        if ($expType === 'days' && $expValue !== '' && is_numeric($expValue)) {
          $sql = "UPDATE dbo.Patches SET is_Unlimited = 0, Expiration_Days = ?, Expiration_Date = NULL WHERE Patch_ID = ?";
          $ok = sqlsrv_query($con3, $sql, [intval($expValue), $pid]);
        } elseif ($expType === 'date' && $expValue !== '') {
          $dateInput = date('Y-m-d', strtotime($expValue));
          if ($dateInput === false || $dateInput === '' || $dateInput === '1970-01-01') {
            $update_msg = 'Invalid date format. Use YYYY-MM-DD.';
            $ok = false;
          } else {
          $sql = "UPDATE dbo.Patches SET is_Unlimited = 0, Expiration_Date = ?, Expiration_Days = NULL WHERE Patch_ID = ?";
          $ok = sqlsrv_query($con3, $sql, [$dateInput, $pid]);
          }
        } else {
          $sql = "UPDATE dbo.Patches SET is_Unlimited = 0, Expiration_Date = NULL, Expiration_Days = NULL WHERE Patch_ID = ?";
            $ok = sqlsrv_query($con3, $sql, [$pid]);
        }
    }
    if ($update_msg === '') {
      $update_msg = $ok ? 'Patch updated.' : 'Update failed.';
    }
}

// Fetch patches
$rows = [];
$stmt = sqlsrv_query($con3, "SELECT Patch_ID, Patch_Name, Patch_Description, Expiration_Date, Expiration_Days, is_Unlimited FROM dbo.Patches ORDER BY Patch_Name");
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $rows[] = $r; }
    sqlsrv_free_stmt($stmt);
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>Manage Patches</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50">
<?php include 'admin_navbar.php'; ?>
  <div class="max-w-6xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Manage Patches Expiration</h1>
    <div class="mb-4">
      <a href="recalculate_expirations.php" class="inline-flex items-center px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded" title="Recompute employee patch expirations based on current policies">Recalculate Expirations</a>
    </div>
    <?php if (!empty($update_msg)): ?>
      <div class="mb-4 p-3 rounded <?php echo ($update_msg==='Patch updated.')?'bg-emerald-50 text-emerald-700':'bg-red-50 text-red-700'; ?>"><?php echo htmlspecialchars($update_msg); ?></div>
    <?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php foreach ($rows as $row): ?>
        <div class="bg-white shadow rounded p-4">
          <div class="flex justify-between items-center mb-2">
            <div>
              <div class="text-lg font-semibold"><?php echo htmlspecialchars($row['Patch_Name']); ?></div>
              <div class="text-sm text-slate-500"><?php echo htmlspecialchars($row['Patch_Description'] ?? ''); ?></div>
            </div>
            <span class="px-2 py-1 text-xs rounded <?php echo ((int)($row['is_Unlimited'] ?? 0)===1)?'bg-slate-100 text-slate-700':'bg-indigo-50 text-indigo-700'; ?>">
              <?php echo ((int)($row['is_Unlimited'] ?? 0)===1)?'Unlimited':'Expiring'; ?>
            </span>
          </div>
          <form method="POST" class="space-y-2">
            <input type="hidden" name="patch_id" value="<?php echo (int)$row['Patch_ID']; ?>" />
            <label class="flex items-center gap-2">
              <input type="checkbox" name="is_unlimited" <?php echo ((int)($row['is_Unlimited'] ?? 0)===1)?'checked':''; ?> />
              <span class="text-sm">Unlimited</span>
            </label>
            <div class="text-sm text-slate-600">Set expiration by days or absolute date</div>
            <div class="flex items-center gap-2">
              <?php 
                $currentType = 'none';
                if ((int)($row['is_Unlimited'] ?? 0)===1) { $currentType = 'none'; }
                elseif (isset($row['Expiration_Days']) && $row['Expiration_Days']!==null) { $currentType = 'days'; }
                elseif (!empty($row['Expiration_Date'])) { $currentType = 'date'; }
                $currentValue = isset($row['Expiration_Days']) && $row['Expiration_Days']!==null ? $row['Expiration_Days'] : ($row['Expiration_Date'] instanceof DateTime ? $row['Expiration_Date']->format('Y-m-d') : ($row['Expiration_Date'] ?? ''));
              ?>
              <select name="exp_type" class="border rounded px-2 py-1">
                <option value="none" <?php echo $currentType==='none'?'selected':''; ?>>None</option>
                <option value="days" <?php echo $currentType==='days'?'selected':''; ?>>Days</option>
                <option value="date" <?php echo $currentType==='date'?'selected':''; ?>>Date</option>
              </select>
              <input name="exp_value" class="border rounded px-2 py-1 flex-1" placeholder="e.g. 60 or 2026-03-01" value="<?php echo htmlspecialchars($currentValue); ?>" />
            </div>
            <button class="mt-2 px-3 py-1.5 bg-indigo-600 text-white rounded">Save</button>
            <div class="text-xs text-slate-500 mt-1">Current: 
              <?php 
                if (isset($row['Expiration_Days']) && $row['Expiration_Days']!==null) {
                  echo htmlspecialchars($row['Expiration_Days']) . ' days';
                } else if (!empty($row['Expiration_Date'])) {
                  $d = $row['Expiration_Date'];
                  echo $d instanceof DateTime ? $d->format('Y-m-d') : htmlspecialchars($d);
                } else {
                  echo 'None';
                }
              ?>
            </div>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
