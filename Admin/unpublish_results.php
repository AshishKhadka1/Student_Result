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

// Check if upload exists
$stmt = $conn->prepare("SELECT * FROM result_uploads WHERE id = ?");
$stmt->bind_param("i", $upload_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Upload not found.";
    header("Location: manage_results.php?tab=manage");
    exit();
}

$upload = $result->fetch_assoc();
$stmt->close();

// Update status to Draft
$stmt = $conn->prepare("UPDATE result_uploads SET status = 'Draft' WHERE id = ?");
$stmt->bind_param("i", $upload_id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Results have been unpublished successfully.";
} else {
    $_SESSION['error_message'] = "Failed to unpublish results: " . $conn->error;
}

$stmt->close();
$conn->close();

// Redirect back to manage results page or view upload page
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
if (strpos($referrer, 'view_upload.php') !== false) {
    header("Location: view_upload.php?id=" . $upload_id);
} else {
    header("Location: manage_results.php?tab=manage");
}
exit();
