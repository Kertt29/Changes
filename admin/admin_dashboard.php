<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is logged in as admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../config/database.php';
require_once '../config/timezone.php';

try {
    // Fetch admin data
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        session_unset();
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    // Count courses
    $stmt = $pdo->query("SELECT COUNT(*) FROM courses");
    $courses_count = $stmt->fetchColumn();

    // Count exams
    $stmt = $pdo->query("SELECT COUNT(*) FROM exams");
    $exams_count = $stmt->fetchColumn();

    // Count students
    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
    $students_count = $stmt->fetchColumn();

    // Fetch recent exams
    $stmt = $pdo->query("SELECT * FROM exams ORDER BY created_at DESC LIMIT 5");
    $recent_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Only count enrollments that are not yet approved
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE is_approved = 0");
    $stmt->execute();
    $pending_enrollments = (int)$stmt->fetchColumn();

} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading the dashboard";
    header('Location: ../login.php');
    exit();
}

$page_title = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
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
                    <a href="admin_dashboard.php" class="nav-link active">
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
                    <a href="exams.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        <span>Exams</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="register_student.php" class="nav-link">
                        <i class="fas fa-user-plus"></i>
                        <span>Register Student</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="approve_students.php" class="nav-link">
                        <i class="fas fa-user-check"></i>
                        <span>Approve Students</span>
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
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>Welcome, Administrator</h1>
            <div class="header-actions">
                <div class="notification-wrapper">
                    <button class="notification-btn" onclick="toggleNotificationDropdown()">
                        <i class="fas fa-bell"></i>
                        <?php if ($pending_enrollments > 0): ?>
                            <span class="notification-badge"><?php echo $pending_enrollments; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <button class="clear-all-btn" onclick="clearAllNotifications()">Clear All</button>
                        </div>
                        <div class="notification-list">
                            <?php if ($pending_enrollments > 0): ?>
                                <div class="notification-item">
                                    <div class="notification-icon">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p><?php echo $pending_enrollments; ?> pending enrollment<?php echo $pending_enrollments > 1 ? 's' : ''; ?></p>
                                        <a href="approve_students.php" class="view-link">View Details</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="notification-item empty">
                                    <p>No new notifications</p>
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
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <div class="action-card">
                <div class="card-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="card-content">
                    <h3><?php echo $courses_count; ?></h3>
                    <p>Total Courses</p>
                    <a href="create_course.php" class="btn-create">
                        <i class="fas fa-plus"></i> Create Course
                    </a>
                </div>
            </div>
            <div class="action-card">
                <div class="card-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="card-content">
                    <h3><?php echo $exams_count; ?></h3>
                    <p>Total Exams</p>
                    <a href="create_exam.php" class="btn-create">
                        <i class="fas fa-plus"></i> Create Exam
                    </a>
                </div>
            </div>
            <div class="action-card">
                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-content">
                    <h3><?php echo $students_count; ?></h3>
                    <p>Total Students</p>
                    <a href="students.php" class="btn-create">
                        <i class="fas fa-eye"></i> View Students
                    </a>
                </div>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h2>Recent Exams</h2>
                <a href="exams.php" class="view-all">View All</a>
            </div>
            <div class="recent-exams">
                <?php foreach ($recent_exams as $exam): ?>
                    <div class="exam-card">
                        <div class="exam-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="exam-content">
                            <h4><?php echo htmlspecialchars($exam['exam_name']); ?></h4>
                            <p class="exam-description"><?php echo htmlspecialchars($exam['description']); ?></p>
                            <div class="exam-details">
                                <span><i class="far fa-clock"></i> <?php echo htmlspecialchars($exam['duration']); ?> minutes</span>
                                <span><i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($exam['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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
    margin-top: -250px;
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

.notification-wrapper {
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
    gap: 8px;
}

.notification-btn:hover {
    color: #3498db;
}

.notification-badge {
    background-color: #e74c3c;
    color: white;
    font-size: 12px;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
    display: inline-block;
    position: absolute;
    top: 2px;
    right: 2px;
    transform: translate(50%, -50%);
    z-index: 1;
}

.notification-btn {
    position: relative;
}

.notification-btn i {
    position: relative;
    z-index: 2;
}

.date-time {
    color: #a1a1a1;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.date-time i {
    color: #3498db;
}

.separator {
    margin: 0 8px;
    color: #373a40;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.action-card {
    background-color: rgb(53, 54, 57);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    transition: transform 0.3s ease;
}

.action-card:hover {
    transform: translateY(-5px);
}

.card-icon {
    width: 60px;
    height: 60px;
    background-color: #3498db;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
}

.card-icon i {
    font-size: 24px;
    color: white;
}

.card-content h3 {
    color: white;
    font-size: 24px;
    margin: 0 0 5px 0;
}

.card-content p {
    color: #a1a1a1;
    margin: 0 0 15px 0;
    font-size: 14px;
}

.btn-create {
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

.btn-create i {
    margin-right: 6px;
}

.btn-create:hover {
    background-color: #2980b9;
    color: white;
}

.dashboard-section {
    background-color: rgb(53, 54, 57);
    border-radius: 12px;
    padding: 20px;
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

.recent-exams {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.exam-card {
    background-color: #1e1f25;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    align-items: flex-start;
    transition: transform 0.3s ease;
}

.exam-card:hover {
    transform: translateY(-3px);
}

.exam-icon {
    width: 40px;
    height: 40px;
    background-color: #3498db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.exam-icon i {
    color: white;
    font-size: 18px;
}

.exam-content {
    flex: 1;
}

.exam-content h4 {
    color: white;
    font-size: 16px;
    margin: 0 0 8px 0;
}

.exam-description {
    color: #a1a1a1;
    font-size: 14px;
    margin: 0 0 12px 0;
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
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 300px;
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
    max-height: 300px;
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

.notification-content p {
    color: white;
    margin: 0 0 4px 0;
    font-size: 14px;
}

.view-link {
    color: #3498db;
    text-decoration: none;
    font-size: 12px;
    transition: color 0.3s ease;
}

.view-link:hover {
    color: #2980b9;
}
</style> 