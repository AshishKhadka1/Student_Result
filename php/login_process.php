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
    header("Location: ../index.php");
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if required fields are set
    if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['role'])) {
        // Get form data
        $username = $_POST['username'];
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        // For debugging
        // error_log("Login attempt: Username: $username, Role: $role");
        
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT * FROM Users WHERE username=? AND role=?");
        $stmt->bind_param("ss", $username, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // For debugging
            // error_log("User found: " . print_r($row, true));
            
            // Try multiple password verification methods
            if (password_verify($password, $row['password'])) {
                // Password is verified with bcrypt
                // Login successful
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                
                // Update last login time
                $updateStmt = $conn->prepare("UPDATE Users SET last_login = NOW() WHERE user_id = ?");
                $updateStmt->bind_param("i", $row['user_id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Remember me functionality
                if (isset($_POST['remember-me']) && $_POST['remember-me'] == 'on') {
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                    setcookie('remember_user', $row['username'], $expiry, '/');
                    setcookie('remember_role', $row['role'], $expiry, '/');
                }
                
                // Redirect based on role
                if ($role == 'student') {
                    header("Location: ../student_dashboard.php");
                    exit();
                } elseif ($role == 'teacher') {
                    header("Location: ../teacher_dashboard.php");
                    exit();
                } elseif ($role == 'admin') {
                    header("Location: ../admin_dashboard.php");
                    exit();
                }
            } elseif ($row['password'] === md5($password)) {
                // Password is using MD5 hash
                // Update to bcrypt for future logins
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
                $updateStmt->bind_param("si", $newHash, $row['user_id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Login successful
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                
                // Redirect based on role
                if ($role == 'student') {
                    header("Location: ../student_dashboard.php");
                    exit();
                } elseif ($role == 'teacher') {
                    header("Location: ../teacher_dashboard.php");
                    exit();
                } elseif ($role == 'admin') {
                    header("Location: ../admin_dashboard.php");
                    exit();
                }
            } elseif ($row['password'] === $password) {
                // Password is stored as plain text
                // Update to bcrypt for future logins
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
                $updateStmt->bind_param("si", $newHash, $row['user_id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Login successful
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                
                // Redirect based on role
                if ($role == 'student') {
                    header("Location: ../student_dashboard.php");
                    exit();
                } elseif ($role == 'teacher') {
                    header("Location: ../teacher_dashboard.php");
                    exit();
                } elseif ($role == 'admin') {
                    header("Location: ../admin_dashboard.php");
                    exit();
                }
            } else {
                // For debugging
                // error_log("Password verification failed");
                
                $_SESSION['error'] = "Invalid password!";
                header("Location: ../index.php");
                exit();
            }
        } else {
            // For debugging
            // error_log("No user found with username: $username and role: $role");
            
            $_SESSION['error'] = "User not found!";
            header("Location: ../index.php");
            exit();
        }
        
        $stmt->close();
    } else {
        $_SESSION['error'] = "Please fill all required fields";
        header("Location: ../index.php");
        exit();
    }
} else {
    // If direct access or not POST
    $_SESSION['error'] = "Invalid request method";
    header("Location: ../index.php");
    exit();
}

$conn->close();
?>

