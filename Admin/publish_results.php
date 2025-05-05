<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Invalid request. Upload ID is required.";
    $_SESSION['message_type'] = "red";
    header("Location: manage_results.php?tab=manage");
    exit();
}

$upload_id = $_GET['id'];

// Update upload status to Published
$stmt = $conn->prepare("UPDATE ResultUploads SET status = 'Published' WHERE id = ?");
$stmt->bind_param("i", $upload_id);

if ($stmt->execute()) {
    // Also update all associated results to published
    $conn->query("UPDATE Results SET status = 'published' WHERE upload_id = $upload_id");
    
    $_SESSION['message'] = "Results have been published successfully.";
    $_SESSION['message_type'] = "green";
} else {
    $_SESSION['message'] = "Error publishing results: " . $conn->error;
    $_SESSION['message_type'] = "red";
}

$stmt->close();
$conn->close();

header("Location: manage_results.php?tab=manage");
exit();
?>
