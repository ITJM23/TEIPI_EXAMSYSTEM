<?php
include "../includes/sessions.php";
include "../includes/db.php";

date_default_timezone_set('Asia/Manila');

$messages = [];
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $created_by  = $_SESSION['Emp_Id'] ?? null;  // Or your admin ID field

    if ($title === '') {
        $errors[] = "Exam title is required.";
    }

    if (empty($errors)) {
        $sql = "INSERT INTO teipiexam.dbo.Exams (Exam_Title, Description, Date_Created, Created_By)
                VALUES (?, ?, GETDATE(), ?)";

        $params = [$title, $description, $created_by];

        $stmt = sqlsrv_query($con3, $sql, $params);

        if ($stmt === false) {
            $errors[] = print_r(sqlsrv_errors(), true);
        } else {
            $messages[] = "Exam created successfully!";
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create New Exam</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container py-4">

<h3>Create New Exam</h3>

<a href="adminindex.php" class="btn btn-secondary mb-3">← Back to Exam List</a>

<?php foreach ($messages as $m): ?>
<div class="alert alert-success"><?php echo $m; ?></div>
<?php endforeach; ?>

<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><pre><?php echo $err; ?></pre></div>
<?php endforeach; ?>

<div class="card p-4 shadow-sm">
<form method="post">

<div class="mb-3">
<label class="form-label">Exam Title</label>
<input type="text" name="title" class="form-control" placeholder="Enter exam title">
</div>

<div class="mb-3">
<label class="form-label">Description</label>
<textarea name="description" class="form-control" rows="3" placeholder="Enter description"></textarea>
</div>

<button type="submit" class="btn btn-primary">Create Exam</button>

</form>
</div>

</div>
</body>
</html>
