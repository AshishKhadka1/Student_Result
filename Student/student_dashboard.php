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

// Accurate GPA calculation based on marks
function calculateGPA($total_marks) {
    if ($total_marks >= 90) return 4.0;
    if ($total_marks >= 85) return 3.7;
    if ($total_marks >= 80) return 3.3;
    if ($total_marks >= 75) return 3.0;
    if ($total_marks >= 70) return 2.7;
    if ($total_marks >= 65) return 2.3;
    if ($total_marks >= 60) return 2.0;
    if ($total_marks >= 55) return 1.7;
    if ($total_marks >= 50) return 1.3;
    if ($total_marks >= 45) return 1.0;
    if ($total_marks >= 40) return 0.7;
    return 0.0;
}

// Get grade based on marks
function getGrade($total_marks) {
    if ($total_marks >= 90) return 'A+';
    if ($total_marks >= 85) return 'A';
    if ($total_marks >= 80) return 'B+';
    if ($total_marks >= 75) return 'B';
    if ($total_marks >= 70) return 'C+';
    if ($total_marks >= 65) return 'C';
    if ($total_marks >= 60) return 'D+';
    if ($total_marks >= 55) return 'D';
    if ($total_marks >= 50) return 'E';
    return 'F';
}

// Get recent results with accurate calculations
function getRecentResults($conn, $student_id) {
    $recent_results = [];
    try {
        $stmt = $conn->prepare("SELECT r.*, s.subject_name, s.credit_hours, e.exam_name, e.exam_date,
                              (r.theory_marks + COALESCE(r.practical_marks, 0)) as total_marks
                              FROM results r 
                              JOIN subjects s ON r.subject_id = s.subject_id 
                              JOIN exams e ON r.exam_id = e.exam_id
                              WHERE r.student_id = ? AND (r.is_published = 1 OR r.status = 'published')
                              ORDER BY e.exam_date DESC, r.created_at DESC");
        
        if ($stmt === false) {
            error_log("Prepare statement failed: " . $conn->error);
            return $recent_results;
        }
        
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Calculate accurate GPA and grade
            $total_marks = $row['total_marks'];
            $row['calculated_gpa'] = calculateGPA($total_marks);
            $row['calculated_grade'] = getGrade($total_marks);
            $row['percentage'] = $total_marks; // Assuming total possible marks is 100
            
            // Use calculated values if database values are missing or incorrect
            if (empty($row['gpa']) || $row['gpa'] == 0) {
                $row['gpa'] = $row['calculated_gpa'];
            }
            if (empty($row['grade']) || $row['grade'] == 'F') {
                $row['grade'] = $row['calculated_grade'];
            }
            
            $recent_results[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching recent results: " . $e->getMessage());
    }
    return $recent_results;
}

// Get subject performance data with accurate calculations
function getSubjectPerformance($conn, $student_id) {
    $subject_performance = [];
    try {
        $stmt = $conn->prepare("SELECT s.subject_name, s.subject_id, s.credit_hours,
                                AVG(r.theory_marks + COALESCE(r.practical_marks, 0)) as avg_marks,
                                MAX(r.theory_marks + COALESCE(r.practical_marks, 0)) as highest_marks,
                                MIN(r.theory_marks + COALESCE(r.practical_marks, 0)) as lowest_marks,
                                COUNT(r.result_id) as exam_count,
                                AVG(CASE WHEN (r.theory_marks + COALESCE(r.practical_marks, 0)) >= 40 THEN 1 ELSE 0 END) * 100 as pass_rate
                              FROM results r
                              JOIN subjects s ON r.subject_id = s.subject_id
                              WHERE r.student_id = ? AND (r.is_published = 1 OR r.status = 'published')
                              GROUP BY r.subject_id, s.subject_name, s.credit_hours
                              ORDER BY avg_marks DESC");
        
        if ($stmt === false) {
            error_log("Prepare statement failed: " . $conn->error);
            return $subject_performance;
        }
        
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Calculate GPA for this subject
            $row['avg_gpa'] = calculateGPA($row['avg_marks']);
            $row['avg_grade'] = getGrade($row['avg_marks']);
            $subject_performance[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching subject performance: " . $e->getMessage());
    }
    return $subject_performance;
}

// Get GPA trend data with accurate calculations
function getGPATrend($conn, $student_id) {
    $gpa_trend = [];
    $time_periods = [];
    try {
        $stmt = $conn->prepare("SELECT e.exam_name, e.exam_date, e.exam_id,
                              AVG(r.theory_marks + COALESCE(r.practical_marks, 0)) as avg_marks,
                              COUNT(r.result_id) as subject_count
                              FROM results r
                              JOIN exams e ON r.exam_id = e.exam_id
                              WHERE r.student_id = ? AND (r.is_published = 1 OR r.status = 'published')
                              GROUP BY e.exam_id, e.exam_name, e.exam_date
                              ORDER BY e.exam_date ASC");
        
        if ($stmt === false) {
            error_log("Prepare statement failed: " . $conn->error);
            return ['gpa_trend' => [], 'time_periods' => []];
        }
        
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Calculate accurate GPA based on average marks
            $gpa = calculateGPA($row['avg_marks']);
            $gpa_trend[] = $gpa;
            $time_periods[] = $row['exam_name'];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching GPA trend: " . $e->getMessage());
    }
    return ['gpa_trend' => $gpa_trend, 'time_periods' => $time_periods];
}

// Calculate accurate overall performance
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
        'fail_count' => 0,
        'weighted_gpa' => 0,
        'total_credit_hours' => 0
    ];

    if (!empty($recent_results)) {
        $overall_performance['subjects_with_results'] = count($recent_results);
        $total_gpa = 0;
        $total_weighted_points = 0;
        $total_credit_hours = 0;
        
        // Group results by exam to get the most recent performance
        $exam_results = [];
        foreach ($recent_results as $result) {
            $exam_id = $result['exam_id'];
            if (!isset($exam_results[$exam_id])) {
                $exam_results[$exam_id] = [];
            }
            $exam_results[$exam_id][] = $result;
        }
        
        // Get the most recent exam results
        $latest_exam_results = [];
        if (!empty($exam_results)) {
            $latest_exam_key = array_keys($exam_results)[0]; // Most recent exam
            $latest_exam_results = $exam_results[$latest_exam_key];
        }
        
        foreach ($latest_exam_results as $result) {
            $total_marks = $result['theory_marks'] + ($result['practical_marks'] ?? 0);
            $credit_hours = $result['credit_hours'] ?? 3; // Default credit hours
            
            $overall_performance['obtained_marks'] += $total_marks;
            $overall_performance['total_marks'] += 100; // Assuming each subject is out of 100
            
            // Calculate GPA
            $gpa = calculateGPA($total_marks);
            $total_gpa += $gpa;
            
            // Weighted GPA calculation
            $total_weighted_points += ($gpa * $credit_hours);
            $total_credit_hours += $credit_hours;
            
            if ($total_marks >= 40) { // Passing marks
                $overall_performance['pass_count']++;
            } else {
                $overall_performance['fail_count']++;
            }
        }
        
        $overall_performance['total_credit_hours'] = $total_credit_hours;
        
        if ($overall_performance['total_marks'] > 0) {
            $overall_performance['average_percentage'] = ($overall_performance['obtained_marks'] / $overall_performance['total_marks']) * 100;
            $overall_performance['average_gpa'] = $total_gpa / count($latest_exam_results);
            
            // Weighted GPA
            if ($total_credit_hours > 0) {
                $overall_performance['weighted_gpa'] = $total_weighted_points / $total_credit_hours;
            }
            
            // Determine average grade based on percentage
            $overall_performance['average_grade'] = getGrade($overall_performance['average_percentage']);
        }
        
        // Get total unique subjects the student has taken
        $unique_subjects = [];
        foreach ($recent_results as $result) {
            $unique_subjects[$result['subject_id']] = true;
        }
        $overall_performance['total_subjects'] = count($unique_subjects);
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
$subject_performance = getSubjectPerformance($conn, $student['student_id']);
$gpa_data = getGPATrend($conn, $student['student_id']);
$overall_performance = calculateOverallPerformance($recent_results);

// Prepare data for charts
$chart_labels = [];
$chart_data = [];
$chart_colors = [
    'rgba(54, 162, 235, 0.8)',
    'rgba(255, 99, 132, 0.8)',
    'rgba(255, 206, 86, 0.8)',
    'rgba(75, 192, 192, 0.8)',
    'rgba(153, 102, 255, 0.8)',
    'rgba(255, 159, 64, 0.8)',
    'rgba(199, 199, 199, 0.8)',
    'rgba(83, 102, 255, 0.8)'
];
$chart_borders = [
    'rgba(54, 162, 235, 1)',
    'rgba(255, 99, 132, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(153, 102, 255, 1)',
    'rgba(255, 159, 64, 1)',
    'rgba(199, 199, 199, 1)',
    'rgba(83, 102, 255, 1)'
];

foreach ($subject_performance as $index => $subject) {
    $chart_labels[] = $subject['subject_name'];
    $chart_data[] = round($subject['avg_marks'], 2);
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
            0%, 100% {
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

        /* Progress bars */
        .progress-bar {
            background-color: #e5e7eb;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            transition: width 0.5s ease;
        }

        /* Real-time indicator */
        .real-time-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            background-color: #dcfce7;
            color: #166534;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .real-time-dot {
            width: 6px;
            height: 6px;
            background-color: #22c55e;
            border-radius: 50%;
            margin-right: 0.25rem;
            animation: pulse 2s infinite;
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
                                        Here's your real-time academic performance dashboard. Stay updated with your results and progress.
                                    </p>
                                </div>
                                <div class="mt-4 md:mt-0">
                                    <span class="real-time-indicator">
                                        <span class="real-time-dot"></span>
                                        Live Data
                                    </span>
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
                                                Student ID: <?php echo htmlspecialchars($student['student_id']); ?>
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
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4 mb-6 stats-grid">
                            <!-- Current GPA -->
                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                            <i class="fas fa-chart-line text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Current GPA</dt>
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
                                        <?php if ($overall_performance['weighted_gpa'] > 0): ?>
                                            <span class="text-gray-500 ml-2">Weighted: <?php echo number_format($overall_performance['weighted_gpa'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Average Percentage -->
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
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo number_format($overall_performance['average_percentage'], 1); ?>%</div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="progress-bar h-2">
                                            <div class="progress-fill" style="width: <?php echo min($overall_performance['average_percentage'], 100); ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <span class="font-medium text-green-600"><?php echo $overall_performance['pass_count']; ?> subjects passed</span>
                                        <?php if ($overall_performance['fail_count'] > 0): ?>
                                            <span class="text-red-600 ml-2"><?php echo $overall_performance['fail_count']; ?> failed</span>
                                        <?php endif; ?>
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
                                    <p class="mt-1 text-sm text-gray-500">Your real-time performance across different subjects</p>
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

                        <?php if (empty($recent_results)): ?>
                        <!-- No Published Results Message -->
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        <strong>No Published Results Available</strong><br>
                                        Your results are currently being processed by the administration. Published results will appear here once they are officially released.
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

        // Subject Performance Chart with accurate data
        const subjectCtx = document.getElementById('subjectPerformanceChart').getContext('2d');
        const subjectPerformanceChart = new Chart(subjectCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Average Marks (%)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: <?php echo json_encode(array_slice($chart_colors, 0, count($chart_labels))); ?>,
                    borderColor: <?php echo json_encode(array_slice($chart_borders, 0, count($chart_labels))); ?>,
                    borderWidth: 2,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
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
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toFixed(1) + '%';
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // GPA Trend Chart with accurate data
        const gpaCtx = document.getElementById('gpaTrendChart').getContext('2d');
        const gpaTrendChart = new Chart(gpaCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($gpa_data['time_periods']); ?>,
                datasets: [{
                    label: 'GPA',
                    data: <?php echo json_encode($gpa_data['gpa_trend']); ?>,
                    backgroundColor: 'rgba(66, 153, 225, 0.1)',
                    borderColor: 'rgba(66, 153, 225, 1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'rgba(66, 153, 225, 1)',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
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
                            stepSize: 0.5,
                            callback: function(value) {
                                return value.toFixed(1);
                            }
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
                        callbacks: {
                            label: function(context) {
                                return 'GPA: ' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                animation: {
                    duration: 1500,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // User menu toggle
        document.getElementById('user-menu-button').addEventListener('click', function() {
            document.getElementById('user-menu').classList.toggle('hidden');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('user-menu');
            const userMenuButton = document.getElementById('user-menu-button');

            if (userMenu && userMenuButton && !userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });

        // Auto-refresh data every 30 seconds for real-time updates
        setInterval(function() {
            // You can implement AJAX calls here to refresh data without page reload
            console.log('Real-time data refresh...');
        }, 30000);

        // Add loading states for better UX
        window.addEventListener('load', function() {
            document.querySelectorAll('.skeleton').forEach(function(element) {
                element.classList.remove('skeleton');
            });
        });
    </script>
</body>

</html>
