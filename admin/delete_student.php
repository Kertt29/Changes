<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if student ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid student ID";
    header('Location: students.php');
    exit();
}

$student_id = $_GET['id'];

try {
    // Start transaction
    $pdo->beginTransaction();

    // First, delete related records in other tables
    // Delete exam attempts
    $stmt = $pdo->prepare("DELETE FROM exam_attempts WHERE student_id = ?");
    $stmt->execute([$student_id]);

    // Delete enrollments
    $stmt = $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?");
    $stmt->execute([$student_id]);

    // Finally, delete the student
    $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = "Student and all related data have been deleted successfully.";
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error'] = "Error deleting student: " . $e->getMessage();
}

// Redirect back to students page
header('Location: students.php');
exit(); 