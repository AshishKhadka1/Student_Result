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
$teacher_query = "SELECT t.teacher_id, u.full_name 
                FROM teachers t
                JOIN users u ON t.user_id = u.user_id
                WHERE t.user_id = ?";
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
        $teacher_name = $teacher['full_name'];
    } else {
        $error_message = "Teacher record not found. Please contact the administrator.";
    }
    $stmt->close();
}

// Determine the current mode (view or edit)
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view';
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;

// Handle AJAX request for individual mark updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_individual_marks') {
    header('Content-Type: application/json');
    
    $result_id = intval($_POST['result_id']);
    $theory_marks = floatval($_POST['theory_marks']);
    $practical_marks = isset($_POST['practical_marks']) ? floatval($_POST['practical_marks']) : 0;
    
    // Get subject details for calculations
    $subject_query = "SELECT s.full_marks_theory, s.full_marks_practical, s.pass_marks_theory, s.pass_marks_practical
                     FROM results r
                     JOIN subjects s ON r.subject_id = s.subject_id
                     WHERE r.result_id = ?";
    
    $stmt = $conn->prepare($subject_query);
    $stmt->bind_param("i", $result_id);
    $stmt->execute();
    $subject_result = $stmt->get_result();
    $subject_data = $subject_result->fetch_assoc();
    $stmt->close();
    
    if (!$subject_data) {
        echo json_encode(['success' => false, 'message' => 'Subject data not found']);
        exit();
    }
    
    // Calculate new values
    $total_marks = $theory_marks + $practical_marks;
    $full_marks = ($subject_data['full_marks_theory'] ?? 100) + ($subject_data['full_marks_practical'] ?? 0);
    $percentage = ($full_marks > 0) ? ($total_marks / $full_marks) * 100 : 0;
    
    // Check pass/fail
    $theory_pass = $theory_marks >= ($subject_data['pass_marks_theory'] ?? 33);
    $practical_pass = true;
    if (isset($subject_data['full_marks_practical']) && $subject_data['full_marks_practical'] > 0) {
        $practical_pass = $practical_marks >= ($subject_data['pass_marks_practical'] ?? 0);
    }
    
    // Determine grade and GPA
    $grade = 'F';
    $gpa = 0;
    $remarks = 'Fail';
    
    if ($theory_pass && $practical_pass) {
        if ($percentage >= 90) { $grade = 'A+'; $gpa = 4.0; }
        elseif ($percentage >= 80) { $grade = 'A'; $gpa = 3.7; }
        elseif ($percentage >= 70) { $grade = 'B+'; $gpa = 3.3; }
        elseif ($percentage >= 60) { $grade = 'B'; $gpa = 3.0; }
        elseif ($percentage >= 50) { $grade = 'C+'; $gpa = 2.7; }
        elseif ($percentage >= 40) { $grade = 'C'; $gpa = 2.3; }
        elseif ($percentage >= 33) { $grade = 'D'; $gpa = 1.0; }
        else { $grade = 'F'; $gpa = 0.0; }
        
        if ($grade != 'F') $remarks = 'Pass';
    }
    
    // Update the result
    $update_query = "UPDATE results SET 
                     theory_marks = ?, practical_marks = ?, total_marks = ?,
                     percentage = ?, grade = ?, gpa = ?, remarks = ?,
                     updated_by = ?, updated_at = NOW()
                     WHERE result_id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ddddsdsii", $theory_marks, $practical_marks, $total_marks,
                     $percentage, $grade, $gpa, $remarks, $user_id, $result_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Marks updated successfully',
            'data' => [
                'total_marks' => number_format($total_marks, 2),
                'percentage' => number_format($percentage, 2),
                'grade' => $grade,
                'gpa' => number_format($gpa, 2),
                'remarks' => $remarks
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update marks']);
    }
    $stmt->close();
    exit();
}

// Get filter parameters for view mode
$selected_class_filter = isset($_GET['class_filter']) ? intval($_GET['class_filter']) : 0;
$selected_subject_filter = isset($_GET['subject_filter']) ? intval($_GET['subject_filter']) : 0;
$selected_exam_filter = isset($_GET['exam_filter']) ? intval($_GET['exam_filter']) : 0;
$selected_academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';

// Get all classes
$classes = [];
$classes_query = "SELECT c.class_id, c.class_name, c.section, c.academic_year
                FROM classes c
                ORDER BY c.class_name, c.section";
$stmt = $conn->prepare($classes_query);
if (!$stmt) {
    $error_message = "Database error: " . $conn->error;
} else {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt->close();
}

// Get all academic years
$academic_years = [];
foreach ($classes as $class) {
    if (!in_array($class['academic_year'], $academic_years)) {
        $academic_years[] = $class['academic_year'];
    }
}
sort($academic_years);

// Get available subjects for this teacher
$subjects = [];
if (isset($teacher_id) && empty($error_message)) {
    $subjects_query = "SELECT s.subject_id, s.subject_name, s.subject_code, c.class_id, c.class_name, c.section 
                      FROM subjects s
                      JOIN teachersubjects ts ON s.subject_id = ts.subject_id
                      JOIN classes c ON ts.class_id = c.class_id
                      WHERE ts.teacher_id = ? AND ts.is_active = 1
                      ORDER BY c.class_name, s.subject_name";
    
    $stmt = $conn->prepare($subjects_query);
    if (!$stmt) {
        // Try alternative query if the first one fails
        $alt_subjects_query = "SELECT s.subject_id, s.subject_name, s.subject_code 
                              FROM subjects s
                              ORDER BY s.subject_name";
        $stmt = $conn->prepare($alt_subjects_query);
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $subjects[] = $row;
            }
            $stmt->close();
        }
    } else {
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
        
        if (empty($subjects)) {
            $warning_message = "You don't have any subjects assigned to you. Please contact the administrator.";
        }
    }
}

// Get available exams
$exams = [];
if (empty($error_message)) {
    $exams_query = "SELECT e.exam_id, e.exam_name, e.exam_type, e.academic_year, e.start_date, e.end_date
                   FROM exams e
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

// Get grade sheets for view mode
if ($mode === 'view' && isset($teacher_id)) {
    $filter_conditions = [];
    $filter_params = [];
    $param_types = "i";
    $filter_params[] = $teacher_id;

    $base_query = "SELECT 
                e.exam_id, e.exam_name, e.exam_type, e.academic_year, e.start_date, e.end_date,
                c.class_id, c.class_name, c.section,
                s.subject_id, s.subject_name, s.subject_code,
                COUNT(DISTINCT r.student_id) as students_count,
                COUNT(r.result_id) as results_count,
                AVG(r.percentage) as average_percentage,
                SUM(CASE WHEN r.remarks = 'Pass' THEN 1 ELSE 0 END) as pass_count
              FROM exams e
              JOIN results r ON e.exam_id = r.exam_id
              JOIN students st ON r.student_id = st.student_id
              JOIN classes c ON st.class_id = c.class_id
              JOIN subjects s ON r.subject_id = s.subject_id
              JOIN teachersubjects ts ON s.subject_id = ts.subject_id AND c.class_id = ts.class_id
              WHERE ts.teacher_id = ?";

    // Add filters based on selection
    if ($selected_class_filter) {
        $filter_conditions[] = "c.class_id = ?";
        $param_types .= "i";
        $filter_params[] = $selected_class_filter;
    }

    if ($selected_subject_filter) {
        $filter_conditions[] = "s.subject_id = ?";
        $param_types .= "i";
        $filter_params[] = $selected_subject_filter;
    }

    if ($selected_exam_filter) {
        $filter_conditions[] = "e.exam_id = ?";
        $param_types .= "i";
        $filter_params[] = $selected_exam_filter;
    }

    if ($selected_academic_year) {
        $filter_conditions[] = "e.academic_year = ?";
        $param_types .= "s";
        $filter_params[] = $selected_academic_year;
    }

    // Add filter conditions to query
    if (!empty($filter_conditions)) {
        $base_query .= " AND " . implode(" AND ", $filter_conditions);
    }

    // Group by and order by
    $base_query .= " GROUP BY e.exam_id, c.class_id, s.subject_id
                    ORDER BY e.start_date DESC, c.class_name, s.subject_name";

    // Execute query
    $stmt = $conn->prepare($base_query);
    if ($stmt) {
        if (!empty($filter_params)) {
            $stmt->bind_param($param_types, ...$filter_params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $grade_sheets[] = $row;
        }
        $stmt->close();
    }
}

// Check if all required parameters are provided for edit mode
$missing_params = false;
if ($mode === 'edit' && (!$subject_id || !$class_id || !$exam_id)) {
    $missing_params = true;
    
    // Only show error if user tried to submit the form
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['submit'])) {
        $error_message = "Missing required parameters. Please select a subject, class, and exam.";
    }
}

// Get subject details for edit mode
$subject = [];
if ($mode === 'edit' && !$missing_params && empty($error_message)) {
    $subject_query = "SELECT subject_name, subject_code, credit_hours,
                     full_marks_theory, full_marks_practical, 
                     pass_marks_theory, pass_marks_practical
                     FROM subjects
                     WHERE subject_id = ?";
    $stmt = $conn->prepare($subject_query);
    if (!$stmt) {
        $error_message = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        $subject = $subject_result->fetch_assoc();
        $stmt->close();

        if (!$subject) {
            $error_message = "Subject not found.";
        }
    }
}

// Get class details for edit mode
$class = [];
if ($mode === 'edit' && !$missing_params && empty($error_message)) {
    $class_query = "SELECT class_name, section
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

// Get exam details for edit mode
$exam = [];
if ($mode === 'edit' && !$missing_params && empty($error_message)) {
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

// Verify that the teacher is authorized to edit results for this subject and class
if ($mode === 'edit' && !$missing_params && empty($error_message)) {
    $auth_query = "SELECT id FROM teachersubjects 
                  WHERE teacher_id = ? AND subject_id = ? AND class_id = ? AND is_active = 1";
    $stmt = $conn->prepare($auth_query);
    if (!$stmt) {
        $error_message = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("iii", $teacher_id, $subject_id, $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error_message = "You are not authorized to edit results for this subject and class. Please select a subject and class assigned to you.";
        }
        $stmt->close();
    }
}

// Get students and their results for edit mode
$students = [];
if ($mode === 'edit' && !$missing_params && empty($error_message)) {
    // If a specific student is requested
    if ($student_id) {
        $students_query = "SELECT s.student_id, s.roll_number, s.registration_number, u.full_name,
                      r.result_id, r.theory_marks, r.practical_marks, r.total_marks, r.percentage, r.grade, r.gpa, r.remarks
                      FROM students s
                      JOIN users u ON s.user_id = u.user_id
                      LEFT JOIN results r ON s.student_id = r.student_id 
                          AND r.subject_id = ? AND r.exam_id = ?
                      WHERE s.student_id = ?";
    } else {
        $students_query = "SELECT s.student_id, s.roll_number, s.registration_number, u.full_name,
                      r.result_id, r.theory_marks, r.practical_marks, r.total_marks, r.percentage, r.grade, r.gpa, r.remarks
                      FROM students s
                      JOIN users u ON s.user_id = u.user_id
                      LEFT JOIN results r ON s.student_id = r.student_id 
                          AND r.subject_id = ? AND r.exam_id = ?
                      WHERE s.class_id = ?
                      ORDER BY s.roll_number";
    }
    $stmt = $conn->prepare($students_query);
    if (!$stmt) {
        $error_message = "Database error: " . $conn->error;
    } else {
        if ($student_id) {
            $stmt->bind_param("iis", $subject_id, $exam_id, $student_id);
        } else {
            $stmt->bind_param("iii", $subject_id, $exam_id, $class_id);
        }
        $stmt->execute();
        $students_result = $stmt->get_result();
        while ($student = $students_result->fetch_assoc()) {
            $students[] = $student;
        }
        $stmt->close();
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

// Handle form submission for bulk updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results']) && $mode === 'edit' && !$missing_params && empty($error_message)) {
    // Begin transaction
    $conn->begin_transaction();
    try {
        $updated_students = [];
        $total_updated = 0;
        
        foreach ($_POST['student_id'] as $index => $student_id) {
            $theory_marks = isset($_POST['theory_marks'][$index]) ? floatval($_POST['theory_marks'][$index]) : 0;
            $practical_marks = isset($_POST['practical_marks'][$index]) ? floatval($_POST['practical_marks'][$index]) : 0;
            $result_id = $_POST['result_id'][$index];
            
            // Skip if no marks are entered and no existing result
            if ($theory_marks == 0 && $practical_marks == 0 && empty($result_id)) {
                continue;
            }
            
            // Calculate total marks
            $total_marks = $theory_marks + $practical_marks;
            
            // Calculate percentage
            $full_marks = ($subject['full_marks_theory'] ?? 100) + ($subject['full_marks_practical'] ?? 0);
            $percentage = ($full_marks > 0) ? ($total_marks / $full_marks) * 100 : 0;
            
            // Determine grade based on percentage
            $grade = 'F';
            $gpa = 0;
            $remarks = '';
            
            // Check if student passed both theory and practical
            $theory_pass = $theory_marks >= ($subject['pass_marks_theory'] ?? 33);
            $practical_pass = true; // Default to true
            if (isset($subject['full_marks_practical']) && $subject['full_marks_practical'] > 0) {
                $practical_pass = $practical_marks >= ($subject['pass_marks_practical'] ?? 0);
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
                $stmt->bind_param("iiiddddssi", $student_id, $subject_id, $exam_id, $theory_marks, $practical_marks, 
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
            
            // Store updated student data for response
            $updated_students[$index] = [
                'student_id' => $student_id,
                'theory_marks' => $theory_marks,
                'practical_marks' => $practical_marks,
                'total_marks' => $total_marks,
                'percentage' => $percentage,
                'grade' => $grade,
                'gpa' => $gpa,
                'remarks' => $remarks
            ];
        }
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Results saved successfully! Updated $total_updated student records.";
        
        // If this is an AJAX request, return JSON response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $success_message,
                'updated_students' => $updated_students
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
        
        // If this is an AJAX request, return JSON error response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error_message
            ]);
            exit;
        }
    }
}

// Calculate class statistics for edit mode
$stats = [
    'total_students' => count($students),
    'results_entered' => 0,
    'pass_count' => 0,
    'fail_count' => 0,
    'highest_marks' => 0,
    'lowest_marks' => 100,
    'average_marks' => 0,
    'total_marks' => 0
];

if ($mode === 'edit' && !$missing_params) {
    foreach ($students as $student) {
        if (isset($student['total_marks']) && $student['total_marks'] > 0) {
            $stats['results_entered']++;
            $stats['total_marks'] += $student['total_marks'];
            
            if ($student['grade'] != 'F') {
                $stats['pass_count']++;
            } else {
                $stats['fail_count']++;
            }
            
            if ($student['total_marks'] > $stats['highest_marks']) {
                $stats['highest_marks'] = $student['total_marks'];
            }
            
            if ($student['total_marks'] < $stats['lowest_marks']) {
                $stats['lowest_marks'] = $student['total_marks'];
            }
        }
    }

    if ($stats['results_entered'] > 0) {
        $stats['average_marks'] = $stats['total_marks'] / $stats['results_entered'];
    } else {
        $stats['lowest_marks'] = 0;
    }

    $stats['pass_percentage'] = ($stats['total_students'] > 0) ? 
        ($stats['pass_count'] / $stats['total_students']) * 100 : 0;
}

// Get school settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Set default values if settings are not available
$prepared_by = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'System Administrator';
$issue_date = date('Y-m-d');

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode === 'edit' ? 'Edit Results' : 'Grade Sheets'; ?> | Teacher Dashboard</title>
    <link href="../css/tailwind.css" rel="stylesheet">
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
        
        .result-row:hover { background-color: #F3F4F6; }
        .result-row.editing { background-color: #EFF6FF; border-left: 3px solid #3B82F6; }
        
        .marks-input { transition: all 0.2s; }
        .marks-input:focus { border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); }
        .marks-input.invalid { border-color: #EF4444; background-color: #FEF2F2; }
        
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
        
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .stat-card { transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .notification.show { transform: translateX(0); }
        .notification.success { background-color: #D1FAE5; border-left: 4px solid #10B981; color: #065F46; }
        .notification.error { background-color: #FEE2E2; border-left: 4px solid #EF4444; color: #991B1B; }
        
        .grade-card {
            transition: all 0.3s ease;
        }
        
        .grade-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.good {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.average {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.poor {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .filter-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: #e5e7eb;
            color: #374151;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .filter-badge button {
            margin-left: 0.25rem;
            color: #6b7280;
        }
        
        .filter-badge button:hover {
            color: #ef4444;
        }
        
        @media (max-width: 640px) {
            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-container > div {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <div class="flex">
        <?php include 'includes/teacher_sidebar.php'; ?>
        
        <div class="flex-1 p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-edit text-blue-500 mr-2"></i>
                    <?php echo $mode === 'edit' ? 'Edit Student Results' : 'Grade Sheets Management'; ?>
                </h1>
                <div class="flex space-x-3">
                    <?php if ($mode === 'view'): ?>
                    
                    <a href="teacher_dashboard.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                    <?php endif; ?>
                    
                </div>
            </div>
            
            <?php if (!empty($success_message)): ?>
            <div id="success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 fade-in" role="alert">
                <div class="flex items-center">
                    <div class="py-1"><i class="fas fa-check-circle text-green-500 mr-3"></i></div>
                    <div>
                        <p class="font-bold">Success!</p>
                        <p><?php echo $success_message; ?></p>
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
                        <p><?php echo $error_message; ?></p>
                    </div>
                    <button type="button" class="ml-auto" onclick="document.getElementById('error-alert').style.display='none'">
                        <i class="fas fa-times text-red-500"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($mode === 'view'): ?>
            <!-- VIEW MODE: Grade Sheets List -->
            
            <!-- Teacher Info Card -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($teacher_name ?? 'Teacher'); ?></h2>
                        <p class="mt-1 text-sm text-gray-600">Manage and view grade sheets for your assigned classes and subjects.</p>
                    </div>
                </div>
            </div>
            
            <!-- Published Results Notice -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Published Results Only:</strong> You can only view and edit results that have been officially published by the administration.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Grade Sheets</h3>
                
                <form action="" method="get" class="filter-container flex flex-wrap gap-4 mb-4">
                    <input type="hidden" name="mode" value="view">
                    
                    <!-- Class Filter -->
                    <div class="w-full sm:w-auto">
                        <label for="class_filter" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select id="class_filter" name="class_filter" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>" <?php echo $selected_class_filter == $class['class_id'] ? 'selected' : ''; ?>>
                                    <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Subject Filter -->
                    <div class="w-full sm:w-auto">
                        <label for="subject_filter" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <select id="subject_filter" name="subject_filter" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_id']; ?>" <?php echo $selected_subject_filter == $subject['subject_id'] ? 'selected' : ''; ?>>
                                    <?php echo $subject['subject_name'] . ' (' . $subject['subject_code'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Exam Filter -->
                    <div class="w-full sm:w-auto">
                        <label for="exam_filter" class="block text-sm font-medium text-gray-700 mb-1">Exam</label>
                        <select id="exam_filter" name="exam_filter" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">All Exams</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['exam_id']; ?>" <?php echo $selected_exam_filter == $exam['exam_id'] ? 'selected' : ''; ?>>
                                    <?php echo $exam['exam_name'] . ' (' . $exam['academic_year'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Academic Year Filter -->
                    <div class="w-full sm:w-auto">
                        <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                        <select id="academic_year" name="academic_year" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">All Years</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $selected_academic_year == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Filter Button -->
                    <div class="w-full sm:w-auto flex items-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                    </div>
                    
                    <!-- Reset Filters -->
                    <?php if ($selected_class_filter || $selected_subject_filter || $selected_exam_filter || $selected_academic_year): ?>
                        <div class="w-full sm:w-auto flex items-end">
                            <a href="?mode=view" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-times mr-2"></i> Reset Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
                
                <!-- Active Filters -->
                <?php if ($selected_class_filter || $selected_subject_filter || $selected_exam_filter || $selected_academic_year): ?>
                    <div class="mt-4">
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Active Filters:</h4>
                        <div>
                            <?php if ($selected_class_filter): ?>
                                <?php 
                                $class_name = '';
                                foreach ($classes as $class) {
                                    if ($class['class_id'] == $selected_class_filter) {
                                        $class_name = $class['class_name'] . ' ' . $class['section'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="filter-badge">
                                    Class: <?php echo $class_name; ?>
                                    <a href="?mode=view&<?php 
                                        $params = $_GET;
                                        unset($params['class_filter']);
                                        echo http_build_query($params);
                                    ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($selected_subject_filter): ?>
                                <?php 
                                $subject_name = '';
                                foreach ($subjects as $subject) {
                                    if ($subject['subject_id'] == $selected_subject_filter) {
                                        $subject_name = $subject['subject_name'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="filter-badge">
                                    Subject: <?php echo $subject_name; ?>
                                    <a href="?mode=view&<?php 
                                        $params = $_GET;
                                        unset($params['subject_filter']);
                                        echo http_build_query($params);
                                    ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($selected_exam_filter): ?>
                                <?php 
                                $exam_name = '';
                                foreach ($exams as $exam) {
                                    if ($exam['exam_id'] == $selected_exam_filter) {
                                        $exam_name = $exam['exam_name'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="filter-badge">
                                    Exam: <?php echo $exam_name; ?>
                                    <a href="?mode=view&<?php 
                                        $params = $_GET;
                                        unset($params['exam_filter']);
                                        echo http_build_query($params);
                                    ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($selected_academic_year): ?>
                                <span class="filter-badge">
                                    Academic Year: <?php echo $selected_academic_year; ?>
                                    <a href="?mode=view&<?php 
                                        $params = $_GET;
                                        unset($params['academic_year']);
                                        echo http_build_query($params);
                                    ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Grade Sheets Grid -->
            <div class="mb-8">
                <?php if (empty($grade_sheets)): ?>
                    <div class="bg-white shadow rounded-lg p-8 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 text-yellow-500 mb-4">
                            <i class="fas fa-exclamation-circle text-3xl"></i>
                        </div>
                        <h2 class="text-xl font-medium text-gray-900 mb-2">No Grade Sheets Found</h2>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">
                            <?php if ($selected_class_filter || $selected_subject_filter || $selected_exam_filter || $selected_academic_year): ?>
                                No grade sheets match your filter criteria. Try adjusting your filters or view all grade sheets.
                            <?php else: ?>
                                You don't have any grade sheets available yet. Start by adding results for your assigned classes and subjects.
                            <?php endif; ?>
                        </p>
                        
                        <?php if ($selected_class_filter || $selected_subject_filter || $selected_exam_filter || $selected_academic_year): ?>
                            <a href="?mode=view" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-list mr-2"></i> View All Grade Sheets
                            </a>
                        <?php else: ?>
                            
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($grade_sheets as $sheet): ?>
                            <div class="bg-white shadow rounded-lg overflow-hidden grade-card">
                                <div class="bg-blue-600 text-white px-4 py-3">
                                    <div class="flex justify-between items-center">
                                        <h3 class="font-semibold"><?php echo $sheet['exam_name']; ?></h3>
                                        <span class="text-xs bg-blue-500 px-2 py-1 rounded-full">
                                            <?php echo $sheet['exam_type'] ?? 'Exam'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="p-4">
                                    <div class="flex justify-between items-center mb-3">
                                        <span class="text-sm text-gray-500">
                                            <i class="far fa-calendar-alt mr-1"></i> 
                                            <?php echo $sheet['academic_year']; ?>
                                        </span>
                                        
                                        <?php 
                                        $percentage = $sheet['average_percentage'] ?? 0;
                                        $status_class = 'poor';
                                        $status_text = 'Poor';
                                        
                                        if ($percentage >= 70) {
                                            $status_class = 'good';
                                            $status_text = 'Good';
                                        } elseif ($percentage >= 40) {
                                            $status_class = 'average';
                                            $status_text = 'Average';
                                        }
                                        ?>
                                        
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="text-sm font-medium text-gray-700">
                                            <i class="fas fa-book mr-1 text-blue-500"></i> 
                                            <?php echo $sheet['subject_name']; ?> (<?php echo $sheet['subject_code']; ?>)
                                        </p>
                                        <p class="text-sm font-medium text-gray-700">
                                            <i class="fas fa-users mr-1 text-green-500"></i> 
                                            <?php echo $sheet['class_name'] . ' ' . $sheet['section']; ?>
                                        </p>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-2 mb-4">
                                        <div class="bg-gray-50 p-2 rounded">
                                            <p class="text-xs text-gray-500">Students</p>
                                            <p class="font-semibold"><?php echo $sheet['students_count']; ?></p>
                                        </div>
                                        <div class="bg-gray-50 p-2 rounded">
                                            <p class="text-xs text-gray-500">Pass Rate</p>
                                            <p class="font-semibold">
                                                <?php 
                                                $pass_rate = ($sheet['students_count'] > 0) ? 
                                                    round(($sheet['pass_count'] / $sheet['students_count']) * 100) : 0;
                                                echo $pass_rate . '%'; 
                                                ?>
                                            </p>
                                        </div>
                                        <div class="bg-gray-50 p-2 rounded">
                                            <p class="text-xs text-gray-500">Average</p>
                                            <p class="font-semibold"><?php echo round($sheet['average_percentage'], 1); ?>%</p>
                                        </div>
                                        <div class="bg-gray-50 p-2 rounded">
                                            <p class="text-xs text-gray-500">Exam Date</p>
                                            <p class="font-semibold text-xs">
                                                <?php 
                                                if (isset($sheet['start_date'])) {
                                                    echo date('d M Y', strtotime($sheet['start_date']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <a href="?mode=edit&subject_id=<?php echo $sheet['subject_id']; ?>&class_id=<?php echo $sheet['class_id']; ?>&exam_id=<?php echo $sheet['exam_id']; ?>" 
                                           class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-edit mr-2"></i> Edit Results
                                        </a>
                                        <a href="view_students.php?subject_id=<?php echo $sheet['subject_id']; ?>&class_id=<?php echo $sheet['class_id']; ?>&exam_id=<?php echo $sheet['exam_id']; ?>" 
                                           class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-users mr-2"></i> View Students
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <!-- EDIT MODE: Result Entry Form -->
            
            <?php if ($missing_params): ?>
            <!-- Parameter Selection Form -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Select Parameters to Edit Results</h3>
                <p class="text-sm text-gray-600 mb-6">Please select a subject, class, and exam to edit student results.</p>
                
                <form action="" method="get" class="space-y-4">
                    <input type="hidden" name="mode" value="edit">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Subject Selection -->
                        <div>
                            <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <select id="subject_id" name="subject_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subj): ?>
                                    <option value="<?php echo $subj['subject_id']; ?>" <?php echo $subject_id == $subj['subject_id'] ? 'selected' : ''; ?>>
                                        <?php echo $subj['subject_name'] . ' (' . $subj['subject_code'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Class Selection -->
                        <div>
                            <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                            <select id="class_id" name="class_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $cls): ?>
                                    <option value="<?php echo $cls['class_id']; ?>" <?php echo $class_id == $cls['class_id'] ? 'selected' : ''; ?>>
                                        <?php echo $cls['class_name'] . ' ' . $cls['section'] . ' (' . $cls['academic_year'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Exam Selection -->
                        <div>
                            <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-1">Exam</label>
                            <select id="exam_id" name="exam_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                <option value="">Select Exam</option>
                                <?php foreach ($exams as $ex): ?>
                                    <option value="<?php echo $ex['exam_id']; ?>" <?php echo $exam_id == $ex['exam_id'] ? 'selected' : ''; ?>>
                                        <?php echo $ex['exam_name'] . ' (' . $ex['academic_year'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-search mr-2"></i> Load Results
                        </button>
                    </div>
                </form>
            </div>
            
            <?php else: ?>
            <!-- Subject and Exam Information -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-4 text-white">
                        <h3 class="text-sm font-medium opacity-80 mb-1">Subject</h3>
                        <p class="text-xl font-bold mb-1"><?php echo isset($subject['subject_name']) ? $subject['subject_name'] : 'N/A'; ?></p>
                        <p class="text-sm opacity-90"><?php echo isset($subject['subject_code']) ? 'Code: ' . $subject['subject_code'] : ''; ?></p>
                    </div>
                    
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-4 text-white">
                        <h3 class="text-sm font-medium opacity-80 mb-1">Class</h3>
                        <p class="text-xl font-bold mb-1">
                            <?php echo isset($class['class_name']) ? $class['class_name'] : 'N/A'; ?>
                            <?php echo isset($class['section']) ? ' - ' . $class['section'] : ''; ?>
                        </p>
                        <p class="text-sm opacity-90">Total Students: <?php echo count($students); ?></p>
                    </div>
                    
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-4 text-white">
                        <h3 class="text-sm font-medium opacity-80 mb-1">Exam</h3>
                        <p class="text-xl font-bold mb-1"><?php echo isset($exam['exam_name']) ? $exam['exam_name'] : 'N/A'; ?></p>
                        <p class="text-sm opacity-90">
                            <?php echo isset($exam['academic_year']) ? $exam['academic_year'] : ''; ?>
                            <?php echo isset($exam['exam_type']) ? ' - ' . $exam['exam_type'] : ''; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Results Entry Form -->
            <?php if (empty($students)): ?>
            <div class="bg-yellow-100 text-yellow-700 p-4 rounded">
                <p><i class="fas fa-exclamation-triangle mr-2"></i> No students found in this class.</p>
            </div>
            <?php else: ?>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1  gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4 stat-card">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500">Total Students</p>
                            <p class="text-lg font-semibold text-gray-900"><?php echo $stats['total_students']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4 stat-card">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500">Pass Count</p>
                            <p class="text-lg font-semibold text-gray-900"><?php echo $stats['pass_count']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4 stat-card">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500">Average Marks</p>
                            <p class="text-lg font-semibold text-gray-900"><?php echo number_format($stats['average_marks'], 1); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4 stat-card">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500">Pass Rate</p>
                            <p class="text-lg font-semibold text-gray-900"><?php echo number_format($stats['pass_percentage'], 1); ?>%</p>
                        </div>
                    </div>
                </div>
                
                <form id="results-form" method="POST" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Theory Marks
                                    <br><span class="text-xs text-gray-400">(Max: <?php echo $subject['full_marks_theory'] ?? 100; ?>)</span>
                                </th>
                                <?php if (isset($subject['full_marks_practical']) && $subject['full_marks_practical'] > 0): ?>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Practical Marks
                                    <br><span class="text-xs text-gray-400">(Max: <?php echo $subject['full_marks_practical']; ?>)</span>
                                </th>
                                <?php endif; ?>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($students as $index => $student): ?>
                            <tr class="result-row" id="student-row-<?php echo $index; ?>"
                                data-theory-full-marks="<?php echo $subject['full_marks_theory'] ?? 100; ?>"
                                data-practical-full-marks="<?php echo $subject['full_marks_practical'] ?? 0; ?>"
                                data-theory-pass-marks="<?php echo $subject['pass_marks_theory'] ?? 33; ?>"
                                data-practical-pass-marks="<?php echo $subject['pass_marks_practical'] ?? 0; ?>">
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                    <div class="text-sm text-gray-500">ID: <?php echo htmlspecialchars($student['student_id']); ?></div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($student['roll_number']); ?>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" 
                                           name="theory_marks[<?php echo $index; ?>]" 
                                           value="<?php echo $student['theory_marks'] ?? ''; ?>"
                                           class="marks-input w-20 px-2 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           min="0" 
                                           max="<?php echo $subject['full_marks_theory'] ?? 100; ?>"
                                           step="0.01"
                                           data-index="<?php echo $index; ?>"
                                           onchange="validateMarks(this)"
                                           onfocus="highlightRow(<?php echo $index; ?>)">
                                </td>
                                
                                <?php if (isset($subject['full_marks_practical']) && $subject['full_marks_practical'] > 0): ?>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" 
                                           name="practical_marks[<?php echo $index; ?>]" 
                                           value="<?php echo $student['practical_marks'] ?? ''; ?>"
                                           class="marks-input w-20 px-2 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           min="0" 
                                           max="<?php echo $subject['full_marks_practical']; ?>"
                                           step="0.01"
                                           data-index="<?php echo $index; ?>"
                                           onchange="validateMarks(this)"
                                           onfocus="highlightRow(<?php echo $index; ?>)">
                                </td>
                                <?php endif; ?>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span id="total-marks-<?php echo $index; ?>"><?php echo number_format($student['total_marks'] ?? 0, 2); ?></span>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span id="percentage-<?php echo $index; ?>"><?php echo number_format($student['percentage'] ?? 0, 2); ?>%</span>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $grade = $student['grade'] ?? 'F';
                                    $grade_class = 'grade-' . strtolower(str_replace('+', '-plus', $grade));
                                    ?>
                                    <span id="grade-<?php echo $index; ?>" class="grade-badge <?php echo $grade_class; ?>">
                                        <?php echo $grade; ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $remarks = $student['remarks'] ?? 'Fail';
                                    $status_class = ($remarks === 'Pass') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                    ?>
                                    <span id="status-<?php echo $index; ?>" class="px-2 py-1 rounded-full text-xs <?php echo $status_class; ?>">
                                        <?php echo $remarks; ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button type="button" 
                                            onclick="editIndividualResult('<?php echo $student['result_id']; ?>', <?php echo $index; ?>)"
                                            class="text-blue-600 hover:text-blue-900"
                                            title="Quick Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                                
                                <!-- Hidden fields -->
                                <input type="hidden" name="student_id[<?php echo $index; ?>]" value="<?php echo $student['student_id']; ?>">
                                <input type="hidden" name="result_id[<?php echo $index; ?>]" value="<?php echo $student['result_id'] ?? ''; ?>">
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                You can edit the results below. Use Tab or Enter to navigate between fields.
                            </div>
                            <button type="submit" 
                                    name="save_results" 
                                    id="save-button"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-save mr-2"></i> Save All Results
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php endif; ?>          
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Edit Modal -->
    <div id="quickEditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Quick Edit Marks</h3>
                    <button onclick="closeQuickEditModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="quickEditForm" onsubmit="return updateIndividualMarks(event)">
                    <input type="hidden" id="editResultId" name="result_id">
                    <input type="hidden" id="editIndex" name="index">
                    
                    <div class="mb-4">
                        <label for="editTheoryMarks" class="block text-sm font-medium text-gray-700 mb-1">
                            Theory Marks
                        </label>
                        <input type="number" id="editTheoryMarks" name="theory_marks" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                               step="0.01" min="0" required>
                        <p class="text-xs text-gray-500 mt-1" id="theoryMaxText">Maximum marks: 100</p>
                    </div>
                    
                    <div class="mb-4" id="practicalSection">
                        <label for="editPracticalMarks" class="block text-sm font-medium text-gray-700 mb-1">
                            Practical Marks
                        </label>
                        <input type="number" id="editPracticalMarks" name="practical_marks" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                               step="0.01" min="0">
                        <p class="text-xs text-gray-500 mt-1" id="practicalMaxText">Maximum marks: 0</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeQuickEditModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" id="updateButton"
                                class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
                            <i class="fas fa-save mr-1"></i> Update Marks
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Highlight the row being edited
        function highlightRow(index) {
            document.querySelectorAll('.result-row').forEach(row => {
                row.classList.remove('editing');
            });
            document.getElementById('student-row-' + index).classList.add('editing');
        }
        
        // Validate marks and update total
        function validateMarks(input) {
            const index = input.dataset.index;
            const value = parseFloat(input.value) || 0;
            const maxMarks = parseFloat(input.max) || 100;
            
            // Validate input
            if (value < 0) {
                input.value = 0;
            } else if (value > maxMarks) {
                input.value = maxMarks;
                input.classList.add('invalid');
                setTimeout(() => input.classList.remove('invalid'), 2000);
            }
            
            // Calculate total marks
            const theoryInput = document.querySelector(`input[name="theory_marks[${index}]"]`);
            const practicalInput = document.querySelector(`input[name="practical_marks[${index}]"]`);
            
            const theoryMarks = theoryInput ? (parseFloat(theoryInput.value) || 0) : 0;
            const practicalMarks = practicalInput ? (parseFloat(practicalInput.value) || 0) : 0;
            const totalMarks = theoryMarks + practicalMarks;
            
            // Update total marks display
            const totalElement = document.getElementById('total-marks-' + index);
            if (totalElement) {
                totalElement.textContent = totalMarks.toFixed(2);
            }
            
            // Calculate percentage and update grade/status
            calculateGradeAndStatus(index, theoryMarks, practicalMarks, totalMarks);
            
            // Mark the row as modified
            const row = document.getElementById('student-row-' + index);
            if (row) {
                row.classList.add('editing');
            }
        }
        
        // Calculate and update grade and status based on marks
        function calculateGradeAndStatus(index, theoryMarks, practicalMarks, totalMarks) {
            const rowElement = document.getElementById('student-row-' + index);
            const theoryFullMarks = parseFloat(rowElement.dataset.theoryFullMarks) || 100;
            const practicalFullMarks = parseFloat(rowElement.dataset.practicalFullMarks) || 0;
            const theoryPassMarks = parseFloat(rowElement.dataset.theoryPassMarks) || 33;
            const practicalPassMarks = parseFloat(rowElement.dataset.practicalPassMarks) || 0;
            
            // Calculate percentage
            const fullMarks = theoryFullMarks + practicalFullMarks;
            const percentage = (fullMarks > 0) ? (totalMarks / fullMarks) * 100 : 0;
            
            // Check if student passed
            const theoryPassed = theoryMarks >= theoryPassMarks;
            const practicalPassed = practicalFullMarks > 0 ? (practicalMarks >= practicalPassMarks) : true;
            const passed = theoryPassed && practicalPassed;
            
            // Determine grade
            let grade = 'F';
            if (passed) {
                if (percentage >= 90) grade = 'A+';
                else if (percentage >= 80) grade = 'A';
                else if (percentage >= 70) grade = 'B+';
                else if (percentage >= 60) grade = 'B';
                else if (percentage >= 50) grade = 'C+';
                else if (percentage >= 40) grade = 'C';
                else if (percentage >= 33) grade = 'D';
                else grade = 'F';
            }
            
            // Update percentage display
            const percentageElement = document.getElementById('percentage-' + index);
            if (percentageElement) {
                percentageElement.textContent = percentage.toFixed(2) + '%';
            }
            
            // Update grade and status elements
            const gradeElement = document.getElementById('grade-' + index);
            const statusElement = document.getElementById('status-' + index);
            
            if (gradeElement) {
                // Remove all existing grade classes
                gradeElement.classList.remove('grade-A-plus', 'grade-A', 'grade-B-plus', 'grade-B', 'grade-C-plus', 'grade-C', 'grade-D', 'grade-F', 'bg-gray-200');
                
                // Add appropriate grade class
                const gradeClass = 'grade-' + grade.replace('+', '-plus');
                gradeElement.classList.add(gradeClass);
                gradeElement.textContent = grade;
            }
            
            if (statusElement) {
                // Remove existing status classes
                statusElement.classList.remove('bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800', 'bg-gray-100', 'text-gray-800');
                
                // Add appropriate status class and text
                if (passed) {
                    statusElement.classList.add('bg-green-100', 'text-green-800');
                    statusElement.textContent = 'Pass';
                } else {
                    statusElement.classList.add('bg-red-100', 'text-red-800');
                    statusElement.textContent = 'Fail';
                }
            }
        }
        
        // Quick edit individual result
        function editIndividualResult(resultId, index) {
            const theoryInput = document.querySelector(`input[name="theory_marks[${index}]"]`);
            const practicalInput = document.querySelector(`input[name="practical_marks[${index}]"]`);
            const rowElement = document.getElementById('student-row-' + index);
            
            const theoryMax = parseFloat(rowElement.dataset.theoryFullMarks) || 100;
            const practicalMax = parseFloat(rowElement.dataset.practicalFullMarks) || 0;
            
            document.getElementById('editResultId').value = resultId;
            document.getElementById('editIndex').value = index;
            document.getElementById('editTheoryMarks').value = theoryInput.value;
            document.getElementById('editPracticalMarks').value = practicalInput ? practicalInput.value : '';
            document.getElementById('editTheoryMarks').max = theoryMax;
            document.getElementById('editPracticalMarks').max = practicalMax;
            
            document.getElementById('theoryMaxText').textContent = 'Maximum marks: ' + theoryMax;
            document.getElementById('practicalMaxText').textContent = 'Maximum marks: ' + practicalMax;
            
            // Show/hide practical section
            const practicalSection = document.getElementById('practicalSection');
            if (practicalMax > 0) {
                practicalSection.style.display = 'block';
                document.getElementById('editPracticalMarks').disabled = false;
            } else {
                practicalSection.style.display = 'none';
                document.getElementById('editPracticalMarks').disabled = true;
            }
            
            document.getElementById('quickEditModal').classList.remove('hidden');
        }
        
        function closeQuickEditModal() {
            document.getElementById('quickEditModal').classList.add('hidden');
        }
        
        function updateIndividualMarks(event) {
            event.preventDefault();
            
            const formData = new FormData(document.getElementById('quickEditForm'));
            formData.append('action', 'update_individual_marks');
            
            const updateButton = document.getElementById('updateButton');
            const originalText = updateButton.innerHTML;
            updateButton.innerHTML = '<div class="spinner"></div> Updating...';
            updateButton.disabled = true;
            
            fetch('edit_results.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const index = formData.get('index');
                    
                    // Update the form inputs
                    const theoryInput = document.querySelector(`input[name="theory_marks[${index}]"]`);
                    const practicalInput = document.querySelector(`input[name="practical_marks[${index}]"]`);
                    
                    if (theoryInput) theoryInput.value = formData.get('theory_marks');
                    if (practicalInput) practicalInput.value = formData.get('practical_marks') || '';
                    
                    // Update displays
                    document.getElementById('total-marks-' + index).textContent = data.data.total_marks;
                    document.getElementById('percentage-' + index).textContent = data.data.percentage + '%';
                    
                    const gradeElement = document.getElementById('grade-' + index);
                    gradeElement.className = 'grade-badge grade-' + data.data.grade.toLowerCase().replace('+', '-plus');
                    gradeElement.textContent = data.data.grade;
                    
                    const statusElement = document.getElementById('status-' + index);
                    statusElement.className = 'px-2 py-1 rounded-full text-xs ' + 
                        (data.data.remarks === 'Pass' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
                    statusElement.textContent = data.data.remarks;
                    
                    showNotification('Marks updated successfully!', 'success');
                    closeQuickEditModal();
                } else {
                    showNotification(data.message || 'Failed to update marks', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating marks', 'error');
            })
            .finally(() => {
                updateButton.innerHTML = originalText;
                updateButton.disabled = false;
            });
            
            return false;
        }
        
        // Show loading indicator when saving
        const form = document.getElementById('results-form');
        if (form) {
            form.addEventListener('submit', function() {
                const saveButton = document.getElementById('save-button');
                if (saveButton) {
                    saveButton.innerHTML = '<div class="spinner"></div> Saving...';
                    saveButton.disabled = true;
                }
            });
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const successAlert = document.getElementById('success-alert');
            const errorAlert = document.getElementById('error-alert');
            
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }, 5000);
        
        // Add keyboard navigation for faster data entry
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                const activeElement = document.activeElement;
                if (activeElement.tagName === 'INPUT' && activeElement.type === 'number') {
                    e.preventDefault();
                    
                    const inputs = Array.from(document.querySelectorAll('input[type="number"].marks-input'));
                    const currentIndex = inputs.indexOf(activeElement);
                    
                    if (currentIndex < inputs.length - 1) {
                        inputs[currentIndex + 1].focus();
                    }
                }
            }
        });

        // Auto-select class based on subject selection
        function updateClassSelection() {
            const subjectSelect = document.getElementById('subject_id');
            const classSelect = document.getElementById('class_id');
            
            if (subjectSelect && classSelect && subjectSelect.selectedIndex > 0) {
                const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
                const classId = selectedOption.getAttribute('data-class-id');
                
                for (let i = 0; i < classSelect.options.length; i++) {
                    if (classSelect.options[i].value === classId) {
                        classSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateClassSelection();
        });

        function showNotification(message, type) {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-current opacity-70 hover:opacity-100">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }
    </script>
</body>
</html>
