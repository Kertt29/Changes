<?php
require_once 'config/database.php';

echo "<h2>Debug Reopened Exams</h2>";

// Check if table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'reopened_exams'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ reopened_exams table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ reopened_exams table does not exist</p>";
        exit();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking table: " . $e->getMessage() . "</p>";
    exit();
}

// Check table structure
try {
    $stmt = $pdo->query("DESCRIBE reopened_exams");
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error describing table: " . $e->getMessage() . "</p>";
}

// Check for reopened exams
try {
    $stmt = $pdo->query("
        SELECT re.*, e.exam_name, s.full_name as student_name
        FROM reopened_exams re
        JOIN exams e ON re.exam_id = e.exam_id
        JOIN students s ON re.student_id = s.student_id
        ORDER BY re.created_at DESC
    ");
    
    echo "<h3>Reopened Exams Data:</h3>";
    if ($stmt->rowCount() > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Exam</th><th>Student</th><th>Reopen Start</th><th>Reopen End</th><th>Status</th></tr>";
        while ($row = $stmt->fetch()) {
            $now = new DateTime();
            $start = new DateTime($row['reopen_start_date']);
            $end = new DateTime($row['reopen_end_date']);
            
            if ($now >= $start && $now <= $end) {
                $status = "Active";
                $color = "green";
            } elseif ($now < $start) {
                $status = "Not Started";
                $color = "orange";
            } else {
                $status = "Ended";
                $color = "red";
            }
            
            echo "<tr>";
            echo "<td>" . $row['exam_name'] . "</td>";
            echo "<td>" . $row['student_name'] . "</td>";
            echo "<td>" . $row['reopen_start_date'] . "</td>";
            echo "<td>" . $row['reopen_end_date'] . "</td>";
            echo "<td style='color: $color;'>" . $status . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No reopened exams found in database</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error querying reopened exams: " . $e->getMessage() . "</p>";
}

// Check current time
echo "<h3>Current Server Time:</h3>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Timezone: " . date_default_timezone_get() . "</p>";

// Test a specific student's exams
if (isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    echo "<h3>Exams for Student ID: $student_id</h3>";
    
    try {
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
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Exam</th><th>Course</th><th>Original Status</th><th>Reopen Start</th><th>Reopen End</th><th>Reopen Status</th><th>Attempt Status</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>" . $row['exam_name'] . "</td>";
            echo "<td>" . $row['course_name'] . "</td>";
            echo "<td>" . $row['exam_status'] . "</td>";
            echo "<td>" . ($row['reopen_start_date'] ?: 'N/A') . "</td>";
            echo "<td>" . ($row['reopen_end_date'] ?: 'N/A') . "</td>";
            echo "<td>" . ($row['reopen_status'] ?: 'N/A') . "</td>";
            echo "<td>" . ($row['attempt_status'] ?: 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error querying student exams: " . $e->getMessage() . "</p>";
    }
}

echo "<h3>Test Student Exams:</h3>";
echo "<p>Add ?student_id=1 to URL to test specific student</p>";
?> 