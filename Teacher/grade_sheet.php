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

// Define publication settings - teachers can only see published results
$results_table = 'results';
$publication_condition = "AND (r.is_published = 1 OR r.status = 'published' OR e.results_published = 1)";

// Initialize variables
$student = [];
$subjects = [];
$results = [];
$exams = [];
$performance_data = [];
$chart_data = [];
$subject_names = [];
$time_periods = [];
$gpa_trend = [];

// Get available exams with published results only
$exams_query = "SELECT DISTINCT e.exam_id, e.exam_name, e.exam_type, e.academic_year 
                FROM exams e
                JOIN results r ON e.exam_id = r.exam_id
                WHERE e.is_active = 1 
                AND (r.is_published = 1 OR r.status = 'published' OR e.results_published = 1)
                ORDER BY e.created_at DESC";
$stmt = $conn->prepare($exams_query);
if ($stmt) {
    $stmt->execute();
    $exams_result = $stmt->get_result();
    while ($row = $exams_result->fetch_assoc()) {
        $exams[] = $row;
    }
    $stmt->close();
} else {
    // Fallback query
    $exams_result = $conn->query("SELECT exam_id, exam_name, exam_type, academic_year FROM exams WHERE is_active = 1 ORDER BY created_at DESC");
    while ($row = $exams_result->fetch_assoc()) {
        $exams[] = $row;
    }
}

// Check if viewing a specific student result
if (isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    
    // Get student details
    $stmt = $conn->prepare("
        SELECT s.student_id, s.roll_number, s.registration_number, u.full_name, 
               c.class_name, c.section, c.academic_year
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        JOIN classes c ON s.class_id = c.class_id
        WHERE s.student_id = ?
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        die("Student not found");
    }
    
    $student = $result->fetch_assoc();
    $stmt->close();
    
    // Get subjects taught by this teacher for this student
    $stmt = $conn->prepare("
    SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code, s.credit_hours
    FROM subjects s
    JOIN results r ON s.subject_id = r.subject_id
    WHERE r.student_id = ?
    ORDER BY s.subject_id
");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $subjects_result = $stmt->get_result();
    
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects[] = $row;
        $subject_names[] = $row['subject_name'];
    }
    $stmt->close();
    
    // Get selected exam or default to the most recent
    $selected_exam = isset($_GET['exam_id']) ? $_GET['exam_id'] : null;
    
    if (!$selected_exam && count($exams) > 0) {
        $selected_exam = $exams[0]['exam_id'];
    }
    
    // Get results for all exams for this student (only for teacher's subjects)
    $query = "SELECT r.*, e.exam_name, e.exam_type, e.academic_year, s.subject_name
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    JOIN subjects s ON r.subject_id = s.subject_id
    WHERE r.student_id = ?
    AND (r.is_published = 1 OR r.status = 'published' OR e.results_published = 1)
    ORDER BY e.created_at DESC, s.subject_name ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $results_data = $stmt->get_result();
    
    // Organize results by exam
    $results_by_exam = [];
    $chart_data = [];
    $time_periods = [];
    
    while ($row = $results_data->fetch_assoc()) {
        $exam_id = $row['exam_id'];
        
        if (!isset($results_by_exam[$exam_id])) {
            $results_by_exam[$exam_id] = [
                'exam_id' => $exam_id,
                'exam_name' => $row['exam_name'],
                'exam_type' => $row['exam_type'],
                'academic_year' => $row['academic_year'],
                'subjects' => []
            ];
            
            // Add to time periods for charts
            if (!in_array($row['exam_name'], $time_periods)) {
                $time_periods[] = $row['exam_name'];
            }
        }
        
        $results_by_exam[$exam_id]['subjects'][$row['subject_id']] = [
            'subject_id' => $row['subject_id'],
            'subject_name' => $row['subject_name'],
            'theory_marks' => $row['theory_marks'],
            'practical_marks' => $row['practical_marks'],
            'total_marks' => $row['theory_marks'] + $row['practical_marks'],
            'grade' => $row['grade'],
            'gpa' => $row['gpa'],
            'remarks' => $row['remarks'] ?? ''
        ];
        
        // Add to chart data
        $chart_data[] = [
            'period' => $row['exam_name'],
            'subject' => $row['subject_name'],
            'theory_marks' => $row['theory_marks'],
            'practical_marks' => $row['practical_marks'],
            'gpa' => $row['gpa']
        ];
    }
    $stmt->close();
    
    // Get performance data for all exams
    $stmt = $conn->prepare("
        SELECT sp.*, e.exam_name
        FROM student_performance sp
        JOIN exams e ON sp.exam_id = e.exam_id
        JOIN results r ON sp.student_id = r.student_id AND sp.exam_id = r.exam_id
        WHERE sp.student_id = ?
        AND (r.is_published = 1 OR r.status = 'published' OR e.results_published = 1)
        ORDER BY e.created_at DESC
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $performance_result = $stmt->get_result();
    
    while ($row = $performance_result->fetch_assoc()) {
        $performance_data[$row['exam_id']] = $row;
        
        // Add to GPA trend for charts
        $gpa_trend[] = $row['gpa'];
    }
    $stmt->close();
    
    // If we have a selected exam, get detailed results for it
    if ($selected_exam) {
        $stmt = $conn->prepare("
    SELECT DISTINCT r.*, s.subject_name, s.subject_code, s.full_marks_theory, s.full_marks_practical, s.credit_hours
    FROM results r
    JOIN subjects s ON r.subject_id = s.subject_id
    WHERE r.student_id = ? AND r.exam_id = ?
    AND (r.is_published = 1 OR r.status = 'published' OR 
         EXISTS (SELECT 1 FROM exams e WHERE e.exam_id = r.exam_id AND e.results_published = 1))
    GROUP BY r.subject_id
    ORDER BY s.subject_name
");
        $stmt->bind_param("si", $student_id, $selected_exam);
        $stmt->execute();
        $current_results = $stmt->get_result();
        
        $results = [];
        
        while ($row = $current_results->fetch_assoc()) {
            $results[] = [
                'subject_id' => $row['subject_id'],
                'subject_name' => $row['subject_name'],
                'subject_code' => $row['subject_code'] ?? $row['subject_id'],
                'credit_hours' => $row['credit_hours'] ?? 3,
                'theory_marks' => $row['theory_marks'] ?? 0,
                'practical_marks' => $row['practical_marks'] ?? 0,
                'grade' => $row['grade'],
                'gpa' => $row['gpa'],
                'remarks' => $row['remarks'] ?? '',
                'full_marks_theory' => $row['full_marks_theory'] ?? 100,
                'full_marks_practical' => $row['full_marks_practical'] ?? 0
            ];
        }
        $stmt->close();
    }
} else {
    // If no specific student is requested, show a list of students to select
    $show_student_list = true;
    
    // Get students taught by this teacher
    $students_query = "
    SELECT DISTINCT s.student_id, s.roll_number, u.full_name, c.class_name, c.section
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN classes c ON s.class_id = c.class_id
    JOIN results r ON s.student_id = r.student_id
    WHERE (r.is_published = 1 OR r.status = 'published' OR 
           EXISTS (SELECT 1 FROM exams e WHERE e.exam_id = r.exam_id AND e.results_published = 1))
    ORDER BY c.class_name, c.section, s.roll_number
";
    $stmt = $conn->prepare($students_query);
    if ($stmt) {
        $stmt->execute();
        $students_result = $stmt->get_result();
        
        while ($row = $students_result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
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

// Function to get grade letter from percentage
function getGradeLetter($percentage)
{
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C+';
    if ($percentage >= 40) return 'C';
    if ($percentage >= 33) return 'D';
    return 'F';
}

// Function to get remarks based on grade
function getRemarks($grade)
{
    switch ($grade) {
        case 'A+': return 'Outstanding';
        case 'A': return 'Excellent';
        case 'B+': return 'Very Good';
        case 'B': return 'Good';
        case 'C+': return 'Satisfactory';
        case 'C': return 'Acceptable';
        case 'D': return 'Needs Improvement';
        case 'F': return 'Fail';
        default: return '';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Sheets | Teacher Dashboard</title>
    <link href="../css/tailwind.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        .result-container {
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

        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .result-table,
        .result-table th,
        .result-table td {
            border: 1px solid #bdc3c7;
        }

        .result-table th,
        .result-table td {
            padding: 10px;
            text-align: center;
        }

        .result-table th {
            background-color: #1a5276;
            color: white;
            font-weight: bold;
        }

        .result-table tr:nth-child(even) {
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

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }

        .grade-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .grade-a-plus {
            background-color: #dcfce7;
            color: #166534;
        }

        .grade-a {
            background-color: #dcfce7;
            color: #166534;
        }

        .grade-b-plus {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .grade-b {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .grade-c-plus {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .grade-c {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .grade-d {
            background-color: #ffedd5;
            color: #9a3412;
        }

        .grade-f {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media print {
            body {
                background-color: white !important;
            }

            .result-container {
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
            .selection-container,
            .tab-buttons,
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .tab-content {
                display: block !important;
            }
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

        /* Simple Overall Result Summary styles */
        .simple-summary {
            margin: 20px 0;
            position: relative;
            z-index: 1;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
            padding: 20px;
        }

        .simple-summary h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
            font-weight: bold;
        }

        .simple-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .simple-summary-item {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }

        .simple-summary-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .simple-summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .simple-additional-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .simple-info-item {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }

        .simple-info-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .simple-info-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .simple-info-value.pass {
            color: #28a745;
        }

        .simple-info-value.fail {
            color: #dc3545;
        }

        /* Print-specific styles for summary */
        @media print {
            .simple-summary .simple-summary-grid {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 1rem !important;
            }
            
            .simple-summary .simple-summary-item,
            .simple-summary .simple-info-item {
                background-color: #f8f9fa !important;
                border: 1px solid #dee2e6 !important;
                box-shadow: none !important;
            }
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
    </style>
</head>

<body class="bg-gray-100">
    
    <div class="flex">
        <?php include 'includes/teacher_sidebar.php'; ?>
        
        <div class="flex-1 p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Grade Sheets</h1>
                <div class="flex space-x-3">
                    <a href="teacher_dashboard.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
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
            
           

            <!-- Published Results Notice -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Published Results Only:</strong> You can only view results that have been officially published by the administration.
                        </p>
                    </div>
                </div>
            </div>
            
            <?php if (isset($show_student_list)): ?>
                <!-- Student Selection Form -->
                <div class="bg-white shadow rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Select Student</h2>
                    <?php if (empty($students)): ?>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        No students found. You may not have any assigned subjects with results yet.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
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
                                                <a href="?student_id=<?php echo $student['student_id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                    <i class="fas fa-eye mr-1"></i> View Grade Sheet
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Student Result View -->
                <div class="mb-4 flex justify-between">
                    <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-arrow-left mr-2"></i> Back to List
                    </a>
                    <div>
                        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 mr-2">
                            <i class="fas fa-print mr-2"></i> Print Grade Sheet
                        </button>
                        
                    </div>
                </div>

                <!-- Student Information Card -->
                <div class="bg-white shadow rounded-lg p-6 mb-6">
                    <div class="flex flex-col md:flex-row justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900"><?php echo $student['full_name']; ?></h2>
                            <p class="text-gray-600">Roll Number: <?php echo $student['roll_number']; ?></p>
                            <p class="text-gray-600">Registration Number: <?php echo $student['registration_number']; ?></p>
                        </div>
                        <div class="mt-4 md:mt-0 text-right">
                            <p class="text-gray-600">Class: <?php echo $student['class_name'] . ' ' . $student['section']; ?></p>
                            <p class="text-gray-600">Academic Year: <?php echo $student['academic_year']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Exam Selection -->
                <div class="bg-white shadow rounded-lg p-6 mb-6 no-print">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Select Exam</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($exams as $exam): ?>
                            <?php if (isset($results_by_exam[$exam['exam_id']])): ?>
                                <a href="?student_id=<?php echo $student_id; ?>&exam_id=<?php echo $exam['exam_id']; ?>" 
                                   class="px-4 py-2 rounded-md text-sm font-medium <?php echo ($selected_exam == $exam['exam_id']) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                                    <?php echo $exam['exam_name']; ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="bg-white shadow rounded-lg mb-6 overflow-hidden">
                    <div class="border-b border-gray-200 no-print">
                        <nav class="flex -mb-px">
                            <button onclick="openTab('result')" class="tab-button w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm border-blue-500 text-blue-600">
                                <i class="fas fa-clipboard-list mr-2"></i> Grade Sheet
                            </button>
                            <button onclick="openTab('progress')" class="tab-button w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-chart-line mr-2"></i> Progress Tracking
                            </button>
                        </nav>
                    </div>

                    <!-- Result Tab Content -->
                    <div id="result" class="tab-content active p-6">
    <?php if (isset($selected_exam) && !empty($results)): ?>
        <!-- Grade Sheet Container with proper styling -->
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
                    <span><?php echo $exams[array_search($selected_exam, array_column($exams, 'exam_id'))]['exam_name']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Academic Year:</span>
                    <span><?php echo $student['academic_year']; ?></span>
                </div>
                <?php if (!empty($exams[array_search($selected_exam, array_column($exams, 'exam_id'))]['exam_type'])): ?>
                <div class="info-item">
                    <span class="info-label">Exam Type:</span>
                    <span><?php echo $exams[array_search($selected_exam, array_column($exams, 'exam_id'))]['exam_type']; ?></span>
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
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">No results found for this student.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $total_marks = 0;
                        $max_marks = 0;
                        $total_subjects = 0;
                        $total_grade_points = 0;
                        
                        foreach ($results as $result): 
                            $theory_marks = $result['theory_marks'] ?? 0;
                            $practical_marks = $result['practical_marks'] ?? 0;
                            $total_subject_marks = $theory_marks + $practical_marks;
                            $subject_max_marks = $result['full_marks_theory'] + $result['full_marks_practical'];

                            // Enhanced subject processing with detailed grade calculations
                            // Determine if subject has practical
                            $has_practical = !is_null($result['practical_marks']) && $result['practical_marks'] > 0;
                            
                            // Set full marks based on practical existence
                            $theory_full_marks = $has_practical ? 75 : 100;
                            $practical_full_marks = $has_practical ? 25 : 0;
                            
                            // Calculate theory and practical percentages
                            $theory_percentage = $theory_full_marks > 0 ? 
                                ($theory_marks / $theory_full_marks) * 100 : 0;
                            $practical_percentage = $practical_full_marks > 0 ? 
                                ($practical_marks / $practical_full_marks) * 100 : 0;
                            
                            // Calculate final grade and GPA
                            $total_obtained = $theory_marks + $practical_marks;
                            $total_full = $theory_full_marks + $practical_full_marks;
                            $total_percentage = $total_full > 0 ? ($total_obtained / $total_full) * 100 : 0;
                            
                            // Check for failure (35% rule)
                            $theory_failed = $theory_percentage < 35;
                            $practical_failed = $has_practical && $practical_percentage < 35;
                            
                            if ($theory_failed || $practical_failed) {
                                $calculated_grade = 'NG';
                                $calculated_gpa = 0.0;
                            } else {
                                // Calculate grade based on total percentage
                                if ($total_percentage >= 90) {
                                    $calculated_grade = 'A+';
                                    $calculated_gpa = 4.0;
                                } elseif ($total_percentage >= 80) {
                                    $calculated_grade = 'A';
                                    $calculated_gpa = 3.6;
                                } elseif ($total_percentage >= 70) {
                                    $calculated_grade = 'B+';
                                    $calculated_gpa = 3.2;
                                } elseif ($total_percentage >= 60) {
                                    $calculated_grade = 'B';
                                    $calculated_gpa = 2.8;
                                } elseif ($total_percentage >= 50) {
                                    $calculated_grade = 'C+';
                                    $calculated_gpa = 2.4;
                                } elseif ($total_percentage >= 40) {
                                    $calculated_grade = 'C';
                                    $calculated_gpa = 2.0;
                                } elseif ($total_percentage >= 35) {
                                    $calculated_grade = 'D';
                                    $calculated_gpa = 1.6;
                                } else {
                                    $calculated_grade = 'NG';
                                    $calculated_gpa = 0.0;
                                }
                            }

                            $total_marks += $total_subject_marks;
                            $total_subjects++;
                            $max_marks += $subject_max_marks;
                            $total_grade_points += $calculated_gpa;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['subject_code'] ?? $result['subject_id']); ?></td>
                                <td><?php echo htmlspecialchars($result['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['credit_hours'] ?? 3); ?></td>
                                <td>
                                    <?php 
                                    // Calculate theory grade from marks
                                    if ($theory_full_marks > 0) {
                                        if ($theory_percentage >= 91) echo 'A+';
                                        elseif ($theory_percentage >= 81) echo 'A';
                                        elseif ($theory_percentage >= 71) echo 'B+';
                                        elseif ($theory_percentage >= 61) echo 'B';
                                        elseif ($theory_percentage >= 51) echo 'C+';
                                        elseif ($theory_percentage >= 41) echo 'C';
                                        elseif ($theory_percentage >= 35) echo 'D';
                                        else echo 'NG';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    // Calculate practical grade from marks
                                    if ($has_practical && $practical_full_marks > 0) {
                                        if ($practical_marks > 0) {
                                            if ($practical_percentage >= 90) echo 'A+';
                                            elseif ($practical_percentage >= 80) echo 'A';
                                            elseif ($practical_percentage >= 70) echo 'B+';
                                            elseif ($practical_percentage >= 60) echo 'B';
                                            elseif ($practical_percentage >= 50) echo 'C+';
                                            elseif ($practical_percentage >= 40) echo 'C';
                                            elseif ($practical_percentage >= 35) echo 'D';
                                            else echo 'NG';
                                        } else {
                                            echo 'N/A';
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    // Use calculated grade or fall back to stored grade
                                    echo htmlspecialchars($calculated_grade ?? $result['grade'] ?? 'N/A'); 
                                    ?>
                                </td>
                                <td>
                                    <?php echo number_format($calculated_gpa, 1); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Simple Overall Result Summary -->
            <?php
            // Check if any subject has NG grade
            $has_ng_grade = false;
            $failed_subjects = 0;
            
            foreach ($results as $result) {
                $theory_marks = $result['theory_marks'] ?? 0;
                $practical_marks = $result['practical_marks'] ?? 0;
                $has_practical = !is_null($result['practical_marks']) && $result['practical_marks'] > 0;
                
                $theory_full_marks = $has_practical ? 75 : 100;
                $practical_full_marks = $has_practical ? 25 : 0;
                
                $theory_percentage = $theory_full_marks > 0 ? ($theory_marks / $theory_full_marks) * 100 : 0;
                $practical_percentage = $practical_full_marks > 0 ? ($practical_marks / $practical_full_marks) * 100 : 0;
                
                // Check for failure (35% rule)
                $theory_failed = $theory_percentage < 35;
                $practical_failed = $has_practical && $practical_percentage < 35;
                
                if ($theory_failed || $practical_failed) {
                    $has_ng_grade = true;
                    $failed_subjects++;
                }
            }
            
            $percentage = $max_marks > 0 ? ($total_marks / $max_marks) * 100 : 0;
            $gpa = $total_subjects > 0 ? ($total_grade_points / $total_subjects) : 0;
            $is_pass = ($failed_subjects == 0 && $percentage >= 35);
            ?>

            <div class="simple-summary">
                <h2>Overall Result Summary</h2>
                
                <!-- Summary Cards -->
                <div class="simple-summary-grid">
                    <div class="simple-summary-item">
                        <div class="simple-summary-label">Total Marks</div>
                        <div class="simple-summary-value">
                            <?php echo number_format($total_marks, 0) . ' / ' . number_format($max_marks, 0); ?>
                        </div>
                    </div>
                    <div class="simple-summary-item">
                        <div class="simple-summary-label">Percentage</div>
                        <div class="simple-summary-value"><?php echo number_format($percentage, 2); ?>%</div>
                    </div>
                    <div class="simple-summary-item">
                        <div class="simple-summary-label">GPA</div>
                        <div class="simple-summary-value"><?php echo number_format($gpa, 2); ?> / 4.0</div>
                    </div>
                    <?php if (!$has_ng_grade): ?>
                    <div class="simple-summary-item">
                        <div class="simple-summary-label">Grade</div>
                        <div class="simple-summary-value">
                            <?php
                            // Calculate grade based on percentage
                            if ($percentage >= 91) echo 'A+';
                            elseif ($percentage >= 81) echo 'A';
                            elseif ($percentage >= 71) echo 'B+';
                            elseif ($percentage >= 61) echo 'B';
                            elseif ($percentage >= 51) echo 'C+';
                            elseif ($percentage >= 41) echo 'C';
                            elseif ($percentage >= 35) echo 'D';
                            else echo 'NG';
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Additional Information -->
                <div class="simple-additional-info">
                    <div class="simple-info-item">
                        <div class="simple-info-label">Result Status</div>
                        <div class="simple-info-value <?php echo $is_pass ? 'pass' : 'fail'; ?>">
                            <?php echo $is_pass ? 'PASS' : 'FAIL'; ?>
                        </div>
                    </div>
                    
                    <div class="simple-info-item">
                        <div class="simple-info-label">Division</div>
                        <div class="simple-info-value">
                            <?php 
                            $division = '';
                            if ($percentage >= 91) $division = 'Distinction (A+)';
                            elseif ($percentage >= 81) $division = 'First Division (A)';
                            elseif ($percentage >= 71) $division = 'Second Division (B+)';
                            elseif ($percentage >= 61) $division = 'Second Division (B)';
                            elseif ($percentage >= 51) $division = 'Third Division (C+)';
                            elseif ($percentage >= 41) $division = 'Third Division (C)';
                            elseif ($percentage >= 35) $division = 'Pass (D)';
                            else $division = 'Not Graded (NG)';
                            
                            echo $division;
                            ?>
                        </div>
                    </div>
                    
                    <div class="simple-info-item">
                        <div class="simple-info-label">Failed Subjects</div>
                        <div class="simple-info-value <?php echo $failed_subjects > 0 ? 'fail' : 'pass'; ?>">
                            <?php echo $failed_subjects; ?>
                        </div>
                    </div>
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
                    <div><?php echo $teacher_name; ?></div>
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
                <p>Issue Date: <?php echo date('d-m-Y'); ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        No results found for this exam in your subjects. Please select another exam or contact your administrator.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

                    <!-- Progress Tracking Tab -->
                    <div id="progress" class="tab-content p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Academic Progress Tracking</h3>
                        
                        <?php if (count($gpa_trend) > 0): ?>
                            <!-- GPA Progress Chart -->
                            <div class="mb-8">
                                <h4 class="text-md font-medium text-gray-700 mb-2">GPA Progress</h4>
                                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                    <div class="chart-container">
                                        <canvas id="gpaChart"></canvas>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-500 mt-2">This chart shows the student's GPA progression over time in your subjects.</p>
                            </div>
                            
                            <!-- Subject Performance Chart -->
                            <div class="mb-8">
                                <h4 class="text-md font-medium text-gray-700 mb-2">Subject Performance</h4>
                                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                    <div class="chart-container">
                                        <canvas id="subjectChart"></canvas>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-500 mt-2">Compare the student's performance across your subjects.</p>
                            </div>
                            
                            <!-- Performance Summary -->
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h4 class="text-md font-medium text-blue-800 mb-2">Performance Summary</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="bg-white p-4 rounded-lg shadow-sm">
                                        <h5 class="font-medium text-blue-700 mb-1">Current GPA</h5>
                                        <div class="flex items-baseline">
                                            <span class="text-2xl font-bold text-blue-900"><?php echo number_format(end($gpa_trend), 2); ?></span>
                                            <span class="text-sm text-gray-500 ml-1">/ 4.0</span>
                                        </div>
                                        <div class="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-blue-600 rounded-full" style="width: <?php echo (end($gpa_trend) / 4) * 100; ?>%;"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white p-4 rounded-lg shadow-sm">
                                        <h5 class="font-medium text-blue-700 mb-1">Improvement</h5>
                                        <?php 
                                        $improvement = count($gpa_trend) >= 2 ? end($gpa_trend) - $gpa_trend[0] : 0;
                                        $improvement_percent = count($gpa_trend) >= 2 ? ($improvement / max(0.1, $gpa_trend[0])) * 100 : 0;
                                        ?>
                                        <div class="flex items-baseline">
                                            <span class="text-2xl font-bold <?php echo $improvement >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo $improvement >= 0 ? '+' : ''; ?><?php echo number_format($improvement, 2); ?>
                                            </span>
                                            <span class="text-sm text-gray-500 ml-1">points</span>
                                        </div>
                                        <p class="text-sm <?php echo $improvement >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $improvement >= 0 ? '+' : ''; ?><?php echo number_format($improvement_percent, 1); ?>% since first term
                                        </p>
                                    </div>
                                    
                                    <div class="bg-white p-4 rounded-lg shadow-sm">
                                        <h5 class="font-medium text-blue-700 mb-1">Best Subject</h5>
                                        <?php
                                        // Find best subject from chart data
                                        $best_subject = '';
                                        $best_gpa = 0;
                                        foreach ($chart_data as $data) {
                                            if ($data['gpa'] > $best_gpa) {
                                                $best_gpa = $data['gpa'];
                                                $best_subject = $data['subject'];
                                            }
                                        }
                                        ?>
                                        <div class="text-lg font-semibold text-blue-900"><?php echo $best_subject; ?></div>
                                        <div class="flex items-baseline">
                                            <span class="text-2xl font-bold text-green-600"><?php echo number_format($best_gpa, 2); ?></span>
                                            <span class="text-sm text-gray-500 ml-1">GPA</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700">
                                            Not enough data to show progress tracking. Results from multiple exams are needed.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
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
        
        // Tab functionality
        function openTab(tabName) {
            const tabContents = document.getElementsByClassName('tab-content');
            const tabButtons = document.getElementsByClassName('tab-button');
            
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
                tabButtons[i].classList.remove('border-blue-500', 'text-blue-600');
                tabButtons[i].classList.add('border-transparent', 'text-gray-500');
            }
            
            document.getElementById(tabName).classList.add('active');
            
            // Find the button that opened this tab and style it as active
            for (let i = 0; i < tabButtons.length; i++) {
                if (tabButtons[i].getAttribute('onclick').includes(tabName)) {
                    tabButtons[i].classList.remove('border-transparent', 'text-gray-500');
                    tabButtons[i].classList.add('border-blue-500', 'text-blue-600');
                }
            }
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (count($gpa_trend) > 0): ?>
            // GPA Progress Chart
            const gpaCtx = document.getElementById('gpaChart');
            if (gpaCtx) {
                new Chart(gpaCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($time_periods); ?>,
                        datasets: [{
                            label: 'GPA',
                            data: <?php echo json_encode($gpa_trend); ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 2,
                            tension: 0.1,
                            fill: true,
                            pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false,
                                min: 0,
                                max: 4,
                                title: {
                                    display: true,
                                    text: 'GPA (0-4)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Exams'
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `GPA: ${context.raw.toFixed(2)}`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Subject Performance Chart
            const subjectCtx = document.getElementById('subjectChart');
            if (subjectCtx) {
                // Prepare data for subject chart
                const subjectData = {};
                <?php foreach ($subject_names as $subject): ?>
                    subjectData['<?php echo $subject; ?>'] = [];
                <?php endforeach; ?>
                
                <?php foreach ($chart_data as $data): ?>
                    if (subjectData['<?php echo $data['subject']; ?>']) {
                        subjectData['<?php echo $data['subject']; ?>'].push({
                            x: '<?php echo $data['period']; ?>',
                            y: <?php echo $data['gpa']; ?>
                        });
                    }
                <?php endforeach; ?>
                
                const datasets = [];
                const colors = [
                    'rgba(59, 130, 246, 1)', // blue
                    'rgba(16, 185, 129, 1)', // green
                    'rgba(245, 158, 11, 1)', // amber
                    'rgba(239, 68, 68, 1)',  // red
                    'rgba(139, 92, 246, 1)', // purple
                    'rgba(236, 72, 153, 1)'  // pink
                ];
                
                let colorIndex = 0;
                for (const subject in subjectData) {
                    if (subjectData[subject].length > 0) {
                        datasets.push({
                            label: subject,
                            data: subjectData[subject],
                            borderColor: colors[colorIndex % colors.length],
                            backgroundColor: colors[colorIndex % colors.length].replace('1)', '0.2)'),
                            borderWidth: 2,
                            tension: 0.1
                        });
                        colorIndex++;
                    }
                }
                
                new Chart(subjectCtx, {
                    type: 'line',
                    data: {
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false,
                                min: 0,
                                max: 4,
                                title: {
                                    display: true,
                                    text: 'GPA (0-4)'
                                }
                            },
                            x: {
                                type: 'category',
                                title: {
                                    display: true,
                                    text: 'Exams'
                                }
                            }
                        }
                    }
                });
            }
            <?php endif; ?>
        });
        
        // PDF Generation
        function generatePDF(type = 'current') {
            // Check if jsPDF is loaded
            if (typeof jsPDF === 'undefined') {
                console.error('jsPDF library not loaded');
                alert(' library not loaded');
                alert('PDF generation library not loaded. Please refresh the page and try again.');
                return;
            }
            
            const doc = new jsPDF('p', 'mm', 'a4');
            
            // Add title
            doc.setFontSize(18);
            doc.setTextColor(26, 82, 118); // #1a5276
            
            if (type === 'current') {
                doc.text('Student Grade Sheet', 105, 20, { align: 'center' });
                
                // Add student info
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.text('Student Name: <?php echo isset($student['full_name']) ? $student['full_name'] : ''; ?>', 20, 35);
                doc.text('Roll Number: <?php echo isset($student['roll_number']) ? $student['roll_number'] : ''; ?>', 20, 42);
                doc.text('Class: <?php echo isset($student['class_name']) && isset($student['section']) ? $student['class_name'] . ' ' . $student['section'] : ''; ?>', 20, 49);
                
                <?php if (isset($selected_exam) && !empty($results)): ?>
                // Add exam info
                doc.text('Exam: <?php echo $exams[array_search($selected_exam, array_column($exams, 'exam_id'))]['exam_name']; ?>', 20, 56);
                doc.text('Academic Year: <?php echo $student['academic_year']; ?>', 20, 63);
                
                // Add results table
                doc.autoTable({
                    startY: 70,
                    head: [['Subject Code', 'Subject', 'Credit Hr', 'Theory Grade', 'Practical Grade', 'Final Grade', 'Grade Point']],
                    body: [
                        <?php foreach ($results as $result): ?>
                        ['<?php echo $result['subject_code']; ?>', 
                         '<?php echo $result['subject_name']; ?>', 
                         '<?php echo $result['credit_hours']; ?>',
                         '<?php 
                         $theory_marks = $result['theory_marks'];
                         $theory_full_marks = $result['full_marks_theory'] ?? 100;
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
                         ?>', 
                         '<?php 
                         $practical_marks = $result['practical_marks'];
                         $practical_full_marks = $result['full_marks_practical'] ?? 0;
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
                         ?>', 
                         '<?php echo $result['grade']; ?>',
                         '<?php 
                         $total_marks = $result['total_marks'];
                         $total_full_marks = ($result['full_marks_theory'] ?? 100) + ($result['full_marks_practical'] ?? 0);
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
                         ?>'],
                        <?php endforeach; ?>
                    ],
                    theme: 'striped',
                    headStyles: { fillColor: [26, 82, 118] },
                    styles: { fontSize: 8 }
                });
                
                // Add summary
                const finalY = doc.lastAutoTable.finalY + 10;
                doc.setFontSize(12);
                doc.text('Summary:', 20, finalY);
                doc.text('GPA: <?php echo number_format($overall_performance['gpa'], 2); ?>', 20, finalY + 7);
                doc.text('Percentage: <?php echo number_format($overall_performance['average_marks'], 2); ?>%', 20, finalY + 14);
                doc.text('Rank: <?php echo $overall_performance['rank']; ?>', 20, finalY + 21);
                <?php endif; ?>
            }
            
            // Add footer
            doc.setFontSize(8);
            doc.setTextColor(128, 128, 128);
            doc.text('Generated on: ' + new Date().toLocaleDateString(), 20, 280);
            doc.text('Teacher: <?php echo $teacher_name; ?>', 20, 285);
            
            // Save the PDF
            const filename = type === 'current' ? 
                'grade_sheet_<?php echo isset($student['full_name']) ? str_replace(' ', '_', $student['full_name']) : 'student'; ?>.pdf' :
                'progress_report_<?php echo isset($student['full_name']) ? str_replace(' ', '_', $student['full_name']) : 'student'; ?>.pdf';
            
            doc.save(filename);
        }
    </script>
</body>
</html>
