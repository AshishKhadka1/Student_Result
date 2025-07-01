<?php
session_start();
include '../includes/config.php';
include '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get teacher ID from the teachers table
$teacher_query = "SELECT teacher_id FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($teacher_query);
if (!$stmt) {
    $error_message = "Database error: " . $conn->error;
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $teacher = $result->fetch_assoc();
        $teacher_id = $teacher['teacher_id'];
    } else {
        $error_message = "Teacher record not found. Please contact the administrator.";
    }
    $stmt->close();
}

// Get class ID and exam ID from URL parameters
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;

// Check if required parameters are provided
$missing_params = false;
if (!$class_id || !$exam_id) {
    $missing_params = true;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['submit'])) {
        $error_message = "Missing required parameters. Please select a class and exam.";
    }
}

// Get available classes for this teacher
$classes = [];
if (empty($error_message)) {
    $classes_query = "SELECT DISTINCT c.class_id, c.class_name, c.section, c.academic_year
                     FROM classes c
                     JOIN teachersubjects ts ON c.class_id = ts.class_id
                     WHERE ts.teacher_id = ? AND ts.is_active = 1
                     ORDER BY c.class_name, c.section";
    
    $stmt = $conn->prepare($classes_query);
    if (!$stmt) {
        $error_message = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        $stmt->close();
    }
}

// Get available exams
$exams = [];
if (empty($error_message)) {
    $exams_query = "SELECT e.exam_id, e.exam_name, e.exam_type, e.academic_year, e.start_date, e.end_date
                   FROM exams e
                   WHERE e.is_active = 1
                   ORDER BY e.start_date DESC";
    
    $stmt = $conn->prepare($exams_query);
    if (!$stmt) {
        $error_message = "Database error: " . $conn->error;
    } else {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
        $stmt->close();
    }
}

// Get subjects assigned to this teacher for the selected class
$subjects = [];
if (!$missing_params && empty($error_message)) {
    $subjects_query = "SELECT s.subject_id, s.subject_name, s.subject_code, s.credit_hours,
                      s.full_marks_theory, s.full_marks_practical, 
                      s.pass_marks_theory, s.pass_marks_practical
                      FROM subjects s
                      JOIN teachersubjects ts ON s.subject_id = ts.subject_id
                      WHERE ts.teacher_id = ? AND ts.class_id = ? AND ts.is_active = 1
                      ORDER BY s.subject_name";
    
    $stmt = $conn->prepare($subjects_query);
    if (!$stmt) {
        $error_message = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("ii", $teacher_id, $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Apply marking scheme logic
            if ($row['full_marks_practical'] > 0) {
                // Both theory and practical: Theory = 75, Practical = 25
                $row['display_theory_marks'] = 75;
                $row['display_practical_marks'] = 25;
                $row['display_total_marks'] = 100;
                $row['display_pass_theory'] = 25; // 33% of 75
                $row['display_pass_practical'] = 8; // 33% of 25
                $row['has_practical'] = true;
            } else {
                // Only theory: Theory = 100, Practical = N/A
                $row['display_theory_marks'] = 100;
                $row['display_practical_marks'] = 0;
                $row['display_total_marks'] = 100;
                $row['display_pass_theory'] = 33; // 33% of 100
                $row['display_pass_practical'] = 0;
                $row['has_practical'] = false;
            }
            $subjects[] = $row;
        }
        $stmt->close();
        
        if (empty($subjects)) {
            $error_message = "You don't have any subjects assigned to this class. Please contact the administrator.";
        }
    }
}

// Get class details
$class = [];
if (!$missing_params && empty($error_message)) {
    $class_query = "SELECT class_name, section, academic_year
                   FROM classes
                   WHERE class_id = ?";
    $stmt = $conn->prepare($class_query);
    if (!$stmt) {
        $error_message = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $class_result = $stmt->get_result();
        $class = $class_result->fetch_assoc();
        $stmt->close();

        if (!$class) {
            $error_message = "Class not found.";
        }
    }
}

// Get exam details
$exam = [];
if (!$missing_params && empty($error_message)) {
    $exam_query = "SELECT exam_name, exam_type, academic_year, start_date, end_date
                  FROM exams
                  WHERE exam_id = ?";
    $stmt = $conn->prepare($exam_query);
    if (!$stmt) {
        $error_message = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $exam_result = $stmt->get_result();
        $exam = $exam_result->fetch_assoc();
        $stmt->close();

        if (!$exam) {
            $error_message = "Exam not found.";
        }
    }
}

// Get students and their results for all subjects
$students = [];
if (!$missing_params && empty($error_message)) {
    if ($student_id) {
        // Get specific student
        $students_query = "SELECT s.student_id, s.roll_number, s.registration_number, u.full_name
                          FROM students s
                          JOIN users u ON s.user_id = u.user_id
                          WHERE s.student_id = ? AND s.class_id = ?
                          ORDER BY s.roll_number";
        $stmt = $conn->prepare($students_query);
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("si", $student_id, $class_id);
            $stmt->execute();
            $students_result = $stmt->get_result();
            while ($student = $students_result->fetch_assoc()) {
                $students[] = $student;
            }
            $stmt->close();
        }
    } else {
        // Get all students in the class
        $students_query = "SELECT s.student_id, s.roll_number, s.registration_number, u.full_name
                          FROM students s
                          JOIN users u ON s.user_id = u.user_id
                          WHERE s.class_id = ?
                          ORDER BY s.roll_number";
        $stmt = $conn->prepare($students_query);
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $students_result = $stmt->get_result();
            while ($student = $students_result->fetch_assoc()) {
                $students[] = $student;
            }
            $stmt->close();
        }
    }
    
    // Get results for all students and subjects
    foreach ($students as &$student) {
        $student['subjects'] = [];
        foreach ($subjects as $subject) {
            $result_query = "SELECT result_id, theory_marks, practical_marks, total_marks, percentage, grade, gpa, remarks
                            FROM results
                            WHERE student_id = ? AND subject_id = ? AND exam_id = ?";
            $stmt = $conn->prepare($result_query);
            if ($stmt) {
                $stmt->bind_param("ssi", $student['student_id'], $subject['subject_id'], $exam_id);
                $stmt->execute();
                $result_data = $stmt->get_result();
                if ($result_data->num_rows > 0) {
                    $student['subjects'][$subject['subject_id']] = $result_data->fetch_assoc();
                } else {
                    $student['subjects'][$subject['subject_id']] = [
                        'result_id' => '',
                        'theory_marks' => '',
                        'practical_marks' => '',
                        'total_marks' => 0,
                        'percentage' => 0,
                        'grade' => '',
                        'gpa' => 0,
                        'remarks' => ''
                    ];
                }
                $stmt->close();
            }
        }
    }
}

// Get grading system
$grading_system = [];
$grade_query = "SELECT * FROM grading_system ORDER BY min_percentage DESC";
$grade_result = $conn->query($grade_query);
if ($grade_result && $grade_result->num_rows > 0) {
    while ($grade = $grade_result->fetch_assoc()) {
        $grading_system[] = $grade;
    }
} else {
    // Default grading system if table doesn't exist
    $grading_system = [
        ['grade' => 'A+', 'min_percentage' => 90, 'gpa' => 4.0],
        ['grade' => 'A', 'min_percentage' => 80, 'gpa' => 3.7],
        ['grade' => 'B+', 'min_percentage' => 70, 'gpa' => 3.3],
        ['grade' => 'B', 'min_percentage' => 60, 'gpa' => 3.0],
        ['grade' => 'C+', 'min_percentage' => 50, 'gpa' => 2.7],
        ['grade' => 'C', 'min_percentage' => 40, 'gpa' => 2.3],
        ['grade' => 'D', 'min_percentage' => 33, 'gpa' => 1.0],
        ['grade' => 'F', 'min_percentage' => 0, 'gpa' => 0.0]
    ];
}

// Handle form submission for updating results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results']) && !$missing_params && empty($error_message)) {
    // Temporarily disable the trigger to avoid conflicts
    $conn->query("SET @disable_trigger = 1");
    
    $conn->begin_transaction();
    try {
        $total_updated = 0;
        
        if (isset($_POST['marks']) && is_array($_POST['marks'])) {
            foreach ($_POST['marks'] as $student_id => $student_marks) {
                foreach ($student_marks as $subject_id => $marks) {
                    $theory_marks = isset($marks['theory']) && $marks['theory'] !== '' ? floatval($marks['theory']) : 0;
                    $practical_marks = isset($marks['practical']) && $marks['practical'] !== '' ? floatval($marks['practical']) : 0;
                    $result_id = isset($marks['result_id']) ? $marks['result_id'] : '';
                    
                    // Skip if no marks entered and no existing result
                    if (($theory_marks === 0 && $practical_marks === 0) && empty($result_id)) {
                        continue;
                    }
                    
                    // Get subject details for calculations
                    $subject_details = null;
                    foreach ($subjects as $subj) {
                        if ($subj['subject_id'] == $subject_id) {
                            $subject_details = $subj;
                            break;
                        }
                    }
                    
                    if (!$subject_details) continue;
                    
                    // For theory-only subjects, set practical marks to 0
                    if (!$subject_details['has_practical']) {
                        $practical_marks = 0;
                    }
                    
                    // Calculate total marks using new scheme (always out of 100)
                    $total_marks = $theory_marks + $practical_marks;
                    
                    // Calculate percentage (always out of 100)
                    $percentage = $total_marks; // Since total is already out of 100
                    
                    // Determine grade based on percentage
                    $grade = 'F';
                    $gpa = 0;
                    $remarks = '';
                    
                    // Check if student passed both theory and practical using display marks
                    $theory_pass = $theory_marks >= $subject_details['display_pass_theory'];
                    $practical_pass = true;
                    if ($subject_details['has_practical']) {
                        $practical_pass = $practical_marks >= $subject_details['display_pass_practical'];
                    }
                    
                    // Set grade based on grading system
                    if ($theory_pass && $practical_pass) {
                        foreach ($grading_system as $grade_item) {
                            if ($percentage >= $grade_item['min_percentage']) {
                                $grade = $grade_item['grade'];
                                $gpa = $grade_item['gpa'];
                                break;
                            }
                        }
                        $remarks = 'Pass';
                    } else {
                        $grade = 'F';
                        $gpa = 0.0;
                        $remarks = 'Fail';
                    }
                    
                    if (empty($result_id)) {
                        // Insert new result
                        $insert_query = "INSERT INTO results (student_id, subject_id, exam_id, theory_marks, practical_marks, 
                                        total_marks, percentage, grade, gpa, remarks, created_by, created_at, updated_at)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                        $stmt = $conn->prepare($insert_query);
                        if (!$stmt) {
                            throw new Exception("Database error: " . $conn->error);
                        }
                        $stmt->bind_param("ssiiddddssi", $student_id, $subject_id, $exam_id, $theory_marks, $practical_marks, 
                                        $total_marks, $percentage, $grade, $gpa, $remarks, $user_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to insert result: " . $stmt->error);
                        }
                        $stmt->close();
                        $total_updated++;
                    } else {
                        // Update existing result
                        $update_query = "UPDATE results SET theory_marks = ?, practical_marks = ?, total_marks = ?, 
                                       percentage = ?, grade = ?, gpa = ?, remarks = ?, updated_by = ?, updated_at = NOW()
                                       WHERE result_id = ?";
                        $stmt = $conn->prepare($update_query);
                        if (!$stmt) {
                            throw new Exception("Database error: " . $conn->error);
                        }
                        $stmt->bind_param("ddddssdii", $theory_marks, $practical_marks, $total_marks, 
                                        $percentage, $grade, $gpa, $remarks, $user_id, $result_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update result: " . $stmt->error);
                        }
                        $stmt->close();
                        $total_updated++;
                    }
                }
            }
        }
        
        // Manually update student performance after all results are saved
        updateStudentPerformanceManual($conn, $exam_id);
        
        // Commit transaction
        $conn->commit();
        
        // Re-enable trigger
        $conn->query("SET @disable_trigger = NULL");
        
        // Refresh student data after update
        foreach ($students as &$student) {
            $student['subjects'] = [];
            foreach ($subjects as $subject) {
                $result_query = "SELECT result_id, theory_marks, practical_marks, total_marks, percentage, grade, gpa, remarks
                                FROM results
                                WHERE student_id = ? AND subject_id = ? AND exam_id = ?";
                $stmt = $conn->prepare($result_query);
                if ($stmt) {
                    $stmt->bind_param("ssi", $student['student_id'], $subject['subject_id'], $exam_id);
                    $stmt->execute();
                    $result_data = $stmt->get_result();
                    if ($result_data->num_rows > 0) {
                        $student['subjects'][$subject['subject_id']] = $result_data->fetch_assoc();
                    } else {
                        $student['subjects'][$subject['subject_id']] = [
                            'result_id' => '',
                            'theory_marks' => '',
                            'practical_marks' => '',
                            'total_marks' => 0,
                            'percentage' => 0,
                            'grade' => '',
                            'gpa' => 0,
                            'remarks' => ''
                        ];
                    }
                    $stmt->close();
                }
            }
        }
        
        $success_message = "Results updated successfully! Updated $total_updated records.";
        
    } catch (Exception $e) {
        $conn->rollback();
        // Re-enable trigger
        $conn->query("SET @disable_trigger = NULL");
        error_log("Error saving results: " . $e->getMessage());
        $error_message = "Error saving results: " . $e->getMessage();
    }
}

// Function to manually update student performance
function updateStudentPerformanceManual($conn, $exam_id) {
    // Check if student_performance table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'student_performance'");
    if ($tableCheck->num_rows == 0) {
        return; // Table doesn't exist, skip
    }
    
    // Get all students who have results for this exam
    $studentsQuery = "SELECT DISTINCT student_id FROM results WHERE exam_id = ?";
    $stmt = $conn->prepare($studentsQuery);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $studentsResult = $stmt->get_result();
    
    while ($student = $studentsResult->fetch_assoc()) {
        $student_id = $student['student_id'];
        
        // Calculate metrics for this student
        $metricsQuery = "SELECT 
                        AVG(theory_marks + practical_marks) as average_marks,
                        AVG(gpa) as average_gpa,
                        COUNT(*) as total_subjects,
                        SUM(CASE WHEN gpa > 0 THEN 1 ELSE 0 END) as subjects_passed
                        FROM results 
                        WHERE student_id = ? AND exam_id = ?";
        
        $metricsStmt = $conn->prepare($metricsQuery);
        $metricsStmt->bind_param("si", $student_id, $exam_id);
        $metricsStmt->execute();
        $metricsResult = $metricsStmt->get_result();
        
        if ($metricsResult->num_rows > 0) {
            $metrics = $metricsResult->fetch_assoc();
            
            // Check if performance record exists
            $checkQuery = "SELECT performance_id FROM student_performance 
                          WHERE student_id = ? AND exam_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("si", $student_id, $exam_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                // Update existing record
                $performanceRow = $checkResult->fetch_assoc();
                $updateQuery = "UPDATE student_performance SET 
                              average_marks = ?, gpa = ?, total_subjects = ?, subjects_passed = ?, updated_at = NOW()
                              WHERE performance_id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ddiii", $metrics['average_marks'], $metrics['average_gpa'], 
                                      $metrics['total_subjects'], $metrics['subjects_passed'], 
                                      $performanceRow['performance_id']);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                // Insert new record
                $insertQuery = "INSERT INTO student_performance 
                              (student_id, exam_id, average_marks, gpa, total_subjects, subjects_passed, created_at, updated_at) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("siddii", $student_id, $exam_id, $metrics['average_marks'], 
                                      $metrics['average_gpa'], $metrics['total_subjects'], $metrics['subjects_passed']);
                $insertStmt->execute();
                $insertStmt->close();
            }
            $checkStmt->close();
        }
        $metricsStmt->close();
    }
    $stmt->close();
}

// Calculate overall statistics for each student
if (!empty($students)) {
    foreach ($students as &$student) {
        $total_subjects = count($subjects);
        $total_marks = 0;
        $total_full_marks = 0;
        $total_gpa = 0;
        $subjects_with_results = 0;
        $passed_subjects = 0;
        
        foreach ($subjects as $subject) {
            $subject_result = $student['subjects'][$subject['subject_id']];
            if (!empty($subject_result['total_marks'])) {
                $total_marks += $subject_result['total_marks'];
                $total_gpa += $subject_result['gpa'];
                $subjects_with_results++;
                
                if ($subject_result['remarks'] === 'Pass') {
                    $passed_subjects++;
                }
            }
            $total_full_marks += $subject['display_total_marks']; // Always 100 per subject
        }
        
        $student['overall'] = [
            'total_marks' => $total_marks,
            'total_full_marks' => $total_full_marks,
            'percentage' => $total_full_marks > 0 ? ($total_marks / $total_full_marks) * 100 : 0,
            'average_gpa' => $subjects_with_results > 0 ? $total_gpa / $subjects_with_results : 0,
            'subjects_passed' => $passed_subjects,
            'total_subjects' => $total_subjects
        ];
    }
}

// Get school settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$prepared_by = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'System Administrator';
$issue_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Results - Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .grade-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
            text-align: center;
            min-width: 2rem;
        }
        
        .grade-A-plus { background-color: #10B981; color: white; }
        .grade-A { background-color: #34D399; color: white; }
        .grade-B-plus { background-color: #3B82F6; color: white; }
        .grade-B { background-color: #60A5FA; color: white; }
        .grade-C-plus { background-color: #F59E0B; color: white; }
        .grade-C { background-color: #FBBF24; color: white; }
        .grade-D { background-color: #F97316; color: white; }
        .grade-F { background-color: #EF4444; color: white; }
        
        .marks-input {
            width: 80px;
            padding: 8px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .marks-input:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        
        .marks-input.modified {
            background-color: #FEF3C7;
            border-color: #F59E0B;
        }
        
        .marks-input.invalid {
            border-color: #EF4444;
            background-color: #FEF2F2;
        }
        
        .student-row:hover {
            background-color: #F9FAFB;
        }
        
        .student-row.editing {
            background-color: #EFF6FF;
            border-left: 4px solid #3B82F6;
        }
        
        .save-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-radius: 50%;
            border-top: 3px solid #3B82F6;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/teacher_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <?php include 'includes/teacher_topbar.php'; ?>

            <!-- Main Content Area -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Header -->
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Edit Student Results</h1>
                                <p class="text-gray-600">Manage and update student examination results</p>
                            </div>
                            <div class="flex space-x-3">
                                <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to Grade Sheets
                                </a>
                            </div>
                        </div>

                        <!-- Save Indicator -->
                        <div id="save-indicator" class="save-indicator">
                            <i class="fas fa-check mr-2"></i> Changes saved successfully!
                        </div>

                        <!-- Success/Error Messages -->
                        <?php if (!empty($success_message)): ?>
                        <div id="success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 fade-in" role="alert">
                            <div class="flex items-center">
                                <div class="py-1"><i class="fas fa-check-circle text-green-500 mr-3"></i></div>
                                <div>
                                    <p class="font-bold">Success!</p>
                                    <p><?php echo htmlspecialchars($success_message); ?></p>
                                </div>
                                <button type="button" class="ml-auto" onclick="document.getElementById('success-alert').style.display='none'">
                                    <i class="fas fa-times text-green-500"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                        <div id="error-alert" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 fade-in" role="alert">
                            <div class="flex items-center">
                                <div class="py-1"><i class="fas fa-exclamation-circle text-red-500 mr-3"></i></div>
                                <div>
                                    <p class="font-bold">Error!</p>
                                    <p><?php echo htmlspecialchars($error_message); ?></p>
                                </div>
                                <button type="button" class="ml-auto" onclick="document.getElementById('error-alert').style.display='none'">
                                    <i class="fas fa-times text-red-500"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($missing_params): ?>
                        <!-- Selection Form -->
                        <div class="bg-white shadow rounded-lg p-6 mb-8">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">
                                <i class="fas fa-clipboard-list text-blue-500 mr-2"></i> Select Class and Exam
                            </h2>
                            
                            <form action="edit_results.php" method="GET" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="class_id" class="block text-sm font-medium text-gray-700 mb-2">Class</label>
                                        <select id="class_id" name="class_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                            <option value="">Select Class</option>
                                            <?php foreach ($classes as $cls): ?>
                                            <option value="<?php echo $cls['class_id']; ?>">
                                                <?php echo htmlspecialchars($cls['class_name']); ?> <?php echo htmlspecialchars($cls['section']); ?> (<?php echo htmlspecialchars($cls['academic_year']); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-2">Exam</label>
                                        <select id="exam_id" name="exam_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                            <option value="">Select Exam</option>
                                            <?php foreach ($exams as $ex): ?>
                                            <option value="<?php echo $ex['exam_id']; ?>">
                                                <?php echo htmlspecialchars($ex['exam_name']); ?> (<?php echo htmlspecialchars($ex['academic_year']); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" name="submit" value="1" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-search mr-2"></i> Load Results
                                    </button>
                                </div>
                            </form>

                            <!-- Marking Scheme Information -->
                            <div class="mt-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 text-white">
                                <h3 class="text-lg font-bold mb-4">
                                    <i class="fas fa-info-circle mr-2"></i> Marking Scheme
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                                        <h4 class="font-semibold mb-2">Theory + Practical Subjects</h4>
                                        <ul class="text-sm space-y-1">
                                            <li>• Theory Marks: 75 (Pass: 25)</li>
                                            <li>• Practical Marks: 25 (Pass: 8)</li>
                                            <li>• Total Marks: 100</li>
                                        </ul>
                                    </div>
                                    <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                                        <h4 class="font-semibold mb-2">Theory Only Subjects</h4>
                                        <ul class="text-sm space-y-1">
                                            <li>• Theory Marks: 100 (Pass: 33)</li>
                                            <li>• Practical Marks: N/A</li>
                                            <li>• Total Marks: 100</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php else: ?>
                        <!-- Class and Exam Information -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-4 text-white">
                                    <h3 class="text-sm font-medium opacity-80 mb-1">Class</h3>
                                    <p class="text-xl font-bold mb-1">
                                        <?php echo isset($class['class_name']) ? htmlspecialchars($class['class_name']) : 'N/A'; ?>
                                        <?php echo isset($class['section']) ? ' - ' . htmlspecialchars($class['section']) : ''; ?>
                                    </p>
                                    <p class="text-sm opacity-90">
                                        Academic Year: <?php echo isset($class['academic_year']) ? htmlspecialchars($class['academic_year']) : 'N/A'; ?>
                                    </p>
                                </div>
                                
                                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-4 text-white">
                                    <h3 class="text-sm font-medium opacity-80 mb-1">Exam</h3>
                                    <p class="text-xl font-bold mb-1"><?php echo isset($exam['exam_name']) ? htmlspecialchars($exam['exam_name']) : 'N/A'; ?></p>
                                    <p class="text-sm opacity-90">
                                        <?php echo isset($exam['exam_type']) ? htmlspecialchars($exam['exam_type']) : ''; ?>
                                    </p>
                                </div>
                                
                                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-4 text-white">
                                    <h3 class="text-sm font-medium opacity-80 mb-1">Statistics</h3>
                                    <p class="text-xl font-bold mb-1"><?php echo count($students); ?> Students</p>
                                    <p class="text-sm opacity-90"><?php echo count($subjects); ?> Subjects</p>
                                </div>
                            </div>
                        </div>

                        <!-- Subject Information -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Your Assigned Subjects</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($subjects as $subject): ?>
                                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <h5 class="font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($subject['subject_name']); ?></h5>
                                    <p class="text-sm text-gray-600 mb-2">Code: <?php echo htmlspecialchars($subject['subject_code']); ?></p>
                                    <div class="text-xs text-gray-700 space-y-1">
                                        <div class="flex justify-between">
                                            <span>Theory:</span>
                                            <span class="font-semibold text-blue-600"><?php echo $subject['display_theory_marks']; ?> marks</span>
                                        </div>
                                        <?php if ($subject['has_practical']): ?>
                                        <div class="flex justify-between">
                                            <span>Practical:</span>
                                            <span class="font-semibold text-green-600"><?php echo $subject['display_practical_marks']; ?> marks</span>
                                        </div>
                                        <?php else: ?>
                                        <div class="flex justify-between">
                                            <span>Practical:</span>
                                            <span class="font-semibold text-gray-400">N/A</span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="flex justify-between border-t pt-1">
                                            <span>Total:</span>
                                            <span class="font-bold text-purple-600"><?php echo $subject['display_total_marks']; ?> marks</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if (empty($students)): ?>
                        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded">
                            <p><i class="fas fa-exclamation-triangle mr-2"></i> No students found in this class.</p>
                        </div>
                        <?php else: ?>

                        <!-- Results Entry Form -->
                        <form method="POST" action="" id="results-form">
                            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                            
                            <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">
                                                <i class="fas fa-edit text-blue-500 mr-2"></i> Edit Student Results
                                            </h2>
                                            <p class="text-sm text-gray-600 mt-1">Enter marks according to the marking scheme. Modified cells are highlighted.</p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button type="button" onclick="clearAllMarks()" class="inline-flex items-center px-3 py-2 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">
                                                <i class="fas fa-eraser mr-1"></i> Clear All
                                            </button>
                                            <span id="modified-count" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium bg-yellow-100 text-yellow-800">
                                                0 changes made
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Student
                                                </th>
                                                <?php foreach ($subjects as $subject): ?>
                                                <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-l border-gray-200">
                                                    <div class="space-y-1">
                                                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                                                        <div class="text-xs text-blue-600">
                                                            T(<?php echo $subject['display_theory_marks']; ?>)
                                                            <?php if ($subject['has_practical']): ?>
                                                            + P(<?php echo $subject['display_practical_marks']; ?>)
                                                            <?php else: ?>
                                                            + P(N/A)
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </th>
                                                <?php endforeach; ?>
                                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-l border-gray-200">
                                                    Overall
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($students as $student_index => $student): ?>
                                            <tr class="student-row" id="student-row-<?php echo $student_index; ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-10 w-10">
                                                            <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-sm">
                                                                <?php echo htmlspecialchars($student['roll_number']); ?>
                                                            </div>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                                            <div class="text-sm text-gray-500">Roll: <?php echo htmlspecialchars($student['roll_number']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <?php foreach ($subjects as $subject_index => $subject): ?>
                                                <?php $subject_result = $student['subjects'][$subject['subject_id']]; ?>
                                                <td class="px-3 py-4 text-center border-l border-gray-200">
                                                    <input type="hidden" name="marks[<?php echo $student['student_id']; ?>][<?php echo $subject['subject_id']; ?>][result_id]" 
                                                           value="<?php echo htmlspecialchars($subject_result['result_id']); ?>">
                                                    
                                                    <div class="space-y-2">
                                                        <!-- Theory Marks -->
                                                        <div>
                                                            <label class="block text-xs text-gray-500 mb-1">Theory</label>
                                                            <input type="number" 
                                                                   name="marks[<?php echo $student['student_id']; ?>][<?php echo $subject['subject_id']; ?>][theory]" 
                                                                   value="<?php echo $subject_result['theory_marks']; ?>" 
                                                                   min="0" max="<?php echo $subject['display_theory_marks']; ?>" step="0.01"
                                                                   class="marks-input"
                                                                   data-student="<?php echo $student_index; ?>" 
                                                                   data-subject="<?php echo $subject_index; ?>" 
                                                                   data-subject-id="<?php echo $subject['subject_id']; ?>"
                                                                   data-student-id="<?php echo $student['student_id']; ?>"
                                                                   data-type="theory"
                                                                   data-max-marks="<?php echo $subject['display_theory_marks']; ?>"
                                                                   data-pass-marks="<?php echo $subject['display_pass_theory']; ?>"
                                                                   data-has-practical="<?php echo $subject['has_practical'] ? 'true' : 'false'; ?>"
                                                                   onchange="updateMarks(this)" 
                                                                   oninput="markAsModified(this)"
                                                                   onfocus="highlightRow(<?php echo $student_index; ?>)"
                                                                   placeholder="0">
                                                        </div>

                                                        <!-- Practical Marks -->
                                                        <?php if ($subject['has_practical']): ?>
                                                        <div>
                                                            <label class="block text-xs text-gray-500 mb-1">Practical</label>
                                                            <input type="number" 
                                                                   name="marks[<?php echo $student['student_id']; ?>][<?php echo $subject['subject_id']; ?>][practical]" 
                                                                   value="<?php echo $subject_result['practical_marks']; ?>" 
                                                                   min="0" max="<?php echo $subject['display_practical_marks']; ?>" step="0.01"
                                                                   class="marks-input"
                                                                   data-student="<?php echo $student_index; ?>" 
                                                                   data-subject="<?php echo $subject_index; ?>" 
                                                                   data-subject-id="<?php echo $subject['subject_id']; ?>"
                                                                   data-student-id="<?php echo $student['student_id']; ?>"
                                                                   data-type="practical"
                                                                   data-max-marks="<?php echo $subject['display_practical_marks']; ?>"
                                                                   data-pass-marks="<?php echo $subject['display_pass_practical']; ?>"
                                                                   data-has-practical="true"
                                                                   onchange="updateMarks(this)" 
                                                                   oninput="markAsModified(this)"
                                                                   onfocus="highlightRow(<?php echo $student_index; ?>)"
                                                                   placeholder="0">
                                                        </div>
                                                        <?php else: ?>
                                                        <div>
                                                            <label class="block text-xs text-gray-500 mb-1">Practical</label>
                                                            <div class="marks-input bg-gray-100 text-gray-400 cursor-not-allowed flex items-center justify-center">
                                                                N/A
                                                            </div>
                                                            <input type="hidden" name="marks[<?php echo $student['student_id']; ?>][<?php echo $subject['subject_id']; ?>][practical]" value="0">
                                                        </div>
                                                        <?php endif; ?>

                                                        <!-- Grade and Status -->
                                                        <div class="mt-2">
                                                            <span id="grade-<?php echo $student_index; ?>-<?php echo $subject_index; ?>" 
                                                                  class="grade-badge <?php 
                                                                      if (!empty($subject_result['grade'])) {
                                                                          $grade = str_replace('+', '-plus', $subject_result['grade']);
                                                                          echo 'grade-' . $grade;
                                                                      } else {
                                                                          echo 'bg-gray-200 text-gray-800';
                                                                      }
                                                                  ?>">
                                                                <?php echo $subject_result['grade'] ?: 'N/A'; ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <span id="status-<?php echo $student_index; ?>-<?php echo $subject_index; ?>" 
                                                                  class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                                      if (!empty($subject_result['remarks'])) {
                                                                          echo ($subject_result['remarks'] == 'Pass') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                                                      } else {
                                                                          echo 'bg-gray-100 text-gray-800';
                                                                      }
                                                                  ?>">
                                                                <?php echo $subject_result['remarks'] ?: 'N/A'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <?php endforeach; ?>

                                                <td class="px-6 py-4 text-center border-l border-gray-200">
                                                    <div id="overall-<?php echo $student_index; ?>" class="space-y-1">
                                                        <div class="text-lg font-bold text-blue-600">
                                                            <?php echo number_format($student['overall']['percentage'], 1); ?>%
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            GPA: <?php echo number_format($student['overall']['average_gpa'], 2); ?>
                                                        </div>
                                                        <div class="text-sm">
                                                            <span class="<?php echo $student['overall']['subjects_passed'] == $student['overall']['total_subjects'] ? 'text-green-600' : 'text-red-600'; ?>">
                                                                <?php echo $student['overall']['subjects_passed']; ?>/<?php echo $student['overall']['total_subjects']; ?> Pass
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                                    <div class="flex justify-between items-center">
                                        <div class="text-sm text-gray-600">
                                            <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                                            Theory+Practical = 75+25, Theory only = 100, Practical N/A
                                        </div>
                                        
                                        <div class="flex space-x-3">
                                            <button type="button" onclick="validateAllMarks()" class="inline-flex items-center px-4 py-2 border border-blue-300 rounded-md text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">
                                                <i class="fas fa-check-double mr-2"></i> Validate All
                                            </button>
                                            
                                            <button type="submit" name="save_results" id="save-button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                <i class="fas fa-save mr-2"></i> Save All Results
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Global variables
        let modifiedCells = new Set();
        let subjectsData = <?php echo json_encode($subjects); ?>;
        let gradingSystem = <?php echo json_encode($grading_system); ?>;

        // Highlight the row being edited
        function highlightRow(index) {
            document.querySelectorAll('.student-row').forEach(row => {
                row.classList.remove('editing');
            });
            const row = document.getElementById('student-row-' + index);
            if (row) {
                row.classList.add('editing');
            }
        }

        // Mark cell as modified
        function markAsModified(input) {
            input.classList.add('modified');
            modifiedCells.add(input);
            updateModifiedCount();
        }

        // Update modified count
        function updateModifiedCount() {
            const countElement = document.getElementById('modified-count');
            if (countElement) {
                countElement.textContent = modifiedCells.size + ' changes made';
            }
        }

        // Update marks and calculate grades in real-time
        function updateMarks(input) {
            const studentIndex = input.dataset.student;
            const subjectIndex = input.dataset.subject;
            const studentId = input.dataset.studentId;
            const subjectId = input.dataset.subjectId;
            const type = input.dataset.type;
            const value = parseFloat(input.value) || 0;
            const maxMarks = parseFloat(input.dataset.maxMarks);
            const hasPractical = input.dataset.hasPractical === 'true';

            // Validate input
            if (value < 0) {
                input.value = 0;
                return;
            } else if (value > maxMarks) {
                input.value = maxMarks;
                input.classList.add('invalid');
                setTimeout(() => {
                    input.classList.remove('invalid');
                }, 2000);
                return;
            }

            // Get theory and practical marks for this subject
            const theoryInput = document.querySelector(`input[data-student="${studentIndex}"][data-subject="${subjectIndex}"][data-type="theory"]`);
            const practicalInput = document.querySelector(`input[data-student="${studentIndex}"][data-subject="${subjectIndex}"][data-type="practical"]`);

            const theoryMarks = theoryInput ? (parseFloat(theoryInput.value) || 0) : 0;
            const practicalMarks = hasPractical && practicalInput ? (parseFloat(practicalInput.value) || 0) : 0;

            // Find subject details
            const subject = subjectsData.find(s => s.subject_id == subjectId);
            if (!subject) return;

            // Calculate total marks (always out of 100)
            const totalMarks = theoryMarks + practicalMarks;
            const percentage = totalMarks;

            // Check if student passed using display marks
            const theoryPass = theoryMarks >= subject.display_pass_theory;
            const practicalPass = subject.has_practical ? 
                practicalMarks >= subject.display_pass_practical : true;
            const passed = theoryPass && practicalPass;

            // Determine grade
            let grade = 'F';
            let gpa = 0;

            if (passed) {
                for (const gradeItem of gradingSystem) {
                    if (percentage >= gradeItem.min_percentage) {
                        grade = gradeItem.grade;
                        gpa = gradeItem.gpa;
                        break;
                    }
                }
            }

            // Update grade display
            const gradeElement = document.getElementById(`grade-${studentIndex}-${subjectIndex}`);
            const statusElement = document.getElementById(`status-${studentIndex}-${subjectIndex}`);

            if (gradeElement) {
                gradeElement.className = 'grade-badge';
                gradeElement.classList.add('grade-' + grade.replace('+', '-plus'));
                gradeElement.textContent = grade;
            }

            if (statusElement) {
                statusElement.className = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium';
                if (passed) {
                    statusElement.classList.add('bg-green-100', 'text-green-800');
                    statusElement.textContent = 'Pass';
                } else {
                    statusElement.classList.add('bg-red-100', 'text-red-800');
                    statusElement.textContent = 'Fail';
                }
            }

            // Update overall performance
            updateOverallPerformance(studentIndex);
        }

        // Update overall performance for a student
        function updateOverallPerformance(studentIndex) {
            let totalMarks = 0;
            let totalSubjects = 0;
            let totalGPA = 0;
            let passedSubjects = 0;
            let subjectsWithMarks = 0;

            subjectsData.forEach((subject, subjectIndex) => {
                const theoryInput = document.querySelector(`input[data-student="${studentIndex}"][data-subject="${subjectIndex}"][data-type="theory"]`);
                const practicalInput = document.querySelector(`input[data-student="${studentIndex}"][data-subject="${subjectIndex}"][data-type="practical"]`);

                const theoryMarks = theoryInput ? (parseFloat(theoryInput.value) || 0) : 0;
                const practicalMarks = subject.has_practical && practicalInput ? (parseFloat(practicalInput.value) || 0) : 0;
                const subjectTotal = theoryMarks + practicalMarks;

                if (subjectTotal > 0) {
                    totalMarks += subjectTotal;
                    subjectsWithMarks++;

                    const percentage = subjectTotal;
                    const theoryPass = theoryMarks >= subject.display_pass_theory;
                    const practicalPass = subject.has_practical ? 
                        practicalMarks >= subject.display_pass_practical : true;

                    if (theoryPass && practicalPass) {
                        for (const gradeItem of gradingSystem) {
                            if (percentage >= gradeItem.min_percentage) {
                                totalGPA += gradeItem.gpa;
                                passedSubjects++;
                                break;
                            }
                        }
                    }
                }
                totalSubjects++;
            });

            const overallPercentage = subjectsWithMarks > 0 ? (totalMarks / (subjectsWithMarks * 100)) * 100 : 0;
            const averageGPA = subjectsWithMarks > 0 ? totalGPA / subjectsWithMarks : 0;

            // Update overall display
            const overallElement = document.getElementById(`overall-${studentIndex}`);
            if (overallElement) {
                overallElement.innerHTML = `
                    <div class="text-lg font-bold text-blue-600">
                        ${overallPercentage.toFixed(1)}%
                    </div>
                    <div class="text-sm text-gray-500">
                        GPA: ${averageGPA.toFixed(2)}
                    </div>
                    <div class="text-sm">
                        <span class="${passedSubjects == totalSubjects ? 'text-green-600' : 'text-red-600'}">
                            ${passedSubjects}/${totalSubjects} Pass
                        </span>
                    </div>
                `;
            }
        }

        // Clear all marks
        function clearAllMarks() {
            if (confirm('Are you sure you want to clear all marks? This action cannot be undone.')) {
                document.querySelectorAll('.marks-input[type="number"]').forEach(input => {
                    input.value = '';
                    input.classList.remove('modified');
                    updateMarks(input);
                });
                modifiedCells.clear();
                updateModifiedCount();
            }
        }

        // Validate all marks
        function validateAllMarks() {
            let errors = [];

            document.querySelectorAll('.marks-input[type="number"]').forEach(input => {
                const value = parseFloat(input.value) || 0;
                const maxMarks = parseFloat(input.dataset.maxMarks);

                if (value > maxMarks) {
                    errors.push(`${input.dataset.type} marks for student ${input.dataset.student} exceeds maximum (${maxMarks})`);
                }
            });

            if (errors.length > 0) {
                alert('Validation Errors:\n' + errors.join('\n'));
            } else {
                showSaveIndicator('All marks validated successfully!');
            }
        }

        // Show save indicator
        function showSaveIndicator(message) {
            const indicator = document.getElementById('save-indicator');
            if (indicator) {
                indicator.innerHTML = `<i class="fas fa-check mr-2"></i> ${message}`;
                indicator.style.display = 'block';
                setTimeout(() => {
                    indicator.style.display = 'none';
                }, 3000);
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize overall performance for all students
            document.querySelectorAll('.student-row').forEach((row, index) => {
                updateOverallPerformance(index);
            });

            // Auto-save functionality (optional)
            const form = document.getElementById('results-form');
            if (form) {
                // Add keyboard shortcut for save
                document.addEventListener('keydown', function(e) {
                    if (e.ctrlKey && e.key === 's') {
                        e.preventDefault();
                        form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>
