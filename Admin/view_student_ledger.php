<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Exact GPA Calculation Functions - SAME as view_student_result.php
function calculateExactGPA($percentage)
{
    $percentage = round($percentage, 2);

    if ($percentage >= 91) {
        $gpa = 3.6 + (($percentage - 91) / 9) * (4.0 - 3.6);
        return ['grade' => 'A+', 'gpa' => round($gpa, 2), 'class' => 'bg-green-100 text-green-800'];
    } elseif ($percentage >= 81) {
        $gpa = 3.2 + (($percentage - 81) / 9) * (3.6 - 3.2);
        return ['grade' => 'A', 'gpa' => round($gpa, 2), 'class' => 'bg-green-100 text-green-800'];
    } elseif ($percentage >= 71) {
        $gpa = 2.8 + (($percentage - 71) / 9) * (3.2 - 2.8);
        return ['grade' => 'B+', 'gpa' => round($gpa, 2), 'class' => 'bg-green-100 text-green-800'];
    } elseif ($percentage >= 61) {
        $gpa = 2.6 + (($percentage - 61) / 9) * (2.8 - 2.6);
        return ['grade' => 'B', 'gpa' => round($gpa, 2), 'class' => 'bg-green-100 text-green-800'];
    } elseif ($percentage >= 51) {
        $gpa = 2.2 + (($percentage - 51) / 9) * (2.6 - 2.2);
        return ['grade' => 'C+', 'gpa' => round($gpa, 2), 'class' => 'bg-yellow-100 text-yellow-800'];
    } elseif ($percentage >= 41) {
        $gpa = 1.6 + (($percentage - 41) / 9) * (2.2 - 1.6);
        return ['grade' => 'C', 'gpa' => round($gpa, 2), 'class' => 'bg-yellow-100 text-yellow-800'];
    } elseif ($percentage >= 35) {
        return ['grade' => 'D+', 'gpa' => 1.6, 'class' => 'bg-orange-100 text-orange-800'];
    } else {
        return ['grade' => 'NG', 'gpa' => 0.0, 'class' => 'bg-red-100 text-red-800'];
    }
}

function calculateTheoryOnlyGPA($theoryMarks, $theoryFullMarks = 100)
{
    if ($theoryMarks > $theoryFullMarks) {
        return ['error' => 'Theory marks cannot exceed full marks'];
    }

    $percentage = ($theoryMarks / $theoryFullMarks) * 100;
    $result = calculateExactGPA($percentage);

    return [
        'percentage' => round($percentage, 2),
        'grade' => $result['grade'],
        'gpa' => $result['gpa'],
        'class' => $result['class']
    ];
}

function calculateTheoryPracticalGPA($theoryMarks, $practicalMarks, $theoryFullMarks = 75, $practicalFullMarks = 25)
{
    if ($theoryMarks > $theoryFullMarks || $practicalMarks > $practicalFullMarks) {
        return ['error' => 'Marks cannot exceed respective full marks'];
    }

    $theoryPercentage = ($theoryMarks / $theoryFullMarks) * 100;
    $practicalPercentage = ($practicalMarks / $practicalFullMarks) * 100;

    $theoryResult = calculateExactGPA($theoryPercentage);
    $practicalResult = calculateExactGPA($practicalPercentage);

    $finalGPA = ($theoryResult['gpa'] * 75 + $practicalResult['gpa'] * 25) / 100;

    return [
        'theory' => [
            'percentage' => round($theoryPercentage, 2),
            'grade' => $theoryResult['grade'],
            'gpa' => $theoryResult['gpa'],
            'class' => $theoryResult['class']
        ],
        'practical' => [
            'percentage' => round($practicalPercentage, 2),
            'grade' => $practicalResult['grade'],
            'gpa' => $practicalResult['gpa'],
            'class' => $practicalResult['class']
        ],
        'final_gpa' => round($finalGPA, 2)
    ];
}

function isSubjectFailed($theory_marks, $practical_marks = null, $has_practical = false)
{
    $theory_full_marks = $has_practical ? 75 : 100;
    $theory_percentage = ($theory_marks / $theory_full_marks) * 100;

    if ($theory_percentage < 33) {
        return true;
    }

    if ($has_practical && $practical_marks !== null) {
        $practical_percentage = ($practical_marks / 25) * 100;
        if ($practical_percentage < 33) {
            return true;
        }
    }

    return false;
}

// Get parameters
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if (!$selected_class || !$selected_exam) {
    $_SESSION['error'] = "Please select both class and exam.";
    header("Location: student_ledger.php");
    exit();
}

// Get class information
$stmt = $conn->prepare("SELECT class_name, section, academic_year FROM classes WHERE class_id = ?");
$stmt->bind_param("i", $selected_class);
$stmt->execute();
$class_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class_info) {
    $_SESSION['error'] = "Class not found.";
    header("Location: student_ledger.php");
    exit();
}

// Get exam information
$stmt = $conn->prepare("SELECT exam_name, exam_type, academic_year FROM exams WHERE exam_id = ?");
$stmt->bind_param("i", $selected_exam);
$stmt->execute();
$exam_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam_info) {
    $_SESSION['error'] = "Exam not found.";
    header("Location: student_ledger.php");
    exit();
}

// Get all subjects for this class from results
$subjects = [];
$subject_query = "SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code 
                 FROM subjects s
                 JOIN results r ON s.subject_id = r.subject_id
                 JOIN students st ON r.student_id = st.student_id
                 WHERE st.class_id = ? AND r.exam_id = ?
                 ORDER BY s.subject_name";

$stmt = $conn->prepare($subject_query);
$stmt->bind_param("ii", $selected_class, $selected_exam);
$stmt->execute();
$subject_result = $stmt->get_result();

while ($row = $subject_result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Get all students in the class with their results - EXACT same logic as view_student_result.php
$ledger_data = [];
$student_query = "SELECT DISTINCT s.student_id, s.roll_number, u.full_name
                 FROM students s
                 JOIN users u ON s.user_id = u.user_id
                 WHERE s.class_id = ?
                 ORDER BY s.roll_number";

$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $selected_class);
$stmt->execute();
$student_result = $stmt->get_result();

while ($student_row = $student_result->fetch_assoc()) {
    $student_id = $student_row['student_id'];
    
    // Get all results for this student and exam - EXACT same query structure as view_student_result.php
    $all_results = [];
    $query = "SELECT r.*, s.subject_name, s.subject_code
              FROM results r
              JOIN subjects s ON r.subject_id = s.subject_id
              WHERE r.student_id = ? AND r.exam_id = ?
              ORDER BY s.subject_name, r.result_id DESC";

    $result_stmt = $conn->prepare($query);
    $result_stmt->bind_param("ii", $student_id, $selected_exam);
    $result_stmt->execute();
    $result = $result_stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $all_results[] = $row;
    }
    $result_stmt->close();

    // Process results using EXACT same logic as view_student_result.php
    $student_subjects = [];
    
    // Remove duplicates by keeping only the first (most recent) result for each subject
    $seen_subjects = [];
    $unique_subjects = [];

    foreach ($all_results as $row) {
        $subject_key = $row['subject_id'];
        if (!isset($seen_subjects[$subject_key])) {
            $seen_subjects[$subject_key] = true;
            $unique_subjects[] = $row;
        }
    }

    // Process each unique subject with EXACT same calculation as view_student_result.php
    foreach ($unique_subjects as $row) {
        $theory_marks = floatval($row['theory_marks'] ?? 0);
        $practical_marks = floatval($row['practical_marks'] ?? 0);

        // Determine if subject has practical based on whether practical_marks > 0 - EXACT same logic
        $has_practical = $practical_marks > 0;

        // Determine full marks based on whether practical exists - EXACT same logic
        $theory_full_marks = $has_practical ? 75 : 100;
        $practical_full_marks = $has_practical ? 25 : 0;
        $subject_full_marks = 100; // Total is always 100

        $subject_total_obtained = $theory_marks + $practical_marks;

        // Calculate using exact GPA functions - SAME as view_student_result.php
        if ($has_practical) {
            // Theory + Practical case (75 + 25)
            $gpaResult = calculateTheoryPracticalGPA($theory_marks, $practical_marks, 75, 25);
            if (isset($gpaResult['error'])) {
                $theory_percentage = 0;
                $practical_percentage = 0;
                $theory_grade_info = ['grade' => 'Error', 'gpa' => 0, 'class' => 'bg-red-100 text-red-800'];
                $practical_grade_info = ['grade' => 'Error', 'gpa' => 0, 'class' => 'bg-red-100 text-red-800'];
                $final_gpa = 0.0;
                $final_grade = 'Error';
                $final_grade_class = 'bg-red-100 text-red-800';
                $is_failed = true;
            } else {
                $theory_percentage = $gpaResult['theory']['percentage'];
                $practical_percentage = $gpaResult['practical']['percentage'];
                $theory_grade_info = ['grade' => $gpaResult['theory']['grade'], 'gpa' => $gpaResult['theory']['gpa'], 'class' => $gpaResult['theory']['class']];
                $practical_grade_info = ['grade' => $gpaResult['practical']['grade'], 'gpa' => $gpaResult['practical']['gpa'], 'class' => $gpaResult['practical']['class']];
                $final_gpa = $gpaResult['final_gpa'];

                // Check for failure condition (either theory or practical below 35%)
                $is_failed = ($theory_percentage < 35) || ($practical_percentage < 35);

                if ($is_failed) {
                    $final_gpa = 0.0;
                    $final_grade = 'NG';
                    $final_grade_class = 'bg-red-100 text-red-800';
                } else {
                    // Determine final grade based on exact GPA
                    if ($final_gpa >= 3.6) {
                        $final_grade = 'A+';
                    } elseif ($final_gpa >= 3.2) {
                        $final_grade = 'A';
                    } elseif ($final_gpa >= 2.8) {
                        $final_grade = 'B+';
                    } elseif ($final_gpa >= 2.6) {
                        $final_grade = 'B';
                    } elseif ($final_gpa >= 2.2) {
                        $final_grade = 'C+';
                    } elseif ($final_gpa > 1.6) {
                        $final_grade = 'C';
                    } elseif ($final_gpa == 1.6) {
                        $final_grade = 'D+';
                    } else {
                        $final_grade = 'NG';
                    }

                    $final_grade_class = $final_grade == 'NG' ? 'bg-red-100 text-red-800' : ($final_gpa >= 3.0 ? 'bg-green-100 text-green-800' : ($final_gpa >= 2.0 ? 'bg-yellow-100 text-yellow-800' : 'bg-orange-100 text-orange-800'));
                }
            }
        } else {
            // Theory only case (100 marks)
            $gpaResult = calculateTheoryOnlyGPA($theory_marks, 100);
            if (isset($gpaResult['error'])) {
                $theory_percentage = 0;
                $theory_grade_info = ['grade' => 'Error', 'gpa' => 0, 'class' => 'bg-red-100 text-red-800'];
                $final_gpa = 0.0;
                $final_grade = 'Error';
                $final_grade_class = 'bg-red-100 text-red-800';
                $is_failed = true;
            } else {
                $theory_percentage = $gpaResult['percentage'];
                $theory_grade_info = ['grade' => $gpaResult['grade'], 'gpa' => $gpaResult['gpa'], 'class' => $gpaResult['class']];
                $final_gpa = $gpaResult['gpa'];

                // Check for failure condition (theory below 35%)
                $is_failed = ($theory_percentage < 35);

                if ($is_failed) {
                    $final_gpa = 0.0;
                    $final_grade = 'NG';
                    $final_grade_class = 'bg-red-100 text-red-800';
                } else {
                    $final_grade = $gpaResult['grade'];
                    $final_grade_class = $gpaResult['class'];
                }
            }
            $practical_percentage = 0;
            $practical_grade_info = ['grade' => 'N/A', 'gpa' => 0, 'class' => 'bg-gray-100 text-gray-800'];
        }

        // Store calculated values - EXACT same structure as view_student_result.php
        $student_subjects[] = [
            'result_id' => $row['result_id'],
            'subject_id' => $row['subject_id'],
            'subject_name' => $row['subject_name'],
            'subject_code' => $row['subject_code'],
            'theory_marks' => $theory_marks,
            'practical_marks' => $has_practical ? $practical_marks : null,
            'grade' => $row['grade'], // Keep original grade from DB
            'gpa' => $row['gpa'], // Keep original GPA from DB
            'calculated_grade' => $final_grade,
            'calculated_gpa' => $final_gpa,
            'calculated_percentage' => ($subject_total_obtained / $subject_full_marks) * 100,
            'theory_full_marks' => $theory_full_marks,
            'practical_full_marks' => $practical_full_marks,
            'subject_full_marks' => $subject_full_marks,
            'has_practical' => $has_practical,
            'theory_percentage' => $theory_percentage,
            'practical_percentage' => $practical_percentage,
            'theory_grade_info' => $theory_grade_info,
            'practical_grade_info' => $practical_grade_info,
            'final_grade_class' => $final_grade_class,
            'is_failed' => $is_failed
        ];
    }

    // Calculate overall result from subject results using EXACT same logic as view_student_result.php
    $total_marks_obtained = 0;
    $total_full_marks = 0;
    $total_subjects = count($student_subjects);
    $failed_subjects = 0;
    $total_gpa_points = 0;

    foreach ($student_subjects as $subject) {
        $subject_total_obtained = $subject['theory_marks'] + ($subject['practical_marks'] ?? 0);
        $total_marks_obtained += $subject_total_obtained;
        $total_full_marks += $subject['subject_full_marks'];
        $total_gpa_points += $subject['calculated_gpa'];

        if ($subject['is_failed']) {
            $failed_subjects++;
        }
    }

    // Calculate overall percentage and GPA - EXACT same logic as view_student_result.php
    $overall_percentage = $total_full_marks > 0 ? ($total_marks_obtained / $total_full_marks) * 100 : 0;
    $overall_gpa = $total_subjects > 0 ? ($total_gpa_points / $total_subjects) : 0;
    $is_pass = ($failed_subjects == 0);

    // Determine overall grade - EXACT same logic as view_student_result.php
    if ($failed_subjects > 0) {
        $overall_grade = 'NG';
    } else {
        if ($overall_percentage >= 91) {
            $overall_grade = 'A+';
        } elseif ($overall_percentage >= 81) {
            $overall_grade = 'A';
        } elseif ($overall_percentage >= 71) {
            $overall_grade = 'B+';
        } elseif ($overall_percentage >= 61) {
            $overall_grade = 'B';
        } elseif ($overall_percentage >= 51) {
            $overall_grade = 'C+';
        } elseif ($overall_percentage >= 41) {
            $overall_grade = 'C';
        } elseif ($overall_percentage >= 35) {
            $overall_grade = 'D+';
        } else {
            $overall_grade = 'NG';
        }
    }

    // Determine division - EXACT same logic as view_student_result.php
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

    $ledger_data[] = [
        'student_id' => $student_id,
        'roll_number' => $student_row['roll_number'],
        'name' => $student_row['full_name'],
        'subjects' => $student_subjects,
        'total_marks' => $total_marks_obtained,
        'total_full_marks' => $total_full_marks,
        'percentage' => $overall_percentage,
        'grade' => $overall_grade,
        'gpa' => $overall_gpa,
        'result' => $is_pass ? 'PASS' : 'FAIL',
        'division' => $division,
        'failed_subjects' => $failed_subjects
    ];
}
$stmt->close();

// Sort by roll number
usort($ledger_data, function($a, $b) {
    return strcmp($a['roll_number'], $b['roll_number']);
});

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Result Ledger | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background-color: white !important; }
            .print-container { 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 10px !important; 
            }
            table { font-size: 9px !important; }
            th, td { padding: 3px !important; }
        }
        
        .ledger-table {
            font-size: 11px;
        }
        
        .ledger-table th,
        .ledger-table td {
            border: 1px solid #e5e7eb;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
        }
        
        .ledger-table th {
            background-color: #f3f4f6;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .subject-header {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            min-width: 35px;
            max-width: 35px;
            font-size: 10px;
        }
        
        .student-name {
            text-align: left;
            min-width: 120px;
            max-width: 150px;
            font-size: 11px;
        }
        
        .marks-cell {
            font-weight: 500;
            font-size: 10px;
        }
        
        .grade-cell {
            font-weight: 600;
            font-size: 10px;
        }
        
        .fail-mark {
            color: #dc2626;
            font-weight: bold;
        }
        
        .pass-mark {
            color: #059669;
            font-weight: bold;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="no-print">
            <?php include 'sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="no-print">
                <?php include 'topBar.php'; ?>
            </div>

            <!-- Main Content Area -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-full mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Header -->
                        <div class="flex justify-between items-center mb-6 no-print">
                            <div>
                                <h1 class="text-2xl font-semibold text-gray-900">Class Result Ledger</h1>
                                <p class="mt-1 text-sm text-gray-600">
                                    <?php echo $class_info['class_name'] . ' ' . $class_info['section']; ?> - 
                                    <?php echo $exam_info['exam_name']; ?> (<?php echo $exam_info['exam_type']; ?>)
                                </p>
                                <p class="text-sm text-gray-500">Academic Year: <?php echo $exam_info['academic_year']; ?></p>
                            </div>
                            <div class="flex space-x-2">
                                <a href="student_ledger.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
                                    <i class="fas fa-arrow-left mr-2"></i>Back
                                </a>
                                <button onclick="window.print()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
                                    <i class="fas fa-print mr-2"></i>Print
                                </button>
                                <button onclick="exportToPDF()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md">
                                    <i class="fas fa-file-pdf mr-2"></i>Export PDF
                                </button>
                                <button onclick="exportToExcel()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                    <i class="fas fa-file-excel mr-2"></i>Export Excel
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($ledger_data)): ?>
                            <!-- Ledger Table -->
                            <div class="bg-white shadow rounded-lg overflow-hidden print-container">
                                <!-- Print Header -->
                                <div class="hidden print:block p-4 text-center border-b">
                                    <h1 class="text-xl font-bold">Student Result Ledger</h1>
                                    <p class="text-sm">Class: <?php echo $class_info['class_name'] . ' ' . $class_info['section']; ?></p>
                                    <p class="text-sm">Exam: <?php echo $exam_info['exam_name']; ?> (<?php echo $exam_info['exam_type']; ?>)</p>
                                    <p class="text-sm">Academic Year: <?php echo $exam_info['academic_year']; ?></p>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full ledger-table" id="ledgerTable">
                                        <thead>
                                            <tr>
                                                <th rowspan="2" class="bg-blue-50">Roll No</th>
                                                <th rowspan="2" class="bg-blue-50 student-name">Student Name</th>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <th class="bg-green-50 subject-header" title="<?php echo $subject['subject_name']; ?>">
                                                        <?php echo substr($subject['subject_name'], 0, 12); ?>
                                                        <?php if (strlen($subject['subject_name']) > 12) echo '...'; ?>
                                                    </th>
                                                <?php endforeach; ?>
                                                <th rowspan="2" class="bg-yellow-50">Total</th>
                                                <th rowspan="2" class="bg-yellow-50">Percentage</th>
                                                <th rowspan="2" class="bg-yellow-50">Grade</th>
                                                <th rowspan="2" class="bg-yellow-50">Result</th>
                                            </tr>
                                            <tr>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <th class="bg-green-50 text-xs">
                                                        (<?php echo $subject['subject_code'] ?? substr($subject['subject_name'], 0, 3); ?>)
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ledger_data as $student): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="font-medium"><?php echo $student['roll_number']; ?></td>
                                                    <td class="student-name font-medium text-left"><?php echo htmlspecialchars($student['name']); ?></td>
                                                    
                                                    <?php foreach ($subjects as $subject): ?>
                                                        <td class="marks-cell">
                                                            <?php 
                                                            $subject_found = false;
                                                            foreach ($student['subjects'] as $student_subject) {
                                                                if ($student_subject['subject_id'] == $subject['subject_id']) {
                                                                    $subject_found = true;
                                                                    $total_marks = $student_subject['theory_marks'] + ($student_subject['practical_marks'] ?? 0);
                                                                    ?>
                                                                    <div class="<?php echo $student_subject['is_failed'] ? 'fail-mark' : 'pass-mark'; ?>">
                                                                        <?php echo number_format($total_marks, 0); ?>
                                                                    </div>
                                                                    <div class="text-xs text-gray-500">
                                                                        <?php echo $student_subject['calculated_grade']; ?>
                                                                    </div>
                                                                    <?php
                                                                    break;
                                                                }
                                                            }
                                                            if (!$subject_found): ?>
                                                                <span class="text-gray-400">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    
                                                    <td class="marks-cell font-bold">
                                                        <?php echo number_format($student['total_marks'], 0); ?>/<?php echo $student['total_full_marks']; ?>
                                                    </td>
                                                    <td class="marks-cell font-bold">
                                                        <?php echo number_format($student['percentage'], 2); ?>%
                                                    </td>
                                                    <td class="grade-cell">
                                                        <span class="px-2 py-1 rounded text-xs font-semibold
                                                            <?php 
                                                            if ($student['grade'] == 'A+' || $student['grade'] == 'A') echo 'bg-green-100 text-green-800';
                                                            elseif ($student['grade'] == 'B+' || $student['grade'] == 'B') echo 'bg-blue-100 text-blue-800';
                                                            elseif ($student['grade'] == 'C+' || $student['grade'] == 'C') echo 'bg-yellow-100 text-yellow-800';
                                                            elseif ($student['grade'] == 'D+') echo 'bg-orange-100 text-orange-800';
                                                            else echo 'bg-red-100 text-red-800';
                                                            ?>">
                                                            <?php echo $student['grade']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="grade-cell">
                                                        <span class="px-2 py-1 rounded text-xs font-semibold
                                                            <?php echo $student['result'] == 'PASS' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                            <?php echo $student['result']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Summary Statistics -->
                                <div class="p-4 bg-gray-50 border-t">
                                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900">Total Students</div>
                                            <div class="text-lg font-bold text-blue-600"><?php echo count($ledger_data); ?></div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900">Passed</div>
                                            <div class="text-lg font-bold text-green-600">
                                                <?php echo count(array_filter($ledger_data, function($s) { return $s['result'] == 'PASS'; })); ?>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900">Failed</div>
                                            <div class="text-lg font-bold text-red-600">
                                                <?php echo count(array_filter($ledger_data, function($s) { return $s['result'] == 'FAIL'; })); ?>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900">Pass Rate</div>
                                            <div class="text-lg font-bold text-blue-600">
                                                <?php 
                                                $pass_count = count(array_filter($ledger_data, function($s) { return $s['result'] == 'PASS'; }));
                                                $pass_rate = count($ledger_data) > 0 ? ($pass_count / count($ledger_data)) * 100 : 0;
                                                echo number_format($pass_rate, 1) . '%';
                                                ?>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-gray-900">Class Average</div>
                                            <div class="text-lg font-bold text-purple-600">
                                                <?php 
                                                $total_percentage = array_sum(array_column($ledger_data, 'percentage'));
                                                $class_average = count($ledger_data) > 0 ? $total_percentage / count($ledger_data) : 0;
                                                echo number_format($class_average, 2) . '%';
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Print-Only Signature Section -->
                                <div class="hidden print:block mt-8 p-4">
                                    <div class="grid grid-cols-3 gap-4 text-center">
                                        <div>
                                            <div class="border-t border-gray-400 pt-2 mt-12">
                                                <p class="text-sm">Class Teacher</p>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="border-t border-gray-400 pt-2 mt-12">
                                                <p class="text-sm">Examination Controller</p>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="border-t border-gray-400 pt-2 mt-12">
                                                <p class="text-sm">Principal</p>
                                            </div>
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
                                            No results found for the selected class and exam combination.
                                        </p>
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
        // Export to PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4'); // Landscape orientation
            
            // Add title
            doc.setFontSize(16);
            doc.text('Student Result Ledger', 20, 20);
            
            // Add class and exam info
            doc.setFontSize(12);
            doc.text('Class: <?php echo $class_info['class_name'] . ' ' . $class_info['section']; ?>', 20, 30);
            doc.text('Exam: <?php echo $exam_info['exam_name']; ?>', 20, 37);
            doc.text('Generated on: <?php echo date('F d, Y'); ?>', 20, 44);
            
            // Add table
            doc.autoTable({
                html: '#ledgerTable',
                startY: 50,
                theme: 'grid',
                headStyles: { 
                    fillColor: [59, 130, 246],
                    textColor: [255, 255, 255],
                    fontSize: 7
                },
                bodyStyles: {
                    fontSize: 6
                },
                columnStyles: {
                    0: { cellWidth: 12 }, // Roll No
                    1: { cellWidth: 35 }  // Name
                },
                margin: { left: 10, right: 10 }
            });
            
            // Save the PDF
            doc.save('student_ledger_<?php echo date('Y-m-d'); ?>.pdf');
        }

        // Export to Excel (CSV format)
        function exportToExcel() {
            const table = document.getElementById('ledgerTable');
            const rows = table.querySelectorAll('tr');
            
            let csv = [];
            
            // Process header rows
            const headerRow1 = rows[0];
            const headerRow2 = rows[1];
            
            let header1 = [];
            let header2 = [];
            
            // Build headers
            for (let i = 0; i < headerRow1.cells.length; i++) {
                const cell = headerRow1.cells[i];
                const colspan = cell.colSpan || 1;
                const rowspan = cell.rowSpan || 1;
                
                for (let j = 0; j < colspan; j++) {
                    header1.push(cell.textContent.trim());
                    if (rowspan === 1) {
                        header2.push('');
                    }
                }
            }
            
            // Add second header row
            let headerIndex = 0;
            for (let i = 0; i < headerRow2.cells.length; i++) {
                const cell = headerRow2.cells[i];
                while (header2[headerIndex] !== '') {
                    headerIndex++;
                }
                header2[headerIndex] = cell.textContent.trim();
                headerIndex++;
            }
            
            csv.push(header1.map(h => `"${h}"`).join(','));
            csv.push(header2.map(h => `"${h}"`).join(','));
            
            // Process data rows
            for (let i = 2; i < rows.length; i++) {
                const row = [];
                const cols = rows[i].querySelectorAll('td');
                
                for (let j = 0; j < cols.length; j++) {
                    let data = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, ' ').trim();
                    data = data.replace(/"/g, '""');
                    row.push(`"${data}"`);
                }
                csv.push(row.join(','));
            }
            
            const csvString = csv.join('\n');
            const filename = 'student_ledger_<?php echo date('Y-m-d'); ?>.csv';
            
            // Create download link
            const link = document.createElement('a');
            link.style.display = 'none';
            link.setAttribute('target', '_blank');
            link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
