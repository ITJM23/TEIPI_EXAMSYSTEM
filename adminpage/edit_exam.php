<?php
// edit_exam.php — Edit Exam Details

include "../includes/sessions.php";
include "../includes/db.php";

date_default_timezone_set('Asia/Manila');

$messages = [];
$errors = [];
$exam = null;

// Get exam ID
$exam_id = intval($_GET['exam_id'] ?? 0);

if ($exam_id <= 0) {
    header("Location: adminindex.php");
    exit;
}

// Fetch exam details
$sql = "SELECT Exam_ID, Exam_Title, Description FROM teipiexam.dbo.Exams WHERE Exam_ID = ?";
$stmt = sqlsrv_query($con3, $sql, [$exam_id]);
if ($stmt && ($exam = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    sqlsrv_free_stmt($stmt);
} else {
    header("Location: adminindex.php");
    exit;
}

// Check if this exam currently has access protection
$has_access = false;
$accStmt = @sqlsrv_query($con3, "SELECT Access_PasswordHash FROM dbo.Exam_Access WHERE Exam_ID = ?", [$exam_id]);
if ($accStmt) {
    $accRow = sqlsrv_fetch_array($accStmt, SQLSRV_FETCH_ASSOC);
    if ($accRow && isset($accRow['Access_PasswordHash'])) $has_access = true;
    sqlsrv_free_stmt($accStmt);
}

// ---------- Handle Update ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['exam_title'] ?? '');
    $desc  = trim($_POST['exam_description'] ?? '');
    $access_password = trim($_POST['access_password'] ?? '');
    
    if ($title === '') {
        $errors[] = 'Exam title is required.';
    } else {
        $sql = "UPDATE teipiexam.dbo.Exams SET Exam_Title = ?, Description = ? WHERE Exam_ID = ?";
        $stmt = sqlsrv_query($con3, $sql, [$title, $desc, $exam_id]);
        if ($stmt === false) {
            $errors[] = 'Failed to update exam: ' . print_r(sqlsrv_errors(), true);
        } else {
            $messages[] = 'Exam updated successfully.';
            $exam['Exam_Title'] = $title;
            $exam['Description'] = $desc;
            sqlsrv_free_stmt($stmt);
        }
    }

    // Handle access password if provided (create/replace or remove)
    // ensure Exam_Access table exists
    $create_access = "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'dbo.Exam_Access') AND type in (N'U')) CREATE TABLE dbo.Exam_Access (Access_ID INT IDENTITY(1,1) PRIMARY KEY, Exam_ID INT NOT NULL, Access_PasswordHash VARBINARY(255) NOT NULL, Date_Created DATETIME DEFAULT GETDATE(), Created_By VARCHAR(100))";
    @sqlsrv_query($con3, $create_access);

    if ($access_password !== '') {
        $hash = password_hash($access_password, PASSWORD_DEFAULT);
        $delAcc = "IF OBJECT_ID('dbo.Exam_Access','U') IS NOT NULL DELETE FROM dbo.Exam_Access WHERE Exam_ID = ?";
        sqlsrv_query($con3, $delAcc, [$exam_id]);
        $insAcc = "INSERT INTO dbo.Exam_Access (Exam_ID, Access_PasswordHash, Created_By) VALUES (?, CONVERT(VARBINARY(255), ?), ?)";
        sqlsrv_query($con3, $insAcc, [$exam_id, $hash, ($username ?? '')]);
        $messages[] = 'Exam access password updated.';
        $has_access = true;
    } else {
        // remove access if exists
        $delAcc2 = "IF OBJECT_ID('dbo.Exam_Access','U') IS NOT NULL DELETE FROM dbo.Exam_Access WHERE Exam_ID = ?";
        sqlsrv_query($con3, $delAcc2, [$exam_id]);
        $messages[] = 'Exam access removed (public).';
        $has_access = false;
    }
}

// Fetch questions for this exam
$questions = [];
$q_sql = "SELECT Question_ID, Question, Question_type, Question_Pic FROM teipiexam.dbo.Questions WHERE Exam_ID = ? ORDER BY Question_ID";
$q_stmt = sqlsrv_query($con3, $q_sql, [$exam_id]);
if ($q_stmt) {
    while ($q = sqlsrv_fetch_array($q_stmt, SQLSRV_FETCH_ASSOC)) $questions[] = $q;
    sqlsrv_free_stmt($q_stmt);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Exam</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-4">
<h2>Edit Exam</h2>
<div>
<a href="adminindex.php" class="btn btn-outline-secondary">Back to Dashboard</a>
<a href="../logout.php" class="btn btn-danger">Logout</a>
</div>
</div>

<?php foreach ($messages as $m): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($m); ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><pre><?php echo htmlspecialchars($err); ?></pre></div>
<?php endforeach; ?>

<div class="row">
<div class="col-md-5">
<div class="card p-4 shadow-sm mb-4">
<h5 class="mb-3">Exam Details</h5>
<form method="POST">
<div class="mb-3">
<label class="form-label">Exam Title <span class="text-danger">*</span></label>
<input class="form-control" name="exam_title" value="<?php echo htmlspecialchars($exam['Exam_Title']); ?>" required>
</div>
<div class="mb-3">
<label class="form-label">Description</label>
<textarea class="form-control" name="exam_description" rows="4"><?php echo htmlspecialchars($exam['Description']); ?></textarea>
</div>
<div class="mb-3">
<label class="form-label">Exam Access Password (optional)</label>
<input type="password" name="access_password" class="form-control" placeholder="Set an access password to lock the exam">
<small class="form-text text-muted">Leave empty to keep the exam public. Current: <?php echo $has_access ? '<span class="badge bg-danger">Locked</span>' : '<span class="badge bg-success">Public</span>'; ?></small>
</div>
<div class="d-flex justify-content-between">
<a href="adminindex.php" class="btn btn-secondary">Cancel</a>
<button class="btn btn-primary">Update Exam</button>
</div>
</form>
</div>
</div>

<div class="col-md-7">
<div class="card p-4 shadow-sm">
<div class="d-flex justify-content-between align-items-center mb-3">
<h5 class="mb-0">Questions (<?php echo count($questions); ?>)</h5>
<a href="add_question.php" class="btn btn-sm btn-success">+ Add Question</a>
</div>

<?php if (empty($questions)): ?>
<p class="text-muted">No questions yet. Add some questions to this exam.</p>
<?php else: ?>
<div class="list-group">
<?php foreach ($questions as $idx => $q): ?>
<div class="list-group-item">
<div class="d-flex justify-content-between align-items-start">
<div class="flex-grow-1">
<strong>Q<?php echo $idx + 1; ?>:</strong> <?php echo htmlspecialchars($q['Question']); ?>
<br>
<small class="text-muted">
Type: <span class="badge bg-info"><?php echo htmlspecialchars($q['Question_type']); ?></span>
<?php if ($q['Question_Pic']): ?>
<span class="badge bg-secondary">Has Image</span>
<?php endif; ?>
</small>
</div>
<div>
<a href="edit_question.php?question_id=<?php echo $q['Question_ID']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
<a href="delete_question.php?question_id=<?php echo $q['Question_ID']; ?>&exam_id=<?php echo $exam_id; ?>" 
   onclick="return confirm('Delete this question?');" 
   class="btn btn-sm btn-outline-danger">Delete</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
</div>
</div>
</body>
</html>