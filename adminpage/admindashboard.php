<?php
// admin_dashboard.php — with multiple enumeration answers support

include "../includes/sessions.php";
include "../includes/db.php";

date_default_timezone_set('Asia/Manila');

// ---------- Handle Delete Exam ----------
if (isset($_GET['delete_exam'])) {
    $exam_id = intval($_GET['delete_exam']);
    $deleteDetails = [
        "DELETE FROM dbo.Incorrect_Answers WHERE Question_ID IN (SELECT Question_ID FROM dbo.Questions WHERE Exam_ID = ?)",
        "DELETE FROM dbo.Correct_Answers WHERE Question_ID IN (SELECT Question_ID FROM dbo.Questions WHERE Exam_ID = ?)",
        "DELETE FROM dbo.AnswerDetails WHERE Answers_ID IN (SELECT Answers_ID FROM dbo.Answers WHERE Exam_ID = ?)",
        "DELETE FROM dbo.Answers WHERE Exam_ID = ?",
        "DELETE FROM dbo.Results WHERE Exam_ID = ?",
        "DELETE FROM dbo.Questions WHERE Exam_ID = ?",
        "DELETE FROM dbo.Exams WHERE Exam_ID = ?"
    ];
    $ok = true;
    foreach ($deleteDetails as $sql) {
        $stmt = sqlsrv_query($con3, $sql, [$exam_id]);
        if ($stmt === false) {
            $ok = false;
            echo "<pre style='color:red;'>Error deleting:\n" . print_r(sqlsrv_errors(), true) . "</pre>";
            break;
        }
    }
    if ($ok) {
        echo "<script>alert('Exam deleted successfully.'); window.location='admindashboard.php';</script>";
        exit;
    }
}

// ---------- Setup ----------
if (!isset($_SESSION)) session_start();
$uploadDir = __DIR__ . '/../uploads/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

$messages = [];
$errors = [];

// Helper: unique filename
function unique_filename($dir, $name) {
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = preg_replace('/[^A-Za-z0-9_-]/', '_', $base);
    $candidate = $base . '.' . $ext;
    $i = 0;
    while (file_exists($dir . $candidate)) {
        $i++;
        $candidate = $base . '_' . $i . '.' . $ext;
    }
    return $candidate;
}

// ---------- Handle Add Exam ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_exam') {
    $title = trim($_POST['exam_title'] ?? '');
    $desc  = trim($_POST['exam_description'] ?? '');
    if ($title === '') {
        $errors[] = 'Exam title is required.';
    } else {
        $sql = "INSERT INTO teipiexam.dbo.Exams (Exam_Title, Description) VALUES (?, ?)";
        $stmt = sqlsrv_query($con3, $sql, [$title, $desc]);
        if ($stmt === false) {
            $errors[] = 'Failed to create exam: ' . print_r(sqlsrv_errors(), true);
        } else {
            $messages[] = 'Exam created successfully.';
            sqlsrv_free_stmt($stmt);
        }
    }
}

// ---------- Handle Add Question ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_question') {
    $exam_id = intval($_POST['exam_id'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $q_type = trim($_POST['question_type'] ?? '');

    if ($exam_id <= 0) $errors[] = 'Select an exam first.';
    if ($question_text === '') $errors[] = 'Question text is required.';
    if (!in_array($q_type, ['multiple_choice', 'true_false', 'enumeration'])) $errors[] = 'Invalid question type.';

    // Image upload
    $savedImage = null;
    if (!empty($_FILES['question_image']) && $_FILES['question_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['question_image'];
        if ($f['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg','jpeg','png','gif','webp'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $fname = unique_filename($uploadDir, $f['name']);
                $dest = $uploadDir . $fname;
                if (move_uploaded_file($f['tmp_name'], $dest)) $savedImage = $fname;
                else $errors[] = 'Failed to move uploaded image.';
            } else {
                $errors[] = 'Unsupported image type.';
            }
        } else {
            $errors[] = 'Image upload error.';
        }
    }

    // Multiple choice logic
    $choices = [];
    $correct = null;
    if ($q_type === 'multiple_choice') {
        $rawChoices = $_POST['choice_text'] ?? [];
        foreach ($rawChoices as $c) {
            $c = trim($c);
            if ($c !== '') $choices[] = $c;
        }
        $correctIndex = intval($_POST['correct_index'] ?? -1);
        if (count($choices) < 2) $errors[] = 'At least 2 choices required.';
        if ($correctIndex < 0 || $correctIndex >= count($choices)) $errors[] = 'Mark a correct choice.';
        if (empty($errors)) $correct = $choices[$correctIndex];
    }

    // True/False
    if ($q_type === 'true_false') {
        $correct = ($_POST['tf_correct'] ?? 'True') === 'True' ? 'True' : 'False';
    }

    // Enumeration (multiple correct)
    if ($q_type === 'enumeration') {
        $correct_answers = $_POST['enumeration_answer'] ?? [];
        $correct_answers = array_filter(array_map('trim', $correct_answers), fn($a) => $a !== '');
    }

    // Insert into DB
    if (empty($errors)) {
        sqlsrv_begin_transaction($con3);
        $ok = true;

        $q_insert = "INSERT INTO teipiexam.dbo.Questions (Exam_ID, Question, Question_type, Question_Pic) VALUES (?, ?, ?, ?)";
        $q_stmt = sqlsrv_query($con3, $q_insert, [$exam_id, $question_text, $q_type, $savedImage]);
        if ($q_stmt === false) {
            $errors[] = 'Insert question failed: ' . print_r(sqlsrv_errors(), true);
            $ok = false;
        }

        $question_id = null;
        if ($ok) {
            $sel = "SELECT TOP 1 Question_ID FROM teipiexam.dbo.Questions WHERE Exam_ID = ? AND Question = ? ORDER BY Question_ID DESC";
            $sstmt = sqlsrv_query($con3, $sel, [$exam_id, $question_text]);
            if ($sstmt !== false && ($r = sqlsrv_fetch_array($sstmt, SQLSRV_FETCH_ASSOC))) {
                $question_id = $r['Question_ID'];
            } else {
                $errors[] = 'Failed to retrieve Question_ID.';
                $ok = false;
            }
            sqlsrv_free_stmt($sstmt);
        }

        if ($ok && $question_id) {
            if ($q_type === 'enumeration') {
                foreach ($correct_answers as $ans) {
                    $ca_sql = "INSERT INTO teipiexam.dbo.Correct_Answers (Question_ID, Correct_answer) VALUES (?, ?)";
                    $ca_stmt = sqlsrv_query($con3, $ca_sql, [$question_id, $ans]);
                    if ($ca_stmt === false) {
                        $errors[] = 'Failed to insert enumeration answer: ' . print_r(sqlsrv_errors(), true);
                        $ok = false;
                        break;
                    }
                }
            } else {
                $ca_sql = "INSERT INTO teipiexam.dbo.Correct_Answers (Question_ID, Correct_answer) VALUES (?, ?)";
                $ca_stmt = sqlsrv_query($con3, $ca_sql, [$question_id, $correct]);
                if ($ca_stmt === false) {
                    $errors[] = 'Failed to insert correct answer: ' . print_r(sqlsrv_errors(), true);
                    $ok = false;
                }

                if ($ok && $q_type === 'multiple_choice') {
                    foreach ($choices as $cText) {
                        if ($cText === $correct) continue;
                        $ia_sql = "INSERT INTO teipiexam.dbo.Incorrect_Answers (Question_ID, Incorrect_answer) VALUES (?, ?)";
                        $ia_stmt = sqlsrv_query($con3, $ia_sql, [$question_id, $cText]);
                        if ($ia_stmt === false) {
                            $errors[] = 'Failed to insert incorrect answer: ' . print_r(sqlsrv_errors(), true);
                            $ok = false;
                            break;
                        }
                    }
                }
            }
        }

        if ($ok) {
            if (sqlsrv_commit($con3) === false) {
                $errors[] = 'Commit failed: ' . print_r(sqlsrv_errors(), true);
                sqlsrv_rollback($con3);
            } else {
                $messages[] = 'Question added successfully.';
            }
        } else {
            sqlsrv_rollback($con3);
            if ($savedImage && file_exists($uploadDir . $savedImage)) unlink($uploadDir . $savedImage);
        }
    }
}

// ---------- Fetch Exams ----------
$exams = [];
$e_sql = "SELECT Exam_ID, Exam_Title, Description FROM teipiexam.dbo.Exams ORDER BY Exam_ID DESC";
$e_stmt = sqlsrv_query($con3, $e_sql);
if ($e_stmt) {
    while ($er = sqlsrv_fetch_array($e_stmt, SQLSRV_FETCH_ASSOC)) $exams[] = $er;
    sqlsrv_free_stmt($e_stmt);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — Exams</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'admin_navbar.php'; ?>
<div class="container py-4">
<h2>Admin Dashboard — Exams</h2>

<?php foreach ($messages as $m): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($m); ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><pre><?php echo htmlspecialchars($err); ?></pre></div>
<?php endforeach; ?>

<div class="row">
<div class="col-md-5">
<div class="card p-3 shadow-sm mb-4">
<h5>Add New Exam</h5>
<form method="POST">
<input type="hidden" name="action" value="add_exam">
<div class="mb-3">
<label class="form-label">Exam Title</label>
<input class="form-control" name="exam_title" required>
</div>
<div class="mb-3">
<label class="form-label">Description</label>
<textarea class="form-control" name="exam_description" rows="3"></textarea>
</div>
<button class="btn btn-primary">Create Exam</button>
</form>
</div>

<div class="card p-3 shadow-sm">
<h5>Existing Exams</h5>
<ul class="list-group">
<?php if (empty($exams)): ?>
<li class="list-group-item text-muted">No exams yet.</li>
<?php else: ?>
<?php foreach ($exams as $ex): ?>
<li class="list-group-item d-flex justify-content-between align-items-start">
<div>
<strong><?php echo htmlspecialchars($ex['Exam_Title']); ?></strong><br>
<small class="text-muted"><?php echo htmlspecialchars($ex['Description']); ?></small>
</div>
<div>
<a href="take_exam.php?exam_id=<?php echo urlencode($ex['Exam_ID']); ?>" class="btn btn-sm btn-outline-success me-1">View</a>
<a href="admindashboard.php?delete_exam=<?php echo $ex['Exam_ID']; ?>" onclick="return confirm('Delete this exam?');" class="btn btn-danger btn-sm">Delete</a>
</div>
</li>
<?php endforeach; ?>
<?php endif; ?>
</ul>
</div>
</div>

<div class="col-md-7">
<div class="card p-3 shadow-sm">
<h5>Add Question</h5>
<form method="POST" enctype="multipart/form-data" id="questionForm">
<input type="hidden" name="action" value="add_question">

<div class="mb-3">
<label class="form-label">Select Exam</label>
<select name="exam_id" class="form-select" required>
<option value="">-- choose exam --</option>
<?php foreach ($exams as $ex): ?>
<option value="<?php echo (int)$ex['Exam_ID']; ?>"><?php echo htmlspecialchars($ex['Exam_Title']); ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Question Text</label>
<textarea name="question_text" class="form-control" rows="3" required></textarea>
</div>

<div class="mb-3">
<label class="form-label">Question Type</label>
<select name="question_type" id="qtype" class="form-select" required>
<option value="multiple_choice">Multiple Choice</option>
<option value="true_false">True / False</option>
<option value="enumeration">Enumeration</option>
</select>
</div>

<div class="mb-3">
<label class="form-label">Question Image (optional)</label>
<input type="file" name="question_image" accept="image/*" class="form-control">
</div>

<!-- Multiple choice -->
<div id="mc_block">
<label class="form-label">Choices</label>
<div id="choices_wrap">
<div class="input-group mb-2 choice_row">
<span class="input-group-text">1</span>
<input type="text" name="choice_text[]" class="form-control" placeholder="Choice text">
<button type="button" class="btn btn-outline-secondary mark_correct">Correct</button>
</div>
<div class="input-group mb-2 choice_row">
<span class="input-group-text">2</span>
<input type="text" name="choice_text[]" class="form-control" placeholder="Choice text">
<button type="button" class="btn btn-outline-secondary mark_correct">Correct</button>
</div>
</div>
<div class="mb-3">
<button type="button" id="add_choice" class="btn btn-sm btn-secondary">+ Add choice</button>
<input type="hidden" name="correct_index" id="correct_index" value="-1">
</div>
</div>

<!-- True/False -->
<div id="tf_block" style="display:none;">
<label class="form-label">Correct Answer</label>
<select name="tf_correct" class="form-select">
<option value="True">True</option>
<option value="False">False</option>
</select>
</div>

<!-- Enumeration -->
<div id="enum_block" style="display:none;">
<label class="form-label">Correct Answers (optional)</label>
<div id="enum_answers_wrap">
<div class="input-group mb-2 enum_row">
<span class="input-group-text">1</span>
<input type="text" name="enumeration_answer[]" class="form-control" placeholder="Answer text">
</div>
</div>
<button type="button" id="add_enum_answer" class="btn btn-sm btn-secondary">+ Add another answer</button>
<div class="form-text">You can add multiple correct answers (case-insensitive matching).</div>
</div>

<div class="mt-3">
<button class="btn btn-primary">Add Question</button>
</div>
</form>
</div>

<div class="card p-3 mt-3 shadow-sm">
<h5>Notes</h5>
<ul>
<li>Multiple choice: mark one correct choice.</li>
<li>Enumeration: supports multiple correct answers.</li>
<li>Uploaded images are stored in <code>/uploads</code>.</li>
</ul>
</div>
</div>
</div>
</div>

<script>
(function(){
    const qtype=document.getElementById('qtype'),
    mc=document.getElementById('mc_block'),
    tf=document.getElementById('tf_block'),
    en=document.getElementById('enum_block');
    function toggle(){mc.style.display=qtype.value==='multiple_choice'?'block':'none';
        tf.style.display=qtype.value==='true_false'?'block':'none';
        en.style.display=qtype.value==='enumeration'?'block':'none';}
    qtype.addEventListener('change',toggle);toggle();

    document.getElementById('add_choice').addEventListener('click',function(){
        const wrap=document.getElementById('choices_wrap');
        const idx=wrap.querySelectorAll('.choice_row').length+1;
        const div=document.createElement('div');
        div.className='input-group mb-2 choice_row';
        div.innerHTML=`<span class="input-group-text">${idx}</span>
            <input type="text" name="choice_text[]" class="form-control" placeholder="Choice text">
            <button type="button" class="btn btn-outline-secondary mark_correct">Correct</button>`;
        wrap.appendChild(div);
        attachMarkButtons();
    });
    function attachMarkButtons(){
        document.querySelectorAll('.mark_correct').forEach(function(btn){
            btn.onclick=function(){
                const rows=Array.from(document.querySelectorAll('.choice_row'));
                const index=rows.indexOf(btn.closest('.choice_row'));
                document.getElementById('correct_index').value=index;
                rows.forEach(r=>r.style.border='');
                btn.closest('.choice_row').style.border='2px solid #0d6efd33';
            };
        });
    }
    attachMarkButtons();

    // Enumeration multiple answers
    const addEnumBtn=document.getElementById('add_enum_answer');
    if(addEnumBtn){
        addEnumBtn.addEventListener('click',function(){
            const wrap=document.getElementById('enum_answers_wrap');
            const count=wrap.querySelectorAll('.enum_row').length+1;
            const div=document.createElement('div');
            div.className='input-group mb-2 enum_row';
            div.innerHTML=`<span class="input-group-text">${count}</span>
                <input type="text" name="enumeration_answer[]" class="form-control" placeholder="Answer text">
                <button type="button" class="btn btn-outline-danger remove_enum">Remove</button>`;
            wrap.appendChild(div);
            wrap.querySelectorAll('.remove_enum').forEach(btn=>{
                btn.onclick=()=>btn.closest('.enum_row').remove();
            });
        });
    }
})();
</script>
</body>
</html>
