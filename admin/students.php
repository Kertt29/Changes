<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Students';

// Pagination logic
$students_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $students_per_page;
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_pages = ceil($total_students / $students_per_page);
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
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>Students</h1>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F d, Y'); ?>
                <span class="separator">|</span>
                <i class="far fa-clock"></i>
                <span id="live-time"></span>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-section">
            <div class="section-header">
                <h2>All Students</h2>
                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Search students...">
                    <button class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Enrolled Courses</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT s.*, 
                                   COUNT(DISTINCT e.course_id) as enrolled_courses,
                                   CASE WHEN s.is_approved = 1 THEN 'Approved' ELSE 'Pending' END as status
                            FROM students s
                            LEFT JOIN enrollments e ON s.student_id = e.student_id
                            GROUP BY s.student_id
                            ORDER BY s.created_at DESC
                            LIMIT :limit OFFSET :offset
                        ");
                        $stmt->bindValue(':limit', $students_per_page, PDO::PARAM_INT);
                        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                        $stmt->execute();
                        while ($student = $stmt->fetch()) {
                            echo "<tr>";
                            echo "<td>{$student['full_name']}</td>";
                            echo "<td>{$student['email']}</td>";
                            echo "<td><span class='status-badge " . strtolower($student['status']) . "'>{$student['status']}</span></td>";
                            echo "<td>{$student['enrolled_courses']}</td>";
                            echo "<td class='action-buttons'>
                                    <a href='view_student.php?id={$student['student_id']}' class='btn-action btn-view'>
                                        <i class='fas fa-eye'></i>
                                    </a>
                                    <a href='delete_student.php?id={$student['student_id']}' class='btn-action btn-delete' onclick='return confirm(\"Are you sure you want to delete this student?\");'>
                                        <i class='fas fa-trash'></i>
                                    </a>
                                  </td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <nav aria-label="Page navigation" class="pagination-container">
                <ul class="pagination">
                    <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $students_per_page; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if($i == $page) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $students_per_page; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $students_per_page; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
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

.table {
    color: #a1a1a1;
    margin: 0;
}

.table thead th {
    background-color: #1e1f25;
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
    background-color: #1e1f25;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-badge.approved {
    background-color: #2ecc71;
    color: white;
}

.status-badge.pending {
    background-color: #f1c40f;
    color: #2c3e50;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-view {
    background-color: #3498db;
}

.btn-view:hover {
    background-color: #2980b9;
}

.btn-edit {
    background-color: #f1c40f;
}

.btn-edit:hover {
    background-color: #f39c12;
}

.btn-delete {
    background-color: #e74c3c;
}

.btn-delete:hover {
    background-color: #c0392b;
}

.pagination-container {
    margin-top: 20px;
    display: flex;
    justify-content: center;
}

.pagination {
    display: flex;
    gap: 5px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.page-item {
    margin: 0 2px;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background-color: #1e1f25;
    border: 1px solid #373a40;
    border-radius: 6px;
    color: #a1a1a1;
    text-decoration: none;
    transition: all 0.3s ease;
}

.page-link:hover {
    background-color: #3498db;
    border-color: #3498db;
    color: white;
}

.page-item.active .page-link {
    background-color: #3498db;
    border-color: #3498db;
    color: white;
}

.page-item.disabled .page-link {
    background-color: #1e1f25;
    border-color: #373a40;
    color: #373a40;
    cursor: not-allowed;
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

    .table-responsive {
        overflow-x: auto;
    }
}
</style> 