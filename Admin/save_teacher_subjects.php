<?php
// Enable error logging to a file
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Starting teacher subjects save process");

// Start output buffering to prevent any unwanted output
ob_start();

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Clean any output that might have been generated
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Clean any output that might have been generated
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Function to safely get POST values
function getPostValue($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

// Check for duplicate submission
$submission_token = getPostValue('submission_token');
if (!empty($submission_token)) {
    // Store submission tokens in session to prevent duplicates
    if (!isset($_SESSION['submission_tokens'])) {
        $_SESSION['submission_tokens'] = [];
    }
    
    // Check if this token has been used before
    if (in_array($submission_token, $_SESSION['submission_tokens'])) {
        error_log("Duplicate submission detected with token: $submission_token");
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'This form has already been submitted. Please refresh the page to submit again.']);
        exit();
    }
    
    // Add token to session
    $_SESSION['submission_tokens'][] = $submission_token;
    
    // Limit the number of stored tokens to prevent session bloat
    if (count($_SESSION['submission_tokens']) > 10) {
        array_shift($_SESSION['submission_tokens']);
    }
}

try {
    error_log("Connecting to database");
    // Database connection
    $conn = new mysqli('localhost', 'root', '', 'result_management');
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    error_log("Database connection successful");

    // Check if teachersubjects table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'teachersubjects'");
    if ($table_check->num_rows == 0) {
        // Create the teachersubjects table
        $create_table_sql = "CREATE TABLE `teachersubjects` (
            `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
            `teacher_id` int(11) NOT NULL,
            `subject_id` int(11) NOT NULL,
            `class_id` int(11) NOT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`assignment_id`),
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

    // Begin transaction
    error_log("Beginning transaction");
    $conn->begin_transaction();

    // Get action type
    $action = getPostValue('action', 'assign');
    error_log("Action: $action");

    if ($action === 'assign') {
        // Assign new subject
        $teacher_id = getPostValue('teacher_id');
        $subject_id = getPostValue('subject_id');
        $class_id = getPostValue('class_id');
        
        error_log("Form data received: " . json_encode([
            'teacher_id' => $teacher_id,
            'subject_id' => $subject_id,
            'class_id' => $class_id
        ]));

        // Validate required fields
        if (empty($teacher_id) || empty($subject_id) || empty($class_id)) {
            error_log("Missing required fields");
            throw new Exception('Please fill all required fields');
        }

        // Check if assignment already exists
        error_log("Checking if assignment already exists");
        $stmt = $conn->prepare("SELECT assignment_id FROM teachersubjects WHERE teacher_id = ? AND subject_id = ? AND class_id = ?");
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("iii", $teacher_id, $subject_id, $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Assignment exists, update it to active
            $row = $result->fetch_assoc();
            $assignment_id = $row['assignment_id'];
            $stmt->close();
            
            error_log("Assignment exists, updating to active: $assignment_id");
            $update_stmt = $conn->prepare("UPDATE teachersubjects SET is_active = 1 WHERE assignment_id = ?");
            if (!$update_stmt) {
                error_log("Prepare statement failed: " . $conn->error);
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $update_stmt->bind_param("i", $assignment_id);
            if (!$update_stmt->execute()) {
                error_log("Execute failed: " . $update_stmt->error);
                throw new Exception('Error updating assignment: ' . $update_stmt->error);
            }
            $update_stmt->close();
            
            $message = 'Subject assignment updated successfully';
        } else {
            // New assignment
            $stmt->close();
            
            error_log("Creating new assignment");
            $insert_stmt = $conn->prepare("INSERT INTO teachersubjects (teacher_id, subject_id, class_id, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
            if (!$insert_stmt) {
                error_log("Prepare statement failed: " . $conn->error);
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $insert_stmt->bind_param("iii", $teacher_id, $subject_id, $class_id);
            if (!$insert_stmt->execute()) {
                error_log("Execute failed: " . $insert_stmt->error);
                throw new Exception('Error creating assignment: ' . $insert_stmt->error);
            }
            $assignment_id = $conn->insert_id;
            $insert_stmt->close();
            
            $message = 'Subject assigned successfully';
        }
        
        error_log("Assignment completed: $message");
    } elseif ($action === 'toggle_status') {
        // Toggle subject status
        $assignment_id = getPostValue('assignment_id');
        $status = getPostValue('status');
        $teacher_id = getPostValue('teacher_id');
        
        error_log("Form data received: " . json_encode([
            'assignment_id' => $assignment_id,
            'status' => $status,
            'teacher_id' => $teacher_id
        ]));

        // Validate required fields
        if (empty($assignment_id) || !isset($status) || empty($teacher_id)) {
            error_log("Missing required fields");
            throw new Exception('Missing required fields for status toggle');
        }

        // Convert status to boolean
        $is_active = $status ? 1 : 0;
        
        error_log("Updating assignment status: $assignment_id to $is_active");
        $stmt = $conn->prepare("UPDATE teachersubjects SET is_active = ? WHERE assignment_id = ? AND teacher_id = ?");
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("iii", $is_active, $assignment_id, $teacher_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            throw new Exception('Error updating assignment status: ' . $stmt->error);
        }
        $stmt->close();
        
        $message = $is_active ? 'Subject activated successfully' : 'Subject deactivated successfully';
        error_log("Status updated: $message");
    } elseif ($action === 'remove') {
        // Remove subject assignment
        $assignment_id = getPostValue('assignment_id');
        $teacher_id = getPostValue('teacher_id');
        
        error_log("Form data received: " . json_encode([
            'assignment_id' => $assignment_id,
            'teacher_id' => $teacher_id
        ]));

        // Validate required fields
        if (empty($assignment_id) || empty($teacher_id)) {
            error_log("Missing required fields");
            throw new Exception('Missing required fields for assignment removal');
        }

        error_log("Removing assignment: $assignment_id");
        $stmt = $conn->prepare("DELETE FROM teachersubjects WHERE assignment_id = ? AND teacher_id = ?");
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("ii", $assignment_id, $teacher_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            throw new Exception('Error removing assignment: ' . $stmt->error);
        }
        $stmt->close();
        
        $message = 'Subject assignment removed successfully';
        error_log("Assignment removed: $message");
    } else {
        error_log("Invalid action: $action");
        throw new Exception('Invalid action');
    }
    
    // Log the activity
    error_log("Logging activity");
    $activity_type = 'teacher_subject_' . $action;
    $description = "Teacher subject $action: Teacher ID $teacher_id";
    $admin_id = $_SESSION['user_id'];
    $current_time = date('Y-m-d H:i:s');
    
    // Check if activities table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'activities'");
    if ($table_check && $table_check->num_rows > 0) {
        error_log("Activities table exists, logging activity");
        $log_stmt = $conn->prepare("INSERT INTO activities (user_id, activity_type, description, created_by, created_at) VALUES (?, ?, ?, ?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("issss", $teacher_id, $activity_type, $description, $admin_id, $current_time);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            error_log("Could not prepare activity log statement: " . $conn->error);
            // Non-critical, continue without throwing exception
        }
    } else {
        error_log("Activities table does not exist, skipping activity log");
    }
    
    // Commit transaction
    error_log("Committing transaction");
    $conn->commit();
    
    // Clean any output that might have been generated
    ob_end_clean();
    
    error_log("Operation completed successfully");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    error_log("Error in save_teacher_subjects.php: " . $e->getMessage());
    // Rollback transaction on error if connection exists
    if (isset($conn) && $conn instanceof mysqli) {
        error_log("Rolling back transaction");
        $conn->rollback();
    }
    
    // Clean any output that might have been generated
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Close connection if it exists
if (isset($conn) && $conn instanceof mysqli) {
    error_log("Closing database connection");
    $conn->close();
}
?>
