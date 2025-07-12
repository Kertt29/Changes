<?php
session_start();
require_once 'config/database.php';

// Only allow existing admins to create new admin accounts
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: /finalProject/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_key = $_POST['admin_key']; // Additional security measure

    // Validation
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists in students table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Email already registered as a student. Please use a different email for admin registration.";
    }
    
    // Check if email already exists in admin table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Email already registered as an admin";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // Verify admin key (you can change this to any value you want)
    if ($admin_key !== 'your_secure_admin_key') {
        $errors[] = "Invalid admin key";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            // Insert new admin into the database
            $stmt = $pdo->prepare("INSERT INTO admin (email, password) VALUES (?, ?)");
            $stmt->execute([$email, $hashed_password]);
            
            $_SESSION['success'] = "New admin account created successfully!";
            header('Location: /finalProject/admin/admin_dashboard.php');
            exit();
        } catch(PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin - ExaMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/finalProject/assets/css/styles.css">
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo">E</div>
            <h1 class="portal-title">ExaMatrix</h1>
        </div>
        
        <h2 class="portal-title">Create Admin Account</h2>
        <p class="signin-text">Register a new administrator</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                       required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="mb-3">
                <label for="admin_key" class="form-label">Admin Security Key</label>
                <input type="password" class="form-control" id="admin_key" name="admin_key" required>
            </div>

            <button type="submit" class="btn-sign-in">Create Admin Account</button>
        </form>

        <div class="text-center mt-3">
            <a href="/finalProject/admin/admin_dashboard.php" class="text-white">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 