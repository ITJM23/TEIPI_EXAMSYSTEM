<?php
include "../includes/sessions.php";
include "../includes/db.php";

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

if ($type === 'exam') {
    sqlsrv_query($con3, "DELETE FROM Exams WHERE Exam_ID = ?", [$id]);
} elseif ($type === 'question') {
    sqlsrv_query($con3, "DELETE FROM Questions WHERE Question_ID = ?", [$id]);
    sqlsrv_query($con3, "DELETE FROM Correct_Answers WHERE Question_ID = ?", [$id]);
    sqlsrv_query($con3, "DELETE FROM Incorrect_Answers WHERE Question_ID = ?", [$id]);
}

header("Location: " . ($type === 'exam' ? "exams.php" : "questions.php"));
exit;
?>
yes