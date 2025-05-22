<?php
session_start();
include '../includes/config.php';
include '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Get teacher's ID from teachers table
$teacher_query = "SELECT teacher_id FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($teacher_query);
if ($stmt) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
        $teacher_record_id = $teacher_data['teacher_id'];
    } else {
        $_SESSION['error'] = "Teacher record not found.";
        header("Location: teacher_dashboard.php");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header("Location: teacher_dashboard.php");
    exit();
}

// Verify that this teacher teaches this subject
$verify_query = "
    SELECT 1
    FROM teachersubjects ts
    WHERE ts.teacher_id = ? AND ts.subject_id = ?
";

$verify_stmt = $conn->prepare($verify_query);
if ($verify_stmt) {
    $verify_stmt->bind_param("is", $teacher_record_id, $subject_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $_SESSION['error'] = "You are not authorized to view this student's results for this subject.";
        header("Location: teacher_dashboard.php");
        exit();
    }
    $verify_stmt->close();
} else {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header("Location: teacher_dashboard.php");
    exit();
}

// Get student details
$student_query = "
    SELECT s.student_id, s.roll_number, u.full_name, c.class_name, c.section, 
           sub.subject_name, sub.subject_id
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN classes c ON s.class_id = c.class_id
    JOIN subjects sub ON sub.subject_id = ?
    WHERE s.student_id = ?
";

$student_stmt = $conn->prepare($student_query);
if ($student_stmt) {
    $student_stmt->bind_param("ss", $subject_id, $student_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    $student = $student_result->fetch_assoc();
    
    if (!$student) {
        $_SESSION['error'] = "Student not found.";
        header("Location: teacher_dashboard.php");
        exit();
    }
    $student_stmt->close();
} else {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header("Location: teacher_dashboard.php");
    exit();
}

// Get all exams
$exams_query = "
    SELECT exam_id, exam_name, exam_type, start_date, end_date, total_marks, passing_marks
    FROM exams
    WHERE is_active = 1
    ORDER BY start_date DESC
";

$exams_result = $conn->query($exams_query);
$exams = [];
if ($exams_result) {
    while ($exam = $exams_result->fetch_assoc()) {
        $exams[$exam['exam_id']] = $exam;
    }
}

// Get all results for this student in this subject
$results_query = "
    SELECT r.result_id, r.exam_id, r.theory_marks, r.practical_marks, r.grade, r.gpa, 
           r.remarks, r.percentage, r.is_pass, e.exam_name, e.start_date, e.total_marks, e.passing_marks
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE r.student_id = ? AND r.subject_id = ?
    ORDER BY e.start_date DESC
";

$results_stmt = $conn->prepare($results_query);
if ($results_stmt) {
    $results_stmt->bind_param("ss", $student_id, $subject_id);
    $results_stmt->execute();
    $results_result = $results_stmt->get_result();
    
    $results = [];
    while ($result = $results_result->fetch_assoc()) {
        $results[] = $result;
    }
    $results_stmt->close();
} else {
    $_SESSION['error'] = "Database error: " . $conn->error;
}

// Calculate performance metrics
$total_marks = 0;
$total_max_marks = 0;
$exam_count = count($results);
$pass_count = 0;
$fail_count = 0;
$first_exam_marks = null;
$last_exam_marks = null;
$highest_marks = 0;
$lowest_marks = 100;
$marks_array = [];

foreach ($results as $index => $result) {
    $total_marks_obtained = $result['theory_marks'] + $result['practical_marks'];
    $total_marks += $total_marks_obtained;
    $total_max_marks += $result['total_marks'];
    
    if ($result['is_pass']) {
        $pass_count++;
    } else {
        $fail_count++;
    }
    
    if ($index === $exam_count - 1) {
        $first_exam_marks = $total_marks_obtained;
    }
    
    if ($index === 0) {
        $last_exam_marks = $total_marks_obtained;
    }
    
    if ($total_marks_obtained > $highest_marks) {
        $highest_marks = $total_marks_obtained;
    }
    
    if ($total_marks_obtained < $lowest_marks && $exam_count > 0) {
        $lowest_marks = $total_marks_obtained;
    }
    
    $marks_array[] = $total_marks_obtained;
}

$average_marks = $exam_count > 0 ? round($total_marks / $exam_count, 1) : 0;
$average_percentage = $total_max_marks > 0 ? round(($total_marks / $total_max_marks) * 100, 1) : 0;
$progress = ($first_exam_marks !== null && $last_exam_marks !== null) ? round($last_exam_marks - $first_exam_marks, 1) : null;

// Calculate standard deviation for consistency analysis
$variance = 0;
if ($exam_count > 1) {
    foreach ($marks_array as $mark) {
        $variance += pow($mark - $average_marks, 2);
    }
    $variance = $variance / $exam_count;
    $std_deviation = round(sqrt($variance), 1);
} else {
    $std_deviation = 0;
}

// Get class average for comparison
$class_avg_query = "
    SELECT AVG(r.theory_marks + r.practical_marks) AS class_avg
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.class_id = (SELECT class_id FROM students WHERE student_id = ?) 
    AND r.subject_id = ?
";

$class_avg_stmt = $conn->prepare($class_avg_query);
$class_avg = 0;
if ($class_avg_stmt) {
    $class_avg_stmt->bind_param("ss", $student_id, $subject_id);
    $class_avg_stmt->execute();
    $class_avg_result = $class_avg_stmt->get_result();
    $class_avg_data = $class_avg_result->fetch_assoc();
    $class_avg = $class_avg_data['class_avg'] ? round($class_avg_data['class_avg'], 1) : 0;
    $class_avg_stmt->close();
}

// Process form submission for adding a new result
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_result'])) {
    $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
    $theory_marks = isset($_POST['theory_marks']) ? floatval($_POST['theory_marks']) : 0;
    $practical_marks = isset($_POST['practical_marks']) ? floatval($_POST['practical_marks']) : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate inputs
    $errors = [];
    
    if ($exam_id <= 0) {
        $errors[] = "Please select a valid exam.";
    }
    
    if ($theory_marks < 0) {
        $errors[] = "Theory marks cannot be negative.";
    }
    
    if ($practical_marks < 0) {
        $errors[] = "Practical marks cannot be negative.";
    }
    
    // Check if result already exists
    $check_query = "
        SELECT result_id FROM results 
        WHERE student_id = ? AND subject_id = ? AND exam_id = ?
    ";
    
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt) {
        $check_stmt->bind_param("ssi", $student_id, $subject_id, $exam_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Result for this exam already exists. Please edit the existing result instead.";
        }
        $check_stmt->close();
    } else {
        $errors[] = "Database error: " . $conn->error;
    }
    
    // Get exam details for grade calculation
    if (empty($errors)) {
        $exam_query = "SELECT total_marks, passing_marks FROM exams WHERE exam_id = ?";
        $exam_stmt = $conn->prepare($exam_query);
        if ($exam_stmt) {
            $exam_stmt->bind_param("i", $exam_id);
            $exam_stmt->execute();
            $exam_result = $exam_stmt->get_result();
            $exam = $exam_result->fetch_assoc();
            
            if (!$exam) {
                $errors[] = "Invalid exam selected.";
            } else {
                // Validate marks against max marks
                $total_marks_obtained = $theory_marks + $practical_marks;
                if ($total_marks_obtained > $exam['total_marks']) {
                    $errors[] = "Total marks cannot exceed maximum marks for this exam (" . $exam['total_marks'] . ").";
                }
                
                // Calculate percentage and determine grade
                $percentage = ($total_marks_obtained / $exam['total_marks']) * 100;
                $is_pass = ($total_marks_obtained >= $exam['passing_marks']) ? 1 : 0;
                
                // Get grade based on percentage
                $grade_query = "SELECT grade, gpa FROM grading_system WHERE ? BETWEEN min_percentage AND max_percentage";
                $grade_stmt = $conn->prepare($grade_query);
                if ($grade_stmt) {
                    $grade_stmt->bind_param("d", $percentage);
                    $grade_stmt->execute();
                    $grade_result = $grade_stmt->get_result();
                    
                    if ($grade_result->num_rows > 0) {
                        $grade_data = $grade_result->fetch_assoc();
                        $grade = $grade_data['grade'];
                        $gpa = $grade_data['gpa'];
                    } else {
                        $grade = 'F';
                        $gpa = 0.0;
                    }
                    $grade_stmt->close();
                } else {
                    $errors[] = "Database error: " . $conn->error;
                }
            }
            $exam_stmt->close();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
    }
    
    // Insert result if no errors
    if (empty($errors)) {
        $insert_query = "
            INSERT INTO results (student_id, subject_id, exam_id, theory_marks, practical_marks, 
                               grade, gpa, remarks, percentage, is_pass, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        
        $insert_stmt = $conn->prepare($insert_query);
        if ($insert_stmt) {
            $insert_stmt->bind_param("ssiiddsddii", $student_id, $subject_id, $exam_id, $theory_marks, $practical_marks, 
                                  $grade, $gpa, $remarks, $percentage, $is_pass, $teacher_id);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success'] = "Result added successfully.";
                header("Location: student_results.php?student_id=$student_id&subject_id=$subject_id");
                exit();
            } else {
                $_SESSION['error'] = "Failed to add result: " . $conn->error;
            }
            $insert_stmt->close();
        } else {
            $_SESSION['error'] = "Database error: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

// Get available exams for adding new results
$available_exams_query = "
    SELECT e.exam_id, e.exam_name, e.start_date, e.total_marks
    FROM exams e
    WHERE e.exam_id NOT IN (
        SELECT exam_id FROM results 
        WHERE student_id = ? AND subject_id = ?
    )
    AND e.is_active = 1
    ORDER BY e.start_date DESC
";

$available_exams_stmt = $conn->prepare($available_exams_query);
$available_exams = [];
if ($available_exams_stmt) {
    $available_exams_stmt->bind_param("ss", $student_id, $subject_id);
    $available_exams_stmt->execute();
    $available_exams_result = $available_exams_stmt->get_result();
    
    while ($exam = $available_exams_result->fetch_assoc()) {
        $available_exams[] = $exam;
    }
    $available_exams_stmt->close();
}

// Get subject details
$subject_query = "SELECT * FROM subjects WHERE subject_id = ?";
$subject_stmt = $conn->prepare($subject_query);
$subject = null;
if ($subject_stmt) {
    $subject_stmt->bind_param("s", $subject_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    $subject = $subject_result->fetch_assoc();
    $subject_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Results - Teacher Dashboard</title>
    <link rel="stylesheet" href="../css/tailwind.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/teacher_topbar.php'; ?>
    
    <div class="flex">
        <?php include 'includes/teacher_sidebar.php'; ?>
        
        <div class="w-full p-4 md:ml-64">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Student Results</h1>
                <p class="text-gray-600">
                    Viewing results for <?php echo htmlspecialchars($student['full_name']); ?> in <?php echo htmlspecialchars($student['subject_name']); ?>
                </p>
                <div class="mt-2">
                    <a href="grade_sheet.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Grade Sheets
                    </a>
                </div>
            </div>
            
            <!-- Display Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Student Info Card -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <div class="md:flex justify-between">
                    <div>
                        <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                        <p class="text-gray-600">Roll No: <?php echo htmlspecialchars($student['roll_number']); ?></p>
                        <p class="text-gray-600">Class: <?php echo htmlspecialchars($student['class_name']); ?> - Section: <?php echo htmlspecialchars($student['section']); ?></p>
                        <p class="text-gray-600">Subject: <?php echo htmlspecialchars($student['subject_name']); ?></p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <a href="edit_results.php?student_id=<?php echo $student_id; ?>&subject_id=<?php echo $subject_id; ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            <i class="fas fa-edit mr-2"></i> Edit Results
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Performance Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Overall Performance -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-lg font-semibold mb-3">Overall Performance</h3>
                    
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-600">Average Marks</p>
                            <?php 
                                $avg_color = '';
                                if ($average_marks >= 80) {
                                    $avg_color = 'text-green-600';
                                } elseif ($average_marks >= 60) {
                                    $avg_color = 'text-blue-600';
                                } elseif ($average_marks >= 40) {
                                    $avg_color = 'text-yellow-600';
                                } else {
                                    $avg_color = 'text-red-600';
                                }
                            ?>
                            <p class="text-2xl font-bold <?php echo $avg_color; ?>"><?php echo $average_marks; ?></p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-600">Average Percentage</p>
                            <p class="text-xl font-semibold <?php echo $avg_color; ?>"><?php echo $average_percentage; ?>%</p>
                        </div>
                        
                        <div class="flex justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Exams Taken</p>
                                <p class="text-lg font-semibold"><?php echo $exam_count; ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Pass/Fail</p>
                                <p class="text-lg font-semibold">
                                    <span class="text-green-600"><?php echo $pass_count; ?></span> / 
                                    <span class="text-red-600"><?php echo $fail_count; ?></span>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($progress !== null): ?>
                            <div>
                                <p class="text-sm text-gray-600">Progress (First to Last Exam)</p>
                                <?php if ($progress > 0): ?>
                                    <p class="text-lg font-semibold text-green-600">+<?php echo $progress; ?> <i class="fas fa-arrow-up"></i></p>
                                <?php elseif ($progress < 0): ?>
                                    <p class="text-lg font-semibold text-red-600"><?php echo $progress; ?> <i class="fas fa-arrow-down"></i></p>
                                <?php else: ?>
                                    <p class="text-lg font-semibold text-gray-600">No change</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Performance Analysis -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-lg font-semibold mb-3">Performance Analysis</h3>
                    
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-600">Highest Marks</p>
                            <p class="text-xl font-semibold text-green-600"><?php echo $highest_marks; ?></p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-600">Lowest Marks</p>
                            <p class="text-xl font-semibold text-red-600"><?php echo $exam_count > 0 ? $lowest_marks : 'N/A'; ?></p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-600">Consistency (Std. Deviation)</p>
                            <?php 
                                $consistency_text = '';
                                $consistency_color = '';
                                
                                if ($std_deviation < 5) {
                                    $consistency_text = 'Very Consistent';
                                    $consistency_color = 'text-green-600';
                                } elseif ($std_deviation < 10) {
                                    $consistency_text = 'Consistent';
                                    $consistency_color = 'text-blue-600';
                                } elseif ($std_deviation < 15) {
                                    $consistency_text = 'Somewhat Inconsistent';
                                    $consistency_color = 'text-yellow-600';
                                } else {
                                    $consistency_text = 'Highly Inconsistent';
                                    $consistency_color = 'text-red-600';
                                }
                            ?>
                            <p class="text-lg font-semibold <?php echo $consistency_color; ?>">
                                <?php echo $std_deviation; ?> (<?php echo $consistency_text; ?>)
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-600">Comparison with Class Average</p>
                            <?php 
                                $diff = $average_marks - $class_avg;
                                $diff_text = '';
                                $diff_color = '';
                                
                                if ($diff >= 10) {
                                    $diff_text = 'Well Above Average';
                                    $diff_color = 'text-green-600';
                                } elseif ($diff >= 5) {
                                    $diff_text = 'Above Average';
                                    $diff_color = 'text-blue-600';
                                } elseif ($diff >= -5) {
                                    $diff_text = 'Average';
                                    $diff_color = 'text-gray-600';
                                } elseif ($diff >= -10) {
                                    $diff_text = 'Below Average';
                                    $diff_color = 'text-yellow-600';
                                } else {
                                    $diff_text = 'Well Below Average';
                                    $diff_color = 'text-red-600';
                                }
                            ?>
                            <p class="text-lg font-semibold <?php echo $diff_color; ?>">
                                <?php echo $diff > 0 ? '+' . round($diff, 1) : round($diff, 1); ?> (<?php echo $diff_text; ?>)
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Subject Details & Recommendations -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-lg font-semibold mb-3">Subject Details</h3>
                    
                    <?php if ($subject): ?>
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Subject Information</p>
                            <p class="font-medium"><?php echo htmlspecialchars($subject['subject_name']); ?> (<?php echo htmlspecialchars($subject['subject_id']); ?>)</p>
                            <p class="text-sm text-gray-500">
                                Theory: <?php echo $subject['full_marks_theory']; ?> marks (Pass: <?php echo $subject['pass_marks_theory']; ?>)<br>
                                Practical: <?php echo $subject['full_marks_practical']; ?> marks (Pass: <?php echo $subject['pass_marks_practical']; ?>)
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Recommendations</p>
                        <ul class="mt-2 space-y-2 text-sm">
                            <?php
                                // Generate recommendations based on performance data
                                $recommendations = [];
                                
                                if ($average_percentage < 40) {
                                    $recommendations[] = "<li class='text-red-600'><i class='fas fa-exclamation-circle mr-1'></i> Requires immediate academic intervention</li>";
                                }
                                
                                if ($std_deviation > 15) {
                                    $recommendations[] = "<li class='text-yellow-600'><i class='fas fa-exclamation-triangle mr-1'></i> Work on consistency in performance</li>";
                                }
                                
                                if ($progress !== null && $progress < -5) {
                                    $recommendations[] = "<li class='text-red-600'><i class='fas fa-arrow-down mr-1'></i> Declining performance trend - needs attention</li>";
                                }
                                
                                if ($average_percentage >= 80) {
                                    $recommendations[] = "<li class='text-green-600'><i class='fas fa-star mr-1'></i> Excellent performance - consider advanced material</li>";
                                }
                                
                                if ($progress !== null && $progress > 10) {
                                    $recommendations[] = "<li class='text-green-600'><i class='fas fa-arrow-up mr-1'></i> Significant improvement - continue current approach</li>";
                                }
                                
                                if (empty($recommendations)) {
                                    $recommendations[] = "<li class='text-blue-600'><i class='fas fa-info-circle mr-1'></i> Performance is satisfactory</li>";
                                }
                                
                                echo implode("\n", $recommendations);
                            ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Performance Chart -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <h3 class="text-lg font-semibold mb-3">Performance Trend</h3>
                
                <?php if (count($results) > 1): ?>
                    <div style="height: 300px;">
                        <canvas id="performanceChart"></canvas>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('performanceChart').getContext('2d');
                            
                            // Prepare data for chart
                            const examDates = <?php 
                                $dates = array_map(function($result) {
                                    return date('M d, Y', strtotime($result['start_date']));
                                }, array_reverse($results));
                                echo json_encode($dates);
                            ?>;
                            
                            const studentMarks = <?php 
                                $marks = array_map(function($result) {
                                    return $result['theory_marks'] + $result['practical_marks'];
                                }, array_reverse($results));
                                echo json_encode($marks);
                            ?>;
                            
                            const passingMarks = <?php 
                                $passing = array_map(function($result) {
                                    return $result['passing_marks'];
                                }, array_reverse($results));
                                echo json_encode($passing);
                            ?>;
                            
                            const maxMarks = <?php 
                                $max = array_map(function($result) {
                                    return $result['total_marks'];
                                }, array_reverse($results));
                                echo json_encode($max);
                            ?>;
                            
                            const classAvg = <?php echo $class_avg; ?>;
                            
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: examDates,
                                    datasets: [
                                        {
                                            label: 'Student Marks',
                                            data: studentMarks,
                                            borderColor: 'rgb(59, 130, 246)',
                                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                            tension: 0.1,
                                            fill: true
                                        },
                                        {
                                            label: 'Passing Marks',
                                            data: passingMarks,
                                            borderColor: 'rgb(239, 68, 68)',
                                            borderWidth: 2,
                                            borderDash: [5, 5],
                                            pointRadius: 0,
                                            fill: false
                                        },
                                        {
                                            label: 'Class Average',
                                            data: Array(examDates.length).fill(classAvg),
                                            borderColor: 'rgb(16, 185, 129)',
                                            borderWidth: 2,
                                            borderDash: [3, 3],
                                            pointRadius: 0,
                                            fill: false
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: Math.max(...maxMarks) + 5,
                                            title: {
                                                display: true,
                                                text: 'Marks'
                                            }
                                        },
                                        x: {
                                            title: {
                                                display: true,
                                                text: 'Exam Date'
                                            }
                                        }
                                    },
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    let label = context.dataset.label || '';
                                                    if (label) {
                                                        label += ': ';
                                                    }
                                                    if (context.parsed.y !== null) {
                                                        label += context.parsed.y;
                                                    }
                                                    return label;
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        });
                    </script>
                <?php else: ?>
                    <div class="p-4 text-center text-gray-500">
                        <p>Not enough data to display performance trend. At least two exam results are required.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Results Table and Add Result Form -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Results Table -->
                <div class="md:col-span-2 bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-4 border-b">
                        <h3 class="text-lg font-semibold">Exam Results</h3>
                    </div>
                    
                    <?php if (empty($results)): ?>
                        <div class="p-6 text-center">
                            <p class="text-gray-500">No results found for this student in this subject.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Theory</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Practical</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($results as $result): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($result['exam_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $result['start_date'] ? date('d M Y', strtotime($result['start_date'])) : 'N/A'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo $result['theory_marks']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo $result['practical_marks']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                    $total_marks = $result['theory_marks'] + $result['practical_marks'];
                                                    $mark_color = '';
                                                    if ($result['is_pass']) {
                                                        $mark_color = 'text-green-600';
                                                    } else {
                                                        $mark_color = 'text-red-600';
                                                    }
                                                ?>
                                                <span class="<?php echo $mark_color; ?> font-medium">
                                                    <?php echo $total_marks; ?> / <?php echo $result['total_marks']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                    $grade_color = '';
                                                    if (in_array($result['grade'], ['A+', 'A', 'B+'])) {
                                                        $grade_color = 'bg-green-100 text-green-800';
                                                    } elseif (in_array($result['grade'], ['B', 'C+', 'C'])) {
                                                        $grade_color = 'bg-blue-100 text-blue-800';
                                                    } elseif ($result['grade'] === 'D') {
                                                        $grade_color = 'bg-yellow-100 text-yellow-800';
                                                    } else {
                                                        $grade_color = 'bg-red-100 text-red-800';
                                                    }
                                                ?>
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $grade_color; ?>">
                                                    <?php echo $result['grade']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php if ($result['is_pass']): ?>
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Pass</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Fail</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="edit_results.php?student_id=<?php echo $student_id; ?>&subject_id=<?php echo $subject_id; ?>&exam_id=<?php echo $result['exam_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Add Result Form -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-lg font-semibold mb-3">Add New Result</h3>
                    
                    <?php if (empty($available_exams)): ?>
                        <div class="p-4 text-center text-gray-500">
                            <p>No available exams found. All exams have results recorded for this student.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-1">Exam</label>
                                <select id="exam_id" name="exam_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    <option value="">Select Exam</option>
                                    <?php foreach ($available_exams as $exam): ?>
                                        <option value="<?php echo $exam['exam_id']; ?>">
                                            <?php echo htmlspecialchars($exam['exam_name']); ?> 
                                            <?php echo $exam['start_date'] ? '(' . date('d M Y', strtotime($exam['start_date'])) . ')' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="theory_marks" class="block text-sm font-medium text-gray-700 mb-1">Theory Marks</label>
                                <input type="number" id="theory_marks" name="theory_marks" step="0.01" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                <p class="text-xs text-gray-500 mt-1">Enter theory marks obtained by the student</p>
                            </div>
                            
                            <div class="mb-4">
                                <label for="practical_marks" class="block text-sm font-medium text-gray-700 mb-1">Practical Marks</label>
                                <input type="number" id="practical_marks" name="practical_marks" step="0.01" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                <p class="text-xs text-gray-500 mt-1">Enter practical marks obtained by the student</p>
                            </div>
                            
                            <div class="mb-4">
                                <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarks (Optional)</label>
                                <textarea id="remarks" name="remarks" rows="3" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="add_result" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                    <i class="fas fa-plus mr-2"></i> Add Result
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <h3 class="text-lg font-semibold mb-3">Quick Links</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="edit_results.php?class_id=<?php echo $student['class_id']; ?>&subject_id=<?php echo $subject_id; ?>" class="flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                        <div class="bg-blue-500 text-white p-2 rounded-full mr-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <p class="font-medium">Class Results</p>
                            <p class="text-sm text-gray-600">Edit results for entire class</p>
                        </div>
                    </a>
                    
                    <a href="grade_sheet.php" class="flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                        <div class="bg-green-500 text-white p-2 rounded-full mr-3">
                            <i class="fas fa-table"></i>
                        </div>
                        <div>
                            <p class="font-medium">Grade Sheets</p>
                            <p class="text-sm text-gray-600">View all grade sheets</p>
                        </div>
                    </a>
                    
                    <a href="teacher_dashboard.php" class="flex items-center p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                        <div class="bg-purple-500 text-white p-2 rounded-full mr-3">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div>
                            <p class="font-medium">Dashboard</p>
                            <p class="text-sm text-gray-600">Return to dashboard</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
</body>
</html>
