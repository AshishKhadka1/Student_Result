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

// Get subject ID, class ID, and exam ID from URL parameters
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

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
    $subject_query = "SELECT subject_name, subject_code, 
                     theory_full_marks as full_marks_theory, practical_full_marks as full_marks_practical, 
                     theory_pass_marks as pass_marks_theory, practical_pass_marks as pass_marks_practical
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
if (!$missing_params && empty($error_message)) {
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
    $students_query = "SELECT s.student_id, s.roll_number, s.registration_number, u.full_name,
                      r.result_id, r.theory_marks, r.practical_marks, r.total_marks, r.grade, r.remarks
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
        foreach ($_POST['student_id'] as $index => $student_id) {
            $theory_marks = isset($_POST['theory_marks'][$index]) ? floatval($_POST['theory_marks'][$index]) : 0;
            $practical_marks = isset($_POST['practical_marks'][$index]) ? floatval($_POST['practical_marks'][$index]) : 0;
            $result_id = $_POST['result_id'][$index];
            
            // Skip if no marks are entered
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
                $stmt->bind_param("iiiddddssdsi", $student_id, $subject_id, $exam_id, $theory_marks, $practical_marks, 
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
        
        // Commit transaction
        $conn->commit();
        $success_message = "Results saved successfully!";
        
        // Refresh the student data
        $stmt = $conn->prepare($students_query);
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("iii", $subject_id, $exam_id, $class_id);
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
    'total_marks' => 0
];

if (!$missing_params) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Results - <?php echo isset($subject['subject_name']) ? $subject['subject_name'] : 'Subject'; ?></title>
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
        
        .result-row:hover {
            background-color: #F3F4F6;
        }
        
        .result-row.editing {
            background-color: #EFF6FF;
            border-left: 3px solid #3B82F6;
        }
        
        .marks-input {
            transition: all 0.2s;
        }
        
        .marks-input:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        
        .marks-input.invalid {
            border-color: #EF4444;
            background-color: #FEF2F2;
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
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Loading spinner */
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
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/teacher_topbar.php'; ?>
    
    <div class="flex">
        <?php include 'includes/teacher_sidebar.php'; ?>
        
        <!-- Mobile sidebar toggle -->
        <div class="fixed bottom-4 right-4 md:hidden z-20">
            <button id="mobile-sidebar-toggle" class="bg-blue-600 text-white p-3 rounded-full shadow-lg">
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
                <h1 class="text-2xl font-bold text-gray-800">Edit Results</h1>
                <div class="flex space-x-3">
                    <?php if (!$missing_params): ?>
                    <button onclick="window.print()" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded no-print">
                        <i class="fas fa-print mr-2"></i> Print Results
                    </button>
                    <?php endif; ?>
                    <a href="grade_sheet.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded no-print">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Grade Sheets
                    </a>
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
            
            <?php if ($missing_params): ?>
            <!-- Selection Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-clipboard-list text-blue-500 mr-2"></i> Select Class, Subject and Exam
                </h2>
                
                <form action="edit_results.php" method="GET" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Subject and Class Selection -->
                        <div>
                            <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                            <select id="subject_id" name="subject_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" onchange="updateClassSelection()">
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
                            <select id="class_id" name="class_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
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
                            <select id="exam_id" name="exam_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
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
                        <button type="submit" name="submit" value="1" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-search mr-2"></i> Load Results
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 bg-blue-50 p-4 rounded-md">
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
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-history text-blue-500 mr-2"></i> Recent Exams
                </h2>
                
                <?php if (count($exams) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            // Show only the 5 most recent exams
                            $recent_exams = array_slice($exams, 0, 5);
                            foreach ($recent_exams as $ex): 
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $ex['exam_name']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $ex['exam_type'] ?? 'N/A'; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $ex['academic_year']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    if (isset($ex['start_date'])) {
                                        echo date('d M Y', strtotime($ex['start_date']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="grade_sheet.php?exam_id=<?php echo $ex['exam_id']; ?>" class="text-blue-600 hover:text-blue-900">View Results</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 text-right">
                    <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-list mr-2"></i> View All Grade Sheets
                    </a>
                </div>
                <?php else: ?>
                <div class="bg-yellow-50 p-4 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
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
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="text-md font-semibold text-gray-700 mb-3">Marks Distribution</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Theory Marks:</p>
                                <p class="font-medium">
                                    Full Marks: <?php echo isset($subject['full_marks_theory']) ? $subject['full_marks_theory'] : '0'; ?>
                                </p>
                                <p class="font-medium">
                                    Pass Marks: <?php echo isset($subject['pass_marks_theory']) ? $subject['pass_marks_theory'] : '0'; ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Practical Marks:</p>
                                <p class="font-medium">
                                    Full Marks: <?php echo isset($subject['full_marks_practical']) ? $subject['full_marks_practical'] : '0'; ?>
                                </p>
                                <p class="font-medium">
                                    Pass Marks: <?php echo isset($subject['pass_marks_practical']) ? $subject['pass_marks_practical'] : '0'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="text-md font-semibold text-gray-700 mb-3">Exam Period</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Start Date:</p>
                                <p class="font-medium">
                                    <?php echo isset($exam['start_date']) ? date('d M Y', strtotime($exam['start_date'])) : 'N/A'; ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">End Date:</p>
                                <p class="font-medium">
                                    <?php echo isset($exam['end_date']) ? date('d M Y', strtotime($exam['end_date'])) : 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Class Statistics -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar text-blue-500 mr-2"></i> Class Statistics
                </h2>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="stat-card bg-blue-50 rounded-lg p-4 border border-blue-100">
                        <p class="text-sm text-blue-600 mb-1">Results Entered</p>
                        <p class="text-2xl font-bold text-blue-700"><?php echo $stats['results_entered']; ?> / <?php echo $stats['total_students']; ?></p>
                        <p class="text-xs text-blue-500 mt-1">
                            <?php echo ($stats['total_students'] > 0) ? round(($stats['results_entered'] / $stats['total_students']) * 100) : 0; ?>% Complete
                        </p>
                    </div>
                    
                    <div class="stat-card bg-green-50 rounded-lg p-4 border border-green-100">
                        <p class="text-sm text-green-600 mb-1">Pass Rate</p>
                        <p class="text-2xl font-bold text-green-700"><?php echo round($stats['pass_percentage']); ?>%</p>
                        <p class="text-xs text-green-500 mt-1">
                            <?php echo $stats['pass_count']; ?> Passed, <?php echo $stats['fail_count']; ?> Failed
                        </p>
                    </div>
                    
                    <div class="stat-card bg-purple-50 rounded-lg p-4 border border-purple-100">
                        <p class="text-sm text-purple-600 mb-1">Average Marks</p>
                        <p class="text-2xl font-bold text-purple-700"><?php echo round($stats['average_marks'], 2); ?></p>
                        <p class="text-xs text-purple-500 mt-1">
                            Out of <?php echo isset($subject['full_marks_theory']) && isset($subject['full_marks_practical']) ? 
                                ($subject['full_marks_theory'] + $subject['full_marks_practical']) : '100'; ?>
                        </p>
                    </div>
                    
                    <div class="stat-card bg-yellow-50 rounded-lg p-4 border border-yellow-100">
                        <p class="text-sm text-yellow-600 mb-1">Highest / Lowest</p>
                        <p class="text-2xl font-bold text-yellow-700"><?php echo round($stats['highest_marks'], 2); ?> / <?php echo round($stats['lowest_marks'], 2); ?></p>
                        <p class="text-xs text-yellow-500 mt-1">
                            Range: <?php echo round($stats['highest_marks'] - $stats['lowest_marks'], 2); ?> marks
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
            <form method="POST" action="" id="results-form">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6 bg-gray-50 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-edit text-blue-500 mr-2"></i> Edit Student Results
                        </h2>
                        <p class="text-gray-600 mt-1">Enter marks for each student below. Fields will be validated automatically.</p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-left">Roll No.</th>
                                    <th class="py-3 px-6 text-left">Student Name</th>
                                    <th class="py-3 px-6 text-center">Theory Marks
                                        <span class="text-xs text-gray-500 block">
                                            (Max: <?php echo isset($subject['full_marks_theory']) ? $subject['full_marks_theory'] : '100'; ?>)
                                        </span>
                                    </th>
                                    <?php if (isset($subject['full_marks_practical']) && $subject['full_marks_practical'] > 0): ?>
                                    <th class="py-3 px-6 text-center">Practical Marks
                                        <span class="text-xs text-gray-500 block">
                                            (Max: <?php echo $subject['full_marks_practical']; ?>)
                                        </span>
                                    </th>
                                    <?php endif; ?>
                                    <th class="py-3 px-6 text-center">Total Marks</th>
                                    <th class="py-3 px-6 text-center">Grade</th>
                                    <th class="py-3 px-6 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm">
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="7" class="py-4 px-6 text-center">No students found in this class.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $index => $student): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-100 result-row" 
                                        id="student-row-<?php echo $index; ?>"
                                        data-theory-full-marks="<?php echo isset($subject['full_marks_theory']) ? $subject['full_marks_theory'] : 100; ?>"
                                        data-practical-full-marks="<?php echo isset($subject['full_marks_practical']) ? $subject['full_marks_practical'] : 0; ?>"
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
                                                    <i class="fas fa-user-graduate text-blue-500"></i>
                                                </div>
                                                <span><?php echo $student['full_name']; ?></span>
                                            </div>
                                            <div class="text-xs text-gray-500"><?php echo $student['registration_number'] ?? 'N/A'; ?></div>
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            <input type="hidden" name="student_id[<?php echo $index; ?>]" value="<?php echo $student['student_id']; ?>">
                                            <input type="hidden" name="result_id[<?php echo $index; ?>]" value="<?php echo $student['result_id'] ?? ''; ?>">
                                            <input type="number" name="theory_marks[<?php echo $index; ?>]" 
                                                   value="<?php echo isset($student['theory_marks']) ? $student['theory_marks'] : ''; ?>" 
                                                   min="0" max="<?php echo isset($subject['full_marks_theory']) ? $subject['full_marks_theory'] : '100'; ?>" step="0.01"
                                                   class="w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 marks-input"
                                                   data-index="<?php echo $index; ?>" data-type="theory"
                                                   onchange="validateMarks(this)" onfocus="highlightRow(<?php echo $index; ?>)">
                                        </td>
                                        <?php if (isset($subject['full_marks_practical']) && $subject['full_marks_practical'] > 0): ?>
                                        <td class="py-3 px-6 text-center">
                                            <input type="number" name="practical_marks[<?php echo $index; ?>]" 
                                                   value="<?php echo isset($student['practical_marks']) ? $student['practical_marks'] : ''; ?>" 
                                                   min="0" max="<?php echo $subject['full_marks_practical']; ?>" step="0.01"
                                                   class="w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 marks-input"
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
                    
                    <div class="p-6 bg-gray-50 border-t border-gray-200 flex justify-between items-center no-print">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                            Grades are calculated automatically based on the marks entered.
                        </div>
                        <div class="flex space-x-3">
                            <button type="button" onclick="window.location.href='grade_sheet.php'" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </button>
                            
                            <button type="submit" name="save_results" id="save-button" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-save mr-2"></i> Save Results
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
            
            <!-- Grading Information -->
            <div class="bg-white rounded-lg shadow-md p-6 mt-8 no-print">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i> Grading Information
                </h2>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-8 gap-3">
                    <?php foreach ($grading_system as $grade): ?>
                    <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-200">
                        <div class="grade-badge grade-<?php echo str_replace('+', '-plus', $grade['grade']); ?> mx-auto mb-2">
                            <?php echo $grade['grade']; ?>
                        </div>
                        <p class="text-xs text-gray-600"><?php echo $grade['min_percentage']; ?>% and above</p>
                        <p class="text-xs font-medium">GPA: <?php echo $grade['gpa']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4 text-sm text-gray-600">
                    <p><i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i> Students must pass both theory and practical components to receive a passing grade.</p>
                    <p><i class="fas fa-info-circle text-blue-500 mr-1"></i> Theory pass marks: <?php echo isset($subject['pass_marks_theory']) ? $subject['pass_marks_theory'] : '0'; ?>, 
                       Practical pass marks: <?php echo isset($subject['pass_marks_practical']) ? $subject['pass_marks_practical'] : '0'; ?></p>
                </div>
            </div>
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
            
            if (successAlert) {
                successAlert.style.display = 'none';
            }
            
            if (errorAlert) {
                errorAlert.style.display = 'none';
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
        }
    );

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
});
</script>
</body>
</html>
