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
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// Verify that this teacher teaches this student in this subject
$verify_query = "
    SELECT 1
    FROM Students s
    JOIN Sections sec ON s.section_id = sec.id
    JOIN TeacherSubjects ts ON sec.id = ts.section_id
    WHERE s.id = ? AND ts.teacher_id = ? AND ts.subject_id = ?
";

$verify_stmt = $conn->prepare($verify_query);
if ($verify_stmt === false) {
    die("Error preparing verification statement: " . $conn->error);
}

$verify_stmt->bind_param("iii", $student_id, $teacher_id, $subject_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $_SESSION['error'] = "You are not authorized to view this student's results.";
    header("Location: view_students.php");
    exit();
}

// Get student details
$student_query = "
    SELECT s.id, s.roll_number, u.name, c.name AS class_name, sec.name AS section_name, 
           sub.name AS subject_name, sub.id AS subject_id
    FROM Students s
    JOIN Users u ON s.user_id = u.id
    JOIN Sections sec ON s.section_id = sec.id
    JOIN Classes c ON sec.class_id = c.id
    JOIN TeacherSubjects ts ON sec.id = ts.section_id
    JOIN Subjects sub ON ts.subject_id = sub.id
    WHERE s.id = ? AND ts.teacher_id = ? AND ts.subject_id = ?
";

$student_stmt = $conn->prepare($student_query);
if ($student_stmt === false) {
    die("Error preparing student statement: " . $conn->error);
}

$student_stmt->bind_param("iii", $student_id, $teacher_id, $subject_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();

if (!$student) {
    $_SESSION['error'] = "Student not found.";
    header("Location: view_students.php");
    exit();
}

// Get all results for this student in this subject
$results_query = "
    SELECT sr.id, sr.marks, sr.grade, sr.remarks, e.name AS exam_name, e.date AS exam_date, 
           e.max_marks, e.passing_marks
    FROM StudentResults sr
    JOIN Exams e ON sr.exam_id = e.id
    WHERE sr.student_id = ? AND sr.subject_id = ?
    ORDER BY e.date DESC
";

$results_stmt = $conn->prepare($results_query);
if ($results_stmt === false) {
    die("Error preparing results statement: " . $conn->error);
}

$results_stmt->bind_param("ii", $student_id, $subject_id);
$results_stmt->execute();
$results_result = $results_stmt->get_result();

$results = [];
while ($result = $results_result->fetch_assoc()) {
    $results[] = $result;
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
    $total_marks += $result['marks'];
    $total_max_marks += $result['max_marks'];
    
    if ($result['marks'] >= $result['passing_marks']) {
        $pass_count++;
    } else {
        $fail_count++;
    }
    
    if ($index === $exam_count - 1) {
        $first_exam_marks = $result['marks'];
    }
    
    if ($index === 0) {
        $last_exam_marks = $result['marks'];
    }
    
    if ($result['marks'] > $highest_marks) {
        $highest_marks = $result['marks'];
    }
    
    if ($result['marks'] < $lowest_marks) {
        $lowest_marks = $result['marks'];
    }
    
    $marks_array[] = $result['marks'];
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
    SELECT AVG(sr.marks) AS class_avg
    FROM StudentResults sr
    JOIN Students s ON sr.student_id = s.id
    JOIN Sections sec ON s.section_id = sec.id
    WHERE sec.id = (SELECT section_id FROM Students WHERE id = ?) 
    AND sr.subject_id = ?
";

$class_avg_stmt = $conn->prepare($class_avg_query);
if ($class_avg_stmt === false) {
    die("Error preparing class average statement: " . $conn->error);
}

$class_avg_stmt->bind_param("ii", $student_id, $subject_id);
$class_avg_stmt->execute();
$class_avg_result = $class_avg_stmt->get_result();
$class_avg_data = $class_avg_result->fetch_assoc();
$class_avg = $class_avg_data['class_avg'] ? round($class_avg_data['class_avg'], 1) : 0;

// Get attendance data if available
$attendance_query = "
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) AS present_count,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) AS absent_count,
        COUNT(*) AS total_classes
    FROM Attendance
    WHERE student_id = ? AND subject_id = ?
";

$attendance_stmt = $conn->prepare($attendance_query);
$attendance = null;
if ($attendance_stmt !== false) {
    $attendance_stmt->bind_param("ii", $student_id, $subject_id);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    $attendance = $attendance_result->fetch_assoc();
    
    if ($attendance['total_classes'] > 0) {
        $attendance['percentage'] = round(($attendance['present_count'] / $attendance['total_classes']) * 100, 1);
    } else {
        $attendance['percentage'] = null;
    }
}

// Process form submission for adding a new result
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_result'])) {
    $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
    $marks = isset($_POST['marks']) ? floatval($_POST['marks']) : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate inputs
    $errors = [];
    
    if ($exam_id <= 0) {
        $errors[] = "Please select a valid exam.";
    }
    
    if ($marks < 0) {
        $errors[] = "Marks cannot be negative.";
    }
    
    // Check if result already exists
    $check_query = "
        SELECT id FROM StudentResults 
        WHERE student_id = ? AND subject_id = ? AND exam_id = ?
    ";
    
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt === false) {
        die("Error preparing check statement: " . $conn->error);
    }
    
    $check_stmt->bind_param("iii", $student_id, $subject_id, $exam_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Result for this exam already exists. Please edit the existing result instead.";
    }
    
    // Get exam details for grade calculation
    $exam_query = "SELECT max_marks, passing_marks FROM Exams WHERE id = ?";
    $exam_stmt = $conn->prepare($exam_query);
    if ($exam_stmt === false) {
        die("Error preparing exam statement: " . $conn->error);
    }
    
    $exam_stmt->bind_param("i", $exam_id);
    $exam_stmt->execute();
    $exam_result = $exam_stmt->get_result();
    $exam = $exam_result->fetch_assoc();
    
    if (!$exam) {
        $errors[] = "Invalid exam selected.";
    } else {
        // Validate marks against max marks
        if ($marks > $exam['max_marks']) {
            $errors[] = "Marks cannot exceed maximum marks for this exam (" . $exam['max_marks'] . ").";
        }
        
        // Calculate grade based on marks
        $percentage = ($marks / $exam['max_marks']) * 100;
        
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
    }
    
    // Insert result if no errors
    if (empty($errors)) {
        $insert_query = "
            INSERT INTO StudentResults (student_id, subject_id, exam_id, marks, grade, remarks)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        $insert_stmt = $conn->prepare($insert_query);
        if ($insert_stmt === false) {
            die("Error preparing insert statement: " . $conn->error);
        }
        
        $insert_stmt->bind_param("iiidss", $student_id, $subject_id, $exam_id, $marks, $grade, $remarks);
        
        if ($insert_stmt->execute()) {
            $_SESSION['success'] = "Result added successfully.";
            header("Location: student_results.php?student_id=$student_id&subject_id=$subject_id");
            exit();
        } else {
            $_SESSION['error'] = "Failed to add result: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

// Get available exams for adding new results
$exams_query = "
    SELECT e.id, e.name, e.date, e.max_marks
    FROM Exams e
    WHERE e.id NOT IN (
        SELECT exam_id FROM StudentResults 
        WHERE student_id = ? AND subject_id = ?
    )
    ORDER BY e.date DESC
";

$exams_stmt = $conn->prepare($exams_query);
if ($exams_stmt === false) {
    die("Error preparing exams statement: " . $conn->error);
}

$exams_stmt->bind_param("ii", $student_id, $subject_id);
$exams_stmt->execute();
$exams_result = $exams_stmt->get_result();

$available_exams = [];
while ($exam = $exams_result->fetch_assoc()) {
    $available_exams[] = $exam;
}

// Get teacher notes for this student
$notes_query = "
    SELECT n.id, n.note, n.created_at, u.name AS teacher_name
    FROM StudentNotes n
    JOIN Users u ON n.teacher_id = u.id
    WHERE n.student_id = ? AND n.subject_id = ?
    ORDER BY n.created_at DESC
";

$notes_stmt = $conn->prepare($notes_query);
$notes = [];

if ($notes_stmt !== false) {
    $notes_stmt->bind_param("ii", $student_id, $subject_id);
    $notes_stmt->execute();
    $notes_result = $notes_stmt->get_result();
    
    while ($note = $notes_result->fetch_assoc()) {
        $notes[] = $note;
    }
} else {
    // StudentNotes table might not exist
    // Create the table
    $create_notes_table = "
        CREATE TABLE IF NOT EXISTS StudentNotes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            note TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES Students(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES Users(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES Subjects(id) ON DELETE CASCADE
        )
    ";
    
    if ($conn->query($create_notes_table) === TRUE) {
        // Table created successfully
    } else {
        // Error creating table
    }
}

// Process form submission for adding a new note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note_text = isset($_POST['note_text']) ? trim($_POST['note_text']) : '';
    
    // Validate inputs
    $errors = [];
    
    if (empty($note_text)) {
        $errors[] = "Note text cannot be empty.";
    }
    
    // Insert note if no errors
    if (empty($errors)) {
        $insert_note_query = "
            INSERT INTO StudentNotes (student_id, teacher_id, subject_id, note)
            VALUES (?, ?, ?, ?)
        ";
        
        $insert_note_stmt = $conn->prepare($insert_note_query);
        if ($insert_note_stmt === false) {
            die("Error preparing insert note statement: " . $conn->error);
        }
        
        $insert_note_stmt->bind_param("iiis", $student_id, $teacher_id, $subject_id, $note_text);
        
        if ($insert_note_stmt->execute()) {
            $_SESSION['success'] = "Note added successfully.";
            header("Location: student_results.php?student_id=$student_id&subject_id=$subject_id");
            exit();
        } else {
            $_SESSION['error'] = "Failed to add note: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
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
                    Viewing results for <?php echo htmlspecialchars($student['name']); ?> in <?php echo htmlspecialchars($student['subject_name']); ?>
                </p>
                <div class="mt-2">
                    <a href="view_students.php?class_id=<?php echo $student['class_id']; ?>&subject_id=<?php echo $student['subject_id']; ?>" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Students List
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
                        <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($student['name']); ?></h2>
                        <p class="text-gray-600">Roll No: <?php echo htmlspecialchars($student['roll_number']); ?></p>
                        <p class="text-gray-600">Class: <?php echo htmlspecialchars($student['class_name']); ?> - Section: <?php echo htmlspecialchars($student['section_name']); ?></p>
                        <p class="text-gray-600">Subject: <?php echo htmlspecialchars($student['subject_name']); ?></p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <a href="student_profile.php?student_id=<?php echo $student_id; ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            <i class="fas fa-user mr-2"></i> View Full Profile
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
                            <p class="text-xl font-semibold text-red-600"><?php echo $lowest_marks; ?></p>
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
                
                <!-- Attendance & Recommendations -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-lg font-semibold mb-3">Attendance & Recommendations</h3>
                    
                    <?php if ($attendance !== null && $attendance['total_classes'] > 0): ?>
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Attendance Rate</p>
                            <?php 
                                $att_color = '';
                                if ($attendance['percentage'] >= 90) {
                                    $att_color = 'text-green-600';
                                } elseif ($attendance['percentage'] >= 75) {
                                    $att_color = 'text-blue-600';
                                } elseif ($attendance['percentage'] >= 60) {
                                    $att_color = 'text-yellow-600';
                                } else {
                                    $att_color = 'text-red-600';
                                }
                            ?>
                            <p class="text-xl font-semibold <?php echo $att_color; ?>"><?php echo $attendance['percentage']; ?>%</p>
                            <p class="text-sm text-gray-500">
                                Present: <?php echo $attendance['present_count']; ?> / 
                                Absent: <?php echo $attendance['absent_count']; ?> / 
                                Total: <?php echo $attendance['total_classes']; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Attendance Data</p>
                            <p class="text-gray-500">No attendance data available</p>
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
                                
                                if ($attendance !== null && $attendance['percentage'] < 75) {
                                    $recommendations[] = "<li class='text-red-600'><i class='fas fa-calendar-times mr-1'></i> Poor attendance affecting performance</li>";
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
                                    return date('M d, Y', strtotime($result['exam_date']));
                                }, array_reverse($results));
                                echo json_encode($dates);
                            ?>;
                            
                            const studentMarks = <?php 
                                $marks = array_map(function($result) {
                                    return $result['marks'];
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
                                    return $result['max_marks'];
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
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
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
                                                <?php echo date('d M Y', strtotime($result['exam_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                    $mark_color = '';
                                                    if ($result['marks'] >= $result['passing_marks']) {
                                                        $mark_color = 'text-green-600';
                                                    } else {
                                                        $mark_color = 'text-red-600';
                                                    }
                                                ?>
                                                <span class="<?php echo $mark_color; ?> font-medium">
                                                    <?php echo $result['marks']; ?> / <?php echo $result['max_marks']; ?>
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
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo !empty($result['remarks']) ? htmlspecialchars($result['remarks']) : '-'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="edit_result.php?id=<?php echo $result['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete_result.php?id=<?php echo $result['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this result?');">
                                                    <i class="fas fa-trash"></i>
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
                                        <option value="<?php echo $exam['id']; ?>">
                                            <?php echo htmlspecialchars($exam['name']); ?> (<?php echo date('d M Y', strtotime($exam['date'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="marks" class="block text-sm font-medium text-gray-700 mb-1">Marks</label>
                                <input type="number" id="marks" name="marks" step="0.01" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                <p class="text-xs text-gray-500 mt-1">Enter marks obtained by the student</p>
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
            
            <!-- Teacher Notes -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <h3 class="text-lg font-semibold mb-3">Teacher Notes</h3>
                
                <div class="mb-4">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="note_text" class="block text-sm font-medium text-gray-700 mb-1">Add a Note</label>
                            <textarea id="note_text" name="note_text" rows="3" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required></textarea>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="add_note" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                <i class="fas fa-plus mr-2"></i> Add Note
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php if (empty($notes)): ?>
                    <div class="p-4 text-center text-gray-500">
                        <p>No notes found for this student in this subject.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($notes as $note): ?>
                            <div class="border rounded-lg p-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($note['teacher_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('d M Y, h:i A', strtotime($note['created_at'])); ?></p>
                                    </div>
                                    <div>
                                        <a href="save_student_notes.php?action=delete&id=<?php echo $note['id']; ?>&student_id=<?php echo $student_id; ?>&subject_id=<?php echo $subject_id; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this note?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
</body>
</html>
