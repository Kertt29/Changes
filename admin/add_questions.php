<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Please log in as admin";
    header('Location: ../login.php');
    exit();
}

// Check if exam_id is provided and is valid
if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    $_SESSION['error'] = "No exam selected or invalid exam ID";
    header('Location: exams.php');
    exit();
}

$exam_id = (int)$_GET['exam_id'];

// Get exam details and question count
try {
    $stmt = $pdo->prepare("
        SELECT e.*, c.course_name,
               (SELECT COUNT(*) FROM questions WHERE exam_id = e.exam_id) as question_count,
               (SELECT COUNT(*) FROM questions WHERE exam_id = e.exam_id AND question_type = 'multiple_choice') as multiple_choice_count,
               (SELECT COUNT(*) FROM questions WHERE exam_id = e.exam_id AND question_type = 'true_false') as true_false_count,
               (SELECT COUNT(*) FROM questions WHERE exam_id = e.exam_id AND question_type = 'Matching Type') as matching_count
        FROM exams e
        JOIN courses c ON e.course_id = c.course_id
        WHERE e.exam_id = ?
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        $_SESSION['error'] = "Exam not found";
        header('Location: exams.php');
        exit();
    }

    // Get allowed types for this exam
    $allowed_types = isset($exam['allowed_types']) ? explode(',', $exam['allowed_types']) : ['multiple_choice', 'true_false', 'Matching Type'];
    $question_type = '';
    if (in_array('multiple_choice', $allowed_types) && $exam['multiple_choice_count'] < $exam['min_multiple_choice']) {
        $question_type = 'multiple_choice';
        $current_count = $exam['multiple_choice_count'];
        $total_required = $exam['min_multiple_choice'];
    } elseif (in_array('true_false', $allowed_types) && $exam['true_false_count'] < $exam['min_true_false']) {
        $question_type = 'true_false';
        $current_count = $exam['true_false_count'];
        $total_required = $exam['min_true_false'];
    } elseif (in_array('Matching Type', $allowed_types) && $exam['matching_count'] < $exam['min_matching']) {
        $question_type = 'Matching Type';
        $current_count = $exam['matching_count'];
        $total_required = $exam['min_matching'];
    }
    // If only one allowed type, always set $question_type to that type
    if (count($allowed_types) === 1) {
        $question_type = $allowed_types[0];
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching exam details";
    header('Location: exams.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = $_POST['question_text'];
    $exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : $_GET['exam_id'];
    
    // Validate question text
    if (empty($question_text)) {
        $error = "Question text is required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert the question with the determined type
            if ($question_type === 'Matching Type' || strtolower($question_type) === 'matching type' || $question_type === 'matching') {
                $question_type = 'Matching Type';
            }
            $stmt = $pdo->prepare("INSERT INTO questions (exam_id, question_text, question_type) VALUES (?, ?, ?)");
            $stmt->execute([$exam_id, $question_text, $question_type]);
            $question_id = $pdo->lastInsertId();
            
            if ($question_type === 'multiple_choice') {
                // Handle multiple choice options
                $choices = $_POST['choices'];
                $correct_answer = $_POST['correct_answer'];
                
                foreach ($choices as $index => $choice) {
                    $is_correct = ($index == $correct_answer) ? 1 : 0;
                    $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$question_id, $choice, $is_correct]);
                }
            } elseif ($question_type === 'true_false') {
                // Handle true/false options
                $correct_answer = $_POST['correct_answer_tf'];
                $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, 'True', ?)");
                $stmt->execute([$question_id, ($correct_answer === 'true') ? 1 : 0]);
                $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, 'False', ?)");
                $stmt->execute([$question_id, ($correct_answer === 'false') ? 1 : 0]);
            } elseif ($question_type === 'Matching Type') {
                // Handle matching choices
                $choices = $_POST['choices'];
                $correct_answer = $_POST['correct_answer'];
                $_SESSION['matching_choices'][$exam_id] = $choices;
                // Insert all choices as answers
                foreach ($choices as $index => $choice) {
                    if (!empty($choice)) {
                        $is_correct = ($index == $correct_answer) ? 1 : 0;
                        $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                        $stmt->execute([$question_id, $choice, $is_correct]);
                    }
                }
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Question added successfully!";
            header("Location: add_questions.php?exam_id=" . $exam_id);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error adding question: " . $e->getMessage();
        }
    }
}

// At the top of the file, after session_start()
if (!isset($_SESSION['matching_choices'])) {
    $_SESSION['matching_choices'] = [];
}
if (!isset($_SESSION['matching_choices'][$exam_id])) {
    $_SESSION['matching_choices'][$exam_id] = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Questions - ExaMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #1e1f25;
            color: #ffffff;
        }
        .container {
            padding: 20px;
        }
        .question-form {
            background-color: #2d2e36;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .form-control {
            background-color: #1e1f25;
            border: 1px solid #373a40;
            color: #ffffff;
        }
        .form-control:focus {
            background-color: #1e1f25;
            color: #ffffff;
        }
        .btn-primary {
            background-color: #3498db;
            border: none;
        }
        .btn-back {
            background-color: #3498db;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 20px;
        }
        .btn-back:hover {
            background-color: #2980b9;
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #373a40;
        }
        .header-container h2 {
            margin: 0 0 10px 0;
            color: #ffffff;
        }
        .header-container .text-muted {
            margin: 0;
            color: #a1a1a1;
        }
        .header-actions {
            display: flex;
            gap: 10px;
        }
        .btn-success {
            background-color: #2ecc71;
            border: none;
        }
        .btn-success:hover {
            background-color: #27ae60;
        }
        .question-form {
            max-width: 800px;
            margin: 0 auto;
        }
        .form-label {
            color: #ffffff;
            font-weight: 500;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
        }
        .alert i {
            margin-right: 10px;
            margin-top: 3px;
        }
        .alert-success {
            background-color: #2ecc71;
            color: white;
            border: none;
        }
        .alert-danger {
            background-color: #e74c3c;
            color: white;
            border: none;
        }
        .alert ul {
            margin: 0;
            padding-left: 20px;
        }
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 20px;
            }
            .header-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
        .question-type-header {
            background-color: #2d2e36;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        .question-type-header h3 {
            color: #ffffff;
            margin: 0;
            font-size: 1.2rem;
        }
        .question-type-header p {
            color: #a1a1a1;
            margin: 5px 0 0 0;
            font-size: 0.9rem;
        }
        .matching-pair {
            background-color: #2d2e36;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #373a40;
        }
        .matching-pair .btn-danger {
            padding: 0.375rem 0.75rem;
        }
        .matching-pair .form-control {
            background-color: #1e1f25;
            border: 1px solid #373a40;
            color: #ffffff;
        }
        .matching-pair .form-control:focus {
            background-color: #1e1f25;
            border-color: #3498db;
            color: #ffffff;
        }
        #matching_correct_answer {
            background-color: #1e1f25;
            border: 1px solid #373a40;
            color: #ffffff;
        }
        #matching_correct_answer:focus {
            background-color: #1e1f25;
            border-color: #3498db;
            color: #ffffff;
        }
        .matching-choice {
            background-color: #2d2e36;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #373a40;
        }
        .matching-choice .btn-danger {
            padding: 0.375rem 0.75rem;
        }
        .matching-choice .form-control {
            background-color: #1e1f25;
            border: 1px solid #373a40;
            color: #ffffff;
        }
        .matching-choice .form-control:focus {
            background-color: #1e1f25;
            border-color: #3498db;
            color: #ffffff;
        }
        #matching_correct_answer {
            background-color: #1e1f25;
            border: 1px solid #373a40;
            color: #ffffff;
        }
        #matching_correct_answer:focus {
            background-color: #1e1f25;
            border-color: #3498db;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container">
            <div class="header-container">
                <div>
                    <h2>Add Questions to <?php echo htmlspecialchars($exam['exam_name']); ?></h2>
                    <p class="text-muted">Course: <?php echo htmlspecialchars($exam['course_name']); ?></p>
                    <p class="text-muted">Total Questions: <?php echo $exam['question_count']; ?> / <?php echo $exam['total_questions']; ?></p>
                </div>
                <div class="header-actions">
                    <a href="exams.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Exams
                    </a>
                    <?php if ($exam['question_count'] >= $exam['total_questions']): ?>
                        <a href="exams.php" class="btn btn-success">
                            <i class="fas fa-check"></i> Exam Complete
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($exam['question_count'] < $exam['total_questions'] && $question_type): ?>
                <div class="question-type-header">
                    <h3><?php echo ($question_type === 'Matching Type' ? 'Matching Type' : ucfirst(str_replace('_', ' ', $question_type))); ?> Questions</h3>
                    <p>Adding question <?php echo isset($current_count) ? ($current_count + 1) : 1; ?> of <?php echo isset($total_required) ? $total_required : 1; ?></p>
                </div>

                <form method="POST" class="question-form">
                    <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($exam_id); ?>">
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question Text</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required><?php echo isset($_POST['question_text']) ? htmlspecialchars($_POST['question_text']) : ''; ?></textarea>
                    </div>

                    <?php if ($question_type === 'multiple_choice'): ?>
                    <div class="mb-3">
                        <label class="form-label">Choices</label>
                        <div id="choices_container">
                            <?php for($i = 0; $i < 4; $i++): ?>
                            <div class="mb-2">
                                <input type="text" class="form-control" name="choices[]" placeholder="Choice <?php echo $i + 1; ?>" value="<?php echo isset($_POST['choices'][$i]) ? htmlspecialchars($_POST['choices'][$i]) : ''; ?>">
                            </div>
                            <?php endfor; ?>
                        </div>
                        <label class="form-label mt-2">Correct Answer</label>
                        <select class="form-control" name="correct_answer">
                            <?php for($i = 0; $i < 4; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo (isset($_POST['correct_answer']) && $_POST['correct_answer'] == $i) ? 'selected' : ''; ?>>
                                Choice <?php echo $i + 1; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php elseif ($question_type === 'true_false'): ?>
                        <div class="mb-3">
                        <label class="form-label">Correct Answer</label>
                        <select class="form-control" name="correct_answer_tf">
                            <option value="true" <?php echo (isset($_POST['correct_answer_tf']) && $_POST['correct_answer_tf'] === 'true') ? 'selected' : ''; ?>>True</option>
                            <option value="false" <?php echo (isset($_POST['correct_answer_tf']) && $_POST['correct_answer_tf'] === 'false') ? 'selected' : ''; ?>>False</option>
                        </select>
                    </div>
                    <?php elseif ($question_type === 'Matching Type'): ?>
                        <div class="mb-3">
                        <label class="form-label">Choices</label>
                        <div id="matching_choices_container">
                            <?php 
                                $saved_choices = $_SESSION['matching_choices'][$exam_id] ?? [];
                                if (empty($saved_choices)) {
                                    $saved_choices = [''];
                                }
                                foreach ($saved_choices as $index => $choice): 
                                ?>
                                <div class="matching-choice mb-2">
                                    <div class="row">
                                        <div class="col">
                                            <input type="text" class="form-control" name="choices[]" 
                                                placeholder="Enter choice" required 
                                                value="<?php echo htmlspecialchars($choice); ?>">
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-danger remove-choice" onclick="removeMatchingChoice(this)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-primary mt-2" onclick="addMatchingChoice()">
                                <i class="fas fa-plus"></i> Add Choice
                            </button>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Correct Answer</label>
                            <select class="form-control" name="correct_answer" id="matching_correct_answer" required>
                                <option value="">Select Correct Answer</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Question
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    All questions have been added to this exam.
                </div>
                <?php
                if ($exam['matching_count'] >= $exam['min_matching']) {
                    unset($_SESSION['matching_choices'][$exam_id]);
                }
                ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to add a new matching choice
        function addMatchingChoice() {
            const container = document.getElementById('matching_choices_container');
            const newChoice = document.createElement('div');
            newChoice.className = 'matching-choice mb-2';
            newChoice.innerHTML = `
                <div class="row">
                    <div class="col">
                        <input type="text" class="form-control" name="choices[]" placeholder="Enter choice" required>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-danger remove-choice" onclick="removeMatchingChoice(this)">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(newChoice);
            updateMatchingCorrectAnswer();
        }

        // Function to remove a matching choice
        function removeMatchingChoice(button) {
            const container = document.getElementById('matching_choices_container');
            const choices = container.querySelectorAll('.matching-choice');
            
            // Don't remove if it's the last choice
            if (choices.length > 1) {
                const choice = button.closest('.matching-choice');
                choice.remove();
                updateMatchingCorrectAnswer();
            }
        }

        // Function to update the correct answer dropdown
        function updateMatchingCorrectAnswer() {
            const container = document.getElementById('matching_choices_container');
            const select = document.getElementById('matching_correct_answer');
            const choices = container.querySelectorAll('input[name="choices[]"]');
            
            // Clear existing options
            select.innerHTML = '<option value="">Select Correct Answer</option>';
            
            // Add new options based on the choices
            choices.forEach((choice, index) => {
                if (choice.value.trim() !== '') {
                const option = document.createElement('option');
                    option.value = index;
                    option.textContent = choice.value;
                select.appendChild(option);
            }
            });
        }

        // Add event listeners to update correct answer dropdown when inputs change
        document.addEventListener('input', function(e) {
            if (e.target.matches('input[name="choices[]"]')) {
                updateMatchingCorrectAnswer();
            }
        });

        // Initialize the correct answer dropdown
        document.addEventListener('DOMContentLoaded', function() {
            updateMatchingCorrectAnswer();
        });
    </script>
</body>
</html>