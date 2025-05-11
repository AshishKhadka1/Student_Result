<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Upload ID is required.";
    header("Location: manage_results.php?tab=manage");
    exit();
}

$upload_id = $_GET['id'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start transaction
$conn->begin_transaction();

try {
    // Check if upload exists
    $stmt = $conn->prepare("SELECT * FROM result_uploads WHERE id = ?");
    $stmt->bind_param("i", $upload_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Upload not found.");
    }

    $upload = $result->fetch_assoc();
    $stmt->close();

    // Delete associated results first
    $stmt = $conn->prepare("DELETE FROM results WHERE upload_id = ?");
    $stmt->bind_param("i", $upload_id);
    $stmt->execute();
    $stmt->close();

    // Delete the upload record
    $stmt = $conn->prepare("DELETE FROM result_uploads WHERE id = ?");
    $stmt->bind_param("i", $upload_id);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $conn->commit();
    $_SESSION['success_message'] = "Upload and associated results have been deleted successfully.";
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error_message'] = "Failed to delete upload: " . $e->getMessage();
}

$conn->close();

// Redirect back to manage results page
header("Location: manage_results.php?tab=manage");
exit();
