<?php
include "../includes/sessions.php";
include "../includes/db.php";


$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : null;
$title = $desc = "";
$editing_patches = [];

// fetch all patches for selection
$all_patches = [];
$pstmt = sqlsrv_query($con3, "SELECT Patch_ID, Patch_Name FROM dbo.Patches ORDER BY Patch_Name");
if ($pstmt) {
    while ($r = sqlsrv_fetch_array($pstmt, SQLSRV_FETCH_ASSOC)) {
        $all_patches[] = $r;
    }
    sqlsrv_free_stmt($pstmt);
}

if ($edit_id) {
    $sql = "SELECT * FROM Exams WHERE Exam_ID = ?";
    $stmt = sqlsrv_query($con3, $sql, [$edit_id]);
    $exam = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $title = $exam['Exam_Title'];
    $desc = $exam['Description'];

    // load existing exam->patch mappings
    $mstmt = sqlsrv_query($con3, "IF OBJECT_ID('dbo.Exam_Patches','U') IS NULL SELECT 0 as cnt ELSE SELECT Patch_ID FROM dbo.Exam_Patches WHERE Exam_ID = ?", [$edit_id]);
    if ($mstmt) {
        while ($mr = sqlsrv_fetch_array($mstmt, SQLSRV_FETCH_ASSOC)) {
            if (isset($mr['Patch_ID'])) $editing_patches[] = $mr['Patch_ID'];
        }
        sqlsrv_free_stmt($mstmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['Exam_Title'] ?? '');
    $desc = trim($_POST['Description'] ?? '');
    $selected_patches = $_POST['patches'] ?? [];
    $new_patches_raw = trim($_POST['new_patches'] ?? '');

    if ($edit_id) {
        $sql = "UPDATE Exams SET Exam_Title = ?, Description = ? WHERE Exam_ID = ?";
        sqlsrv_query($con3, $sql, [$title, $desc, $edit_id]);

        // handle new patches and mappings below
        $exam_id = $edit_id;
    } else {
        $sql = "INSERT INTO Exams (Exam_Title, Description) VALUES (?, ?)";
        sqlsrv_query($con3, $sql, [$title, $desc]);

        // get inserted exam id
        $idRes = sqlsrv_query($con3, "SELECT SCOPE_IDENTITY() AS id");
        $exam_id = null;
        if ($idRes) {
            $rr = sqlsrv_fetch_array($idRes, SQLSRV_FETCH_ASSOC);
            $exam_id = $rr['id'] ?? null;
            sqlsrv_free_stmt($idRes);
        }
        if (empty($exam_id)) {
            $s = sqlsrv_query($con3, "SELECT TOP 1 Exam_ID FROM Exams WHERE Exam_Title = ? ORDER BY Exam_ID DESC", [$title]);
            if ($s) {
                $r2 = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC);
                $exam_id = $r2['Exam_ID'] ?? null;
                sqlsrv_free_stmt($s);
            }
        }
    }

    // ensure mapping table exists
    $create_map = "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'dbo.Exam_Patches') AND type in (N'U')) CREATE TABLE dbo.Exam_Patches (Exam_ID INT NOT NULL, Patch_ID INT NOT NULL)";
    @sqlsrv_query($con3, $create_map);

    // process new patches (comma-separated)
    if (!empty($new_patches_raw)) {
        $names = array_filter(array_map('trim', explode(',', $new_patches_raw)));
        foreach ($names as $pname) {
            if ($pname === '') continue;
            $chk = sqlsrv_query($con3, "SELECT Patch_ID FROM dbo.Patches WHERE Patch_Name = ?", [$pname]);
            $existing_id = null;
            if ($chk) {
                $er = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC);
                if ($er && isset($er['Patch_ID'])) $existing_id = $er['Patch_ID'];
                sqlsrv_free_stmt($chk);
            }
            if (empty($existing_id)) {
                $insPatch = "INSERT INTO dbo.Patches (Patch_Name) VALUES (?)";
                $r = sqlsrv_query($con3, $insPatch, [$pname]);
                if ($r) {
                    $idRes2 = sqlsrv_query($con3, "SELECT SCOPE_IDENTITY() AS id");
                    if ($idRes2) {
                        $rr = sqlsrv_fetch_array($idRes2, SQLSRV_FETCH_ASSOC);
                        $existing_id = $rr['id'] ?? null;
                        sqlsrv_free_stmt($idRes2);
                    }
                    if (empty($existing_id)) {
                        $s = sqlsrv_query($con3, "SELECT TOP 1 Patch_ID FROM dbo.Patches WHERE Patch_Name = ? ORDER BY Patch_ID DESC", [$pname]);
                        if ($s) {
                            $row2 = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC);
                            $existing_id = $row2['Patch_ID'] ?? null;
                            sqlsrv_free_stmt($s);
                        }
                    }
                }
            }
            if (!empty($existing_id)) $selected_patches[] = $existing_id;
        }
    }

    // replace mappings for this exam
    if (!empty($exam_id)) {
        $del = "DELETE FROM dbo.Exam_Patches WHERE Exam_ID = ?";
        sqlsrv_query($con3, $del, [$exam_id]);
        $ins = "INSERT INTO dbo.Exam_Patches (Exam_ID, Patch_ID) VALUES (?, ?)";
        if (!empty($selected_patches) && is_array($selected_patches)) {
            foreach ($selected_patches as $pid) {
                $pid = intval($pid);
                if ($pid > 0) sqlsrv_query($con3, $ins, [$exam_id, $pid]);
            }
        }
    }

    header("Location: exams.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add/Edit Exam</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4 bg-light">

<h2><?php echo $edit_id ? "Edit" : "Add"; ?> Exam</h2>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">Exam Title</label>
        <input type="text" name="Exam_Title" class="form-control" value="<?php echo htmlspecialchars($title); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="Description" class="form-control"><?php echo htmlspecialchars($desc); ?></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Linked Patches (optional)</label>
        <select name="patches[]" multiple class="form-control" size="5">
            <?php foreach ($all_patches as $p): ?>
                <option value="<?php echo $p['Patch_ID']; ?>" <?php echo in_array($p['Patch_ID'], $editing_patches) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['Patch_Name']); ?></option>
            <?php endforeach; ?>
        </select>
        <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple patches.</small>
    </div>
    <div class="mb-3">
        <label class="form-label">Add New Patches (comma-separated)</label>
        <input type="text" name="new_patches" class="form-control" placeholder="e.g., Intro to PHP, Advanced SQL">
        <small class="form-text text-muted">New patch names will be created and linked to this exam.</small>
    </div>
    <button type="submit" class="btn btn-success">Save</button>
    <a href="exams.php" class="btn btn-secondary">Cancel</a>
</form>

</body>
</html>
