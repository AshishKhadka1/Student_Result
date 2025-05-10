<?php
// Start session for potential authentication check
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'teacher' && $_SESSION['role'] != 'admin')) {
    header("Location: ../login.php");
    exit();
}

// Connect to database
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$student = [];
$subjects = [];
$gpa = 0;
$percentage = 0;
$division = '';
$total_marks = 0;
$max_marks = 0;
$prepared_by = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'System Administrator';
$issue_date = date('Y-m-d');

// Get all classes for filter
$classes = [];
$class_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name, section");
if ($class_result) {
    while ($class_row = $class_result->fetch_assoc()) {
        $classes[] = $class_row;
    }
}

// Get all academic years for filter
$academic_years = [];
$year_result = $conn->query("SELECT DISTINCT academic_year FROM exams ORDER BY academic_year DESC");
if ($year_result) {
    while ($year_row = $year_result->fetch_assoc()) {
        $academic_years[] = $year_row['academic_year'];
    }
}

// Get all exam types for filter
$exam_types = [];
// First check if exam_type column exists
$column_check = $conn->query("SHOW COLUMNS FROM exams LIKE 'exam_type'");
if ($column_check && $column_check->num_rows > 0) {
    $type_result = $conn->query("SELECT DISTINCT exam_type FROM exams WHERE exam_type IS NOT NULL ORDER BY exam_type");
    if ($type_result) {
        while ($type_row = $type_result->fetch_assoc()) {
            if (!empty($type_row['exam_type'])) {
                $exam_types[] = $type_row['exam_type'];
            }
        }
    }
}

// If no exam types found, add default ones
if (empty($exam_types)) {
    $exam_types = ['Final Exam', 'Mid-Term Exam', 'Unit Test', 'Quarterly Exam', 'Half-Yearly Exam'];
}

// Check if viewing a specific student result
if (isset($_GET['student_id']) && isset($_GET['exam_id'])) {
    // Get student information
    $student_id = $_GET['student_id'];
    $exam_id = $_GET['exam_id'];

    // Get student details
    $stmt = $conn->prepare("
        SELECT s.student_id, s.roll_number, s.registration_number, u.full_name, 
               c.class_name, c.section, e.exam_name, e.exam_type, e.academic_year,
               e.start_date, e.end_date
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        JOIN classes c ON s.class_id = c.class_id
        JOIN exams e ON e.exam_id = ?
        WHERE s.student_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("is", $exam_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            die("Student or exam not found");
        }

        $student = $result->fetch_assoc();
        $stmt->close();
    } else {
        die("Database error: " . $conn->error);
    }

    // Get results for this student and exam
    $stmt = $conn->prepare("
        SELECT r.*, s.subject_name, s.subject_code, s.full_marks_theory, s.full_marks_practical
        FROM results r
        JOIN subjects s ON r.subject_id = s.subject_id
        WHERE r.student_id = ? AND r.exam_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("si", $student_id, $exam_id);
        $stmt->execute();
        $results_data = $stmt->get_result();

        $subjects = [];
        $total_marks = 0;
        $total_subjects = 0;
        $max_marks = 0;

        while ($row = $results_data->fetch_assoc()) {
            $theory_marks = $row['theory_marks'] ?? 0;
            $practical_marks = $row['practical_marks'] ?? 0;
            $total_subject_marks = $theory_marks + $practical_marks;
            $subject_max_marks = $row['full_marks_theory'] + $row['full_marks_practical'];

            $subjects[] = [
                'code' => $row['subject_code'] ?? $row['subject_id'],
                'name' => $row['subject_name'],
                'credit_hour' => $row['credit_hours'],
                'theory_marks' => $theory_marks,
                'practical_marks' => $practical_marks,
                'total_marks' => $total_subject_marks,
                'grade' => $row['grade'],
                'remarks' => $row['remarks'] ?? ''
            ];

            $total_marks += $total_subject_marks;
            $total_subjects++;
            $max_marks += $subject_max_marks;
        }

        $stmt->close();
    } else {
        die("Database error: " . $conn->error);
    }

    // Get student performance data if available
    $stmt = $conn->prepare("
        SELECT * FROM student_performance 
        WHERE student_id = ? AND exam_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("si", $student_id, $exam_id);
        $stmt->execute();
        $performance_result = $stmt->get_result();

        if ($performance_result->num_rows > 0) {
            $performance = $performance_result->fetch_assoc();
            $gpa = $performance['gpa'];
            $percentage = $performance['average_marks'];
        } else {
            // Calculate percentage if performance data not available
            $percentage = $max_marks > 0 ? ($total_marks / $max_marks) * 100 : 0;

            // Calculate GPA (using grading system from database)
            $gpa = calculateGPA($percentage, $conn);
        }

        $stmt->close();
    } else {
        // If query fails, calculate manually
        $percentage = $max_marks > 0 ? ($total_marks / $max_marks) * 100 : 0;
        $gpa = calculateGPA($percentage, $conn);
    }

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
} else {
    // If no specific student is requested, show a list of students to select
    $show_student_list = true;

    // Get available exams based on filters
    $exams = [];
    $filter_class = isset($_GET['class_id']) ? $_GET['class_id'] : '';
    $filter_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';
    $filter_exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';
    
    // Build the query with filters
    $exam_query = "SELECT e.exam_id, e.exam_name, e.exam_type, e.academic_year 
                  FROM exams e 
                  WHERE e.is_active = 1";
    
    $params = [];
    $param_types = "";
    
    if (!empty($filter_class)) {
        // Check if the exams table has a class_id column
        $column_check = $conn->query("SHOW COLUMNS FROM exams LIKE 'class_id'");
        if ($column_check && $column_check->num_rows > 0) {
            $exam_query .= " AND e.class_id = ?";
            $params[] = $filter_class;
            $param_types .= "i";
        } else {
            // If no class_id in exams table, we need to join with results and students
            $exam_query = "SELECT DISTINCT e.exam_id, e.exam_name, e.exam_type, e.academic_year 
                          FROM exams e 
                          JOIN results r ON e.exam_id = r.exam_id
                          JOIN students s ON r.student_id = s.student_id
                          WHERE e.is_active = 1 AND s.class_id = ?";
            $params[] = $filter_class;
            $param_types .= "i";
        }
    }
    
    if (!empty($filter_year)) {
        $exam_query .= " AND e.academic_year = ?";
        $params[] = $filter_year;
        $param_types .= "s";
    }
    
    // Check if exam_type column exists
    $column_check = $conn->query("SHOW COLUMNS FROM exams LIKE 'exam_type'");
    if ($column_check && $column_check->num_rows > 0 && !empty($filter_exam_type)) {
        $exam_query .= " AND e.exam_type = ?";
        $params[] = $filter_exam_type;
        $param_types .= "s";
    }
    
    $exam_query .= " ORDER BY e.created_at DESC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($exam_query);
        if ($stmt) {
            $stmt->bind_param($param_types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $exams[] = $row;
            }
            $stmt->close();
        } else {
            // Fallback to simple query if prepare fails
            $result = $conn->query("SELECT exam_id, exam_name, exam_type, academic_year FROM exams WHERE is_active = 1 ORDER BY created_at DESC");
            while ($row = $result->fetch_assoc()) {
                $exams[] = $row;
            }
        }
    } else {
        // No filters, get all exams
        $result = $conn->query($exam_query);
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
    }

    // Get students if an exam is selected
    $students = [];
    if (isset($_GET['exam_id'])) {
        $selected_exam_id = $_GET['exam_id'];

        $stmt = $conn->prepare("
            SELECT DISTINCT s.student_id, s.roll_number, u.full_name, c.class_name, c.section
            FROM students s
            JOIN users u ON s.user_id = u.user_id
            JOIN classes c ON s.class_id = c.class_id
            JOIN results r ON r.student_id = s.student_id
            WHERE r.exam_id = ?
            ORDER BY s.roll_number
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $selected_exam_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            $stmt->close();
        } else {
            die("Database error: " . $conn->error);
        }
    }
}

// Function to calculate GPA based on percentage
function calculateGPA($percentage, $conn)
{
    // Get grading system from database
    $result = $conn->query("SELECT * FROM grading_system ORDER BY min_percentage DESC");

    while ($grade = $result->fetch_assoc()) {
        if ($percentage >= $grade['min_percentage']) {
            return $grade['gpa'];
        }
    }

    return 0; // Default if no matching grade found
}

// Get school settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
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
            .selection-container {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }
        
        /* Filter styles */
        .filter-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .filter-title {
            font-weight: 600;
            margin-bottom: 12px;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }
        
        .filter-title i {
            margin-right: 8px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .filter-item {
            margin-bottom: 8px;
        }
        
        .filter-label {
            display: block;
            font-size: 14px;
            margin-bottom: 4px;
            color: #4a5568;
        }
        
        .filter-select {
            width: 100%;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            background-color: white;
            font-size: 14px;
        }
        
        .filter-button {
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .filter-button:hover {
            background-color: #1a5276;
        }
        
        .filter-reset {
            background-color: #e2e8f0;
            color: #4a5568;
            margin-left: 8px;
        }
        
        .filter-reset:hover {
            background-color: #cbd5e0;
        }
        
        .filter-actions {
            margin-top: 12px;
            display: flex;
            justify-content: flex-end;
        }
        
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        
        .filter-tag {
            background-color: #e2e8f0;
            color: #4a5568;
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 12px;
            display: flex;
            align-items: center;
        }
        
        .filter-tag i {
            margin-left: 6px;
            cursor: pointer;
        }
        
        .filter-tag i:hover {
            color: #e53e3e;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

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
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            Results
                        </a>
                        <a href="grade_sheet.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-file-alt mr-3"></i>
                            Grade Sheets
                        </a>
                        <!-- Add more mobile navigation items as needed -->
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
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
            <?php include 'topBar.php'; ?>

            <!-- Main Content Area -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <?php if (isset($show_student_list)): ?>
                            <!-- Selection Form Container -->
                            <div class="bg-white shadow rounded-lg p-6 mb-6">
                                <h1 class="text-2xl font-bold text-gray-900 mb-4">Grade Sheet</h1>
                                
                                <!-- Advanced Filters -->
                                <div class="filter-container">
                                    <div class="filter-title">
                                        <i class="fas fa-filter"></i> Filter Grade Sheets
                                    </div>
                                    <form action="" method="GET" id="filter-form">
                                        <div class="filter-grid">
                                            <div class="filter-item">
                                                <label for="class_id" class="filter-label">Class:</label>
                                                <select name="class_id" id="class_id" class="filter-select">
                                                    <option value="">All Classes</option>
                                                    <?php foreach ($classes as $class): ?>
                                                        <option value="<?php echo $class['class_id']; ?>" <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                                                            <?php echo $class['class_name'] . ' ' . $class['section']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="filter-item">
                                                <label for="academic_year" class="filter-label">Academic Year:</label>
                                                <select name="academic_year" id="academic_year" class="filter-select">
                                                    <option value="">All Years</option>
                                                    <?php foreach ($academic_years as $year): ?>
                                                        <option value="<?php echo $year; ?>" <?php echo (isset($_GET['academic_year']) && $_GET['academic_year'] == $year) ? 'selected' : ''; ?>>
                                                            <?php echo $year; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="filter-item">
                                                <label for="exam_type" class="filter-label">Exam Type:</label>
                                                <select name="exam_type" id="exam_type" class="filter-select">
                                                    <option value="">All Types</option>
                                                    <?php foreach ($exam_types as $type): ?>
                                                        <option value="<?php echo $type; ?>" <?php echo (isset($_GET['exam_type']) && $_GET['exam_type'] == $type) ? 'selected' : ''; ?>>
                                                            <?php echo $type; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="filter-item">
                                                <label for="exam_id" class="filter-label">Exam:</label>
                                                <select name="exam_id" id="exam_id" class="filter-select">
                                                    <option value="">-- Select Exam --</option>
                                                    <?php foreach ($exams as $exam): ?>
                                                        <option value="<?php echo $exam['exam_id']; ?>" <?php echo (isset($_GET['exam_id']) && $_GET['exam_id'] == $exam['exam_id']) ? 'selected' : ''; ?>>
                                                            <?php echo $exam['exam_name'] . ' (' . $exam['academic_year'] . ')'; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="filter-actions">
                                            <button type="submit" class="filter-button">
                                                <i class="fas fa-search mr-2"></i> Apply Filters
                                            </button>
                                            <button type="button" id="reset-filters" class="filter-button filter-reset">
                                                <i class="fas fa-undo mr-2"></i> Reset
                                            </button>
                                        </div>
                                        
                                        <?php if (isset($_GET['class_id']) || isset($_GET['academic_year']) || isset($_GET['exam_type'])): ?>
                                            <div class="active-filters">
                                                <div class="text-sm text-gray-600 mr-2">Active filters:</div>
                                                <?php if (isset($_GET['class_id']) && !empty($_GET['class_id'])): 
                                                    $class_name = '';
                                                    foreach ($classes as $class) {
                                                        if ($class['class_id'] == $_GET['class_id']) {
                                                            $class_name = $class['class_name'] . ' ' . $class['section'];
                                                            break;
                                                        }
                                                    }
                                                ?>
                                                    <div class="filter-tag">
                                                        Class: <?php echo $class_name; ?>
                                                        <i class="fas fa-times-circle" onclick="removeFilter('class_id')"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($_GET['academic_year']) && !empty($_GET['academic_year'])): ?>
                                                    <div class="filter-tag">
                                                        Year: <?php echo $_GET['academic_year']; ?>
                                                        <i class="fas fa-times-circle" onclick="removeFilter('academic_year')"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($_GET['exam_type']) && !empty($_GET['exam_type'])): ?>
                                                    <div class="filter-tag">
                                                        Type: <?php echo $_GET['exam_type']; ?>
                                                        <i class="fas fa-times-circle" onclick="removeFilter('exam_type')"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                </div>

                                <?php if (isset($_GET['exam_id']) && !empty($students)): ?>
                                    <div class="mt-6">
                                        <h3 class="text-md font-medium text-gray-700 mb-3">Select Student:</h3>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll Number</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($students as $student): ?>
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['roll_number']; ?></td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['full_name']; ?></td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['class_name'] . ' ' . $student['section']; ?></td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                                <a href="?student_id=<?php echo $student['student_id']; ?>&exam_id=<?php echo $_GET['exam_id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                                    <i class="fas fa-eye mr-1"></i> View Grade Sheet
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php elseif (isset($_GET['exam_id'])): ?>
                                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mt-6">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-yellow-700">No students found with results for this exam.</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif (!empty($_GET['class_id']) || !empty($_GET['academic_year']) || !empty($_GET['exam_type'])): ?>
                                    <?php if (empty($exams)): ?>
                                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mt-6">
                                            <div class="flex">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm text-yellow-700">No exams found matching the selected filters.</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mt-6">
                                            <div class="flex">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-info-circle text-blue-500"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm text-blue-700">Please select an exam from the dropdown to view student grade sheets.</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Grade Sheet View -->
                            <div class="mb-4 flex justify-between">
                                <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                                </a>
                                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <i class="fas fa-print mr-2"></i> Print Grade Sheet
                                </button>
                            </div>

                            <div class="grade-sheet-container">
                                <div class="watermark">OFFICIAL</div>

                                <div class="header">
                                    <div class="logo">LOGO</div>
                                    <div class="title"><?php echo isset($settings['school_name']) ? strtoupper($settings['school_name']) : 'GOVERNMENT OF NEPAL'; ?></div>
                                    <div class="title"><?php echo isset($settings['result_header']) ? strtoupper($settings['result_header']) : 'NATIONAL EXAMINATION BOARD'; ?></div>
                                    <div class="subtitle">SECONDARY EDUCATION EXAMINATION</div>
                                    <div class="exam-title">GRADE SHEET</div>
                                </div>

                                <div class="student-info">
                                    <div class="info-item">
                                        <span class="info-label">Student Name:</span>
                                        <span><?php echo $student['full_name']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Roll No:</span>
                                        <span><?php echo $student['roll_number']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Registration No:</span>
                                        <span><?php echo $student['registration_number']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Class:</span>
                                        <span><?php echo $student['class_name'] . ' ' . $student['section']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Examination:</span>
                                        <span><?php echo $student['exam_name']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Academic Year:</span>
                                        <span><?php echo $student['academic_year']; ?></span>
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
                                            <th>REMARKS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($subjects)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">No results found for this student.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($subjects as $subject): ?>
                                                <tr>
                                                    <td><?php echo $subject['code']; ?></td>
                                                    <td><?php echo $subject['name']; ?></td>
                                                    <td><?php echo $subject['credit_hour']; ?></td>
                                                    <td><?php echo $subject['theory_marks']; ?></td>
                                                    <td><?php echo $subject['practical_marks']; ?></td>
                                                    <td><?php echo $subject['total_marks']; ?></td>
                                                    <td><?php echo $subject['grade']; ?></td>
                                                    <td><?php echo $subject['remarks']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
        
        // Filter functionality
        document.getElementById('reset-filters').addEventListener('click', function() {
            window.location.href = 'grade_sheet.php';
        });
        
        // Class filter change
        document.getElementById('class_id').addEventListener('change', function() {
            // If we're changing class, we should reset exam selection
            document.getElementById('exam_id').value = '';
            
            // If we want to auto-submit on change, uncomment the line below
            // document.getElementById('filter-form').submit();
        });
        
        // Academic year filter change
        document.getElementById('academic_year').addEventListener('change', function() {
            // If we're changing year, we should reset exam selection
            document.getElementById('exam_id').value = '';
            
            // If we want to auto-submit on change, uncomment the line below
            // document.getElementById('filter-form').submit();
        });
        
        // Exam type filter change
        document.getElementById('exam_type').addEventListener('change', function() {
            // If we're changing exam type, we should reset exam selection
            document.getElementById('exam_id').value = '';
            
            // If we want to auto-submit on change, uncomment the line below
            // document.getElementById('filter-form').submit();
        });
        
        // Remove a specific filter
        function removeFilter(filterName) {
            // Create a new URL object
            const url = new URL(window.location.href);
            
            // Remove the specified parameter
            url.searchParams.delete(filterName);
            
            // If we're removing a filter that affects exams, also reset exam_id
            if (filterName === 'class_id' || filterName === 'academic_year' || filterName === 'exam_type') {
                url.searchParams.delete('exam_id');
            }
            
            // Redirect to the new URL
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
