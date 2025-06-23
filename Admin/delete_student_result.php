<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check if required parameters are provided
if (!isset($_GET['upload_id']) || !isset($_GET['student_id']) || empty($_GET['upload_id']) || empty($_GET['student_id'])) {
    $_SESSION['error_message'] = "Upload ID and Student ID are required.";
    header("Location: manage_results.php?tab=manage");
    exit();
}

$upload_id = $_GET['upload_id'];
$student_id = $_GET['student_id'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start transaction
$conn->begin_transaction();

try {
    // Check if the student results exist for this upload
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM results WHERE upload_id = ? AND student_id = ?");
    $stmt->bind_param("is", $upload_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] === 0) {
        throw new Exception("No results found for this student in the specified upload.");
    }
    $stmt->close();

    // Get student name for success message
    $stmt = $conn->prepare("SELECT u.full_name FROM students s JOIN users u ON s.user_id = u.user_id WHERE s.student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_name = $result->num_rows > 0 ? $result->fetch_assoc()['full_name'] : 'Unknown Student';
    $stmt->close();

    // Delete the student's results for this upload
    $stmt = $conn->prepare("DELETE FROM results WHERE upload_id = ? AND student_id = ?");
    $stmt->bind_param("is", $upload_id, $student_id);
    $stmt->execute();
    $deleted_count = $stmt->affected_rows;
    $stmt->close();

    // Check if there are any remaining results for this upload
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM results WHERE upload_id = ?");
    $stmt->bind_param("i", $upload_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $remaining_results = $result->fetch_assoc()['count'];
    $stmt->close();

    // If no results remain, delete the upload record as well
    if ($remaining_results == 0) {
        $stmt = $conn->prepare("DELETE FROM result_uploads WHERE id = ?");
        $stmt->bind_param("i", $upload_id);
        $stmt->execute();
        $stmt->close();
    }

    // Commit transaction
    $conn->commit();
    $_SESSION['success_message'] = "Successfully deleted results for " . htmlspecialchars($student_name) . " (" . $deleted_count . " records deleted).";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error_message'] = "Failed to delete student results: " . $e->getMessage();
}

$conn->close();

// Redirect back to manage results page
header("Location: manage_results.php?tab=manage");
exit();
?>
