<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if student is logged in
if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
    $_SESSION['error'] = "Please log in as a student";
    header('Location: ../login.php');
    exit();
}

// Check if exam_id is provided
if (!isset($_GET['exam_id'])) {
    $_SESSION['error'] = "No exam selected";
    header('Location: my_exams.php');
    exit();
}

$exam_id = $_GET['exam_id'];
$student_id = $_SESSION['student_id'];

try {
    // Get exam details including reopen information
    $stmt = $pdo->prepare("
        SELECT e.*, c.course_name, re.reopen_start_date, re.reopen_end_date
        FROM exams e 
        JOIN courses c ON e.course_id = c.course_id 
        LEFT JOIN reopened_exams re ON e.exam_id = re.exam_id AND re.student_id = ?
        WHERE e.exam_id = ?
    ");
    $stmt->execute([$student_id, $exam_id]);
    $exam = $stmt->fetch();

    if (!$exam) {
        $_SESSION['error'] = "Exam not found";
        header('Location: my_exams.php');
        exit();
    }

    // Check if exam is available (either original or reopened)
    $is_available = false;
    $availability_message = '';
    $now = new DateTime();
    
    if ($exam['reopen_start_date'] && $exam['reopen_end_date']) {
        // This is a reopened exam
        $reopen_start = new DateTime($exam['reopen_start_date']);
        $reopen_end = new DateTime($exam['reopen_end_date']);
        
        if ($now >= $reopen_start && $now <= $reopen_end) {
            $is_available = true;
        } elseif ($now < $reopen_start) {
            $availability_message = "Reopened exam will be available from " . $reopen_start->format('F d, Y h:i A');
        } else {
            $availability_message = "Reopened exam has ended on " . $reopen_end->format('F d, Y h:i A');
        }
    } else {
        // Original exam
        $start_date = new DateTime($exam['start_date']);
        $end_date = new DateTime($exam['end_date']);
        
        if ($now >= $start_date && $now <= $end_date) {
            $is_available = true;
        } elseif ($now < $start_date) {
            $availability_message = "Exam will be available from " . $start_date->format('F d, Y h:i A');
        } else {
            $availability_message = "Exam has ended on " . $end_date->format('F d, Y h:i A');
        }
    }
    
    if (!$is_available) {
        $_SESSION['error'] = $availability_message;
        header('Location: my_exams.php');
        exit();
    }

    // Check if student has already started or completed this exam
    $stmt = $pdo->prepare("
        SELECT * FROM exam_attempts 
        WHERE exam_id = ? AND student_id = ? 
        ORDER BY attempt_id DESC LIMIT 1
    ");
    $stmt->execute([$exam_id, $student_id]);
    $attempt = $stmt->fetch();

    // If no attempt exists, create one
    if (!$attempt) {
        $stmt = $pdo->prepare("
            INSERT INTO exam_attempts (exam_id, student_id, start_time, status) 
            VALUES (?, ?, NOW(), 'in_progress')
        ");
        $stmt->execute([$exam_id, $student_id]);
        $attempt_id = $pdo->lastInsertId();
    } else {
        $attempt_id = $attempt['attempt_id'];
        if ($attempt['status'] === 'finished') {
            $_SESSION['error'] = "You have already completed this exam";
            header('Location: my_exams.php');
            exit();
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();

        try {
            // Delete any existing answers for this attempt
            $stmt = $pdo->prepare("DELETE FROM student_answers WHERE attempt_id = ?");
            $stmt->execute([$attempt_id]);

            // Get correct answers for comparison
            $stmt = $pdo->prepare("
                SELECT question_id, answer_text 
                FROM answers 
                WHERE question_id IN (SELECT question_id FROM questions WHERE exam_id = ?) 
                AND is_correct = 1
            ");
            $stmt->execute([$exam_id]);
            $correct_answers = [];
            while ($row = $stmt->fetch()) {
                $correct_answers[$row['question_id']] = strtolower($row['answer_text']);
            }

            // Insert new answers with correctness
            $stmt = $pdo->prepare("
                INSERT INTO student_answers (attempt_id, question_id, answer_text, is_correct) 
                VALUES (?, ?, ?, ?)
            ");

            foreach ($_POST['answers'] as $question_id => $answer) {
                if (!empty($answer)) {
                    $is_correct = isset($correct_answers[$question_id]) && 
                                strtolower(trim($answer)) === strtolower(trim($correct_answers[$question_id]));
                    $stmt->execute([$attempt_id, $question_id, $answer, $is_correct]);
                }
            }

            // Update attempt status and calculate score
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total, SUM(is_correct) as correct 
                FROM student_answers 
                WHERE attempt_id = ?
            ");
            $stmt->execute([$attempt_id]);
            $result = $stmt->fetch();
            $score = ($result['correct'] / $result['total']) * 100;

            $stmt = $pdo->prepare("
                UPDATE exam_attempts 
                SET status = 'finished', end_time = NOW(), score = ? 
                WHERE attempt_id = ?
            ");
            $stmt->execute([$score, $attempt_id]);

            $pdo->commit();
            $_SESSION['success'] = "Exam submitted successfully";
            header('Location: my_exams.php');
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error submitting exam: " . $e->getMessage();
        }
    }

    // Get questions for this exam
    $stmt = $pdo->prepare("
        SELECT q.* 
        FROM questions q 
        WHERE q.exam_id = ?
        ORDER BY q.question_id
    ");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll();

    // Get answers for multiple choice and true/false questions
    $stmt = $pdo->prepare("
        SELECT question_id, answer_text, is_correct 
        FROM answers 
        WHERE question_id IN (SELECT question_id FROM questions WHERE exam_id = ?)
    ");
    $stmt->execute([$exam_id]);
    $answers = [];
    while ($row = $stmt->fetch()) {
        if (!isset($answers[$row['question_id']])) {
            $answers[$row['question_id']] = [];
        }
        $answers[$row['question_id']][] = $row;
    }

    // Get student's existing answers if any
    $stmt = $pdo->prepare("
        SELECT question_id, answer_text 
        FROM student_answers 
        WHERE attempt_id = ?
    ");
    $stmt->execute([$attempt_id]);
    $existing_answers = [];
    while ($row = $stmt->fetch()) {
        $existing_answers[$row['question_id']] = $row['answer_text'];
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: my_exams.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam - <?php echo htmlspecialchars($exam['exam_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="exam-page">
    <div class="exam-container">
        <div class="exam-header">
            <div class="exam-info">
                <h1><?php echo htmlspecialchars($exam['exam_name']); ?></h1>
                <p class="course-name"><?php echo htmlspecialchars($exam['course_name']); ?></p>
                <?php if ($exam['reopen_start_date']): ?>
                    <p class="reopen-notice">
                        <i class="fas fa-redo"></i> 
                        This is a reopened exam available from <?php echo date('M d, Y h:i A', strtotime($exam['reopen_start_date'])); ?> 
                        to <?php echo date('M d, Y h:i A', strtotime($exam['reopen_end_date'])); ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="exam-timer">
                <i class="fas fa-clock"></i>
                <span id="timer"><?php echo $exam['duration']; ?>:00</span>
            </div>
        </div>

        <form method="POST" action="" id="examForm" onsubmit="return confirmSubmission();">
            <div class="questions-container">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card <?php echo $index === 0 ? 'active' : ''; ?>" data-question="<?php echo $index; ?>">
                        <div class="question-header">
                            <h3>Question <?php echo $index + 1; ?> of <?php echo count($questions); ?></h3>
                            <div class="question-type">
                                <?php 
                                $type_icon = '';
                                switch($question['question_type']) {
                                    case 'multiple_choice':
                                        $type_icon = '<i class="fas fa-list-ul"></i> Multiple Choice';
                                        break;
                                    case 'true_false':
                                        $type_icon = '<i class="fas fa-toggle-on"></i> True/False';
                                        break;
                                    case 'identification':
                                        $type_icon = '<i class="fas fa-pen"></i> Identification';
                                        break;
                                    default:
                                        $type_icon = '<i class="fas fa-question"></i> Question';
                                }
                                echo $type_icon;
                                ?>
                            </div>
                        </div>
                        
                        <div class="question-content">
                            <p><?php echo htmlspecialchars($question['question_text']); ?></p>

                                <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                <div class="answer-options">
                                    <?php foreach ($answers[$question['question_id']] ?? [] as $answer): ?>
                                        <div class="answer-option">
                                            <input type="radio" 
                                                   name="answers[<?php echo $question['question_id']; ?>]" 
                                                   value="<?php echo htmlspecialchars($answer['answer_text']); ?>"
                                                   id="answer_<?php echo $question['question_id']; ?>_<?php echo $answer['answer_id']; ?>"
                                                   <?php echo (isset($existing_answers[$question['question_id']]) && $existing_answers[$question['question_id']] === $answer['answer_text']) ? 'checked' : ''; ?>
                                                   required>
                                            <label for="answer_<?php echo $question['question_id']; ?>_<?php echo $answer['answer_id']; ?>">
                                                <?php echo htmlspecialchars($answer['answer_text']); ?>
                                        </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php elseif ($question['question_type'] === 'true_false'): ?>
                                <div class="answer-options">
                                    <?php foreach ($answers[$question['question_id']] ?? [] as $answer): ?>
                                        <div class="answer-option">
                                        <input type="radio" 
                                               name="answers[<?php echo $question['question_id']; ?>]" 
                                                   value="<?php echo htmlspecialchars($answer['answer_text']); ?>"
                                                   id="answer_<?php echo $question['question_id']; ?>_<?php echo $answer['answer_id']; ?>"
                                                   <?php echo (isset($existing_answers[$question['question_id']]) && $existing_answers[$question['question_id']] === $answer['answer_text']) ? 'checked' : ''; ?>
                                                   required>
                                            <label for="answer_<?php echo $question['question_id']; ?>_<?php echo $answer['answer_id']; ?>">
                                                <?php echo htmlspecialchars($answer['answer_text']); ?>
                                    </label>
                                        </div>
                                    <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                                <div class="answer-input">
                                    <input type="text" 
                                           name="answers[<?php echo $question['question_id']; ?>]" 
                                           value="<?php echo htmlspecialchars($existing_answers[$question['question_id']] ?? ''); ?>"
                                           placeholder="Enter your answer here..."
                                           class="form-control"
                                           required>
                        </div>
                                <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <div class="exam-footer">
                <div class="navigation-buttons">
                    <button type="button" class="btn btn-secondary" onclick="previousQuestion()" id="prevBtn" style="display: none;">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextQuestion()" id="nextBtn">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="submit-section">
                    <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">
                        <i class="fas fa-check"></i> Submit Exam
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        let currentQuestion = 0;
        const totalQuestions = <?php echo count($questions); ?>;
        let timeLeft = <?php echo $exam['duration'] * 60; ?>; // Convert minutes to seconds
        let timer;

        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('timer').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                alert('Time is up! Your exam will be submitted automatically.');
                document.getElementById('examForm').submit();
            }
            timeLeft--;
        }

        function showQuestion(index) {
            // Hide all questions
            document.querySelectorAll('.question-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Show current question
            document.querySelectorAll('.question-card')[index].classList.add('active');
            
            // Update navigation buttons
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');
            
            prevBtn.style.display = index === 0 ? 'none' : 'inline-block';
            nextBtn.style.display = index === totalQuestions - 1 ? 'none' : 'inline-block';
            submitBtn.style.display = index === totalQuestions - 1 ? 'inline-block' : 'none';
        }

        function nextQuestion() {
            if (currentQuestion < totalQuestions - 1) {
                currentQuestion++;
                showQuestion(currentQuestion);
            }
        }

        function previousQuestion() {
            if (currentQuestion > 0) {
                currentQuestion--;
                showQuestion(currentQuestion);
            }
        }

        function confirmSubmission() {
            return confirm('Are you sure you want to submit your exam? You cannot change your answers after submission.');
        }

        // Start timer
        timer = setInterval(updateTimer, 1000);
        updateTimer();

        // Initialize first question
        showQuestion(0);
    </script>

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8f9fa;
        }

        .exam-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .exam-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .exam-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .exam-info h1 {
            margin: 0 0 5px 0;
            font-size: 24px;
        }

        .course-name {
            margin: 0;
            opacity: 0.9;
        }

        .reopen-notice {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 4px;
        }

        .exam-timer {
            background: rgba(255,255,255,0.2);
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
        }

        .questions-container {
            padding: 20px;
        }

        .question-card {
            display: none;
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .question-card.active {
            display: block;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .question-header h3 {
            margin: 0;
            color: #495057;
        }

        .question-type {
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            color: #6c757d;
        }

        .question-content p {
            font-size: 16px;
            line-height: 1.6;
            color: #212529;
            margin-bottom: 20px;
        }

        .answer-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .answer-option {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .answer-option:hover {
            border-color: #667eea;
            background-color: #f8f9ff;
        }

        .answer-option input[type="radio"] {
            margin-right: 10px;
        }

        .answer-option label {
            cursor: pointer;
            margin: 0;
            flex: 1;
        }

        .answer-input input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 16px;
        }

        .answer-input input:focus {
            outline: none;
            border-color: #667eea;
        }

        .exam-footer {
            background: #f8f9fa;
            padding: 20px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navigation-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        @media (max-width: 768px) {
            .exam-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .exam-footer {
                flex-direction: column;
                gap: 15px;
            }

            .navigation-buttons {
                width: 100%;
                justify-content: center;
        }
        }
    </style>
</body>
</html> 