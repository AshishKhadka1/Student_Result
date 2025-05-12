<?php
// Turn off error display but keep logging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

// Set JSON header
header('Content-Type: application/json');

session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
try {
    $conn = new mysqli('localhost', 'root', '', 'result_management');
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data with validation
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $roll_number = isset($_POST['roll_number']) ? trim($_POST['roll_number']) : '';
        $registration_number = isset($_POST['registration_number']) ? trim($_POST['registration_number']) : '';
        $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
        $batch_year = isset($_POST['batch_year']) ? trim($_POST['batch_year']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
        $parent_name = isset($_POST['parent_name']) ? trim($_POST['parent_name']) : '';
        $parent_phone = isset($_POST['parent_phone']) ? trim($_POST['parent_phone']) : '';
        $parent_email = isset($_POST['parent_email']) ? trim($_POST['parent_email']) : '';

        // Validate required fields
        if (empty($full_name) || empty($email) || empty($password) || empty($roll_number) || empty($class_id) || empty($batch_year)) {
            throw new Exception('Please fill all required fields');
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception('Email already exists');
        }
        $stmt->close();

        // Check if roll number already exists in the same class
        $stmt = $conn->prepare("SELECT s.student_id FROM students s WHERE s.roll_number = ? AND s.class_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("si", $roll_number, $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception('Roll number already exists in this class');
        }
        $stmt->close();

        // Begin transaction
        $conn->begin_transaction();

        // Generate a student ID (S001, S002, etc.)
        $stmt = $conn->prepare("SELECT MAX(SUBSTRING(student_id, 2)) as max_id FROM students");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $max_id = intval($row['max_id'] ?? 0);
        $next_id = $max_id + 1;
        $student_id = "S" . str_pad($next_id, 3, "0", STR_PAD_LEFT);
        $stmt->close();

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role, status, phone, address, created_at) VALUES (?, ?, ?, ?, 'student', ?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sssssss", $email, $hashed_password, $email, $full_name, $status, $phone, $address);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $user_id = $conn->insert_id;
        $stmt->close();

        // If registration_number is empty, use the student_id
        if (empty($registration_number)) {
            $registration_number = $student_id;
        }

        // Check if the students table has a gender column
        $result = $conn->query("SHOW COLUMNS FROM students LIKE 'gender'");
        $has_gender_column = $result->num_rows > 0;

        // Check if the students table has a date_of_birth column
        $result = $conn->query("SHOW COLUMNS FROM students LIKE 'date_of_birth'");
        $has_dob_column = $result->num_rows > 0;

        // Add date_of_birth to the SQL query if the column exists and the value is provided
        $date_of_birth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';

        // Dynamically build the SQL query based on available columns
        $sql = "INSERT INTO students (student_id, user_id, roll_number, registration_number, class_id, batch_year";
        $values = "(?, ?, ?, ?, ?, ?";
        $types = "sissis"; // string, int, string, string, int, string
        $params = [$student_id, $user_id, $roll_number, $registration_number, $class_id, $batch_year];

        if ($has_gender_column) {
            $sql .= ", gender";
            $values .= ", ?";
            $types .= "s";
            $params[] = $gender;
        }

        if ($has_dob_column && !empty($date_of_birth)) {
            $sql .= ", date_of_birth";
            $values .= ", ?";
            $types .= "s";
            $params[] = $date_of_birth;
        }

        // Add parent information if provided
        if (!empty($parent_name)) {
            $sql .= ", parent_name";
            $values .= ", ?";
            $types .= "s";
            $params[] = $parent_name;
        }

        if (!empty($parent_phone)) {
            $sql .= ", parent_phone";
            $values .= ", ?";
            $types .= "s";
            $params[] = $parent_phone;
        }

        if (!empty($parent_email)) {
            $sql .= ", parent_email";
            $values .= ", ?";
            $types .= "s";
            $params[] = $parent_email;
        }

        $sql .= ", created_at) VALUES " . $values . ", NOW())";

        // Insert into students table with dynamic query
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Dynamically bind parameters
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();

        // Commit transaction
        $conn->commit();

        // Clear any buffered output
        ob_clean();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Student added successfully', 
            'student_id' => $student_id
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->connect_errno == 0) {
            $conn->rollback();
        }
        
        // Clear any buffered output
        ob_clean();
        
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    // Clear any buffered output
    ob_clean();
    
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
exit();
?>
