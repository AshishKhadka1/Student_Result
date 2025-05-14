<?php
// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db_connetc.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_results.php?tab=manual");
    exit();
}

// Get form data from the manual_entry.php form
$studentId = $_POST['student_id'] ?? '';
$examId = $_POST['exam_id'] ?? '';
$subjectIds = $_POST['subject_id'] ?? [];
$theoryMarks = $_POST['theory_marks'] ?? [];
$practicalMarks = $_POST['practical_marks'] ?? [];
$remarks = $_POST['remarks'] ?? [];

// Basic validation
if (empty($studentId) || empty($examId) || empty($subjectIds)) {
    $_SESSION['error_message'] = "Please fill all required fields.";
    header("Location: manage_results.php?tab=manual");
    exit();
}

// Get class ID from the student record
$stmt = $conn->prepare("SELECT class_id FROM students WHERE student_id = ?");
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Student not found.";
    header("Location: manage_results.php?tab=manual");
    exit();
}
$classId = $result->fetch_assoc()['class_id'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // First, determine the correct table name by checking which one is referenced by the foreign key
    $fkQuery = "SELECT REFERENCED_TABLE_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'results' 
                AND CONSTRAINT_NAME = 'results_ibfk_4' 
                LIMIT 1";
    $fkResult = $conn->query($fkQuery);
    
    if ($fkResult && $fkResult->num_rows > 0) {
        $uploadsTable = $fkResult->fetch_assoc()['REFERENCED_TABLE_NAME'];
    } else {
        // If we can't determine from foreign key, check which table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'result_uploads'");
        if ($tableCheck->num_rows > 0) {
            $uploadsTable = 'result_uploads';
        } else {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'resultuploads'");
            if ($tableCheck->num_rows > 0) {
                $uploadsTable = 'resultuploads';
            } else {
                // If neither table exists, create result_uploads
                $conn->query("
                    CREATE TABLE IF NOT EXISTS `result_uploads` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `file_name` varchar(255) NOT NULL,
                      `description` text DEFAULT NULL,
                      `status` enum('Pending','Processing','Published','Failed') NOT NULL DEFAULT 'Pending',
                      `uploaded_by` int(11) NOT NULL,
                      `upload_date` datetime NOT NULL,
                      `exam_id` int(11) DEFAULT NULL,
                      `class_id` int(11) DEFAULT NULL,
                      `student_count` int(11) NOT NULL DEFAULT 0,
                      `success_count` int(11) NOT NULL DEFAULT 0,
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
                $uploadsTable = 'result_uploads';
            }
        }
    }
    
    // First, create a record in the uploads table for this manual entry
    $uploadDescription = "Manual entry for Student ID: $studentId, Exam ID: $examId";
    $userId = $_SESSION['user_id'];
    
    // Verify that the user ID exists in the users table
    $userCheckStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $userCheckStmt->bind_param("i", $userId);
    $userCheckStmt->execute();
    $userResult = $userCheckStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        throw new Exception("Invalid user ID. Please log in again.");
    }
    
    // Check if we already have a manual entry upload for today
    $checkUpload = $conn->prepare("SELECT id FROM $uploadsTable WHERE file_name = 'Manual Entry' AND DATE(upload_date) = CURDATE() AND uploaded_by = ? AND exam_id = ? LIMIT 1");
    $checkUpload->bind_param("ii", $userId, $examId);
    $checkUpload->execute();
    $uploadResult = $checkUpload->get_result();

    if ($uploadResult->num_rows > 0) {
        // Use existing upload record
        $uploadId = $uploadResult->fetch_assoc()['id'];
        
        // Update the description to include this student
        $updateDesc = $conn->prepare("UPDATE $uploadsTable SET description = CONCAT(description, ', Student ID: $studentId') WHERE id = ?");
        $updateDesc->bind_param("i", $uploadId);
        $updateDesc->execute();
    } else {
        // Check if the required columns exist in the uploads table
        $columnsCheck = $conn->query("SHOW COLUMNS FROM $uploadsTable LIKE 'exam_id'");
        $examIdExists = $columnsCheck->num_rows > 0;
        
        $columnsCheck = $conn->query("SHOW COLUMNS FROM $uploadsTable LIKE 'class_id'");
        $classIdExists = $columnsCheck->num_rows > 0;
        
        try {
            // Create new upload record
            if ($examIdExists && $classIdExists) {
                // If both columns exist, use them in the query
                $uploadStmt = $conn->prepare("INSERT INTO $uploadsTable 
                    (file_name, description, status, uploaded_by, upload_date, exam_id, class_id) 
                    VALUES ('Manual Entry', ?, 'Published', ?, NOW(), ?, ?)");
                $uploadStmt->bind_param("siis", $uploadDescription, $userId, $examId, $classId);
            } else {
                // Otherwise, use the original schema
                $uploadStmt = $conn->prepare("INSERT INTO $uploadsTable 
                    (file_name, description, status, uploaded_by, upload_date) 
                    VALUES ('Manual Entry', ?, 'Published', ?, NOW())");
                $uploadStmt->bind_param("si", $uploadDescription, $userId);
            }
            
            if (!$uploadStmt->execute()) {
                throw new Exception("Error creating upload record: " . $conn->error);
            }
            
            $uploadId = $conn->insert_id;
        } catch (Exception $e) {
            // If there's an error with the upload record, try a different approach
            // This is a fallback in case there are issues with the foreign key
            $conn->query("INSERT INTO $uploadsTable 
                (file_name, description, status, uploaded_by, upload_date, exam_id, class_id, student_count, success_count) 
                VALUES ('Manual Entry', '$uploadDescription', 'Published', $userId, NOW(), $examId, '$classId', 0, 0)");
            
            if ($conn->error) {
                throw new Exception("Error creating upload record: " . $conn->error);
            }
            
            $uploadId = $conn->insert_id;
        }
    }
    
    // Process each subject
    $successCount = 0;
    for ($i = 0; $i < count($subjectIds); $i++) {
        if (empty($subjectIds[$i])) continue;
        
        $subjectId = $subjectIds[$i];
        $theory = isset($theoryMarks[$i]) && is_numeric($theoryMarks[$i]) ? $theoryMarks[$i] : 0;
        $practical = isset($practicalMarks[$i]) && is_numeric($practicalMarks[$i]) ? $practicalMarks[$i] : 0;
        $remark = $remarks[$i] ?? '';
        
        // Calculate total and grade
        $totalMarks = $theory + $practical;
        
        // Determine grade based on percentage
        $percentage = ($totalMarks / 100) * 100; // Assuming total possible marks is 100
        $grade = '';
        $gpa = 0;
        
        if ($percentage >= 90) {
            $grade = 'A+';
            $gpa = 4.0;
        } elseif ($percentage >= 80) {
            $grade = 'A';
            $gpa = 3.7;
        } elseif ($percentage >= 70) {
            $grade = 'B+';
            $gpa = 3.3;
        } elseif ($percentage >= 60) {
            $grade = 'B';
            $gpa = 3.0;
        } elseif ($percentage >= 50) {
            $grade = 'C+';
            $gpa = 2.7;
        } elseif ($percentage >= 40) {
            $grade = 'C';
            $gpa = 2.3;
        } elseif ($percentage >= 33) {
            $grade = 'D';
            $gpa = 1.0;
        } else {
            $grade = 'F';
            $gpa = 0.0;
        }
        
        // Check if result already exists
        $checkStmt = $conn->prepare("SELECT result_id FROM results WHERE student_id = ? AND exam_id = ? AND subject_id = ?");
        $checkStmt->bind_param("sis", $studentId, $examId, $subjectId);
        $checkStmt->execute();
        $existingResult = $checkStmt->get_result();
        
        if ($existingResult->num_rows > 0) {
            // Update existing result
            $resultRow = $existingResult->fetch_assoc();
            $updateStmt = $conn->prepare("UPDATE results 
                SET theory_marks = ?, practical_marks = ?, grade = ?, gpa = ?, remarks = ?, upload_id = ?, updated_at = NOW() 
                WHERE result_id = ?");
            $updateStmt->bind_param("ddsdsis", $theory, $practical, $grade, $gpa, $remark, $uploadId, $resultRow['result_id']);
            $updateStmt->execute();
        } else {
            // Insert new result
            $insertStmt = $conn->prepare("INSERT INTO results 
                (student_id, exam_id, subject_id, theory_marks, practical_marks, grade, gpa, remarks, upload_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $insertStmt->bind_param("sisddsdsi", $studentId, $examId, $subjectId, $theory, $practical, $grade, $gpa, $remark, $uploadId);
            $insertStmt->execute();
        }
        
        $successCount++;
    }
    
    // Check if success_count column exists in the uploads table
    $columnsCheck = $conn->query("SHOW COLUMNS FROM $uploadsTable LIKE 'success_count'");
    $successCountExists = $columnsCheck->num_rows > 0;
    
    if ($successCountExists) {
        // Update the uploads record with success count
        $updateUploadStmt = $conn->prepare("UPDATE $uploadsTable 
            SET success_count = success_count + ?, student_count = student_count + 1 
            WHERE id = ?");
        $updateUploadStmt->bind_param("ii", $successCount, $uploadId);
        $updateUploadStmt->execute();
    } else {
        // Just update student_count if success_count doesn't exist
        $updateUploadStmt = $conn->prepare("UPDATE $uploadsTable 
            SET student_count = student_count + 1 
            WHERE id = ?");
        $updateUploadStmt->bind_param("i", $uploadId);
        $updateUploadStmt->execute();
    }
    
    // Log the activity
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) 
        VALUES (?, 'MANUAL_ENTRY', ?, NOW())");
    $details = "Added/updated $successCount results for Student ID: $studentId, Exam ID: $examId";
    $logStmt->bind_param("is", $userId, $details);
    $logStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Update student performance summary
    updateStudentPerformance($studentId, $examId, $conn);
    
    $_SESSION['success_message'] = "Results saved successfully! Added/updated $successCount subjects.";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

// Redirect back to the form
header("Location: manage_results.php?tab=manual");
exit();

// Function to update student performance summary
function updateStudentPerformance($student_id, $exam_id, $conn) {
    // Get all results for this student and exam
    $stmt = $conn->prepare("
        SELECT r.*, s.subject_name
        FROM results r
        JOIN subjects s ON r.subject_id = s.subject_id
        WHERE r.student_id = ? AND r.exam_id = ?
    ");
    $stmt->bind_param("si", $student_id, $exam_id);
    $stmt->execute();
    $results = $stmt->get_result();
    
    $total_marks = 0;
    $total_subjects = 0;
    $subjects_passed = 0;
    $total_gpa = 0;
    
    while ($row = $results->fetch_assoc()) {
        $total_marks += ($row['theory_marks'] + $row['practical_marks']);
        $total_gpa += $row['gpa'] ?? 0;
        $total_subjects++;
        
        if (($row['theory_marks'] + $row['practical_marks']) >= 33) {
            $subjects_passed++;
        }
    }
    
    // Calculate average marks and GPA
    $average_marks = $total_subjects > 0 ? $total_marks / $total_subjects : 0;
    $gpa = $total_subjects > 0 ? $total_gpa / $total_subjects : 0;
    
    // Check if student_performance table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'student_performance'")->num_rows > 0;
    if (!$tableExists) {
        // Create student_performance table
        $conn->query("
            CREATE TABLE IF NOT EXISTS `student_performance` (
              `performance_id` int(11) NOT NULL AUTO_INCREMENT,
              `student_id` varchar(50) NOT NULL,
              `exam_id` int(11) NOT NULL,
              `average_marks` decimal(5,2) DEFAULT NULL,
              `gpa` decimal(3,2) DEFAULT NULL,
              `total_subjects` int(11) DEFAULT NULL,
              `subjects_passed` int(11) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`performance_id`),
              UNIQUE KEY `student_exam` (`student_id`,`exam_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
    
    // Check if performance record exists
    $stmt = $conn->prepare("SELECT performance_id FROM student_performance WHERE student_id = ? AND exam_id = ?");
    $stmt->bind_param("si", $student_id, $exam_id);
    $stmt->execute();
    $performance = $stmt->get_result();
    
    if ($performance->num_rows > 0) {
        // Update existing performance record
        $performance_row = $performance->fetch_assoc();
        $stmt = $conn->prepare("
            UPDATE student_performance 
            SET average_marks = ?, gpa = ?, total_subjects = ?, subjects_passed = ?, updated_at = NOW() 
            WHERE performance_id = ?
        ");
        $stmt->bind_param("ddiii", $average_marks, $gpa, $total_subjects, $subjects_passed, $performance_row['performance_id']);
        $stmt->execute();
    } else {
        // Insert new performance record
        $stmt = $conn->prepare("
            INSERT INTO student_performance 
            (student_id, exam_id, average_marks, gpa, total_subjects, subjects_passed, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("siddii", $student_id, $exam_id, $average_marks, $gpa, $total_subjects, $subjects_passed);
        $stmt->execute();
    }
}
?>
