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
    $stmt->bind_param("is", $exam_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        die("Student or exam not found");
    }

    $student = $result->fetch_assoc();
    $stmt->close();

    // Get results for this student and exam
    $stmt = $conn->prepare("
        SELECT r.*, s.subject_name, s.subject_code, s.full_marks_theory, s.full_marks_practical
        FROM results r
        JOIN subjects s ON r.subject_id = s.subject_id
        WHERE r.student_id = ? AND r.exam_id = ?
    ");
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

    // Get student performance data if available
    $stmt = $conn->prepare("
        SELECT * FROM student_performance 
        WHERE student_id = ? AND exam_id = ?
    ");
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

    // Get available exams
    $exams = [];
    $result = $conn->query("SELECT exam_id, exam_name, exam_type, academic_year FROM exams WHERE is_active = 1 ORDER BY created_at DESC");
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
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
        $stmt->bind_param("i", $selected_exam_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
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
                            <h1 class="text-2xl font-bold text-gray-900 my-auto">Grade Sheet</h1><br>
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Select Exam and Student</h2>
                                <form action="" method="GET" class="space-y-4">
                                    <div>
                                        <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-1">Select Exam:</label>
                                        <select name="exam_id" id="exam_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" onchange="this.form.submit()">
                                            <option value="">-- Select Exam --</option>
                                            <?php foreach ($exams as $exam): ?>
                                                <option value="<?php echo $exam['exam_id']; ?>" <?php echo (isset($_GET['exam_id']) && $_GET['exam_id'] == $exam['exam_id']) ? 'selected' : ''; ?>>
                                                    <?php echo $exam['exam_name'] . ' (' . $exam['academic_year'] . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>

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
    </script>
</body>
</html>
