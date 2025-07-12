<?php
session_start();
require_once '../config/database.php';

// Check if the course ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid course ID";
    header('Location: courses.php');
    exit();
}

$course_id = intval($_GET['id']);

try {
    // Start transaction for data consistency
    $pdo->beginTransaction();
    
    // Check if the course exists
    $stmt = $pdo->prepare("SELECT course_id FROM courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Course not found");
    }
    
    // 1. Delete answers associated with questions for this course
    $stmt = $pdo->prepare("DELETE FROM answers WHERE question_id IN 
        (SELECT question_id FROM questions WHERE exam_id IN 
        (SELECT exam_id FROM exams WHERE course_id = ?))");
    $stmt->execute([$course_id]);
    
    // 2. Delete questions associated with this course
    $stmt = $pdo->prepare("DELETE FROM questions WHERE exam_id IN 
        (SELECT exam_id FROM exams WHERE course_id = ?)");
    $stmt->execute([$course_id]);
    
    // 3. Delete from exams associated with this course
    $stmt = $pdo->prepare("DELETE FROM exams WHERE course_id = ?");
    $stmt->execute([$course_id]);
    
    // 4. Finally delete the course itself
    $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    
    // Commit the transaction
    $pdo->commit();
    
    $_SESSION['success'] = "Course deleted successfully";
} catch (Exception $e) {
    // Rollback the transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the specific error for debugging
    error_log("Course deletion error: " . $e->getMessage());
    $_SESSION['error'] = "Error deleting course: " . $e->getMessage();
}

// Redirect back to courses page
header('Location: courses.php');
exit();
?>
