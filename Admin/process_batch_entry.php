<?php
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

// Initialize variables
$errors = [];
$success_count = 0;
$error_count = 0;

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate subject_id
    if (empty($_POST['subject_id'])) {
        $errors[] = "Subject is required";
    } else {
        $subject_id = $conn->real_escape_string($_POST['subject_id']);
        
        // Verify subject exists
        $subjectCheck = $conn->query("SELECT subject_id FROM subjects WHERE subject_id = '$subject_id'");
        if ($subjectCheck->num_rows == 0) {
            $errors[] = "Invalid subject selected";
        }
    }
    
    // Validate exam_id
    if (empty($_POST['exam_id'])) {
        $errors[] = "Exam is required";
    } else {
        $exam_id = $conn->real_escape_string($_POST['exam_id']);
        
        // Verify exam exists
        $examCheck = $conn->query("SELECT exam_id FROM exams WHERE exam_id = '$exam_id'");
        if ($examCheck->num_rows == 0) {
            $errors[] = "Invalid exam selected";
        }
    }
    
    // Get class_id (optional)
    $class_id = null;
    if (!empty($_POST['class_id'])) {
        $class_id = $conn->real_escape_string($_POST['class_id']);
    }
    
    // Check if students array exists
    if (empty($_POST['students']) || !is_array($_POST['students'])) {
        $errors[] = "No student data submitted";
    }
    
    // If no errors, process the batch entry
    if (empty($errors)) {
        // Get current user ID for tracking who created/updated the results
        $user_id = $_SESSION['user_id'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // First check if result_uploads table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'result_uploads'");
            if ($tableCheck->num_rows == 0) {
                // Redirect to database update script
                $_SESSION['error_message'] = "Database schema needs to be updated. Please run the database update script first.";
                header("Location: database_update.php");
                exit();
            }
            
            // Verify that the user ID exists in the users table
            $userCheck = $conn->query("SELECT user_id FROM users WHERE user_id = '$user_id'");
            if ($userCheck->num_rows == 0) {
                throw new Exception("Invalid user ID. Please log out and log back in.");
            }
            
            // Create a record in result_uploads table for tracking
            $uploadQuery = "INSERT INTO result_uploads (
                file_name, description, status, uploaded_by, upload_date, 
                student_count, success_count, error_count, is_manual_entry, exam_id, class_id
            ) VALUES (
                'Batch Entry', 'Batch entry for subject ID $subject_id', 'Draft', 
                '$user_id', NOW(), 0, 0, 0, 1, '$exam_id', " . ($class_id ? "'$class_id'" : "NULL") . "
            )";
            
            if (!$conn->query($uploadQuery)) {
                throw new Exception("Error creating upload record: " . $conn->error);
            }
            
            $upload_id = $conn->insert_id;
            $student_count = 0;
            
            // Process each student's result
            foreach ($_POST['students'] as $student) {
                // Skip empty entries
                if (empty($student['student_id'])) {
                    continue;
                }
                
                $student_id = $conn->real_escape_string($student['student_id']);
                $theory_marks = isset($student['theory_marks']) ? (float)$student['theory_marks'] : 0;
                $practical_marks = isset($student['practical_marks']) ? (float)$student['practical_marks'] : 0;
                $remarks = isset($student['remarks']) ? $conn->real_escape_string($student['remarks']) : '';
                
                // Validate marks
                if ($theory_marks < 0 || $theory_marks > 100) {
                    throw new Exception("Theory marks for student ID $student_id must be between 0 and 100");
                }
                
                if ($practical_marks < 0 || $practical_marks > 100) {
                    throw new Exception("Practical marks for student ID $student_id must be between 0 and 100");
                }
                
                // Calculate total marks for grade calculation
                $total_marks = $theory_marks + $practical_marks;
                
                // Get grade and GPA based on total marks
                $gradeInfo = getGradeInfo($conn, $total_marks);
                $grade = $gradeInfo['grade'];
                $gpa = $gradeInfo['gpa'];
                
                // Get credit hours for the subject
                $creditQuery = $conn->query("SELECT credit_hours FROM subjects WHERE subject_id = '$subject_id'");
                $credit_hours = 1.0; // Default value
                if ($creditQuery && $creditQuery->num_rows > 0) {
                    $creditRow = $creditQuery->fetch_assoc();
                    $credit_hours = $creditRow['credit_hours'];
                }
                
                // Check if result already exists
                $checkQuery = "SELECT result_id FROM results 
                              WHERE student_id = '$student_id' 
                              AND subject_id = '$subject_id' 
                              AND exam_id = '$exam_id'";
                $checkResult = $conn->query($checkQuery);
                
                if ($checkResult->num_rows > 0) {
                    // Update existing result
                    $resultRow = $checkResult->fetch_assoc();
                    $result_id = $resultRow['result_id'];
                    
                    // Check if upload_id column exists
                    $columnCheck = $conn->query("SHOW COLUMNS FROM `results` LIKE 'upload_id'");
                    if ($columnCheck->num_rows > 0) {
                        $updateQuery = "UPDATE results SET 
                                      theory_marks = '$theory_marks',
                                      practical_marks = '$practical_marks',
                                      grade = '$grade',
                                      gpa = '$gpa',
                                      credit_hours = '$credit_hours',
                                      remarks = '$remarks',
                                      upload_id = '$upload_id',
                                      updated_by = '$user_id',
                                      updated_at = NOW()
                                      WHERE result_id = '$result_id'";
                    } else {
                        // No upload_id column, skip it
                        $updateQuery = "UPDATE results SET 
                                      theory_marks = '$theory_marks',
                                      practical_marks = '$practical_marks',
                                      grade = '$grade',
                                      gpa = '$gpa',
                                      credit_hours = '$credit_hours',
                                      remarks = '$remarks',
                                      updated_by = '$user_id',
                                      updated_at = NOW()
                                      WHERE result_id = '$result_id'";
                    }
                    
                    if ($conn->query($updateQuery)) {
                        $success_count++;
                    } else {
                        throw new Exception("Error updating result for student ID $student_id: " . $conn->error);
                    }
                } else {
                    // Insert new result
                    // Check if upload_id column exists
                    $columnCheck = $conn->query("SHOW COLUMNS FROM `results` LIKE 'upload_id'");
                    if ($columnCheck->num_rows > 0) {
                        $insertQuery = "INSERT INTO results (
                                      student_id, subject_id, exam_id, 
                                      theory_marks, practical_marks,
                                      grade, gpa, credit_hours, 
                                      remarks, upload_id, status, created_by, updated_by,
                                      created_at, updated_at
                                      ) VALUES (
                                      '$student_id', '$subject_id', '$exam_id',
                                      '$theory_marks', '$practical_marks',
                                      '$grade', '$gpa', '$credit_hours',
                                      '$remarks', '$upload_id', 'pending', '$user_id', '$user_id',
                                      NOW(), NOW()
                                      )";
                    } else {
                        // No upload_id column, skip it
                        $insertQuery = "INSERT INTO results (
                                      student_id, subject_id, exam_id, 
                                      theory_marks, practical_marks,
                                      grade, gpa, credit_hours, 
                                      remarks, status, created_by, updated_by,
                                      created_at, updated_at
                                      ) VALUES (
                                      '$student_id', '$subject_id', '$exam_id',
                                      '$theory_marks', '$practical_marks',
                                      '$grade', '$gpa', '$credit_hours',
                                      '$remarks', 'pending', '$user_id', '$user_id',
                                      NOW(), NOW()
                                      )";
                    }
                    
                    if ($conn->query($insertQuery)) {
                        $success_count++;
                    } else {
                        throw new Exception("Error inserting result for student ID $student_id: " . $conn->error);
                    }
                }
                
                $student_count++;
            }
            
            // Update the upload record with final counts
            $updateUploadQuery = "UPDATE result_uploads SET 
                                student_count = '$student_count', 
                                success_count = '$success_count', 
                                error_count = '0', 
                                status = 'Published' 
                                WHERE id = '$upload_id'";
            
            if (!$conn->query($updateUploadQuery)) {
                throw new Exception("Error updating upload record: " . $conn->error);
            }
            
            // Update student performance summary
            updateStudentPerformance($conn, $exam_id);
            
            // If we got here, commit the transaction
            $conn->commit();
            
            // Set success message
            $_SESSION['success_message'] = "Successfully processed $success_count student results.";
            
            // Redirect back to manage results page
            header("Location: manage_results.php?tab=batch");
            exit();
            
        } catch (Exception $e) {
            // An error occurred, rollback the transaction
            $conn->rollback();
            
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
            header("Location: manage_results.php?tab=batch");
            exit();
        }
    } else {
        // If there were errors, set error message and redirect back
        $_SESSION['error_message'] = "Error: " . implode(", ", $errors);
        header("Location: manage_results.php?tab=batch");
        exit();
    }
} else {
    // Not a POST request
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: manage_results.php");
    exit();
}

/**
 * Get grade and GPA information based on total marks
 * 
 * @param mysqli $conn Database connection
 * @param float $total_marks Total marks
 * @return array Grade and GPA information
 */
function getGradeInfo($conn, $total_marks) {
    // Query the grading_system table to get the appropriate grade and GPA
    $query = "SELECT grade, gpa FROM grading_system 
              WHERE $total_marks BETWEEN min_percentage AND max_percentage 
              LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        // Default fallback if no matching grade is found
        return ['grade' => 'F', 'gpa' => 0.0];
    }
}

/**
 * Update student performance summary for an exam
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 */
function updateStudentPerformance($conn, $exam_id) {
    // Check if student_performance table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'student_performance'");
    if ($tableCheck->num_rows == 0) {
        // Table doesn't exist, skip this step
        return;
    }
    
    // Get all students who have results for this exam
    $studentsQuery = "SELECT DISTINCT student_id FROM results WHERE exam_id = '$exam_id'";
    $studentsResult = $conn->query($studentsQuery);
    
    if ($studentsResult && $studentsResult->num_rows > 0) {
        while ($student = $studentsResult->fetch_assoc()) {
            $student_id = $student['student_id'];
            
            // Calculate average marks, GPA, and other metrics
            $metricsQuery = "SELECT 
                            AVG(theory_marks + practical_marks) as average_marks,
                            AVG(gpa) as average_gpa,
                            COUNT(*) as total_subjects,
                            SUM(CASE WHEN gpa > 0 THEN 1 ELSE 0 END) as subjects_passed
                            FROM results 
                            WHERE student_id = '$student_id' AND exam_id = '$exam_id'";
            
            $metricsResult = $conn->query($metricsQuery);
            
            if ($metricsResult && $metricsResult->num_rows > 0) {
                $metrics = $metricsResult->fetch_assoc();
                
                // Check if performance record already exists
                $checkQuery = "SELECT performance_id FROM student_performance 
                              WHERE student_id = '$student_id' AND exam_id = '$exam_id'";
                $checkResult = $conn->query($checkQuery);
                
                if ($checkResult->num_rows > 0) {
                    // Update existing record
                    $performanceRow = $checkResult->fetch_assoc();
                    $performance_id = $performanceRow['performance_id'];
                    
                    $updateQuery = "UPDATE student_performance SET 
                                  average_marks = '{$metrics['average_marks']}',
                                  gpa = '{$metrics['average_gpa']}',
                                  total_subjects = '{$metrics['total_subjects']}',
                                  subjects_passed = '{$metrics['subjects_passed']}',
                                  updated_at = NOW()
                                  WHERE performance_id = '$performance_id'";
                    
                    $conn->query($updateQuery);
                } else {
                    // Insert new record
                    $insertQuery = "INSERT INTO student_performance (
                                  student_id, exam_id, average_marks, gpa,
                                  total_subjects, subjects_passed, created_at, updated_at
                                  ) VALUES (
                                  '$student_id', '$exam_id', '{$metrics['average_marks']}', '{$metrics['average_gpa']}',
                                  '{$metrics['total_subjects']}', '{$metrics['subjects_passed']}', NOW(), NOW()
                                  )";
                    
                    $conn->query($insertQuery);
                }
            }
        }
        
        // Update rankings
        updateRankings($conn, $exam_id);
    }
}

/**
 * Update student rankings for an exam
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 */
function updateRankings($conn, $exam_id) {
    // Get all performances for this exam, ordered by GPA
    $rankQuery = "SELECT performance_id, student_id, gpa 
                 FROM student_performance 
                 WHERE exam_id = '$exam_id' 
                 ORDER BY gpa DESC, average_marks DESC";
    
    $rankResult = $conn->query($rankQuery);
    
    if ($rankResult && $rankResult->num_rows > 0) {
        $rank = 1;
        
        while ($performance = $rankResult->fetch_assoc()) {
            $performance_id = $performance['performance_id'];
            
            // Update rank
            $updateQuery = "UPDATE student_performance SET 
                          rank = '$rank' 
                          WHERE performance_id = '$performance_id'";
            
            $conn->query($updateQuery);
            
            $rank++;
        }
    }
}
?>
