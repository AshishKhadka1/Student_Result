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

// Check if we're filtering by upload
$upload_filter = false;
$upload_data = null;

if (isset($_GET['upload_id']) && !empty($_GET['upload_id'])) {
    $upload_id = intval($_GET['upload_id']);
    $upload_filter = true;

    // Get upload information
    $stmt = $conn->prepare("SELECT ru.*, e.exam_name, c.class_name, c.section 
                           FROM result_uploads ru
                           LEFT JOIN exams e ON ru.exam_id = e.exam_id
                           LEFT JOIN classes c ON ru.class_id = c.class_id
                           WHERE ru.id = ?");
    $stmt->bind_param("i", $upload_id);
    $stmt->execute();
    $upload_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$upload_data) {
        $_SESSION['error_message'] = "Upload not found.";
        header("Location: manage_results.php");
        exit();
    }
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
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = $_GET['student_id'];

    // Debug: Show what student_id we're looking for
    error_log("Looking for student_id: " . $student_id);

    // Get student details with better error handling
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
        // Debug: Let's see what students actually exist
        $debug_query = "SELECT student_id, roll_number FROM students LIMIT 5";
        $debug_result = $conn->query($debug_query);
        $existing_students = [];
        while ($row = $debug_result->fetch_assoc()) {
            $existing_students[] = $row;
        }

        echo "<div class='bg-red-50 border-l-4 border-red-500 p-4 mb-6'>
                <h3 class='text-red-800 font-medium'>Debug Information</h3>
                <p class='text-red-700'>Student not found with ID: " . htmlspecialchars($student_id) . "</p>
                <p class='text-red-700'>Available students in database:</p>
                <ul class='text-red-700 ml-4'>";

        foreach ($existing_students as $debug_student) {
            echo "<li>ID: " . htmlspecialchars($debug_student['student_id']) . " - Roll: " . htmlspecialchars($debug_student['roll_number']) . "</li>";
        }

        echo "</ul>
                <p class='text-red-700 mt-2'>
                    <a href='students_result.php' class='text-blue-600 underline'>← Go back to student list</a>
                </p>
              </div>";

        $stmt->close();
        $conn->close();
        exit();
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

    // Remove this automatic selection - only show results when explicitly selected
    // if (!$selected_exam && count($exams) > 0) {
    //     $selected_exam = $exams[0]['exam_id'];
    // }

    // Get results for all exams for this student (only published results)
    $query = "SELECT r.*, e.exam_name, e.exam_type, e.academic_year, s.subject_name
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    JOIN subjects s ON r.subject_id = s.subject_id
    WHERE r.student_id = ? 
    AND (
        (r.status = 'published') OR 
        (r.is_published = 1) OR 
        (e.results_published = 1 AND e.status = 'published')
    )
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

    // If we have a selected exam, get detailed results for it (only published)
    if ($selected_exam) {
        // First, get all results for this student and exam
        $stmt = $conn->prepare("
        SELECT r.*, s.subject_name, s.subject_code, s.credit_hours
        FROM results r
        JOIN subjects s ON r.subject_id = s.subject_id
        WHERE r.student_id = ? AND r.exam_id = ?
        ORDER BY s.subject_name
    ");
        $stmt->bind_param("si", $student_id, $selected_exam);
        $stmt->execute();
        $current_results = $stmt->get_result();

        $results = [];
        $total_marks_obtained = 0;
        $total_full_marks = 0;
        $total_subjects = 0;
        $failed_subjects = 0;
        $total_gpa_points = 0;

        while ($row = $current_results->fetch_assoc()) {
            $theory_marks = $row['theory_marks'] ?? 0;
            $practical_marks = $row['practical_marks'] ?? 0;

            // Determine if subject has practical based on whether practical_marks is not null and > 0
            $has_practical = !is_null($row['practical_marks']) && $row['practical_marks'] > 0;

            // Determine full marks based on whether practical exists
            $theory_full_marks = $has_practical ? 75 : 100;
            $practical_full_marks = $has_practical ? 25 : 0;
            $subject_full_marks = 100; // Total is always 100

            $subject_total_obtained = $theory_marks + $practical_marks;

            $total_marks_obtained += $subject_total_obtained;
            $total_full_marks += $subject_full_marks;
            $total_subjects++;

            // Calculate individual component percentages
            $theory_percentage = ($theory_marks / $theory_full_marks) * 100;
            $practical_percentage = $has_practical ? ($practical_marks / $practical_full_marks) * 100 : 0;

            // Calculate theory and practical grades
            $theory_grade = getGradeFromPercentage($theory_percentage);
            $practical_grade = $has_practical ? getGradeFromPercentage($practical_percentage) : 'N/A';

            // Check for failure condition (either theory or practical below 35%)
            $is_failed = ($theory_percentage < 35) || ($has_practical && $practical_percentage < 35);

            // Calculate final grade and GPA
            if ($is_failed) {
                $final_grade = 'NG';
                $grade_point = 0.0;
                $failed_subjects++;
            } else {
                $theory_grade_point = getGradePointFromPercentage($theory_percentage);
                $practical_grade_point = $has_practical ? getGradePointFromPercentage($practical_percentage) : 0;

                if ($has_practical) {
                    $grade_point = (($theory_grade_point * $theory_full_marks) + ($practical_grade_point * $practical_full_marks)) / $subject_full_marks;
                } else {
                    $grade_point = $theory_grade_point;
                }

                // Determine final grade based on total percentage
                $total_percentage = ($subject_total_obtained / $subject_full_marks) * 100;
                $final_grade = getGradeFromPercentage($total_percentage);
            }

            $total_gpa_points += $grade_point;

            $results[] = [
                'subject_id' => $row['subject_id'],
                'subject_name' => $row['subject_name'],
                'subject_code' => $row['subject_code'] ?? $row['subject_id'],
                'credit_hours' => $row['credit_hours'] ?? 4,
                'theory_marks' => $theory_marks,
                'practical_marks' => $practical_marks,
                'total_marks' => $subject_total_obtained,
                'max_marks' => $subject_full_marks,
                'theory_full_marks' => $theory_full_marks,
                'practical_full_marks' => $practical_full_marks,
                'has_practical' => $has_practical,
                'theory_grade' => $theory_grade,
                'practical_grade' => $practical_grade,
                'final_grade' => $final_grade,
                'grade_point' => $grade_point,
                'grade' => $row['grade'],
                'gpa' => $row['gpa'],
                'remarks' => $row['remarks'] ?? '',
                'theory_percentage' => $theory_percentage,
                'practical_percentage' => $practical_percentage,
                'subject_percentage' => ($subject_total_obtained / $subject_full_marks) * 100
            ];
        }
        $stmt->close();

        // Calculate overall performance metrics
        $overall_percentage = $total_full_marks > 0 ? ($total_marks_obtained / $total_full_marks) * 100 : 0;
        $overall_gpa = $total_subjects > 0 ? ($total_gpa_points / $total_subjects) : 0;
        $is_pass = ($failed_subjects == 0);

        // Determine overall grade
        if ($failed_subjects > 0) {
            $overall_grade = 'NG';
        } else {
            $overall_grade = getGradeFromPercentage($overall_percentage);
        }

        // Determine division
        $division = '';
        if ($failed_subjects > 0) {
            $division = 'Fail';
        } elseif ($overall_percentage >= 80) {
            $division = 'Distinction';
        } elseif ($overall_percentage >= 60) {
            $division = 'First Division';
        } elseif ($overall_percentage >= 45) {
            $division = 'Second Division';
        } elseif ($overall_percentage >= 35) {
            $division = 'Third Division';
        } else {
            $division = 'Fail';
        }

        // Create overall performance array
        $overall_performance = [
            'total_marks' => $total_full_marks,
            'marks_obtained' => $total_marks_obtained,
            'average_marks' => $overall_percentage,
            'gpa' => $overall_gpa,
            'grade' => $overall_grade,
            'division' => $division,
            'is_pass' => $is_pass,
            'failed_subjects' => $failed_subjects,
            'total_subjects' => $total_subjects,
            'rank' => 'N/A'
        ];
    }
} else {
    // If no specific student is requested, show a list of students to select
    $show_student_list = true;

    // Get students with published results only
    $students = [];
    $students_query = "
    SELECT DISTINCT s.student_id, s.roll_number, u.full_name, c.class_name, c.section,
           COUNT(r.result_id) as result_count
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN classes c ON s.class_id = c.class_id
    JOIN results r ON s.student_id = r.student_id
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE (r.status = 'published' OR r.is_published = 1 OR e.results_published = 1)
    GROUP BY s.student_id, s.roll_number, u.full_name, c.class_name, c.section
    HAVING result_count > 0
    ORDER BY c.class_name, c.section, s.roll_number
";
    $students_result = $conn->query($students_query);

    if (!$students_result) {
        echo "<div class='bg-red-50 border-l-4 border-red-500 p-4 mb-6'>
                <p class='text-red-700'>Database Error: " . $conn->error . "</p>
              </div>";
    } else {
        while ($row = $students_result->fetch_assoc()) {
            $students[] = $row;
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

// Function to get grade from percentage
function getGradeFromPercentage($percentage)
{
    if ($percentage >= 90) return 'A+';
    elseif ($percentage >= 80) return 'A';
    elseif ($percentage >= 70) return 'B+';
    elseif ($percentage >= 60) return 'B';
    elseif ($percentage >= 50) return 'C+';
    elseif ($percentage >= 40) return 'C';
    elseif ($percentage >= 35) return 'D';
    else return 'NG';
}

// Function to get grade point from percentage
function getGradePointFromPercentage($percentage)
{
    if ($percentage >= 90) return 4.0;
    elseif ($percentage >= 80) return 3.6;
    elseif ($percentage >= 70) return 3.2;
    elseif ($percentage >= 60) return 2.8;
    elseif ($percentage >= 50) return 2.4;
    elseif ($percentage >= 40) return 2.0;
    elseif ($percentage >= 35) return 1.6;
    else return 0.0;
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
        case 'A+':
            return 'Outstanding';
        case 'A':
            return 'Excellent';
        case 'B+':
            return 'Very Good';
        case 'B':
            return 'Good';
        case 'C+':
            return 'Satisfactory';
        case 'C':
            return 'Acceptable';
        case 'D':
            return 'Needs Improvement';
        case 'F':
            return 'Fail';
        default:
            return '';
    }
}

// Add a heading to show we're filtering by upload
if ($upload_filter) {
    echo '<div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-500"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        Showing results for upload: <strong>' . htmlspecialchars($upload_data['description'] ?: $upload_data['file_name']) . '</strong>
                        <br>Exam: ' . htmlspecialchars($upload_data['exam_name']) . '
                        <br>Class: ' . htmlspecialchars($upload_data['class_name'] . ' ' . $upload_data['section']) . '
                        <br>Status: <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-' .
        ($upload_data['status'] == 'Published' ? 'green' : 'yellow') . '-100 text-' .
        ($upload_data['status'] == 'Published' ? 'green' : 'yellow') . '-800">' .
        htmlspecialchars($upload_data['status']) . '</span>
                    </p>
                </div>
            </div>
        </div>';
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

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include 'topBar.php'; ?>

            <?php include 'mobile_sidebar.php'; ?>

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
                                            <?php foreach ($students as $student_item): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student_item['roll_number']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student_item['full_name']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student_item['class_name'] . ' ' . $student_item['section']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <a href="?student_id=<?php echo $student_item['student_id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
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

                            <!-- Student Information -->
                            <div class="bg-white border border-gray-200 rounded-md p-4 mb-6">
                                <div class="flex flex-col md:flex-row justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-800"><?php echo $student['full_name']; ?></h2>
                                        <p class="text-sm text-gray-600">Roll No: <?php echo $student['roll_number']; ?></p>
                                        <p class="text-sm text-gray-600">Reg. No: <?php echo $student['registration_number']; ?></p>
                                    </div>
                                    <div class="mt-4 md:mt-0 text-left md:text-right">
                                        <p class="text-sm text-gray-600">Class: <?php echo $student['class_name'] . ' ' . $student['section']; ?></p>
                                        <p class="text-sm text-gray-600">Academic Year: <?php echo $student['academic_year']; ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Exam Selection -->
                            <div class="bg-white border border-gray-200 rounded-md p-4 mb-6 no-print">
                                <h3 class="text-base font-medium text-gray-800 mb-3">Select Exam</h3>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($exams as $exam): ?>
                                        <?php if (isset($results_by_exam[$exam['exam_id']])): ?>
                                            <a href="?student_id=<?php echo $student_id ?? ''; ?>&exam_id=<?php echo $exam['exam_id']; ?>"
                                                class="px-3 py-1.5 rounded text-sm font-medium border
                   <?php echo (isset($selected_exam) && $selected_exam == $exam['exam_id'])
                                                ? 'bg-blue-500 text-white'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                                <?php echo $exam['exam_name']; ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Prompt if no exam selected -->
                            <?php if (!isset($selected_exam) || empty($selected_exam)): ?>
                                <div class="border-l-4 border-blue-400 bg-blue-50 p-4 mb-6">
                                    <div class="flex items-start space-x-2">
                                        <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                                        <p class="text-sm text-blue-700">Please select an exam to view the student's results.</p>
                                    </div>
                                </div>
                            <?php endif; ?>


                            <!-- Tab Navigation -->
                            <div class="bg-white shadow rounded-lg mb-6 overflow-hidden">
                                <div class="border-b border-gray-200 no-print">
                                    <nav class="flex -mb-px">
                                        <button onclick="openTab('result')" class="tab-button w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm border-blue-500 text-blue-600">
                                            <i class="fas fa-clipboard-list mr-2"></i> Result Sheet
                                        </button>
                                        <button onclick="openTab('progress')" class="tab-button w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                            <i class="fas fa-chart-line mr-2"></i> Progress Tracking
                                        </button>
                                    </nav>
                                </div>

                                <!-- Result Tab Content -->
                                <div id="result" class="tab-content active p-6">
                                    <?php if (isset($selected_exam) && !empty($selected_exam) && !empty($results)): ?>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                                            <?php
                                            $exam_key = array_search($selected_exam, array_column($exams, 'exam_id'));
                                            echo $exam_key !== false ? $exams[$exam_key]['exam_name'] : 'Selected Exam';
                                            ?> Results
                                        </h3>

                                        <!-- Subject-wise Results Table -->
                                        <div class="overflow-x-auto mb-6">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            <div class="flex flex-col">
                                                                <span>Theory</span>
                                                                <span class="text-xs font-normal text-gray-400">Marks/% /Grade</span>
                                                            </div>
                                                        </th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            <div class="flex flex-col">
                                                                <span>Practical</span>
                                                                <span class="text-xs font-normal text-gray-400">Marks/% /Grade</span>
                                                            </div>
                                                        </th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($results as $result): ?>
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($result['subject_name']); ?></div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($result['subject_code']); ?></div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="flex flex-col">
                                                                    <span><?php echo htmlspecialchars($result['theory_marks']); ?> / <?php echo $result['theory_full_marks']; ?></span>
                                                                    <span class="text-xs text-gray-400"><?php echo number_format($result['theory_percentage'], 1); ?>%</span>
                                                                    <span class="px-1 inline-flex text-xs leading-4 font-semibold rounded <?php
                                                                                                                                            $theory_grade = $result['theory_grade'];
                                                                                                                                            if ($theory_grade == 'A+' || $theory_grade == 'A') echo 'bg-green-100 text-green-800';
                                                                                                                                            elseif ($theory_grade == 'B+' || $theory_grade == 'B') echo 'bg-blue-100 text-blue-800';
                                                                                                                                            elseif ($theory_grade == 'C+' || $theory_grade == 'C') echo 'bg-yellow-100 text-yellow-800';
                                                                                                                                            elseif ($theory_grade == 'D') echo 'bg-orange-100 text-orange-800';
                                                                                                                                            else echo 'bg-red-100 text-red-800';
                                                                                                                                            ?>">
                                                                        <?php echo $theory_grade; ?>
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <?php if ($result['has_practical']): ?>
                                                                    <div class="flex flex-col">
                                                                        <span><?php echo htmlspecialchars($result['practical_marks'] ?? 0); ?> / <?php echo $result['practical_full_marks']; ?></span>
                                                                        <span class="text-xs text-gray-400"><?php echo number_format($result['practical_percentage'], 1); ?>%</span>
                                                                        <span class="px-1 inline-flex text-xs leading-4 font-semibold rounded <?php
                                                                                                                                                $practical_grade = $result['practical_grade'];
                                                                                                                                                if ($practical_grade == 'A+' || $practical_grade == 'A') echo 'bg-green-100 text-green-800';
                                                                                                                                                elseif ($practical_grade == 'B+' || $practical_grade == 'B') echo 'bg-blue-100 text-blue-800';
                                                                                                                                                elseif ($practical_grade == 'C+' || $practical_grade == 'C') echo 'bg-yellow-100 text-yellow-800';
                                                                                                                                                elseif ($practical_grade == 'D') echo 'bg-orange-100 text-orange-800';
                                                                                                                                                else echo 'bg-red-100 text-red-800';
                                                                                                                                                ?>">
                                                                            <?php echo $practical_grade; ?>
                                                                        </span>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="text-gray-400">N/A</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm font-semibold text-gray-900">
                                                                    <?php echo htmlspecialchars(number_format($result['total_marks'], 2)) . ' / ' . $result['max_marks']; ?>
                                                                </div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm font-semibold text-gray-900">
                                                                    <?php echo number_format($result['subject_percentage'], 2); ?>%
                                                                </div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="flex flex-col">
                                                                    <?php
                                                                    $grade_class = '';
                                                                    switch ($result['final_grade']) {
                                                                        case 'A+':
                                                                            $grade_class = 'bg-green-100 text-green-800';
                                                                            break;
                                                                        case 'A':
                                                                            $grade_class = 'bg-green-100 text-green-800';
                                                                            break;
                                                                        case 'B+':
                                                                            $grade_class = 'bg-green-100 text-green-800';
                                                                            break;
                                                                        case 'B':
                                                                            $grade_class = 'bg-green-100 text-green-800';
                                                                            break;
                                                                        case 'C+':
                                                                            $grade_class = 'bg-yellow-100 text-yellow-800';
                                                                            break;
                                                                        case 'C':
                                                                            $grade_class = 'bg-yellow-100 text-yellow-800';
                                                                            break;
                                                                        case 'D':
                                                                            $grade_class = 'bg-orange-100 text-orange-800';
                                                                            break;
                                                                        case 'NG':
                                                                            $grade_class = 'bg-red-100 text-red-800';
                                                                            break;
                                                                        default:
                                                                            $grade_class = 'bg-gray-100 text-gray-800';
                                                                    }
                                                                    ?>
                                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $grade_class; ?>">
                                                                        <?php echo htmlspecialchars($result['final_grade']); ?>
                                                                    </span>
                                                                    <span class="text-xs text-gray-400 mt-1">GPA: <?php echo number_format($result['grade_point'], 2); ?></span>
                                                                </div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <?php
                                                                $is_pass = ($result['final_grade'] != 'NG');
                                                                ?>
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $is_pass ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                                    <?php echo $is_pass ? 'Pass' : 'Fail'; ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Overall Result Summary -->
                                        <div class="bg-white p-6 rounded-md shadow-sm border border-gray-200">
                                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Overall Result Summary</h2>

                                            <!-- Summary Cards -->
                                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                                <div class="p-4 border rounded text-center">
                                                    <p class="text-sm text-gray-500">Total Marks</p>
                                                    <p class="text-xl font-bold text-gray-800">
                                                        <?php echo number_format($overall_performance['marks_obtained'], 0); ?> /
                                                        <?php echo number_format($overall_performance['total_marks'], 0); ?>
                                                    </p>
                                                </div>
                                                <div class="p-4 border rounded text-center">
                                                    <p class="text-sm text-gray-500">Percentage</p>
                                                    <p class="text-xl font-bold text-gray-800">
                                                        <?php echo number_format($overall_performance['average_marks'], 2); ?>%
                                                    </p>
                                                </div>
                                                <div class="p-4 border rounded text-center">
                                                    <p class="text-sm text-gray-500">GPA</p>
                                                    <p class="text-xl font-bold text-gray-800">
                                                        <?php echo number_format($overall_performance['gpa'], 2); ?> / 4.0
                                                    </p>
                                                </div>
                                                <div class="p-4 border rounded text-center">
                                                    <p class="text-sm text-gray-500">Grade</p>
                                                    <?php
                                                    $grade_class = match ($overall_performance['grade']) {
                                                        'A+', 'A' => 'text-green-700',
                                                        'B+', 'B' => 'text-blue-700',
                                                        'C+', 'C' => 'text-yellow-700',
                                                        'D'        => 'text-orange-700',
                                                        'NG'       => 'text-red-700',
                                                        default    => 'text-gray-700'
                                                    };
                                                    ?>
                                                    <p class="text-xl font-bold <?php echo $grade_class; ?>">
                                                        <?php echo htmlspecialchars($overall_performance['grade']); ?>
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Additional Information -->
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                                                <div class="p-4 border rounded">
                                                    <p class="text-sm text-gray-500">Result Status</p>
                                                    <p class="text-lg font-bold <?php echo $overall_performance['is_pass'] ? 'text-green-600' : 'text-red-600'; ?>">
                                                        <?php echo $overall_performance['is_pass'] ? 'PASS' : 'FAIL'; ?>
                                                    </p>
                                                </div>
                                                <div class="p-4 border rounded">
                                                    <p class="text-sm text-gray-500">Division</p>
                                                    <p class="text-lg font-bold text-gray-800">
                                                        <?php echo htmlspecialchars($overall_performance['division']); ?>
                                                    </p>
                                                </div>
                                                <div class="p-4 border rounded">
                                                    <p class="text-sm text-gray-500">Failed Subjects</p>
                                                    <p class="text-lg font-bold <?php echo $overall_performance['failed_subjects'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                                        <?php echo $overall_performance['failed_subjects']; ?> /
                                                        <?php echo $overall_performance['total_subjects']; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                    <?php else: ?>

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
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const closeSidebar = document.getElementById('close-sidebar');
        const sidebarBackdrop = document.getElementById('sidebar-backdrop');
        const mobileSidebar = document.getElementById('mobile-sidebar');

        if (sidebarToggle && mobileSidebar) {
            sidebarToggle.addEventListener('click', function() {
                mobileSidebar.classList.remove('-translate-x-full');
            });
        }

        if (closeSidebar && mobileSidebar) {
            closeSidebar.addEventListener('click', function() {
                mobileSidebar.classList.add('-translate-x-full');
            });
        }

        if (sidebarBackdrop && mobileSidebar) {
            sidebarBackdrop.addEventListener('click', function() {
                mobileSidebar.classList.add('-translate-x-full');
            });
        }

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
                        'rgba(239, 68, 68, 1)', // red
                        'rgba(139, 92, 246, 1)', // purple
                        'rgba(236, 72, 153, 1)' // pink
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



            // Add student info
            doc.setFontSize(12);
            doc.setTextColor(0, 0, 0);
            doc.text('Student Name: <?php echo isset($student['full_name']) ? $student['full_name'] : ''; ?>', 20, 35);
            doc.text('Roll Number: <?php echo isset($student['roll_number']) ? $student['roll_number'] : ''; ?>', 20, 42);
            doc.text('Class: <?php echo isset($student['class_name']) && isset($student['section']) ? $student['class_name'] . ' ' . $student['section'] : ''; ?>', 20, 49);

            <?php if (isset($selected_exam) && !empty($results)): ?>
                // Add exam info
                <?php
                $exam_key = array_search($selected_exam, array_column($exams, 'exam_id'));
                $exam_name = $exam_key !== false ? $exams[$exam_key]['exam_name'] : 'Selected Exam';
                ?>
                doc.text('Exam: <?php echo $exam_name; ?>', 20, 56);
                doc.text('Academic Year: <?php echo $student['academic_year']; ?>', 20, 63);

                // Add results table
                doc.autoTable({
                    startY: 70,
                    head: [
                        ['Subject Code', 'Subject', 'Credit Hr', 'Theory Grade', 'Practical Grade', 'Final Grade', 'Grade Point']
                    ],
                    body: [
                        <?php foreach ($results as $result): ?>['<?php echo $result['subject_code']; ?>',
                                '<?php echo $result['subject_name']; ?>',
                                '<?php echo $result['credit_hours']; ?>',
                                '<?php echo $result['theory_grade']; ?>',
                                '<?php echo $result['practical_grade']; ?>',
                                '<?php echo $result['final_grade']; ?>',
                                '<?php echo number_format($result['grade_point'], 1); ?>'],
                        <?php endforeach; ?>
                    ],
                    theme: 'grid',
                    styles: {
                        fontSize: 9
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
                doc.text('This document is computer-generated and does not require a signature.', 105, 280, {
                    align: 'center'
                });
                doc.text('Generated on: <?php echo date('d-m-Y'); ?>', 105, 285, {
                    align: 'center'
                });
            <?php endif; ?>
        }

        // Save the PDF
        doc.save('Student_Result_<?php echo isset($student['roll_number']) ? $student['roll_number'] : 'Report'; ?>_<?php echo date('Y-m-d'); ?>.pdf');
    </script>
</body>

</html>