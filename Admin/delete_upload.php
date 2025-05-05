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

// Begin transaction
$conn->begin_transaction();

try {
    // First delete all results associated with this upload
    $stmt = $conn->prepare("DELETE FROM Results WHERE upload_id = ?");
    $stmt->bind_param("i", $upload_id);
    $stmt->execute();
    $stmt->close();
    
    // Then delete the upload record
    $stmt = $conn->prepare("DELETE FROM ResultUploads WHERE id = ?");
    $stmt->bind_param("i", $upload_id);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['message'] = "Upload and associated results have been deleted successfully.";
    $_SESSION['message_type'] = "green";
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $_SESSION['message'] = "Error deleting upload: " . $e->getMessage();
    $_SESSION['message_type'] = "red";
}

$conn->close();

header("Location: manage_results.php?tab=manage");
exit();
?>
