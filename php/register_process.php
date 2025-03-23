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
    // Get common form data
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Validate required fields
    if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $_SESSION['error'] = "All fields are required";
        header("Location: ../register.php");
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header("Location: ../register.php");
        exit();
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long";
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
        // Check admin registration code
        $admin_code = $_POST['admin_code'] ?? '';
        $expected_admin_code = "admin123"; // This should be stored securely, not hardcoded
        
        if ($admin_code !== $expected_admin_code) {
            $_SESSION['error'] = "Invalid admin registration code";
            header("Location: ../register.php");
            exit();
        }
        
        // Set admin status to 'inactive' until approved
        $status = 'inactive';
    } else {
        $status = 'active';
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert into Users table
        $stmt = $conn->prepare("INSERT INTO Users (username, password, email, full_name, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssss", $username, $hashed_password, $email, $full_name, $role, $status);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();
        
        // Handle role-specific data
        if ($role === 'student') {
            $roll_number = $_POST['roll_number'] ?? '';
            $class_id = $_POST['class_id'] ?? null;
            $batch_year = $_POST['batch_year'] ?? date('Y');
            
            // Generate a registration number
            $registration_number = 'REG' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
            
            // Insert into Students table
            $stmt = $conn->prepare("INSERT INTO Students (user_id, roll_number, registration_number, class_id, batch_year, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssi", $user_id, $roll_number, $registration_number, $class_id, $batch_year);
            $stmt->execute();
            $stmt->close();
        } elseif ($role === 'teacher') {
            $employee_id = $_POST['employee_id'] ?? '';
            $department = $_POST['department'] ?? '';
            $qualification = $_POST['qualification'] ?? '';
            
            // Insert into Teachers table
            $stmt = $conn->prepare("INSERT INTO Teachers (user_id, employee_id, department, qualification, joining_date, created_at) VALUES (?, ?, ?, ?, CURDATE(), NOW())");
            $stmt->bind_param("isss", $user_id, $employee_id, $department, $qualification);
            $stmt->execute();
            $stmt->close();
        }
        
        // Create a notification for new user registration
        $notification_title = "New Account Created";
        $notification_message = "Welcome to the Result Management System! Your account has been created successfully.";
        
        $stmt = $conn->prepare("INSERT INTO Notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'success', NOW())");
        $stmt->bind_param("iss", $user_id, $notification_title, $notification_message);
        $stmt->execute();
        $stmt->close();
        
        // If admin registration, create notification for existing admins
        if ($role === 'admin') {
            $admin_notification_title = "New Admin Registration";
            $admin_notification_message = "A new admin account ({$username}) has registered and is pending approval.";
            
            $stmt = $conn->prepare("SELECT user_id FROM Users WHERE role = 'admin' AND status = 'active' AND user_id != ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $admin_result = $stmt->get_result();
            
            while ($admin = $admin_result->fetch_assoc()) {
                $admin_id = $admin['user_id'];
                $notify_stmt = $conn->prepare("INSERT INTO Notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'warning', NOW())");
                $notify_stmt->bind_param("iss", $admin_id, $admin_notification_title, $admin_notification_message);
                $notify_stmt->execute();
                $notify_stmt->close();
            }
            
            $stmt->close();
        }
        
        // Log the activity
        $action = "User Registration";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (user_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES (?, ?, 'Users', ?, ?, ?, NOW())");
        $stmt->bind_param("isiss", $user_id, $action, $user_id, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        if ($role === 'admin') {
            $_SESSION['success'] = "Registration successful! Your admin account is pending approval.";
        } else {
            $_SESSION['success'] = "Registration successful! You can now log in with your credentials.";
        }
        
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

