<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Please log in as admin";
    header('Location: ../login.php');
    exit();
}

// Check if exam_id is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No exam selected";
    header('Location: exams.php');
    exit();
}

$exam_id = $_GET['id'];

try {
    // Start transaction
    $pdo->beginTransaction();

    // First, delete all answers for questions in this exam
    $stmt = $pdo->prepare("
        DELETE a FROM answers a
        INNER JOIN questions q ON a.question_id = q.question_id
        WHERE q.exam_id = ?
    ");
    $stmt->execute([$exam_id]);

    // Then, delete all questions for this exam
    $stmt = $pdo->prepare("DELETE FROM questions WHERE exam_id = ?");
    $stmt->execute([$exam_id]);

    // Then, delete associated notifications
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE exam_id = ?");
    $stmt->execute([$exam_id]);

    // Finally, delete the exam itself
    $stmt = $pdo->prepare("DELETE FROM exams WHERE exam_id = ?");
    $stmt->execute([$exam_id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = "Exam deleted successfully";
    header('Location: exams.php');
    exit();

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    $_SESSION['error'] = "Error deleting exam: " . $e->getMessage();
    header('Location: exams.php');
    exit();
}
?>
