<?php
// delete_question.php — Delete Question Handler

include "../includes/sessions.php";
include "../includes/db.php";

date_default_timezone_set('Asia/Manila');

$question_id = intval($_GET['question_id'] ?? 0);
$exam_id = intval($_GET['exam_id'] ?? 0);

if ($question_id <= 0) {
    header("Location: adminindex.php");
    exit;
}

// Get the question's image filename before deleting
$img_sql = "SELECT Question_Pic FROM teipiexam.dbo.Questions WHERE Question_ID = ?";
$img_stmt = sqlsrv_query($con3, $img_sql, [$question_id]);
$image_file = null;
if ($img_stmt && ($row = sqlsrv_fetch_array($img_stmt, SQLSRV_FETCH_ASSOC))) {
    $image_file = $row['Question_Pic'];
    sqlsrv_free_stmt($img_stmt);
}

// Delete related records
$deleteQueries = [
    "DELETE FROM teipiexam.dbo.Incorrect_Answers WHERE Question_ID = ?",
    "DELETE FROM teipiexam.dbo.Correct_Answers WHERE Question_ID = ?",
    "DELETE FROM teipiexam.dbo.Questions WHERE Question_ID = ?"
];

$ok = true;
foreach ($deleteQueries as $sql) {
    $stmt = sqlsrv_query($con3, $sql, [$question_id]);
    if ($stmt === false) {
        $ok = false;
        echo "<pre style='color:red;'>Error deleting question:\n" . print_r(sqlsrv_errors(), true) . "</pre>";
        break;
    }
}

// Delete image file if exists
if ($ok && $image_file) {
    $uploadDir = __DIR__ . '/../uploads/';
    $filepath = $uploadDir . $image_file;
    if (file_exists($filepath)) {
        unlink($filepath);
    }
}

if ($ok) {
    $redirect = $exam_id > 0 ? "edit_exam.php?exam_id={$exam_id}" : "adminindex.php";
    echo "<script>alert('Question deleted successfully.'); window.location='{$redirect}';</script>";
} else {
    echo "<script>alert('Failed to delete question.'); window.history.back();</script>";
}
exit;