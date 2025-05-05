<?php
// This script safely updates the database schema
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize error and success arrays
$errors = [];
$success = [];

// Function to safely execute SQL and handle errors
function executeSafely($conn, $sql, $description) {
    global $errors, $success;
    
    try {
        if ($conn->query($sql)) {
            $success[] = "$description - Success";
            return true;
        } else {
            $errors[] = "$description - Error: " . $conn->error;
            return false;
        }
    } catch (Exception $e) {
        $errors[] = "$description - Exception: " . $e->getMessage();
        return false;
    }
}

// Check if upload_id column exists in results table
$checkColumn = $conn->query("SHOW COLUMNS FROM `results` LIKE 'upload_id'");
if ($checkColumn->num_rows == 0) {
    // Column doesn't exist, add it
    executeSafely($conn, 
        "ALTER TABLE `results` ADD COLUMN `upload_id` int(11) NULL AFTER `remarks`",
        "Adding upload_id column"
    );
}

// Check if the foreign key constraint exists
$checkConstraint = $conn->query("
    SELECT * FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND CONSTRAINT_NAME = 'results_ibfk_4' 
    AND TABLE_NAME = 'results'
");

if ($checkConstraint->num_rows == 0) {
    // First check if result_uploads table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'result_uploads'");
    if ($checkTable->num_rows == 0) {
        // Create result_uploads table if it doesn't exist
        executeSafely($conn, "
            CREATE TABLE IF NOT EXISTS `result_uploads` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `file_name` varchar(255) NOT NULL,
              `description` text DEFAULT NULL,
              `status` enum('Draft','Published','Archived','Error') NOT NULL DEFAULT 'Draft',
              `uploaded_by` int(11) NOT NULL,
              `upload_date` datetime NOT NULL,
              `student_count` int(11) NOT NULL DEFAULT 0,
              `success_count` int(11) NOT NULL DEFAULT 0,
              `error_count` int(11) NOT NULL DEFAULT 0,
              `error_details` text DEFAULT NULL,
              `is_manual_entry` tinyint(1) NOT NULL DEFAULT 0,
              `exam_id` int(11) DEFAULT NULL,
              `class_id` int(11) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ", "Creating result_uploads table");
        
        // Add foreign keys to result_uploads table
        executeSafely($conn, "
            ALTER TABLE `result_uploads` 
            ADD CONSTRAINT `result_uploads_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
            ADD CONSTRAINT `result_uploads_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE SET NULL,
            ADD CONSTRAINT `result_uploads_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL
        ", "Adding foreign keys to result_uploads table");
        
        // Add indexes to result_uploads table
        executeSafely($conn, "
            ALTER TABLE `result_uploads` 
            ADD INDEX `uploaded_by` (`uploaded_by`),
            ADD INDEX `exam_id` (`exam_id`),
            ADD INDEX `class_id` (`class_id`),
            ADD INDEX `idx_result_uploads_status` (`status`),
            ADD INDEX `idx_result_uploads_date` (`upload_date`),
            ADD INDEX `idx_result_uploads_manual` (`is_manual_entry`)
        ", "Adding indexes to result_uploads table");
    }
    
    // Try to add the foreign key constraint
    executeSafely($conn, 
        "ALTER TABLE `results` ADD CONSTRAINT `results_ibfk_4` FOREIGN KEY (`upload_id`) REFERENCES `result_uploads` (`id`) ON DELETE SET NULL",
        "Adding foreign key constraint"
    );
}

// Check if remarks column exists
$checkRemarksColumn = $conn->query("SHOW COLUMNS FROM `results` LIKE 'remarks'");
if ($checkRemarksColumn->num_rows == 0) {
    // Column doesn't exist, add it
    executeSafely($conn, 
        "ALTER TABLE `results` ADD COLUMN `remarks` text NULL AFTER `gpa`",
        "Adding remarks column"
    );
}

// Create student_performance table if it doesn't exist
executeSafely($conn, "
    CREATE TABLE IF NOT EXISTS `student_performance` (
      `performance_id` int(11) NOT NULL AUTO_INCREMENT,
      `student_id` varchar(20) NOT NULL,
      `exam_id` int(11) NOT NULL,
      `average_marks` decimal(5,2) DEFAULT NULL,
      `gpa` decimal(3,2) DEFAULT NULL,
      `total_subjects` int(11) DEFAULT NULL,
      `subjects_passed` int(11) DEFAULT NULL,
      `rank` int(11) DEFAULT NULL,
      `remarks` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`performance_id`),
      UNIQUE KEY `student_exam_unique` (`student_id`,`exam_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
", "Creating student_performance table");

// Add foreign keys to student_performance table
executeSafely($conn, "
    ALTER TABLE `student_performance` 
    ADD CONSTRAINT `student_performance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
    ADD CONSTRAINT `student_performance_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE
", "Adding foreign keys to student_performance table");

// Add indexes to student_performance table
executeSafely($conn, "
    ALTER TABLE `student_performance` 
    ADD INDEX `idx_performance_gpa` (`gpa`),
    ADD INDEX `idx_performance_rank` (`rank`)
", "Adding indexes to student_performance table");

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Update | Result Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-4xl mx-auto">
            <h1 class="text-2xl font-bold mb-6 text-gray-800">Database Update Results</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-red-600 mb-2">Errors:</h2>
                    <ul class="list-disc pl-5 text-red-500">
                        <?php foreach ($errors as $error): ?>
                            <li class="mb-1"><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-green-600 mb-2">Successful Operations:</h2>
                    <ul class="list-disc pl-5 text-green-500">
                        <?php foreach ($success as $message): ?>
                            <li class="mb-1"><?php echo htmlspecialchars($message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="mt-6 flex justify-between">
                <a href="admin_dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Back to Dashboard
                </a>
                <a href="manage_results.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    Go to Manage Results
                </a>
            </div>
        </div>
    </div>
</body>
</html>
