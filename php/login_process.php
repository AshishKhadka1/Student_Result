<?php
// Start session
session_start();

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $remember = isset($_POST['remember-me']);
    
    // Validate input
    if (empty($username) || empty($password) || empty($role)) {
        $_SESSION['error'] = "All fields are required";
        header("Location: ../login.php");
        exit();
    }
    
    // Connect to database
    $conn = new mysqli('localhost', 'root', '', 'result_management');
    if ($conn->connect_error) {
        $_SESSION['error'] = "Connection failed: " . $conn->connect_error;
        header("Location: ../login.php");
        exit();
    }
    
    // Prepare SQL statement
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
    $stmt->bind_param("ss", $username, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if user exists
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Special case for admin with plain password (for testing)
        if ($username === 'admin' && $password === 'admin123' && $role === 'admin') {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // Set remember-me cookie if checked
            if ($remember) {
                setcookie('remember_user', $username, time() + (86400 * 30), "/"); // 30 days
                setcookie('remember_role', $role, time() + (86400 * 30), "/"); // 30 days
            }
            
            // Update last login time
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("i", $user['user_id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Redirect to dashboard
            header("Location: ../{$role}_dashboard.php");
            exit();
        }
        // Normal password verification
        else if (password_verify($password, $user['password'])) {
            // Check if account is active
            if ($user['status'] == 'inactive') {
                $_SESSION['error'] = "Your account is inactive. Please contact the administrator.";
                header("Location: ../login.php");
                exit();
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // Set remember-me cookie if checked
            if ($remember) {
                setcookie('remember_user', $username, time() + (86400 * 30), "/"); // 30 days
                setcookie('remember_role', $role, time() + (86400 * 30), "/"); // 30 days
            }
            
            // Update last login time
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("i", $user['user_id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Redirect to dashboard
            header("Location: ../{$role}_dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid username or password";
            header("Location: ../login.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Invalid username or password";
        header("Location: ../login.php");
        exit();
    }
    
    $stmt->close();
    $conn->close();
} else {
    // If not POST request, redirect to login page
    header("Location: ../login.php");
    exit();
}
?>