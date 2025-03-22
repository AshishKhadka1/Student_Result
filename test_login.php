<?php
// This is a simple test script to verify database connection and user authentication
// Place this in your root directory and access it via browser to test

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Login Test Script</h1>";

// Test database connection
echo "<h2>Testing Database Connection</h2>";
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    echo "<p style='color:red'>Connection failed: " . $conn->connect_error . "</p>";
    exit();
} else {
    echo "<p style='color:green'>Database connection successful!</p>";
}

// Test user query
echo "<h2>Testing User Query</h2>";
$username = "admin"; // Change this to a username you know exists
$role = "admin";     // Change this to match the user's role

$stmt = $conn->prepare("SELECT * FROM Users WHERE username=? AND role=?");
$stmt->bind_param("ss", $username, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<p style='color:green'>User found: " . htmlspecialchars($row['username']) . " (Role: " . htmlspecialchars($row['role']) . ")</p>";
    
    // Test password verification with a known password
    $testPassword = "password123"; // Change this to the actual password
    if (password_verify($testPassword, $row['password'])) {
        echo "<p style='color:green'>Password verification successful!</p>";
    } else {
        echo "<p style='color:red'>Password verification failed!</p>";
        echo "<p>Password hash in database: " . htmlspecialchars($row['password']) . "</p>";
        
        // Check if the password is stored in plain text or with a different hashing method
        if ($row['password'] === $testPassword) {
            echo "<p>Note: Password is stored as plain text!</p>";
        } elseif ($row['password'] === md5($testPassword)) {
            echo "<p>Note: Password appears to be hashed with MD5!</p>";
        }
    }
} else {
    echo "<p style='color:red'>No user found with username: " . htmlspecialchars($username) . " and role: " . htmlspecialchars($role) . "</p>";
}

$stmt->close();

// Test session functionality
echo "<h2>Testing Session Functionality</h2>";
session_start();
$_SESSION['test_value'] = "This is a test session value";
echo "<p>Session value set. Refresh the page to verify it persists.</p>";

if (isset($_SESSION['test_value'])) {
    echo "<p style='color:green'>Session value found: " . htmlspecialchars($_SESSION['test_value']) . "</p>";
} else {
    echo "<p style='color:red'>No session value found. Sessions may not be working correctly.</p>";
}

// Test redirection
echo "<h2>Testing Redirection</h2>";
echo "<p>Click the button below to test redirection:</p>";
echo "<form method='post'><button type='submit' name='test_redirect' style='padding: 5px 10px; background-color: #0047AB; color: white; border: none; border-radius: 4px;'>Test Redirect</button></form>";

if (isset($_POST['test_redirect'])) {
    echo "<p>Attempting to redirect to index.php in 3 seconds...</p>";
    echo "<script>setTimeout(function() { window.location.href = 'index.php'; }, 3000);</script>";
}

$conn->close();
?>

