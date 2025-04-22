<?php
session_start();

// Database connection (Optional, if you have a database)
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "result_management";

$conn = mysqli_connect($host, $user, $pass, $dbname);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
