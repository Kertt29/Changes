<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as student
if (!isset($_SESSION['student_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    $_SESSION['error'] = "Please log in as a student.";
    header('Location: ../login.php');  // Redirect to login page if not logged in
    exit();
}

$student_id = $_SESSION['student_id'];

// Fetch courses that the student can enroll in
$stmt = $pdo->prepare("SELECT c.course_id, c.course_name, c.description, 
                        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id AND e.student_id = ?) AS is_enrolled,
                        (SELECT is_approved FROM enrollments e WHERE e.course_id = c.course_id AND e.student_id = ?) AS is_approved 
                        FROM courses c");
$stmt->execute([$student_id, $student_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    $course_id = $_POST['course_id'];

    // Check if already enrolled
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND student_id = ?");
    $checkStmt->execute([$course_id, $student_id]);
    $alreadyEnrolled = $checkStmt->fetchColumn();

    if ($alreadyEnrolled) {
        $_SESSION['error'] = "You are already enrolled in this course.";
    } else {
        // Enroll the student in the course (pending approval)
        $enrollStmt = $pdo->prepare("INSERT INTO enrollments (course_id, student_id, is_approved) VALUES (?, ?, 0)");
        $enrollStmt->execute([$course_id, $student_id]);
        $_SESSION['success'] = "Course enrollment request submitted! Please wait for admin approval.";
        header('Location: enroll_courses.php'); // Refresh the page to see updated enrollment status
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enroll in Courses - ExaMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
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
                    <a href="student_dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="enroll_courses.php" class="nav-link active">
                        <i class="fas fa-book"></i>
                        <span>Enroll in Courses</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="my_exams.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        <span>My Exams</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="edit_profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <span>Edit Profile</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="student-section">
            <div class="student-avatar">SD</div>
            <div class="student-details">
                <div class="student-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                <div class="student-role">Student</div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>Enroll in Courses</h1>
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

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-section">
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="course-content">
                            <h4><?php echo htmlspecialchars($course['course_name']); ?></h4>
                            <p class="course-description"><?php echo htmlspecialchars($course['description']); ?></p>
                            <?php if ($course['is_enrolled'] > 0): ?>
                                <?php if ($course['is_approved'] == 1): ?>
                                    <div class="enrollment-status enrolled">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Enrolled</span>
                                    </div>
                                <?php else: ?>
                                    <div class="enrollment-status pending">
                                        <i class="fas fa-clock"></i>
                                        <span>Pending Approval</span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="POST" action="" class="enroll-form">
                                    <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                    <button type="submit" name="enroll" class="btn-enroll">
                                        <i class="fas fa-plus"></i> Enroll Now
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
    background-color: rgb(53, 54, 57);
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

.student-section {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: #1e1f25;
    border-radius: 8px;
    margin-top: auto;
}

.student-avatar {
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

.student-details {
    flex: 1;
}

.student-name {
    color: white;
    font-size: 14px;
    margin-bottom: 2px;
    font-weight: 500;
}

.student-role {
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
    background-color: rgb(53, 54, 57);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
}

.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.course-card {
    background-color: #1e1f25;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: flex-start;
    transition: transform 0.3s ease;
}

.course-card:hover {
    transform: translateY(-3px);
}

.course-icon {
    width: 40px;
    height: 40px;
    background-color: #3498db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.course-icon i {
    color: white;
    font-size: 18px;
}

.course-content {
    flex: 1;
}

.course-content h4 {
    color: white;
    font-size: 16px;
    margin: 0 0 8px 0;
}

.course-description {
    color: #a1a1a1;
    font-size: 14px;
    margin: 0 0 15px 0;
}

.enrollment-status {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 14px;
}

.enrollment-status.enrolled {
    background-color: #2ecc71;
    color: white;
}

.enrollment-status.pending {
    background-color: #f1c40f;
    color: white;
}

.enrollment-status.enrolled i,
.enrollment-status.pending i {
    margin-right: 6px;
}

.enroll-form {
    margin-top: 15px;
}

.btn-enroll {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.btn-enroll i {
    margin-right: 6px;
}

.btn-enroll:hover {
    background-color: #2980b9;
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
    background-color: #ff4757;
    color: white;
    border: none;
}

.alert-success {
    background-color: #2ecc71;
    color: white;
    border: none;
}

@media (max-width: 768px) {
    .sidebar {
        width: 70px;
    }

    .brand-name, .nav-link span, .student-details {
        display: none;
    }

    .nav-link {
        justify-content: center;
        padding: 15px;
    }

    .nav-link i {
        margin: 0;
    }

    .student-section {
        padding: 10px;
    }

    .main-content {
        padding: 20px;
    }
}
</style>





