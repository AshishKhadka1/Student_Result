<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = "Invalid request.";
    $_SESSION['message_type'] = "red";
    header("Location: manage_results.php");
    exit();
}

// Get form data
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
        $_SESSION['message'] = "Result updated successfully.";
        $_SESSION['message_type'] = "green";
    } else {
        $_SESSION['message'] = "Error updating result: " . $conn->error;
        $_SESSION['message_type'] = "red";
    }
} else {
    // Create a manual upload record if needed
    $uploadId = null;
    $checkUpload = $conn->query("SELECT id FROM ResultUploads WHERE file_name = 'Manual Entry' AND upload_date >= CURDATE() ORDER BY id DESC LIMIT 1");
    
    if ($checkUpload->num_rows > 0) {
        $uploadId = $checkUpload->fetch_assoc()['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO ResultUploads (file_name, description, status, uploaded_by, upload_date) VALUES ('Manual Entry', 'Manually entered results', 'Published', ?, NOW())");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $uploadId = $conn->insert_id;
    }
    
    // Insert new result
    $stmt = $conn->prepare("INSERT INTO Results (student_id, subject_id, theory_marks, practical_marks, credit_hours, grade, gpa, upload_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssdddsdi", $studentId, $subjectId, $theoryMarks, $practicalMarks, $creditHours, $grade, $gpa, $uploadId);
    
    if ($stmt->execute()) {
        // Update upload record with student count
        $conn->query("UPDATE ResultUploads SET student_count = student_count + 1 WHERE id = $uploadId");
        
        $_SESSION['message'] = "Result added successfully.";
        $_SESSION['message_type'] = "green";
    } else {
        $_SESSION['message'] = "Error adding result: " . $conn->error;
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
?>

