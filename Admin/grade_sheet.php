<?php
// Start session for potential authentication check
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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
$success_message = '';
$error_message = '';
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == 'true';

// Get subjects for dropdown
$all_subjects = [];
$result = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
while ($row = $result->fetch_assoc()) {
    $all_subjects[] = $row;
}

// Process form submission for editing marks
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_marks'])) {
    $student_id = $_POST['student_id'] ?? '';
    $exam_id = $_POST['exam_id'] ?? '';
    $subject_ids = $_POST['subject_id'] ?? [];
    $theory_marks = $_POST['theory_marks'] ?? [];
    $practical_marks = $_POST['practical_marks'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    
    // Basic validation
    if (empty($student_id) || empty($exam_id) || empty($subject_ids)) {
        $error_message = "Please fill all required fields.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Process each subject
            for ($i = 0; $i < count($subject_ids); $i++) {
                if (empty($subject_ids[$i])) continue;
                
                $subject_id = $subject_ids[$i];
                $theory = isset($theory_marks[$i]) && is_numeric($theory_marks[$i]) ? $theory_marks[$i] : 0;
                $practical = isset($practical_marks[$i]) && is_numeric($practical_marks[$i]) ? $practical_marks[$i] : 0;
                $remark = $remarks[$i] ?? '';
                
                // Calculate total and grade
                $total_marks = $theory + $practical;
                
                // Determine grade based on percentage
                $percentage = ($total_marks / 100) * 100; // Assuming total possible marks is 100
                $grade = '';
                
                if ($percentage >= 90) {
                    $grade = 'A+';
                } elseif ($percentage >= 80) {
                    $grade = 'A';
                } elseif ($percentage >= 70) {
                    $grade = 'B+';
                } elseif ($percentage >= 60) {
                    $grade = 'B';
                } elseif ($percentage >= 50) {
                    $grade = 'C+';
                } elseif ($percentage >= 40) {
                    $grade = 'C';
                } elseif ($percentage >= 33) {
                    $grade = 'D';
                } else {
                    $grade = 'F';
                }
                
                // Check if result already exists
                $stmt = $conn->prepare("SELECT result_id FROM results WHERE student_id = ? AND exam_id = ? AND subject_id = ?");
                $stmt->bind_param("sis", $student_id, $exam_id, $subject_id);
                $stmt->execute();
                $existing_result = $stmt->get_result();
                $stmt->close();
                
                if ($existing_result->num_rows > 0) {
                    // Update existing result
                    $result_row = $existing_result->fetch_assoc();
                    $stmt = $conn->prepare("UPDATE results SET theory_marks = ?, practical_marks = ?, grade = ?, remarks = ?, updated_at = NOW() WHERE result_id = ?");
                    $stmt->bind_param("ddssi", $theory, $practical, $grade, $remark, $result_row['result_id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Insert new result
                    $stmt = $conn->prepare("INSERT INTO results (student_id, exam_id, subject_id, theory_marks, practical_marks, grade, remarks, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    $stmt->bind_param("sisdds", $student_id, $exam_id, $subject_id, $theory, $practical, $grade, $remark);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Marks saved successfully!";
            
            // Redirect to view mode
            header("Location: grade_sheet.php?student_id=$student_id&exam_id=$exam_id");
            exit();
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = "Error saving marks: " . $e->getMessage();
        }
    }
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
        JOIN exams e ON e.class_id = c.class_id
        WHERE s.student_id = ? AND e.exam_id = ?
    ");
    $stmt->bind_param("si", $student_id, $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        die("Student or exam not found");
    }
    
    $student = $result->fetch_assoc();
    $stmt->close();
    
    // Get results for this student and exam
    $stmt = $conn->prepare("
        SELECT r.*, s.subject_name, s.subject_id as subject_code
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
    
    while ($row = $results_data->fetch_assoc()) {
        $subjects[] = [
            'code' => $row['subject_code'],
            'name' => $row['subject_name'],
            'credit_hour' => 5, // Default value, adjust as needed
            'theory_marks' => $row['theory_marks'],
            'practical_marks' => $row['practical_marks'],
            'total_marks' => $row['theory_marks'] + $row['practical_marks'],
            'grade' => $row['grade'],
            'remarks' => $row['remarks'] ?? '',
            'subject_id' => $row['subject_id']
        ];
        
        $total_marks += ($row['theory_marks'] + $row['practical_marks']);
        $total_subjects++;
        $max_marks += 100; // Assuming each subject is out of 100
    }
    
    $stmt->close();
    
    // Calculate percentage
    $percentage = $total_subjects > 0 ? ($total_marks / $max_marks) * 100 : 0;
    
    // Calculate GPA (using grading system from database)
    $gpa = calculateGPA($percentage, $conn);
    
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
    $result = $conn->query("SELECT exam_id, exam_name, exam_type, academic_year FROM exams ORDER BY created_at DESC");
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
function calculateGPA($percentage, $conn) {
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
    <title>Examination Grade Sheet</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
        }
        .container {
            width: 21cm;
            min-height: 29.7cm;
            padding: 1cm;
            margin: 0 auto;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        table, th, td {
            border: 1px solid #bdc3c7;
        }
        th, td {
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #1a5276;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
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
        .grade-table th, .grade-table td {
            padding: 3px;
            text-align: center;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #1a5276;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            z-index: 100;
        }
        .print-button:hover {
            background-color: #154360;
        }
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            z-index: 100;
            text-decoration: none;
        }
        .back-button:hover {
            background-color: #2980b9;
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
        .selection-form {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2874a6;
        }
        .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .form-button {
            padding: 10px 20px;
            background-color: #1a5276;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .form-button:hover {
            background-color: #154360;
        }
        .student-table {
            width: 100%;
            margin-top: 20px;
        }
        .student-table th {
            background-color: #1a5276;
            color: white;
            padding: 10px;
        }
        .student-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .student-table tr:hover {
            background-color: #f5f5f5;
        }
        .view-link {
            padding: 5px 10px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 14px;
        }
        .view-link:hover {
            background-color: #2980b9;
        }
        @media print {
            body {
                background-color: white;
            }
            .container {
                width: 100%;
                min-height: auto;
                padding: 0.5cm;
                margin: 0;
                box-shadow: none;
            }
            .print-button, .back-button {
                display: none;
            }
        }
        /* Dashboard Layout Styles */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        .main-content {
            display: flex;
            flex-direction: column;
            flex: 1;
            overflow-x: hidden;
        }
        .content-area {
            padding: 1rem;
            flex: 1;
            overflow-y: auto;
        }
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Navigation -->
            <?php include 'topBar.php'; ?>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Success/Error Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700"><?php echo $success_message; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo $error_message; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Main Content -->
                <?php if (isset($show_student_list)): ?>
                    <div class="selection-form">
                        <h1 class="text-2xl font-bold mb-6 text-center text-gray-800">Grade Sheet Generator</h1>
                        
                        <form action="" method="GET">
                            <div class="form-group">
                                <label for="exam_id" class="form-label">Select Exam:</label>
                                <select name="exam_id" id="exam_id" class="form-select" onchange="this.form.submit()">
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
                            <h2 class="text-xl font-semibold mb-4 mt-8 text-gray-700">Select Student:</h2>
                            <table class="student-table">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Name</th>
                                        <th>Class</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo $student['roll_number']; ?></td>
                                            <td><?php echo $student['full_name']; ?></td>
                                            <td><?php echo $student['class_name'] . ' ' . $student['section']; ?></td>
                                            <td>
                                                <a href="?student_id=<?php echo $student['student_id']; ?>&exam_id=<?php echo $_GET['exam_id']; ?>" class="view-link">
                                                    View Grade Sheet
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif (isset($_GET['exam_id'])): ?>
                            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mt-6">
                                <p>No students found with results for this exam.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if ($edit_mode): ?>
                        <!-- Edit Mode -->
                        <div class="flex justify-between mb-4">
                            <a href="grade_sheet.php?student_id=<?php echo $student_id; ?>&exam_id=<?php echo $exam_id; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i class="fas fa-arrow-left mr-2"></i> Cancel Editing
                            </a>
                        </div>
                        
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Edit Student Marks</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Student: <?php echo $student['full_name']; ?> | 
                                    Class: <?php echo $student['class_name'] . ' ' . $student['section']; ?> | 
                                    Exam: <?php echo $student['exam_name']; ?>
                                </p>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <form action="grade_sheet.php" method="POST" id="marksForm">
                                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                    <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                                    
                                    <!-- Subject Marks Section -->
                                    <div class="mt-6">
                                        <h4 class="text-md font-medium text-gray-700 mb-3">Subject Marks</h4>
                                        
                                        <div id="subjectsContainer">
                                            <?php if (empty($subjects)): ?>
                                                <!-- No subjects yet, show empty form -->
                                                <div class="subject-row grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-12 mb-4 pb-4 border-b border-gray-200">
                                                    <div class="sm:col-span-4">
                                                        <label class="block text-sm font-medium text-gray-700">Subject</label>
                                                        <select name="subject_id[]" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                            <option value="">-- Select Subject --</option>
                                                            <?php foreach ($all_subjects as $subject): ?>
                                                                <option value="<?php echo $subject['subject_id']; ?>">
                                                                    <?php echo $subject['subject_name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700">Theory Marks</label>
                                                        <input type="number" name="theory_marks[]" min="0" max="100" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700">Practical Marks</label>
                                                        <input type="number" name="practical_marks[]" min="0" max="100" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                    </div>
                                                    <div class="sm:col-span-3">
                                                        <label class="block text-sm font-medium text-gray-700">Remarks</label>
                                                        <input type="text" name="remarks[]" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                    </div>
                                                    <div class="sm:col-span-1 flex items-end">
                                                        <button type="button" class="remove-subject mt-1 text-red-600 hover:text-red-800">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <!-- Show existing subjects -->
                                                <?php foreach ($subjects as $index => $subject): ?>
                                                    <div class="subject-row grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-12 mb-4 pb-4 border-b border-gray-200">
                                                        <div class="sm:col-span-4">
                                                            <label class="block text-sm font-medium text-gray-700">Subject</label>
                                                            <select name="subject_id[]" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                                <option value="">-- Select Subject --</option>
                                                                <?php foreach ($all_subjects as $sub): ?>
                                                                    <option value="<?php echo $sub['subject_id']; ?>" <?php echo ($sub['subject_id'] == $subject['subject_id']) ? 'selected' : ''; ?>>
                                                                        <?php echo $sub['subject_name']; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <label class="block text-sm font-medium text-gray-700">Theory Marks</label>
                                                            <input type="number" name="theory_marks[]" min="0" max="100" value="<?php echo $subject['theory_marks']; ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <label class="block text-sm font-medium text-gray-700">Practical Marks</label>
                                                            <input type="number" name="practical_marks[]" min="0" max="100" value="<?php echo $subject['practical_marks']; ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                        <div class="sm:col-span-3">
                                                            <label class="block text-sm font-medium text-gray-700">Remarks</label>
                                                            <input type="text" name="remarks[]" value="<?php echo $subject['remarks']; ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                        <div class="sm:col-span-1 flex items-end">
                                                            <button type="button" class="remove-subject mt-1 text-red-600 hover:text-red-800">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <button type="button" id="addSubject" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-plus mr-2"></i> Add Subject
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-6 flex justify-end space-x-3">
                                        <a href="grade_sheet.php?student_id=<?php echo $student_id; ?>&exam_id=<?php echo $exam_id; ?>" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Cancel
                                        </a>
                                        <button type="submit" name="save_marks" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-save mr-2"></i> Save Marks
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- View Mode -->
                        <div class="flex justify-between mb-4">
                            <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i class="fas fa-arrow-left mr-2"></i> Back to List
                            </a>
                            <div class="flex space-x-2">
                                <a href="grade_sheet.php?student_id=<?php echo $student_id; ?>&exam_id=<?php echo $exam_id; ?>&edit=true" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <i class="fas fa-edit mr-2"></i> Edit Marks
                                </a>
                                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-print mr-2"></i> Print
                                </button>
                            </div>
                        </div>
                        
                        <div class="container">
                            <div class="watermark">OFFICIAL</div>
                            
                            <div class="header">
                                <div class="logo">LOGO</div>
                                <div class="title"><?php echo isset($settings['school_name']) ? strtoupper($settings['school_name']) : 'GOVERNMENT OF NEPAL'; ?></div>
                                <div class="title">NATIONAL EXAMINATION BOARD</div>
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

                            <table>
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
                                        <th>D+</th>
                                        <th>D</th>
                                        <th>E</th>
                                    </tr>
                                    <tr>
                                        <th>Percentage</th>
                                        <td>90-100</td>
                                        <td>80-89</td>
                                        <td>70-79</td>
                                        <td>60-69</td>
                                        <td>50-59</td>
                                        <td>40-49</td>
                                        <td>30-39</td>
                                        <td>20-29</td>
                                        <td>0-19</td>
                                    </tr>
                                    <tr>
                                        <th>Grade Point</th>
                                        <td>4.0</td>
                                        <td>3.6</td>
                                        <td>3.2</td>
                                        <td>2.8</td>
                                        <td>2.4</td>
                                        <td>2.0</td>
                                        <td>1.6</td>
                                        <td>1.2</td>
                                        <td>0.8</td>
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
                                <p>This is a computer-generated document. No signature is required.</p>
                                <p>Issue Date: <?php echo date('d-m-Y', strtotime($issue_date)); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Add Subject Row
        document.getElementById('addSubject')?.addEventListener('click', function() {
            const container = document.getElementById('subjectsContainer');
            const subjectRow = document.querySelector('.subject-row').cloneNode(true);
            
            // Clear input values
            subjectRow.querySelectorAll('input').forEach(input => {
                input.value = '';
            });
            
            // Reset select
            subjectRow.querySelector('select').selectedIndex = 0;
            
            // Add event listener to remove button
            subjectRow.querySelector('.remove-subject').addEventListener('click', function() {
                if (container.children.length > 1) {
                    this.closest('.subject-row').remove();
                }
            });
            
            container.appendChild(subjectRow);
        });
        
        // Add event listener to initial remove buttons
        document.querySelectorAll('.remove-subject').forEach(button => {
            button.addEventListener('click', function() {
                const container = document.getElementById('subjectsContainer');
                if (container.children.length > 1) {
                    this.closest('.subject-row').remove();
                }
            });
        });
    </script>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

</body>
</html>
