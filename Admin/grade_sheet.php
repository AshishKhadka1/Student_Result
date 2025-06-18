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
        ORDER BY s.subject_name
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
                'full_marks_theory' => $row['full_marks_theory'],
                'full_marks_practical' => $row['full_marks_practical'],
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
    if ($percentage >= 91) {
        $division = 'Distinction (A+)';
    } elseif ($percentage >= 81) {
        $division = 'First Division (A)';
    } elseif ($percentage >= 71) {
        $division = 'Second Division (B+)';
    } elseif ($percentage >= 61) {
        $division = 'Second Division (B)';
    } elseif ($percentage >= 51) {
        $division = 'Third Division (C+)';
    } elseif ($percentage >= 41) {
        $division = 'Third Division (C)';
    } elseif ($percentage >= 35) {
        $division = 'Pass (D+)';
    } else {
        $division = 'Not Graded (NG)';
    }
} else {
    // If no specific student is requested, show a list of students to select
    $show_student_list = true;

    // Get available exams based on filters
    $exams = [];
    $filter_class = isset($_GET['class_id']) ? $_GET['class_id'] : '';
    $filter_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';
    $filter_exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';
    $search_submitted = isset($_GET['search_submitted']) && $_GET['search_submitted'] == '1';

    // Only apply filters if search was submitted
    if ($search_submitted) {
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
    } else {
        // If search not submitted, just get recent exams for the dropdown
        $result = $conn->query("SELECT exam_id, exam_name, exam_type, academic_year FROM exams WHERE is_active = 1 ORDER BY created_at DESC LIMIT 20");
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
    // Use the new grading system
    if ($percentage >= 91) return 3.8; // Average of 3.6-4.0
    elseif ($percentage >= 81) return 3.4; // Average of 3.2-3.6
    elseif ($percentage >= 71) return 3.0; // Average of 2.8-3.2
    elseif ($percentage >= 61) return 2.7; // Average of 2.6-2.8
    elseif ($percentage >= 51) return 2.4; // Average of 2.2-2.6
    elseif ($percentage >= 41) return 1.9; // Average of 1.6-2.2
    elseif ($percentage >= 35) return 1.6; // Borderline
    else return 0.0; // Not Graded
}


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

        /* Search button styles */
        .search-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .search-button:hover {
            background-color: #2980b9;
        }
        
        .search-button i {
            margin-right: 8px;
        }
        
        .exam-type-container {
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 12px;
            background-color: #f8fafc;
        }
        
        .exam-type-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .exam-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 8px;
        }
        
        .exam-type-option {
            display: flex;
            align-items: center;
        }
        
        .exam-type-option input {
            margin-right: 6px;
        }
        
        .exam-type-option label {
            font-size: 14px;
            color: #4a5568;
            cursor: pointer;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; 
        
        ?>
        <?php include 'mobile_sidebar.php'; 
        
        ?>

        

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
                                        <input type="hidden" name="search_submitted" value="1">
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
                                        </div>
                                        
                                        <!-- Enhanced Exam Type Selection -->
                                        <div class="exam-type-container mt-4">
                                            <div class="exam-type-title">Exam Type:</div>
                                            <div class="exam-type-grid">
                                                <div class="exam-type-option">
                                                    <input type="radio" id="exam_type_all" name="exam_type" value="" <?php echo (!isset($_GET['exam_type']) || empty($_GET['exam_type'])) ? 'checked' : ''; ?>>
                                                    <label for="exam_type_all">All Types</label>
                                                </div>
                                                <?php foreach ($exam_types as $index => $type): ?>
                                                    <div class="exam-type-option">
                                                        <input type="radio" id="exam_type_<?php echo $index; ?>" name="exam_type" value="<?php echo $type; ?>" <?php echo (isset($_GET['exam_type']) && $_GET['exam_type'] == $type) ? 'checked' : ''; ?>>
                                                        <label for="exam_type_<?php echo $index; ?>"><?php echo $type; ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="filter-actions mt-6">
                                            <button type="button" id="reset-filters" class="filter-button filter-reset">
                                                <i class="fas fa-undo mr-2"></i> Reset
                                            </button>
                                            <button type="submit" class="search-button ml-3">
                                                <i class="fas fa-search"></i> Search Grade Sheets
                                            </button>
                                        </div>
                                        
                                        <?php if ($search_submitted && (isset($_GET['class_id']) || isset($_GET['academic_year']) || isset($_GET['exam_type']))): ?>
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

                                <?php if ($search_submitted): ?>
                                    <?php if (!empty($exams)): ?>
                                        <div class="mt-6">
                                            <h3 class="text-lg font-medium text-gray-900 mb-3">Select Exam:</h3>
                                            <div class="bg-white overflow-hidden shadow rounded-lg divide-y divide-gray-200">
                                                <div class="px-4 py-5 sm:px-6">
                                                    <h3 class="text-md font-medium text-gray-900">Found <?php echo count($exams); ?> exam(s) matching your criteria</h3>
                                                </div>
                                                <div class="px-4 py-5 sm:p-6">
                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                                        <?php foreach ($exams as $exam): ?>
                                                            <div class="bg-gray-50 overflow-hidden shadow-sm rounded-md hover:bg-blue-50 transition-colors">
                                                                <a href="?search_submitted=1&exam_id=<?php echo $exam['exam_id']; ?>&class_id=<?php echo isset($_GET['class_id']) ? $_GET['class_id'] : ''; ?>&academic_year=<?php echo isset($_GET['academic_year']) ? $_GET['academic_year'] : ''; ?>&exam_type=<?php echo isset($_GET['exam_type']) ? $_GET['exam_type'] : ''; ?>" class="block p-4">
                                                                    <div class="flex items-center">
                                                                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                                                            <i class="fas fa-file-alt text-white"></i>
                                                                        </div>
                                                                        <div class="ml-4">
                                                                            <div class="text-sm font-medium text-gray-900"><?php echo $exam['exam_name']; ?></div>
                                                                            <div class="text-sm text-gray-500"><?php echo $exam['academic_year']; ?></div>
                                                                            <?php if (!empty($exam['exam_type'])): ?>
                                                                                <div class="text-xs text-gray-500 mt-1">
                                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                                        <?php echo $exam['exam_type']; ?>
                                                                                    </span>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </a>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
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
                                    <?php endif; ?>
                                <?php endif; ?>

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
                                    <?php if (!empty($student['exam_type'])): ?>
                                    <div class="info-item">
                                        <span class="info-label">Exam Type:</span>
                                        <span><?php echo $student['exam_type']; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <table class="grade-sheet-table">
                                    <thead>
                                        <tr>
                                            <th>SUBJECT CODE</th>
                                            <th>SUBJECTS</th>
                                            <th>CREDIT HOUR</th>
                                            <th>THEORY GRADE</th>
                                            <th>PRACTICAL GRADE</th>
                                            <th>FINAL GRADE</th>
                                            <th>GRADE POINT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($subjects)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">No results found for this student.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($subjects as $subject): ?>
                                                <tr>
                                                    <td><?php echo $subject['code']; ?></td>
                                                    <td><?php echo $subject['name']; ?></td>
                                                    <td><?php echo $subject['credit_hour']; ?></td>
                                                    <td>
                                                        <?php 
                                                        // Convert theory marks to grade format
                                                        $theory_marks = $subject['theory_marks'];
                                                        $theory_full_marks = $subject['full_marks_theory'] ?? 100;
                                                        if ($theory_marks > 0 && $theory_full_marks > 0) {
                                                            $theory_percentage = ($theory_marks / $theory_full_marks) * 100;
                                                            if ($theory_percentage >= 91) echo 'A+';
                                                            elseif ($theory_percentage >= 81) echo 'A';
                                                            elseif ($theory_percentage >= 71) echo 'B+';
                                                            elseif ($theory_percentage >= 61) echo 'B';
                                                            elseif ($theory_percentage >= 51) echo 'C+';
                                                            elseif ($theory_percentage >= 41) echo 'C';
                                                            elseif ($theory_percentage >= 35) echo 'D+';
                                                            else echo 'NG';
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        // Convert practical marks to grade format
                                                        $practical_marks = $subject['practical_marks'];
                                                        $practical_full_marks = $subject['full_marks_practical'] ?? 0;
                                                        if ($practical_full_marks > 0) {
                                                            if ($practical_marks > 0) {
                                                                $practical_percentage = ($practical_marks / $practical_full_marks) * 100;
                                                                if ($practical_percentage >= 91) echo 'A+';
                                                                elseif ($practical_percentage >= 81) echo 'A';
                                                                elseif ($practical_percentage >= 71) echo 'B+';
                                                                elseif ($practical_percentage >= 61) echo 'B';
                                                                elseif ($practical_percentage >= 51) echo 'C+';
                                                                elseif ($practical_percentage >= 41) echo 'C';
                                                                elseif ($practical_percentage >= 35) echo 'D+';
                                                                else echo 'NG';
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo $subject['grade']; ?></td>
                                                    <td>
                                                        <?php 
                                                        // Calculate grade point based on total marks percentage
                                                        $total_marks = $subject['total_marks'];
                                                        $total_full_marks = ($subject['full_marks_theory'] ?? 100) + ($subject['full_marks_practical'] ?? 0);
                                                        if ($total_marks > 0 && $total_full_marks > 0) {
                                                            $total_percentage = ($total_marks / $total_full_marks) * 100;
                                                            if ($total_percentage >= 91) echo '3.8';
                                                            elseif ($total_percentage >= 81) echo '3.4';
                                                            elseif ($total_percentage >= 71) echo '3.0';
                                                            elseif ($total_percentage >= 61) echo '2.7';
                                                            elseif ($total_percentage >= 51) echo '2.4';
                                                            elseif ($total_percentage >= 41) echo '1.9';
                                                            elseif ($total_percentage >= 35) echo '1.6';
                                                            else echo '0.0';
                                                        } else {
                                                            echo '0.0';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>

                                <div class="summary">
                                    <div class="summary-item">
                                        <div class="summary-label">GRADE POINT AVERAGE</div>
                                        <div class="summary-value"><?php echo number_format($gpa, 2); ?></div>
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
                                            <th>D+</th>
                                            <th>NG</th>
                                        </tr>
                                        <tr>
                                            <th>Marks Range</th>
                                            <td>91-100</td>
                                            <td>81-90</td>
                                            <td>71-80</td>
                                            <td>61-70</td>
                                            <td>51-60</td>
                                            <td>41-50</td>
                                            <td>35-40</td>
                                            <td>Below 35</td>
                                        </tr>
                                        <tr>
                                            <th>Grade Point</th>
                                            <td>3.6-4.0</td>
                                            <td>3.2-3.6</td>
                                            <td>2.8-3.2</td>
                                            <td>2.6-2.8</td>
                                            <td>2.2-2.6</td>
                                            <td>1.6-2.2</td>
                                            <td>1.6</td>
                                            <td>0.0</td>
                                        </tr>
                                        <tr>
                                            <th>Description</th>
                                            <td>Excellent</td>
                                            <td>Very Good</td>
                                            <td>Good</td>
                                            <td>Satisfactory</td>
                                            <td>Acceptable</td>
                                            <td>Partially Acceptable</td>
                                            <td>Borderline</td>
                                            <td>Not Graded</td>
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
            
            // Keep the search_submitted parameter
            if (!url.searchParams.has('search_submitted')) {
                url.searchParams.set('search_submitted', '1');
            }
            
            // Redirect to the new URL
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
