<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    echo '<div style="color:red; padding:20px;">Invalid exam ID.</div>';
    exit();
}

$exam_id = (int)$_GET['exam_id'];

// Fetch exam info
$stmt = $pdo->prepare('SELECT exam_name FROM exams WHERE exam_id = ?');
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();
if (!$exam) {
    echo '<div style="color:red; padding:20px;">Exam not found.</div>';
    exit();
}

// Fetch top students for this exam
$stmt = $pdo->prepare('
    SELECT s.student_id, s.full_name, ea.score
    FROM exam_attempts ea
    JOIN students s ON ea.student_id = s.student_id
    WHERE ea.exam_id = ?
    ORDER BY ea.score DESC
    LIMIT 10
');
$stmt->execute([$exam_id]);
$top_students = $stmt->fetchAll();

$page_title = 'Top Students - ' . htmlspecialchars($exam['exam_name']);
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
        <div class="content-header" style="margin-bottom: 30px;">
            <h1 style="font-size: 2rem; color: #fff; font-weight: 600;">Top Students - <?php echo htmlspecialchars($exam['exam_name']); ?></h1>
            <a href="exams.php" class="btn btn-secondary" style="margin-bottom: 20px;"><i class="fas fa-arrow-left"></i> Back to Exams</a>
        </div>
        <div class="dashboard-section">
            <?php if (empty($top_students)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-graduate"></i>
                    <p>No students have taken this exam yet.</p>
                </div>
            <?php else: ?>
                <div class="card top-students-card">
                    <div class="card-header" style="background: linear-gradient(90deg, #3498db 0%, #6a82fb 100%); border-radius: 10px 10px 0 0; padding: 18px 24px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-trophy" style="color: gold; font-size: 1.5rem;"></i>
                        <span style="font-size: 1.2rem; color: #fff; font-weight: 500; letter-spacing: 1px;">Top 10 Students</span>
                    </div>
                    <div style="padding: 0 24px 24px 24px;">
                        <table class="table top-students-table" style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student Name</th>
                                    <th>Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($top_students as $student): ?>
                                    <tr class="top-row-<?php echo $rank; ?>">
                                        <td><?php if ($rank === 1): ?><i class="fas fa-trophy" style="color: gold;"></i> <?php endif; ?><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['score']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
    .top-students-table th, .top-students-table td {
        color: #fff;
        font-size: 17px;
        vertical-align: middle;
        padding: 14px 12px;
    }
    .top-students-table th {
        background-color: #23242a;
        border-bottom: 2px solid #3498db;
        font-weight: 600;
        letter-spacing: 1px;
    }
    .top-students-table tr {
        background-color: rgba(255,255,255,0.03);
        transition: background 0.2s;
    }
    .top-students-table tr:hover {
        background-color: #23242a;
    }
    .top-students-table tr:nth-child(even) {
        background-color: rgba(255,255,255,0.07);
    }
    .top-students-table td {
        border-bottom: 1px solid #373a40;
    }
    .top-students-card {
        background: linear-gradient(135deg, #23242a 0%, #353639 100%);
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.12);
        margin-top: 10px;
        margin-bottom: 10px;
    }
    .card-header {
        border-bottom: 1px solid #3498db;
    }
    .empty-state {
        text-align: center;
        color: #a1a1a1;
        padding: 40px 0;
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
    </style>
</body>
</html> 