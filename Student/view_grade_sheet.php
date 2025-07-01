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

// Check if exam_id is provided
if (!isset($_GET['exam_id'])) {
    header("Location: grade_sheet.php");
    exit();
}

$exam_id = $_GET['exam_id'];

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

// Verify this exam belongs to the student
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM results r
        WHERE r.student_id = ? AND r.exam_id = ?
    ");
    $stmt->bind_param("si", $student['student_id'], $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] == 0) {
        header("Location: grade_sheet.php");
        exit();
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error verifying exam: " . $e->getMessage());
    header("Location: grade_sheet.php");
    exit();
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
        ORDER BY s.subject_id
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
    $failed_subjects = 0;

    while ($row = $results_data->fetch_assoc()) {
        $theory_marks = $row['theory_marks'] ?? 0;
        $practical_marks = $row['practical_marks'] ?? 0;
        $total_subject_marks = $theory_marks + $practical_marks;
        $subject_max_marks = ($row['full_marks_theory'] ?? 100) + ($row['full_marks_practical'] ?? 0);
        $credit_hours = $row['credit_hours'] ?? 1;

        // Check if practical marks exist
        $has_practical = ($row['full_marks_practical'] > 0 && $practical_marks > 0);

        // Calculate grade point based on total marks percentage - Updated to match Admin grading system
        $total_percentage = $subject_max_marks > 0 ? ($total_subject_marks / $subject_max_marks) * 100 : 0;

        // Determine theory and practical percentages
        $theory_percentage = $row['full_marks_theory'] > 0 ? ($theory_marks / $row['full_marks_theory']) * 100 : 0;
        $practical_percentage = $row['full_marks_practical'] > 0 ? ($practical_marks / $row['full_marks_practical']) * 100 : 0;

        // Determine individual grades
        $theory_grade = 'NG';
        if ($theory_percentage >= 90) $theory_grade = 'A+';
        elseif ($theory_percentage >= 80) $theory_grade = 'A';
        elseif ($theory_percentage >= 70) $theory_grade = 'B+';
        elseif ($theory_percentage >= 60) $theory_grade = 'B';
        elseif ($theory_percentage >= 50) $theory_grade = 'C+';
        elseif ($theory_percentage >= 40) $theory_grade = 'C';
        elseif ($theory_percentage >= 33) $theory_grade = 'D';

        // Set practical grade to N/A if no practical marks
        $practical_grade = 'N/A';
        if ($has_practical) {
            $practical_grade = 'NG';
            if ($practical_percentage >= 90) $practical_grade = 'A+';
            elseif ($practical_percentage >= 80) $practical_grade = 'A';
            elseif ($practical_percentage >= 70) $practical_grade = 'B+';
            elseif ($practical_percentage >= 60) $practical_grade = 'B';
            elseif ($practical_percentage >= 50) $practical_grade = 'C+';
            elseif ($practical_percentage >= 40) $practical_grade = 'C';
            elseif ($practical_percentage >= 33) $practical_grade = 'D';
        }

        // Check for 35% failure rule
        $is_failed = ($theory_percentage < 35 || ($has_practical && $practical_percentage < 35));

        $grade_point = 0;
        $grade = 'NG';
        if ($total_percentage >= 91) {
            $grade_point = 4.0;
            $grade = 'A+';
        } elseif ($total_percentage >= 81) {
            $grade_point = 3.7;
            $grade = 'A';
        } elseif ($total_percentage >= 71) {
            $grade_point = 3.3;
            $grade = 'B+';
        } elseif ($total_percentage >= 61) {
            $grade_point = 3.0;
            $grade = 'B';
        } elseif ($total_percentage >= 51) {
            $grade_point = 2.7;
            $grade = 'C+';
        } elseif ($total_percentage >= 41) {
            $grade_point = 2.3;
            $grade = 'C';
        } elseif ($total_percentage >= 33) {
            $grade_point = 1.0;
            $grade = 'D';
        }

        if ($is_failed) {
            $grade_point = 0.0;
            $grade = 'NG';
            $failed_subjects++;
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
            'grade' => $grade,
            'grade_point' => $grade_point,
            'remarks' => $row['remarks'] ?? '',
            'full_marks_theory' => $row['full_marks_theory'] ?? 100,
            'full_marks_practical' => $row['full_marks_practical'] ?? 0,
            'theory_grade' => $theory_grade,
            'practical_grade' => $practical_grade,
            'is_failed' => $is_failed,
            'has_practical' => $has_practical
        ];

        $total_marks += $total_subject_marks;
        $total_subjects++;
        $max_marks += $subject_max_marks;
    }

    $stmt->close();

    // Calculate GPA
    $gpa = $total_credit_hours > 0 ? ($total_grade_points / $total_credit_hours) : 0;

    // Determine grade based on GPA - Updated to match Admin grading system
    $grade = 'NG';
    if ($gpa >= 4.0) $grade = 'A+';
    elseif ($gpa >= 3.7) $grade = 'A';
    elseif ($gpa >= 3.3) $grade = 'B+';
    elseif ($gpa >= 3.0) $grade = 'B';
    elseif ($gpa >= 2.7) $grade = 'C+';
    elseif ($gpa >= 2.3) $grade = 'C';
    elseif ($gpa >= 1.0) $grade = 'D';
    else $grade = 'NG';

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
    <title>View Result | <?php echo htmlspecialchars($exam_details['exam_name']); ?></title>
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
            padding: 1.5cm;
            margin: 20px auto;
            background-color: white;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
            position: relative;
            box-sizing: border-box;
            border-radius: 8px;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(0, 0, 0, 0.03);
            z-index: 0;
            pointer-events: none;
            font-weight: bold;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
            border-bottom: 3px solid #1e40af;
            padding-bottom: 15px;
        }

        .logo {
            width: 90px;
            height: 90px;
            margin: 0 auto 15px;
            background-color: #f0f7ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #1e40af;
            border: 2px solid #1e40af;
        }

        .title {
            font-weight: bold;
            font-size: 24px;
            margin-bottom: 5px;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .subtitle {
            font-size: 20px;
            margin-bottom: 8px;
            color: #2563eb;
            font-weight: 500;
        }

        .exam-title {
            font-size: 22px;
            font-weight: bold;
            margin: 15px 0;
            color: #1e40af;
            border: 2px solid #1e40af;
            display: inline-block;
            padding: 8px 20px;
            border-radius: 8px;
            background-color: #f0f7ff;
            letter-spacing: 1px;
        }

        .student-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            max-width: 800px;
            margin: 0 auto 30px;
        }

        .info-item {
            background: #f9f9f9;
            padding: 10px 15px;
            border-radius: 6px;
            box-shadow: 0 0 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 12px;
        }

        .info-label {
            font-weight: bold;
            color: #1e40af;
            display: block;
            margin-bottom: 5px;
        }

        .grade-sheet-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .grade-sheet-table th,
        .grade-sheet-table td {
            padding: 16px;
            text-align: center;
            border: none;
        }

        .grade-sheet-table th {
            background-color: #1e40af;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        .grade-sheet-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .simple-summary {
            margin: 20px 0;
            position: relative;
            z-index: 1;
            padding: 20px;
        }

        .simple-summary h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #1e40af;
            font-size: 18px;
            font-weight: bold;
        }

        .simple-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .simple-summary-item {
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
            font-weight: bold;
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

        .grade-title {
            margin-bottom: 10px;
            text-align: center;
            letter-spacing: 0.5px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            font-size: 16px;
        }

        .grade-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .grade-table th,
        .grade-table td {
            padding: 12px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .grade-table th {
            background-color: #1e40af;
            color: white;
            font-weight: bold;
        }

        .grade-table tr:nth-child(even) {
            background-color: #f0f7ff;
        }

        .footer {
            margin-top: 50px;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .signature {
            text-align: center;
        }

        .signature-line {
            width: 80%;
            margin: 30px auto 15px;
            border-top: 2px solid #333;
        }

        .signature-title {
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background-color: #1e40af;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .action-button i {
            margin-right: 10px;
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
                border-radius: 0;
            }

            .print-button,
            .back-button,
            .sidebar,
            .top-navigation,
            .action-buttons {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }

        @media (max-width: 768px) {
            .grade-sheet-container {
                width: 100%;
                padding: 1cm;
            }

            .student-info {
                grid-template-columns: 1fr;
            }

            .grade-sheet-table {
                font-size: 14px;
            }

            .grade-sheet-table th,
            .grade-sheet-table td {
                padding: 8px 5px;
            }

            .action-buttons {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .grade-sheet-table {
                font-size: 12px;
            }

            .title {
                font-size: 20px;
            }

            .subtitle {
                font-size: 16px;
            }

            .exam-title {
                font-size: 18px;
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
                            <h1 class="text-2xl font-bold text-blue-900">
                                <i class="fas fa-file-alt mr-2"></i>
                                <?php echo htmlspecialchars($exam_details['exam_name']); ?> Result
                            </h1>
                            <div class="action-buttons">
                                <a href="grade_sheet.php" class="action-button">
                                    <i class="fas fa-arrow-left"></i> Back to Grade Sheets
                                </a>
                                <button onclick="window.print()" class="action-button">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button onclick="downloadPDF()" class="action-button">
                                    <i class="fas fa-file-pdf"></i> Download PDF
                                </button>
                            </div>
                        </div>

                        <!-- Grade Sheet -->
                        <div class="grade-sheet-container" id="grade-sheet">
                            <div class="watermark">OFFICIAL</div>

                            <div class="header">
                                <div class="logo-section">
                                    <?php if (!empty($settings['school_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($settings['school_logo']); ?>" alt="School Logo">
                                    <?php else: ?>
                                        <i class="fas fa-graduation-cap text-5xl text-blue-600"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="title"><?php echo isset($settings['school_name']) ? strtoupper($settings['school_name']) : 'School Name'; ?></div>
                                <div class="subtitle"><?php echo isset($settings['result_header']) ? strtoupper($settings['result_header']) : 'Result Management System'; ?></div>
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
                                        <th>THEORY GRADE</th>
                                        <th>PRACTICAL GRADE</th>
                                        <th>FINAL GRADE</th>
                                        <th>GRADE POINT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $subject): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject['code']); ?></td>
                                            <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                            <td><?php echo htmlspecialchars($subject['credit_hour']); ?></td>
                                            <td><?php echo htmlspecialchars($subject['theory_grade']); ?></td>
                                            <td><?php echo htmlspecialchars($subject['practical_grade']); ?></td>
                                            <td class="<?php echo $subject['is_failed'] ? 'text-red-600 font-bold' : ''; ?>"><?php echo htmlspecialchars($subject['grade']); ?></td>
                                            <td><?php echo number_format($subject['grade_point'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Overall Result Summary -->
                            <?php
                            // Check if any subject has NG grade
                            $has_ng_grade = false;
                            $failed_subjects_count = 0;

                            foreach ($subjects as $subject) {
                                if ($subject['is_failed']) {
                                    $has_ng_grade = true;
                                    $failed_subjects_count++;
                                }
                            }

                            $is_pass = ($failed_subjects_count == 0 && $percentage >= 35);

                            // Determine division based on percentage
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
                            ?>

                            <div class="simple-summary">
                                <h2>Overall Result Summary</h2>

                                <!-- Summary Cards -->
                                <div class="simple-summary-grid">
                                    <div class="simple-summary-item">
                                        <div class="simple-summary-label">Total Marks</div>
                                        <div class="simple-summary-value">
                                            <?php echo number_format($total_marks, 0); ?> / <?php echo number_format($max_marks, 0); ?>
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
                                                if ($percentage >= 90) echo 'A+';
                                                elseif ($percentage >= 80) echo 'A';
                                                elseif ($percentage >= 70) echo 'B+';
                                                elseif ($percentage >= 60) echo 'B';
                                                elseif ($percentage >= 50) echo 'C+';
                                                elseif ($percentage >= 40) echo 'C';
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
                                        <div class="simple-info-value"><?php echo $division; ?></div>
                                    </div>

                                    <div class="simple-info-item">
                                        <div class="simple-info-label">Failed Subjects</div>
                                        <div class="simple-info-value <?php echo $failed_subjects_count > 0 ? 'fail' : 'pass'; ?>">
                                            <?php echo $failed_subjects_count; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

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
                            </table>

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

                            <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #777;">
                                <p><?php echo isset($settings['result_footer']) ? htmlspecialchars($settings['result_footer']) : 'This is a computer-generated document. No signature is required.'; ?></p>
                                <p>Issue Date: <?php echo date('d-m-Y', strtotime($issue_date)); ?></p>
                            </div>
                        </div>

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
            // Show loading indicator
            const loadingIndicator = document.createElement('div');
            loadingIndicator.innerHTML = `
                <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white p-5 rounded-lg shadow-lg flex flex-col items-center">
                        <i class="fas fa-spinner fa-spin text-blue-600 text-3xl mb-3"></i>
                        <p class="text-gray-700">Generating PDF...</p>
                    </div>
                </div>
            `;
            document.body.appendChild(loadingIndicator);

            // Use html2canvas to capture the element as an image
            html2canvas(document.getElementById('grade-sheet'), {
                scale: 2, // Higher scale for better quality
                useCORS: true,
                logging: false
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');

                // Initialize jsPDF
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');

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

                // Remove loading indicator
                document.body.removeChild(loadingIndicator);
            }).catch(error => {
                console.error('Error generating PDF:', error);
                alert('An error occurred while generating the PDF. Please try again.');

                // Remove loading indicator
                document.body.removeChild(loadingIndicator);
            });
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt+P to print
            if (e.altKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }

            // Alt+D to download PDF
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                downloadPDF();
            }

            // Alt+B to go back to grade sheets
            if (e.altKey && e.key === 'b') {
                e.preventDefault();
                window.location.href = 'grade_sheet.php';
            }
        });
    </script>
</body>

</html>
