<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if the exam ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid exam ID";
    header('Location: exams.php');
    exit();
}

$exam_id = intval($_GET['id']);

// Fetch the exam details
try {
    $stmt = $pdo->prepare("
        SELECT e.*, c.course_name,
               (SELECT COUNT(*) FROM questions WHERE exam_id = e.exam_id) as question_count
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

    // Fetch questions for this exam
    $stmt = $pdo->prepare("
        SELECT q.*, 
               (SELECT COUNT(*) FROM answers WHERE question_id = q.question_id) as answer_count
        FROM questions q 
        WHERE q.exam_id = ?
        ORDER BY q.created_at ASC
    ");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching exam details: " . $e->getMessage();
    header('Location: exams.php');
    exit();
}

$page_title = 'View Exam';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ExaMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/finalProject/assets/css/styles.css">
</head>
<body class="dashboard-layout">
    <div class="sidebar">
        <div class="brand-section">
            <div class="logo">E</div>
            <h1 class="brand-name">ExaMatrix</h1>
        </div>
        <nav class="nav-menu">
            <ul>
                <li class="nav-item">
                    <a href="admin_dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="courses.php" class="nav-link">
                        <i class="fas fa-book"></i>
                        <span>Courses</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="students.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Students</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="exams.php" class="nav-link active">
                        <i class="fas fa-file-alt"></i>
                        <span>Exams</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="admin-section">
            <div class="admin-avatar">AD</div>
            <div class="admin-details">
                <div class="admin-name">Admin User</div>
                <div class="admin-role">Administrator</div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>View Exam</h1>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F d, Y'); ?>
                <span class="separator">|</span>
                <i class="far fa-clock"></i>
                <?php echo date('h:i A'); ?>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-section">
            <div class="exam-header">
                <div class="exam-title">
                    <h2><?php echo htmlspecialchars($exam['exam_name']); ?></h2>
                    <span class="course-badge"><?php echo htmlspecialchars($exam['course_name']); ?></span>
                </div>
                <div class="exam-actions">
                    <a href="edit_exam.php?id=<?php echo $exam_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Exam
                    </a>
                    <a href="add_questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Questions
                    </a>
                    <a href="exams.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Exams
                    </a>
                </div>
            </div>

            <div class="exam-details-grid">
                <div class="detail-card">
                    <div class="detail-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="detail-content">
                        <h3>Duration</h3>
                        <p><?php echo htmlspecialchars($exam['duration']); ?> minutes</p>
                    </div>
                </div>
                <div class="detail-card">
                    <div class="detail-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="detail-content">
                        <h3>Questions</h3>
                        <p><?php echo $exam['question_count']; ?> / <?php echo htmlspecialchars($exam['total_questions']); ?></p>
                    </div>
                </div>
                <div class="detail-card">
                    <div class="detail-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="detail-content">
                        <h3>Passing Score</h3>
                        <p><?php echo htmlspecialchars($exam['passing_score']); ?>%</p>
                    </div>
                </div>
                <div class="detail-card">
                    <div class="detail-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="detail-content">
                        <h3>Created</h3>
                        <p><?php echo date('M d, Y', strtotime($exam['created_at'])); ?></p>
                    </div>
                </div>
            </div>

            <div class="exam-description">
                <h3>Description</h3>
                <p><?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
            </div>

            <?php if (!empty($questions)): ?>
                <div class="questions-section">
                    <h3>Questions</h3>
                    <div class="questions-grid">
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="question-card">
                                <div class="question-header">
                                    <span class="question-number">Question <?php echo $index + 1; ?></span>
                                    <span class="question-type"><?php echo ucfirst($question['question_type']); ?></span>
                                </div>
                                <div class="question-content">
                                    <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                                    <div class="question-meta">
                                        <span><i class="fas fa-list"></i> <?php echo $question['answer_count']; ?> answers</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-question-circle"></i>
                    <p>No questions added yet</p>
                    <a href="add_questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Questions
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<style>
body {
    background-color: #1e1f25;
    color: #a1a1a1;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    margin: 0;
    padding: 0;
}

.dashboard-layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 250px;
    background-color: #25262b;
    padding: 20px;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
}

.brand-section {
    display: flex;
    align-items: center;
    margin-bottom: 40px;
    padding: 0 10px;
}

.logo {
    width: 40px;
    height: 40px;
    background-color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 20px;
    color: #1e1f25;
    margin-right: 12px;
}

.brand-name {
    color: white;
    font-size: 20px;
    margin: 0;
    font-weight: 600;
}

.nav-menu {
    flex: 1;
}

.nav-menu ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin-bottom: 8px;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    color: #a1a1a1;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.nav-link:hover, .nav-link.active {
    background-color: #3498db;
    color: white;
}

.nav-link i {
    margin-right: 12px;
    width: 20px;
    text-align: center;
}

.admin-section {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: #1e1f25;
    border-radius: 8px;
    margin-top: auto;
}

.admin-avatar {
    width: 40px;
    height: 40px;
    background-color: #3498db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    margin-right: 12px;
}

.admin-details {
    flex: 1;
}

.admin-name {
    color: white;
    font-size: 14px;
    margin-bottom: 2px;
    font-weight: 500;
}

.admin-role {
    color: #a1a1a1;
    font-size: 12px;
}

.logout-btn {
    color: #ff4757;
    text-decoration: none;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.logout-btn:hover {
    background-color: #ff4757;
    color: white;
}

.main-content {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
    margin-top: 300px;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.content-header h1 {
    color: white;
    font-size: 24px;
    margin: 0;
}

.date-time {
    color: #a1a1a1;
    font-size: 14px;
}

.date-time i {
    margin-right: 8px;
    color: #3498db;
}

.separator {
    margin: 0 15px;
    color: #373a40;
}

.dashboard-section {
    background-color: #25262b;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
}

.exam-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.exam-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.exam-title h2 {
    color: white;
    margin: 0;
    font-size: 24px;
}

.course-badge {
    background-color: #3498db;
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 14px;
}

.exam-actions {
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    border-radius: 6px;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn i {
    margin-right: 6px;
}

.btn-primary {
    background-color: #3498db;
    color: white;
}

.btn-primary:hover {
    background-color: #2980b9;
}

.btn-success {
    background-color: #2ecc71;
    color: white;
}

.btn-success:hover {
    background-color: #27ae60;
}

.btn-secondary {
    background-color: #373a40;
    color: white;
}

.btn-secondary:hover {
    background-color: #2c3e50;
}

.exam-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.detail-card {
    background-color: #1e1f25;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.detail-icon {
    width: 40px;
    height: 40px;
    background-color: #3498db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.detail-content h3 {
    color: #a1a1a1;
    font-size: 14px;
    margin: 0 0 5px 0;
}

.detail-content p {
    color: white;
    font-size: 18px;
    margin: 0;
    font-weight: 500;
}

.exam-description {
    background-color: #1e1f25;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.exam-description h3 {
    color: white;
    font-size: 16px;
    margin: 0 0 15px 0;
}

.exam-description p {
    color: #a1a1a1;
    margin: 0;
    line-height: 1.6;
}

.questions-section {
    margin-top: 30px;
}

.questions-section h3 {
    color: white;
    font-size: 18px;
    margin-bottom: 20px;
}

.questions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.question-card {
    background-color: #1e1f25;
    border-radius: 8px;
    padding: 20px;
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.question-number {
    color: #3498db;
    font-weight: 500;
}

.question-type {
    background-color: #373a40;
    color: #a1a1a1;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.question-content p {
    color: white;
    margin: 0 0 15px 0;
    line-height: 1.5;
}

.question-meta {
    display: flex;
    gap: 15px;
    color: #a1a1a1;
    font-size: 12px;
}

.question-meta i {
    margin-right: 4px;
    color: #3498db;
}

.empty-state {
    text-align: center;
    padding: 40px;
    background-color: #1e1f25;
    border-radius: 8px;
}

.empty-state i {
    font-size: 48px;
    color: #3498db;
    margin-bottom: 15px;
}

.empty-state p {
    color: #a1a1a1;
    margin-bottom: 20px;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.alert i {
    margin-right: 10px;
}

.alert-danger {
    background-color: #e74c3c;
    color: white;
    border: none;
}

@media (max-width: 768px) {
    .sidebar {
        width: 70px;
    }

    .brand-name, .nav-link span, .admin-details {
        display: none;
    }

    .nav-link {
        justify-content: center;
        padding: 15px;
    }

    .nav-link i {
        margin: 0;
    }

    .admin-section {
        padding: 10px;
    }

    .main-content {
        padding: 20px;
    }

    .exam-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }

    .exam-actions {
        width: 100%;
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
