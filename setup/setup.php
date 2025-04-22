<?php
// Database setup script
// Run this script to create all necessary tables and initial data

// Connect to MySQL without selecting a database
$conn = new mysqli('localhost', 'root', '');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Result Management System - Database Setup</h1>";

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS result_management";
if ($conn->query($sql) === TRUE) {
    echo "<p>Database created successfully or already exists</p>";
} else {
    echo "<p>Error creating database: " . $conn->error . "</p>";
    exit();
}

// Select the database
$conn->select_db("result_management");

// Read and execute the SQL file
$sql_file = file_get_contents('database.sql');

// Split SQL file into individual statements
$statements = explode(';', $sql_file);

// Execute each statement
$success = true;
foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        if ($conn->query($statement) !== TRUE) {
            echo "<p>Error executing statement: " . $conn->error . "</p>";
            echo "<p>Statement: " . $statement . "</p>";
            $success = false;
        }
    }
}

if ($success) {
    echo "<p>Database setup completed successfully!</p>";
    echo "<p>Default admin credentials:</p>";
    echo "<ul>";
    echo "<li>Username: admin</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    echo "<p><a href='../login.php'>Go to Login Page</a></p>";
} else {
    echo "<p>Database setup completed with errors. Please check the error messages above.</p>";
}

$conn->close();
?>