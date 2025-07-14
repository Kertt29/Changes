<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in as student
if (!isset($_SESSION['student_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    $_SESSION['error'] = "Please log in as a student.";
    header('Location: ../login.php');
    exit();
}

$student_id = $_SESSION['student_id'];

// Fetch current student data
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        // Validate current password
        if (!empty($current_password)) {
            if (!password_verify($current_password, $student['password'])) {
                throw new Exception("Current password is incorrect");
            }
        }

        // Check if email is already taken by another student
        if ($email !== $student['email']) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ? AND student_id != ?");
            $stmt->execute([$email, $student_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Email is already registered");
            }
        }

        // Update profile
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }
            // Update with new password
            $stmt = $pdo->prepare("UPDATE students SET full_name = ?, email = ?, password = ? WHERE student_id = ?");
            $stmt->execute([
                $full_name,
                $email,
                password_hash($new_password, PASSWORD_DEFAULT),
                $student_id
            ]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("UPDATE students SET full_name = ?, email = ? WHERE student_id = ?");
            $stmt->execute([$full_name, $email, $student_id]);
        }

        // Update session data
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;

        $_SESSION['success'] = "Profile updated successfully!";
        header('Location: edit_profile.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - ExaMatrix</title>
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
                    <a href="edit_profile.php" class="nav-link active">
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
            <h1>Edit Profile</h1>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F d, Y'); ?>
                <span class="separator">|</span>
                <i class="far fa-clock"></i>
                <?php echo date('h:i A'); ?>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-section">
            <div class="profile-form">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($student['email']); ?>" required>
                    </div>

                    <div class="password-section">
                        <h3>Change Password</h3>
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
    margin-bottom: 30px;
}

.profile-form {
    max-width: 600px;
    margin: 0 auto;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: white;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    background-color: #1e1f25;
    border: 1px solid #373a40;
    border-radius: 8px;
    color: white;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.password-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #373a40;
}

.password-section h3 {
    color: white;
    font-size: 18px;
    margin-bottom: 20px;
}

.form-actions {
    margin-top: 30px;
    display: flex;
    justify-content: flex-end;
}

.btn-save {
    display: inline-flex;
    align-items: center;
    padding: 10px 20px;
    background-color: #2ecc71;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-save:hover {
    background-color: #27ae60;
}

.btn-save i {
    margin-right: 8px;
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

    .dashboard-section {
        padding: 20px;
    }
}
</style> 