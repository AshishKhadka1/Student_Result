<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Check if class_id is provided
if (!isset($_GET['class_id']) || empty($_GET['class_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Class ID is required']);
    exit();
}

$class_id = $conn->real_escape_string($_GET['class_id']);

// Get students for the specified class
$query = "SELECT s.student_id, u.full_name, s.roll_number 
          FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          WHERE s.class_id = ? AND s.is_active = 1
          ORDER BY u.full_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = [
        'student_id' => $row['student_id'],
        'full_name' => $row['full_name'],
        'roll_number' => $row['roll_number']
    ];
}

// Return the students as JSON
header('Content-Type: application/json');
echo json_encode($students);
exit();
?>
