<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection with error handling
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = $_SESSION['user_id'];

// Get student details with error handling
function getStudentDetails($conn, $user_id) {
    $student = null;
    try {
        $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, c.academic_year, u.full_name, u.email, u.phone, u.status 
                              FROM students s 
                              JOIN classes c ON s.class_id = c.class_id 
                              JOIN users u ON s.user_id = u.user_id 
                              WHERE s.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching student details: " . $e->getMessage());
    }
    return $student;
}

// Get subjects for the student's class with error handling
function getSubjects($conn, $academic_year) {
    $subjects = [];
    try {
        $stmt = $conn->prepare("SELECT DISTINCT s.* FROM subjects s 
                              JOIN teachersubjects ts ON ts.subject_id = s.subject_id 
                              WHERE ts.academic_year = ?
                              ORDER BY s.subject_name");
        $stmt->bind_param("s", $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching subjects: " . $e->getMessage());
    }
    return $subjects;
}

// Get upcoming exams with error handling
function getUpcomingExams($conn, $class_id) {
    $upcoming_exams = [];
    try {
        // Check if exam_date or start_date is used in the database
        $date_column = 'exam_date';
        $result = $conn->query("SHOW COLUMNS FROM exams LIKE 'exam_date'");
        if ($result->num_rows == 0) {
            $date_column = 'start_date';
        }
        
        $stmt = $conn->prepare("SELECT e.*, c.class_name, c.section FROM exams e 
                              JOIN classes c ON e.class_id = c.class_id 
                              WHERE e.class_id = ? AND e.status = 'upcoming' 
                              ORDER BY e.$date_column ASC LIMIT 5");
        
        // Check if prepare statement failed
        if ($stmt === false) {
            error_log("Prepare statement failed: " . $conn->error);
            return $upcoming_exams;
        }
        
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $upcoming_exams[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching upcoming exams: " . $e->getMessage());
    }
    return $upcoming_exams;
}

// Get recent results with error handling
function getRecentResults($conn, $student_id) {
    $recent_results = [];
    try {
        // Simplified query that doesn't rely on the student_performance table
        $stmt = $conn->prepare("SELECT r.*, s.subject_name, e.exam_name 
                              FROM results r 
                              JOIN subjects s ON r.subject_id = s.subject_id 
                              JOIN exams e ON r.exam_id = e.exam_id
                              WHERE r.student_id = ? 
                              ORDER BY r.created_at DESC LIMIT 10");
        
        // Check if prepare statement failed
        if ($stmt === false) {
            error_log("Prepare statement failed: " . $conn->error);
            return $recent_results;
        }
        
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Calculate GPA based on grade if not provided
            if (!isset($row['gpa'])) {
                switch ($row['grade']) {
                    case 'A+': $row['gpa'] = 4.0; break;
                    case 'A': $row['gpa'] = 3.7; break;
                    case 'B+': $row['gpa'] = 3.3; break;
                    case 'B': $row['gpa'] = 3.0; break;
                    case 'C+': $row['gpa'] = 2.7; break;
                    case 'C': $row['gpa'] = 2.3; break;
                    case 'D': $row['gpa'] = 2.0; break;
                    case 'F': $row['gpa'] = 0.0; break;
                    default: $row['gpa'] = 0.0;
                }
            }
            $recent_results[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching recent results: " . $e->getMessage());
    }
    return $recent_results;
}

// Get notifications with error handling
function getNotifications($conn, $user_id) {
    $notifications = [];
    try {
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
    }
    return $notifications;
}

// Get attendance data with error handling
function getAttendanceData($conn, $student_id) {
    $attendance = [
        'present' => 0,
        'absent' => 0,
        'leave' => 0,
        'total_days' => 0,
        'percentage' => 0
    ];
    
    try {
        $stmt = $conn->prepare("SELECT 
                                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                                SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count,
                                COUNT(*) as total_days
                              FROM attendance 
                              WHERE student_id = ?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $attendance['present'] = $row['present'] ?? 0;
            $attendance['absent'] = $row['absent'] ?? 0;
            $attendance['leave'] = $row['leave_count'] ?? 0;
            $attendance['total_days'] = $row['total_days'] ?? 0;
            
            if ($attendance['total_days'] > 0) {
                $attendance['percentage'] = round(($attendance['present'] / $attendance['total_days']) * 100, 2);
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching attendance data: " . $e->getMessage());
    }
    
    return $attendance;
}

// Get subject performance data with error handling
function getSubjectPerformance($conn, $student_id) {
    $subject_performance = [];
    try {
        $stmt = $conn->prepare("SELECT s.subject_name, 
                                AVG(r.theory_marks + COALESCE(r.practical_marks, 0)) as avg_marks,
                                MAX(r.theory_marks + COALESCE(r.practical_marks, 0)) as highest_marks,
                                MIN(r.theory_marks + COALESCE(r.practical_marks, 0)) as lowest_marks
                              FROM results r
                              JOIN subjects s ON r.subject_id = s.subject_id
                              WHERE r.student_id = ?
                              GROUP BY r.subject_id
                              ORDER BY avg_marks DESC");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subject_performance[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching subject performance: " . $e->getMessage());
    }
    return $subject_performance;
}

// Get GPA trend data with error handling
function getGPATrend($conn, $student_id) {
    $gpa_trend = [];
    $time_periods = [];
    try {
        // Simplified query that doesn't rely on student_performance table
        $stmt = $conn->prepare("SELECT e.exam_name, r.grade, e.created_at
                              FROM results r
                              JOIN exams e ON r.exam_id = e.exam_id
                              WHERE r.student_id = ?
                              GROUP BY e.exam_id
                              ORDER BY e.created_at ASC");
        
        // Check if prepare statement failed
        if ($stmt === false) {
            error_log("Prepare statement failed: " . $conn->error);
            return ['gpa_trend' => [], 'time_periods' => []];
        }
        
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Calculate GPA based on grade
            $gpa = 0;
            switch ($row['grade']) {
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
            $gpa_trend[] = $gpa;
            $time_periods[] = $row['exam_name'];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching GPA trend: " . $e->getMessage());
    }
    return ['gpa_trend' => $gpa_trend, 'time_periods' => $time_periods];
}

// Get assignments with error handling
function getAssignments($conn, $class_id) {
    $assignments = [];
    try {
        $stmt = $conn->prepare("SELECT a.*, s.subject_name, u.full_name as teacher_name
                              FROM assignments a
                              JOIN subjects s ON a.subject_id = s.subject_id
                              JOIN users u ON a.teacher_id = u.user_id
                              WHERE a.class_id = ? AND a.due_date >= CURDATE()
                              ORDER BY a.due_date ASC
                              LIMIT 5");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching assignments: " . $e->getMessage());
    }
    return $assignments;
}

// Calculate overall performance
function calculateOverallPerformance($recent_results) {
    $overall_performance = [
        'total_subjects' => 0,
        'subjects_with_results' => 0,
        'total_marks' => 0,
        'obtained_marks' => 0,
        'average_percentage' => 0,
        'average_grade' => 'N/A',
        'average_gpa' => 0,
        'pass_count' => 0,
        'fail_count' => 0
    ];

    if (!empty($recent_results)) {
        $overall_performance['subjects_with_results'] = count($recent_results);
        $total_gpa = 0;
        
        foreach ($recent_results as $result) {
            $overall_performance['obtained_marks'] += $result['theory_marks'] + ($result['practical_marks'] ?? 0);
            $overall_performance['total_marks'] += 100; // Assuming each subject is out of 100
            $total_gpa += $result['gpa'];
            
            if ($result['grade'] != 'F') {
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
    
    return $overall_performance;
}

// Fetch all data using the functions
$student = getStudentDetails($conn, $user_id);
if (!$student) {
    die("Student record not found. Please contact administrator.");
}

$subjects = getSubjects($conn, $student['academic_year']);
$upcoming_exams = getUpcomingExams($conn, $student['class_id']);
$recent_results = getRecentResults($conn, $student['student_id']);
$notifications = getNotifications($conn, $user_id);
$attendance_data = getAttendanceData($conn, $student['student_id']);
$subject_performance = getSubjectPerformance($conn, $student['student_id']);
$gpa_data = getGPATrend($conn, $student['student_id']);
$assignments = getAssignments($conn, $student['class_id']);
$overall_performance = calculateOverallPerformance($recent_results);

// Prepare data for charts
$chart_labels = [];
$chart_data = [];
$chart_colors = [
    'rgba(54, 162, 235, 0.6)',
    'rgba(255, 99, 132, 0.6)',
    'rgba(255, 206, 86, 0.6)',
    'rgba(75, 192, 192, 0.6)',
    'rgba(153, 102, 255, 0.6)'
];
$chart_borders = [
    'rgba(54, 162, 235, 1)',
    'rgba(255, 99, 132, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(153, 102, 255, 1)'
];

foreach ($subject_performance as $index => $subject) {
    $chart_labels[] = $subject['subject_name'];
    $chart_data[] = $subject['avg_marks'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Result Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
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

        /* Responsive grid */
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }
        }

        @media (min-width: 641px) and (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        /* Animations */
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .5;
            }
        }

        .hover-scale {
            transition: all 0.3s ease;
        }

        .hover-scale:hover {
            transform: scale(1.03);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Card hover effects */
        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Skeleton loading */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }

        @keyframes skeleton-loading {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            width: 120px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Badge notification */
        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 3px 6px;
            border-radius: 50%;
            background: red;
            color: white;
            font-size: 10px;
        }

        /* Dark mode toggle */
        .dark-mode {
            background-color: #1a202c;
            color: #e2e8f0;
        }

        .dark-mode .bg-white {
            background-color: #2d3748 !important;
            color: #e2e8f0;
        }

        .dark-mode .bg-gray-50 {
            background-color: #4a5568 !important;
            color: #e2e8f0;
        }

        .dark-mode .text-gray-900 {
            color: #e2e8f0 !important;
        }

        .dark-mode .text-gray-500 {
            color: #a0aec0 !important;
        }

        .dark-mode .border-gray-200 {
            border-color: #4a5568 !important;
        }

        /* Grade badge styles */
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
    </style>
</head>

<body class="bg-gray-100" id="body">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include('./includes/student_sidebar.php'); ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include('./includes/top_navigation.php'); ?>
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
                            <a href="student_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-md">
                                <i class="fas fa-tachometer-alt mr-3"></i>
                                Dashboard
                            </a>
                            <a href="view_result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-clipboard-list mr-3"></i>
                                My Results
                            </a>
                            <a href="#" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-book mr-3"></i>
                                Subjects
                            </a>
                            <a href="#" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-calendar-alt mr-3"></i>
                                Exam Schedule
                            </a>
                            <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-cog mr-3"></i>
                                Settings
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
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Welcome Banner -->
                        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-lg shadow-lg mb-6 overflow-hidden">
                            <div class="px-6 py-8 md:px-8 md:flex md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-white sm:text-2xl">
                                        Welcome, <?php echo isset($student['full_name']) ? htmlspecialchars($student['full_name']) : 'Student'; ?>!
                                    </h2>
                                    <p class="mt-2 text-sm text-blue-100 max-w-md">
                                        Here's your academic performance dashboard. Stay updated with your results and upcoming exams.
                                    </p>
                                </div>
                                <div class="mt-4 md:mt-0 flex space-x-3">
                                    <a href="view_result.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-500 hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-400">
                                        <i class="fas fa-eye mr-2"></i> View Results
                                    </a>
                                </div>
                            </div>
                            <div class="bg-indigo-800 bg-opacity-50 px-6 py-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar-day text-blue-200 mr-2"></i>
                                        <span class="text-sm text-blue-100"><?php echo date('l, F j, Y'); ?></span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-clock text-blue-200 mr-2"></i>
                                        <span class="text-sm text-blue-100" id="live-clock"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Student Profile Card -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6 card-hover">
                            <div class="p-6">
                                <div class="flex flex-col md:flex-row md:items-center">
                                    <div class="flex-shrink-0 h-20 w-20 rounded-full bg-blue-600 flex items-center justify-center mb-4 md:mb-0">
                                        <span class="text-2xl font-medium text-white"><?php echo substr($student['full_name'], 0, 1); ?></span>
                                    </div>
                                    <div class="md:ml-6">
                                        <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                                        <div class="mt-1 flex flex-col md:flex-row md:items-center">
                                            <span class="text-sm text-gray-600 mr-4"><?php echo htmlspecialchars($student['email']); ?></span>
                                            <?php if (!empty($student['phone'])): ?>
                                                <span class="text-sm text-gray-600"><?php echo htmlspecialchars($student['phone']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Student
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Roll No: <?php echo htmlspecialchars($student['roll_number']); ?>
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                Class: <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?>
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Academic Year: <?php echo htmlspecialchars($student['academic_year']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Performance Overview -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 stats-grid">
                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                            <i class="fas fa-chart-line text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Average GPA</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo number_format($overall_performance['average_gpa'], 2); ?></div>
                                                    <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                                        <span class="sr-only">out of</span>
                                                        / 4.0
                                                    </div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <span class="font-medium text-blue-600">Grade: <?php echo $overall_performance['average_grade']; ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                            <i class="fas fa-percentage text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Average Percentage</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo number_format($overall_performance['average_percentage'], 2); ?>%</div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <span class="font-medium text-green-600"><?php echo $overall_performance['pass_count']; ?> subjects passed</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                            <i class="fas fa-calendar-check text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Attendance</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $attendance_data['percentage']; ?>%</div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <span class="font-medium text-indigo-600"><?php echo $attendance_data['present']; ?> days present</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                            <i class="fas fa-tasks text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Assignments</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo count($assignments); ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="#assignments" class="font-medium text-blue-600 hover:text-blue-500">View details</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Academic Performance Charts -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Subject Performance Chart -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Subject Performance</h3>
                                    <p class="mt-1 text-sm text-gray-500">Your performance across different subjects</p>
                                </div>
                                <div class="p-6">
                                    <div class="h-64">
                                        <canvas id="subjectPerformanceChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- GPA Trend Chart -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">GPA Trend</h3>
                                    <p class="mt-1 text-sm text-gray-500">Your GPA progression over time</p>
                                </div>
                                <div class="p-6">
                                    <div class="h-64">
                                        <canvas id="gpaTrendChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Results and Upcoming Exams -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Recent Results -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Recent Results</h3>
                                        <p class="mt-1 text-sm text-gray-500">Your latest examination results</p>
                                    </div>
                                    <a href="view_result.php" class="text-sm text-blue-600 hover:text-blue-500">View all</a>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($recent_results)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-info-circle text-blue-500 text-2xl mb-2"></i>
                                            <p class="text-gray-500">No results available yet.</p>
                                        </div>
                                    <?php else: ?>
                                        <ul class="divide-y divide-gray-200">
                                            <?php foreach (array_slice($recent_results, 0, 5) as $result): ?>
                                                <li class="py-4">
                                                    <div class="flex space-x-3">
                                                        <div class="flex-1 space-y-1">
                                                            <div class="flex items-center justify-between">
                                                                <h3 class="text-sm font-medium"><?php echo htmlspecialchars($result['subject_name']); ?></h3>
                                                                <p class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($result['created_at'])); ?></p>
                                                            </div>
                                                            <p class="text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($result['exam_name']); ?> | 
                                                                Theory: <?php echo $result['theory_marks']; ?> | 
                                                                Practical: <?php echo $result['practical_marks'] ?? 'N/A'; ?>
                                                            </p>
                                                            <div class="mt-1">
                                                                <span class="grade-badge grade-<?php echo strtolower(str_replace('+', '-plus', $result['grade'])); ?>">
                                                                    Grade: <?php echo $result['grade']; ?> (GPA: <?php echo number_format($result['gpa'], 2); ?>)
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Upcoming Exams -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Upcoming Exams</h3>
                                    <p class="mt-1 text-sm text-gray-500">Scheduled examinations for your class</p>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($upcoming_exams)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-calendar-check text-green-500 text-2xl mb-2"></i>
                                            <p class="text-gray-500">No upcoming exams scheduled.</p>
                                        </div>
                                    <?php else: ?>
                                        <ul class="divide-y divide-gray-200">
                                            <?php foreach ($upcoming_exams as $exam): ?>
                                                <li class="py-4">
                                                    <div class="flex space-x-3">
                                                        <div class="flex-shrink-0">
                                                            <div class="h-10 w-10 rounded-md bg-indigo-500 flex items-center justify-center">
                                                                <i class="fas fa-calendar-alt text-white"></i>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 space-y-1">
                                                            <div class="flex items-center justify-between">
                                                                <h3 class="text-sm font-medium"><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                                                                <?php 
                                                                $days_left = ceil((strtotime($exam['start_date']) - time()) / (60 * 60 * 24));
                                                                $badge_color = $days_left <= 3 ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800';
                                                                ?>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badge_color; ?>">
                                                                    <?php echo $days_left; ?> days left
                                                                </span>
                                                            </div>
                                                            <p class="text-sm text-gray-500">
                                                                Date: <?php echo date('M d, Y', strtotime($exam['start_date'])); ?> | 
                                                                Time: <?php echo !empty($exam['start_time']) ? date('h:i A', strtotime($exam['start_time'])) : 'TBA'; ?>
                                                            </p>
                                                            <p class="text-sm text-gray-500">
                                                                <?php echo !empty($exam['description']) ? htmlspecialchars($exam['description']) : 'No additional details available.'; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Assignments and Notifications -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Assignments -->
                            <div id="assignments" class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Pending Assignments</h3>
                                    <p class="mt-1 text-sm text-gray-500">Tasks that need to be completed</p>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($assignments)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                                            <p class="text-gray-500">No pending assignments. You're all caught up!</p>
                                        </div>
                                    <?php else: ?>
                                        <ul class="divide-y divide-gray-200">
                                            <?php foreach ($assignments as $assignment): ?>
                                                <li class="py-4">
                                                    <div class="flex space-x-3">
                                                        <div class="flex-shrink-0">
                                                            <div class="h-10 w-10 rounded-md bg-yellow-500 flex items-center justify-center">
                                                                <i class="fas fa-book text-white"></i>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 space-y-1">
                                                            <div class="flex items-center justify-between">
                                                                <h3 class="text-sm font-medium"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                                                <?php 
                                                                $days_left = ceil((strtotime($assignment['due_date']) - time()) / (60 * 60 * 24));
                                                                $badge_color = $days_left <= 2 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800';
                                                                ?>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badge_color; ?>">
                                                                    Due in <?php echo $days_left; ?> days
                                                                </span>
                                                            </div>
                                                            <p class="text-sm text-gray-500">
                                                                Subject: <?php echo htmlspecialchars($assignment['subject_name']); ?> | 
                                                                Teacher: <?php echo htmlspecialchars($assignment['teacher_name']); ?>
                                                            </p>
                                                            <p class="text-sm text-gray-500">
                                                                <?php echo !empty($assignment['description']) ? htmlspecialchars(substr($assignment['description'], 0, 100)) . (strlen($assignment['description']) > 100 ? '...' : '') : 'No description available.'; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Notifications -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Notifications</h3>
                                    <p class="mt-1 text-sm text-gray-500">Latest updates and announcements</p>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($notifications)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-bell-slash text-gray-400 text-2xl mb-2"></i>
                                            <p class="text-gray-500">No new notifications.</p>
                                        </div>
                                    <?php else: ?>
                                        <ul class="divide-y divide-gray-200">
                                            <?php foreach ($notifications as $notification): ?>
                                                <li class="py-4">
                                                    <div class="flex space-x-3">
                                                        <div class="flex-shrink-0">
                                                            <?php 
                                                            $icon_class = 'bg-blue-500';
                                                            $icon = '<i class="fas fa-bell text-white"></i>';
                                                            
                                                            if (strpos(strtolower($notification['message']), 'result') !== false) {
                                                                $icon_class = 'bg-green-500';
                                                                $icon = '<i class="fas fa-clipboard-check text-white"></i>';
                                                            } elseif (strpos(strtolower($notification['message']), 'exam') !== false) {
                                                                $icon_class = 'bg-purple-500';
                                                                $icon = '<i class="fas fa-calendar-alt text-white"></i>';
                                                            } elseif (strpos(strtolower($notification['message']), 'assignment') !== false) {
                                                                $icon_class = 'bg-yellow-500';
                                                                $icon = '<i class="fas fa-tasks text-white"></i>';
                                                            }
                                                            ?>
                                                            <div class="h-10 w-10 rounded-full <?php echo $icon_class; ?> flex items-center justify-center">
                                                                <?php echo $icon; ?>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 space-y-1">
                                                            <div class="flex items-center justify-between">
                                                                <h3 class="text-sm font-medium"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                                                <p class="text-sm text-gray-500"><?php echo date('M d, h:i A', strtotime($notification['created_at'])); ?></p>
                                                            </div>
                                                            <p class="text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($notification['message']); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance Overview -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6 hover-scale card-hover">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Attendance Overview</h3>
                                <p class="mt-1 text-sm text-gray-500">Your attendance record for this academic year</p>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
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
                                </div>
                                
                                <div class="mt-4">
                                    <h4 class="text-md font-medium text-gray-700 mb-2">Attendance Percentage: <?php echo $attendance_data['percentage']; ?>%</h4>
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
                                                        Your attendance is below the required 75%. Please improve your attendance to avoid academic penalties.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize live clock
        function updateClock() {
            const now = new Date();
            document.getElementById('live-clock').textContent = now.toLocaleTimeString();
        }

        updateClock();
        setInterval(updateClock, 1000);

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

        // Subject Performance Chart
        const subjectCtx = document.getElementById('subjectPerformanceChart').getContext('2d');
        const subjectPerformanceChart = new Chart(subjectCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Average Marks',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: <?php echo json_encode($chart_colors); ?>,
                    borderColor: <?php echo json_encode($chart_borders); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                }
            }
        });

        // GPA Trend Chart
        const gpaCtx = document.getElementById('gpaTrendChart').getContext('2d');
        const gpaTrendChart = new Chart(gpaCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($gpa_data['time_periods']); ?>,
                datasets: [{
                    label: 'GPA',
                    data: <?php echo json_encode($gpa_data['gpa_trend']); ?>,
                    backgroundColor: 'rgba(66, 153, 225, 0.2)',
                    borderColor: 'rgba(66, 153, 225, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
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
                        ticks: {
                            stepSize: 0.5
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                }
            }
        });

        // User menu toggle
        document.getElementById('user-menu-button').addEventListener('click', function() {
            document.getElementById('user-menu').classList.toggle('hidden');
        });

        // Notification dropdown toggle
        document.getElementById('notification-button').addEventListener('click', function() {
            document.getElementById('notification-dropdown').classList.toggle('hidden');
        });

        // Dark mode toggle
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const darkModeToggleDot = document.getElementById('dark-mode-toggle-dot');
        const mobileDarkModeToggle = document.getElementById('mobile-dark-mode-toggle');
        const body = document.getElementById('body');

        function toggleDarkMode() {
            if (body.classList.contains('dark-mode')) {
                body.classList.remove('dark-mode');
                darkModeToggleDot.classList.remove('translate-x-5');
                darkModeToggleDot.classList.add('translate-x-0.5');
                localStorage.setItem('darkMode', 'false');
            } else {
                body.classList.add('dark-mode');
                darkModeToggleDot.classList.remove('translate-x-0.5');
                darkModeToggleDot.classList.add('translate-x-5');
                localStorage.setItem('darkMode', 'true');
            }
        }

        // Check for saved dark mode preference
        if (localStorage.getItem('darkMode') === 'true') {
            body.classList.add('dark-mode');
            darkModeToggleDot.classList.remove('translate-x-0.5');
            darkModeToggleDot.classList.add('translate-x-5');
        }

        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', toggleDarkMode);
        }
        
        if (mobileDarkModeToggle) {
            mobileDarkModeToggle.addEventListener('click', toggleDarkMode);
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('user-menu');
            const userMenuButton = document.getElementById('user-menu-button');
            const notificationDropdown = document.getElementById('notification-dropdown');
            const notificationButton = document.getElementById('notification-button');

            if (userMenu && userMenuButton && !userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }

            if (notificationDropdown && notificationButton && !notificationButton.contains(event.target) && !notificationDropdown.contains(event.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });
    </script>
</body>

</html>
