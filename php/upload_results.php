<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if file was uploaded
if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
    $file = $_FILES['file'];
    
    // Check file type
    $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);
    if ($fileType != 'csv') {
        $_SESSION['message'] = "Only CSV files are allowed.";
        $_SESSION['message_type'] = "red";
        header("Location: ../admin_dashboard.php");
        exit();
    }
    
    // Process the file
    $fileName = $file['name'];
    $tempPath = $file['tmp_name'];
    $uploadDir = "../uploads/";
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $uploadPath = $uploadDir . time() . '_' . $fileName;
    
    // Move the file to the uploads directory
    if (move_uploaded_file($tempPath, $uploadPath)) {
        // Count the number of students in the CSV
        $studentCount = 0;
        if (($handle = fopen($uploadPath, "r")) !== FALSE) {
            // Skip header row
            fgetcsv($handle, 1000, ",");
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $studentCount++;
            }
            fclose($handle);
        }
        
        // Insert record into database
        $stmt = $conn->prepare("INSERT INTO ResultUploads (file_name, file_path, upload_date, student_count, status) VALUES (?, ?, NOW(), ?, 'Processing')");
        $stmt->bind_param("ssi", $fileName, $uploadPath, $studentCount);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "File uploaded successfully. Processing results for $studentCount students.";
            $_SESSION['message_type'] = "green";
        } else {
            $_SESSION['message'] = "Error: " . $stmt->error;
            $_SESSION['message_type'] = "red";
        }
        
        $stmt->close();
    } else {
        $_SESSION['message'] = "Failed to upload file.";
        $_SESSION['message_type'] = "red";
    }
} else {
    $_SESSION['message'] = "No file uploaded or an error occurred.";
    $_SESSION['message_type'] = "red";
}

header("Location: ../admin_dashboard.php");
exit();
?>
