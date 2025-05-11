<?php
// Helper script to get a sample assignment for testing
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Get teacher ID
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

if (!$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Teacher ID is required']);
    exit();
}

// Get a sample assignment for this teacher
$query = "SELECT * FROM teachersubjects WHERE teacher_id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $assignment = $result->fetch_assoc();
    echo json_encode(['success' => true, 'assignment' => $assignment]);
} else {
    echo json_encode(['success' => false, 'message' => 'No assignments found for this teacher']);
}

$stmt->close();
$conn->close();
?>
