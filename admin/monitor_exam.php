<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: exams.php');
    exit();
}

$exam_id = $_GET['id'];

// Get exam details
$stmt = $pdo->prepare("
    SELECT e.*, c.course_name 
    FROM exams e 
    JOIN courses c ON e.course_id = c.course_id 
    WHERE e.exam_id = ?
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    header('Location: exams.php');
    exit();
}

// Get all enrolled students and their exam status
$stmt = $pdo->prepare("
    SELECT 
        s.student_id,
        s.full_name,
        s.email,
        ea.status as attempt_status,
        ea.score,
        ea.start_time,
        ea.end_time,
        CASE 
            WHEN ea.status = 'finished' THEN 'Completed'
            WHEN ea.status = 'in_progress' THEN 'In Progress'
            WHEN ea.status IS NULL THEN 'Not Started'
        END as participation_status
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    LEFT JOIN exam_attempts ea ON ea.student_id = s.student_id AND ea.exam_id = ?
    WHERE e.course_id = ?
    ORDER BY s.full_name
");
$stmt->execute([$exam_id, $exam['course_id']]);
$students = $stmt->fetchAll();

// Calculate statistics
$total_students = count($students);
$completed = 0;
$in_progress = 0;
$not_started = 0;
$total_score = 0;

foreach ($students as $student) {
    if ($student['participation_status'] === 'Completed') {
        $completed++;
        $total_score += $student['score'];
    } elseif ($student['participation_status'] === 'In Progress') {
        $in_progress++;
    } else {
        $not_started++;
    }
}

$average_score = $completed > 0 ? round($total_score / $completed, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Exam - <?php echo htmlspecialchars($exam['exam_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/finalProject/assets/css/styles.css">
</head>
<body class="dashboard-layout">
    <div class="sidebar">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>Exam Monitoring</h1>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F d, Y'); ?>
                <span class="separator">|</span>
                <i class="far fa-clock"></i>
                <span id="live-time"></span>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h2><?php echo htmlspecialchars($exam['exam_name']); ?></h2>
                <div class="exam-info">
                    <span class="course-name"><?php echo htmlspecialchars($exam['course_name']); ?></span>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo $total_students; ?></div>
                    <div class="stats-label">Total Students</div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo $completed; ?></div>
                    <div class="stats-label">Completed</div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-number"><?php echo $in_progress; ?></div>
                    <div class="stats-label">In Progress</div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-hourglass-start"></i>
                    </div>
                    <div class="stats-number"><?php echo $not_started; ?></div>
                    <div class="stats-label">Not Started</div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stats-number"><?php echo $average_score; ?>%</div>
                    <div class="stats-label">Average Score</div>
                </div>
            </div>

            <div class="table-responsive mt-4">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Score</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower(str_replace(' ', '_', $student['participation_status'])); ?>">
                                        <?php echo $student['participation_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($student['score'] !== null): ?>
                                        <?php echo $student['score']; ?>%
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $student['start_time'] ? date('M d, Y h:i A', strtotime($student['start_time'])) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo $student['end_time'] ? date('M d, Y h:i A', strtotime($student['end_time'])) : '-'; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($student['start_time'] && $student['end_time']) {
                                        $start = new DateTime($student['start_time']);
                                        $end = new DateTime($student['end_time']);
                                        $duration = $start->diff($end);
                                        echo $duration->format('%H:%I:%S');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
            
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            
            document.getElementById('live-time').textContent = hours + ':' + minutes + ' ' + ampm;
        }

        updateTime();
        setInterval(updateTime, 1000);
    </script>

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
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
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
            margin-left: 250px;
            margin-top: 100px;
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

        .exam-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .course-name {
            color: #a1a1a1;
            font-size: 0.9rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background-color: #1e1f25;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid #373a40;
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .stats-icon i {
            font-size: 1.5rem;
            color: #3498db;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: white;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #a1a1a1;
            font-size: 0.9rem;
        }

        .table {
            color: #a1a1a1;
            margin: 0;
            background-color: #1e1f25;
            border-radius: 8px;
            overflow: hidden;
        }

        .table thead th {
            background-color: rgb(53, 54, 57);
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
            background-color: rgb(53, 54, 57);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .status-badge.completed {
            background-color: #2ecc71;
            color: white;
        }

        .status-badge.in_progress {
            background-color: #f1c40f;
            color: #2c3e50;
        }

        .status-badge.not_started {
            background-color: #95a5a6;
            color: white;
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
                margin-left: 70px;
                padding: 20px;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html> 