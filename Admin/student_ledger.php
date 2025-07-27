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

// Exact GPA Calculation Functions
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

// Get available classes
$classes = [];
$class_query = "SELECT class_id, class_name, section FROM classes ORDER BY class_name, section";
$class_result = $conn->query($class_query);
while ($row = $class_result->fetch_assoc()) {
    $classes[] = $row;
}

// Get available exams
$exams = [];
$exam_query = "SELECT exam_id, exam_name, exam_type, academic_year FROM exams WHERE is_active = 1 ORDER BY created_at DESC";
$exam_result = $conn->query($exam_query);
while ($row = $exam_result->fetch_assoc()) {
    $exams[] = $row;
}

// Initialize variables
$selected_class = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$selected_exam = isset($_GET['exam_id']) ? $_GET['exam_id'] : '';
$ledger_data = [];
$subjects = [];
$class_info = null;
$exam_info = null;

if ($selected_class && $selected_exam) {
    // Get class information
    $stmt = $conn->prepare("SELECT class_name, section, academic_year FROM classes WHERE class_id = ?");
    $stmt->bind_param("i", $selected_class);
    $stmt->execute();
    $class_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get exam information
    $stmt = $conn->prepare("SELECT exam_name, exam_type, academic_year FROM exams WHERE exam_id = ?");
    $stmt->bind_param("i", $selected_exam);
    $stmt->execute();
    $exam_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get all subjects for this class from results
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

    // Get all students in the class with their results
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
        
        // Get results for this student and exam
        $result_query = "SELECT r.*, s.subject_name, s.subject_id
                        FROM results r
                        JOIN subjects s ON r.subject_id = s.subject_id
                        WHERE r.student_id = ? AND r.exam_id = ?
                        ORDER BY s.subject_name";
        
        $result_stmt = $conn->prepare($result_query);
        $result_stmt->bind_param("si", $student_id, $selected_exam);
        $result_stmt->execute();
        $results = $result_stmt->get_result();
        
        $student_subjects = [];
        $total_marks = 0;
        $total_full_marks = 0;
        $failed_subjects = 0;
        $total_gpa_points = 0;
        $subject_count = 0;
        
        while ($result_row = $results->fetch_assoc()) {
            $theory_marks = floatval($result_row['theory_marks'] ?? 0);
            $practical_marks = floatval($result_row['practical_marks'] ?? 0);
            $subject_total = $theory_marks + $practical_marks;
            
            // Determine if subject has practical
            $has_practical = $practical_marks > 0;
            $theory_full_marks = $has_practical ? 75 : 100;
            $practical_full_marks = $has_practical ? 25 : 0;
            $subject_full_marks = 100;
            
            // Calculate percentage and grade
            $subject_percentage = ($subject_total / $subject_full_marks) * 100;
            
            // Check if failed (below 33% in theory or practical)
            $theory_percentage = ($theory_marks / $theory_full_marks) * 100;
            $practical_percentage = $has_practical ? ($practical_marks / $practical_full_marks) * 100 : 100;
            
            $is_failed = ($theory_percentage < 33) || ($has_practical && $practical_percentage < 33);
            
            if ($is_failed) {
                $grade_info = ['grade' => 'NG', 'gpa' => 0.0, 'class' => 'bg-red-100 text-red-800'];
                $failed_subjects++;
            } else {
                $grade_info = calculateExactGPA($subject_percentage);
            }
            
            $student_subjects[$result_row['subject_id']] = [
                'marks' => $subject_total,
                'percentage' => $subject_percentage,
                'grade' => $grade_info['grade'],
                'gpa' => $grade_info['gpa'],
                'is_failed' => $is_failed
            ];
            
            $total_marks += $subject_total;
            $total_full_marks += $subject_full_marks;
            $total_gpa_points += $grade_info['gpa'];
            $subject_count++;
        }
        $result_stmt->close();
        
        // Calculate overall performance
        $overall_percentage = $total_full_marks > 0 ? ($total_marks / $total_full_marks) * 100 : 0;
        $overall_gpa = $subject_count > 0 ? ($total_gpa_points / $subject_count) : 0;
        
        // Determine overall grade
        if ($failed_subjects > 0) {
            $overall_grade = 'NG';
            $result_status = 'FAIL';
        } else {
            $grade_info = calculateExactGPA($overall_percentage);
            $overall_grade = $grade_info['grade'];
            $result_status = 'PASS';
        }
        
        $ledger_data[] = [
            'student_id' => $student_id,
            'roll_number' => $student_row['roll_number'],
            'name' => $student_row['full_name'],
            'subjects' => $student_subjects,
            'total_marks' => $total_marks,
            'total_full_marks' => $total_full_marks,
            'percentage' => $overall_percentage,
            'grade' => $overall_grade,
            'gpa' => $overall_gpa,
            'result' => $result_status,
            'failed_subjects' => $failed_subjects
        ];
    }
    $stmt->close();
    
    // Sort by roll number
    usort($ledger_data, function($a, $b) {
        return strcmp($a['roll_number'], $b['roll_number']);
    });
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Ledger | Result Management System</title>
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
            table { font-size: 10px !important; }
            th, td { padding: 4px !important; }
        }
        
        .ledger-table {
            font-size: 12px;
        }
        
        .ledger-table th,
        .ledger-table td {
            border: 1px solid #e5e7eb;
            padding: 6px;
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
            min-width: 40px;
            max-width: 40px;
        }
        
        .student-name {
            text-align: left;
            min-width: 150px;
            max-width: 200px;
        }
        
        .marks-cell {
            font-weight: 500;
        }
        
        .grade-cell {
            font-weight: 600;
        }
        
        .fail-mark {
            color: #dc2626;
            font-weight: bold;
        }
        
        .pass-mark {
            color: #059669;
            font-weight: bold;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include 'topBar.php'; ?>
            <?php include 'mobile_sidebar.php'; ?>

            <!-- Main Content Area -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-full mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Header -->
                        <div class="mb-6 no-print">
                            <h1 class="text-2xl font-semibold text-gray-900">Student Ledger</h1>
                            <p class="mt-1 text-sm text-gray-600">Terminal-wise class performance ledger</p>
                        </div>

                        <!-- Filter Section -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6 no-print">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Select Class and Exam</h2>
                            <form method="GET" action="view_student_ledger.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="class_id" class="block text-sm font-medium text-gray-700 mb-2">Class</label>
                                    <select name="class_id" id="class_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['class_id']; ?>" <?php echo $selected_class == $class['class_id'] ? 'selected' : ''; ?>>
                                                <?php echo $class['class_name'] . ' ' . $class['section']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-2">Exam/Terminal</label>
                                    <select name="exam_id" id="exam_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="">Select Exam</option>
                                        <?php foreach ($exams as $exam): ?>
                                            <option value="<?php echo $exam['exam_id']; ?>" <?php echo $selected_exam == $exam['exam_id'] ? 'selected' : ''; ?>>
                                                <?php echo $exam['exam_name'] . ' (' . $exam['exam_type'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="flex items-end">
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition duration-200">
                                        <i class="fas fa-search mr-2"></i>Generate Ledger
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.remove('-translate-x-full');
        });

        document.getElementById('close-sidebar')?.addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });

        document.getElementById('sidebar-backdrop')?.addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });
    </script>
</body>
</html>
