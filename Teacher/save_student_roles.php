<?php
session_start();
require_once '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: teacher_dashboard.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Validate inputs
if ($student_id <= 0 || empty($notes)) {
    $_SESSION['error'] = "Invalid input. Please provide valid notes.";
    header("Location: student_profile.php?student_id=" . $student_id);
    exit();
}

// Get teacher information
$stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Teacher record not found.";
    header("Location: teacher_dashboard.php");
    exit();
}

$teacher = $result->fetch_assoc();
$stmt->close();

// Check if student exists and get their class
$stmt = $conn->prepare("SELECT class_id FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Student not found.";
    header("Location: teacher_dashboard.php");
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

// Check if teacher is assigned to this student's class
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM teachersubjects 
                       WHERE teacher_id = ? AND class_id = ?");
$stmt->bind_param("ii", $teacher['teacher_id'], $student['class_id']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$is_class_teacher = ($row['count'] > 0);
$stmt->close();

if (!$is_class_teacher) {
    $_SESSION['error'] = "You are not assigned to this student's class.";
    header("Location: teacher_dashboard.php");
    exit();
}

// Check if student_notes table exists, create if not
$conn->query("
    CREATE TABLE IF NOT EXISTS student_notes (
        note_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        teacher_id INT NOT NULL,
        note_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(student_id),
        FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id)
    )
");

// Save the note
$stmt = $conn->prepare("INSERT INTO student_notes (student_id, teacher_id, note_text) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $student_id, $teacher['teacher_id'], $notes);

if ($stmt->execute()) {
    $_SESSION['success'] = "Notes saved successfully.";
} else {
    $_SESSION['error'] = "Failed to save notes: " . $conn->error;
}
$stmt->close();

// Redirect back to student profile
header("Location: student_profile.php?student_id=" . $student_id);
exit();
?>
