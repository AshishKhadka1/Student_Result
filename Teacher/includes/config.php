<?php
/**
* Database configuration
*/

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'result_management');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
   die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");

// Application settings
define('SITE_NAME', 'Result Management System');
define('SITE_URL', 'http://localhost/Student_Result');
define('ADMIN_EMAIL', 'admin@example.com');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
   session_unset();
   session_destroy();
   header("Location: ../login.php?timeout=1");
   exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/error.log');

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../../logs')) {
    mkdir(__DIR__ . '/../../logs', 0777, true);
}
?>
