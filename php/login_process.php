<?php
// Start session
session_start();

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    // Check connection
    if ($conn->connect_error) {
        $_SESSION['error'] = "Connection failed: " . $conn->connect_error;
        header("Location: ../login.php");
        exit();
    }

    // Check for default admin login
    if ($username === "admin" && $password === "admin123" && $role === "admin") {
        // Set session variables for default admin
        $_SESSION['user_id'] = 1; // Assuming ID 1 for default admin
        $_SESSION['username'] = "admin";
        $_SESSION['role'] = "admin";
        $_SESSION['full_name'] = "System Administrator";
        
        // Set remember-me cookie if checked
        if ($remember) {
            $cookie_expiry = time() + (30 * 24 * 60 * 60); // 30 days
            setcookie('remember_user', $username, $cookie_expiry, '/');
            setcookie('remember_role', $role, $cookie_expiry, '/');
        }
        
        // Try to log login activity if the table exists
        try {
            // Check if LoginLogs table exists
            $tableExists = false;
            $checkTable = $conn->query("SHOW TABLES LIKE 'LoginLogs'");
            if ($checkTable->num_rows > 0) {
                $tableExists = true;
            }
            
            if ($tableExists) {
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_stmt = $conn->prepare("INSERT INTO LoginLogs (user_id, ip_address, user_agent, login_time) VALUES (?, ?, ?, NOW())");
                $log_stmt->bind_param("iss", $_SESSION['user_id'], $ip_address, $user_agent);
                $log_stmt->execute();
            }
        } catch (Exception $e) {
            // Silently continue if logging fails
        }
        
        // Redirect to admin dashboard
        header("Location: ../Admin/admin_dashboard.php");
        exit();
    }

    // Prepare SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM Users WHERE username=? AND role=?");
    $stmt->bind_param("ss", $username, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Check if account is active
            if ($user['status'] == 'inactive') {
                $_SESSION['error'] = "Your account has been deactivated. Please contact the administrator.";
                header("Location: ../login.php");
                exit();
            }
            
            // Check if admin account is pending approval
            if ($role == 'admin' && $user['status'] == 'pending') {
                $_SESSION['error'] = "Your admin account is pending approval. Please wait for an existing admin to approve your account.";
                header("Location: ../login.php");
                exit();
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Set remember-me cookie if checked
            if ($remember) {
                $cookie_expiry = time() + (30 * 24 * 60 * 60); // 30 days
                setcookie('remember_user', $username, $cookie_expiry, '/');
                setcookie('remember_role', $role, $cookie_expiry, '/');
            }
            
            // Try to log login activity if the table exists
            try {
                // Check if LoginLogs table exists
                $tableExists = false;
                $checkTable = $conn->query("SHOW TABLES LIKE 'LoginLogs'");
                if ($checkTable->num_rows > 0) {
                    $tableExists = true;
                }
                
                if ($tableExists) {
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_stmt = $conn->prepare("INSERT INTO LoginLogs (user_id, ip_address, user_agent, login_time) VALUES (?, ?, ?, NOW())");
                    $log_stmt->bind_param("iss", $user['user_id'], $ip_address, $user_agent);
                    $log_stmt->execute();
                }
            } catch (Exception $e) {
                // Silently continue if logging fails
            }
            
            // Redirect based on role
            if ($role == 'student') {
                header("Location: ../Student/student_dashboard.php");
            } elseif ($role == 'teacher') {
                header("Location: ../Teacher/teacher_dashboard.php");
            } elseif ($role == 'admin') {
                header("Location: ../Admin/admin_dashboard.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Invalid password";
            header("Location: ../login.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Invalid username or role";
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
