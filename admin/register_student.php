<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Register Student';

// Function to generate a random password
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);

    try {
        // Validate input
        if (empty($full_name) || empty($email)) {
            throw new Exception("All fields are required");
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Email already registered");
        }

        // Only allow Gmail accounts
        if (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
            throw new Exception("Invalid email address.");
        }
        $auto_password = generatePassword(); // Generate random password
        $hashed_password = password_hash($auto_password, PASSWORD_DEFAULT);

        // Insert new student with auto-approval
        $sql = "INSERT INTO students (full_name, email, password, is_approved) VALUES (?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $full_name,
            $email,
            $hashed_password
        ]);

        $registration_success = true;
        $registered_name = $full_name;
        $registered_email = $email;
        $registered_password = $auto_password;
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
                    <a href="register_student.php" class="nav-link active">
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
            <h1>Register New Student</h1>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F d, Y'); ?>
                <span class="separator">|</span>
                <i class="far fa-clock"></i>
                <span id="live-time"></span>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($registration_success) && $registration_success): ?>
            <div class="dashboard-section">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Student registered successfully!
                </div>
                <div class="generated-password">
                    <strong>Student Details:</strong>
                    <ul>
                        <li><b>Name:</b> <?php echo htmlspecialchars($registered_name); ?></li>
                        <li><b>Email:</b> <?php echo htmlspecialchars($registered_email); ?></li>
                        <li><b>Generated Password:</b> <input type="text" id="generatedPassword" value="<?php echo htmlspecialchars($registered_password); ?>" readonly>
                            <button type="button" class="btn-copy" onclick="copyPassword()"><i class="fas fa-copy"></i></button>
                            <button type="button" class="btn-toggle" onclick="togglePassword()"><i class="fas fa-eye"></i></button>
                        </li>
                    </ul>
                </div>
                <div class="form-actions" style="margin-top: 20px;">
                    <a href="register_student.php" class="btn-submit"><i class="fas fa-user-plus"></i> Register Another Student</a>
                    <a href="admin_dashboard.php" class="btn-cancel"><i class="fas fa-home"></i> Go to Dashboard</a>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard-section">
                <form method="POST" action="" class="register-form">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-user-plus"></i> Register Student
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyPassword() {
            const passwordInput = document.getElementById('generatedPassword');
            passwordInput.select();
            document.execCommand('copy');
            alert('Password copied to clipboard!');
        }

        function togglePassword() {
            const passwordInput = document.getElementById('generatedPassword');
            const toggleButton = document.querySelector('.btn-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.classList.remove('fa-eye');
                toggleButton.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleButton.classList.remove('fa-eye-slash');
                toggleButton.classList.add('fa-eye');
            }
        }

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
    padding: 30px;
    margin-bottom: 30px;
}

.register-form {
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
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 12px;
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

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
}

.btn-cancel, .btn-submit {
    display: inline-flex;
    align-items: center;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-cancel {
    background-color: #373a40;
    color: white;
}

.btn-cancel:hover {
    background-color: #2c3e50;
}

.btn-submit {
    background-color: #2ecc71;
    color: white;
}

.btn-submit:hover {
    background-color: #27ae60;
}

.btn-cancel i, .btn-submit i {
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

.alert-danger {
    background-color: #e74c3c;
    color: white;
    border: none;
}

.alert-success {
    background-color: #2ecc71;
    color: white;
    border: none;
}

.generated-password {
    margin-top: 15px;
    padding: 10px;
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 6px;
}

.generated-password strong {
    display: block;
    margin-bottom: 8px;
}

.password-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

#generatedPassword {
    flex: 1;
    padding: 8px;
    background-color: #1e1f25;
    border: 1px solid #373a40;
    border-radius: 6px;
    color: white;
    font-family: monospace;
}

.btn-copy, .btn-toggle {
    padding: 8px;
    border: none;
    border-radius: 6px;
    background-color: #3498db;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-copy:hover, .btn-toggle:hover {
    background-color: #2980b9;
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

    .form-actions {
        flex-direction: column;
    }

    .btn-cancel, .btn-submit {
        width: 100%;
        justify-content: center;
    }
}
</style>
