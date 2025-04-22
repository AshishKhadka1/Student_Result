<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Teacher Dashboard Debug</h1>";

// Check session variables
echo "<h2>Session Variables</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo "<p style='color:red'>Error: Not logged in as a teacher</p>";
    echo "<p>Please <a href='../login.php'>login</a> as a teacher to continue.</p>";
    exit();
}

// Include database connection
require_once __DIR__ . '/includes/config.php';

// Test database connection
echo "<h2>Database Connection</h2>";
if ($conn->connect_error) {
    echo "<p style='color:red'>Connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color:green'>Database connection successful</p>";
}

// Check if teacher exists in users table
$teacher_id = $_SESSION['user_id'];
echo "<h2>Teacher User Check</h2>";
echo "Looking for teacher with user_id: " . $teacher_id . "<br>";

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'teacher'");
if (!$stmt) {
    echo "<p style='color:red'>Query preparation failed: " . $conn->error . "</p>";
} else {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        echo "<p style='color:red'>Teacher not found in users table</p>";
    } else {
        echo "<p style='color:green'>Teacher found in users table</p>";
        echo "<pre>";
        print_r($user);
        echo "</pre>";
    }
}

// Check if teacher exists in teachers table
echo "<h2>Teacher Record Check</h2>";
$stmt = $conn->prepare("SELECT * FROM teachers WHERE user_id = ?");
if (!$stmt) {
    echo "<p style='color:red'>Query preparation failed: " . $conn->error . "</p>";
} else {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    $stmt->close();
    
    if (!$teacher) {
        echo "<p style='color:red'>Teacher not found in teachers table</p>";
        echo "<p>This could be causing the dashboard error. You may need to add this teacher to the teachers table.</p>";
        
        // Show SQL to fix
        echo "<h3>SQL to fix this issue:</h3>";
        echo "<pre>";
        echo "INSERT INTO teachers (user_id, department, designation, employee_id) VALUES (" . $teacher_id . ", 'General', 'Teacher', 'T" . str_pad($teacher_id, 3, '0', STR_PAD_LEFT) . "');";
        echo "</pre>";
    } else {
        echo "<p style='color:green'>Teacher found in teachers table</p>";
        echo "<pre>";
        print_r($teacher);
        echo "</pre>";
    }
}

// Check assigned classes
echo "<h2>Assigned Classes Check</h2>";
$stmt = $conn->prepare("SELECT DISTINCT c.*, 
                      (SELECT COUNT(*) FROM students s WHERE s.class_id = c.class_id) as student_count
                      FROM classes c 
                      JOIN teachersubjects ts ON c.class_id = ts.class_id 
                      JOIN teachers t ON ts.teacher_id = t.teacher_id 
                      WHERE t.user_id = ?");
if (!$stmt) {
    echo "<p style='color:red'>Query preparation failed: " . $conn->error . "</p>";
} else {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt->close();
    
    if (empty($classes)) {
        echo "<p style='color:orange'>No classes assigned to this teacher</p>";
    } else {
        echo "<p style='color:green'>Found " . count($classes) . " assigned classes</p>";
        echo "<pre>";
        print_r($classes);
        echo "</pre>";
    }
}

// Check assigned subjects
echo "<h2>Assigned Subjects Check</h2>";
$stmt = $conn->prepare("SELECT ts.*, s.subject_name, s.subject_code, c.class_name, c.section
                      FROM teachersubjects ts 
                      JOIN subjects s ON ts.subject_id = s.subject_id 
                      JOIN classes c ON ts.class_id = c.class_id 
                      JOIN teachers t ON ts.teacher_id = t.teacher_id 
                      WHERE t.user_id = ?");
if (!$stmt) {
    echo "<p style='color:red'>Query preparation failed: " . $conn->error . "</p>";
} else {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
    
    if (empty($subjects)) {
        echo "<p style='color:orange'>No subjects assigned to this teacher</p>";
    } else {
        echo "<p style='color:green'>Found " . count($subjects) . " assigned subjects</p>";
        echo "<pre>";
        print_r($subjects);
        echo "</pre>";
    }
}

// Check database tables
echo "<h2>Database Tables Check</h2>";
$tables = ['users', 'teachers', 'classes', 'subjects', 'teachersubjects', 'students', 'exams', 'results'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p style='color:green'>Table '$table' exists</p>";
        
        // Check row count
        $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
        $count = $count_result->fetch_assoc()['count'];
        echo " - Contains $count rows<br>";
    } else {
        echo "<p style='color:red'>Table '$table' does not exist</p>";
    }
}

// Close connection
$conn->close();
?>
