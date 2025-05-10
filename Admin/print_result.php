<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo "Unauthorized access";
    exit();
}

// Check if required parameters are provided
if (!isset($_GET['student_id']) || empty($_GET['student_id']) || !isset($_GET['exam_id']) || empty($_GET['exam_id'])) {
    echo "Missing required parameters";
    exit();
}

$student_id = $_GET['student_id'];
$exam_id = $_GET['exam_id'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error;
    exit();
}

// Get student data
$stmt = $conn->prepare("
    SELECT s.*, u.full_name, c.class_name, c.section, c.academic_year
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN classes c ON s.class_id = c.class_id
    WHERE s.student_id = ?
");

$stmt->bind_param("s", $student_id);
$stmt->execute();
$student_result = $stmt->get_result();

if ($student_result->num_rows === 0) {
    echo "Student not found";
    exit();
}

$student = $student_result->fetch_assoc();

// Get exam details
$stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam_result = $stmt->get_result();

if ($exam_result->num_rows === 0) {
    echo "Exam not found";
    exit();
}

$exam = $exam_result->fetch_assoc();

// Get results for the exam
$results_query = "
    SELECT r.*, s.subject_name, s.subject_code, s.credit_hours
    FROM results r
    JOIN subjects s ON r.subject_id = s.subject_id
    WHERE r.student_id = ? AND r.exam_id = ?
    ORDER BY s.subject_name
";

$stmt = $conn->prepare($results_query);
$stmt->bind_param("si", $student_id, $exam_id);
$stmt->execute();
$results_result = $stmt->get_result();

$results = [];
$total_marks = 0;
$total_subjects = 0;
$total_credit_hours = 0;
$total_grade_points = 0;
$failed_subjects = 0;

while ($row = $results_result->fetch_assoc()) {
    $results[] = $row;
    $total_marks += ($row['theory_marks'] + ($row['practical_marks'] ?? 0));
    $total_subjects++;
    
    // Calculate GPA if not already set
    if (!isset($row['g'  ?? 0]));
    $total_subjects++;
    
    // Calculate GPA if not already set
    if (!isset($row['gpa']) || $row['gpa'] === null) {
        $grade = $row['grade'];
        $gpa = 0;
        
        switch ($grade) {
            case 'A+': $gpa = 4.0; break;
            case 'A': $gpa = 3.7; break;
            case 'B+': $gpa = 3.3; break;
            case 'B': $gpa = 3.0; break;
            case 'C+': $gpa = 2.7; break;
            case 'C': $gpa = 2.3; break;
            case 'D': $gpa = 1.0; break;
            case 'F': $gpa = 0.0; break;
        }
        
        $row['gpa'] = $gpa;
    }
    
    $credit = $row['credit_hours'] ?? 1;
    $total_credit_hours += $credit;
    $total_grade_points += ($row['gpa'] * $credit);
    
    if ($row['grade'] == 'F') {
        $failed_subjects++;
    }
}

// Calculate overall GPA and percentage
$overall_gpa = $total_credit_hours > 0 ? ($total_grade_points / $total_credit_hours) : 0;
$percentage = $total_subjects > 0 ? ($total_marks / ($total_subjects * 100)) * 100 : 0;

// Determine result status and division
$result_status = $failed_subjects > 0 ? 'FAIL' : 'PASS';
$division = '';

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

// Get school information
$school_info = [];
$school_query = "SELECT * FROM settings WHERE setting_key LIKE 'school_%'";
$school_result = $conn->query($school_query);
if ($school_result && $school_result->num_rows > 0) {
    while ($row = $school_result->fetch_assoc()) {
        $key = str_replace('school_', '', $row['setting_key']);
        $school_info[$key] = $row['setting_value'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Sheet - <?php echo htmlspecialchars($student['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @media print {
            body {
                font-size: 12pt;
                color: #000;
                background-color: #fff;
            }
            
            .print-container {
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
            }
            
            .page-break {
                page-break-after: always;
            }
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
    <div class="print-container max-w-4xl mx-auto bg-white p-8 shadow-md">
        <!-- Header -->
        <div class="text-center mb-6">
            <?php if (!empty($school_info['logo'])): ?>
                <img src="<?php echo htmlspecialchars($school_info['logo']); ?>" alt="School Logo" class="h-20 mx-auto mb-2">
            <?php endif; ?>
            
            <h1 class="text-2xl font-bold"><?php echo !empty($school_info['name']) ? htmlspecialchars($school_info['name']) : 'School Name'; ?></h1>
            <p class="text-gray-600"><?php echo !empty($school_info['address']) ? htmlspecialchars($school_info['address']) : 'School Address'; ?></p>
            <p class="text-gray-600"><?php echo !empty($school_info['contact']) ? htmlspecialchars($school_info['contact']) : 'Contact Information'; ?></p>
            
            <div class="mt-4 border-t border-b border-gray-300 py-2">
                <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($exam['exam_name']); ?> - Result Sheet</h2>
                <p class="text-gray-600"><?php echo ucfirst(htmlspecialchars($exam['exam_type'])); ?></p>
            </div>
        </div>
        
        <!-- Student Information -->
        <div class="mb-6 grid grid-cols-2 gap-4">
            <div>
                <p><span class="font-semibold">Student Name:</span> <?php echo htmlspecialchars($student['full_name']); ?></p>
                <p><span class="font-semibold">Student ID:</span> <?php echo htmlspecialchars($student['student_id']); ?></p>
                <p><span class="font-semibold">Roll Number:</span> <?php echo htmlspecialchars($student['roll_number']); ?></p>
            </div>
            <div>
                <p><span class="font-semibold">Class:</span> <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?></p>
                <p><span class="font-semibold">Academic Year:</span> <?php echo htmlspecialchars($student['academic_year']); ?></p>
                <p><span class="font-semibold">Date:</span> <?php echo date('F j, Y'); ?></p>
            </div>
        </div>
        
        <!-- Results Table -->
        <div class="mb-6">
            <table class="min-w-full border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-4 py-2">Subject</th>
                        <th class="border border-gray-300 px-4 py-2">Theory</th>
                        <th class="border border-gray-300 px-4 py-2">Practical</th>
                        <th class="border border-gray-300 px-4 py-2">Total</th>
                        <th class="border border-gray-300 px-4 py-2">Grade</th>
                        <th class="border border-gray-300 px-4 py-2">GPA</th>
                        <th class="border border-gray-300 px-4 py-2">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="7" class="border border-gray-300 px-4 py-2 text-center">No results found for this exam.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td class="border border-gray-300 px-4 py-2">
                                    <?php echo htmlspecialchars($result['subject_name']); ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($result['subject_code']); ?></div>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    <?php echo number_format($result['theory_marks'], 2); ?>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    <?php echo isset($result['practical_marks']) ? number_format($result['practical_marks'], 2) : 'N/A'; ?>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    <?php 
                                    $total = $result['theory_marks'] + ($result['practical_marks'] ?? 0);
                                    echo number_format($total, 2); 
                                    ?>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    <?php echo $result['grade']; ?>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    <?php echo number_format($result['gpa'], 2); ?>
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <?php echo htmlspecialchars($result['remarks'] ?? ''); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100">
                        <td colspan="3" class="border border-gray-300 px-4 py-2 text-right font-semibold">Total:</td>
                        <td class="border border-gray-300 px-4 py-2 text-center font-semibold"><?php echo number_format($total_marks, 2); ?></td>
                        <td colspan="3" class="border border-gray-300 px-4 py-2"></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="border border-gray-300 px-4 py-2 text-right font-semibold">Percentage:</td>
                        <td class="border border-gray-300 px-4 py-2 text-center font-semibold"><?php echo number_format($percentage, 2); ?>%</td>
                        <td colspan="3" class="border border-gray-300 px-4 py-2"></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="border border-gray-300 px-4 py-2 text-right font-semibold">GPA:</td>
                        <td class="border border-gray-300 px-4 py-2 text-center font-semibold"><?php echo number_format($overall_gpa, 2); ?></td>
                        <td colspan="3" class="border border-gray-300 px-4 py-2"></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="border border-gray-300 px-4 py-2 text-right font-semibold">Division:</td>
                        <td class="border border-gray-300 px-4 py-2 text-center font-semibold"><?php echo $division; ?></td>
                        <td colspan="3" class="border border-gray-300 px-4 py-2"></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="border border-gray-300 px-4 py-2 text-right font-semibold">Result:</td>
                        <td class="border border-gray-300 px-4 py-2 text-center font-semibold"><?php echo $result_status; ?></td>
                        <td colspan="3" class="border border-gray-300 px-4 py-2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Grading Scale -->
        <div class="mb-6 text-sm">
            <h3 class="font-semibold mb-2">Grading Scale:</h3>
            <div class="grid grid-cols-4 gap-2">
                <div>A+ (90-100%): 4.0</div>
                <div>A (80-89%): 3.7</div>
                <div>B+ (70-79%): 3.3</div>
                <div>B (60-69%): 3.0</div>
                <div>C+ (50-59%): 2.7</div>
                <div>C (40-49%): 2.3</div>
                <div>D (33-39%): 1.0</div>
                <div>F (Below 33%): 0.0</div>
            </div>
        </div>
        
        <!-- Signatures -->
        <div class="mt-12 grid grid-cols-3 gap-4 text-center">
            <div>
                <div class="border-t border-gray-400 pt-2">
                    <p class="font-semibold">Class Teacher</p>
                </div>
            </div>
            <div>
                <div class="border-t border-gray-400 pt-2">
                    <p class="font-semibold">Examination Controller</p>
                </div>
            </div>
            <div>
                <div class="border-t border-gray-400 pt-2">
                    <p class="font-semibold">Principal</p>
                </div>
            </div>
        </div>
        
        <!-- Print Button (only visible on screen) -->
        <div class="mt-6 text-center no-print">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Print Result Sheet
            </button>
        </div>
    </div>
    
    <script>
        // Auto-print when the page loads
        window.onload = function() {
            // Slight delay to ensure everything is rendered
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
