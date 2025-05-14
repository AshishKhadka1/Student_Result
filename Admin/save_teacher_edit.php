<?php
// Enable error logging to a file
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Starting teacher edit process");

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

    // Get form data
    $teacher_id = getPostValue('teacher_id');
    $user_id = getPostValue('user_id');
    $full_name = getPostValue('full_name');
    $email = getPostValue('email');
    $phone = getPostValue('phone');
    $employee_id = getPostValue('employee_id');
    // Department removed
    $qualification = getPostValue('qualification');
    $joining_date = !empty(getPostValue('joining_date')) ? getPostValue('joining_date') : null;
    $experience = !empty(getPostValue('experience')) ? intval(getPostValue('experience')) : null;
    $status = getPostValue('status', 'active');
    $gender = getPostValue('gender');
    $date_of_birth = !empty(getPostValue('date_of_birth')) ? getPostValue('date_of_birth') : null;
    $address = getPostValue('address');

    error_log("Form data received: " . json_encode([
        'teacher_id' => $teacher_id,
        'user_id' => $user_id,
        'full_name' => $full_name,
        'email' => $email,
        'employee_id' => $employee_id,
        'status' => $status
    ]));

    // Validate required fields
    if (empty($teacher_id) || empty($user_id) || empty($full_name) || empty($email) || empty($employee_id)) {
        error_log("Missing required fields");
        throw new Exception('Please fill all required fields');
    }

    // Begin transaction
    error_log("Beginning transaction");
    $conn->begin_transaction();

    // Check if email already exists for another user
    error_log("Checking if email exists for another user: $email");
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        error_log("Email already exists for another user: $email");
        throw new Exception('Email already exists for another user');
    }
    $stmt->close();

    // Check if employee ID already exists for another teacher
    error_log("Checking if employee ID exists for another teacher: $employee_id");
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE employee_id = ? AND teacher_id != ?");
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $employee_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        error_log("Employee ID already exists for another teacher: $employee_id");
        throw new Exception('Employee ID already exists for another teacher');
    }
    $stmt->close();

    // Update user information
    error_log("Updating user information");
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, status = ?, phone = ? WHERE user_id = ?");
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ssssi", $full_name, $email, $status, $phone, $user_id);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        throw new Exception('Error updating user: ' . $stmt->error);
    }
    $stmt->close();
    
    // Check if gender column exists in teachers table
    error_log("Checking for gender column");
    $column_check = $conn->query("SHOW COLUMNS FROM teachers LIKE 'gender'");
    $gender_exists = $column_check && $column_check->num_rows > 0;
    error_log("Gender column exists: " . ($gender_exists ? 'yes' : 'no'));
    
    // Check if date_of_birth column exists in teachers table
    error_log("Checking for date_of_birth column");
    $column_check = $conn->query("SHOW COLUMNS FROM teachers LIKE 'date_of_birth'");
    $dob_exists = $column_check && $column_check->num_rows > 0;
    error_log("DOB column exists: " . ($dob_exists ? 'yes' : 'no'));
    
    // Prepare SQL based on existing columns
    error_log("Preparing teacher update statement");
    if ($gender_exists && $dob_exists) {
        $query = "UPDATE teachers SET employee_id = ?, qualification = ?, joining_date = ?, experience = ?, address = ?, gender = ?, date_of_birth = ? WHERE teacher_id = ?";
        error_log("Using query with gender and DOB: $query");
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("sssisssi", $employee_id, $qualification, $joining_date, $experience, $address, $gender, $date_of_birth, $teacher_id);
    } elseif ($gender_exists) {
        $query = "UPDATE teachers SET employee_id = ?, qualification = ?, joining_date = ?, experience = ?, address = ?, gender = ? WHERE teacher_id = ?";
        error_log("Using query with gender: $query");
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("ssisssi", $employee_id, $qualification, $joining_date, $experience, $address, $gender, $teacher_id);
    } elseif ($dob_exists) {
        $query = "UPDATE teachers SET employee_id = ?, qualification = ?, joining_date = ?, experience = ?, address = ?, date_of_birth = ? WHERE teacher_id = ?";
        error_log("Using query with DOB: $query");
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("ssisssi", $employee_id, $qualification, $joining_date, $experience, $address, $date_of_birth, $teacher_id);
    } else {
        $query = "UPDATE teachers SET employee_id = ?, qualification = ?, joining_date = ?, experience = ?, address = ? WHERE teacher_id = ?";
        error_log("Using basic query: $query");
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("ssisi", $employee_id, $qualification, $joining_date, $experience, $address, $teacher_id);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        throw new Exception('Error updating teacher: ' . $stmt->error);
    }
    $stmt->close();
    
    // Log the activity
    error_log("Logging activity");
    $activity_type = 'teacher_update';
    $description = "Updated teacher: $full_name";
    $admin_id = $_SESSION['user_id'];
    $current_time = date('Y-m-d H:i:s');
    
    // Check if activities table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'activities'");
    if ($table_check && $table_check->num_rows > 0) {
        error_log("Activities table exists, logging activity");
        $log_stmt = $conn->prepare("INSERT INTO activities (user_id, activity_type, description, created_by, created_at) VALUES (?, ?, ?, ?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("issss", $user_id, $activity_type, $description, $admin_id, $current_time);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            error_log("Could not prepare activity log statement: " . $conn->error);
            // Non-critical, continue without throwing exception
        }
    } else {
        error_log("Activities table does not exist, skipping activity log");
    }
    
    error_log("All database operations completed successfully");

    // Commit transaction
    error_log("Committing transaction");
    $conn->commit();
    
    error_log("Transaction committed successfully");

    // Clean any output that might have been generated
    ob_end_clean();
    
    error_log("Teacher updated successfully: $teacher_id");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Teacher updated successfully', 'teacher_id' => $teacher_id]);
} catch (Exception $e) {
    error_log("Error in save_teacher_edit.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    // Rollback transaction on error if connection exists
    if (isset($conn) && $conn instanceof mysqli) {
        error_log("Rolling back transaction");
        $conn->rollback();
    }
    
    // Clean any output that might have been generated
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error updating teacher: ' . $e->getMessage()]);
}

// Close connection if it exists
if (isset($conn) && $conn instanceof mysqli) {
    error_log("Closing database connection");
    $conn->close();
}
?>
