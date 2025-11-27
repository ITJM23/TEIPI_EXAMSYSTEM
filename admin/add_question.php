<?php
include "../includes/sessions.php";
include "../includes/db.php";

// Create uploads folder if not existing
$uploadDir = "/../uploads/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exam_id = $_POST['exam_id'];
    $question_text = $_POST['question_text'];
    $question_type = $_POST['question_type'];
    $option_a = trim($_POST['option_a'] ?? '');
    $option_b = trim($_POST['option_b'] ?? '');
    $option_c = trim($_POST['option_c'] ?? '');
    $option_d = trim($_POST['option_d'] ?? '');
    $correct_answer = trim($_POST['answer']);

    // Handle image upload
    $imageName = null;
    if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] == 0) {
        $tmpName = $_FILES['question_image']['tmp_name'];
        $imageName = time() . "_" . basename($_FILES['question_image']['name']);
        $targetPath = $uploadDir . $imageName;
        move_uploaded_file($tmpName, $targetPath);
    }

    // 1️⃣ Insert into Questions table
    $insertQuestion = "
        INSERT INTO Questions (Question, Exam_ID, Question_type, question_image)
        VALUES (?, ?, ?, ?)";
    $params = array($question_text, $exam_id, $question_type, $imageName);
    $stmt = sqlsrv_query($con3, $insertQuestion, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    // 2️⃣ Get the inserted Question_ID
    $getId = "SELECT SCOPE_IDENTITY() AS Question_ID";
    $result = sqlsrv_query($con3, $getId);
    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    $question_id = $row['Question_ID'];

    // 3️⃣ Insert all options into AnswerDetails
    // Each option is stored as a possible answer; correct one is flagged IsCorrect = 1
    $options = array_filter([$option_a, $option_b, $option_c, $option_d]);
    foreach ($options as $opt) {
        $isCorrect = ($opt === $correct_answer) ? 1 : 0;
        $insertDetail = "
            INSERT INTO AnswerDetails (Question_ID, User_Answer, IsCorrect)
            VALUES (?, ?, ?)";
        sqlsrv_query($con3, $insertDetail, array($question_id, $opt, $isCorrect));
    }

    // 4️⃣ If the correct answer is not part of A-D (like True/False, Enumeration)
    // ensure it's inserted at least once
    $alreadyInserted = false;
    foreach ($options as $opt) {
        if (strcasecmp($opt, $correct_answer) === 0) {
            $alreadyInserted = true;
            break;
        }
    }

    if (!$alreadyInserted && !empty($correct_answer)) {
        $insertCorrect = "
            INSERT INTO AnswerDetails (Question_ID, User_Answer, IsCorrect)
            VALUES (?, ?, 1)";
        sqlsrv_query($con3, $insertCorrect, array($question_id, $correct_answer));
    }

    echo "<script>alert('Question added successfully!'); window.location='add_question.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Question</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gray-100 p-8">
<div class="max-w-2xl mx-auto bg-white shadow-md rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4 text-gray-800">Add Question</h2>

    <form method="POST" enctype="multipart/form-data">
        <label class="block mb-2 font-semibold">Select Exam:</label>
        <select name="exam_id" required class="border rounded p-2 w-full mb-4">
            <option value="">-- Choose Exam --</option>
            <?php
            $examQuery = sqlsrv_query($con3, "SELECT Exam_ID, Exam_Title FROM Exams");
            while ($exam = sqlsrv_fetch_array($examQuery, SQLSRV_FETCH_ASSOC)) {
                echo "<option value='{$exam['Exam_ID']}'>{$exam['Exam_Title']}</option>";
            }
            ?>
        </select>

        <label class="block mb-2 font-semibold">Question Text:</label>
        <textarea name="question_text" required class="border rounded p-2 w-full mb-4"></textarea>

        <label class="block mb-2 font-semibold">Question Type:</label>
        <select name="question_type" class="border rounded p-2 w-full mb-4" required>
            <option value="multiple">Multiple Choice</option>
            <option value="enumeration">Enumeration</option>
            <option value="truefalse">True or False</option>
        </select>

        <div id="multiple-choice-options">
            <label class="block mb-2 font-semibold">Option A:</label>
            <input type="text" name="option_a" class="border rounded p-2 w-full mb-2">

            <label class="block mb-2 font-semibold">Option B:</label>
            <input type="text" name="option_b" class="border rounded p-2 w-full mb-2">

            <label class="block mb-2 font-semibold">Option C:</label>
            <input type="text" name="option_c" class="border rounded p-2 w-full mb-2">

            <label class="block mb-2 font-semibold">Option D:</label>
            <input type="text" name="option_d" class="border rounded p-2 w-full mb-4">
        </div>

        <label class="block mb-2 font-semibold">Correct Answer:</label>
        <input type="text" name="answer" required class="border rounded p-2 w-full mb-4">

        <label class="block mb-2 font-semibold">Attach Image (optional):</label>
        <input type="file" name="question_image" accept="image/*" class="border p-2 w-full mb-4">

        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white rounded px-4 py-2">
            Add Question
        </button>
    </form>
</div>
</body>
</html>
