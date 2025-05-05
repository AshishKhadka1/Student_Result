<?php
// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db_connetc.php';
// Remove the redundant session_start() since it's likely already started in config.php

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: ../login.php");
    exit();
}

// Database connection is already established in db_connetc.php as $conn

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = "Invalid request.";
    $_SESSION['message_type'] = "red";
    header("Location: manage_results.php");
    exit();
}

// Get form data - using the original form field names
$studentId = $_POST['student_id'];
$subjectId = $_POST['subject_id'];
$theoryMarks = $_POST['theory_marks'];
$practicalMarks = isset($_POST['practical_marks']) && !empty($_POST['practical_marks']) ? $_POST['practical_marks'] : null;
$creditHours = $_POST['credit_hours'];

// Validate data
if (empty($studentId) || empty($subjectId) || !is_numeric($theoryMarks) || !is_numeric($creditHours)) {
    $_SESSION['message'] = "Please fill all required fields with valid data.";
    $_SESSION['message_type'] = "red";
    header("Location: manage_results.php");
    exit();
}

// Check if student exists
$stmt = $conn->prepare("SELECT student_id FROM Students WHERE student_id = ?");
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Student ID not found.";
    $_SESSION['message_type'] = "red";
    header("Location: manage_results.php");
    exit();
}

// Check if subject exists
$stmt = $conn->prepare("SELECT subject_id FROM Subjects WHERE subject_id = ?");
$stmt->bind_param("s", $subjectId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Subject ID not found.";
    $_SESSION['message_type'] = "red";
    header("Location: manage_results.php");
    exit();
}

// Calculate grade and GPA
$grade = calculateGrade($theoryMarks, $practicalMarks);
$gpa = calculateGPA($grade);

// Check if result already exists
$stmt = $conn->prepare("SELECT result_id FROM Results WHERE student_id = ? AND subject_id = ?");
$stmt->bind_param("ss", $studentId, $subjectId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing result
    $stmt = $conn->prepare("UPDATE Results SET theory_marks = ?, practical_marks = ?, credit_hours = ?, grade = ?, gpa = ?, updated_at = NOW() WHERE student_id = ? AND subject_id = ?");
    $stmt->bind_param("dddsiss", $theoryMarks, $practicalMarks, $creditHours, $grade, $gpa, $studentId, $subjectId);
    
    if ($stmt->execute()) {
        // Log the action
        logAction($conn, $_SESSION['user_id'], "UPDATE_RESULT", "Updated result for student ID: $studentId, subject ID: $subjectId");
        
        $_SESSION['message'] = "Result updated successfully.";
        $_SESSION['message_type'] = "green";
    } else {
        $_SESSION['message'] = "Error updating result: " . $conn->error;
        $_SESSION['message_type'] = "red";
    }
} else {
    // Create a manual upload record if needed
    $uploadId = null;
    
    // First check if we have a manual entry upload for today
    $checkUpload = $conn->query("SELECT id FROM ResultUploads WHERE file_name = 'Manual Entry' AND upload_date >= CURDATE() AND uploaded_by = {$_SESSION['user_id']} ORDER BY id DESC LIMIT 1");
    
    if ($checkUpload->num_rows > 0) {
        $uploadId = $checkUpload->fetch_assoc()['id'];
    } else {
        // Create a new manual entry upload record
        $stmt = $conn->prepare("INSERT INTO ResultUploads (file_name, description, status, uploaded_by, upload_date, is_manual_entry) VALUES ('Manual Entry', 'Manually entered results', 'Published', ?, NOW(), 1)");
        $stmt->bind_param("i", $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            $_SESSION['message'] = "Error creating upload record: " . $conn->error;
            $_SESSION['message_type'] = "red";
            header("Location: manage_results.php");
            exit();
        }
        
        $uploadId = $conn->insert_id;
    }
    
    // Insert new result with the upload_id
    $stmt = $conn->prepare("INSERT INTO Results (student_id, subject_id, theory_marks, practical_marks, credit_hours, grade, gpa, upload_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssdddsdi", $studentId, $subjectId, $theoryMarks, $practicalMarks, $creditHours, $grade, $gpa, $uploadId);
    
    if ($stmt->execute()) {
        // Update upload record with student count
        $conn->query("UPDATE ResultUploads SET student_count = student_count + 1 WHERE id = $uploadId");
        
        // Log the action
        logAction($conn, $_SESSION['user_id'], "ADD_RESULT", "Added new result for student ID: $studentId, subject ID: $subjectId");
        
        $_SESSION['message'] = "Result added successfully.";
        $_SESSION['message_type'] = "green";
    } else {
        $_SESSION['message'] = "Error adding result: " . $conn->error . " (SQL State: " . $stmt->sqlstate . ")";
        $_SESSION['message_type'] = "red";
    }
}

header("Location: manage_results.php");
exit();

// Helper functions
function calculateGrade($theoryMarks, $practicalMarks = null) {
    // Calculate average marks
    $totalMarks = $theoryMarks;
    $divisor = 1;
    
    if (!empty($practicalMarks)) {
        $totalMarks += $practicalMarks;
        $divisor = 2;
    }
    
    $averageMarks = $totalMarks / $divisor;
    
    // Determine grade based on average marks
    if ($averageMarks >= 90) return 'A+';
    if ($averageMarks >= 80) return 'A';
    if ($averageMarks >= 70) return 'B+';
    if ($averageMarks >= 60) return 'B';
    if ($averageMarks >= 50) return 'C+';
    if ($averageMarks >= 40) return 'C';
    if ($averageMarks >= 30) return 'D';
    return 'F';
}

function calculateGPA($grade) {
    switch ($grade) {
        case 'A+': return 4.0;
        case 'A': return 3.6;
        case 'B+': return 3.2;
        case 'B': return 2.8;
        case 'C+': return 2.4;
        case 'C': return 2.0;
        case 'D': return 1.6;
        default: return 0.0;
    }
}

// Function to log actions
function logAction($conn, $user_id, $action, $details) {
    try {
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Create activity_logs table if it doesn't exist
        $conn->query("
            CREATE TABLE IF NOT EXISTS `activity_logs` (
              `log_id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `action` varchar(255) NOT NULL,
              `details` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`log_id`),
              KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Try again
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}
?>
