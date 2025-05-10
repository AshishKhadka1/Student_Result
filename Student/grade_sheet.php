<?php
// Start session for authentication check
session_start();

// Check if user is logged in as a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Connect to database with error handling
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Initialize variables
$student = [];
$subjects = [];
$gpa = 0;
$percentage = 0;
$division = '';
$total_marks = 0;
$max_marks = 0;
$issue_date = date('Y-m-d');

// Get student information
try {
    $stmt = $conn->prepare("
        SELECT s.student_id, s.roll_number, s.registration_number, u.full_name, 
               c.class_name, c.section, c.academic_year
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        JOIN classes c ON s.class_id = c.class_id
        WHERE s.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        die("Student record not found. Please contact administrator.");
    }

    $student = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching student details: " . $e->getMessage());
    die("An error occurred while retrieving student information. Please try again later.");
}

// Get available exams for this student
$exams = [];
try {
    // First try to get exams with exam_type field
    $query = "
        SELECT DISTINCT e.exam_id, e.exam_name, e.exam_type, e.academic_year 
        FROM exams e
        JOIN results r ON e.exam_id = r.exam_id
        WHERE r.student_id = ?
        ORDER BY e.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("s", $student['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // If exam_type is NULL or empty, use exam_name as the type
        if (empty($row['exam_type'])) {
            $row['exam_type'] = $row['exam_name'];
        }
        $exams[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching exams: " . $e->getMessage());
    
    // Try an alternative query if the first one fails (e.g., if exam_type column doesn't exist)
    try {
        $alt_query = "
            SELECT DISTINCT e.exam_id, e.exam_name, e.academic_year 
            FROM exams e
            JOIN results r ON e.exam_id = r.exam_id
            WHERE r.student_id = ?
            ORDER BY e.created_at DESC
        ";
        
        $stmt = $conn->prepare($alt_query);
        if ($stmt === false) {
            throw new Exception("Failed to prepare alternative statement: " . $conn->error);
        }
        
        $stmt->bind_param("s", $student['student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Add exam_type field with the same value as exam_name
            $row['exam_type'] = $row['exam_name'];
            $exams[] = $row;
        }
        $stmt->close();
    } catch (Exception $e2) {
        error_log("Error fetching exams (alternative query): " . $e2->getMessage());
    }
}

// Get available exam types for filtering
$exam_types = [];
try {
    // Check if exam_type column exists in exams table
    $check_column = $conn->query("SHOW COLUMNS FROM exams LIKE 'exam_type'");
    
    if ($check_column->num_rows > 0) {
        // Column exists, get distinct exam types
        $stmt = $conn->prepare("SELECT DISTINCT exam_type FROM exams WHERE exam_type IS NOT NULL ORDER BY exam_type");
        if ($stmt === false) {
            throw new Exception("Failed to prepare exam types statement: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['exam_type'])) {
                $exam_types[] = $row['exam_type'];
            }
        }
        $stmt->close();
    } else {
        // Column doesn't exist, use exam names instead
        $stmt = $conn->prepare("SELECT DISTINCT exam_name FROM exams ORDER BY exam_name");
        if ($stmt === false) {
            throw new Exception("Failed to prepare exam names statement: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $exam_types[] = $row['exam_name'];
        }
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching exam types: " . $e->getMessage());
}

// If no exam types found, add some defaults
if (empty($exam_types)) {
    $exam_types = ['Yearly Exam', 'Term Exam', 'Mid-Term Exam', 'Final Exam'];
}

// Get selected exam type from URL parameter
$selected_exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';

// Check if viewing a specific exam result
if (isset($_GET['exam_id'])) {
    $exam_id = $_GET['exam_id'];
    
    // Verify this exam belongs to the student
    $valid_exam = false;
    foreach ($exams as $exam) {
        if ($exam['exam_id'] == $exam_id) {
            $valid_exam = true;
            break;
        }
    }
    
    if (!$valid_exam) {
        die("Invalid exam selection.");
    }
    
    // Get exam details
    try {
        // First try with exam_type field
        $query = "
            SELECT exam_name, exam_type, academic_year, start_date, end_date
            FROM exams
            WHERE exam_id = ?
        ";
        
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Failed to prepare exam details statement: " . $conn->error);
        }
        
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            die("Exam not found");
        }
        
        $exam_details = $result->fetch_assoc();
        
        // If exam_type is NULL or empty, use exam_name as the type
        if (empty($exam_details['exam_type'])) {
            $exam_details['exam_type'] = $exam_details['exam_name'];
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching exam details: " . $e->getMessage());
        
        // Try alternative query without exam_type
        try {
            $alt_query = "
                SELECT exam_name, academic_year, start_date, end_date
                FROM exams
                WHERE exam_id = ?
            ";
            
            $stmt = $conn->prepare($alt_query);
            if ($stmt === false) {
                throw new Exception("Failed to prepare alternative exam details statement: " . $conn->error);
            }
            
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                die("Exam not found");
            }
            
            $exam_details = $result->fetch_assoc();
            // Add exam_type field with the same value as exam_name
            $exam_details['exam_type'] = $exam_details['exam_name'];
            
            $stmt->close();
        } catch (Exception $e2) {
            error_log("Error fetching exam details (alternative query): " . $e2->getMessage());
            die("An error occurred while retrieving exam details. Please try again later.");
        }
    }
    
    // Get results for this student and exam
    try {
        $stmt = $conn->prepare("
            SELECT r.*, s.subject_name, s.subject_code, s.full_marks_theory, s.full_marks_practical, s.credit_hours
            FROM results r
            JOIN subjects s ON r.subject_id = s.subject_id
            WHERE r.student_id = ? AND r.exam_id = ?
        ");
        if ($stmt === false) {
            throw new Exception("Failed to prepare results statement: " . $conn->error);
        }
        
        $stmt->bind_param("si", $student['student_id'], $exam_id);
        $stmt->execute();
        $results_data = $stmt->get_result();

        $subjects = [];
        $total_marks = 0;
        $total_subjects = 0;
        $max_marks = 0;
        $total_credit_hours = 0;
        $total_grade_points = 0;

        while ($row = $results_data->fetch_assoc()) {
            $theory_marks = $row['theory_marks'] ?? 0;
            $practical_marks = $row['practical_marks'] ?? 0;
            $total_subject_marks = $theory_marks + $practical_marks;
            $subject_max_marks = $row['full_marks_theory'] + $row['full_marks_practical'];
            $credit_hours = $row['credit_hours'] ?? 1;
            
            // Calculate grade point for this subject
            $grade_point = 0;
            switch ($row['grade']) {
                case 'A+': $grade_point = 4.0; break;
                case 'A': $grade_point = 3.7; break;
                case 'B+': $grade_point = 3.3; break;
                case 'B': $grade_point = 3.0; break;
                case 'C+': $grade_point = 2.7; break;
                case 'C': $grade_point = 2.3; break;
                case 'D': $grade_point = 2.0; break;
                case 'F': $grade_point = 0.0; break;
                default: $grade_point = 0.0;
            }
            
            $total_grade_points += ($grade_point * $credit_hours);
            $total_credit_hours += $credit_hours;

            $subjects[] = [
                'code' => $row['subject_code'] ?? $row['subject_id'],
                'name' => $row['subject_name'],
                'credit_hour' => $credit_hours,
                'theory_marks' => $theory_marks,
                'practical_marks' => $practical_marks,
                'total_marks' => $total_subject_marks,
                'grade' => $row['grade'],
                'grade_point' => $grade_point,
                'remarks' => $row['remarks'] ?? ''
            ];

            $total_marks += $total_subject_marks;
            $total_subjects++;
            $max_marks += $subject_max_marks;
        }

        $stmt->close();
        
        // Calculate GPA
        $gpa = $total_credit_hours > 0 ? ($total_grade_points / $total_credit_hours) : 0;
        
        // Calculate percentage
        $percentage = $max_marks > 0 ? ($total_marks / $max_marks) * 100 : 0;
        
        // Determine division
        if ($percentage >= 80) {
            $division = 'Distinction';
        } elseif ($percentage >= 60) {
            $division = 'First Division';
        } elseif ($percentage >= 45) {
            $division = 'Second Division';
        } elseif ($percentage >= 33) {
            $division = 'Third Division';
        } else {
            $division = 'Fail';
        }
        
        // Get student performance data if available
        try {
            // Check if student_performance table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'student_performance'");
            
            if ($check_table->num_rows > 0) {
                $stmt = $conn->prepare("
                    SELECT * FROM student_performance 
                    WHERE student_id = ? AND exam_id = ?
                ");
                if ($stmt === false) {
                    throw new Exception("Failed to prepare performance statement: " . $conn->error);
                }
                
                $stmt->bind_param("si", $student['student_id'], $exam_id);
                $stmt->execute();
                $performance_result = $stmt->get_result();

                if ($performance_result->num_rows > 0) {
                    $performance = $performance_result->fetch_assoc();
                    // Use the stored GPA and percentage if available
                    $gpa = $performance['gpa'];
                    $percentage = $performance['average_marks'];
                    
                    // Get rank information if available
                    $rank = $performance['rank'] ?? 'N/A';
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Error fetching performance data: " . $e->getMessage());
        }
    } catch (Exception $e) {
        error_log("Error fetching results: " . $e->getMessage());
        die("An error occurred while retrieving results. Please try again later.");
    }
} else if (!empty($selected_exam_type) && count($exams) > 0) {
    // If exam type is selected but no specific exam, find the first exam of that type
    $filtered_exams = array_filter($exams, function($exam) use ($selected_exam_type) {
        return $exam['exam_type'] == $selected_exam_type;
    });
    
    if (!empty($filtered_exams)) {
        $first_exam = reset($filtered_exams);
        header("Location: grade_sheet.php?exam_id=" . $first_exam['exam_id'] . "&exam_type=" . urlencode($selected_exam_type));
        exit();
    }
} else if (count($exams) > 0) {
    // If no specific exam is selected, redirect to the most recent exam
    header("Location: grade_sheet.php?exam_id=" . $exams[0]['exam_id']);
    exit();
}

// Get school settings
$settings = [];
try {
    // Check if settings table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'settings'");
    
    if ($check_table->num_rows > 0) {
        $result = $conn->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } else {
        // Default settings if table doesn't exist
        $settings = [
            'school_name' => 'School Name',
            'result_header' => 'Result Management System',
            'result_footer' => 'This is a computer-generated document. No signature is required.'
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    // Default settings if query fails
    $settings = [
        'school_name' => 'School Name',
        'result_header' => 'Result Management System',
        'result_footer' => 'This is a computer-generated document. No signature is required.'
    ];
}

// Get prepared by information (teacher or admin)
$prepared_by = "System Administrator";
try {
    if (isset($exam_id)) {
        $stmt = $conn->prepare("
            SELECT u.full_name 
            FROM exams e
            JOIN users u ON e.created_by = u.user_id
            WHERE e.exam_id = ?
        ");
        if ($stmt === false) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $prepared_by = $row['full_name'];
        }
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching prepared by information: " . $e->getMessage());
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Sheet | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
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
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
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

        /* Exam selector styles */
        .exam-selector {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .exam-button {
            padding: 8px 16px;
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .exam-button:hover {
            background-color: #e0e0e0;
        }

        .exam-button.active {
            background-color: #1a5276;
            color: white;
            border-color: #1a5276;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #1a5276;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-button:hover {
            background-color: #154360;
        }

        .action-button i {
            margin-right: 8px;
        }

        @media print {
            body {
                background-color: white !important;
            }

            .grade-sheet-container {
                width: 100%;
                min-height: auto;
                padding: 0.5cm;
                margin: 0;
                box-shadow: none;
            }

            .print-button,
            .back-button,
            .sidebar,
            .top-navigation,
            .exam-selector,
            .action-buttons {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include('./includes/student_sidebar.php'); ?>

        <!-- Mobile sidebar -->
        <div class="fixed inset-0 flex z-40 md:hidden transform -translate-x-full transition-transform duration-300 ease-in-out" id="mobile-sidebar">
            <div class="fixed inset-0 bg-gray-600 bg-opacity-75" id="sidebar-backdrop"></div>
            <div class="relative flex-1 flex flex-col max-w-xs w-full bg-gray-800">
                <div class="absolute top-0 right-0 -mr-12 pt-2">
                    <button class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" id="close-sidebar">
                        <span class="sr-only">Close sidebar</span>
                        <i class="fas fa-times text-white"></i>
                    </button>
                </div>
                <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
                    <div class="flex-shrink-0 flex items-center px-4">
                        <span class="text-white text-lg font-semibold">Result Management</span>
                    </div>
                    <nav class="mt-5 px-2 space-y-1">
                        <a href="student_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="grade_sheet.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-file-alt mr-3"></i>
                            Grade Sheet
                        </a>
                        <a href="view_result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            View Results
                        </a>
                        <!-- Add more mobile navigation items as needed -->
                        <a href="../includes/logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include('./includes/top_navigation.php'); ?>

            <!-- Main Content Area -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-bold text-gray-900">Grade Sheet</h1>
                            <div class="action-buttons">
                                <a href="student_dashboard.php" class="action-button">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                                <button onclick="window.print()" class="action-button">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button onclick="downloadPDF()" class="action-button">
                                    <i class="fas fa-file-pdf"></i> Download PDF
                                </button>
                            </div>
                        </div>

                        <?php if (count($exams) > 0): ?>
                            <!-- Exam Type Filter -->
                            <div class="bg-white shadow rounded-lg mb-6 p-4">
                                <h3 class="text-lg font-medium text-gray-900 mb-3">Filter by Exam Type</h3>
                                <div class="flex flex-wrap gap-2">
                                    <a href="grade_sheet.php" class="px-4 py-2 rounded-md text-sm font-medium <?php echo empty($selected_exam_type) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                                        All Exams
                                    </a>
                                    <?php foreach ($exam_types as $type): ?>
                                        <a href="grade_sheet.php?exam_type=<?php echo urlencode($type); ?>" class="px-4 py-2 rounded-md text-sm font-medium <?php echo $selected_exam_type === $type ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                                            <?php echo htmlspecialchars($type); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Exam Selector -->
                            <div class="exam-selector">
                                <div class="text-sm font-medium text-gray-700 mr-3">Select Exam:</div>
                                <?php 
                                $filtered_exams = empty($selected_exam_type) ? $exams : array_filter($exams, function($exam) use ($selected_exam_type) {
                                    return $exam['exam_type'] == $selected_exam_type;
                                });
                                
                                if (empty($filtered_exams) && !empty($selected_exam_type)) {
                                    echo '<div class="text-sm text-red-600">No exams found for the selected type. Showing all exams.</div>';
                                    $filtered_exams = $exams;
                                }
                                
                                foreach ($filtered_exams as $exam): 
                                ?>
                                    <a href="?exam_id=<?php echo $exam['exam_id']; ?><?php echo !empty($selected_exam_type) ? '&exam_type=' . urlencode($selected_exam_type) : ''; ?>" 
                                       class="exam-button <?php echo (isset($_GET['exam_id']) && $_GET['exam_id'] == $exam['exam_id']) ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo htmlspecialchars($exam['academic_year']); ?>)
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['exam_id']) && !empty($subjects)): ?>
                            <!-- Grade Sheet -->
                            <div class="grade-sheet-container" id="grade-sheet">
                                <div class="watermark">OFFICIAL</div>

                                <div class="header">
                                    <div class="logo">
                                        <?php if (!empty($settings['school_logo'])): ?>
                                            <img src="<?php echo htmlspecialchars($settings['school_logo']); ?>" alt="School Logo" class="h-full w-full object-contain">
                                        <?php else: ?>
                                            LOGO
                                        <?php endif; ?>
                                    </div>
                                    <div class="title"><?php echo isset($settings['school_name']) ? strtoupper($settings['school_name']) : 'GOVERNMENT OF NEPAL'; ?></div>
                                    <div class="title"><?php echo isset($settings['result_header']) ? strtoupper($settings['result_header']) : 'NATIONAL EXAMINATION BOARD'; ?></div>
                                    <div class="subtitle">SECONDARY EDUCATION EXAMINATION</div>
                                    <div class="exam-title">GRADE SHEET</div>
                                </div>

                                <div class="student-info">
                                    <div class="info-item">
                                        <span class="info-label">Student Name:</span>
                                        <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Roll No:</span>
                                        <span><?php echo htmlspecialchars($student['roll_number']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Registration No:</span>
                                        <span><?php echo htmlspecialchars($student['registration_number']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Class:</span>
                                        <span><?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Examination:</span>
                                        <span><?php echo htmlspecialchars($exam_details['exam_name']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Exam Type:</span>
                                        <span><?php echo htmlspecialchars($exam_details['exam_type']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Academic Year:</span>
                                        <span><?php echo htmlspecialchars($exam_details['academic_year']); ?></span>
                                    </div>
                                </div>

                                <table class="grade-sheet-table">
                                    <thead>
                                        <tr>
                                            <th>SUBJECT CODE</th>
                                            <th>SUBJECTS</th>
                                            <th>CREDIT HOUR</th>
                                            <th>THEORY MARKS</th>
                                            <th>PRACTICAL MARKS</th>
                                            <th>TOTAL MARKS</th>
                                            <th>GRADE</th>
                                            <th>GRADE POINT</th>
                                            <th>REMARKS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subjects as $subject): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subject['code']); ?></td>
                                                <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                                <td><?php echo htmlspecialchars($subject['credit_hour']); ?></td>
                                                <td><?php echo htmlspecialchars($subject['theory_marks']); ?></td>
                                                <td><?php echo htmlspecialchars($subject['practical_marks']); ?></td>
                                                <td><?php echo htmlspecialchars($subject['total_marks']); ?></td>
                                                <td><?php echo htmlspecialchars($subject['grade']); ?></td>
                                                <td><?php echo number_format($subject['grade_point'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($subject['remarks']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <div class="summary">
                                    <div class="summary-item">
                                        <div class="summary-label">TOTAL MARKS</div>
                                        <div class="summary-value"><?php echo $total_marks; ?> / <?php echo $max_marks; ?></div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="summary-label">PERCENTAGE</div>
                                        <div class="summary-value"><?php echo number_format($percentage, 2); ?>%</div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="summary-label">GPA</div>
                                        <div class="summary-value"><?php echo number_format($gpa, 2); ?></div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="summary-label">DIVISION</div>
                                        <div class="summary-value"><?php echo $division; ?></div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="summary-label">RESULT</div>
                                        <div class="summary-value"><?php echo $percentage >= 33 ? 'PASS' : 'FAIL'; ?></div>
                                    </div>
                                    <?php if (isset($rank)): ?>
                                    <div class="summary-item">
                                        <div class="summary-label">RANK</div>
                                        <div class="summary-value"><?php echo $rank; ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>

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
                                        <div><?php echo htmlspecialchars($prepared_by); ?></div>
                                    </div>
                                    <div class="signature">
                                        <div class="signature-line"></div>
                                        <div class="signature-title">PRINCIPAL</div>
                                        <div>SCHOOL PRINCIPAL</div>
                                    </div>
                                </div>

                                <div class="qr-code">QR CODE</div>

                                <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #777;">
                                    <p><?php echo isset($settings['result_footer']) ? htmlspecialchars($settings['result_footer']) : 'This is a computer-generated document. No signature is required.'; ?></p>
                                    <p>Issue Date: <?php echo date('d-m-Y', strtotime($issue_date)); ?></p>
                                </div>
                            </div>
                        <?php elseif (count($exams) == 0): ?>
                            <!-- No exams available -->
                            <div class="bg-white shadow rounded-lg p-6">
                                <div class="text-center">
                                    <i class="fas fa-exclamation-circle text-yellow-500 text-5xl mb-4"></i>
                                    <h2 class="text-xl font-medium text-gray-900 mb-2">No Exam Results Available</h2>
                                    <p class="text-gray-600 mb-4">You don't have any exam results available yet. Please check back later or contact your teacher for more information.</p>
                                    <a href="student_dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-arrow-left mr-2"></i> Return to Dashboard
                                    </a>
                                </div>
                            </div>
                        <?php elseif (!empty($selected_exam_type) && empty($filtered_exams)): ?>
                            <!-- No exams for selected type -->
                            <div class="bg-white shadow rounded-lg p-6">
                                <div class="text-center">
                                    <i class="fas fa-filter text-blue-500 text-5xl mb-4"></i>
                                    <h2 class="text-xl font-medium text-gray-900 mb-2">No Results for Selected Exam Type</h2>
                                    <p class="text-gray-600 mb-4">There are no exam results available for the selected exam type "<?php echo htmlspecialchars($selected_exam_type); ?>". Please select a different exam type or view all exams.</p>
                                    <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-list mr-2"></i> View All Exams
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.remove('-translate-x-full');
        });
        
        document.getElementById('close-sidebar').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });
        
        document.getElementById('sidebar-backdrop').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });

        // PDF Download functionality
        function downloadPDF() {
            // Check if jsPDF is loaded
            if (typeof window.jspdf === 'undefined') {
                alert('PDF generation library not loaded. Please refresh the page and try again.');
                return;
            }

            const { jsPDF } = window.jspdf;
            
            // Create a new jsPDF instance
            const doc = new jsPDF('p', 'mm', 'a4');
            
            // Get the grade sheet element
            const element = document.getElementById('grade-sheet');
            
            // Use html2canvas to capture the element as an image
            html2canvas(element, {
                scale: 2, // Higher scale for better quality
                useCORS: true,
                logging: false
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                
                // Calculate the width and height to maintain aspect ratio
                const imgWidth = 210; // A4 width in mm
                const pageHeight = 297; // A4 height in mm
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;
                
                // Add the first page
                doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                // Add additional pages if needed
                while (heightLeft > 0) {
                    position = heightLeft - imgHeight;
                    doc.addPage();
                    doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                // Save the PDF
                doc.save('Grade_Sheet_<?php echo isset($student['roll_number']) ? $student['roll_number'] : 'Student'; ?>_<?php echo date('Y-m-d'); ?>.pdf');
            });
        }
    </script>
</body>
</html>
