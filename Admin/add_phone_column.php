<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if phone column exists in the users table
$columnExists = false;
$columnsResult = $conn->query("SHOW COLUMNS FROM Users LIKE 'phone'");
if ($columnsResult->num_rows > 0) {
    $columnExists = true;
}

// If phone column doesn't exist, add it
if (!$columnExists) {
    $alterTableSQL = "ALTER TABLE Users ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL";
    if ($conn->query($alterTableSQL)) {
        $_SESSION['success'] = "Phone column added successfully to Users table.";
    } else {
        $_SESSION['error'] = "Error adding phone column: " . $conn->error;
    }
} else {
    $_SESSION['info'] = "Phone column already exists in Users table.";
}

// Close connection
$conn->close();

// Redirect back to result.php or another appropriate page
header("Location: result.php");
exit();
?>
