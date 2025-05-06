<?php
// Start session for authentication check
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
$results = [];
$exams = [];
$performance_data = [];
$chart_data = [];
$subject_names = [];
$time_periods = [];
$gpa_trend = [];

// Get available exams
$exams_query = "SELECT exam_id, exam_name, exam_type, academic_year FROM exams WHERE is_active = 1 ORDER BY created_at DESC";
$exams_result = $conn->query($exams_query);
while ($row = $exams_result->fetch_assoc()) {
    $exams[] = $row;
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
    
    // Get all subjects for this student's class
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
    
    // Get results for all exams for this student
    $stmt = $conn->prepare("
        SELECT r.*, e.exam_name, e.exam_type, e.academic_year, s.subject_name
        FROM results r
        JOIN exams e ON r.exam_id = e.exam_id
        JOIN subjects s ON r.subject_id = s.subject_id
        WHERE r.student_id = ?
        ORDER BY e.created_at DESC, s.subject_id
    ");
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
        WHERE sp.student_id = ?
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
            SELECT r.*, s.subject_name, s.subject_code, s.full_marks_theory, s.full_marks_practical
            FROM results r
            JOIN subjects s ON r.subject_id = s.subject_id
            WHERE r.student_id = ? AND r.exam_id = ?
            ORDER BY s.subject_id
        ");
        $stmt->bind_param("si", $student_id, $selected_exam);
        $stmt->execute();
        $current_results = $stmt->get_result();
        
        $results = [];
        $total_marks = 0;
        $total_max_marks = 0;
        
        while ($row = $current_results->fetch_assoc()) {
            $theory_marks = $row['theory_marks'] ?? 0;
            $practical_marks = $row['practical_marks'] ?? 0;
            $total_subject_marks = $theory_marks + $practical_marks;
            $max_marks = $row['full_marks_theory'] + $row['full_marks_practical'];
            
            $results[] = [
                'subject_id' => $row['subject_id'],
                'subject_name' => $row['subject_name'],
                'subject_code' => $row['subject_code'] ?? $row['subject_id'],
                'credit_hours' => $row['credit_hours'],
                'theory_marks' => $theory_marks,
                'practical_marks' => $practical_marks,
                'total_marks' => $total_subject_marks,
                'max_marks' => $max_marks,
                'grade' => $row['grade'],
                'gpa' => $row['gpa'],
                'remarks' => $row['remarks'] ?? ''
            ];
            
            $total_marks += $total_subject_marks;
            $total_max_marks += $max_marks;
        }
        $stmt->close();
        
        // Get overall performance for selected exam
        $overall_performance = isset($performance_data[$selected_exam]) ? $performance_data[$selected_exam] : null;
        
        if (!$overall_performance) {
            // Calculate basic performance metrics if not available
            $percentage = $total_max_marks > 0 ? ($total_marks / $total_max_marks) * 100 : 0;
            $gpa = calculateGPA($percentage, $conn);
            
            $overall_performance = [
                'average_marks' => $percentage,
                'gpa' => $gpa,
                'rank' => 'N/A'
            ];
        }
    }
} else {
    // If no specific student is requested, show a list of students to select
    $show_student_list = true;
    
    // Get students
    $students = [];
    $students_query = "
        SELECT s.student_id, s.roll_number, u.full_name, c.class_name, c.section
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        JOIN classes c ON s.class_id = c.class_id
        ORDER BY c.class_name, c.section, s.roll_number
    ";
    $students_result = $conn->query($students_query);
    
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
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

// Get school settings
$settings = [];
$settings_result = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Results | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
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
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
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
                        <a href="student_result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-user-graduate mr-3"></i>
                            Student Results
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
            <?php
        // Include the file that processes form data
        include 'topBar.php';
        ?>

            <!-- Main Content Area -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <?php if (isset($show_student_list)): ?>
                            <!-- Student Selection Form -->
                            <div class="bg-white shadow rounded-lg p-6 mb-6">
                                <h2 class="text-lg font-medium text-gray-900 mb-4">Select Student</h2>
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
                                                            <i class="fas fa-eye mr-1"></i> View Results
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Student Result View -->
                            <div class="mb-4 flex justify-between">
                                <a href="students_result.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                                </a>
                                <div>
                                    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 mr-2">
                                        <i class="fas fa-print mr-2"></i> Print Result
                                    </button>
                                    <button onclick="generatePDF('current')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                        <i class="fas fa-file-pdf mr-2"></i> Download PDF
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
                                        <button onclick="openTab('result')" class="tab-button w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm border-blue-500 text-blue-600">
                                            <i class="fas fa-clipboard-list mr-2"></i> Result Sheet
                                        </button>
                                        <button onclick="openTab('progress')" class="tab-button w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                            <i class="fas fa-chart-line mr-2"></i> Progress Tracking
                                        </button>
                                        <button onclick="openTab('download')" class="tab-button w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                            <i class="fas fa-download mr-2"></i> Download Options
                                        </button>
                                    </nav>
                                </div>

                                <!-- Result Tab Content -->
                                <div id="result" class="tab-content active p-6">
                                    <?php if (isset($selected_exam) && !empty($results)): ?>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                                            <?php echo $exams[array_search($selected_exam, array_column($exams, 'exam_id'))]['exam_name']; ?> Results
                                        </h3>
                                        
                                        <div class="overflow-x-auto">
                                            <table class="result-table min-w-full">
                                                <thead>
                                                    <tr>
                                                        <th>Subject Code</th>
                                                        <th>Subject</th>
                                                        <th>Credit Hours</th>
                                                        <th>Theory</th>
                                                        <th>Practical</th>
                                                        <th>Total</th>
                                                        <th>Grade</th>
                                                        <th>GPA</th>
                                                        <th>Remarks</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($results as $result): ?>
                                                        <tr>
                                                            <td><?php echo $result['subject_code']; ?></td>
                                                            <td><?php echo $result['subject_name']; ?></td>
                                                            <td><?php echo $result['credit_hours']; ?></td>
                                                            <td><?php echo $result['theory_marks']; ?></td>
                                                            <td><?php echo $result['practical_marks']; ?></td>
                                                            <td><?php echo $result['total_marks']; ?></td>
                                                            <td>
                                                                <span class="grade-badge grade-<?php echo strtolower(str_replace('+', '-plus', $result['grade'])); ?>">
                                                                    <?php echo $result['grade']; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo number_format($result['gpa'], 2); ?></td>
                                                            <td><?php echo $result['remarks']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Result Summary -->
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                                <div class="text-sm font-medium text-gray-500 mb-1">GPA</div>
                                                <div class="flex items-baseline">
                                                    <span class="text-3xl font-bold text-gray-900"><?php echo number_format($overall_performance['gpa'], 2); ?></span>
                                                    <span class="text-sm text-gray-500 ml-1">/ 4.0</span>
                                                </div>
                                                <div class="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                    <div class="h-full bg-blue-600 rounded-full" style="width: <?php echo ($overall_performance['gpa'] / 4) * 100; ?>%;"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                                <div class="text-sm font-medium text-gray-500 mb-1">Percentage</div>
                                                <div class="flex items-baseline">
                                                    <span class="text-3xl font-bold text-gray-900"><?php echo number_format($overall_performance['average_marks'], 2); ?></span>
                                                    <span class="text-sm text-gray-500 ml-1">%</span>
                                                </div>
                                                <div class="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                    <div class="h-full bg-green-600 rounded-full" style="width: <?php echo min(100, $overall_performance['average_marks']); ?>%;"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                                <div class="text-sm font-medium text-gray-500 mb-1">Rank</div>
                                                <div class="flex items-baseline">
                                                    <span class="text-3xl font-bold text-gray-900"><?php echo $overall_performance['rank']; ?></span>
                                                    <span class="text-sm text-gray-500 ml-1">in class</span>
                                                </div>
                                                <div class="mt-2 text-sm text-gray-500">
                                                    <?php 
                                                    $division = '';
                                                    $gpa = $overall_performance['gpa'];
                                                    if ($gpa >= 3.6) $division = 'Distinction';
                                                    elseif ($gpa >= 3.2) $division = 'First Division';
                                                    elseif ($gpa >= 2.8) $division = 'Second Division';
                                                    elseif ($gpa >= 2.0) $division = 'Third Division';
                                                    else $division = 'Fail';
                                                    
                                                    echo $division;
                                                    ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Grade Scale Reference -->
                                        <div class="mt-6 bg-gray-50 p-4 rounded-lg text-sm">
                                            <h4 class="font-medium text-gray-700 mb-2">Grade Scale Reference</h4>
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                <div>A+ (4.0): 90-100%</div>
                                                <div>A (3.6): 80-89%</div>
                                                <div>B+ (3.2): 70-79%</div>
                                                <div>B (2.8): 60-69%</div>
                                                <div>C+ (2.4): 50-59%</div>
                                                <div>C (2.0): 40-49%</div>
                                                <div>D (1.6): 30-39%</div>
                                                <div>F (0.0): Below 30%</div>
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
                                                        No results found for this exam. Please select another exam or contact your administrator.
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
                                            <p class="text-sm text-gray-500 mt-2">This chart shows your GPA progression over time.</p>
                                        </div>
                                        
                                        <!-- Subject Performance Chart -->
                                        <div class="mb-8">
                                            <h4 class="text-md font-medium text-gray-700 mb-2">Subject Performance</h4>
                                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                                <div class="chart-container">
                                                    <canvas id="subjectChart"></canvas>
                                                </div>
                                            </div>
                                            <p class="text-sm text-gray-500 mt-2">Compare your performance across different subjects.</p>
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

                                <!-- Download Options Tab -->
                                <div id="download" class="tab-content p-6">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Download Result Sheets</h3>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="bg-blue-50 p-6 rounded-lg">
                                            <h4 class="text-md font-medium text-blue-800 mb-3">Current Result Sheet</h4>
                                            <p class="text-gray-600 mb-4">Download your latest result sheet in PDF format.</p>
                                            <button onclick="generatePDF('current')" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded flex items-center justify-center">
                                                <i class="fas fa-file-pdf mr-2"></i>
                                                Download Result PDF
                                            </button>
                                        </div>
                                        
                                        <div class="bg-green-50 p-6 rounded-lg">
                                            <h4 class="text-md font-medium text-green-800 mb-3">Progress Report</h4>
                                            <p class="text-gray-600 mb-4">Download a comprehensive progress report with charts and analysis.</p>
                                            <button onclick="generatePDF('progress')" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded flex items-center justify-center">
                                                <i class="fas fa-chart-line mr-2"></i>
                                                Download Progress PDF
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-6 bg-gray-50 p-6 rounded-lg">
                                        <h4 class="text-md font-medium text-gray-800 mb-3">Official Format</h4>
                                        <p class="text-gray-600 mb-4">Download your result in the official examination board format.</p>
                                        <button onclick="generatePDF('official')" class="w-full bg-gray-800 hover:bg-gray-900 text-white py-2 px-4 rounded flex items-center justify-center">
                                            <i class="fas fa-file-alt mr-2"></i>
                                            Download Official Format PDF
                                        </button>
                                    </div>
                                    
                                    <div class="mt-6 p-4 bg-yellow-50 rounded-lg">
                                        <div class="flex items-start">
                                            <i class="fas fa-info-circle text-yellow-500 mt-0.5 mr-2"></i>
                                            <div>
                                                <p class="text-sm text-yellow-700">
                                                    <span class="font-medium">Note:</span> These PDF documents are for personal use only. For official purposes, please request a certified copy from the administration office.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
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
                alert('PDF generation library not loaded. Please refresh the page and try again.');
                return;
            }
            
            const doc = new jsPDF('p', 'mm', 'a4');
            
            // Add title
            doc.setFontSize(18);
            doc.setTextColor(26, 82, 118); // #1a5276
            
            if (type === 'current') {
                doc.text('Student Result Sheet', 105, 20, { align: 'center' });
                
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
                    head: [['Subject', 'Theory', 'Practical', 'Total', 'Grade', 'GPA']],
                    body: [
                        <?php foreach ($results as $result): ?>
                        ['<?php echo $result['subject_name']; ?>', 
                         '<?php echo $result['theory_marks']; ?>', 
                         '<?php echo $result['practical_marks']; ?>', 
                         '<?php echo $result['total_marks']; ?>', 
                         '<?php echo $result['grade']; ?>', 
                         '<?php echo number_format($result['gpa'], 2); ?>'],
                        <?php endforeach; ?>
                    ],
                    theme: 'grid',
                    styles: {
                        fontSize: 10
                    },
                    headStyles: {
                        fillColor: [26, 82, 118]
                    }
                });
                
                // Add summary
                const finalY = doc.lastAutoTable.finalY;
                doc.text('GPA: <?php echo number_format($overall_performance['gpa'], 2); ?> / 4.0', 20, finalY + 15);
                doc.text('Percentage: <?php echo number_format($overall_performance['average_marks'], 2); ?>%', 20, finalY + 22);
                doc.text('Rank: <?php echo $overall_performance['rank']; ?>', 20, finalY + 29);
                
                // Add footer
                doc.setFontSize(10);
                doc.setTextColor(100, 100, 100);
                doc.text('This document is computer-generated and does not require a signature.', 105, 280, { align: 'center' });
                doc.text('Generated on: <?php echo date('d-m-Y'); ?>', 105, 285, { align: 'center' });
                <?php endif; ?>
            } else if (type === 'progress') {
                doc.text('Academic Progress Report', 105, 20, { align: 'center' });
                
                // Add student info
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.text('Student Name: <?php echo isset($student['full_name']) ? $student['full_name'] : ''; ?>', 20, 35);
                doc.text('Roll Number: <?php echo isset($student['roll_number']) ? $student['roll_number'] : ''; ?>', 20, 42);
                doc.text('Class: <?php echo isset($student['class_name']) && isset($student['section']) ? $student['class_name'] . ' ' . $student['section'] : ''; ?>', 20, 49);
                doc.text('Academic Year: <?php echo isset($student['academic_year']) ? $student['academic_year'] : ''; ?>', 20, 56);
                
                <?php if (count($gpa_trend) > 0): ?>
                // Add GPA trend
                doc.setFontSize(14);
                doc.setTextColor(26, 82, 118);
                doc.text('GPA Progression', 20, 70);
                
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                
                // Add GPA table
                doc.autoTable({
                    startY: 75,
                    head: [['Exam', 'GPA']],
                    body: [
                        <?php 
                        for ($i = 0; $i < count($time_periods); $i++) {
                            echo "['" . $time_periods[$i] . "', '" . number_format($gpa_trend[$i], 2) . "'],";
                        }
                        ?>
                    ],
                    theme: 'grid',
                    styles: {
                        fontSize: 10
                    },
                    headStyles: {
                        fillColor: [26, 82, 118]
                    }
                });
                
                // Add improvement info
                const finalY1 = doc.lastAutoTable.finalY;
                <?php 
                $improvement = count($gpa_trend) >= 2 ? end($gpa_trend) - $gpa_trend[0] : 0;
                $improvement_percent = count($gpa_trend) >= 2 ? ($improvement / max(0.1, $gpa_trend[0])) * 100 : 0;
                ?>
                
                doc.setFontSize(14);
                doc.setTextColor(26, 82, 118);
                doc.text('Performance Summary', 20, finalY1 + 15);
                
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.text('Current GPA: <?php echo number_format(end($gpa_trend), 2); ?> / 4.0', 20, finalY1 + 25);
                doc.text('GPA Improvement: <?php echo $improvement >= 0 ? '+' : ''; ?><?php echo number_format($improvement, 2); ?> points (<?php echo $improvement >= 0 ? '+' : ''; ?><?php echo number_format($improvement_percent, 1); ?>%)', 20, finalY1 + 32);
                
                // Add best subject
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
                doc.text('Best Subject: <?php echo $best_subject; ?> (GPA: <?php echo number_format($best_gpa, 2); ?>)', 20, finalY1 + 39);
                <?php endif; ?>
                
                // Add footer
                doc.setFontSize(10);
                doc.setTextColor(100, 100, 100);
                doc.text('This document is computer-generated and does not require a signature.', 105, 280, { align: 'center' });
                doc.text('Generated on: <?php echo date('d-m-Y'); ?>', 105, 285, { align: 'center' });
            } else if (type === 'official') {
                doc.text('OFFICIAL EXAMINATION RESULT', 105, 20, { align: 'center' });
                
                // Add header with school name
                doc.setFontSize(16);
                doc.setTextColor(26, 82, 118);
                doc.text('<?php echo isset($settings['school_name']) ? strtoupper($settings['school_name']) : 'SCHOOL NAME'; ?>', 105, 30, { align: 'center' });
                
                // Add subtitle
                doc.setFontSize(14);
                doc.text('<?php echo isset($settings['result_header']) ? $settings['result_header'] : 'EXAMINATION RESULT SHEET'; ?>', 105, 38, { align: 'center' });
                
                // Add student info in official format
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                
                // Create a box for student info
                doc.rect(20, 45, 170, 40);
                
                doc.text('STUDENT NAME:', 25, 55);
                doc.text('<?php echo isset($student['full_name']) ? strtoupper($student['full_name']) : ''; ?>', 80, 55);
                
                doc.text('ROLL NUMBER:', 25, 62);
                doc.text('<?php echo isset($student['roll_number']) ? $student['roll_number'] : ''; ?>', 80, 62);
                
                doc.text('REGISTRATION NO:', 25, 69);
                doc.text('<?php echo isset($student['registration_number']) ? $student['registration_number'] : ''; ?>', 80, 69);
                
                doc.text('CLASS:', 25, 76);
                doc.text('<?php echo isset($student['class_name']) && isset($student['section']) ? $student['class_name'] . ' ' . $student['section'] : ''; ?>', 80, 76);
                
                <?php if (isset($selected_exam) && !empty($results)): ?>
                // Add exam info
                doc.text('EXAMINATION:', 25, 83);
                doc.text('<?php echo $exams[array_search($selected_exam, array_column($exams, 'exam_id'))]['exam_name']; ?>', 80, 83);
                
                // Add results table with official styling
                doc.autoTable({
                    startY: 95,
                    head: [['SUBJECT CODE', 'SUBJECT', 'THEORY', 'PRACTICAL', 'TOTAL', 'GRADE', 'GRADE POINT']],
                    body: [
                        <?php foreach ($results as $result): ?>
                        ['<?php echo $result['subject_code']; ?>', 
                         '<?php echo strtoupper($result['subject_name']); ?>', 
                         '<?php echo $result['theory_marks']; ?>', 
                         '<?php echo $result['practical_marks']; ?>', 
                         '<?php echo $result['total_marks']; ?>', 
                         '<?php echo $result['grade']; ?>', 
                         '<?php echo number_format($result['gpa'], 2); ?>'],
                        <?php endforeach; ?>
                    ],
                    foot: [
                        ['', '', '', '', '', 'GPA', '<?php echo number_format($overall_performance['gpa'], 2); ?>']
                    ],
                    theme: 'grid',
                    styles: {
                        fontSize: 10,
                        cellPadding: 6
                    },
                    headStyles: {
                        fillColor: [26, 82, 118],
                        halign: 'center',
                        valign: 'middle',
                        fontStyle: 'bold'
                    },
                    footStyles: {
                        fillColor: [240, 240, 240],
                        fontStyle: 'bold'
                    }
                });
                
                // Add grading system
                const finalY = doc.lastAutoTable.finalY;
                doc.setFontSize(11);
                doc.setTextColor(26, 82, 118);
                doc.text('GRADING SYSTEM', 105, finalY + 15, { align: 'center' });
                
                doc.setFontSize(9);
                doc.setTextColor(0, 0, 0);
                doc.text('A+ (4.0): 90-100%', 40, finalY + 25);
                doc.text('A (3.6): 80-89%', 80, finalY + 25);
                doc.text('B+ (3.2): 70-79%', 120, finalY + 25);
                doc.text('B (2.8): 60-69%', 160, finalY + 25);
                
                doc.text('C+ (2.4): 50-59%', 40, finalY + 32);
                doc.text('C (2.0): 40-49%', 80, finalY + 32);
                doc.text('D (1.6): 30-39%', 120, finalY + 32);
                doc.text('F (0.0): Below 30%', 160, finalY + 32);
                
                // Add signature lines
                doc.line(40, finalY + 60, 80, finalY + 60);
                doc.line(120, finalY + 60, 160, finalY + 60);
                
                doc.text('Class Teacher', 60, finalY + 65, { align: 'center' });
                doc.text('Principal', 140, finalY + 65, { align: 'center' });
                
                // Add official stamp text
                doc.setFontSize(10);
                doc.text('(Official Stamp)', 140, finalY + 75, { align: 'center' });
                
                // Add footer
                doc.setFontSize(8);
                doc.setTextColor(100, 100, 100);
                doc.text('<?php echo isset($settings['result_footer']) ? $settings['result_footer'] : 'This is a computer-generated document. No signature is required.'; ?>', 105, 280, { align: 'center' });
                doc.text('Issue Date: <?php echo date('d-m-Y'); ?>', 105, 285, { align: 'center' });
                <?php endif; ?>
            }
            
            // Save the PDF
            doc.save('Student_Result_<?php echo isset($student['roll_number']) ? $student['roll_number'] : 'Report'; ?>_<?php echo date('Y-m-d'); ?>.pdf');
        }
    </script>
</body>
</html>
