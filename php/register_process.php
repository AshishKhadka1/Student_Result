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
    require_once '../includes/db_connetc.php';
    
    // Check connection
    if ($conn->connect_error) {
        $_SESSION['error'] = "Connection failed: " . $conn->connect_error;
        header("Location: ../register.php");
        exit();
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT user_id FROM Users WHERE username = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Database error: " . $conn->error;
        header("Location: ../register.php");
        exit();
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Username already exists";
        header("Location: ../register.php");
        exit();
    }
    $stmt->close();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Database error: " . $conn->error;
        header("Location: ../register.php");
        exit();
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Email already exists";
        header("Location: ../register.php");
        exit();
    }
    $stmt->close();
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into Users table
        $status = ($role == 'admin') ? 'pending' : 'active';
        $created_at = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("INSERT INTO Users (username, password, email, full_name, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("sssssss", $username, $hashed_password, $email, $full_name, $role, $status, $created_at);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating user: " . $stmt->error);
        }
        
        $user_id = $conn->insert_id;
        $stmt->close();
        
        // Role-specific data
        if ($role == 'student') {
            $roll_number = $_POST['roll_number'] ?? '';
            $class_id = $_POST['class_id'] ?? '';
            $batch_year = $_POST['batch_year'] ?? '';
            
            if (empty($roll_number) || empty($class_id) || empty($batch_year)) {
                throw new Exception("All student fields are required");
            }
            
            // Check if roll number already exists
            $check_stmt = $conn->prepare("SELECT student_id FROM Students WHERE roll_number = ?");
            if (!$check_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $check_stmt->bind_param("s", $roll_number);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                throw new Exception("Roll number already exists");
            }
            $check_stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO Students (user_id, roll_number, class_id, batch_year, created_at) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param("issss", $user_id, $roll_number, $class_id, $batch_year, $created_at);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating student record: " . $stmt->error);
            }
            $stmt->close();
            
        } elseif ($role == 'teacher') {
            $employee_id = $_POST['employee_id'] ?? '';
            $department = $_POST['department'] ?? '';
            $qualification = $_POST['qualification'] ?? '';
            
            if (empty($employee_id) || empty($department) || empty($qualification)) {
                throw new Exception("All teacher fields are required");
            }
            
            // Check if employee ID already exists
            $check_stmt = $conn->prepare("SELECT teacher_id FROM Teachers WHERE employee_id = ?");
            if (!$check_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $check_stmt->bind_param("s", $employee_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                throw new Exception("Employee ID already exists");
            }
            $check_stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO Teachers (user_id, employee_id, department, qualification, joining_date, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param("isssss", $user_id, $employee_id, $department, $qualification, $created_at, $created_at);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating teacher record: " . $stmt->error);
            }
            $stmt->close();
            
        } elseif ($role == 'admin') {
            $admin_code = $_POST['admin_code'] ?? '';
            
            // Verify admin registration code
            $admin_registration_code = "ADMIN123";
            
            if ($admin_code !== $admin_registration_code) {
                throw new Exception("Invalid admin registration code");
            }
        }
        
        // Try to create notification (optional - won't fail if Notifications table doesn't exist)
        try {
            $welcome_title = "Welcome to Result Management System";
            $welcome_message = "Thank you for registering. We're excited to have you on board!";
            
            $notify_stmt = $conn->prepare("INSERT INTO Notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'success', ?)");
            if ($notify_stmt) {
                $notify_stmt->bind_param("isss", $user_id, $welcome_title, $welcome_message, $created_at);
                $notify_stmt->execute();
                $notify_stmt->close();
            }
        } catch (Exception $e) {
            // Ignore notification errors - they're not critical
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message and handle redirection based on role
        if ($role == 'admin') {
            $_SESSION['success'] = "Registration successful! Your admin account is pending approval.";
            header("Location: ../login.php");
        } else {
            // For students and teachers, automatically log them in
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['success'] = "Registration successful! Welcome to the Result Management System.";
            
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
} else {
    // If not POST request, redirect to register page
    header("Location: ../register.php");
    exit();
}
?>
