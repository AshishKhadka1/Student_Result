<?php
session_start();
require_once '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get teacher information
$stmt = $conn->prepare("SELECT t.*, u.full_name 
                       FROM teachers t 
                       JOIN users u ON t.user_id = u.user_id 
                       WHERE t.user_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // If teacher record doesn't exist, redirect to login
    header("Location: ../login.php");
    exit();
}

$teacher = $result->fetch_assoc();
$stmt->close();

// Check if student_id is provided
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($student_id <= 0) {
    $_SESSION['error'] = "Invalid student ID.";
    header("Location: teacher_dashboard.php");
    exit();
}

// Get student information
$stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, c.academic_year, u.full_name, u.email, u.phone, u.status, u.created_at 
                       FROM students s 
                       JOIN classes c ON s.class_id = c.class_id 
                       JOIN users u ON s.user_id = u.user_id 
                       WHERE s.student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Student not found.";
    header("Location: teacher_dashboard.php");
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

// Check if teacher is assigned to this student's class
$is_class_teacher = false;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM teachersubjects 
                       WHERE teacher_id = ? AND class_id = ?");
$stmt->bind_param("ii", $teacher['teacher_id'], $student['class_id']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$is_class_teacher = ($row['count'] > 0);
$stmt->close();

if (!$is_class_teacher) {
    $_SESSION['error'] = "You are not assigned to this student's class.";
    header("Location: teacher_dashboard.php");
    exit();
}

// Get subjects taught by this teacher to this student
$stmt = $conn->prepare("SELECT ts.*, s.subject_name, s.subject_code 
                       FROM teachersubjects ts 
                       JOIN subjects s ON ts.subject_id = s.subject_id 
                       WHERE ts.teacher_id = ? AND ts.class_id = ?");
$stmt->bind_param("ii", $teacher['teacher_id'], $student['class_id']);
$stmt->execute();
$result = $stmt->get_result();
$teacher_subjects = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all subjects for this class
$stmt = $conn->prepare("SELECT * FROM subjects WHERE class_id = ?");
$stmt->bind_param("i", $student['class_id']);
$stmt->execute();
$result = $stmt->get_result();
$all_subjects = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get recent results for this student
$stmt = $conn->prepare("SELECT r.*, s.subject_name, e.exam_name 
                       FROM results r 
                       JOIN subjects s ON r.subject_id = s.subject_id 
                       JOIN exams e ON r.exam_id = e.exam_id 
                       WHERE r.student_id = ? 
                       ORDER BY r.created_at DESC LIMIT 10");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_results = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get attendance data if available
$attendance_data = [
    'present' => 0,
    'absent' => 0,
    'leave' => 0,
    'total_days' => 0,
    'percentage' => 0
];

// Check if attendance table exists
$table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
$attendance_table_exists = ($table_check && $table_check->num_rows > 0);

if ($attendance_table_exists) {
    $stmt = $conn->prepare("SELECT 
                          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                          SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                          SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count,
                          COUNT(*) as total_days
                        FROM attendance 
                        WHERE student_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $attendance_data['present'] = $row['present'] ?? 0;
            $attendance_data['absent'] = $row['absent'] ?? 0;
            $attendance_data['leave'] = $row['leave_count'] ?? 0;
            $attendance_data['total_days'] = $row['total_days'] ?? 0;
            
            if ($attendance_data['total_days'] > 0) {
                $attendance_data['percentage'] = round(($attendance_data['present'] / $attendance_data['total_days']) * 100, 2);
            }
        }
        $stmt->close();
    }
}

// Calculate overall performance
$overall_performance = [
    'total_subjects' => count($all_subjects),
    'subjects_with_results' => 0,
    'total_marks' => 0,
    'obtained_marks' => 0,
    'average_percentage' => 0,
    'average_grade' => 'N/A',
    'average_gpa' => 0,
    'pass_count' => 0,
    'fail_count' => 0
];

// Get the latest exam results for each subject
$stmt = $conn->prepare("
    SELECT r.*, s.subject_name, s.subject_code, e.exam_name
    FROM results r
    JOIN subjects s ON r.subject_id = s.subject_id
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE r.student_id = ?
    AND r.exam_id = (
        SELECT MAX(r2.exam_id) 
        FROM results r2 
        WHERE r2.student_id = r.student_id 
        AND r2.subject_id = r.subject_id
    )
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$latest_results = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!empty($latest_results)) {
    $overall_performance['subjects_with_results'] = count($latest_results);
    $total_gpa = 0;
    
    foreach ($latest_results as $result) {
        $theory_marks = $result['theory_marks'] ?? $result['marks'] ?? 0;
        $practical_marks = $result['practical_marks'] ?? 0;
        $total_subject_marks = $theory_marks + $practical_marks;
        $subject_max_marks = $result['total_marks'] ?? 100;
        
        // Calculate GPA based on grade if not provided
        $gpa = 0;
        switch ($result['grade']) {
            case 'A+': $gpa = 4.0; break;
            case 'A': $gpa = 3.7; break;
            case 'B+': $gpa = 3.3; break;
            case 'B': $gpa = 3.0; break;
            case 'C+': $gpa = 2.7; break;
            case 'C': $gpa = 2.3; break;
            case 'D': $gpa = 2.0; break;
            case 'F': $gpa = 0.0; break;
            default: $gpa = 0.0;
        }
        $total_gpa += $gpa;
        
        $overall_performance['obtained_marks'] += $total_subject_marks;
        $overall_performance['total_marks'] += $subject_max_marks;
        
        if ($result['status'] == 'pass' || $result['grade'] != 'F') {
            $overall_performance['pass_count']++;
        } else {
            $overall_performance['fail_count']++;
        }
    }
    
    if ($overall_performance['total_marks'] > 0) {
        $overall_performance['average_percentage'] = ($overall_performance['obtained_marks'] / $overall_performance['total_marks']) * 100;
        $overall_performance['average_gpa'] = $total_gpa / $overall_performance['subjects_with_results'];
        
        // Determine average grade
        if ($overall_performance['average_percentage'] >= 90) {
            $overall_performance['average_grade'] = 'A+';
        } elseif ($overall_performance['average_percentage'] >= 80) {
            $overall_performance['average_grade'] = 'A';
        } elseif ($overall_performance['average_percentage'] >= 70) {
            $overall_performance['average_grade'] = 'B+';
        } elseif ($overall_performance['average_percentage'] >= 60) {
            $overall_performance['average_grade'] = 'B';
        } elseif ($overall_performance['average_percentage'] >= 50) {
            $overall_performance['average_grade'] = 'C+';
        } elseif ($overall_performance['average_percentage'] >= 40) {
            $overall_performance['average_grade'] = 'C';
        } elseif ($overall_performance['average_percentage'] >= 33) {
            $overall_performance['average_grade'] = 'D';
        } else {
            $overall_performance['average_grade'] = 'F';
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | Result Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'includes/teacher_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include 'includes/teacher_topbar.php'; ?>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Page Header -->
                        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">
                                        Student Profile: <?php echo htmlspecialchars($student['full_name']); ?>
                                    </h2>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Class: <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?> | 
                                        Roll Number: <?php echo htmlspecialchars($student['roll_number']); ?> | 
                                        Academic Year: <?php echo htmlspecialchars($student['academic_year']); ?>
                                    </p>
                                </div>
                                <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                                    <a href="view_students.php?class_id=<?php echo $student['class_id']; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <i class="fas fa-arrow-left mr-2"></i> Back to Students
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Student Information -->
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-6">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Personal Information</h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <div class="flex items-center mb-4">
                                            <div class="h-20 w-20 rounded-full bg-blue-600 flex items-center justify-center text-white text-2xl font-bold">
                                                <?php echo substr($student['full_name'], 0, 1); ?>
                                            </div>
                                            <div class="ml-4">
                                                <h4 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></h4>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($student['email']); ?></p>
                                                <p class="text-sm text-gray-500">
                                                    <?php if ($student['status'] == 'active'): ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                            Inactive
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Phone</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['phone']) ? htmlspecialchars($student['phone']) : 'Not provided'; ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Gender</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['gender']) ? ucfirst(htmlspecialchars($student['gender'])) : 'Not specified'; ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Date of Birth</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['date_of_birth']) ? date('F j, Y', strtotime($student['date_of_birth'])) : 'Not provided'; ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Address</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['address']) ? htmlspecialchars($student['address']) : 'Not provided'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <h4 class="text-md font-medium text-gray-900 mb-3">Academic Details</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Student ID</p>
                                                <p class="text-base text-gray-900"><?php echo htmlspecialchars($student['student_id']); ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Registration Number</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['registration_number']) ? htmlspecialchars($student['registration_number']) : 'Not assigned'; ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Batch Year</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['batch_year']) ? htmlspecialchars($student['batch_year']) : 'Not specified'; ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Admission Date</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['admission_date']) ? date('F j, Y', strtotime($student['admission_date'])) : 'Not provided'; ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Account Created</p>
                                                <p class="text-base text-gray-900"><?php echo date('F j, Y', strtotime($student['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Performance Overview -->
                        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Performance Overview</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                                    <h4 class="text-sm font-medium text-blue-800">Average Grade</h4>
                                    <p class="text-2xl font-bold text-blue-900"><?php echo $overall_performance['average_grade']; ?></p>
                                    <p class="text-xs text-blue-700 mt-1">GPA: <?php echo number_format($overall_performance['average_gpa'], 2); ?></p>
                                </div>
                                
                                <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                                    <h4 class="text-sm font-medium text-green-800">Average Percentage</h4>
                                    <p class="text-2xl font-bold text-green-900"><?php echo number_format($overall_performance['average_percentage'], 1); ?>%</p>
                                    <p class="text-xs text-green-700 mt-1">
                                        <?php echo $overall_performance['obtained_marks']; ?> / <?php echo $overall_performance['total_marks']; ?> marks
                                    </p>
                                </div>
                                
                                <div class="bg-purple-50 p-4 rounded-lg border border-purple-100">
                                    <h4 class="text-sm font-medium text-purple-800">Subjects Passed</h4>
                                    <p class="text-2xl font-bold text-purple-900">
                                        <?php echo $overall_performance['pass_count']; ?> / <?php echo $overall_performance['subjects_with_results']; ?>
                                    </p>
                                    <p class="text-xs text-purple-700 mt-1">
                                        <?php echo $overall_performance['fail_count']; ?> subject(s) need improvement
                                    </p>
                                </div>
                                
                                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-100">
                                    <h4 class="text-sm font-medium text-yellow-800">Attendance</h4>
                                    <p class="text-2xl font-bold text-yellow-900"><?php echo $attendance_data['percentage']; ?>%</p>
                                    <p class="text-xs text-yellow-700 mt-1">
                                        Present: <?php echo $attendance_data['present']; ?> / <?php echo $attendance_data['total_days']; ?> days
                                    </p>
                                </div>
                            </div>
                            
                            <?php if (!empty($latest_results)): ?>
                                <div class="mt-6">
                                    <h4 class="text-md font-medium text-gray-900 mb-3">Subject Performance</h4>
                                    <div class="h-64">
                                        <canvas id="subjectPerformanceChart"></canvas>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Subject Results -->
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-6">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Subject Results</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Latest Exam</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($latest_results)): ?>
                                            <tr>
                                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No results found for this student.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($latest_results as $result): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($result['subject_name']); ?>
                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($result['subject_code']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($result['exam_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php 
                                                        $theory = $result['theory_marks'] ?? $result['marks'] ?? 0;
                                                        $practical = $result['practical_marks'] ?? 0;
                                                        $total = $theory + $practical;
                                                        $max = $result['total_marks'] ?? 100;
                                                        echo $total . ' / ' . $max;
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            <?php 
                                                            $grade = $result['grade'];
                                                            if ($grade == 'A+' || $grade == 'A') {
                                                                echo 'bg-green-100 text-green-800';
                                                            } elseif ($grade == 'B+' || $grade == 'B') {
                                                                echo 'bg-blue-100 text-blue-800';
                                                            } elseif ($grade == 'C+' || $grade == 'C') {
                                                                echo 'bg-yellow-100 text-yellow-800';
                                                            } elseif ($grade == 'D+' || $grade == 'D') {
                                                                echo 'bg-orange-100 text-orange-800';
                                                            } else {
                                                                echo 'bg-red-100 text-red-800';
                                                            }
                                                            ?>">
                                                            <?php echo htmlspecialchars($result['grade']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($result['status'] == 'pass' || $result['grade'] != 'F'): ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                Pass
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                                Fail
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php
                                                        // Check if this subject is taught by the current teacher
                                                        $is_teacher_subject = false;
                                                        foreach ($teacher_subjects as $subject) {
                                                            if ($subject['subject_id'] == $result['subject_id']) {
                                                                $is_teacher_subject = true;
                                                                break;
                                                            }
                                                        }
                                                        
                                                        if ($is_teacher_subject):
                                                        ?>
                                                            <a href="student_results.php?student_id=<?php echo $student_id; ?>&subject_id=<?php echo $result['subject_id']; ?>&class_id=<?php echo $student['class_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                                <i class="fas fa-chart-bar"></i> View Details
                                                            </a>
                                                            <a href="add_result.php?student_id=<?php echo $student_id; ?>&subject_id=<?php echo $result['subject_id']; ?>&class_id=<?php echo $student['class_id']; ?>" class="text-green-600 hover:text-green-900">
                                                                <i class="fas fa-plus"></i> Add Result
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">Not your subject</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Attendance Overview -->
                        <?php if ($attendance_table_exists && $attendance_data['total_days'] > 0): ?>
                            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Attendance Overview</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                                        <div class="text-3xl font-bold text-blue-600"><?php echo $attendance_data['present']; ?></div>
                                        <div class="text-sm font-medium text-blue-800">Days Present</div>
                                    </div>
                                    
                                    <div class="bg-red-50 rounded-lg p-4 text-center">
                                        <div class="text-3xl font-bold text-red-600"><?php echo $attendance_data['absent']; ?></div>
                                        <div class="text-sm font-medium text-red-800">Days Absent</div>
                                    </div>
                                    
                                    <div class="bg-yellow-50 rounded-lg p-4 text-center">
                                        <div class="text-3xl font-bold text-yellow-600"><?php echo $attendance_data['leave']; ?></div>
                                        <div class="text-sm font-medium text-yellow-800">Days on Leave</div>
                                    </div>
                                    
                                    <div class="bg-green-50 rounded-lg p-4 text-center">
                                        <div class="text-3xl font-bold text-green-600"><?php echo $attendance_data['percentage']; ?>%</div>
                                        <div class="text-sm font-medium text-green-800">Attendance Rate</div>
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <h4 class="text-md font-medium text-gray-700 mb-2">Attendance Percentage</h4>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <?php 
                                        $color_class = 'bg-red-600';
                                        if ($attendance_data['percentage'] >= 90) {
                                            $color_class = 'bg-green-600';
                                        } elseif ($attendance_data['percentage'] >= 75) {
                                            $color_class = 'bg-blue-600';
                                        } elseif ($attendance_data['percentage'] >= 60) {
                                            $color_class = 'bg-yellow-600';
                                        }
                                        ?>
                                        <div class="<?php echo $color_class; ?> h-2.5 rounded-full" style="width: <?php echo $attendance_data['percentage']; ?>%"></div>
                                    </div>
                                    
                                    <?php if ($attendance_data['percentage'] < 75): ?>
                                        <div class="mt-2 bg-red-50 border-l-4 border-red-400 p-4">
                                            <div class="flex">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm text-red-700">
                                                        This student's attendance is below the required 75%. This may affect their academic performance.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Recent Results -->
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-6">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Recent Results</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($recent_results)): ?>
                                            <tr>
                                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No recent results found for this student.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_results as $result): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($result['subject_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($result['exam_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php 
                                                        $theory = $result['theory_marks'] ?? $result['marks'] ?? 0;
                                                        $practical = $result['practical_marks'] ?? 0;
                                                        $total = $theory + $practical;
                                                        $max = $result['total_marks'] ?? 100;
                                                        echo $total . ' / ' . $max;
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            <?php 
                                                            $grade = $result['grade'];
                                                            if ($grade == 'A+' || $grade == 'A') {
                                                                echo 'bg-green-100 text-green-800';
                                                            } elseif ($grade == 'B+' || $grade == 'B') {
                                                                echo 'bg-blue-100 text-blue-800';
                                                            } elseif ($grade == 'C+' || $grade == 'C') {
                                                                echo 'bg-yellow-100 text-yellow-800';
                                                            } elseif ($grade == 'D+' || $grade == 'D') {
                                                                echo 'bg-orange-100 text-orange-800';
                                                            } else {
                                                                echo 'bg-red-100 text-red-800';
                                                            }
                                                            ?>">
                                                            <?php echo htmlspecialchars($result['grade']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('M d, Y', strtotime($result['created_at'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php
                                                        // Check if this subject is taught by the current teacher
                                                        $is_teacher_subject = false;
                                                        foreach ($teacher_subjects as $subject) {
                                                            if ($subject['subject_id'] == $result['subject_id']) {
                                                                $is_teacher_subject = true;
                                                                break;
                                                            }
                                                        }
                                                        
                                                        if ($is_teacher_subject):
                                                        ?>
                                                            <a href="edit_result.php?result_id=<?php echo $result['result_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">Not your subject</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Teacher's Notes -->
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Teacher's Notes</h3>
                            </div>
                            <div class="p-6">
                                <form action="save_student_notes.php" method="POST" class="space-y-4">
                                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                    
                                    <div>
                                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Add Notes about this Student</label>
                                        <textarea id="notes" name="notes" rows="4" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Enter your observations, concerns, or recommendations for this student..."></textarea>
                                    </div>
                                    
                                    <div>
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-save mr-2"></i> Save Notes
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="mt-6">
                                    <h4 class="text-md font-medium text-gray-700 mb-2">Previous Notes</h4>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-sm text-gray-500 italic">No previous notes found for this student.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php if (!empty($latest_results)): ?>
    <script>
        // Subject Performance Chart
        const ctx = document.getElementById('subjectPerformanceChart').getContext('2d');
        const subjectPerformanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    foreach ($latest_results as $result) {
                        echo "'" . htmlspecialchars($result['subject_name']) . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Percentage',
                    data: [
                        <?php 
                        foreach ($latest_results as $result) {
                            $theory = $result['theory_marks'] ?? $result['marks'] ?? 0;
                            $practical = $result['practical_marks'] ?? 0;
                            $total = $theory + $practical;
                            $max = $result['total_marks'] ?? 100;
                            echo number_format(($total / $max) * 100, 1) . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Percentage (%)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Percentage: ${context.parsed.y}%`;
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
