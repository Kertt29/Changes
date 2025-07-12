<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if student ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No student selected";
    header('Location: students.php');
    exit();
}

$student_id = $_GET['id'];

try {
    // Get student details
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COUNT(DISTINCT e.course_id) as enrolled_courses,
               COUNT(DISTINCT ea.exam_id) as completed_exams
        FROM students s
        LEFT JOIN enrollments e ON s.student_id = e.student_id
        LEFT JOIN exam_attempts ea ON s.student_id = ea.student_id AND ea.status = 'finished'
        WHERE s.student_id = ?
        GROUP BY s.student_id
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        $_SESSION['error'] = "Student not found";
        header('Location: students.php');
        exit();
    }

    // Get enrolled courses
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM courses c
        JOIN enrollments e ON c.course_id = e.course_id
        WHERE e.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $enrolled_courses = $stmt->fetchAll();

    // Get exam history
    $stmt = $pdo->prepare("
        SELECT ea.*, e.exam_name, c.course_name
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.exam_id
        JOIN courses c ON e.course_id = c.course_id
        WHERE ea.student_id = ?
        ORDER BY ea.start_time DESC
    ");
    $stmt->execute([$student_id]);
    $exam_history = $stmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: students.php');
    exit();
}

$page_title = 'View Student';
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
                    <a href="students.php" class="nav-link active">
                        <i class="fas fa-users"></i>
                        <span>Students</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="exams.php" class="nav-link">
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
            <h1>Student Details</h1>
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
            <div class="student-profile">
                <div class="profile-header">
                    <div class="student-avatar">
                        <?php echo strtoupper(substr($student['full_name'], 0, 2)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($student['full_name']); ?></h2>
                        <p class="student-email"><?php echo htmlspecialchars($student['email']); ?></p>
                        <div class="profile-stats">
                            <div class="stat-item">
                                <i class="fas fa-book"></i>
                                <span><?php echo $student['enrolled_courses']; ?> Enrolled Courses</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-file-alt"></i>
                                <span><?php echo $student['completed_exams']; ?> Completed Exams</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-user-check"></i>
                                <span>Status: <?php echo $student['is_approved'] ? 'Approved' : 'Pending'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-content">
                    <div class="content-section">
                        <h3>Enrolled Courses</h3>
                        <?php if (empty($enrolled_courses)): ?>
                            <p class="empty-state">No courses enrolled</p>
                        <?php else: ?>
                            <div class="courses-grid">
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <div class="course-card">
                                        <div class="course-icon">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        <div class="course-info">
                                            <h4><?php echo htmlspecialchars($course['course_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($course['description']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="content-section">
                        <h3>Exam History</h3>
                        <?php if (empty($exam_history)): ?>
                            <p class="empty-state">No exam history</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Exam</th>
                                            <th>Course</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($exam_history as $exam): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['course_name']); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo strtolower($exam['status']); ?>">
                                                        <?php echo ucfirst($exam['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="students.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Students
            </a>
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
    margin-top: 150px;
    max-width: 1500px;
    width: 100%;
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

.student-profile {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.profile-header {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #373a40;
}

.student-avatar {
    width: 80px;
    height: 80px;
    background-color: #3498db;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 32px;
}

.profile-info {
    flex: 1;
}

.profile-info h2 {
    color: white;
    font-size: 24px;
    margin: 0 0 8px 0;
}

.student-email {
    color: #a1a1a1;
    font-size: 14px;
    margin: 0 0 15px 0;
}

.profile-stats {
    display: flex;
    gap: 20px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #a1a1a1;
    font-size: 14px;
}

.stat-item i {
    color: #3498db;
}

.profile-content {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.content-section {
    background-color: #1e1f25;
    border-radius: 8px;
    padding: 20px;
}

.content-section h3 {
    color: white;
    font-size: 18px;
    margin: 0 0 20px 0;
}

.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.course-card {
    background-color: #25262b;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.course-icon {
    width: 40px;
    height: 40px;
    background-color: #3498db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.course-info h4 {
    color: white;
    font-size: 16px;
    margin: 0 0 8px 0;
}

.course-info p {
    color: #a1a1a1;
    font-size: 14px;
    margin: 0;
}

.table {
    color: #a1a1a1;
    margin: 0;
}

.table thead th {
    background-color: #25262b;
    border-bottom: 2px solid #373a40;
    color: #a1a1a1;
    font-weight: 500;
    padding: 12px;
}

.table tbody td {
    padding: 12px;
    border-bottom: 1px solid #373a40;
}

.table tbody tr:hover {
    background-color: #25262b;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-badge.finished {
    background-color: #2ecc71;
    color: white;
}

.status-badge.in-progress {
    background-color: #f1c40f;
    color: #2c3e50;
}

.status-badge.pending {
    background-color: #3498db;
    color: white;
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.btn-back, .btn-edit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-back {
    background-color: #3498db;
    color: white;
}

.btn-back:hover {
    background-color: #2980b9;
    color: white;
}

.btn-edit {
    background-color: #f1c40f;
    color: #2c3e50;
}

.btn-edit:hover {
    background-color: #f39c12;
    color: #2c3e50;
}

.empty-state {
    color: #a1a1a1;
    text-align: center;
    padding: 20px;
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

    .profile-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .profile-stats {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .action-buttons {
        flex-direction: column;
    }

    .btn-back, .btn-edit {
        width: 100%;
        justify-content: center;
    }
}
</style> 