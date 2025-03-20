<?php
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$username = $_POST['username'];
$password = $_POST['password'];
$role = $_POST['role'];

// Fetch user from database
$sql = "SELECT * FROM Users WHERE username='$username' AND role='$role'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (password_verify($password, $row['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['role'] = $row['role'];

        // Redirect based on role
        if ($role == 'student') {
            header("Location: ../student_dashboard.php");
        } elseif ($role == 'teacher') {
            header("Location: ../teacher_dashboard.php");
        } elseif ($role == 'admin') {
            header("Location: ../admin_dashboard.php");
        }
    } else {
        echo "Invalid password!";
    }
} else {
    echo "User not found!";
}

$conn->close();
?>