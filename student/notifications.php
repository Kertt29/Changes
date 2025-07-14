<?php
session_start();
require_once '../config/database.php';
require_once '../config/timezone.php';

// Check if user is logged in as student
if (!isset($_SESSION['student_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    $_SESSION['error'] = "Please log in as a student.";
    header('Location: ../login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$full_name = $_SESSION['full_name'];

// Mark notifications as read when viewing
$stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE student_id = ?");
$stmt->execute([$student_id]);

// Fetch all notifications
$stmt = $pdo->prepare("
    SELECT n.*, e.exam_name, e.start_date, e.end_date
    FROM notifications n
    JOIN exams e ON n.exam_id = e.exam_id
    WHERE n.student_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute([$student_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - ExaMatrix</title>
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
            <h1>Notifications</h1>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i>
                <?php echo formatDate(getCurrentTime()); ?>
                <span class="separator">|</span>
                <i class="far fa-clock"></i>
                <span id="live-time"></span>
            </div>
        </div>

        <div class="notifications-container">
            <?php if (empty($notifications)): ?>
                <div class="no-notifications">
                    <i class="far fa-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div class="notification-details">
                                <span class="exam-name"><?php echo htmlspecialchars($notification['exam_name']); ?></span>
                                <span class="notification-time">
                                    <?php echo formatDateTime($notification['created_at']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateTime() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            
            document.getElementById('live-time').textContent = hours + ':' + minutes + ' ' + ampm;
        }

        updateTime();
        setInterval(updateTime, 1000);
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

.main-content {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
    margin-left:0px;
    margin-top: -250px;  
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

.notifications-container {
    background: rgb(53, 54, 57);    
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 24px 0;
    margin-top: 20px;
}

.no-notifications {
    text-align: center;
    padding: 40px;
    color: #95a5a6;
}

.no-notifications i {
    font-size: 48px;
    margin-bottom: 10px;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    padding: 18px 32px;
    border-bottom: 1px solid #373a40;
    transition: background-color 0.2s;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    background-color: #1e1f25;
}

.notification-icon {
    background-color: #3498db;
    color: white;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 18px;
    flex-shrink: 0;
    font-size: 20px;
    box-shadow: 0 2px 8px rgba(52,152,219,0.08);
}

.notification-content {
    flex-grow: 1;
}

.notification-message {
    font-size: 16px;
    color: #fff;
    margin-bottom: 6px;
    font-weight: 500;
}

.notification-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #7f8c8d;
}

.exam-name {
    font-weight: 500;
    color: #3498db;
}

.notification-time {
    color: #95a5a6;
    font-size: 12px;
}

@media (max-width: 768px) {
    .main-content {
        padding: 15px;
    }
    .notification-item {
        padding: 14px 10px;
        flex-direction: column;
        align-items: flex-start;
    }
    .notification-icon {
        margin-bottom: 8px;
    }
    .notification-details {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }
}

.sidebar {
    width: 250px;
    background-color: rgb(53, 54, 57);
    padding: 20px;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    min-height: 100vh;
    position: relative;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 100;
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

@media (max-width: 900px) {
    .sidebar {
        width: 70px;
        padding: 12px 0;
    }
    .brand-name, .nav-link span, .student-details {
        display: none;
    }
    .nav-link {
        justify-content: center;
        padding: 12px 0;
    }
    .nav-link i {
        margin: 0;
    }
    .student-section {
        padding: 10px;
        margin: 20px 4px 0 4px;
    }
}
</style> 