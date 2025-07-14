<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/database.php';
require_once '../config/timezone.php';

// Debug session data
error_log("Dashboard Session Data: " . print_r($_SESSION, true));

// Check if user is logged in as student
if (!isset($_SESSION['student_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    error_log("Access denied - Missing session data: student_id=" . 
              (isset($_SESSION['student_id']) ? 'yes' : 'no') . 
              ", user_type=" . (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'no'));
    $_SESSION['error'] = "Please log in as a student.";
    header('Location: ../login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$full_name = $_SESSION['full_name'];

// Fetch unread notifications count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE student_id = ? AND is_read = FALSE");
$stmt->execute([$student_id]);
$unread_notifications = $stmt->fetchColumn();

// Fetch student's enrolled courses
$stmt = $pdo->prepare("
    SELECT c.course_id, c.course_name, c.description, 
           (SELECT COUNT(*) FROM exams WHERE course_id = c.course_id) as exam_count,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.course_id) as student_count
    FROM courses c 
    JOIN enrollments e ON c.course_id = e.course_id 
    WHERE e.student_id = ?
");
$stmt->execute([$student_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all exams for enrolled courses
$stmt = $pdo->prepare("
    SELECT e.exam_id, e.exam_name, e.start_date, e.end_date, e.duration, e.total_questions, c.course_name
    FROM exams e
    JOIN courses c ON e.course_id = c.course_id
    JOIN enrollments en ON c.course_id = en.course_id
    WHERE en.student_id = ?
    ORDER BY e.start_date ASC
    LIMIT 5
");
$stmt->execute([$student_id]);
$upcoming_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ExaMatrix</title>
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
                    <a href="student_dashboard.php" class="nav-link active">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="enroll_courses.php" class="nav-link">
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
                <div class="student-name"><?php echo htmlspecialchars($full_name); ?></div>
                <div class="student-role">Student</div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>Welcome, <?php echo htmlspecialchars($full_name ?: 'Student'); ?></h1>
            <div class="header-actions">
                <div class="notification-bell">
                    <button class="notification-btn" onclick="toggleNotificationDropdown()">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <button class="clear-all-btn" onclick="clearAllNotifications()">Clear All</button>
                        </div>
                        <div class="notification-list">
                            <?php
                            // Fetch recent notifications
                            $stmt = $pdo->prepare("
                                SELECT n.*, e.exam_name, e.start_date, e.end_date
                                FROM notifications n
                                LEFT JOIN exams e ON n.exam_id = e.exam_id
                                WHERE n.student_id = ?
                                ORDER BY n.created_at DESC
                                LIMIT 5
                            ");
                            $stmt->execute([$student_id]);
                            $recent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (empty($recent_notifications)): ?>
                                <div class="notification-item empty">
                                    <p>No new notifications</p>
                                </div>
                            <?php else: 
                                foreach ($recent_notifications as $notification): ?>
                                    <div class="notification-item">
                                        <div class="notification-icon">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-message">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </div>
                                            <div class="notification-details">
                                                <span class="exam-name">
                                                    <?php 
                                                    if (!empty($notification['exam_name'])) {
                                                        echo htmlspecialchars($notification['exam_name']);
                                                    } else {
                                                        echo 'General Notification';
                                                    }
                                                    ?>
                                                </span>
                                                <span class="notification-time">
                                                    <?php echo formatDateTime($notification['created_at']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="notification-footer">
                                    <a href="notifications.php" class="view-all-notifications">View All Notifications</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="date-time">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo formatDate(getCurrentTime()); ?>
                    <span class="separator">|</span>
                    <i class="far fa-clock"></i>
                    <span id="live-time"></span>
                    <span class="timezone"><?php echo date_default_timezone_get(); ?></span>
                </div>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h2>Upcoming Exams</h2>
                <a href="my_exams.php" class="view-all">View All</a>
            </div>
            <div class="upcoming-exams">
                <?php if (!empty($upcoming_exams)): ?>
                    <?php foreach ($upcoming_exams as $exam): ?>
                        <div class="exam-card">
                            <div class="exam-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="exam-content">
                                <h4><?php echo htmlspecialchars($exam['exam_name']); ?></h4>
                                <div class="exam-details">
                                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($exam['course_name']); ?></span>
                                    <span><i class="far fa-calendar"></i> <?php echo !empty($exam['start_date']) ? date('M d, Y', strtotime($exam['start_date'])) : 'N/A'; ?></span>
                                    <span><i class="far fa-clock"></i> <?php echo !empty($exam['start_date']) ? date('h:i A', strtotime($exam['start_date'])) : 'N/A'; ?></span>
                                    <span><i class="fas fa-hourglass-half"></i> <?php echo $exam['duration']; ?> mins</span>
                                    <span><i class="fas fa-question-circle"></i> <?php echo $exam['total_questions']; ?> items</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No upcoming exams</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h2>My Courses</h2>
                <a href="enroll_courses.php" class="view-all">Enroll in More</a>
            </div>
            <div class="courses-grid">
                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="course-content">
                                <h4><?php echo htmlspecialchars($course['course_name']); ?></h4>
                                <p class="course-description"><?php echo htmlspecialchars($course['description']); ?></p>
                                <div class="course-stats">
                                    <div class="stat-item">
                                        <i class="fas fa-file-alt"></i>
                                        <span><?php echo $course['exam_count']; ?> Exams</span>
                                    </div>
                                    <div class="stat-item">
                                        <i class="fas fa-users"></i>
                                        <span><?php echo $course['student_count']; ?> Students</span>
                                    </div>
                                </div>
                                <a href="my_exams.php?course_id=<?php echo $course['course_id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Exams
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <p>You haven't enrolled in any courses yet</p>
                        <a href="enroll_courses.php" class="btn-enroll">
                            <i class="fas fa-plus"></i> Enroll in Courses
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateTime() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            // Convert to 12-hour format
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            
            // Add leading zeros
            minutes = minutes < 10 ? '0' + minutes : minutes;
            
            // Update the time display
            document.getElementById('live-time').textContent = hours + ':' + minutes + ' ' + ampm;
        }

        // Update time immediately and then every second
        updateTime();
        setInterval(updateTime, 1000);

        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }

        function clearAllNotifications() {
            // Add your clear notifications logic here
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.remove('show');
        }

        // Close the dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const notificationBtn = document.querySelector('.notification-btn');
            
            if (!notificationBtn.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
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
    margin-top: 100px;
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

.header-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

.notification-bell {
    position: relative;
}

.notification-btn {
    background: none;
    border: none;
    color: #a1a1a1;
    font-size: 20px;
    cursor: pointer;
    padding: 8px;
    position: relative;
    transition: color 0.3s ease;
    display: flex;
    align-items: center;
}

.notification-btn:hover {
    color: #3498db;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: #e74c3c;
    color: white;
    font-size: 12px;
    font-weight: bold;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 350px;
    background-color: rgb(53, 54, 57);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    margin-top: 10px;
    display: none;
    z-index: 1000;
}

.notification-dropdown.show {
    display: block;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #373a40;
}

.notification-header h3 {
    color: white;
    font-size: 16px;
    margin: 0;
}

.clear-all-btn {
    background: none;
    border: none;
    color: #3498db;
    font-size: 12px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

.clear-all-btn:hover {
    background-color: #1e1f25;
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    padding: 12px 15px;
    border-bottom: 1px solid #373a40;
    transition: background-color 0.3s ease;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    background-color: #1e1f25;
}

.notification-item.empty {
    justify-content: center;
    color: #a1a1a1;
    font-size: 14px;
    padding: 20px;
}

.notification-icon {
    width: 32px;
    height: 32px;
    background-color: #3498db;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.notification-icon i {
    color: white;
    font-size: 14px;
}

.notification-content {
    flex: 1;
}

.notification-message {
    color: white;
    margin: 0 0 4px 0;
    font-size: 14px;
}

.notification-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
}

.exam-name {
    color: #3498db;
}

.notification-time {
    color: #a1a1a1;
}

.notification-footer {
    padding: 12px 15px;
    border-top: 1px solid #373a40;
    text-align: center;
}

.view-all-notifications {
    color: #3498db;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
}

.view-all-notifications:hover {
    color: #2980b9;
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

.timezone {
    margin-left: 8px;
    color: #6c757d;
    font-size: 12px;
}

.dashboard-section {
    background-color: rgb(53, 54, 57);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h2 {
    color: white;
    font-size: 18px;
    margin: 0;
}

.view-all {
    color: #3498db;
    text-decoration: none;
    font-size: 14px;
}

.view-all:hover {
    color: #2980b9;
}

.upcoming-exams, .courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.exam-card, .course-card {
    background-color: #1e1f25;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    align-items: flex-start;
    transition: transform 0.3s ease;
}

.exam-card:hover, .course-card:hover {
    transform: translateY(-3px);
}

.exam-icon, .course-icon {
    width: 40px;
    height: 40px;
    background-color: #3498db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.exam-icon i, .course-icon i {
    color: white;
    font-size: 18px;
}

.exam-content, .course-content {
    flex: 1;
}

.exam-content h4, .course-content h4 {
    color: white;
    font-size: 16px;
    margin: 0 0 8px 0;
}

.exam-details {
    display: flex;
    gap: 15px;
    color: #a1a1a1;
    font-size: 12px;
}

.exam-details i {
    margin-right: 4px;
    color: #3498db;
}

.course-description {
    color: #a1a1a1;
    font-size: 14px;
    margin: 0 0 12px 0;
}

.course-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #a1a1a1;
    font-size: 12px;
}

.stat-item i {
    color: #3498db;
}

.btn-view {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    background-color: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    transition: background-color 0.3s ease;
}

.btn-view i {
    margin-right: 6px;
}

.btn-view:hover {
    background-color: #2980b9;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #a1a1a1;
}

.empty-state i {
    font-size: 48px;
    color: #3498db;
    margin-bottom: 15px;
}

.empty-state p {
    margin-bottom: 20px;
}

.btn-enroll {
    display: inline-flex;
    align-items: center;
    padding: 10px 20px;
    background-color: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    transition: background-color 0.3s ease;
}

.btn-enroll i {
    margin-right: 6px;
}

.btn-enroll:hover {
    background-color: #2980b9;
    color: white;
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