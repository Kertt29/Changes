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
    // Get exam details
    $stmt = $pdo->prepare("
        SELECT e.*, c.course_name 
        FROM exams e 
        JOIN courses c ON e.course_id = c.course_id 
        WHERE e.exam_id = ?
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();

    if (!$exam) {
        $_SESSION['error'] = "Exam not found";
        header('Location: my_exams.php');
        exit();
    }

    // Get student's attempt
$stmt = $pdo->prepare("
        SELECT * FROM exam_attempts 
        WHERE exam_id = ? AND student_id = ? 
        AND status = 'finished'
        ORDER BY attempt_id DESC LIMIT 1
    ");
    $stmt->execute([$exam_id, $student_id]);
$attempt = $stmt->fetch();

if (!$attempt) {
        $_SESSION['error'] = "No completed attempt found";
        header('Location: my_exams.php');
        exit();
    }

    // Get questions and answers
    $stmt = $pdo->prepare("
        SELECT q.*,
               sa.answer_text as student_answer
        FROM questions q
        LEFT JOIN student_answers sa ON q.question_id = sa.question_id 
            AND sa.attempt_id = ?
        WHERE q.exam_id = ?
        ORDER BY q.question_id
    ");
    $stmt->execute([$attempt['attempt_id'], $exam_id]);
    $questions = $stmt->fetchAll();

    // Get correct answers separately
    $stmt = $pdo->prepare("
        SELECT a.question_id, a.answer_text, a.is_correct
        FROM answers a
        INNER JOIN questions q ON a.question_id = q.question_id
        WHERE q.exam_id = ? AND a.is_correct = 1
    ");
    $stmt->execute([$exam_id]);
    $correct_answers = [];
    while ($row = $stmt->fetch()) {
        $correct_answers[$row['question_id']] = strtolower($row['answer_text']);
    }

    // Calculate score
    $total_questions = count($questions);
    $correct_count = 0;
    foreach ($questions as &$question) {
        // Add the correct answer to each question
        $question['correct_answer'] = isset($correct_answers[$question['question_id']]) ? 
            $correct_answers[$question['question_id']] : null;
        
        // Convert both answers to lowercase for case-insensitive comparison
        $student_answer = strtolower(trim($question['student_answer']));
        $correct_answer = strtolower(trim($question['correct_answer']));
        
        // For true/false questions, standardize the format
        if ($question['question_type'] === 'true_false') {
            $student_answer = $student_answer === 'true' ? 'true' : 'false';
            $correct_answer = $correct_answer === 'true' ? 'true' : 'false';
        }
        
        if ($student_answer === $correct_answer) {
            $correct_count++;
            $question['is_correct'] = true;
        } else {
            $question['is_correct'] = false;
        }
    }
    $score = ($correct_count / $total_questions) * 100;

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
    <title>Exam Result - <?php echo htmlspecialchars($exam['exam_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="result-page">
    <div class="result-container">
        <!-- Summary Section -->
        <div class="result-summary">
            <div class="exam-info">
                <h1><?php echo htmlspecialchars($exam['exam_name']); ?></h1>
                <p class="course-name"><?php echo htmlspecialchars($exam['course_name']); ?></p>
            </div>
            <div class="score-summary">
                <div class="score-circle">
                    <span class="score-value"><?php echo number_format($score, 1); ?>%</span>
                </div>
                <div class="score-stats">
                    <div class="stat-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $correct_count; ?> Correct</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-times-circle"></i>
                        <span><?php echo $total_questions - $correct_count; ?> Incorrect</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-clock"></i>
                        <span>Completed: <?php echo date('M d, Y h:i A', strtotime($attempt['end_time'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Questions Review Section -->
        <div class="questions-review">
            <div class="section-header">
                <h2>Question Review</h2>
                <div class="review-filters">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="correct">Correct</button>
                    <button class="filter-btn" data-filter="incorrect">Incorrect</button>
                </div>
            </div>

            <div class="questions-grid">
                <?php 
                // Sort questions by their order
                usort($questions, function($a, $b) {
                    return strcmp($a['question_text'], $b['question_text']);
                });
                
                foreach ($questions as $index => $question): 
                ?>
                    <div class="question-card <?php echo isset($question['is_correct']) && $question['is_correct'] ? 'correct' : 'incorrect'; ?>">
                        <div class="question-header">
                            <span class="question-number">Question <?php echo $index + 1; ?></span>
                            <span class="question-status">
                                <?php if (isset($question['is_correct']) && $question['is_correct']): ?>
                                    <i class="fas fa-check-circle"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle"></i>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="question-content">
                            <div class="question-text">
                                <?php echo htmlspecialchars($question['question_text']); ?>
                            </div>
                            
                            <div class="answer-review">
                                <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT answer_text, is_correct FROM answers WHERE question_id = ?");
                                    $stmt->execute([$question['question_id']]);
                                    $answers = $stmt->fetchAll();
                                    
                                    foreach ($answers as $answer):
                                        $is_student_answer = strtolower($answer['answer_text']) === strtolower($question['student_answer']);
                                        $is_correct_answer = $answer['is_correct'] == 1;
                                    ?>
                                        <div class="answer-option <?php 
                                            if ($is_correct_answer) echo 'correct';
                                            else if ($is_student_answer && !$is_correct_answer) echo 'incorrect';
                                        ?>">
                                            <span class="option-text">
                                                <?php echo htmlspecialchars($answer['answer_text']); ?>
                                                <?php if ($is_student_answer): ?>
                                                    <i class="fas fa-user-check"></i>
                                                <?php endif; ?>
                                                <?php if ($is_correct_answer): ?>
                                                    <i class="fas fa-check"></i>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>

                                <?php elseif ($question['question_type'] === 'true_false'): ?>
                                    <?php
                                        $student_answer = strtolower($question['student_answer']);
                                        $correct_answer = strtolower($question['correct_answer']);
                                    ?>
                                    <div class="answer-option <?php 
                                        echo $correct_answer === 'true' ? 'correct' : 
                                            ($student_answer === 'true' ? 'incorrect' : ''); 
                                    ?>">
                                        <span class="option-text">
                                            True
                                            <?php if ($student_answer === 'true'): ?>
                                                <i class="fas fa-user-check"></i>
                                            <?php endif; ?>
                                            <?php if ($correct_answer === 'true'): ?>
                                                <i class="fas fa-check"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="answer-option <?php 
                                        echo $correct_answer === 'false' ? 'correct' : 
                                            ($student_answer === 'false' ? 'incorrect' : ''); 
                                    ?>">
                                        <span class="option-text">
                                            False
                                            <?php if ($student_answer === 'false'): ?>
                                                <i class="fas fa-user-check"></i>
                                            <?php endif; ?>
                                            <?php if ($correct_answer === 'false'): ?>
                                                <i class="fas fa-check"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="result-footer">
            <a href="my_exams.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Exams
            </a>
        </div>
    </div>

    <style>
        body {
            background-color: #1e1f25;
            color: #a1a1a1;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .result-container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: rgb(53, 54, 57);  
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .result-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 12px;
            background-color: #1e1f25;
            border-radius: 8px;
        }

        .exam-info h1 {
            color: white;
            font-size: 18px;
            margin: 0;
        }

        .course-name {
            color: #a1a1a1;
            margin: 2px 0 0 0;
            font-size: 13px;
        }

        .score-summary {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .score-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #2ecc71;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
            color: white;
        }

        .score-stats {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #a1a1a1;
            font-size: 12px;
        }

        .stat-item i {
            font-size: 14px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .section-header h2 {
            color: white;
            font-size: 18px;
            margin: 0;
        }

        .review-filters {
            display: flex;
            gap: 8px;
        }

        .filter-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            background-color: #1e1f25;
            color: #a1a1a1;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
        }

        .filter-btn.active {
            background-color: #3498db;
            color: white;
        }

        .questions-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 12px;
            margin-top: 12px;
        }

        .question-card {
            background-color: #1e1f25;
            border-radius: 8px;
            padding: 12px;
            border-left: 4px solid #e74c3c;
            min-height: 150px;
            display: flex;
            flex-direction: column;
        }

        .question-card:nth-child(1) { grid-area: 1 / 1 / 2 / 2; }
        .question-card:nth-child(2) { grid-area: 1 / 2 / 2 / 3; }
        .question-card:nth-child(3) { grid-area: 1 / 3 / 2 / 4; }
        .question-card:nth-child(4) { grid-area: 1 / 4 / 2 / 5; }
        .question-card:nth-child(5) { grid-area: 1 / 5 / 2 / 6; }
        .question-card:nth-child(6) { grid-area: 2 / 1 / 3 / 2; }
        .question-card:nth-child(7) { grid-area: 2 / 2 / 3 / 3; }
        .question-card:nth-child(8) { grid-area: 2 / 3 / 3 / 4; }
        .question-card:nth-child(9) { grid-area: 2 / 4 / 3 / 5; }
        .question-card:nth-child(10) { grid-area: 2 / 5 / 3 / 6; }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid #373a40;
        }

        .question-number {
            color: #3498db;
            font-weight: 500;
            font-size: 12px;
        }

        .question-status {
            font-size: 14px;
        }

        .question-status i.fa-check-circle {
            color: #2ecc71;
        }

        .question-status i.fa-times-circle {
            color: #e74c3c;
        }

        .question-text {
            color: white;
            margin-bottom: 8px;
            font-size: 12px;
            line-height: 1.3;
        }

        .answer-review {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .answer-option {
            padding: 4px 8px;
            background-color: #373a40;
            border-radius: 4px;
            font-size: 11px;
        }

        .answer-option.correct {
            background-color: #2ecc71;
        }

        .answer-option.incorrect {
            background-color: #e74c3c;
        }

        .option-text {
            color: white;
        }

        .result-footer {
            display: flex;
            justify-content: flex-start;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #373a40;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #3498db;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: background-color 0.3s ease;
            font-size: 13px;
        }

        .btn-back:hover {
            background-color: #2980b9;
            color: white;
        }

        @media (max-width: 1200px) {
            .questions-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .questions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', () => {
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                const filter = button.dataset.filter;
                const questions = document.querySelectorAll('.question-card');

                questions.forEach(question => {
                    if (filter === 'all' || 
                        (filter === 'correct' && question.classList.contains('correct')) ||
                        (filter === 'incorrect' && question.classList.contains('incorrect'))) {
                        question.style.display = 'block';
                    } else {
                        question.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html> 