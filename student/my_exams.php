<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

checkStudentLogin();

$student_id = $_SESSION['student_id'];

// Get all available exams with proper scheduling checks including reopened exams
$stmt = $pdo->prepare("
    SELECT e.*, c.course_name,
           (SELECT status FROM exam_attempts 
            WHERE exam_id = e.exam_id AND student_id = ? 
            ORDER BY attempt_id DESC LIMIT 1) as attempt_status,
           CASE 
               WHEN NOW() < e.start_date THEN 'not_started'
               WHEN NOW() > e.end_date THEN 'ended'
               ELSE 'active'
           END as exam_status,
           re.reopen_start_date,
           re.reopen_end_date,
           CASE 
               WHEN re.reopen_start_date IS NOT NULL AND NOW() >= re.reopen_start_date AND NOW() <= re.reopen_end_date THEN 'reopened_active'
               WHEN re.reopen_start_date IS NOT NULL AND NOW() < re.reopen_start_date THEN 'reopened_not_started'
               WHEN re.reopen_start_date IS NOT NULL AND NOW() > re.reopen_end_date THEN 'reopened_ended'
               ELSE NULL
           END as reopen_status
    FROM exams e
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN reopened_exams re ON e.exam_id = re.exam_id AND re.student_id = ?
    ORDER BY e.start_date ASC
");
$stmt->execute([$student_id, $student_id]);
$exams = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exams - ExaMatrix</title>
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
                    <a href="my_exams.php" class="nav-link active">
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
            <h1>My Exams</h1>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F d, Y'); ?>
                <span class="separator">|</span>
                <i class="far fa-clock"></i>
                <span id="live-time"></span>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="exams-grid">
                <?php foreach ($exams as $exam): ?>
                    <div class="exam-card" data-start-date="<?php echo $exam['start_date']; ?>" data-end-date="<?php echo $exam['end_date']; ?>" data-reopen-start="<?php echo $exam['reopen_start_date']; ?>" data-reopen-end="<?php echo $exam['reopen_end_date']; ?>">
                        <div class="exam-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="exam-content">
                            <h4><?php echo htmlspecialchars($exam['exam_name']); ?></h4>
                            <p class="exam-course"><?php echo htmlspecialchars($exam['course_name']); ?></p>
                            
                            <div class="exam-details">
                                <div class="detail-item">
                                    <i class="far fa-clock"></i>
                                    <span><?php echo $exam['duration']; ?> minutes</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-question-circle"></i>
                                    <span><?php echo $exam['total_questions']; ?> questions</span>
                                </div>
                                <?php if ($exam['reopen_start_date']): ?>
                                    <!-- Show reopened exam dates -->
                                    <div class="detail-item">
                                        <i class="fas fa-redo"></i>
                                        <span>Reopened: <?php echo date('M d, Y h:i A', strtotime($exam['reopen_start_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-stop-circle"></i>
                                        <span>Closes: <?php echo date('M d, Y h:i A', strtotime($exam['reopen_end_date'])); ?></span>
                                    </div>
                                <?php else: ?>
                                    <!-- Show original exam dates -->
                                    <div class="detail-item">
                                        <i class="far fa-calendar"></i>
                                        <span>Opens: <?php echo date('M d, Y h:i A', strtotime($exam['start_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="far fa-calendar-times"></i>
                                        <span>Closes: <?php echo date('M d, Y h:i A', strtotime($exam['end_date'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="exam-actions">
                                <?php 
                                // Show exam availability status instead of attempt status
                                if ($exam['attempt_status'] === 'finished') {
                                    echo '<span class="badge bg-success">Completed</span>';
                                } else {
                                    // Determine availability status
                                    if ($exam['reopen_start_date']) {
                                        // This is a reopened exam
                                        if ($exam['reopen_status'] === 'reopened_active') {
                                            echo '<span class="badge bg-success">Available</span>';
                                        } elseif ($exam['reopen_status'] === 'reopened_not_started') {
                                            echo '<span class="badge bg-warning">Reopening Soon</span>';
                                        } elseif ($exam['reopen_status'] === 'reopened_ended') {
                                            echo '<span class="badge bg-danger">Reopened Exam Ended</span>';
                                        }
                                    } else {
                                        // Original exam
                                        if ($exam['exam_status'] === 'active') {
                                            echo '<span class="badge bg-success">Available</span>';
                                        } elseif ($exam['exam_status'] === 'not_started') {
                                            echo '<span class="badge bg-warning">Not Started</span>';
                                        } else {
                                            echo '<span class="badge bg-danger">Exam Ended</span>';
                                        }
                                    }
                                }
                                ?>
                                
                                <?php if ($exam['attempt_status'] !== 'finished'): ?>
                                    <?php 
                                    // Determine if exam is available based on reopen status or original status
                                    $is_available = false;
                                    $status_message = '';
                                    
                                    if ($exam['reopen_start_date']) {
                                        // This is a reopened exam - check reopen status
                                        if ($exam['reopen_status'] === 'reopened_active') {
                                            $is_available = true;
                                        } elseif ($exam['reopen_status'] === 'reopened_not_started') {
                                            $status_message = 'Reopened exam will open on ' . date('F d, Y h:i A', strtotime($exam['reopen_start_date']));
                                        } elseif ($exam['reopen_status'] === 'reopened_ended') {
                                            $status_message = 'Reopened exam has ended on ' . date('F d, Y h:i A', strtotime($exam['reopen_end_date']));
                                        }
                                    } else {
                                        // Original exam - check original status
                                        if ($exam['exam_status'] === 'active') {
                                            $is_available = true;
                                        } elseif ($exam['exam_status'] === 'not_started') {
                                            $status_message = 'Exam will open on ' . date('F d, Y h:i A', strtotime($exam['start_date']));
                                        } else {
                                            $status_message = 'Exam has ended on ' . date('F d, Y h:i A', strtotime($exam['end_date']));
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($is_available): ?>
                                        <a href="take_exam.php?exam_id=<?php echo $exam['exam_id']; ?>" 
                                           class="btn-start">
                                            <i class="fas fa-play"></i> Start Exam
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-start" disabled 
                                                title="<?php echo $status_message; ?>">
                                            <i class="fas fa-lock"></i> Not Available
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="view_result.php?exam_id=<?php echo $exam['exam_id']; ?>" 
                                       class="btn-view">
                                        <i class="fas fa-eye"></i> View Result
                                    </a>
                                <?php endif; ?>
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

            // Check exam availability
            const examCards = document.querySelectorAll('.exam-card');
            examCards.forEach(card => {
                const startDate = new Date(card.dataset.startDate);
                const endDate = new Date(card.dataset.endDate);
                const reopenStart = card.dataset.reopenStart ? new Date(card.dataset.reopenStart) : null;
                const reopenEnd = card.dataset.reopenEnd ? new Date(card.dataset.reopenEnd) : null;
                const startButton = card.querySelector('.btn-start');
                
                if (startButton && !startButton.disabled) {
                    let isAvailable = false;
                    let statusMessage = '';
                    
                    if (reopenStart && reopenEnd) {
                        // This is a reopened exam
                        if (now >= reopenStart && now <= reopenEnd) {
                            isAvailable = true;
                        } else if (now < reopenStart) {
                            statusMessage = `Reopened exam will open on ${reopenStart.toLocaleString()}`;
                        } else {
                            statusMessage = `Reopened exam has ended on ${reopenEnd.toLocaleString()}`;
                        }
                    } else {
                        // Original exam
                        if (now >= startDate && now <= endDate) {
                            isAvailable = true;
                        } else if (now < startDate) {
                            statusMessage = `Exam will open on ${startDate.toLocaleString()}`;
                        } else {
                            statusMessage = `Exam has ended on ${endDate.toLocaleString()}`;
                        }
                    }
                    
                    if (!isAvailable) {
                        startButton.disabled = true;
                        startButton.innerHTML = '<i class="fas fa-lock"></i> Not Available';
                        startButton.title = statusMessage;
                    }
                }
            });
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

.main-content {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
    margin-left:0px;
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

.dashboard-section {
    background-color: rgb(53, 54, 57);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 36px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.exams-grid {
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
    box-shadow: 0 4px 16px rgba(52, 152, 219, 0.08), 0 1.5px 4px rgba(0,0,0,0.08);
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

.exam-course {
    color: #a1a1a1;
    font-size: 14px;
    margin: 0 0 15px 0;
}

.exam-details {
    display: flex;
    gap: 15px;
    color: #a1a1a1;
    font-size: 12px;
    margin-bottom: 15px;
    margin-left: -50px;
}

.exam-details i {
    margin-right: 4px;
    color: #3498db;
}

.exam-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
}

.btn-start, .btn-view {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    border-radius: 6px;
    font-size: 14px;
    text-decoration: none;
    font-weight: 500;
    transition: background 0.2s, color 0.2s;
    border: none;
}

.btn-start {
    background-color: #2ecc71;
    color: white;
}

.btn-start:hover {
    background-color: #27ae60;
    color: white;
}

.btn-view {
    background-color: #3498db;
    color: white;
}

.btn-view:hover {
    background-color: #2980b9;
    color: white;
}

.exam-status, .btn-start[disabled], .btn-view[disabled] {
    opacity: 0.85;
    cursor: not-allowed;
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
        padding: 15px;
    }
    .nav-link i {
        margin: 0;
    }
    .student-section {
        padding: 10px;
    }
    .main-content {
        padding: 16px;
    }
    .exams-grid {
        gap: 10px;
    }
    .exam-card {
        padding: 10px;
    }
}
</style> 