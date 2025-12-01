<?php
include "includes/sessions.php";
include "includes/db.php";

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($exam_id <= 0) {
        die("<div class='p-6 text-red-700'>Invalid or missing Exam ID.</div>");
}

// Check if exam is password-protected
$accessStmt = sqlsrv_query($con3, "IF OBJECT_ID('dbo.Exam_Access','U') IS NULL SELECT 0 as has ELSE SELECT Access_PasswordHash FROM dbo.Exam_Access WHERE Exam_ID = ?", [$exam_id]);
$accessHash = null;
if ($accessStmt) {
    while ($ar = sqlsrv_fetch_array($accessStmt, SQLSRV_FETCH_ASSOC)) {
        if (isset($ar['Access_PasswordHash'])) { $accessHash = $ar['Access_PasswordHash']; break; }
    }
    sqlsrv_free_stmt($accessStmt);
}

// If protected and no access cookie set, require password
if (!empty($accessHash)) {
    $cookieName = 'exam_access_' . $exam_id;
    // handle password submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_password'])) {
        $provided = $_POST['exam_password'];
        // convert binary hash to string if needed
        $stored = is_resource($accessHash) ? stream_get_contents($accessHash) : $accessHash;
        if (password_verify($provided, (string)$stored)) {
            setcookie($cookieName, '1', time()+3600, '/');
            // redirect to GET to avoid repost
            header("Location: take_exam.php?exam_id=" . $exam_id);
            exit;
        } else {
            $pw_error = 'Incorrect password.';
        }
    }

    // If cookie not present, show password form
    if (empty($_COOKIE[$cookieName])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title>Exam Access Required</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-slate-50 min-h-screen flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow w-full max-w-md">
                <h2 class="text-lg font-semibold mb-4">This exam is protected</h2>
                <?php if (!empty($pw_error)): ?><div class="mb-3 text-red-600"><?php echo htmlspecialchars($pw_error); ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Exam Password</label>
                        <input type="password" name="exam_password" class="w-full px-3 py-2 border rounded" required>
                    </div>
                    <div class="flex items-center justify-between">
                        <a href="exam_list.php" class="text-sm text-slate-500">← Back to list</a>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Enter</button>
                    </div>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Fetch exam-specific passing rate (if table exists). Default to 75%.
$passingRate = 75;
$prStmt = @sqlsrv_query($con3, "IF OBJECT_ID('dbo.Exam_Settings','U') IS NULL SELECT 75 AS PassingRate ELSE SELECT PassingRate FROM dbo.Exam_Settings WHERE Exam_ID = ?", [$exam_id]);
if ($prStmt) {
    $prRow = sqlsrv_fetch_array($prStmt, SQLSRV_FETCH_ASSOC);
    if ($prRow && isset($prRow['PassingRate'])) {
        $val = intval($prRow['PassingRate']);
        if ($val > 0 && $val <= 100) $passingRate = $val;
    }
    sqlsrv_free_stmt($prStmt);
}

// Fetch all questions first
 $sql = "SELECT * FROM teipiexam.dbo.Questions WHERE Exam_ID = ?";
 $params = [$exam_id];
 $stmt = sqlsrv_query($con3, $sql, $params);

 $questions = [];
 if ($stmt !== false) {
         while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                 $questions[] = $row;
         }
         sqlsrv_free_stmt($stmt);
 }

?>

<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Take Exam #<?php echo htmlspecialchars($exam_id); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">

    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <header class="px-6 py-4 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold">🧠 Taking Exam #<?php echo htmlspecialchars($exam_id); ?></h1>
                        <p class="text-sm text-slate-500">Answer the questions and submit when finished.</p>
                    </div>
                    <div class="text-sm text-slate-500">Questions: <span id="totalQuestions"><?php echo count($questions); ?></span></div>
                </div>
            </header>

            <form id="examForm" action="submit_exam.php" method="POST" class="px-6 py-6" novalidate>
                <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($exam_id); ?>">

                <?php if (empty($questions)): ?>
                    <div class="p-6 text-center text-slate-600">No questions found for this exam.</div>
                <?php else: ?>

                    <div class="mb-4">
                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div id="progressBar" class="h-2 bg-indigo-600 rounded-full w-0"></div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 flex justify-between"><span id="progressText">Question 1 of <?php echo count($questions); ?></span><span id="answeredCount">0 answered</span></div>
                    </div>

                    <div id="questionsContainer" class="space-y-6">
                        <?php
                        $qnum = 1;
                        foreach ($questions as $q) {
                                $qid = $q['Question_ID'];
                                $question_text = htmlspecialchars($q['Question']);
                                $question_type = strtolower(trim($q['Question_type'] ?? ''));
                        ?>
                        <section class="question-card bg-slate-50 p-4 rounded-lg border" data-index="<?php echo $qnum; ?>" data-qid="<?php echo $qid; ?>" data-type="<?php echo $question_type; ?>" <?php echo $qnum===1 ? '' : 'style="display:none;"'; ?>>
                            <input type="hidden" name="question_id[]" value="<?php echo $qid; ?>">
                            <h3 class="font-medium text-slate-800 mb-2"><?php echo $qnum . '. ' . $question_text; ?></h3>

                            <?php
                            // Image
                            if (!empty($q['Question_Pic']) || !empty($q['question_image'])) {
                                    $img = htmlspecialchars($q['Question_Pic'] ?: $q['question_image']);
                            ?>
                                <div class="my-3 text-center">
                                    <img src="uploads/<?php echo $img; ?>" alt="Question Image" class="mx-auto rounded shadow-sm max-h-64 object-contain">
                                </div>
                            <?php
                            }

                            if ($question_type === 'multiple_choice') {
                                    $choices = [];
                                    // incorrect
                                    $sqlChoices = "SELECT Incorrect_answer FROM teipiexam.dbo.Incorrect_Answers WHERE Question_ID = ?";
                                    $stmtC = sqlsrv_query($con3, $sqlChoices, [$qid]);
                                    while ($c = sqlsrv_fetch_array($stmtC, SQLSRV_FETCH_ASSOC)) {
                                            $choices[] = $c['Incorrect_answer'];
                                    }
                                    // correct
                                    $sqlCorrect = "SELECT Correct_answer FROM teipiexam.dbo.Correct_Answers WHERE Question_ID = ?";
                                    $stmtCorr = sqlsrv_query($con3, $sqlCorrect, [$qid]);
                                    while ($corr = sqlsrv_fetch_array($stmtCorr, SQLSRV_FETCH_ASSOC)) {
                                            if (!empty($corr['Correct_answer'])) $choices[] = $corr['Correct_answer'];
                                    }
                                    shuffle($choices);
                            ?>
                                <div class="grid grid-cols-1 gap-2">
                                    <?php foreach ($choices as $opt): $optSafe = htmlspecialchars($opt); ?>
                                        <label class="flex items-center gap-3 p-3 bg-white rounded-md border hover:bg-indigo-50">
                                            <input type="radio" name="answer[<?php echo $qid; ?>]" value="<?php echo $optSafe; ?>" class="answer-input" />
                                            <span class="text-sm text-slate-700"><?php echo $optSafe; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                            <?php } elseif ($question_type === 'true_false') { ?>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 p-2 bg-white rounded-md border hover:bg-indigo-50"><input type="radio" name="answer[<?php echo $qid; ?>]" value="True" class="answer-input"> <span>True</span></label>
                                    <label class="flex items-center gap-2 p-2 bg-white rounded-md border hover:bg-indigo-50"><input type="radio" name="answer[<?php echo $qid; ?>]" value="False" class="answer-input"> <span>False</span></label>
                                </div>

                            <?php } elseif ($question_type === 'enumeration') {
                                    $sqlEnumCount = "SELECT COUNT(*) AS AnswerCount FROM teipiexam.dbo.Correct_Answers WHERE Question_ID = ?";
                                    $stmtEnum = sqlsrv_query($con3, $sqlEnumCount, [$qid]);
                                    $rowEnum = sqlsrv_fetch_array($stmtEnum, SQLSRV_FETCH_ASSOC);
                                    $count = intval($rowEnum['AnswerCount'] ?? 1);
                                    if ($count < 1) $count = 1;
                            ?>
                                <div class="space-y-2">
                                    <p class="text-sm text-slate-600">Provide <?php echo $count; ?> answer(s):</p>
                                    <?php for ($i=1;$i<=$count;$i++): ?>
                                        <input type="text" name="answer[<?php echo $qid; ?>][]" class="w-full p-2 border rounded-md" placeholder="Answer #<?php echo $i; ?>">
                                    <?php endfor; ?>
                                </div>

                            <?php } else { ?>
                                <div class="text-sm text-amber-700">⚠ Unknown question type: <?php echo htmlspecialchars($question_type); ?></div>
                            <?php } ?>

                        </section>
                        <?php
                                $qnum++;
                        }
                        ?>
                    </div>

                    <!-- Navigation -->
                    <div class="mt-6 flex items-center justify-between">
                        <div>
                            <button type="button" id="prevBtn" class="inline-flex items-center px-4 py-2 rounded-md bg-slate-100 text-slate-700 hover:bg-slate-200">← Previous</button>
                            <button type="button" id="nextBtn" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 ml-3">Next →</button>
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="exam_list.php" class="text-sm text-slate-500 hover:underline">← Back to list</a>
                            <button type="submit" id="submitBtn" class="ml-2 inline-flex items-center px-4 py-2 rounded-md bg-emerald-600 text-white hover:bg-emerald-700">✅ Submit Exam</button>
                        </div>
                    </div>

                <?php endif; ?>

            </form>
        </div>
    </div>

    <script>
        (function(){
            const cards = Array.from(document.querySelectorAll('.question-card'));
            const total = cards.length;
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const answeredCount = document.getElementById('answeredCount');
            let idx = 0;

            function showIndex(i){
                cards.forEach((c, j)=>{
                    c.style.display = j===i ? '' : 'none';
                });
                progressBar.style.width = Math.round(((i+1)/total)*100) + '%';
                progressText.textContent = 'Question ' + (i+1) + ' of ' + total;
                updateAnsweredCount();
            }

            function updateAnsweredCount(){
                let answered = 0;
                cards.forEach(c=>{
                    const type = c.dataset.type;
                    const qid = c.dataset.qid;
                    if(type==='enumeration'){
                        const inputs = c.querySelectorAll('input[type="text"]');
                        let ok = true; inputs.forEach(inp=>{ if(inp.value.trim()==='') ok = false; });
                        if(ok) answered++;
                    } else {
                        const checked = c.querySelector('input[type="radio"]:checked');
                        if(checked) answered++;
                    }
                });
                answeredCount.textContent = answered + ' answered';
            }

            document.getElementById('nextBtn')?.addEventListener('click', ()=>{
                if(idx < total-1) { idx++; showIndex(idx); }
            });
            document.getElementById('prevBtn')?.addEventListener('click', ()=>{
                if(idx > 0) { idx--; showIndex(idx); }
            });

            // Update answered count on input
            document.addEventListener('change', (e)=>{ if(e.target.matches('.answer-input')||e.target.matches('input[type=text]')) updateAnsweredCount(); });

            // Validate before submit
            document.getElementById('examForm')?.addEventListener('submit', function(e){
                // simple validation: ensure every question has an answer
                for(const c of cards){
                    const type = c.dataset.type;
                    if(type==='enumeration'){
                        const inputs = c.querySelectorAll('input[type="text"]');
                        for(const inp of inputs){ if(inp.value.trim()===''){ alert('Please complete all enumeration answers.'); idx = parseInt(c.dataset.index)-1; showIndex(idx); e.preventDefault(); return false; } }
                    } else {
                        const checked = c.querySelector('input[type="radio"]:checked');
                        if(!checked){ alert('Please answer all questions before submitting.'); idx = parseInt(c.dataset.index)-1; showIndex(idx); e.preventDefault(); return false; }
                    }
                }

                if(!confirm('Are you sure you want to submit your exam?')){ e.preventDefault(); return false; }
            });

            // initialize
            if(cards.length>0) showIndex(0);
        })();
    </script>

</body>
</html>
