<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Check if teachersubjects table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'teachersubjects'");
if ($table_check->num_rows == 0) {
    // Create the teachersubjects table
    $create_table_sql = "CREATE TABLE `teachersubjects` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `teacher_id` int(11) NOT NULL,
        `subject_id` int(11) NOT NULL,
        `class_id` int(11) NOT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `teacher_id` (`teacher_id`),
        KEY `subject_id` (`subject_id`),
        KEY `class_id` (`class_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    if (!$conn->query($create_table_sql)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to create teachersubjects table: ' . $conn->error]);
        exit();
    }
}

// Log request data for debugging
$log_file = fopen("teacher_subjects_log.txt", "a");
fwrite($log_file, "Request Time: " . date('Y-m-d H:i:s') . "\n");
fwrite($log_file, "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n");
fwrite($log_file, "POST Data: " . print_r($_POST, true) . "\n");
fwrite($log_file, "GET Data: " . print_r($_GET, true) . "\n");
fwrite($log_file, "-----------------------------------\n");
fclose($log_file);

// Handle POST requests for adding, updating, or removing subjects
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get teacher ID
    $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;

    if (!$teacher_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Teacher ID is required']);
        exit();
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Check the action type
        $action = isset($_POST['action']) ? $_POST['action'] : 'assign';
        
        switch ($action) {
            case 'assign':
                // Assign a new subject to the teacher
                $subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
                $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
                
                if (!$subject_id || !$class_id) {
                    throw new Exception('Subject and Class are required');
                }
                
                // Check if this assignment already exists
                $check_stmt = $conn->prepare("SELECT id FROM teachersubjects WHERE teacher_id = ? AND subject_id = ? AND class_id = ?");
                $check_stmt->bind_param("iii", $teacher_id, $subject_id, $class_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $existing = $check_result->fetch_assoc();
                    
                    // Update the existing assignment to active
                    $update_stmt = $conn->prepare("UPDATE teachersubjects SET is_active = 1 WHERE id = ?");
                    $update_stmt->bind_param("i", $existing['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    $message = "Subject assignment updated successfully";
                } else {
                    // Insert new assignment
                    $insert_stmt = $conn->prepare("INSERT INTO teachersubjects (teacher_id, subject_id, class_id, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
                    $insert_stmt->bind_param("iii", $teacher_id, $subject_id, $class_id);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                    
                    $message = "Subject assigned successfully";
                }
                
                $check_stmt->close();
                break;
                
            case 'toggle_status':
                // Toggle the status of an existing assignment
                $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
                $status = isset($_POST['status']) ? intval($_POST['status']) : 0;
                
                if (!$assignment_id) {
                    throw new Exception('Assignment ID is required');
                }
                
                $update_stmt = $conn->prepare("UPDATE teachersubjects SET is_active = ? WHERE id = ?");
                $update_stmt->bind_param("ii", $status, $assignment_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                $message = $status ? "Subject assignment activated" : "Subject assignment deactivated";
                break;
                
            case 'remove':
                // Remove an assignment
                $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
                
                if (!$assignment_id) {
                    throw new Exception('Assignment ID is required');
                }
                
                $delete_stmt = $conn->prepare("DELETE FROM teachersubjects WHERE id = ?");
                $delete_stmt->bind_param("i", $assignment_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                $message = "Subject assignment removed successfully";
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        // Commit transaction
        $conn->commit();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

    $conn->close();
    exit();
}

// If not a POST request, return an error
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST requests are supported.']);
exit();
?>
