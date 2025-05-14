<?php
// Enable error logging to a file
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Starting teacher save process");

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
    $full_name = getPostValue('full_name');
    $email = getPostValue('email');
    $password = getPostValue('password');
    $confirm_password = getPostValue('confirm_password');
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
        'full_name' => $full_name,
        'email' => $email,
        'employee_id' => $employee_id,
        'status' => $status
    ]));

    // Validate required fields
    if (empty($full_name) || empty($email) || empty($password) || empty($employee_id)) {
        error_log("Missing required fields");
        throw new Exception('Please fill all required fields');
    }

    // Validate password match
    if ($password !== $confirm_password) {
        error_log("Passwords do not match");
        throw new Exception('Passwords do not match');
    }

    // Check if email already exists
    error_log("Checking if email exists: $email");
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        error_log("Email already exists: $email");
        throw new Exception('Email already exists');
    }
    $stmt->close();

    // Check if employee ID already exists
    error_log("Checking if employee ID exists: $employee_id");
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE employee_id = ?");
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        error_log("Employee ID already exists: $employee_id");
        throw new Exception('Employee ID already exists');
    }
    $stmt->close();

    // Begin transaction
    error_log("Beginning transaction");
    $conn->begin_transaction();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if username column exists in users table
    error_log("Checking for username column");
    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'username'");
    $username_exists = $column_check && $column_check->num_rows > 0;
    error_log("Username column exists: " . ($username_exists ? 'yes' : 'no'));
    
    // Generate a username from email if needed
    $username = '';
    if ($username_exists) {
        // Generate username from email (part before @)
        $username = strtolower(explode('@', $email)[0]);
        
        // Check if username already exists and append numbers if needed
        $base_username = $username;
        $counter = 1;
        
        while (true) {
            $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                // Username is available
                $check_stmt->close();
                break;
            }
            
            // Username exists, try with a number appended
            $username = $base_username . $counter;
            $counter++;
            $check_stmt->close();
        }
        
        error_log("Generated username: $username");
    }
    
    // Insert user
    error_log("Inserting user record");
    if ($username_exists) {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, username, password, role, status, phone, created_at) VALUES (?, ?, ?, ?, 'teacher', ?, ?, NOW())");
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("ssssss", $full_name, $email, $username, $hashed_password, $status, $phone);
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, status, phone, created_at) VALUES (?, ?, ?, 'teacher', ?, ?, NOW())");
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $status, $phone);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        throw new Exception('Error inserting user: ' . $stmt->error);
    }
    $user_id = $conn->insert_id;
    error_log("User inserted with ID: $user_id");
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
    
    if ($gender_exists && $dob_exists) {
        $query = "INSERT INTO teachers (user_id, employee_id, qualification, joining_date, experience, address, gender, date_of_birth, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        error_log("Using query with gender and DOB: $query");
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("isssssss", $user_id, $employee_id, $qualification, $joining_date, $experience, $address, $gender, $date_of_birth);
    } elseif ($gender_exists) {
        $query = "INSERT INTO teachers (user_id, employee_id, qualification, joining_date, experience, address, gender, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        error_log("Using query with gender: $query");
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("issssss", $user_id, $employee_id, $qualification, $joining_date, $experience, $address, $gender);
    } elseif ($dob_exists) {
        $query = "INSERT INTO teachers (user_id, employee_id, qualification, joining_date, experience, address, date_of_birth, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        error_log("Using query with DOB: $query");
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("issssss", $user_id, $employee_id, $qualification, $joining_date, $experience, $address, $date_of_birth);
    } else {
        $query = "INSERT INTO teachers (user_id, employee_id, qualification, joining_date, experience, address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        error_log("Using basic query: $query");
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("isssss", $user_id, $employee_id, $qualification, $joining_date, $experience, $address);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        throw new Exception('Error inserting teacher: ' . $stmt->error);
    }
    $teacher_id = $conn->insert_id;
    error_log("Teacher inserted with ID: $teacher_id");
    $stmt->close();
    
    // Log the activity
    error_log("Logging activity");
    $activity_type = 'teacher_create';
    $description = "Added new teacher: $full_name";
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
    
    // Commit transaction
    error_log("Committing transaction");
    $conn->commit();
    
    // Clean any output that might have been generated
    ob_end_clean();
    
    error_log("Teacher added successfully with ID: $teacher_id");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Teacher added successfully', 'teacher_id' => $teacher_id]);
} catch (Exception $e) {
    error_log("Error in save_new_teacher.php: " . $e->getMessage());
    // Rollback transaction on error if connection exists
    if (isset($conn) && $conn instanceof mysqli) {
        error_log("Rolling back transaction");
        $conn->rollback();
    }
    
    // Clean any output that might have been generated
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error adding teacher: ' . $e->getMessage()]);
}

// Close connection if it exists
if (isset($conn) && $conn instanceof mysqli) {
    error_log("Closing database connection");
    $conn->close();
}
?>
