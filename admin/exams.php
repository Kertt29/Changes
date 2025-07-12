<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../config/timezone.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: /finalProject/login.php');
    exit();
}

// Handle Reopen Exam POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reopen_exam_id'])) {
    $exam_id = intval($_POST['reopen_exam_id']);
    $reopen_start_date = $_POST['reopen_start_date'] ?? '';
    $reopen_end_date = $_POST['reopen_end_date'] ?? '';
    
    // Validate dates
    if (empty($reopen_start_date) || empty($reopen_end_date)) {
        $_SESSION['error'] = 'Please select both start and end dates for the reopened exam.';
        header('Location: exams.php');
        exit();
    }
    
    // Validate that end date is after start date
    if (strtotime($reopen_end_date) <= strtotime($reopen_start_date)) {
        $_SESSION['error'] = 'End date must be after start date.';
        header('Location: exams.php');
        exit();
    }
    
    try {
        // Find all students who did NOT take the exam using exam_attempts
        $stmt = $pdo->prepare('SELECT student_id FROM students WHERE student_id NOT IN (SELECT student_id FROM exam_attempts WHERE exam_id = ? AND status = "finished")');
        $stmt->execute([$exam_id]);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Insert into reopened_exams for each student
        $insertStmt = $pdo->prepare('INSERT INTO reopened_exams (exam_id, student_id, reopen_start_date, reopen_end_date) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reopen_start_date = VALUES(reopen_start_date), reopen_end_date = VALUES(reopen_end_date)');
        foreach ($students as $student_id) {
            $insertStmt->execute([$exam_id, $student_id, $reopen_start_date, $reopen_end_date]);
        }
        $_SESSION['success'] = 'Exam reopened for students who missed it.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error reopening exam: ' . $e->getMessage();
    }
    header('Location: exams.php');
    exit();
}

$page_title = 'Exams';
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
        <div class="content-header">
            <h1>Exams</h1>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i>
                <?php echo formatDate(getCurrentTime()); ?>
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
                <h2>All Exams</h2>
                <a href="create_exam.php" class="btn-create">
                    <i class="fas fa-plus"></i> Create Exam
                </a>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Course</th>
                            <th>Duration</th>
                            <th>Questions</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("
                            SELECT e.*, c.course_name,
                                   CASE 
                                       WHEN NOW() < e.start_date THEN 'not_started'
                                       WHEN NOW() > e.end_date THEN 'ended'
                                       ELSE 'active'
                                   END as exam_status
                            FROM exams e 
                            LEFT JOIN courses c ON e.course_id = c.course_id 
                            ORDER BY e.created_at DESC
                        ");
                        while ($exam = $stmt->fetch()) {
                            $status_class = '';
                            $status_text = '';
                            // Check if the exam is reopened
                            $reopenedStmt = $pdo->prepare('SELECT COUNT(*) FROM reopened_exams WHERE exam_id = ?');
                            $reopenedStmt->execute([$exam['exam_id']]);
                            $is_reopened = $reopenedStmt->fetchColumn() > 0;

                            if ($is_reopened) {
                                $status_class = 'open';
                                $status_text = 'Reopened';
                            } else {
                                switch($exam['exam_status']) {
                                    case 'not_started':
                                        $status_class = 'pending';
                                        $status_text = 'Not Started';
                                        break;
                                    case 'active':
                                        $status_class = 'active';
                                        $status_text = 'Active';
                                        break;
                                    case 'ended':
                                        $status_class = 'ended';
                                        $status_text = 'Ended';
                                        break;
                                }
                            }
                            
                            echo "<tr>";
                            echo "<td>{$exam['exam_name']}</td>";
                            echo "<td>{$exam['course_name']}</td>";
                            echo "<td>{$exam['duration']} minutes</td>";
                            echo "<td>{$exam['total_questions']}</td>";
                            echo "<td><span class='status-badge {$status_class}'>{$status_text}</span></td>";
                            echo "<td class='action-buttons'>
                                    <a href='view_exam.php?id={$exam['exam_id']}' class='btn-action btn-view'>
                                        <i class='fas fa-eye'></i>
                                    </a>
                                    <a href='edit_exam.php?id={$exam['exam_id']}' class='btn-action btn-edit'>
                                        <i class='fas fa-edit'></i>
                                    </a>
                                    <a href='delete_exam.php?id={$exam['exam_id']}' class='btn-action btn-delete' onclick='return confirm(\"Are you sure you want to delete this exam?\");'>
                                        <i class='fas fa-trash'></i>
                                    </a>";
                            if ($exam['exam_status'] === 'ended') {
                                echo "<a href='top_students.php?exam_id={$exam['exam_id']}' class='btn-action btn-view' title='Top Students'>
                                        <i class='fas fa-trophy'></i>
                                    </a>";
                            }
                            echo "<button type='button' class='btn btn-warning btn-sm' style='margin-left:5px;' onclick='openReopenModal({$exam['exam_id']}, \"{$exam['exam_name']}\")'>
                                        <i class='fas fa-redo'></i> Reopen Exam
                                    </button>
                              </td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reopen Exam Modal -->
    <div class="modal fade" id="reopenExamModal" tabindex="-1" aria-labelledby="reopenExamModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reopenExamModalLabel">Reopen Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="exams.php">
                    <div class="modal-body">
                        <input type="hidden" name="reopen_exam_id" id="reopen_exam_id">
                        <p>You are about to reopen the exam: <strong id="exam_name_display"></strong></p>
                        <p>This will allow students who missed the exam to take it during the specified time period.</p>
                        
                        <div class="mb-3">
                            <label for="reopen_start_date" class="form-label">Reopen Start Date & Time</label>
                            <input type="datetime-local" class="form-control" id="reopen_start_date" name="reopen_start_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reopen_end_date" class="form-label">Reopen End Date & Time</label>
                            <input type="datetime-local" class="form-control" id="reopen_end_date" name="reopen_end_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Reopen Exam</button>
                    </div>
                </form>
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

        function openReopenModal(examId, examName) {
            document.getElementById('reopen_exam_id').value = examId;
            document.getElementById('exam_name_display').textContent = examName;
            
            // Set default values (current time + 1 hour for start, + 2 hours for end)
            const now = new Date();
            const startTime = new Date(now.getTime() + 60 * 60 * 1000); // 1 hour from now
            const endTime = new Date(now.getTime() + 2 * 60 * 60 * 1000); // 2 hours from now
            
            document.getElementById('reopen_start_date').value = startTime.toISOString().slice(0, 16);
            document.getElementById('reopen_end_date').value = endTime.toISOString().slice(0, 16);
            
            const modal = new bootstrap.Modal(document.getElementById('reopenExamModal'));
            modal.show();
        }

        // Update time immediately and then every second
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

.btn-create {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    background-color: #2ecc71;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-create:hover {
    background-color: #27ae60;
    color: white;
}

.btn-create i {
    margin-right: 6px;
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

.status-badge.active {
    background-color: #2ecc71;
    color: white;
}

.status-badge.pending {
    background-color: #f1c40f;
    color: #2c3e50;
}

.status-badge.ended {
    background-color: #95a5a6;
    color: white;
}

.status-badge.open {
    background-color: #3498db;
    color: white;
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

    .table-responsive {
        overflow-x: auto;
    }
}
</style> 