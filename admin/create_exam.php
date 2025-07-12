<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: /finalProject/login.php');
    exit();
}

// Get all courses for the dropdown
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exam_name = trim($_POST['exam_name']);
    $course_id = $_POST['course_id'];
    $description = trim($_POST['description']);
    $total_questions = (int)$_POST['total_questions'];
    $min_multiple_choice = (int)$_POST['min_multiple_choice'];
    $min_true_false = (int)$_POST['min_true_false'];
    $min_matching = (int)$_POST['min_matching'];
    $passing_score = (int)$_POST['passing_score'];
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $allowed_types = isset($_POST['allowed_types']) ? $_POST['allowed_types'] : [];

    // Calculate duration in minutes from start and end date/time
    $duration = 0;
    if (!empty($start_date) && !empty($end_date)) {
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $duration = (int)(($end - $start) / 60);
        if ($duration <= 0) {
            $errors[] = "End date/time must be after start date/time and duration must be positive.";
        }
    }

    // Get allowed types and set minimums accordingly
    $min_multiple_choice = in_array('multiple_choice', $allowed_types) ? (int)$_POST['min_multiple_choice'] : 0;
    $min_true_false = in_array('true_false', $allowed_types) ? (int)$_POST['min_true_false'] : 0;
    $min_matching = in_array('Matching Type', $allowed_types) ? (int)$_POST['min_matching'] : 0;

    if (in_array('multiple_choice', $allowed_types) && $min_multiple_choice < 10) {
        $errors[] = "Minimum multiple choice questions must be at least 10";
    }
    if (in_array('true_false', $allowed_types) && $min_true_false < 10) {
        $errors[] = "Minimum true/false questions must be at least 10";
    }
    if (in_array('Matching Type', $allowed_types) && $min_matching < 10) {
        $errors[] = "Minimum Matching Type questions must be at least 10";
    }
    if ($total_questions < ($min_multiple_choice + $min_true_false + $min_matching)) {
        $errors[] = "Total questions must be at least equal to the sum of minimum questions per selected type";
    }
    if ($passing_score <= 0 || $passing_score > 100) {
        $errors[] = "Passing score must be between 1 and 100";
    }
    if (empty($start_date)) {
        $errors[] = "Start date is required";
    }
    if (empty($end_date)) {
        $errors[] = "End date is required";
    }
    if (!empty($start_date) && !empty($end_date)) {
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        if ($start >= $end) {
            $errors[] = "End date must be after start date";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO exams (exam_name, course_id, description, duration, total_questions, min_multiple_choice, min_true_false, min_matching, passing_score, start_date, end_date, allowed_types) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([$exam_name, $course_id, $description, $duration, $total_questions, $min_multiple_choice, $min_true_false, $min_matching, $passing_score, $start_date, $end_date, implode(',', $allowed_types)]);
            $exam_id = $pdo->lastInsertId();
            $_SESSION['success'] = "Exam created successfully! Now you can add questions.";
            header("Location: add_questions.php?exam_id=$exam_id");
            exit();
        } catch(PDOException $e) {
            $errors[] = "Error creating exam: " . $e->getMessage();
        }
    }
}

$page_title = 'Create Exam';
// Get today's date for minimum date validation
$today = date('Y-m-d');
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
            <h1>Create Exam</h1>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F d, Y'); ?>
                <span class="separator">|</span>
                <i class="far fa-clock"></i>
                <?php echo date('h:i A'); ?>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="dashboard-section">
            <form method="POST" action="" class="exam-form">
                <div class="form-section">
                    <h3 class="section-title">Basic Information</h3>
                    <div class="form-group mb-4">
                        <label for="exam_name" class="form-label">Exam Name</label>
                        <input type="text" class="form-control" id="exam_name" name="exam_name" placeholder="Enter exam name" value="<?php echo isset($_POST['exam_name']) ? htmlspecialchars($_POST['exam_name']) : ''; ?>" required>
                    </div>

                    <div class="form-group mb-4">
                        <label for="course_id" class="form-label">Course</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Enter exam description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Exam Settings</h3>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="duration" class="form-label">Duration (minutes)</label>
                                <input type="number" class="form-control" id="duration" name="duration" value="<?php echo isset($duration) ? htmlspecialchars($duration) : ''; ?>" readonly>
                                <small class="form-text text-muted">Duration is automatically calculated from the schedule.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="total_questions" class="form-label">Total Questions</label>
                                <input type="number" class="form-control" id="total_questions" name="total_questions" placeholder="Enter number of questions" value="<?php echo isset($_POST['total_questions']) ? htmlspecialchars($_POST['total_questions']) : ''; ?>" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="min_multiple_choice" class="form-label">Minimum Multiple Choice Questions</label>
                                <input type="number" class="form-control" id="min_multiple_choice" name="min_multiple_choice" value="<?php echo isset($_POST['min_multiple_choice']) ? htmlspecialchars($_POST['min_multiple_choice']) : '10'; ?>" min="10" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="min_true_false" class="form-label">Minimum True/False Questions</label>
                                <input type="number" class="form-control" id="min_true_false" name="min_true_false" value="<?php echo isset($_POST['min_true_false']) ? htmlspecialchars($_POST['min_true_false']) : '10'; ?>" min="10" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="min_matching" class="form-label">Minimum Matching Type Questions</label>
                                <input type="number" class="form-control" id="min_matching" name="min_matching" value="<?php echo isset($_POST['min_matching']) ? htmlspecialchars($_POST['min_matching']) : '10'; ?>" min="10" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="passing_score" class="form-label">Passing Score (%)</label>
                                <input type="number" class="form-control" id="passing_score" name="passing_score" placeholder="Enter passing score" value="<?php echo isset($_POST['passing_score']) ? htmlspecialchars($_POST['passing_score']) : ''; ?>" min="1" max="100" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Schedule</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date" class="form-label">Start Date & Time</label>
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>" required>
                                <small class="form-text text-muted">Select a present or future date. You can set any time (AM/PM).</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date" class="form-label">End Date & Time</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>" required>
                                <small class="form-text text-muted">Select an end date/time that comes after the start date/time.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Allowed Question Types</h3>
                    <div class="mb-3">
                        <label class="form-label">Allowed Question Types</label><br>
                        <input type="checkbox" name="allowed_types[]" value="multiple_choice" checked> Multiple Choice<br>
                        <input type="checkbox" name="allowed_types[]" value="true_false" checked> True/False<br>
                        <input type="checkbox" name="allowed_types[]" value="Matching Type" checked> Matching Type<br>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="exams.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Exam & Add Questions
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            // Get today's date in YYYY-MM-DD format for comparison
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const todayStr = today.toISOString().split('T')[0];
            
            // Set minimum date to today for both inputs
            startDateInput.min = todayStr + 'T00:00';
            endDateInput.min = todayStr + 'T00:00';
            
            // Function to format date for comparison
            function formatDate(date) {
                return date.toISOString().slice(0, 16);
            }
            
            // Validate start date
            startDateInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const now = new Date();
                
                // If selected date is in the past, reset to current date/time
                if (selectedDate < today) {
                    this.value = formatDate(now);
                }
                
                // Update end date minimum
                if (this.value) {
                    endDateInput.min = this.value;
                    if (endDateInput.value && endDateInput.value < this.value) {
                        endDateInput.value = this.value;
                    }
                }
            });
            
            // Validate end date
            endDateInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const startDate = new Date(startDateInput.value || todayStr);
                
                // If end date is before start date, set it to start date
                if (selectedDate < startDate) {
                    this.value = startDateInput.value;
                }
            });
            
            // Initial validation on page load
            const now = new Date();
            if (!startDateInput.value) {
                startDateInput.value = formatDate(now);
            }
            if (!endDateInput.value) {
                // Set default end time to 1 hour after start time
                const defaultEnd = new Date(startDateInput.value);
                defaultEnd.setHours(defaultEnd.getHours() + 1);
                endDateInput.value = formatDate(defaultEnd);
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
    margin-top: 550px;
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
    padding: 30px;
    margin-bottom: -30px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.form-section {
    background-color: #1e1f25;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.section-title {
    color: #ffffff;
    font-size: 1rem;
    margin-bottom: 12px;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    font-weight: 500;
}

.form-group {
    margin-bottom: 0.8rem;
}

.form-label {
    color: #ffffff;
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
    font-weight: 500;
}

.form-text {
    color: #a1a1a1;
    font-size: 0.85rem;
    margin-top: 0.5rem;
}

.form-control, .form-select {
    background-color: #25262b;
    border: 1px solid #373a40;
    color: #ffffff;
    padding: 0.5rem 0.8rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    background-color: #25262b;
    border-color: #3498db;
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
    outline: none;
}

.form-control:hover, .form-select:hover {
    border-color: #3498db;
}

.form-control::placeholder {
    color: #6c757d;
}

textarea.form-control {
    min-height: 35px;
    max-height: 100px;
    line-height: 1.3;
    font-size: 0.9rem;
    resize: vertical;
    padding: 0.4rem 0.8rem;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.btn {
    padding: 0.5rem 1.25rem;
    font-weight: 500;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.btn i {
    font-size: 0.9rem;
}

.btn-primary {
    background-color: #3498db;
    border: none;
    box-shadow: 0 2px 4px rgba(52, 152, 219, 0.2);
}

.btn-primary:hover {
    background-color: #2980b9;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(52, 152, 219, 0.25);
}

.btn-secondary {
    background-color: #373a40;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn-secondary:hover {
    background-color: #2c3e50;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
}

.alert {
    border-radius: 8px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.alert-danger {
    background-color: rgba(231, 76, 60, 0.1);
    border-color: rgba(231, 76, 60, 0.2);
    color: #e74c3c;
}

.alert i {
    margin-right: 10px;
    font-size: 1.1rem;
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

    .form-section {
        padding: 20px;
        margin-bottom: 20px;
    }

    .form-actions {
        flex-direction: column;
        gap: 10px;
    }

    .btn {
        width: 100%;
        justify-content: center;
        padding: 0.875rem 1.5rem;
    }
}
</style> 