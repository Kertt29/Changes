<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Approve Course Enrollments';

// Handle enrollment approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enrollment_id'])) {
    $enrollment_id = $_POST['enrollment_id'];
    $action = $_POST['action'];

    try {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE enrollments SET is_approved = 1 WHERE enrollment_id = ?");
            $stmt->execute([$enrollment_id]);
            // Fetch student_id and course_name for notification
            $stmt = $pdo->prepare("SELECT s.student_id, c.course_name FROM enrollments e JOIN students s ON e.student_id = s.student_id JOIN courses c ON e.course_id = c.course_id WHERE e.enrollment_id = ?");
            $stmt->execute([$enrollment_id]);
            $row = $stmt->fetch();
            if ($row) {
                $student_id = $row['student_id'];
                $course_name = $row['course_name'];
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (student_id, exam_id, message, created_at, is_read) VALUES (?, NULL, ?, NOW(), 0)");
                $notif_stmt->execute([$student_id, "Your enrollment in the course '$course_name' has been approved!"]);
            }
            $approval_success = true;
            $approval_message = "Course enrollment approved successfully!";
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
            $stmt->execute([$enrollment_id]);
            $approval_success = true;
            $approval_message = "Course enrollment rejected and removed.";
        }
    } catch (Exception $e) {
        $approval_error = "Error processing request: " . $e->getMessage();
    }
}

// Get pending enrollments
$stmt = $pdo->query("
    SELECT e.enrollment_id, e.created_at as enrollment_date,
           s.student_id, s.full_name as student_name, s.email as student_email,
           c.course_id, c.course_name, c.description as course_description
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.is_approved = 0
    ORDER BY e.created_at DESC
");
$pending_enrollments = $stmt->fetchAll();
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
                    <a href="approve_students.php" class="nav-link active">
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
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>Approve Course Enrollments</h1>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F d, Y'); ?>
                <span class="separator">|</span>
                <i class="far fa-clock"></i>
                <span id="live-time"></span>
            </div>
        </div>

        <?php if (isset($approval_success) && $approval_success): ?>
            <div class="dashboard-section" id="approval-success-section">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $approval_message; ?>
                </div>
                <div class="form-actions" style="margin-top: 20px;">
                    <a href="approve_students.php" class="btn-submit"><i class="fas fa-user-check"></i> Approve More Enrollments</a>
                    <a href="admin_dashboard.php" class="btn-cancel"><i class="fas fa-home"></i> Go to Dashboard</a>
                </div>
            </div>
            <script>
                // Hide the success message and action links after 2.5 seconds, then redirect to dashboard
                setTimeout(function() {
                    var section = document.getElementById('approval-success-section');
                    if (section) section.style.display = 'none';
                    window.location.href = 'admin_dashboard.php';
                }, 2500);
            </script>
        <?php elseif (isset($approval_error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $approval_error; ?>
            </div>
        <?php else: ?>
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Pending Enrollments</h2>
                    <div class="search-container">
                        <input type="text" class="search-input" placeholder="Search enrollments...">
                        <button class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <?php if (empty($pending_enrollments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No pending course enrollments</p>
                    </div>
                <?php else: ?>
                    <div class="enrollments-grid">
                        <?php foreach ($pending_enrollments as $enrollment): ?>
                            <div class="enrollment-card">
                                <div class="enrollment-header">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($enrollment['student_name'], 0, 2)); ?>
                                    </div>
                                    <div class="enrollment-info">
                                        <h3><?php echo htmlspecialchars($enrollment['student_name']); ?></h3>
                                        <p class="student-email"><?php echo htmlspecialchars($enrollment['student_email']); ?></p>
                                    </div>
                                </div>
                                <div class="course-info">
                                    <h4><?php echo htmlspecialchars($enrollment['course_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($enrollment['course_description']); ?></p>
                                    <p class="enrollment-date">Requested: <?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></p>
                                </div>
                                <div class="enrollment-actions">
                                    <form method="POST" action="" class="approval-form">
                                        <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['enrollment_id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="" class="approval-form" onsubmit="return confirm('Are you sure you want to reject this enrollment?');">
                                        <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['enrollment_id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-reject">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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

.dashboard-layout {
    display: flex;
    min-height: 100vh;
    align-items: center;
    justify-content: center;
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
    margin-top: -150px;
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

.search-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-input {
    background-color: #1e1f25;
    border: 1px solid #373a40;
    border-radius: 6px;
    padding: 8px 12px;
    color: white;
    font-size: 14px;
    width: 200px;
}

.search-input:focus {
    outline: none;
    border-color: #3498db;
}

.search-btn {
    background-color: #3498db;
    border: none;
    border-radius: 6px;
    padding: 8px 12px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.search-btn:hover {
    background-color: #2980b9;
}

.enrollments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.enrollment-card {
    background-color: #1e1f25;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    transition: transform 0.3s ease;
}

.enrollment-card:hover {
    transform: translateY(-3px);
}

.enrollment-header {
    display: flex;
    align-items: center;
    gap: 15px;
}

.student-avatar {
    width: 50px;
    height: 50px;
    background-color: #3498db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 18px;
}

.enrollment-info h3 {
    color: white;
    font-size: 16px;
    margin: 0 0 4px 0;
}

.student-email {
    color: #a1a1a1;
    font-size: 14px;
    margin: 0;
}

.course-info {
    padding: 15px;
    background-color: rgb(53, 54, 57);
    border-radius: 6px;
}

.course-info h4 {
    color: white;
    font-size: 16px;
    margin: 0 0 8px 0;
}

.course-info p {
    color: #a1a1a1;
    font-size: 14px;
    margin: 0 0 8px 0;
}

.enrollment-date {
    color: #a1a1a1;
    font-size: 12px;
    margin: 0;
}

.enrollment-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.approval-form {
    margin: 0;
}

.btn-approve, .btn-reject {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
}

.btn-approve {
    background-color: #2ecc71;
    color: white;
}

.btn-approve:hover {
    background-color: #27ae60;
}

.btn-reject {
    background-color: #e74c3c;
    color: white;
}

.btn-reject:hover {
    background-color: #c0392b;
}

.btn-approve i, .btn-reject i {
    margin-right: 6px;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #a1a1a1;
}

.empty-state i {
    font-size: 48px;
    color: #2ecc71;
    margin-bottom: 16px;
}

.empty-state p {
    font-size: 16px;
    margin: 0;
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

    .search-container {
        display: none;
    }

    .enrollments-grid {
        grid-template-columns: 1fr;
    }
}
</style>
