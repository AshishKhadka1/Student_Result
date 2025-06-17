<?php
// Start session
session_start();

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get common form data
    $full_name = $_POST['full_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Validate input
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
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match";
        header("Location: ../register.php");
        exit();
    }
    
    // Check password strength
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long";
        header("Location: ../register.php");
        exit();
    }
    
    // Connect to database
    $conn = new mysqli('localhost', 'root', '', 'result_management');
    
    // Check connection
    if ($conn->connect_error) {
        $_SESSION['error'] = "Connection failed: " . $conn->connect_error;
        header("Location: ../register.php");
        exit();
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT * FROM Users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Username already exists";
        header("Location: ../register.php");
        exit();
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT * FROM Users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Email already exists";
        header("Location: ../register.php");
        exit();
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into Users table
        $status = ($role == 'admin') ? 'pending' : 'active'; // Admin accounts need approval
        $stmt = $conn->prepare("INSERT INTO Users (username, password, email, full_name, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssss", $username, $hashed_password, $email, $full_name, $role, $status);
        $stmt->execute();
        $user_id = $conn->insert_id;
        
        // Role-specific data
        if ($role == 'student') {
            $roll_number = $_POST['roll_number'] ?? '';
            $class_id = $_POST['class_id'] ?? '';
            $batch_year = $_POST['batch_year'] ?? '';
            
            if (empty($roll_number) || empty($class_id) || empty($batch_year)) {
                throw new Exception("All student fields are required");
            }
            
            $stmt = $conn->prepare("INSERT INTO Students (user_id, roll_number, class_id, batch_year, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isss", $user_id, $roll_number, $class_id, $batch_year);
            $stmt->execute();
            
        } elseif ($role == 'teacher') {
            $employee_id = $_POST['employee_id'] ?? '';
            $department = $_POST['department'] ?? '';
            $qualification = $_POST['qualification'] ?? '';
            
            if (empty($employee_id) || empty($department) || empty($qualification)) {
                throw new Exception("All teacher fields are required");
            }
            
            $stmt = $conn->prepare("INSERT INTO Teachers (user_id, employee_id, department, qualification, joining_date, created_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $user_id, $employee_id, $department, $qualification);
            $stmt->execute();
            
        } elseif ($role == 'admin') {
            $admin_code = $_POST['admin_code'] ?? '';
            
            // Verify admin registration code
            $admin_registration_code = "ADMIN123"; // This should be stored securely, not hardcoded
            
            if ($admin_code !== $admin_registration_code) {
                throw new Exception("Invalid admin registration code");
            }
            
            // Create notification for existing admins about new admin registration
            $notification_title = "New Admin Registration";
            $notification_message = "A new admin account has been registered by $full_name ($username) and is pending approval.";
            
            $stmt = $conn->prepare("SELECT user_id FROM Users WHERE role='admin' AND status='active'");
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($admin = $result->fetch_assoc()) {
                $admin_id = $admin['user_id'];
                $notify_stmt = $conn->prepare("INSERT INTO Notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'info', NOW())");
                $notify_stmt->bind_param("iss", $admin_id, $notification_title, $notification_message);
                $notify_stmt->execute();
            }
        }
        
        // Create welcome notification for the new user
        $welcome_title = "Welcome to Result Management System";
        $welcome_message = "Thank you for registering. We're excited to have you on board!";
        
        $notify_stmt = $conn->prepare("INSERT INTO Notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'success', NOW())");
        $notify_stmt->bind_param("iss", $user_id, $welcome_title, $welcome_message);
        $notify_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Set success message and handle redirection based on role
        if ($role == 'admin') {
            $_SESSION['success'] = "Registration successful! Your admin account is pending approval.";
            // Admin accounts need approval, so redirect to login
            header("Location: ../login.php");
        } else {
            // For students and teachers, automatically log them in and redirect to dashboard
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['full_name'] = $full_name;
            
            // Set a welcome message for the dashboard
            $_SESSION['welcome_message'] = "Welcome to the Result Management System! Your account has been created successfully.";
            
            // Redirect based on role
            if ($role == 'student') {
                header("Location: ../Student/student_dashboard.php");
            } elseif ($role == 'teacher') {
                header("Location: ../Teacher/teacher_dashboard.php");
            }
        }
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: ../register.php");
        exit();
    }
    
    $conn->close();
}
?>
