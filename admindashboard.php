<?php
// admin_dashboard.php
// Single-file admin dashboard to create exams and questions (SQL Server using sqlsrv)
// Requirements: includes/sessions.php (checks admin), includes/db.php (provides $con3)
// Uploads folder: /uploads (make writable)

include "includes/sessions.php"; // should set user role and emp id
include "includes/db.php";

date_default_timezone_set('Asia/Manila');


// Handle delete request
if (isset($_GET['delete_exam'])) {
    $exam_id = intval($_GET['delete_exam']);

    // Delete dependencies first (to avoid foreign key constraint errors)
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


// simple admin check (adjust according to your sessions.php)
if (!isset($_SESSION)) session_start();


$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

$messages = [];
$errors = [];

// ---------- Helper: sanitize filename ----------
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_exam') {
    $title = trim($_POST['exam_title'] ?? '');
    $desc  = trim($_POST['exam_description'] ?? '');

    if ($title === '') {
        $errors[] = 'Exam title is required.';
    } else {
        $sql = "INSERT INTO teipiexam.dbo.Exams (Exam_Title, Description) VALUES (?, ?)";
        $params = [$title, $desc];
        $stmt = sqlsrv_query($con3, $sql, $params);
        if ($stmt === false) {
            $errors[] = 'Failed to create exam: ' . print_r(sqlsrv_errors(), true);
        } else {
            $messages[] = 'Exam created successfully.';
            sqlsrv_free_stmt($stmt);
        }
    }
}

// ---------- Handle Add Question ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_question') {
    // Gather fields
    $exam_id = intval($_POST['exam_id'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $q_type = trim($_POST['question_type'] ?? ''); // multiple_choice | true_false | enumeration

    if ($exam_id <= 0) $errors[] = 'Select an exam first.';
    if ($question_text === '') $errors[] = 'Question text is required.';
    if (!in_array($q_type, ['multiple_choice', 'true_false', 'enumeration'])) $errors[] = 'Invalid question type.';

    // Handle image upload (optional)
    $savedImage = null;
    if (!empty($_FILES['question_image']) && $_FILES['question_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['question_image'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload error.';
        } else {
            $allowed = ['jpg','jpeg','png','gif','webp'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errors[] = 'Unsupported image type. Allowed: jpg, png, gif, webp.';
            } else {
                $fname = unique_filename($uploadDir, $f['name']);
                $dest = $uploadDir . $fname;
                if (!move_uploaded_file($f['tmp_name'], $dest)) {
                    $errors[] = 'Failed to move uploaded image.';
                } else {
                    $savedImage = $fname;
                }
            }
        }
    }

    // For multiple choice: read choices from POST
    $choices = [];
    $correct = null;
    if ($q_type === 'multiple_choice') {
        // Expect arrays: choice_text[] and correct_index
        $rawChoices = $_POST['choice_text'] ?? [];
        foreach ($rawChoices as $c) {
            $c = trim($c);
            if ($c !== '') $choices[] = $c;
        }
        $correctIndex = isset($_POST['correct_index']) ? intval($_POST['correct_index']) : -1;
        if (count($choices) < 2) $errors[] = 'At least 2 choices are required for multiple choice.';
        if ($correctIndex < 0 || $correctIndex >= count($choices)) $errors[] = 'Please choose the correct choice.';
        if (empty($errors)) $correct = $choices[$correctIndex];
    }

    // For true_false: store correct as 'True' or 'False'
    if ($q_type === 'true_false') {
        $tf = ($_POST['tf_correct'] ?? 'True') === 'True' ? 'True' : 'False';
        $correct = $tf;
    }

    // For enumeration: correct may be provided (optional)
    if ($q_type === 'enumeration') {
        $correct = trim($_POST['enumeration_answer'] ?? '');
    }

    // If there are no errors, proceed to insert using transaction
    if (empty($errors)) {
        sqlsrv_begin_transaction($con3);
        $ok = true;

        // Insert question
        $q_insert = "INSERT INTO teipiexam.dbo.Questions (Exam_ID, Question, Question_type, Question_Pic) VALUES (?, ?, ?, ?)";
        $q_params = [$exam_id, $question_text, $q_type, $savedImage];
        $q_stmt = sqlsrv_query($con3, $q_insert, $q_params);
        if ($q_stmt === false) {
            $errors[] = 'Failed to insert question: ' . print_r(sqlsrv_errors(), true);
            $ok = false;
        }

        // Retrieve the newly inserted Question_ID. SQL Server: use SCOPE_IDENTITY via OUTPUT or re-query.
        $question_id = null;
        if ($ok) {
            // We can fetch the last inserted identity using OUTPUT clause; here we'll do a quick select by matching most recent for this exam and text
            $sel = "SELECT TOP 1 Question_ID FROM teipiexam.dbo.Questions WHERE Exam_ID = ? AND Question = ? ORDER BY Question_ID DESC";
            $sstmt = sqlsrv_query($con3, $sel, [$exam_id, $question_text]);
            if ($sstmt === false) {
                $errors[] = 'Failed to fetch question id: ' . print_r(sqlsrv_errors(), true);
                $ok = false;
            } else {
                $r = sqlsrv_fetch_array($sstmt, SQLSRV_FETCH_ASSOC);
                if (!$r || empty($r['Question_ID'])) {
                    $errors[] = 'Failed to determine inserted question id.';
                    $ok = false;
                } else {
                    $question_id = $r['Question_ID'];
                }
                sqlsrv_free_stmt($sstmt);
            }
        }

        // Insert correct answer and incorrect answers as needed
        if ($ok && $question_id) {
            // Correct_Answers table
            $ca_sql = "INSERT INTO teipiexam.dbo.Correct_Answers (Question_ID, Correct_answer) VALUES (?, ?)";
            $ca_stmt = sqlsrv_query($con3, $ca_sql, [$question_id, $correct]);
            if ($ca_stmt === false) {
                $errors[] = 'Failed to insert correct answer: ' . print_r(sqlsrv_errors(), true);
                $ok = false;
            }

            // For multiple choices: insert incorrect answers
            if ($ok && $q_type === 'multiple_choice') {
                foreach ($choices as $idx => $cText) {
                    if ($cText === $correct) continue; // skip the correct one
                    $ia_sql = "INSERT INTO teipiexam.dbo.Incorrect_Answers (Question_ID, Incorrect_answer) VALUES (?, ?)";
                    $ia_stmt = sqlsrv_query($con3, $ia_sql, [$question_id, $cText]);
                    if ($ia_stmt === false) {
                        $errors[] = 'Failed to insert an incorrect answer: ' . print_r(sqlsrv_errors(), true);
                        $ok = false;
                        break;
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
            // If image was saved but transaction failed, consider deleting the uploaded file
            if ($savedImage && file_exists($uploadDir . $savedImage)) unlink($uploadDir . $savedImage);
        }
    }
}

// ---------- Utility: fetch exams for dropdown/list ----------
$exams = [];
$e_sql = "SELECT Exam_ID, Exam_Title, Description FROM teipiexam.dbo.Exams ORDER BY Exam_ID DESC";
$e_stmt = sqlsrv_query($con3, $e_sql);
if ($e_stmt !== false) {
    while ($er = sqlsrv_fetch_array($e_stmt, SQLSRV_FETCH_ASSOC)) {
        $exams[] = $er;
    }
    sqlsrv_free_stmt($e_stmt);
}

// ---------- Start output (HTML) ----------
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
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Admin Dashboard — Exams</h2>
        <div>
            <a href="dashboard_home.php" class="btn btn-outline-secondary">Main Dashboard</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $m): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($m); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><pre><?php echo htmlspecialchars($err); ?></pre></div>
        <?php endforeach; ?>
    <?php endif; ?>

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
<a href="admindashboard.php?delete_exam=<?php echo $ex['Exam_ID']; ?>" 
   onclick="return confirm('Are you sure you want to delete this exam?');" 
   class="btn btn-danger btn-sm">Delete</a>

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

                    <!-- Multiple choice block -->
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
                            <small class="text-muted ms-2">Click "Correct" to mark the correct choice.</small>
                        </div>
                    </div>

                    <!-- True/False block -->
                    <div id="tf_block" style="display:none;">
                        <label class="form-label">Correct Answer</label>
                        <select name="tf_correct" class="form-select">
                            <option value="True">True</option>
                            <option value="False">False</option>
                        </select>
                    </div>

                    <!-- Enumeration block -->
                    <div id="enum_block" style="display:none;">
                        <label class="form-label">Correct Answer (optional)</label>
                        <input type="text" name="enumeration_answer" class="form-control" placeholder="Expected answer (optional)">
                        <div class="form-text">This is used for automatic matching. Leave blank to grade manually later.</div>
                    </div>

                    <div class="mt-3">
                        <button class="btn btn-primary">Add Question</button>
                    </div>
                </form>
            </div>

            <div class="card p-3 mt-3 shadow-sm">
                <h5>Notes</h5>
                <ul>
                    <li>Multiple choice: provide at least 2 choices and mark the correct one.</li>
                    <li>Enumeration: matching is case-insensitive trimmed comparison.</li>
                    <li>Uploaded images are stored in <code>/uploads</code>. Ensure the web server can write there.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Simple client-side behavior for choices
(function(){
    const qtype = document.getElementById('qtype');
    const mc = document.getElementById('mc_block');
    const tf = document.getElementById('tf_block');
    const en = document.getElementById('enum_block');

    function toggleBlocks(){
        const v = qtype.value;
        mc.style.display = v === 'multiple_choice' ? 'block' : 'none';
        tf.style.display = v === 'true_false' ? 'block' : 'none';
        en.style.display = v === 'enumeration' ? 'block' : 'none';
    }
    qtype.addEventListener('change', toggleBlocks);
    toggleBlocks();

    // add choice
    document.getElementById('add_choice').addEventListener('click', function(){
        const wrap = document.getElementById('choices_wrap');
        const idx = wrap.querySelectorAll('.choice_row').length + 1;
        const div = document.createElement('div');
        div.className = 'input-group mb-2 choice_row';
        div.innerHTML = `
            <span class="input-group-text">${idx}</span>
            <input type="text" name="choice_text[]" class="form-control" placeholder="Choice text">
            <button type="button" class="btn btn-outline-secondary mark_correct">Correct</button>
        `;
        wrap.appendChild(div);
        attachMarkButtons();
    });

    function attachMarkButtons(){
        document.querySelectorAll('.mark_correct').forEach(function(btn, i){
            btn.onclick = function(){
                // set hidden input correct_index to this button's choice index
                const rows = Array.from(document.querySelectorAll('.choice_row'));
                const index = rows.indexOf(btn.closest('.choice_row'));
                document.getElementById('correct_index').value = index;
                // visual feedback
                rows.forEach(r => r.style.border = '');
                btn.closest('.choice_row').style.border = '2px solid #0d6efd33';
            };
        });
    }
    attachMarkButtons();
})();
</script>
</body>
</html>
