<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    $_SESSION['error'] = "Connection failed: " . $conn->connect_error;
    header("Location: ../register.php");
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get common form data with proper sanitization
    $full_name = trim($conn->real_escape_string($_POST['full_name'] ?? ''));
    $username = trim($conn->real_escape_string($_POST['username'] ?? ''));
    $email = trim($conn->real_escape_string($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $conn->real_escape_string($_POST['role'] ?? '');
    
    // Validate required fields
    $required_fields = [
        'Full Name' => $full_name,
        'Username' => $username,
        'Email' => $email,
        'Password' => $password,
        'Confirm Password' => $confirm_password,
        'Role' => $role
    ];
    
    foreach ($required_fields as $field => $value) {
        if (empty($value)) {
            $_SESSION['error'] = "$field is required";
            header("Location: ../register.php");
            exit();
        }
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header("Location: ../register.php");
        exit();
    }
    
    // Validate password length and complexity
    if (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long";
        header("Location: ../register.php");
        exit();
    }
    
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $_SESSION['error'] = "Password must contain at least one uppercase letter, one lowercase letter, and one number";
        header("Location: ../register.php");
        exit();
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match";
        header("Location: ../register.php");
        exit();
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT user_id FROM Users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Username already exists. Please choose a different username.";
        header("Location: ../register.php");
        exit();
    }
    $stmt->close();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Email already exists. Please use a different email or try to recover your account.";
        header("Location: ../register.php");
        exit();
    }
    $stmt->close();
    
    // Validate role-specific fields and requirements
    if ($role === 'admin') {
        // Check admin registration code (should be stored securely in config)
        $admin_code = $_POST['admin_code'] ?? '';
        $expected_admin_code = "admin123"; // Replace with secure storage
        
        if ($admin_code !== $expected_admin_code) {
            $_SESSION['error'] = "Invalid admin registration code";
            header("Location: ../register.php");
            exit();
        }
        
        $status = 'inactive'; // Admin accounts need approval
    } else {
        $status = 'active';
    }
    
    // Hash the password with cost factor
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert into Users table
        $stmt = $conn->prepare("INSERT INTO Users (username, password, email, full_name, role, status, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssss", $username, $hashed_password, $email, $full_name, $role, $status);
        if (!$stmt->execute()) {
            throw new Exception("Failed to create user account: " . $stmt->error);
        }
        $user_id = $conn->insert_id;
        $stmt->close();
        
        // Handle role-specific data
        if ($role === 'student') {
            $roll_number = $conn->real_escape_string($_POST['roll_number'] ?? '');
            $class_id = intval($_POST['class_id'] ?? 0);
            $batch_year = intval($_POST['batch_year'] ?? date('Y'));
            
            if (empty($roll_number)) {
                throw new Exception("Roll number is required for student registration");
            }
            
            // Check if roll number exists
            $stmt = $conn->prepare("SELECT student_id FROM Students WHERE roll_number = ?");
            $stmt->bind_param("s", $roll_number);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                throw new Exception("Roll number already exists");
            }
            $stmt->close();
            
            // Generate unique student ID
            $student_id = 'STU-' . $batch_year . '-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
            $registration_number = 'REG-' . $batch_year . '-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
            
            // Insert into Students table
            $stmt = $conn->prepare("INSERT INTO Students 
                                  (student_id, user_id, roll_number, registration_number, class_id, batch_year, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sisssi", $student_id, $user_id, $roll_number, $registration_number, $class_id, $batch_year);
            if (!$stmt->execute()) {
                throw new Exception("Failed to create student record: " . $stmt->error);
            }
            $stmt->close();
            
        } elseif ($role === 'teacher') {
            $employee_id = $conn->real_escape_string($_POST['employee_id'] ?? '');
            $department = $conn->real_escape_string($_POST['department'] ?? '');
            $qualification = $conn->real_escape_string($_POST['qualification'] ?? '');
            
            if (empty($employee_id)) {
                throw new Exception("Employee ID is required for teacher registration");
            }
            
            // Check if employee ID exists
            $stmt = $conn->prepare("SELECT teacher_id FROM Teachers WHERE employee_id = ?");
            $stmt->bind_param("s", $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                throw new Exception("Employee ID already exists");
            }
            $stmt->close();
            
            // Insert into Teachers table
            $stmt = $conn->prepare("INSERT INTO Teachers 
                                   (user_id, employee_id, department, qualification, joining_date, created_at) 
                                   VALUES (?, ?, ?, ?, CURDATE(), NOW())");
            $stmt->bind_param("isss", $user_id, $employee_id, $department, $qualification);
            if (!$stmt->execute()) {
                throw new Exception("Failed to create teacher record: " . $stmt->error);
            }
            $stmt->close();
        }
        
        // Create welcome notification
        $notification_title = "Account Created";
        $notification_message = "Welcome to the Result Management System! Your account has been successfully created.";
        
        $stmt = $conn->prepare("INSERT INTO Notifications (user_id, title, message, type, created_at) 
                               VALUES (?, ?, ?, 'success', NOW())");
        $stmt->bind_param("iss", $user_id, $notification_title, $notification_message);
        $stmt->execute();
        $stmt->close();
        
        // If admin registration, notify existing admins
        if ($role === 'admin') {
            $admin_notification_title = "New Admin Registration";
            $admin_notification_message = "A new admin account ({$username}) has registered and is pending approval.";
            
            $stmt = $conn->prepare("SELECT user_id FROM Users WHERE role = 'admin' AND status = 'active' AND user_id != ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $admin_result = $stmt->get_result();
            
            while ($admin = $admin_result->fetch_assoc()) {
                $notify_stmt = $conn->prepare("INSERT INTO Notifications (user_id, title, message, type, created_at) 
                                              VALUES (?, ?, ?, 'warning', NOW())");
                $notify_stmt->bind_param("iss", $admin['user_id'], $admin_notification_title, $admin_notification_message);
                $notify_stmt->execute();
                $notify_stmt->close();
            }
            $stmt->close();
        }
        
        // Log the activity
        $action = "User Registration";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (user_id, action, entity_type, entity_id, ip_address, user_agent, created_at) 
                              VALUES (?, ?, 'Users', ?, ?, ?, NOW())");
        $stmt->bind_param("isiss", $user_id, $action, $user_id, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['success'] = $role === 'admin' 
            ? "Registration successful! Your admin account is pending approval." 
            : "Registration successful! You can now log in.";
        
        header("Location: ../login.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Registration failed: " . $e->getMessage();
        header("Location: ../register.php");
        exit();
    }
} else {
    // If direct access or not POST
    $_SESSION['error'] = "Invalid request method";
    header("Location: ../register.php");
    exit();
}

$conn->close();
?>