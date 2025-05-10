<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Get form data
$student_id = $_POST['student_id'] ?? '';
$user_id = $_POST['user_id'] ?? '';
$full_name = $_POST['full_name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$gender = $_POST['gender'] ?? '';
$date_of_birth = $_POST['date_of_birth'] ?? '';
$address = $_POST['address'] ?? '';
$roll_number = $_POST['roll_number'] ?? '';
$class_id = $_POST['class_id'] ?? '';
$batch_year = $_POST['batch_year'] ?? '';
$status = $_POST['status'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate required fields
if (empty($student_id) || empty($user_id) || empty($full_name) || empty($email) || empty($roll_number) || empty($class_id) || empty($batch_year) || empty($status)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Check if email is already in use by another user
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$stmt->bind_param("si", $email, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Email is already in use by another user']);
    exit();
}

// Check if roll number is already in use by another student
$stmt = $conn->prepare("SELECT student_id FROM students WHERE roll_number = ? AND student_id != ?");
$stmt->bind_param("ss", $roll_number, $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Roll number is already in use by another student']);
    exit();
}

// Validate password if provided
if (!empty($new_password)) {
    if ($new_password !== $confirm_password) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit();
    }
    
    if (strlen($new_password) < 6) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        exit();
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    // Update user information
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, status = ? WHERE user_id = ?");
    $stmt->bind_param("sssi", $full_name, $email, $status, $user_id);
    $stmt->execute();
    
    // Update student information
    $stmt = $conn->prepare("
        UPDATE students 
        SET roll_number = ?, class_id = ?, batch_year = ?, gender = ?, 
            date_of_birth = ?, phone = ?, address = ?, updated_at = NOW() 
        WHERE student_id = ?
    ");
    $stmt->bind_param("sissssss", $roll_number, $class_id, $batch_year, $gender, $date_of_birth, $phone, $address, $student_id);
    $stmt->execute();
    
    // Update password if provided
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        $stmt->execute();
    }
    
    // Log the activity
    $admin_id = $_SESSION['user_id'];
    $action = "EDIT_STUDENT";
    $details = "Updated student information for student ID: $student_id";
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $admin_id, $action, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Student information updated successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error updating student information: ' . $e->getMessage()]);
}

$conn->close();
?>
