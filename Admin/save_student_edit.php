<?php
// Turn off error display but keep logging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

// Set JSON header
header('Content-Type: application/json');

session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
try {
    $conn = new mysqli('localhost', 'root', '', 'result_management');
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data with validation
        $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $roll_number = isset($_POST['roll_number']) ? trim($_POST['roll_number']) : '';
        $registration_number = isset($_POST['registration_number']) ? trim($_POST['registration_number']) : '';
        $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
        $batch_year = isset($_POST['batch_year']) ? trim($_POST['batch_year']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $parent_name = isset($_POST['parent_name']) ? trim($_POST['parent_name']) : '';
        $parent_phone = isset($_POST['parent_phone']) ? trim($_POST['parent_phone']) : '';
        $parent_email = isset($_POST['parent_email']) ? trim($_POST['parent_email']) : '';

        // Validate required fields
        if (empty($student_id) || empty($user_id) || empty($email) || empty($roll_number) || empty($class_id) || empty($batch_year)) {
            throw new Exception('Please fill all required fields');
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Check if email already exists for a different user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception('Email already exists for another user');
        }
        $stmt->close();

        // Check if roll number already exists in the same class for a different student
        $stmt = $conn->prepare("SELECT s.student_id FROM students s WHERE s.roll_number = ? AND s.class_id = ? AND s.student_id != ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sis", $roll_number, $class_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception('Roll number already exists for another student in this class');
        }
        $stmt->close();

        // Begin transaction
        $conn->begin_transaction();

        // Update users table
        $stmt = $conn->prepare("UPDATE users SET email = ?, status = ?, phone = ?, address = ? WHERE user_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssi", $email, $status, $phone, $address, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();

        // Check if the students table has a gender column
        $result = $conn->query("SHOW COLUMNS FROM students LIKE 'gender'");
        $has_gender_column = $result->num_rows > 0;

        // Check if the students table has a date_of_birth column
        $result = $conn->query("SHOW COLUMNS FROM students LIKE 'date_of_birth'");
        $has_dob_column = $result->num_rows > 0;

        // Build the SQL query for updating the students table
        $sql = "UPDATE students SET roll_number = ?, class_id = ?, batch_year = ?";
        $types = "sis"; // string, int, string
        $params = [$roll_number, $class_id, $batch_year];

        // Add registration_number if provided
        if (!empty($registration_number)) {
            $sql .= ", registration_number = ?";
            $types .= "s";
            $params[] = $registration_number;
        }

        // Add gender if the column exists
        if ($has_gender_column && !empty($gender)) {
            $sql .= ", gender = ?";
            $types .= "s";
            $params[] = $gender;
        }

        // Add date_of_birth if the column exists
        if ($has_dob_column && !empty($date_of_birth)) {
            $sql .= ", date_of_birth = ?";
            $types .= "s";
            $params[] = $date_of_birth;
        }

        // Add parent information if provided
        if (!empty($parent_name)) {
            $sql .= ", parent_name = ?";
            $types .= "s";
            $params[] = $parent_name;
        }

        if (!empty($parent_phone)) {
            $sql .= ", parent_phone = ?";
            $types .= "s";
            $params[] = $parent_phone;
        }

        if (!empty($parent_email)) {
            $sql .= ", parent_email = ?";
            $types .= "s";
            $params[] = $parent_email;
        }

        $sql .= " WHERE student_id = ?";
        $types .= "s";
        $params[] = $student_id;

        // Update students table
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Dynamically bind parameters
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();

        // Update password if provided
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                throw new Exception('New password and confirm password do not match');
            }
            
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("si", $hashed_password, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
        }

        // Commit transaction
        $conn->commit();

        // Clear any buffered output
        ob_clean();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Student updated successfully'
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->connect_errno == 0) {
            $conn->rollback();
        }
        
        // Clear any buffered output
        ob_clean();
        
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    // Clear any buffered output
    ob_clean();
    
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
exit();
?>
