<?php
include "../includes/sessions.php";
include "../includes/db.php";

date_default_timezone_set('Asia/Manila');

$question_id = intval($_GET['question_id'] ?? 0);
if ($question_id <= 0) {
    die("Invalid question.");
}

$messages = [];
$errors   = [];

// Fetch question
$sql = "SELECT Question_ID, Question, Question_type, Question_Pic, Exam_ID 
        FROM teipiexam.dbo.Questions WHERE Question_ID = ?";
$stmt = sqlsrv_query($con3, $sql, [$question_id]);

$question = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$question) {
    die("Question not found.");
}
$exam_id = $question['Exam_ID'];
sqlsrv_free_stmt($stmt);

// Fetch correct answer
$sql = "SELECT correct_answer_ID, Correct_answer 
        FROM teipiexam.dbo.Correct_Answers WHERE Question_ID = ?";
$ca_stmt = sqlsrv_query($con3, $sql, [$question_id]);
$correct = sqlsrv_fetch_array($ca_stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($ca_stmt);

// Fetch incorrect answers
$incorrect_list = [];
$ia_stmt = sqlsrv_query($con3,
    "SELECT incorrect_answer_ID, Incorrect_answer
     FROM teipiexam.dbo.Incorrect_Answers WHERE Question_ID = ?", [$question_id]);

while ($ia = sqlsrv_fetch_array($ia_stmt, SQLSRV_FETCH_ASSOC)) {
    $incorrect_list[] = $ia;
}
sqlsrv_free_stmt($ia_stmt);

// ---------- UPDATE QUESTION ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $q_text = trim($_POST['question_text']);
    $q_type = trim($_POST['question_type']);

    if ($q_text === '') {
        $errors[] = "Question text is required.";
    }

    // Handle picture
    $new_pic_name = $question['Question_Pic'];
    if (!empty($_FILES['question_pic']['name'])) {
        $ext = pathinfo($_FILES['question_pic']['name'], PATHINFO_EXTENSION);
        $new_pic_name = "qpic_" . time() . "." . $ext;
        move_uploaded_file($_FILES['question_pic']['tmp_name'], "/../uploads/" . $new_pic_name);
    }

    if (empty($errors)) {

        // Update question
        $sql = "UPDATE teipiexam.dbo.Questions
                SET Question=?, Question_type=?, Question_Pic=?
                WHERE Question_ID=?";
        sqlsrv_query($con3, $sql, [$q_text, $q_type, $new_pic_name, $question_id]);

        // Update correct answer
        $correct_ans = trim($_POST['correct_answer']);
        sqlsrv_query($con3,
            "UPDATE teipiexam.dbo.Correct_Answers SET Correct_answer=? WHERE Question_ID=?",
            [$correct_ans, $question_id]
        );

        // Update incorrect answers
        if (isset($_POST['incorrect'])) {
            foreach ($_POST['incorrect'] as $id => $text) {
                $text = trim($text);
                if ($text !== '') {
                    sqlsrv_query($con3,
                        "UPDATE teipiexam.dbo.Incorrect_Answers SET Incorrect_answer=? 
                         WHERE incorrect_answer_ID=?",
                        [$text, $id]
                    );
                }
            }
        }

        // Add new incorrect answers
        if (isset($_POST['new_incorrect'])) {
            foreach ($_POST['new_incorrect'] as $text) {
                $text = trim($text);
                if ($text !== '') {
                    sqlsrv_query($con3,
                        "INSERT INTO teipiexam.dbo.Incorrect_Answers (Question_ID, Incorrect_answer)
                         VALUES (?, ?)",
                        [$question_id, $text]
                    );
                }
            }
        }

        $messages[] = "Question updated successfully!";
        // Re-fetch updated data
        header("Location: edit_question.php?question_id=$question_id&updated=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Question</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<?php include 'admin_navbar.php'; ?>
<div class="container py-4">

<a href="edit_exam.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-secondary mb-3">← Back</a>

<h3>Edit Question</h3>

<?php foreach ($messages as $m): ?>
<div class="alert alert-success"><?= $m ?></div>
<?php endforeach; ?>

<?php foreach ($errors as $e): ?>
<div class="alert alert-danger"><?= $e ?></div>
<?php endforeach; ?>

<div class="card p-4 shadow-sm">

<form method="POST" enctype="multipart/form-data">

<!-- Question text -->
<div class="mb-3">
<label class="form-label">Question</label>
<textarea name="question_text" class="form-control" rows="3"><?= htmlspecialchars($question['Question']); ?></textarea>
</div>

<!-- Type -->
<div class="mb-3">
<label class="form-label">Question Type</label>
<select name="question_type" class="form-control">
    <option value="multiple_choice" <?= $question['Question_type']=="Multiple Choice"?"selected":"" ?>>Multiple Choice</option>
    <option value="enumeration" <?= $question['Question_type']=="Identification"?"selected":"" ?>>Identification</option>
    <option value="true_false" <?= $question['Question_type']=="true_false"?"selected":"" ?>>True or false</option>
</select>
</div>

<!-- Picture -->
<div class="mb-3">
<label class="form-label">Picture (optional)</label><br>
<?php if ($question['Question_Pic']): ?>
<img src="/../uploads/<?php echo $question['Question_Pic']; ?>" style="max-height:120px;">
<br><br>
<?php endif; ?>
<input type="file" name="question_pic" class="form-control">
</div>

<hr>

<!-- Correct Answer -->
<div class="mb-3">
<label class="form-label">Correct Answer</label>
<input type="text" name="correct_answer" class="form-control"
       value="<?= htmlspecialchars($correct['Correct_answer'] ?? '') ?>">
</div>

<hr>

<!-- Incorrect Answers -->
<h5>Incorrect Answers</h5>
<?php foreach ($incorrect_list as $ia): ?>
<div class="mb-2">
<input type="text" class="form-control"
       name="incorrect[<?= $ia['incorrect_answer_ID'] ?>]"
       value="<?= htmlspecialchars($ia['Incorrect_answer']); ?>">
</div>
<?php endforeach; ?>

<!-- Add new incorrect answers -->
<div id="newIncorrectArea"></div>

<button type="button" class="btn btn-outline-secondary btn-sm mt-2"
        onclick="addIncorrect()">+ Add Incorrect Answer</button>

<script>
function addIncorrect() {
    let div = document.createElement("div");
    div.innerHTML = `<input class="form-control mt-2" name="new_incorrect[]" placeholder="New incorrect answer">`;
    document.getElementById('newIncorrectArea').appendChild(div);
}
</script>

<hr>

<button class="btn btn-primary">Update Question</button>

</form>
</div>
</div>

</body>
</html>
