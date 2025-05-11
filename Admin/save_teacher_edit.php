<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if form data is submitted
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
$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if (!$teacher_id || !$user_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Teacher ID and User ID are required']);
    exit();
}

// Begin transaction
$conn->begin_transaction();

try {
    // Update user information
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? null;
    $status = $_POST['status'];
    
    $user_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE user_id = ?");
    $user_stmt->bind_param("ssssi", $full_name, $email, $phone, $status, $user_id);
    $user_stmt->execute();
    $user_stmt->close();
    
    // Update teacher information
    $employee_id = $_POST['employee_id'];
    $department = $_POST['department'] === 'other' ? $_POST['other_department'] : $_POST['department'];
    $qualification = $_POST['qualification'] ?? null;
    $joining_date = !empty($_POST['joining_date']) ? $_POST['joining_date'] : null;
    $experience = !empty($_POST['experience']) ? intval($_POST['experience']) : null;
    $specialization = $_POST['specialization'] ?? null;
    $address = $_POST['address'] ?? null;
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    
    // Check if gender column exists
    $column_check = $conn->query("SHOW COLUMNS FROM teachers LIKE 'gender'");
    $gender_exists = $column_check && $column_check->num_rows > 0;
    
    if ($gender_exists && isset($_POST['gender'])) {
        $gender = $_POST['gender'];
        $teacher_stmt = $conn->prepare("UPDATE teachers SET 
            employee_id = ?, 
            department = ?, 
            qualification = ?, 
            joining_date = ?, 
            experience = ?, 
            specialization = ?, 
            address = ?, 
            date_of_birth = ?,
            gender = ?
            WHERE teacher_id = ?");
        $teacher_stmt->bind_param("sssssssssi", $employee_id, $department, $qualification, $joining_date, $experience, $specialization, $address, $date_of_birth, $gender, $teacher_id);
    } else {
        $teacher_stmt = $conn->prepare("UPDATE teachers SET 
            employee_id = ?, 
            department = ?, 
            qualification = ?, 
            joining_date = ?, 
            experience = ?, 
            specialization = ?, 
            address = ?, 
            date_of_birth = ?
            WHERE teacher_id = ?");
        $teacher_stmt->bind_param("ssssssssi", $employee_id, $department, $qualification, $joining_date, $experience, $specialization, $address, $date_of_birth, $teacher_id);
    }
    
    $teacher_stmt->execute();
    $teacher_stmt->close();
    
    // Log the activity
    $activity_type = 'teacher_update';
    $description = "Updated teacher information for $full_name";
    $admin_id = $_SESSION['user_id'];
    $current_time = date('Y-m-d H:i:s');
    
    // Check if activities table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'activities'");
    if ($table_check && $table_check->num_rows > 0) {
        $log_stmt = $conn->prepare("INSERT INTO activities (user_id, activity_type, description, created_by, created_at) VALUES (?, ?, ?, ?, ?)");
        $log_stmt->bind_param("issss", $user_id, $activity_type, $description, $admin_id, $current_time);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Teacher information updated successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error updating teacher: ' . $e->getMessage()]);
}

$conn->close();
?>
