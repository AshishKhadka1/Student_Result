<?php
session_start();
include '../includes/config.php';
include '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

// Validate parameters
if (!$result_id || !$student_id || !$subject_id || !$class_id || !$section_id) {
    $_SESSION['error'] = "Invalid parameters provided.";
    header("Location: teacher_dashboard.php");
    exit();
}

// Verify that the teacher is assigned to this subject/class/section
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM TeacherSubjects 
    WHERE teacher_id = ? AND subject_id = ? AND class_id = ? AND section_id = ?
");
$stmt->bind_param("iiii", $teacher_id, $subject_id, $class_id, $section_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Not authorized to view this class
    $_SESSION['error'] = "You are not authorized to delete results for this class.";
    header("Location: teacher_dashboard.php");
    exit();
}

// Verify that the result exists and belongs to this student and subject
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM Results 
    WHERE id = ? AND student_id = ? AND subject_id = ?
");
$stmt->bind_param("iii", $result_id, $student_id, $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $_SESSION['error'] = "Result not found.";
    header("Location: student_results.php?student_id=" . $student_id . "&subject_id=" . $subject_id . "&class_id=" . $class_id . "&section_id=" . $section_id);
    exit();
}

// Delete the result
$stmt = $conn->prepare("DELETE FROM Results WHERE id = ?");
$stmt->bind_param("i", $result_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Result deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete result: " . $conn->error;
}

// Redirect back to student results page
header("Location: student_results.php?student_id=" . $student_id . "&subject_id=" . $subject_id . "&class_id=" . $class_id . "&section_id=" . $section_id);
exit();
?>
