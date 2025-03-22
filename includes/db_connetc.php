<?php
$conn = new mysqli('localhost', 'root', '', 'result_management');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
