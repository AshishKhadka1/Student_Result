<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Admin';

// Get all classes for dropdown
$classes = [];
try {
    $sql = "SELECT class_id, class_name, section, academic_year 
            FROM Classes 
            ORDER BY academic_year DESC, class_name, section";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading classes: " . $e->getMessage();
}

// Get all exams for dropdown
$exams = [];
try {
    $sql = "SELECT exam_id, exam_name, exam_type, academic_year 
            FROM Exams 
            ORDER BY academic_year DESC, exam_date DESC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading exams: " . $e->getMessage();
}

// Get unique academic years for filter
$academic_years = [];
try {
    $sql = "SELECT DISTINCT academic_year FROM Classes ORDER BY academic_year DESC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $academic_years[] = $row['academic_year'];
    }
} catch (Exception $e) {
    // Silently fail
}

// Initialize report variables
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$selected_class = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$selected_exam = isset($_GET['exam_id']) ? $_GET['exam_id'] : '';
$selected_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';
$report_data = [];
$chart_data = [];

// Generate reports based on selected type
if (!empty($report_type)) {
    try {
        // Class Performance Report
        if ($report_type == 'class_performance' && !empty($selected_class) && !empty($selected_exam)) {
            $stmt = $conn->prepare("
                SELECT 
                    s.student_id,
                    s.roll_number,
                    u.full_name,
                    sp.average_marks,
                    sp.gpa,
                    sp.rank,
                    sp.subjects_passed,
                    sp.total_subjects
                FROM 
                    student_performance sp
                JOIN 
                    students s ON sp.student_id = s.student_id
                JOIN 
                    users u ON s.user_id = u.user_id
                WHERE 
                    s.class_id = ? AND sp.exam_id = ?
                ORDER BY 
                    sp.rank
            ");
            $stmt->bind_param("ii", $selected_class, $selected_exam);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            
            // Get class statistics
            $stmt = $conn->prepare("
                SELECT 
                    AVG(sp.average_marks) as class_average,
                    AVG(sp.gpa) as class_avg_gpa,
                    COUNT(CASE WHEN sp.subjects_passed = sp.total_subjects THEN 1 END) as passed_all,
                    COUNT(sp.student_id) as total_students
                FROM 
                    student_performance sp
                JOIN 
                    students s ON sp.student_id = s.student_id
                WHERE 
                    s.class_id = ? AND sp.exam_id = ?
            ");
            $stmt->bind_param("ii", $selected_class, $selected_exam);
            $stmt->execute();
            $class_stats = $stmt->get_result()->fetch_assoc();
            
            // Prepare chart data for class performance
            $stmt = $conn->prepare("
                SELECT 
                    CASE 
                        WHEN sp.gpa >= 3.7 THEN 'A/A+' 
                        WHEN sp.gpa >= 3.0 THEN 'B/B+' 
                        WHEN sp.gpa >= 2.0 THEN 'C/C+' 
                        WHEN sp.gpa >= 1.0 THEN 'D' 
                        ELSE 'F' 
                    END as grade_group,
                    COUNT(*) as count
                FROM 
                    student_performance sp
                JOIN 
                    students s ON sp.student_id = s.student_id
                WHERE 
                    s.class_id = ? AND sp.exam_id = ?
                GROUP BY 
                    grade_group
                ORDER BY 
                    grade_group
            ");
            $stmt->bind_param("ii", $selected_class, $selected_exam);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $grade_labels = [];
            $grade_counts = [];
            
            while ($row = $result->fetch_assoc()) {
                $grade_labels[] = $row['grade_group'];
                $grade_counts[] = $row['count'];
            }
            
            $chart_data = [
                'type' => 'pie',
                'labels' => $grade_labels,
                'data' => $grade_counts,
                'title' => 'Grade Distribution'
            ];
        }
        
        // Subject Performance Report
        elseif ($report_type == 'subject_performance' && !empty($selected_class) && !empty($selected_exam)) {
            $stmt = $conn->prepare("
                SELECT 
                    s.subject_id,
                    s.subject_name,
                    s.subject_code,
                    AVG(r.theory_marks + r.practical_marks) as avg_marks,
                    AVG(r.gpa) as avg_gpa,
                    COUNT(CASE WHEN r.is_pass = 1 THEN 1 END) as passed,
                    COUNT(r.result_id) as total_students,
                    MAX(r.theory_marks + r.practical_marks) as highest_marks,
                    MIN(r.theory_marks + r.practical_marks) as lowest_marks
                FROM 
                    Results r
                JOIN 
                    Subjects s ON r.subject_id = s.subject_id
                JOIN 
                    Students st ON r.student_id = st.student_id
                WHERE 
                    st.class_id = ? AND r.exam_id = ?
                GROUP BY 
                    s.subject_id, s.subject_name, s.subject_code
                ORDER BY 
                    avg_marks DESC
            ");
            $stmt->bind_param("ii", $selected_class, $selected_exam);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            
            // Prepare chart data for subject performance
            $subject_names = [];
            $avg_marks = [];
            $pass_percentages = [];
            
            foreach ($report_data as $subject) {
                $subject_names[] = $subject['subject_name'];
                $avg_marks[] = $subject['avg_marks'];
                $pass_percentages[] = ($subject['passed'] / $subject['total_students']) * 100;
            }
            
            $chart_data = [
                'type' => 'bar',
                'labels' => $subject_names,
                'datasets' => [
                    [
                        'label' => 'Average Marks',
                        'data' => $avg_marks,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.5)'
                    ],
                    [
                        'label' => 'Pass Percentage',
                        'data' => $pass_percentages,
                        'backgroundColor' => 'rgba(75, 192, 192, 0.5)'
                    ]
                ],
                'title' => 'Subject Performance Comparison'
            ];
        }
        
        // Yearly Performance Report
        elseif ($report_type == 'yearly_performance' && !empty($selected_year)) {
            $stmt = $conn->prepare("
                SELECT 
                    c.class_name,
                    c.section,
                    e.exam_name,
                    COUNT(DISTINCT s.student_id) as total_students,
                    AVG(sp.average_marks) as avg_marks,
                    AVG(sp.gpa) as avg_gpa,
                    COUNT(CASE WHEN sp.subjects_passed = sp.total_subjects THEN 1 END) as passed_all,
                    (COUNT(CASE WHEN sp.subjects_passed = sp.total_subjects THEN 1 END) / COUNT(DISTINCT s.student_id)) * 100 as pass_percentage
                FROM 
                    student_performance sp
                JOIN 
                    students s ON sp.student_id = s.student_id
                JOIN 
                    classes c ON s.class_id = c.class_id
                JOIN 
                    exams e ON sp.exam_id = e.exam_id
                WHERE 
                    c.academic_year = ?
                GROUP BY 
                    c.class_id, e.exam_id
                ORDER BY 
                    c.class_name, c.section, e.exam_date
            ");
            $stmt->bind_param("s", $selected_year);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            
            // Prepare chart data for yearly performance
            $class_labels = [];
            $pass_percentages = [];
            $avg_gpas = [];
            
            foreach ($report_data as $row) {
                $class_labels[] = $row['class_name'] . ' ' . $row['section'] . ' (' . $row['exam_name'] . ')';
                $pass_percentages[] = $row['pass_percentage'];
                $avg_gpas[] = $row['avg_gpa'];
            }
            
            $chart_data = [
                'type' => 'bar',
                'labels' => $class_labels,
                'datasets' => [
                    [
                        'label' => 'Pass Percentage',
                        'data' => $pass_percentages,
                        'backgroundColor' => 'rgba(75, 192, 192, 0.5)'
                    ],
                    [
                        'label' => 'Average GPA',
                        'data' => $avg_gpas,
                        'backgroundColor' => 'rgba(153, 102, 255, 0.5)'
                    ]
                ],
                'title' => 'Yearly Performance by Class and Exam'
            ];
        }
        
        // Top Performers Report
        elseif ($report_type == 'top_performers' && !empty($selected_class) && !empty($selected_exam)) {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            
            $stmt = $conn->prepare("
                SELECT 
                    s.student_id,
                    s.roll_number,
                    u.full_name,
                    sp.average_marks,
                    sp.gpa,
                    sp.rank,
                    sp.subjects_passed,
                    sp.total_subjects
                FROM 
                    student_performance sp
                JOIN 
                    students s ON sp.student_id = s.student_id
                JOIN 
                    users u ON s.user_id = u.user_id
                WHERE 
                    s.class_id = ? AND sp.exam_id = ?
                ORDER BY 
                    sp.gpa DESC, sp.average_marks DESC
                LIMIT ?
            ");
            $stmt->bind_param("iii", $selected_class, $selected_exam, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            
            // Prepare chart data for top performers
            $student_names = [];
            $gpas = [];
            $avg_marks = [];
            
            foreach ($report_data as $student) {
                $student_names[] = $student['roll_number'] . ' - ' . $student['full_name'];
                $gpas[] = $student['gpa'];
                $avg_marks[] = $student['average_marks'];
            }
            
            $chart_data = [
                'type' => 'bar',
                'labels' => $student_names,
                'datasets' => [
                    [
                        'label' => 'GPA',
                        'data' => $gpas,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.5)'
                    ],
                    [
                        'label' => 'Average Marks',
                        'data' => $avg_marks,
                        'backgroundColor' => 'rgba(255, 99, 132, 0.5)'
                    ]
                ],
                'title' => 'Top Performers'
            ];
        }
        
        // Attendance vs Performance Report
        elseif ($report_type == 'attendance_performance' && !empty($selected_class)) {
            $stmt = $conn->prepare("
                SELECT 
                    s.student_id,
                    s.roll_number,
                    u.full_name,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                    COUNT(a.attendance_id) as total_days,
                    (COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.attendance_id)) * 100 as attendance_percentage,
                    AVG(sp.gpa) as avg_gpa
                FROM 
                    students s
                JOIN 
                    users u ON s.user_id = u.user_id
                LEFT JOIN 
                    attendance a ON s.student_id = a.student_id
                LEFT JOIN 
                    student_performance sp ON s.student_id = sp.student_id
                WHERE 
                    s.class_id = ?
                GROUP BY 
                    s.student_id, s.roll_number, u.full_name
                ORDER BY 
                    attendance_percentage DESC
            ");
            $stmt->bind_param("i", $selected_class);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            
            // Prepare chart data for attendance vs performance
            $student_names = [];
            $attendance_percentages = [];
            $gpas = [];
            
            foreach ($report_data as $student) {
                $student_names[] = $student['roll_number'];
                $attendance_percentages[] = $student['attendance_percentage'];
                $gpas[] = $student['avg_gpa'] * 25; // Scale GPA to be visible on same chart
            }
            
            $chart_data = [
                'type' => 'scatter',
                'data' => [
                    'datasets' => [
                        [
                            'label' => 'Attendance vs GPA',
                            'data' => array_map(function($i) use ($attendance_percentages, $gpas) {
                                return [
                                    'x' => $attendance_percentages[$i],
                                    'y' => $gpas[$i]
                                ];
                            }, array_keys($attendance_percentages)),
                            'backgroundColor' => 'rgba(54, 162, 235, 0.5)'
                        ]
                    ]
                ],
                'options' => [
                    'scales' => [
                        'x' => [
                            'title' => [
                                'display' => true,
                                'text' => 'Attendance Percentage'
                            ]
                        ],
                        'y' => [
                            'title' => [
                                'display' => true,
                                'text' => 'GPA (scaled)'
                            ]
                        ]
                    ]
                ],
                'title' => 'Attendance vs Performance Correlation'
            ];
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error generating report: " . $e->getMessage();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
        
        /* Hover effects */
        .hover-scale {
            transition: all 0.3s ease;
        }
        
        .hover-scale:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Dark mode */
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
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            body {
                font-size: 12pt;
                line-height: 1.5;
            }
        }
        
        .print-only {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-100" id="body">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php
        // Include the file that processes form data
        include 'sidebar.php';
        ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php
        // Include the file that processes form data
        include 'topBar.php';
        ?>
        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow no-print">
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Reports</h1>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
                        <!-- User dropdown -->
                        <div class="ml-3 relative">
                            <div>
                                <button type="button" class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                    <span class="sr-only">Open user menu</span>
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-600">
                                        <span class="text-sm font-medium leading-none text-white">
                                            <?php echo substr($user_name, 0, 1); ?>
                                        </span>
                                    </span>
                                </button>
                            </div>
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" id="user-menu" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile sidebar -->
            <div class="fixed inset-0 flex z-40 md:hidden transform -translate-x-full transition-transform duration-300 ease-in-out no-print" id="mobile-sidebar">
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
                            <!-- Mobile menu items -->
                            <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-  class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-tachometer-alt mr-3"></i>
                                Dashboard
                            </a>
                            <a href="reports.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                                <i class="fas fa-chart-bar mr-3"></i>
                                Reports
                            </a>
                            <!-- More mobile menu items -->
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Notification Messages -->
                        <?php if(isset($_SESSION['success'])): ?>
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded no-print">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">
                                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                    </p>
                                </div>
                                <div class="ml-auto pl-3">
                                    <div class="-mx-1.5 -my-1.5">
                                        <button class="inline-flex rounded-md p-1.5 text-green-500 hover:bg-green-100 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                                            <span class="sr-only">Dismiss</span>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded no-print">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">
                                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                    </p>
                                </div>
                                <div class="ml-auto pl-3">
                                    <div class="-mx-1.5 -my-1.5">
                                        <button class="inline-flex rounded-md p-1.5 text-red-500 hover:bg-red-100 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                                            <span class="sr-only">Dismiss</span>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Report Selection -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale no-print">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Generate Report</h2>
                            <form action="reports.php" method="GET" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                                        <select id="report_type" name="report_type" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" onchange="toggleReportFields()">
                                            <option value="">Select Report Type</option>
                                            <option value="class_performance" <?php echo ($report_type == 'class_performance') ? 'selected' : ''; ?>>Class Performance Report</option>
                                            <option value="subject_performance" <?php echo ($report_type == 'subject_performance') ? 'selected' : ''; ?>>Subject Performance Report</option>
                                            <option value="yearly_performance" <?php echo ($report_type == 'yearly_performance') ? 'selected' : ''; ?>>Yearly Performance Report</option>
                                            <option value="top_performers" <?php echo ($report_type == 'top_performers') ? 'selected' : ''; ?>>Top Performers Report</option>
                                            <option value="attendance_performance" <?php echo ($report_type == 'attendance_performance') ? 'selected' : ''; ?>>Attendance vs Performance Report</option>
                                        </select>
                                    </div>
                                    
                                    <div id="class_field" class="<?php echo (!in_array($report_type, ['class_performance', 'subject_performance', 'top_performers', 'attendance_performance'])) ? 'hidden' : ''; ?>">
                                        <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                        <select id="class_id" name="class_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <option value="">Select Class</option>
                                            <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['class_id']; ?>" <?php echo ($selected_class == $class['class_id']) ? 'selected' : ''; ?>>
                                                <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div id="exam_field" class="<?php echo (!in_array($report_type, ['class_performance', 'subject_performance', 'top_performers'])) ? 'hidden' : ''; ?>">
                                        <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-1">Exam</label>
                                        <select id="exam_id" name="exam_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <option value="">Select Exam</option>
                                            <?php foreach ($exams as $exam): ?>
                                            <option value="<?php echo $exam['exam_id']; ?>" <?php echo ($selected_exam == $exam['exam_id']) ? 'selected' : ''; ?>>
                                                <?php echo $exam['exam_name'] . ' (' . $exam['exam_type'] . ' - ' . $exam['academic_year'] . ')'; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div id="year_field" class="<?php echo ($report_type != 'yearly_performance') ? 'hidden' : ''; ?>">
                                        <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                                        <select id="academic_year" name="academic_year" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <option value="">Select Year</option>
                                            <?php foreach ($academic_years as $year): ?>
                                            <option value="<?php echo $year; ?>" <?php echo ($selected_year == $year) ? 'selected' : ''; ?>>
                                                <?php echo $year; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div id="limit_field" class="<?php echo ($report_type != 'top_performers') ? 'hidden' : ''; ?>">
                                        <label for="limit" class="block text-sm font-medium text-gray-700 mb-1">Number of Students</label>
                                        <input type="number" id="limit" name="limit" min="1" max="50" value="<?php echo isset($_GET['limit']) ? $_GET['limit'] : 10; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                    </div>
                                </div>
                                
                                <div class="flex justify-end space-x-3 pt-4">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-chart-bar mr-2"></i> Generate Report
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Report Results -->
                        <?php if (!empty($report_data)): ?>
                        <div id="report-container">
                            <!-- Print Header (Only visible when printing) -->
                            <div class="print-only mb-6">
                                <div class="text-center">
                                    <h1 class="text-2xl font-bold">School Result Management System</h1>
                                    <p class="text-lg">
                                        <?php 
                                        switch ($report_type) {
                                            case 'class_performance':
                                                echo 'Class Performance Report';
                                                break;
                                            case 'subject_performance':
                                                echo 'Subject Performance Report';
                                                break;
                                            case 'yearly_performance':
                                                echo 'Yearly Performance Report';
                                                break;
                                            case 'top_performers':
                                                echo 'Top Performers Report';
                                                break;
                                            case 'attendance_performance':
                                                echo 'Attendance vs Performance Report';
                                                break;
                                        }
                                        ?>
                                    </p>
                                    <p>Generated on: <?php echo date('F j, Y'); ?></p>
                                </div>
                            </div>
                            
                            <!-- Report Actions -->
                            <div class="mb-4 flex justify-end space-x-2 no-print">
                                <button onclick="printReport()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-print mr-2"></i> Print Report
                                </button>
                                <button onclick="exportToPDF()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                                </button>
                                <button onclick="exportToCSV()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-file-csv mr-2"></i> Export CSV
                                </button>
                            </div>
                            
                            <!-- Report Title and Description -->
                            <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale">
                                <h2 class="text-xl font-bold text-gray-900 mb-2">
                                    <?php 
                                    switch ($report_type) {
                                        case 'class_performance':
                                            echo 'Class Performance Report';
                                            break;
                                        case 'subject_performance':
                                            echo 'Subject Performance Report';
                                            break;
                                        case 'yearly_performance':
                                            echo 'Yearly Performance Report for ' . $selected_year;
                                            break;
                                        case 'top_performers':
                                            echo 'Top ' . (isset($_GET['limit']) ? $_GET['limit'] : 10) . ' Performers Report';
                                            break;
                                        case 'attendance_performance':
                                            echo 'Attendance vs Performance Report';
                                            break;
                                    }
                                    ?>
                                </h2>
                                <p class="text-sm text-gray-500">
                                    <?php 
                                    if (in_array($report_type, ['class_performance', 'subject_performance', 'top_performers'])) {
                                        foreach ($classes as $class) {
                                            if ($class['class_id'] == $selected_class) {
                                                echo 'Class: ' . $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')';
                                                break;
                                            }
                                        }
                                        
                                        echo ' | ';
                                        
                                        foreach ($exams as $exam) {
                                            if ($exam['exam_id'] == $selected_exam) {
                                                echo 'Exam: ' . $exam['exam_name'] . ' (' . $exam['exam_type'] . ')';
                                                break;
                                            }
                                        }
                                    } elseif ($report_type == 'attendance_performance') {
                                        foreach ($classes as $class) {
                                            if ($class['class_id'] == $selected_class) {
                                                echo 'Class: ' . $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')';
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                </p>
                            </div>
                            
                            <!-- Chart Section -->
                            <?php if (!empty($chart_data)): ?>
                            <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale">
                                <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo $chart_data['title'] ?? 'Chart'; ?></h3>
                                <div class="h-80">
                                    <canvas id="reportChart"></canvas>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Report Data Table -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale">
                                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                                    <h3 class="text-lg font-medium text-gray-900">Report Data</h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="overflow-x-auto">
                                        <?php if ($report_type == 'class_performance'): ?>
                                        <!-- Class Performance Table -->
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Marks</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GPA</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjects Passed</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($report_data as $student): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $student['rank']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $student['roll_number']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $student['full_name']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($student['average_marks'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($student['gpa'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $student['subjects_passed'] . '/' . $student['total_subjects']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        
                                        <?php elseif ($report_type == 'subject_performance'): ?>
                                        <!-- Subject Performance Table -->
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Marks</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average GPA</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pass Rate</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Highest Marks</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lowest Marks</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($report_data as $subject): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $subject['subject_name']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $subject['subject_code']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($subject['avg_marks'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($subject['avg_gpa'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $subject['passed'] . '/' . $subject['total_students'] . ' (' . number_format(($subject['passed'] / $subject['total_students']) * 100, 2) . '%)'; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($subject['highest_marks'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($subject['lowest_marks'], 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        
                                        <?php elseif ($report_type == 'yearly_performance'): ?>
                                        <!-- Yearly Performance Table -->
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Students</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Marks</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average GPA</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pass Rate</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($report_data as $row): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $row['class_name']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['section']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['exam_name']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['total_students']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($row['avg_marks'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($row['avg_gpa'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['passed_all'] . '/' . $row['total_students'] . ' (' . number_format($row['pass_percentage'], 2) . '%)'; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        
                                        <?php elseif ($report_type == 'top_performers'): ?>
                                        <!-- Top Performers Table -->
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Marks</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GPA</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjects Passed</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($report_data as $index => $student): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $index + 1; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $student['roll_number']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $student['full_name']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($student['average_marks'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($student['gpa'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $student['subjects_passed'] . '/' . $student['total_subjects']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        
                                        <?php elseif ($report_type == 'attendance_performance'): ?>
                                        <!-- Attendance vs Performance Table -->
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Present Days</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Days</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance %</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average GPA</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($report_data as $student): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $student['roll_number']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $student['full_name']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $student['present_days']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $student['total_days']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($student['attendance_percentage'], 2) . '%'; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($student['avg_gpa'], 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php endif; ?>
                                    </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const closeSidebar = document.getElementById('close-sidebar');
            const sidebarBackdrop = document.getElementById('sidebar-backdrop');
            const mobileSidebar = document.getElementById('mobile-sidebar');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    mobileSidebar.classList.remove('-translate-x-full');
                });
            }
            
            if (closeSidebar) {
                closeSidebar.addEventListener('click', function() {
                    mobileSidebar.classList.add('-translate-x-full');
                });
            }
            
            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', function() {
                    mobileSidebar.classList.add('-translate-x-full');
                });
            }
            
            // User menu toggle
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            
            if (userMenuButton && userMenu) {
                userMenuButton.addEventListener('click', function() {
                    userMenu.classList.toggle('hidden');
                });
                
                // Close when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                        userMenu.classList.add('hidden');
                    }
                });
            }
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('dark-mode-toggle');
            const darkModeToggleDot = document.getElementById('dark-mode-toggle-dot');
            const body = document.getElementById('body');
            
            if (darkModeToggle && darkModeToggleDot && body) {
                // Check for saved dark mode preference
                if (localStorage.getItem('darkMode') === 'true') {
                    body.classList.add('dark-mode');
                    darkModeToggleDot.classList.remove('translate-x-0.5');
                    darkModeToggleDot.classList.add('translate-x-5');
                }
                
                darkModeToggle.addEventListener('click', function() {
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
                });
            }
            
            // Initialize Chart if data exists
            <?php if (!empty($chart_data)): ?>
            const ctx = document.getElementById('reportChart');
            if (ctx) {
                <?php if ($chart_data['type'] == 'pie'): ?>
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($chart_data['labels']); ?>,
                        datasets: [{
                            data: <?php echo json_encode($chart_data['data']); ?>,
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(255, 206, 86, 0.7)',
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(153, 102, 255, 0.7)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                            },
                            title: {
                                display: true,
                                text: '<?php echo $chart_data['title']; ?>'
                            }
                        }
                    }
                });
                <?php elseif ($chart_data['type'] == 'bar'): ?>
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_data['labels']); ?>,
                        datasets: <?php echo json_encode($chart_data['datasets']); ?>
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: '<?php echo $chart_data['title']; ?>'
                            }
                        }
                    }
                });
                <?php elseif ($chart_data['type'] == 'scatter'): ?>
                new Chart(ctx, {
                    type: 'scatter',
                    data: <?php echo json_encode($chart_data['data']); ?>,
                    options: <?php echo json_encode($chart_data['options']); ?>
                });
                <?php endif; ?>
            }
            <?php endif; ?>
        });
        
        // Toggle report fields based on report type
        function toggleReportFields() {
            const reportType = document.getElementById('report_type').value;
            const classField = document.getElementById('class_field');
            const examField = document.getElementById('exam_field');
            const yearField = document.getElementById('year_field');
            const limitField = document.getElementById('limit_field');
            
            // Hide all fields first
            classField.classList.add('hidden');
            examField.classList.add('hidden');
            yearField.classList.add('hidden');
            limitField.classList.add('hidden');
            
            // Show relevant fields based on report type
            if (['class_performance', 'subject_performance', 'top_performers', 'attendance_performance'].includes(reportType)) {
                classField.classList.remove('hidden');
            }
            
            if (['class_performance', 'subject_performance', 'top_performers'].includes(reportType)) {
                examField.classList.remove('hidden');
            }
            
            if (reportType === 'yearly_performance') {
                yearField.classList.remove('hidden');
            }
            
            if (reportType === 'top_performers') {
                limitField.classList.remove('hidden');
            }
        }
        
        // Print report
        function printReport() {
            window.print();
        }
        
        // Export to PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            const reportContainer = document.getElementById('report-container');
            
            html2canvas(reportContainer).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const imgWidth = 210; // A4 width in mm
                const pageHeight = 295; // A4 height in mm
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;
                
                doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    doc.addPage();
                    doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                doc.save('report.pdf');
            });
        }
        
        // Export to CSV
        function exportToCSV() {
            const table = document.querySelector('table');
            const rows = table.querySelectorAll('tr');
            
            let csv = [];
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Get the text content of the cell
                    let data = cols[j].textContent.trim();
                    
                    // Wrap with quotes if the data contains comma
                    if (data.includes(',')) {
                        data = `"${data}"`;
                    }
                    row.push(data);
                }
                csv.push(row.join(','));
            }
            
            // Add report title and date
            const reportTitle = document.querySelector('h2.text-xl').textContent.trim();
            const reportDate = 'Generated on: ' + new Date().toLocaleDateString();
            
            csv.unshift(''); // Empty line
            csv.unshift(reportDate);
            csv.unshift(reportTitle);
            
            const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'report.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
