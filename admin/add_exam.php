<?php
include "../includes/sessions.php";
include "../includes/db.php";

$edit_id = $_GET['edit'] ?? null;
$title = $desc = "";

if ($edit_id) {
    $sql = "SELECT * FROM Exams WHERE Exam_ID = ?";
    $stmt = sqlsrv_query($con3, $sql, [$edit_id]);
    $exam = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $title = $exam['Exam_Title'];
    $desc = $exam['Description'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['Exam_Title'];
    $desc = $_POST['Description'];

    if ($edit_id) {
        $sql = "UPDATE Exams SET Exam_Title = ?, Description = ? WHERE Exam_ID = ?";
        sqlsrv_query($con3, $sql, [$title, $desc, $edit_id]);
    } else {
        $sql = "INSERT INTO Exams (Exam_Title, Description) VALUES (?, ?)";
        sqlsrv_query($con3, $sql, [$title, $desc]);
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
    <button type="submit" class="btn btn-success">Save</button>
    <a href="exams.php" class="btn btn-secondary">Cancel</a>
</form>

</body>
</html>
