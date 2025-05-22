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
$warning_message = '';

// Get teacher ID from the teachers table
$teacher_query = "SELECT t.teacher_id, u.full_name FROM teachers t 
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

// Get subject ID, class ID, and exam ID from URL parameters
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;

// Check if all required parameters are provided
$missing_params = false;
if (!$subject_id || !$class_id || !$exam_id) {
    $missing_params = true;
    
    // Only show error if user tried to submit the form
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['submit'])) {
        $error_message = "Missing required parameters. Please select a subject, class, and exam.";
    }
}

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
        $error_message = "Database error: " . $conn->error;
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

// Get available classes for this teacher
$classes = [];
if (isset($teacher_id) && empty($error_message)) {
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

// Get subject details
$subject = [];
if (!$missing_params && empty($error_message)) {
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

// Get class details
$class = [];
if (!$missing_params && empty($error_message)) {
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

// Verify that the teacher is authorized to edit results for this subject and class
if (!$missing_params && empty($error_message) && isset($teacher_id)) {
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

// Get students and their results
$students = [];
if (!$missing_params && empty($error_message)) {
    // If a specific student is requested
    if ($student_id) {
        $students_query = "SELECT s.student_id, s.roll_number, s.registration_number, u.full_name,
                          r.result_id, r.theory_marks, r.practical_marks, r.total_marks, r.percentage, r.grade, r.gpa, r.remarks
                          FROM students s
                          JOIN users u ON s.user_id = u.user_id
                          LEFT JOIN results r ON s.student_id = r.student_id 
                              AND r.subject_id = ? AND r.exam_id = ?
                          WHERE s.student_id = ?";
        $stmt = $conn->prepare($students_query);
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("iis", $subject_id, $exam_id, $student_id);
            $stmt->execute();
            $students_result = $stmt->get_result();
            while ($student = $students_result->fetch_assoc()) {
                $students[] = $student;
            }
            $stmt->close();
        }
    } else {
        // Get all students in the class
        $students_query = "SELECT s.student_id, s.roll_number, s.registration_number, u.full_name,
                          r.result_id, r.theory_marks, r.practical_marks, r.total_marks, r.percentage, r.grade, r.gpa, r.remarks
                          FROM students s
                          JOIN users u ON s.user_id = u.user_id
                          LEFT JOIN results r ON s.student_id = r.student_id 
                              AND r.subject_id = ? AND r.exam_id = ?
                          WHERE s.class_id = ?
                          ORDER BY s.roll_number";
        $stmt = $conn->prepare($students_query);
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("iii", $subject_id, $exam_id, $class_id);
            $stmt->execute();
            $students_result = $stmt->get_result();
            while ($student = $students_result->fetch_assoc()) {
                $students[] = $student;
            }
            $stmt->close();
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results']) && !$missing_params && empty($error_message)) {
    // Begin transaction
    $conn->begin_transaction();
    try {
        if (isset($_POST['student_id']) && is_array($_POST['student_id'])) {
            foreach ($_POST['student_id'] as $index => $student_id) {
                $theory_marks = isset($_POST['theory_marks'][$index]) ? floatval($_POST['theory_marks'][$index]) : 0;
                $practical_marks = isset($_POST['practical_marks'][$index]) ? floatval($_POST['practical_marks'][$index]) : 0;
                $result_id = isset($_POST['result_id'][$index]) ? $_POST['result_id'][$index] : '';
                
                // Skip if no marks are entered and no existing result
                if (($theory_marks == 0 && $practical_marks == 0) && empty($result_id)) {
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
                    $stmt->bind_param("iiiddddsdsii", $student_id, $subject_id, $exam_id, $theory_marks, $practical_marks, 
                                    $total_marks, $percentage, $grade, $gpa, $remarks, $user_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Update existing result
                    $update_query = "UPDATE results SET theory_marks = ?, practical_marks = ?, total_marks = ?, 
                                   percentage = ?, grade = ?, gpa = ?, remarks = ?, updated_by = ?, updated_at = NOW()
                                   WHERE result_id = ?";
                    $stmt = $conn->prepare($update_query);
                    if (!$stmt) {
                        throw new Exception("Database error: " . $conn->error);
                    }
                    $stmt->bind_param("ddddsdsii", $theory_marks, $practical_marks, $total_marks, 
                                    $percentage, $grade, $gpa, $remarks, $user_id, $result_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        $success_message = "Results saved successfully!";
        
        // Refresh the student data
        if (isset($student_id) && !is_array($student_id)) {
            $students_query = "SELECT s.student_id, s.roll_number, s.registration_number, u.full_name,
                              r.result_id, r.theory_marks, r.practical_marks, r.total_marks, r.percentage, r.grade, r.gpa, r.remarks
                              FROM students s
                              JOIN users u ON s.user_id = u.user_id
                              LEFT JOIN results r ON s.student_id = r.student_id 
                                  AND r.subject_id = ? AND r.exam_id = ?
                              WHERE s.student_id = ?";
            $stmt = $conn->prepare($students_query);
            $stmt->bind_param("iis", $subject_id, $exam_id, $student_id);
        } else {
            $students_query = "SELECT s.student_id, s.roll_number, s.registration_number, u.full_name,
                              r.result_id, r.theory_marks, r.practical_marks, r.total_marks, r.percentage, r.grade, r.gpa, r.remarks
                              FROM students s
                              JOIN users u ON s.user_id = u.user_id
                              LEFT JOIN results r ON s.student_id = r.student_id 
                                  AND r.subject_id = ? AND r.exam_id = ?
                              WHERE s.class_id = ?
                              ORDER BY s.roll_number";
            $stmt = $conn->prepare($students_query);
            $stmt->bind_param("iii", $subject_id, $exam_id, $class_id);
        }
        
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->execute();
            $students_result = $stmt->get_result();
            $students = [];
            while ($student = $students_result->fetch_assoc()) {
                $students[] = $student;
            }
            $stmt->close();
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Calculate class statistics
$stats = [
    'total_students' => count($students),
    'results_entered' => 0,
    'pass_count' => 0,
    'fail_count' => 0,
    'highest_marks' => 0,
    'lowest_marks' => 100,
    'average_marks' => 0,
    'total_marks' => 0,
    'pass_percentage' => 0
];

if (!$missing_params && !empty($students)) {
    foreach ($students as $student) {
        if (isset($student['total_marks']) && $student['total_marks'] > 0) {
            $stats['results_entered']++;
            $stats['total_marks'] += $student['total_marks'];
            
            if (isset($student['grade']) && $student['grade'] != 'F') {
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
$settings_query = "SELECT setting_key, setting_value FROM settings";
$result = $conn->query($settings_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Set default values if settings are not available
$prepared_by = isset($teacher_name) ? $teacher_name : (isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'System Administrator');
$issue_date = date('Y-m-d');

// Calculate full marks
$full_marks_theory = isset($subject['full_marks_theory']) ? $subject['full_marks_theory'] : 100;
$full_marks_practical = isset($subject['full_marks_practical']) ? $subject['full_marks_practical'] : 0;
$total_full_marks = $full_marks_theory + $full_marks_practical;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Results - <?php echo isset($subject['subject_name']) ? $subject['subject_name'] : 'Subject'; ?></title>
    <link href="../css/tailwind.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4F46E5;
            --primary-hover: #4338CA;
            --primary-light: #EEF2FF;
            --secondary-color: #10B981;
            --secondary-hover: #059669;
            --secondary-light: #ECFDF5;
            --danger-color: #EF4444;
            --danger-hover: #DC2626;
            --danger-light: #FEF2F2;
            --warning-color: #F59E0B;
            --warning-hover: #D97706;
            --warning-light: #FFFBEB;
            --info-color: #3B82F6;
            --info-hover: #2563EB;
            --info-light: #EFF6FF;
            --success-color: #10B981;
            --success-hover: #059669;
            --success-light: #ECFDF5;
            --light-color: #F3F4F6;
            --light-hover: #E5E7EB;
            --dark-color: #1F2937;
            --dark-hover: #111827;
            --border-color: #E5E7EB;
            --border-hover: #D1D5DB;
            --background-color: #F9FAFB;
            --text-color: #1F2937;
            --text-muted: #6B7280;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius-sm: 0.125rem;
            --radius: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-full: 9999px;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.5;
        }
        
        .card {
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            background-color: white;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background-color: white;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border-color);
            background-color: white;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-weight: 500;
            border-radius: var(--radius);
            transition: all 0.2s ease;
            cursor: pointer;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        
        .btn-primary:focus {
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.4);
            outline: none;
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-secondary:hover {
            background-color: var(--secondary-hover);
        }
        
        .btn-secondary:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.4);
            outline: none;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-danger:hover {
            background-color: var(--danger-hover);
        }
        
        .btn-danger:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.4);
            outline: none;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }
        
        .btn-outline:hover {
            background-color: var(--light-color);
            border-color: var(--border-hover);
        }
        
        .btn-outline:focus {
            box-shadow: 0 0 0 3px rgba(229, 231, 235, 0.4);
            outline: none;
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: var(--text-color);
            background-color: white;
            background-clip: padding-box;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }
        
        .form-select {
            display: block;
            width: 100%;
            padding: 0.5rem 2.25rem 0.5rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 400;
            line-height: 1.5;
            color: var(--text-color);
            background-color: white;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            appearance: none;
        }
        
        .form-select:focus {
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }
        
        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: var(--text-color);
            vertical-align: top;
            border-color: var(--border-color);
        }
        
        .table > :not(caption) > * > * {
            padding: 0.75rem;
            border-bottom-width: 1px;
            border-bottom-color: var(--border-color);
        }
        
        .table > thead {
            background-color: var(--light-color);
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .table > tbody > tr:hover {
            background-color: rgba(243, 244, 246, 0.5);
        }
        
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: rgba(249, 250, 251, 0.5);
        }
        
        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 600;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: var(--radius-full);
        }
        
        .badge-primary {
            background-color: var(--primary-color);
        }
        
        .badge-secondary {
            background-color: var(--secondary-color);
        }
        
        .badge-success {
            background-color: var(--success-color);
        }
        
        .badge-danger {
            background-color: var(--danger-color);
        }
        
        .badge-warning {
            background-color: var(--warning-color);
        }
        
        .badge-info {
            background-color: var(--info-color);
        }
        
        .alert {
            position: relative;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: var(--radius);
        }
        
        .alert-success {
            color: #0F766E;
            background-color: var(--success-light);
            border-color: #A7F3D0;
        }
        
        .alert-danger {
            color: #B91C1C;
            background-color: var(--danger-light);
            border-color: #FECACA;
        }
        
        .alert-warning {
            color: #92400E;
            background-color: var(--warning-light);
            border-color: #FDE68A;
        }
        
        .alert-info {
            color: #1E40AF;
            background-color: var(--info-light);
            border-color: #BFDBFE;
        }
        
        .result-row.editing {
            background-color: rgba(79, 70, 229, 0.05) !important;
            border-left: 3px solid var(--primary-color);
        }

        .marks-input {
            width: 5rem;
            text-align: center;
            padding: 0.375rem 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .marks-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
            outline: none;
        }

        .marks-input.invalid {
            border-color: var(--danger-color);
            background-color: var(--danger-light);
        }

        kbd {
            background-color: var(--light-color);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.1);
            color: var(--text-color);
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            padding: 0.25rem 0.5rem;
        }
        
        .grade-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 0.75rem;
            text-align: center;
            min-width: 2rem;
        }
        
        .grade-A-plus {
            background-color: #10B981;
            color: white;
        }
        
        .grade-A {
            background-color: #34D399;
            color: white;
        }
        
        .grade-B-plus {
            background-color: #3B82F6;
            color: white;
        }
        
        .grade-B {
            background-color: #60A5FA;
            color: white;
        }
        
        .grade-C-plus {
            background-color: #F59E0B;
            color: white;
        }
        
        .grade-C {
            background-color: #FBBF24;
            color: white;
        }
        
        .grade-D {
            background-color: #F97316;
            color: white;
        }
        
        .grade-F {
            background-color: #EF4444;
            color: white;
        }
        
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        .stat-card {
            transition: all 0.3s;
            border-radius: var(--radius-md);
            padding: 1rem;
            background-color: white;
            box-shadow: var(--shadow);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Loading spinner */
        .spinner {
            border: 3px solid var(--light-color);
            border-radius: 50%;
            border-top: 3px solid var(--primary-color);
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
        
        /* Grade Sheet Styles */
        @page {
            size: A4;
            margin: 0;
        }

        .grade-sheet-container {
            width: 21cm;
            min-height: 29.7cm;
            padding: 1cm;
            margin: 20px auto;
            background-color: white;
            box-shadow: var(--shadow-lg);
            position: relative;
            box-sizing: border-box;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(0, 0, 0, 0.03);
            z-index: 0;
            pointer-events: none;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            border-bottom: 2px solid #1a5276;
            padding-bottom: 10px;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 10px;
            background-color: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #555;
        }

        .title {
            font-weight: bold;
            font-size: 22px;
            margin-bottom: 5px;
            color: #1a5276;
        }

        .subtitle {
            font-size: 18px;
            margin-bottom: 5px;
            color: #2874a6;
        }

        .exam-title {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
            color: #1a5276;
            border: 2px solid #1a5276;
            display: inline-block;
            padding: 5px 15px;
            border-radius: 5px;
        }

        .student-info {
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .info-item {
            margin-bottom: 8px;
        }

        .info-label {
            font-weight: bold;
            color: #2874a6;
        }

        .grade-sheet-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .grade-sheet-table,
        .grade-sheet-table th,
        .grade-sheet-table td {
            border: 1px solid #bdc3c7;
        }

        .grade-sheet-table th,
        .grade-sheet-table td {
            padding: 10px;
            text-align: center;
        }

        .grade-sheet-table th {
            background-color: #1a5276;
            color: white;
            font-weight: bold;
        }

        .grade-sheet-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .summary {
            margin: 20px 0;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }

        .summary-item {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
        }

        .summary-label {
            font-weight: bold;
            color: #2874a6;
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 18px;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .signature {
            text-align: center;
            margin-top: 50px;
        }

        .signature-line {
            width: 80%;
            margin: 50px auto 10px;
            border-top: 1px solid #333;
        }

        .signature-title {
            font-weight: bold;
        }

        .grade-scale {
            margin-top: 20px;
            font-size: 12px;
            border: 1px solid #bdc3c7;
            padding: 10px;
            background-color: #f9f9f9;
        }

        .grade-title {
            font-weight: bold;
            margin-bottom: 5px;
            text-align: center;
        }

        .grade-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .grade-table th,
        .grade-table td {
            padding: 3px;
            text-align: center;
            border: 1px solid #ddd;
        }

        .qr-code {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 80px;
            height: 80px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #555;
        }
        
        /* Progress bar */
        .progress {
            height: 0.5rem;
            overflow: hidden;
            font-size: 0.75rem;
            background-color: var(--light-color);
            border-radius: var(--radius-full);
        }
        
        .progress-bar {
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            background-color: var(--primary-color);
            transition: width 0.6s ease;
            height: 100%;
        }
        
        /* Animations */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            body {
                font-size: 12pt;
                color: #000;
                background-color: #fff;
            }
            
            .container {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            thead {
                display: table-header-group;
            }
            
            tfoot {
                display: table-footer-group;
            }
            
            .grade-sheet-container {
                width: 100%;
                min-height: auto;
                padding: 0.5cm;
                margin: 0;
                box-shadow: none;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .card-header, .card-body, .card-footer {
                padding: 1rem;
            }
            
            .table > :not(caption) > * > * {
                padding: 0.5rem;
            }
            
            .marks-input {
                width: 4rem;
            }
            
            .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.75rem;
            }
        }
        
        /* Glassmorphism effect */
        .glass {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        /* Gradient backgrounds */
        .bg-gradient-primary {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        }
        
        .bg-gradient-secondary {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        }
        
        .bg-gradient-danger {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
        }
        
        .bg-gradient-warning {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
        }
        
        .bg-gradient-info {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
        }
        
        .bg-gradient-success {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        }
        
        /* Hover effects */
        .hover-lift {
            transition: transform 0.2s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-3px);
        }
        
        .hover-shadow {
            transition: box-shadow 0.2s ease;
        }
        
        .hover-shadow:hover {
            box-shadow: var(--shadow-lg);
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/teacher_topbar.php'; ?>
    
    <div class="flex">
        <?php include 'includes/teacher_sidebar.php'; ?>
        
        <!-- Mobile sidebar toggle -->
        <div class="fixed bottom-4 right-4 md:hidden z-20">
            <button id="mobile-sidebar-toggle" class="bg-indigo-600 text-white p-3 rounded-full shadow-lg">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <script>
            // Mobile sidebar toggle
            document.getElementById('mobile-sidebar-toggle').addEventListener('click', function() {
                const sidebar = document.querySelector('.sidebar');
                sidebar.classList.toggle('-translate-x-full');
            });
        </script>
        
        <div class="flex-1 p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Edit Results</h1>
                    <p class="text-sm text-gray-500">Manage and update student marks</p>
                </div>
                <div class="flex space-x-3">
                    <?php if (!$missing_params): ?>
                    <button onclick="window.print()" class="btn btn-outline no-print hover-lift">
                        <i class="fas fa-print mr-2"></i> Print Results
                    </button>
                    <?php endif; ?>
                    <a href="grade_sheet.php" class="btn btn-outline no-print hover-lift">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Grade Sheets
                    </a>
                </div>
            </div>
            
            <?php if (!empty($success_message)): ?>
            <div id="success-alert" class="alert alert-success fade-in no-print mb-6" role="alert">
                <div class="flex items-center">
                    <div class="py-1"><i class="fas fa-check-circle text-green-600 mr-3"></i></div>
                    <div>
                        <p class="font-bold">Success!</p>
                        <p><?php echo $success_message; ?></p>
                    </div>
                    <button type="button" class="ml-auto" onclick="document.getElementById('success-alert').style.display='none'">
                        <i class="fas fa-times text-green-600"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div id="error-alert" class="alert alert-danger fade-in no-print mb-6" role="alert">
                <div class="flex items-center">
                    <div class="py-1"><i class="fas fa-exclamation-circle text-red-600 mr-3"></i></div>
                    <div>
                        <p class="font-bold">Error!</p>
                        <p><?php echo $error_message; ?></p>
                    </div>
                    <button type="button" class="ml-auto" onclick="document.getElementById('error-alert').style.display='none'">
                        <i class="fas fa-times text-red-600"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($warning_message)): ?>
            <div id="warning-alert" class="alert alert-warning fade-in no-print mb-6" role="alert">
                <div class="flex items-center">
                    <div class="py-1"><i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i></div>
                    <div>
                        <p class="font-bold">Warning!</p>
                        <p><?php echo $warning_message; ?></p>
                    </div>
                    <button type="button" class="ml-auto" onclick="document.getElementById('warning-alert').style.display='none'">
                        <i class="fas fa-times text-yellow-600"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($missing_params): ?>
            <!-- Selection Form -->
            <div class="card mb-8 hover-shadow">
                <div class="card-header bg-gradient-primary text-white">
                    <h2 class="text-xl font-bold">
                        <i class="fas fa-clipboard-list mr-2"></i> Select Class, Subject and Exam
                    </h2>
                    <p class="text-sm mt-1 text-white text-opacity-80">Choose the class, subject, and exam to edit student results</p>
                </div>
                
                <div class="card-body">
                    <form action="edit_results.php" method="GET" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Subject Selection -->
                            <div>
                                <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                                <select id="subject_id" name="subject_id" required class="form-select" onchange="updateClassSelection()">
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subj): ?>
                                    <option value="<?php echo $subj['subject_id']; ?>" data-class-id="<?php echo $subj['class_id']; ?>">
                                        <?php echo $subj['subject_name']; ?> (<?php echo $subj['subject_code']; ?>) - 
                                        <?php echo $subj['class_name']; ?> <?php echo $subj['section']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Class Selection (will be auto-selected based on subject) -->
                            <div>
                                <label for="class_id" class="block text-sm font-medium text-gray-700 mb-2">Class</label>
                                <select id="class_id" name="class_id" required class="form-select">
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $cls): ?>
                                    <option value="<?php echo $cls['class_id']; ?>">
                                        <?php echo $cls['class_name']; ?> <?php echo $cls['section']; ?> (<?php echo $cls['academic_year']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Exam Selection -->
                            <div class="md:col-span-2">
                                <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-2">Exam</label>
                                <select id="exam_id" name="exam_id" required class="form-select">
                                    <option value="">Select Exam</option>
                                    <?php foreach ($exams as $ex): ?>
                                    <option value="<?php echo $ex['exam_id']; ?>">
                                        <?php echo $ex['exam_name']; ?> (<?php echo $ex['academic_year']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="submit" value="1" class="btn btn-primary hover-lift">
                                <i class="fas fa-search mr-2"></i> Load Results
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer bg-blue-50">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-500"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Information</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <p>Select a subject, class, and exam to edit student results. If you don't see your assigned subjects, please contact the administrator.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Exams Quick Access -->
            <div class="card hover-shadow">
                <div class="card-header bg-gradient-to-r from-purple-500 to-indigo-600 text-white">
                    <h2 class="text-xl font-bold">
                        <i class="fas fa-history mr-2"></i> Recent Exams
                    </h2>
                    <p class="text-sm mt-1 text-white text-opacity-80">Quick access to recent examination results</p>
                </div>
                
                <div class="card-body">
                    <?php if (count($exams) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Exam Name</th>
                                    <th>Type</th>
                                    <th>Academic Year</th>
                                    <th>Date</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Show only the 5 most recent exams
                                $recent_exams = array_slice($exams, 0, 5);
                                foreach ($recent_exams as $ex): 
                                ?>
                                <tr class="hover-lift">
                                    <td class="font-medium"><?php echo $ex['exam_name']; ?></td>
                                    <td><?php echo $ex['exam_type'] ?? 'N/A'; ?></td>
                                    <td><?php echo $ex['academic_year']; ?></td>
                                    <td>
                                        <?php 
                                        if (isset($ex['start_date'])) {
                                            echo date('d M Y', strtotime($ex['start_date']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-right">
                                        <a href="grade_sheet.php?exam_id=<?php echo $ex['exam_id']; ?>" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                            <i class="fas fa-eye mr-1"></i> View Results
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4 text-right">
                        <a href="grade_sheet.php" class="btn btn-outline hover-lift">
                            <i class="fas fa-list mr-2"></i> View All Grade Sheets
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">No exams found</h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>There are no exams available in the system. Please contact the administrator to create exams.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Subject and Exam Information -->
            <div class="card mb-8 no-print hover-shadow">
                <div class="card-header bg-gradient-primary text-white">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div>
                            <h2 class="text-2xl font-bold">
                                <?php echo isset($subject['subject_name']) ? $subject['subject_name'] : 'Subject'; ?>
                                <span class="text-sm font-normal opacity-80 ml-2"><?php echo isset($subject['subject_code']) ? '(' . $subject['subject_code'] . ')' : ''; ?></span>
                            </h2>
                            <p class="mt-1 opacity-90">
                                <?php echo isset($class['class_name']) ? $class['class_name'] : ''; ?>
                                <?php echo isset($class['section']) ? ' - ' . $class['section'] : ''; ?> | 
                                <?php echo isset($exam['exam_name']) ? $exam['exam_name'] : ''; ?>
                                <?php echo isset($exam['academic_year']) ? ' (' . $exam['academic_year'] . ')' : ''; ?>
                            </p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <div class="inline-flex items-center px-3 py-1 rounded-full bg-white bg-opacity-20 text-white text-sm">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <?php 
                                if (isset($exam['start_date']) && isset($exam['end_date'])) {
                                    echo date('d M Y', strtotime($exam['start_date'])) . ' to ' . date('d M Y', strtotime($exam['end_date']));
                                } else {
                                    echo 'Exam Period: N/A';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="stat-card bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 hover-lift">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-blue-600 font-medium uppercase tracking-wider">Theory</p>
                                    <p class="text-2xl font-bold text-blue-700 mt-1">
                                        <?php echo isset($subject['full_marks_theory']) ? $subject['full_marks_theory'] : '0'; ?>
                                    </p>
                                    <p class="text-xs text-blue-500 mt-1">
                                        Pass: <?php echo isset($subject['pass_marks_theory']) ? $subject['pass_marks_theory'] : '0'; ?> marks
                                    </p>
                                </div>
                                <div class="bg-blue-100 p-3 rounded-full">
                                    <i class="fas fa-book text-blue-500 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card bg-gradient-to-br from-green-50 to-emerald-50 border border-green-100 hover-lift">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-green-600 font-medium uppercase tracking-wider">Practical</p>
                                    <p class="text-2xl font-bold text-green-700 mt-1">
                                        <?php echo isset($subject['full_marks_practical']) ? $subject['full_marks_practical'] : '0'; ?>
                                    </p>
                                    <p class="text-xs text-green-500 mt-1">
                                        Pass: <?php echo isset($subject['pass_marks_practical']) ? $subject['pass_marks_practical'] : '0'; ?> marks
                                    </p>
                                </div>
                                <div class="bg-green-100 p-3 rounded-full">
                                    <i class="fas fa-flask text-green-500 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card bg-gradient-to-br from-purple-50 to-fuchsia-50 border border-purple-100 hover-lift">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-purple-600 font-medium uppercase tracking-wider">Total</p>
                                    <p class="text-2xl font-bold text-purple-700 mt-1">
                                        <?php echo $total_full_marks; ?>
                                    </p>
                                    <p class="text-xs text-purple-500 mt-1">
                                        Credit: <?php echo $subject['credit_hours'] ?? 1; ?>
                                    </p>
                                </div>
                                <div class="bg-purple-100 p-3 rounded-full">
                                    <i class="fas fa-chart-pie text-purple-500 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-100 hover-lift">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-amber-600 font-medium uppercase tracking-wider">Students</p>
                                    <p class="text-2xl font-bold text-amber-700 mt-1">
                                        <?php echo count($students); ?>
                                    </p>
                                    <div class="flex items-center mt-1">
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 mr-2">
                                            <div class="bg-amber-500 h-1.5 rounded-full" style="width: <?php echo ($stats['total_students'] > 0) ? ($stats['results_entered'] / $stats['total_students'] * 100) : 0; ?>%"></div>
                                        </div>
                                        <p class="text-xs text-amber-500">
                                            <?php echo $stats['results_entered']; ?> results
                                        </p>
                                    </div>
                                </div>
                                <div class="bg-amber-100 p-3 rounded-full">
                                    <i class="fas fa-users text-amber-500 text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover-lift">
                            <div class="flex items-center">
                                <div class="bg-blue-100 p-2 rounded-full mr-3">
                                    <i class="fas fa-chart-line text-blue-500"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase">Average Marks</p>
                                    <p class="text-lg font-bold text-gray-800"><?php echo round($stats['average_marks'], 2); ?></p>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: <?php echo min(100, ($stats['average_marks'] / max(1, $total_full_marks)) * 100); ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover-lift">
                            <div class="flex items-center">
                                <div class="bg-green-100 p-2 rounded-full mr-3">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase">Pass Rate</p>
                                    <p class="text-lg font-bold text-gray-800"><?php echo round($stats['pass_percentage']); ?>%</p>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-green-500 h-1.5 rounded-full" style="width: <?php echo $stats['pass_percentage']; ?>%"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo $stats['pass_count']; ?> passed, <?php echo $stats['fail_count']; ?> failed
                                </p>
                            </div>
                        </div>
                        
                        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover-lift">
                            <div class="flex items-center">
                                <div class="bg-purple-100 p-2 rounded-full mr-3">
                                    <i class="fas fa-trophy text-purple-500"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase">Highest / Lowest</p>
                                    <p class="text-lg font-bold text-gray-800">
                                        <?php echo round($stats['highest_marks'], 1); ?> / <?php echo round($stats['lowest_marks'], 1); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>Min</span>
                                    <span>Max</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5 relative">
                                    <div class="absolute h-3 w-3 bg-red-500 rounded-full -top-1" style="left: <?php echo ($total_full_marks > 0) ? ($stats['lowest_marks'] / $total_full_marks) * 100 : 0; ?>%"></div>
                                    <div class="absolute h-3 w-3 bg-purple-500 rounded-full -top-1" style="left: <?php echo ($total_full_marks > 0) ? ($stats['highest_marks'] / $total_full_marks) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Results Entry Form -->
            <?php if (empty($students)): ?>
            <div class="alert alert-warning no-print">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">No students found</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>There are no students enrolled in this class. Please contact the administrator.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            
            <!-- Grade Sheet View (for printing) -->
            <div class="print-only">
                <div class="grade-sheet-container">
                    <div class="watermark">OFFICIAL</div>

                    <div class="header">
                        <div class="logo">LOGO</div>
                        <div class="title"><?php echo isset($settings['school_name']) ? strtoupper($settings['school_name']) : 'GOVERNMENT OF NEPAL'; ?></div>
                        <div class="title"><?php echo isset($settings['result_header']) ? strtoupper($settings['result_header']) : 'NATIONAL EXAMINATION BOARD'; ?></div>
                        <div class="subtitle">SECONDARY EDUCATION EXAMINATION</div>
                        <div class="exam-title">GRADE SHEET</div>
                    </div>

                    <?php if (count($students) > 0): ?>
                    <div class="student-info">
                        <div class="info-item">
                            <span class="info-label">Student Name:</span>
                            <span><?php echo $students[0]['full_name']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Roll No:</span>
                            <span><?php echo $students[0]['roll_number']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Registration No:</span>
                            <span><?php echo $students[0]['registration_number']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Class:</span>
                            <span><?php echo $class['class_name'] . ' ' . $class['section']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Examination:</span>
                            <span><?php echo $exam['exam_name']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Academic Year:</span>
                            <span><?php echo $exam['academic_year']; ?></span>
                        </div>
                        <?php if (!empty($exam['exam_type'])): ?>
                        <div class="info-item">
                            <span class="info-label">Exam Type:</span>
                            <span><?php echo $exam['exam_type']; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <table class="grade-sheet-table">
    <thead>
        <tr>
            <th>ROLL NO.</th>
            <th>STUDENT NAME</th>
            <th>SUBJECT CODE</th>
            <th>SUBJECTS</th>
            <th>CREDIT HOUR</th>
            <th>THEORY MARKS</th>
            <th>PRACTICAL MARKS</th>
            <th>TOTAL MARKS</th>
            <th>GRADE</th>
            <th>REMARKS</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($students) > 0): ?>
        <tr>
            <td><?php echo $students[0]['roll_number']; ?></td>
            <td><?php echo $students[0]['full_name']; ?></td>
            <td><?php echo $subject['subject_code']; ?></td>
            <td><?php echo $subject['subject_name']; ?></td>
            <td><?php echo $subject['credit_hours'] ?? 1; ?></td>
            <td><?php echo isset($students[0]['theory_marks']) ? $students[0]['theory_marks'] : 'N/A'; ?></td>
            <td><?php echo isset($students[0]['practical_marks']) ? $students[0]['practical_marks'] : 'N/A'; ?></td>
            <td><?php echo isset($students[0]['total_marks']) ? $students[0]['total_marks'] : 'N/A'; ?></td>
            <td><?php echo isset($students[0]['grade']) ? $students[0]['grade'] : 'N/A'; ?></td>
            <td><?php echo isset($students[0]['remarks']) ? $students[0]['remarks'] : 'N/A'; ?></td>
        </tr>
        <?php else: ?>
        <tr>
            <td colspan="10" class="text-center">No results found</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

                    <div class="summary">
                        <div class="summary-item">
                            <div class="summary-label">TOTAL MARKS</div>
                            <div class="summary-value">
                                <?php 
                                $total_marks = isset($students[0]['total_marks']) ? $students[0]['total_marks'] : 0;
                                echo $total_marks . ' / ' . $total_full_marks; 
                                ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">PERCENTAGE</div>
                            <div class="summary-value">
                                <?php 
                                $percentage = isset($students[0]['percentage']) ? $students[0]['percentage'] : 0;
                                echo number_format($percentage, 2) . '%'; 
                                ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">GPA</div>
                            <div class="summary-value">
                                <?php 
                                $gpa = isset($students[0]['gpa']) ? $students[0]['gpa'] : 0;
                                echo number_format($gpa, 2); 
                                ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">RESULT</div>
                            <div class="summary-value">
                                <?php 
                                $remarks = isset($students[0]['remarks']) ? $students[0]['remarks'] : '';
                                echo $remarks; 
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grade-scale">
                        <div class="grade-title">GRADING SCALE</div>
                        <table class="grade-table">
                            <tr>
                                <th>Grade</th>
                                <th>A+</th>
                                <th>A</th>
                                <th>B+</th>
                                <th>B</th>
                                <th>C+</th>
                                <th>C</th>
                                <th>D</th>
                                <th>F</th>
                            </tr>
                            <tr>
                                <th>Percentage</th>
                                <td>90-100</td>
                                <td>80-89</td>
                                <td>70-79</td>
                                <td>60-69</td>
                                <td>50-59</td>
                                <td>40-49</td>
                                <td>33-39</td>
                                <td>0-32</td>
                            </tr>
                            <tr>
                                <th>Grade Point</th>
                                <td>4.0</td>
                                <td>3.7</td>
                                <td>3.3</td>
                                <td>3.0</td>
                                <td>2.7</td>
                                <td>2.3</td>
                                <td>1.0</td>
                                <td>0.0</td>
                            </tr>
                        </table>
                    </div>

                    <div class="footer">
                        <div class="signature">
                            <div class="signature-line"></div>
                            <div class="signature-title">PREPARED BY</div>
                            <div><?php echo $prepared_by; ?></div>
                        </div>
                        <div class="signature">
                            <div class="signature-line"></div>
                            <div class="signature-title">PRINCIPAL</div>
                            <div>SCHOOL PRINCIPAL</div>
                        </div>
                    </div>

                    <div class="qr-code">QR CODE</div>

                    <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #777;">
                        <p><?php echo isset($settings['result_footer']) ? $settings['result_footer'] : 'This is a computer-generated document. No signature is required.'; ?></p>
                        <p>Issue Date: <?php echo date('d-m-Y', strtotime($issue_date)); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Results Entry Form (for editing) -->
            <form method="POST" action="" id="results-form" class="no-print">
                <div class="card hover-shadow">
                    <div class="card-header bg-gradient-primary text-white">
                        <div class="flex justify-between items-center">
                            <h2 class="text-xl font-bold">
                                <i class="fas fa-edit mr-2"></i> Edit Student Results
                            </h2>
                            <div class="text-sm">
                                <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">
                                    <i class="fas fa-users mr-1"></i> <?php echo count($students); ?> Students
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 border-b border-blue-100">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-lightbulb text-yellow-500 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">Quick Tips</h3>
                                <div class="mt-1 text-sm text-blue-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li>Enter theory and practical marks for each student</li>
                                        <li>Press <kbd class="px-2 py-1 bg-gray-100 rounded text-xs">Enter</kbd> to move to the next field</li>
                                        <li>Grades are calculated automatically based on the marks</li>
                                        <li>Click <span class="font-medium">Save Results</span> when finished</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="table">
    <thead>
    <tr class="bg-gradient-to-r from-gray-100 to-gray-50 text-gray-700 uppercase text-xs font-medium tracking-wider">
        <th class="py-3 px-6 text-left">Roll No.</th>
        <th class="py-3 px-6 text-left">Student Name</th>
        <th class="py-3 px-6 text-center">SUBJECT CODE</th>
        <th class="py-3 px-6 text-left">SUBJECTS</th>
        <th class="py-3 px-6 text-center">CREDIT HOUR</th>
        <th class="py-3 px-6 text-center">THEORY MARKS
            <span class="text-xs text-gray-500 block">
                (Max: <?php echo $full_marks_theory; ?>)
            </span>
        </th>
        <?php if ($full_marks_practical > 0): ?>
        <th class="py-3 px-6 text-center">PRACTICAL MARKS
            <span class="text-xs text-gray-500 block">
                (Max: <?php echo $full_marks_practical; ?>)
            </span>
        </th>
        <?php endif; ?>
        <th class="py-3 px-6 text-center">TOTAL MARKS</th>
        <th class="py-3 px-6 text-center">GRADE</th>
        <th class="py-3 px-6 text-center">REMARKS</th>
    </tr>
    </thead>
    <tbody>
        <?php if (empty($students)): ?>
            <tr>
                <td colspan="10" class="py-4 px-6 text-center">No students found in this class.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($students as $index => $student): ?>
            <tr class="border-b border-gray-200 hover:bg-gray-100 result-row hover-lift" 
                id="student-row-<?php echo $index; ?>"
                data-theory-full-marks="<?php echo $full_marks_theory; ?>"
                data-practical-full-marks="<?php echo $full_marks_practical; ?>"
                data-theory-pass-marks="<?php echo isset($subject['pass_marks_theory']) ? $subject['pass_marks_theory'] : 33; ?>"
                data-practical-pass-marks="<?php echo isset($subject['pass_marks_practical']) ? $subject['pass_marks_practical'] : 0; ?>">
                <td class="py-3 px-6 text-left whitespace-nowrap">
                    <div class="flex items-center">
                        <span class="font-medium"><?php echo $student['roll_number']; ?></span>
                    </div>
                </td>
                <td class="py-3 px-6 text-left">
                    <div class="flex items-center">
                        <div class="mr-2">
                            <i class="fas fa-user-graduate text-indigo-500"></i>
                        </div>
                        <span><?php echo $student['full_name']; ?></span>
                    </div>
                    <div class="text-xs text-gray-500"><?php echo $student['registration_number'] ?? 'N/A'; ?></div>
                </td>
                <td class="py-3 px-6 text-center">
                    <?php echo $subject['subject_code']; ?>
                </td>
                <td class="py-3 px-6 text-left">
                    <?php echo $subject['subject_name']; ?>
                </td>
                <td class="py-3 px-6 text-center">
                    <?php echo $subject['credit_hours'] ?? 1; ?>
                </td>
                <td class="py-3 px-6 text-center">
                    <input type="hidden" name="student_id[<?php echo $index; ?>]" value="<?php echo $student['student_id']; ?>">
                    <input type="hidden" name="result_id[<?php echo $index; ?>]" value="<?php echo $student['result_id'] ?? ''; ?>">
                    <input type="number" name="theory_marks[<?php echo $index; ?>]" 
                           value="<?php echo isset($student['theory_marks']) ? $student['theory_marks'] : ''; ?>" 
                           min="0" max="<?php echo $full_marks_theory; ?>" step="0.01"
                           class="marks-input"
                           data-index="<?php echo $index; ?>" data-type="theory"
                           onchange="validateMarks(this)" onfocus="highlightRow(<?php echo $index; ?>)">
                </td>
                <?php if ($full_marks_practical > 0): ?>
                <td class="py-3 px-6 text-center">
                    <input type="number" name="practical_marks[<?php echo $index; ?>]" 
                           value="<?php echo isset($student['practical_marks']) ? $student['practical_marks'] : ''; ?>" 
                           min="0" max="<?php echo $full_marks_practical; ?>" step="0.01"
                           class="marks-input"
                           data-index="<?php echo $index; ?>" data-type="practical"
                           onchange="validateMarks(this)" onfocus="highlightRow(<?php echo $index; ?>)">
                </td>
                <?php endif; ?>
                <td class="py-3 px-6 text-center">
                    <span id="total-marks-<?php echo $index; ?>" class="font-medium">
                        <?php echo isset($student['total_marks']) ? $student['total_marks'] : '0'; ?>
                    </span>
                </td>
                <td class="py-3 px-6 text-center">
                    <span id="grade-<?php echo $index; ?>" class="grade-badge <?php 
                        if (isset($student['grade'])) {
                            $grade = str_replace('+', '-plus', $student['grade']);
                            echo 'grade-' . $grade;
                        } else {
                            echo 'bg-gray-200 text-gray-800';
                        }
                    ?>">
                        <?php echo isset($student['grade']) ? $student['grade'] : 'N/A'; ?>
                    </span>
                </td>
                <td class="py-3 px-6 text-center">
                    <span id="status-<?php echo $index; ?>" class="px-2 py-1 rounded-full text-xs <?php 
                        if (isset($student['remarks'])) {
                            echo ($student['remarks'] == 'Pass') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                        } else {
                            echo 'bg-gray-100 text-gray-800';
                        }
                    ?>">
                        <?php echo isset($student['remarks']) ? $student['remarks'] : 'Not Graded'; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
                    </div>
                    
                    <div class="card-footer flex justify-between items-center">
    <div class="text-sm text-gray-600 flex items-center">
        <i class="fas fa-info-circle text-indigo-500 mr-2"></i>
        <span>Total Students: <strong><?php echo count($students); ?></strong> | Results Entered: <strong><?php echo $stats['results_entered']; ?></strong></span>
    </div>
    <div class="flex space-x-3">
        <button type="button" onclick="window.location.href='grade_sheet.php'" class="btn btn-outline hover-lift">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </button>
        
        <button type="submit" name="save_results" id="save-button" class="btn btn-primary hover-lift">
            <i class="fas fa-save mr-2"></i> Save Results
        </button>
    </div>
</div>
                </div>
            </form>
            <?php endif; ?>
            
            
        </div>
    </div>
    
    <script>
        // Highlight the row being edited
        function highlightRow(index) {
            // Remove highlighting from all rows
            document.querySelectorAll('.result-row').forEach(row => {
                row.classList.remove('editing');
            });
            
            // Add highlighting to the current row
            document.getElementById('student-row-' + index).classList.add('editing');
        }
        
        // Validate marks and update total
        function validateMarks(input) {
            const index = input.dataset.index;
            const type = input.dataset.type;
            const value = parseFloat(input.value) || 0;
            
            // Get max marks
            const maxMarks = parseFloat(input.max) || 100;
            
            // Validate input
            if (value < 0) {
                input.value = 0;
            } else if (value > maxMarks) {
                input.value = maxMarks;
                input.classList.add('invalid');
                setTimeout(() => {
                    input.classList.remove('invalid');
                }, 2000);
            }
            
            // Calculate total marks
            let totalMarks = 0;
            
            // Get theory marks
            const theoryInput = document.querySelector(`input[name="theory_marks[${index}]"]`);
            const theoryMarks = theoryInput ? (parseFloat(theoryInput.value) || 0) : 0;
            
            // Get practical marks if applicable
            const practicalInput = document.querySelector(`input[name="practical_marks[${index}]"]`);
            const practicalMarks = practicalInput ? (parseFloat(practicalInput.value) || 0) : 0;
            
            totalMarks = theoryMarks + practicalMarks;
            
            // Update total marks display
            const totalElement = document.getElementById('total-marks-' + index);
            if (totalElement) {
                totalElement.textContent = totalMarks.toFixed(2);
            }
            
            // Calculate percentage and update grade/status
            calculateGradeAndStatus(index, theoryMarks, practicalMarks, totalMarks);
        }
        
        // Calculate and update grade and status based on marks
        function calculateGradeAndStatus(index, theoryMarks, practicalMarks, totalMarks) {
            // Get subject details from data attributes
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
            let grade = '';
            if (passed) {
                if (percentage >= 90) grade = 'A+';
                else if (percentage >= 80) grade = 'A';
                else if (percentage >= 70) grade = 'B+';
                else if (percentage >= 60) grade = 'B';
                else if (percentage >= 50) grade = 'C+';
                else if (percentage >= 40) grade = 'C';
                else if (percentage >= 33) grade = 'D';
                else grade = 'F';
            } else {
                grade = 'F';
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
                
                // Update text
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
            const warningAlert = document.getElementById('warning-alert');
            
            if (successAlert) {
                successAlert.style.display = 'none';
            }
            
            if (errorAlert) {
                errorAlert.style.display = 'none';
            }
            
            if (warningAlert) {
                warningAlert.style.display = 'none';
            }
        }, 5000);
        
        // Add keyboard navigation for faster data entry
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                const activeElement = document.activeElement;
                if (activeElement.tagName === 'INPUT' && activeElement.type === 'number') {
                    e.preventDefault();
                    
                    // Get all mark inputs
                    const inputs = Array.from(document.querySelectorAll('input[type="number"].marks-input'));
                    const currentIndex = inputs.indexOf(activeElement);
                    
                    // Move to next input
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
                
                // Set the class dropdown to match the subject's class
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
            
            // Initialize all marks inputs
            const inputs = document.querySelectorAll('input[type="number"].marks-input');
            inputs.forEach(input => {
                validateMarks(input);
            });
        });
    </script>
</body>
</html>