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

// Function to get dashboard counts with error handling
function getDashboardCounts($conn) {
    $counts = [
        'students' => 0,
        'teachers' => 0,
        'classes' => 0,
        'subjects' => 0,
        'exams' => 0,
        'results' => 0,
        'active_users' => 0,
        'inactive_users' => 0
    ];
    
    $tables = [
        'students' => "SELECT COUNT(*) as count FROM students",
        'teachers' => "SELECT COUNT(*) as count FROM teachers",
        'classes' => "SELECT COUNT(*) as count FROM classes",
        'subjects' => "SELECT COUNT(*) as count FROM subjects",
        'exams' => "SELECT COUNT(*) as count FROM exams",
        'results' => "SELECT COUNT(*) as count FROM results",
        'active_users' => "SELECT COUNT(*) as count FROM users WHERE status = 'active'",
        'inactive_users' => "SELECT COUNT(*) as count FROM users WHERE status = 'inactive'"
    ];
    
    foreach ($tables as $key => $query) {
        try {
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) {
                $counts[$key] = $result->fetch_assoc()['count'];
            }
        } catch (Exception $e) {
            // Log error or handle gracefully
            error_log("Error fetching count for $key: " . $e->getMessage());
        }
    }
    
    return $counts;
}

// Get notifications with prepared statement
function getNotifications($conn, $user_id, $limit = 5) {
    $notifications = [];
    try {
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $user_id, $limit);
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

// Get recent users with error handling
function getRecentUsers($conn, $limit = 5) {
    $recent_users = [];
    try {
        $result = $conn->query("SELECT u.user_id, u.username, u.full_name, u.email, u.role, u.created_at FROM users u ORDER BY u.created_at DESC LIMIT $limit");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_users[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching recent users: " . $e->getMessage());
    }
    return $recent_users;
}

// Get upcoming exams with error handling
function getUpcomingExams($conn, $limit = 5) {
    $upcoming_exams = [];
    try {
        $result = $conn->query("SELECT e.*, c.class_name, c.section FROM exams e JOIN classes c ON e.class_id = c.class_id WHERE e.status = 'upcoming' ORDER BY e.start_date ASC LIMIT $limit");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $upcoming_exams[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching upcoming exams: " . $e->getMessage());
    }
    return $upcoming_exams;
}

// Get class toppers with error handling
function getClassToppers($conn, $limit = 10) {
    $class_toppers = [];
    try {
        $result = $conn->query("
            SELECT 
                c.class_id, 
                c.class_name, 
                c.section, 
                s.student_id, 
                s.roll_number, 
                u.full_name,
                sp.exam_id,
                e.exam_name,
                sp.gpa,
                sp.average_marks
            FROM 
                student_performance sp
            JOIN 
                students s ON sp.student_id = s.student_id
            JOIN 
                users u ON s.user_id = u.user_id
            JOIN 
                classes c ON s.class_id = c.class_id
            JOIN 
                exams e ON sp.exam_id = e.exam_id
            WHERE 
                (sp.student_id, sp.exam_id, sp.gpa) IN (
                    SELECT 
                        sp2.student_id, 
                        sp2.exam_id, 
                        MAX(sp2.gpa)
                    FROM 
                        student_performance sp2
                    JOIN 
                        students s2 ON sp2.student_id = s2.student_id
                    JOIN 
                        exams e2 ON sp2.exam_id = e2.exam_id
                    GROUP BY 
                        s2.class_id, sp2.exam_id
                )
            ORDER BY 
                c.class_name, c.section, e.exam_name
            LIMIT $limit
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $class_toppers[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching class toppers: " . $e->getMessage());
    }
    return $class_toppers;
}

// Get academic progress with error handling
function getAcademicProgress($conn, $limit = 10) {
    $academic_progress = [];
    try {
        $result = $conn->query("
            SELECT 
                c.class_name, 
                c.section, 
                e.exam_name,
                AVG(sp.gpa) as avg_gpa,
                AVG(sp.average_marks) as avg_marks,
                COUNT(DISTINCT sp.student_id) as student_count,
                SUM(CASE WHEN sp.gpa >= 3.5 THEN 1 ELSE 0 END) as high_performers,
                SUM(CASE WHEN sp.gpa < 2.0 THEN 1 ELSE 0 END) as low_performers
            FROM 
                student_performance sp
            JOIN 
                students s ON sp.student_id = s.student_id
            JOIN 
                classes c ON s.class_id = c.class_id
            JOIN 
                exams e ON sp.exam_id = e.exam_id
            GROUP BY 
                c.class_id, e.exam_id
            ORDER BY 
                c.class_name, c.section, e.exam_name
            LIMIT $limit
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $academic_progress[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching academic progress: " . $e->getMessage());
    }
    return $academic_progress;
}

// Get recent activities with error handling
function getRecentActivities($conn, $limit = 10) {
    $recent_activities = [];
    try {
        $result = $conn->query("
            (SELECT 
                'result' as type,
                CONCAT('New result added for ', s.roll_number, ' in ', sub.subject_name) as description,
                r.created_at as timestamp
            FROM 
                results r
            JOIN 
                students s ON r.student_id = s.student_id
            JOIN 
                subjects sub ON r.subject_id = sub.subject_id
            ORDER BY 
                r.created_at DESC
            LIMIT 5)
            
            UNION
            
            (SELECT 
                'user' as type,
                CONCAT('New ', u.role, ' account created: ', u.full_name) as description,
                u.created_at as timestamp
            FROM 
                users u
            ORDER BY 
                u.created_at DESC
            LIMIT 5)
            
            UNION
            
            (SELECT 
                'exam' as type,
                CONCAT('New exam scheduled: ', e.exam_name, ' for ', c.class_name, ' ', c.section) as description,
                e.created_at as timestamp
            FROM 
                exams e
            JOIN 
                classes c ON e.class_id = c.class_id
            ORDER BY 
                e.created_at DESC
            LIMIT 5)
            
            ORDER BY 
                timestamp DESC
            LIMIT $limit
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_activities[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching recent activities: " . $e->getMessage());
    }
    return $recent_activities;
}

// Get subject performance data for charts with error handling
function getSubjectPerformance($conn, $limit = 5) {
    $subject_performance = [];
    try {
        $result = $conn->query("
            SELECT 
                sub.subject_name,
                AVG(r.theory_marks + r.practical_marks) as avg_marks,
                COUNT(DISTINCT r.student_id) as student_count
            FROM 
                results r
            JOIN 
                subjects sub ON r.subject_id = sub.subject_id
            GROUP BY 
                r.subject_id
            ORDER BY 
                avg_marks DESC
            LIMIT $limit
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $subject_performance[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching subject performance: " . $e->getMessage());
    }
    return $subject_performance;
}

// Get class-wise GPA data for charts with error handling
function getClassGPA($conn, $limit = 5) {
    $class_gpa = [];
    try {
        $result = $conn->query("
            SELECT 
                c.class_name,
                c.section,
                AVG(sp.gpa) as avg_gpa
            FROM 
                student_performance sp
            JOIN 
                students s ON sp.student_id = s.student_id
            JOIN 
                classes c ON s.class_id = c.class_id
            GROUP BY 
                c.class_id
            ORDER BY 
                avg_gpa DESC
            LIMIT $limit
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $class_gpa[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching class GPA: " . $e->getMessage());
    }
    return $class_gpa;
}

// Get system health metrics with error handling
function getSystemHealth($conn) {
    $system_health = [
        'database_size' => 0,
        'last_backup' => 'Never',
        'pending_tasks' => 0
    ];

    try {
        // Get database size
        $result = $conn->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
            FROM 
                information_schema.tables 
            WHERE 
                table_schema = 'result_management'
            GROUP BY 
                table_schema
        ");

        if ($result && $result->num_rows > 0) {
            $system_health['database_size'] = $result->fetch_assoc()['size_mb'];
        }

        // Get last backup time (simulated)
        $system_health['last_backup'] = date('Y-m-d H:i:s', strtotime('-2 days'));

        // Get pending tasks count (simulated)
        $system_health['pending_tasks'] = rand(0, 5);
    } catch (Exception $e) {
        error_log("Error fetching system health: " . $e->getMessage());
    }

    return $system_health;
}

// Fetch all data using the functions
$counts = getDashboardCounts($conn);
$notifications = getNotifications($conn, $_SESSION['user_id']);
$recent_users = getRecentUsers($conn);
$upcoming_exams = getUpcomingExams($conn);
$class_toppers = getClassToppers($conn);
$academic_progress = getAcademicProgress($conn);
$recent_activities = getRecentActivities($conn);
$subject_performance = getSubjectPerformance($conn);
$class_gpa = getClassGPA($conn);
$system_health = getSystemHealth($conn);

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

// Prepare data for GPA chart
$gpa_labels = [];
$gpa_data = [];

foreach ($class_gpa as $class) {
    $gpa_labels[] = $class['class_name'] . ' ' . $class['section'];
    $gpa_data[] = $class['avg_gpa'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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
    </style>
</head>

<body class="bg-gray-100" id="body">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 bg-gray-800">
                <div class="flex items-center justify-center h-16 bg-gray-900">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <div class="flex flex-col flex-grow px-4 mt-5 overflow-y-auto">
                    <nav class="flex-1 space-y-1">
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-md group">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            <span class="truncate">Dashboard</span>
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            <span class="truncate">Results</span>
                        </a>
                        <a href="bulk_upload.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-upload mr-3"></i>
                            <span class="truncate">Bulk Upload</span>
                        </a>
                        <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-users mr-3"></i>
                            <span class="truncate">Users</span>
                        </a>

                        <div class="mt-4">
                            <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Academic</p>
                        </div>

                        <a href="subject.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-book mr-3"></i>
                            <span class="truncate">Subjects</span>
                        </a>

                        <a href="exam_result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-file-alt mr-3"></i>
                            <span class="truncate">Exam Result</span>
                        </a>

                        <a href="result_sheet_template.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-file-invoice mr-3"></i>
                            <span class="truncate">Result Sheet</span>
                        </a>

                        <a href="students_result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-user-graduate mr-3"></i>
                            <span class="truncate">Student Result</span>
                        </a>

                        <div class="mt-4">
                            <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Management</p>
                        </div>

                        <a href="classes.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-chalkboard mr-3"></i>
                            <span class="truncate">Classes</span>
                        </a>
                        <a href="exams.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            <span class="truncate">Exams</span>
                        </a>
                        <a href="reports.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-chart-bar mr-3"></i>
                            <span class="truncate">Reports</span>
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-cog mr-3"></i>
                            <span class="truncate">Settings</span>
                        </a>
                    </nav>
                    <div class="flex-shrink-0 block w-full">
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200 group">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            <span class="truncate">Logout</span>
                        </a>
                        <div class="flex items-center justify-between px-4 py-2 mt-2">
                            <span class="text-sm text-gray-400">Dark Mode</span>
                            <button id="dark-mode-toggle" class="w-10 h-5 rounded-full bg-gray-700 flex items-center transition duration-300 focus:outline-none">
                                <div id="dark-mode-toggle-dot" class="w-4 h-4 bg-white rounded-full transform translate-x-0.5 transition duration-300"></div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Admin Dashboard</h1>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
                        <!-- Search button -->
                        <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3" id="search-button">
                            <span class="sr-only">Search</span>
                            <i class="fas fa-search"></i>
                        </button>
                        
                        <!-- Search modal -->
                        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden" id="search-modal">
                            <div class="flex items-center justify-center min-h-screen p-4">
                                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-medium">Search</h3>
                                        <button id="close-search" class="text-gray-400 hover:text-gray-500">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="relative">
                                        <input type="text" id="global-search" class="w-full border border-gray-300 rounded-md py-2 px-4 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Search for students, classes, exams...">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-search text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="mt-4 max-h-60 overflow-y-auto" id="search-results">
                                        <!-- Search results will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dark mode toggle for mobile -->
                        <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3 md:hidden" id="mobile-dark-mode-toggle">
                            <span class="sr-only">Toggle dark mode</span>
                            <i class="fas fa-moon"></i>
                        </button>
                        
                        <!-- Notification button -->
                        <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 relative" id="notification-button">
                            <span class="sr-only">View notifications</span>
                            <i class="fas fa-bell"></i>
                            <?php if (!empty($notifications)): ?>
                                <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-400 ring-2 ring-white"></span>
                            <?php endif; ?>
                        </button>

                        <!-- Notification dropdown -->
                        <div class="hidden origin-top-right absolute right-0 mt-2 w-80 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" id="notification-dropdown" style="top: 3rem; right: 1rem;">
                            <div class="px-4 py-2 border-b border-gray-200">
                                <h3 class="text-sm font-medium text-gray-700">Notifications</h3>
                            </div>
                            <div class="max-h-60 overflow-y-auto">
                                <?php if (empty($notifications)): ?>
                                    <p class="px-4 py-2 text-sm text-gray-500">No new notifications.</p>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="px-4 py-2 hover:bg-gray-50 border-b border-gray-100">
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <p class="text-xs text-gray-400 mt-1"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="px-4 py-2 border-t border-gray-200">
                                <a href="notifications.php" class="text-xs text-blue-600 hover:text-blue-500">View all notifications</a>
                            </div>
                        </div>

                        <!-- Profile dropdown -->
                        <div class="ml-3 relative">
                            <div>
                                <button type="button"
                                    class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    id="user-menu-button"
                                    aria-expanded="false"
                                    aria-haspopup="true">
                                    <span class="sr-only">Open user menu</span>
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-600">
                                        <span class="text-sm font-medium leading-none text-white">
                                            <?php echo isset($_SESSION['full_name']) ? substr($_SESSION['full_name'], 0, 1) : 'A'; ?>
                                        </span>
                                    </span>
                                </button>
                            </div>

                            <!-- Profile dropdown menu -->
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none"
                                id="user-menu"
                                role="menu"
                                aria-orientation="vertical"
                                aria-labelledby="user-menu-button"
                                tabindex="-1">
                                <div class="px-4 py-2 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900"><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin User'; ?></p>
                                    <p class="text-xs text-gray-500"><?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'admin@example.com'; ?></p>
                                </div>
                                <a href="profile.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                    role="menuitem"
                                    tabindex="-1">
                                    <i class="fas fa-user mr-2"></i> Your Profile
                                </a>
                                <a href="settings.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                    role="menuitem"
                                    tabindex="-1">
                                    <i class="fas fa-cog mr-2"></i> Settings
                                </a>
                                <a href="logout.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                    role="menuitem"
                                    tabindex="-1">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                            <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-md">
                                <i class="fas fa-tachometer-alt mr-3"></i>
                                Dashboard
                            </a>
                            <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-clipboard-list mr-3"></i>
                                Results
                            </a>
                            <a href="bulk_upload.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-upload mr-3"></i>
                                Bulk Upload
                            </a>
                            <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-users mr-3"></i>
                                Users
                            </a>
                            <a href="subject.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-book mr-3"></i>
                                Subjects
                            </a>
                            <a href="classes.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-chalkboard mr-3"></i>
                                Classes
                            </a>
                            <a href="exams.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-calendar-alt mr-3"></i>
                                Exams
                            </a>
                            <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-cog mr-3"></i>
                                Settings
                            </a>
                            <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
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
                                        Welcome back, <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?>!
                                    </h2>
                                    <p class="mt-2 text-sm text-blue-100 max-w-md">
                                        Here's what's happening with your school's academic performance today.
                                    </p>
                                </div>
                                <div class="mt-4 md:mt-0 flex space-x-3">
                                    <a href="reports.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-indigo-700 bg-white hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white">
                                        View Reports
                                    </a>
                                    <a href="add_result.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-500 hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-400">
                                        Add New Result
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

                        <!-- Quick Actions -->
                        <div class="mb-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-3">Quick Actions</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                <a href="bulk_upload.php" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg p-4 text-center hover-scale card-hover">
                                    <i class="fas fa-upload text-2xl mb-2"></i>
                                    <p class="text-sm">Bulk Upload</p>
                                </a>
                                <a href="add_user.php" class="bg-green-600 hover:bg-green-700 text-white rounded-lg p-4 text-center hover-scale card-hover">
                                    <i class="fas fa-user-plus text-2xl mb-2"></i>
                                    <p class="text-sm">Add User</p>
                                </a>
                                <a href="add_exam.php" class="bg-purple-600 hover:bg-purple-700 text-white rounded-lg p-4 text-center hover-scale card-hover">
                                    <i class="fas fa-calendar-plus text-2xl mb-2"></i>
                                    <p class="text-sm">Add Exam</p>
                                </a>
                                <a href="reports.php" class="bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg p-4 text-center hover-scale card-hover">
                                    <i class="fas fa-chart-bar text-2xl mb-2"></i>
                                    <p class="text-sm">Reports</p>
                                </a>
                                <a href="settings.php" class="bg-gray-600 hover:bg-gray-700 text-white rounded-lg p-4 text-center hover-scale card-hover">
                                    <i class="fas fa-cog text-2xl mb-2"></i>
                                    <p class="text-sm">Settings</p>
                                </a>
                            </div>
                        </div>

                        <!-- Stats Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 stats-grid">
                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                            <i class="fas fa-user-graduate text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Students</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $counts['students']; ?></div>
                                                    <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                                        <i class="fas fa-arrow-up"></i>
                                                        <span class="sr-only">Increased by</span>
                                                        5%
                                                    </div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="students.php" class="font-medium text-blue-600 hover:text-blue-500">View all</a>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                            <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Teachers</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $counts['teachers']; ?></div>
                                                    <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                                        <i class="fas fa-arrow-up"></i>
                                                        <span class="sr-only">Increased by</span>
                                                        2%
                                                    </div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="teachers.php" class="font-medium text-blue-600 hover:text-blue-500">View all</a>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                            <i class="fas fa-clipboard-list text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Results</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $counts['results']; ?></div>
                                                    <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                                        <i class="fas fa-arrow-up"></i>
                                                        <span class="sr-only">Increased by</span>
                                                        12%
                                                    </div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="result.php" class="font-medium text-blue-600 hover:text-blue-500">View all</a>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                            <i class="fas fa-calendar-alt text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Exams</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $counts['exams']; ?></div>
                                                    <div class="ml-2 flex items-baseline text-sm font-semibold text-yellow-600">
                                                        <i class="fas fa-arrow-right"></i>
                                                        <span class="sr-only">No change</span>
                                                        0%
                                                    </div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="exams.php" class="font-medium text-blue-600 hover:text-blue-500">View all</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts and Tables -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Performance Chart -->
                            <div class="bg-white shadow rounded-lg p-4 hover-scale card-hover">
                                <div class="flex justify-between items-center mb-4">
                                    <h2 class="text-lg font-medium text-gray-900">Subject Performance</h2>
                                    <div class="flex space-x-2">
                                        <button class="text-sm text-gray-500 hover:text-gray-700 chart-filter active" data-period="week">Week</button>
                                        <button class="text-sm text-gray-500 hover:text-gray-700 chart-filter" data-period="month">Month</button>
                                        <button class="text-sm text-gray-500 hover:text-gray-700 chart-filter" data-period="year">Year</button>
                                    </div>
                                </div>
                                <div class="h-64">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                            </div>

                            <!-- Class GPA Chart -->
                            <div class="bg-white shadow rounded-lg p-4 hover-scale card-hover">
                                <div class="flex justify-between items-center mb-4">
                                    <h2 class="text-lg font-medium text-gray-900">Class-wise GPA</h2>
                                    <div class="flex space-x-2">
                                        <button class="text-sm text-gray-500 hover:text-gray-700 gpa-filter active" data-period="current">Current</button>
                                        <button class="text-sm text-gray-500 hover:text-gray-700 gpa-filter" data-period="previous">Previous</button>
                                    </div>
                                </div>
                                <div class="h-64">
                                    <canvas id="gpaChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Class Toppers and Academic Progress -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Class Toppers -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Class Toppers</h3>
                                        <p class="mt-1 text-sm text-gray-500">Students with highest GPA in each class</p>
                                    </div>
                                    <a href="toppers.php" class="text-sm text-blue-600 hover:text-blue-500">View all</a>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GPA</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php if (empty($class_toppers)): ?>
                                                    <tr>
                                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No data available.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($class_toppers as $topper): ?>
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($topper['class_name'] . ' ' . $topper['section']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <a href="student_profile.php?id=<?php echo $topper['student_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                                    <?php echo htmlspecialchars($topper['full_name'] . ' (' . $topper['roll_number'] . ')'); ?>
                                                                </a>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($topper['exam_name']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                    <?php echo number_format($topper['gpa'], 2); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Academic Progress -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Academic Progress</h3>
                                        <p class="mt-1 text-sm text-gray-500">Overall class performance metrics</p>
                                    </div>
                                    <a href="academic_progress.php" class="text-sm text-blue-600 hover:text-blue-500">View all</a>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. GPA</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php if (empty($academic_progress)): ?>
                                                    <tr>
                                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No data available.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($academic_progress as $progress): ?>
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($progress['class_name'] . ' ' . $progress['section']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($progress['exam_name']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo number_format($progress['avg_gpa'], 2); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="flex items-center">
                                                                    <?php
                                                                    $performance_percentage = ($progress['high_performers'] / max(1, $progress['student_count'])) * 100;
                                                                    $color_class = 'bg-red-500';
                                                                    if ($performance_percentage >= 70) {
                                                                        $color_class = 'bg-green-500';
                                                                    } elseif ($performance_percentage >= 40) {
                                                                $color_class = 'bg-yellow-500';
                                                            }
                                                            ?>
                                                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                                <div class="<?php echo $color_class; ?> h-2.5 rounded-full" style="width: <?php echo $performance_percentage; ?>%"></div>
                                                            </div>
                                                            <span class="ml-2 text-xs text-gray-500">
                                                                <?php echo $progress['high_performers']; ?>/<?php echo $progress['student_count']; ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities and Upcoming Exams -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Recent Activities -->
                    <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Recent Activities</h3>
                            <p class="mt-1 text-sm text-gray-500">Latest actions in the system</p>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flow-root">
                                <ul role="list" class="-mb-8">
                                    <?php if (empty($recent_activities)): ?>
                                        <li class="py-4">
                                            <div class="text-center text-sm text-gray-500">No recent activities.</div>
                                        </li>
                                    <?php else: ?>
                                        <?php foreach ($recent_activities as $index => $activity): ?>
                                            <li>
                                                <div class="relative pb-8">
                                                    <?php if ($index !== count($recent_activities) - 1): ?>
                                                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                                    <?php endif; ?>
                                                    <div class="relative flex space-x-3">
                                                        <div>
                                                            <?php 
                                                            $icon_class = 'bg-gray-400';
                                                            $icon = '<i class="fas fa-info text-white"></i>';
                                                            
                                                            if ($activity['type'] === 'result') {
                                                                $icon_class = 'bg-green-500';
                                                                $icon = '<i class="fas fa-clipboard-check text-white"></i>';
                                                            } elseif ($activity['type'] === 'user') {
                                                                $icon_class = 'bg-blue-500';
                                                                $icon = '<i class="fas fa-user-plus text-white"></i>';
                                                            } elseif ($activity['type'] === 'exam') {
                                                                $icon_class = 'bg-purple-500';
                                                                $icon = '<i class="fas fa-calendar-plus text-white"></i>';
                                                            }
                                                            ?>
                                                            <span class="h-8 w-8 rounded-full <?php echo $icon_class; ?> flex items-center justify-center ring-8 ring-white">
                                                                <?php echo $icon; ?>
                                                            </span>
                                                        </div>
                                                        <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                            <div>
                                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($activity['description']); ?></p>
                                                            </div>
                                                            <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                                <time datetime="<?php echo $activity['timestamp']; ?>"><?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?></time>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Exams -->
                    <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Upcoming Exams</h3>
                            <p class="mt-1 text-sm text-gray-500">Scheduled examinations</p>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <div class="overflow-x-auto">
                                <?php if (empty($upcoming_exams)): ?>
                                    <p class="text-center text-sm text-gray-500">No upcoming exams scheduled.</p>
                                <?php else: ?>
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($upcoming_exams as $exam): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <a href="exam_details.php?id=<?php echo $exam['exam_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                            <?php echo htmlspecialchars($exam['exam_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($exam['class_name'] . ' ' . $exam['section']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('M d, Y', strtotime($exam['start_date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                            Upcoming
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Health -->
                <div class="bg-white shadow rounded-lg overflow-hidden mb-6 hover-scale card-hover">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">System Health</h3>
                        <p class="mt-1 text-sm text-gray-500">System status and metrics</p>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                        <i class="fas fa-database text-white text-xl"></i>
                                    </div>
                                    <div class="ml-5">
                                        <p class="text-sm font-medium text-gray-500">Database Size</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo $system_health['database_size']; ?> MB</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                        <i class="fas fa-save text-white text-xl"></i>
                                    </div>
                                    <div class="ml-5">
                                        <p class="text-sm font-medium text-gray-500">Last Backup</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo date('M d, Y H:i', strtotime($system_health['last_backup'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                        <i class="fas fa-tasks text-white text-xl"></i>
                                    </div>
                                    <div class="ml-5">
                                        <p class="text-sm font-medium text-gray-500">Pending Tasks</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo $system_health['pending_tasks']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Initialize live clock
    function updateClock() {
        const now = new Date();
        document.getElementById('live-clock').textContent = now.toLocaleTimeString();
    }
    
    updateClock();
    setInterval(updateClock, 1000);

    // Performance Chart
    const ctx = document.getElementById('performanceChart').getContext('2d');
    const performanceChart = new Chart(ctx, {
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
                    beginAtZero: true
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

    // GPA Chart
    const gpaCtx = document.getElementById('gpaChart').getContext('2d');
    const gpaChart = new Chart(gpaCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($gpa_labels); ?>,
            datasets: [{
                label: 'Average GPA',
                data: <?php echo json_encode($gpa_data); ?>,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
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
                    max: 4
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

    // Chart filters
    document.querySelectorAll('.chart-filter').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.chart-filter').forEach(btn => btn.classList.remove('active', 'text-blue-600', 'font-medium'));
            this.classList.add('active', 'text-blue-600', 'font-medium');
            
            // Simulate data change (in a real app, you'd fetch new data via AJAX)
            const period = this.dataset.period;
            let newData = [];
            
            if (period === 'week') {
                newData = <?php echo json_encode($chart_data); ?>;
            } else if (period === 'month') {
                // Simulate different data for month view
                newData = <?php echo json_encode($chart_data); ?>.map(val => val * (0.8 + Math.random() * 0.4));
            } else if (period === 'year') {
                // Simulate different data for year view
                newData = <?php echo json_encode($chart_data); ?>.map(val => val * (0.6 + Math.random() * 0.8));
            }
            
            performanceChart.data.datasets[0].data = newData;
            performanceChart.update();
        });
    });

    // GPA filters
    document.querySelectorAll('.gpa-filter').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.gpa-filter').forEach(btn => btn.classList.remove('active', 'text-blue-600', 'font-medium'));
            this.classList.add('active', 'text-blue-600', 'font-medium');
            
            // Simulate data change
            const period = this.dataset.period;
            let newData = [];
            
            if (period === 'current') {
                newData = <?php echo json_encode($gpa_data); ?>;
            } else if (period === 'previous') {
                // Simulate different data for previous period
                newData = <?php echo json_encode($gpa_data); ?>.map(val => val * (0.85 + Math.random() * 0.3));
            }
            
            gpaChart.data.datasets[0].data = newData;
            gpaChart.update();
        });
    });

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

    // User menu toggle
    document.getElementById('user-menu-button').addEventListener('click', function() {
        document.getElementById('user-menu').classList.toggle('hidden');
    });

    // Notification dropdown toggle
    document.getElementById('notification-button').addEventListener('click', function() {
        document.getElementById('notification-dropdown').classList.toggle('hidden');
    });

    // Search modal
    document.getElementById('search-button').addEventListener('click', function() {
        document.getElementById('search-modal').classList.remove('hidden');
    });
    
    document.getElementById('close-search').addEventListener('click', function() {
        document.getElementById('search-modal').classList.add('hidden');
    });
    
    // Global search functionality
    document.getElementById('global-search').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const resultsContainer = document.getElementById('search-results');
        
        if (searchTerm.length < 2) {
            resultsContainer.innerHTML = '<p class="text-sm text-gray-500 p-2">Type at least 2 characters to search</p>';
            return;
        }
        
        // Simulate search results (in a real app, you'd fetch results via AJAX)
        resultsContainer.innerHTML = '<div class="p-2 text-sm text-gray-500">Searching...</div>';
        
        setTimeout(() => {
            // Mock search results
            if (searchTerm.includes('student') || searchTerm.includes('class') || searchTerm.includes('exam')) {
                resultsContainer.innerHTML = `
                    <div class="p-2 border-b">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Students</h4>
                        <a href="#" class="block py-1 text-sm text-blue-600 hover:bg-gray-50">John Doe (Class 10A)</a>
                        <a href="#" class="block py-1 text-sm text-blue-600 hover:bg-gray-50">Jane Smith (Class 9B)</a>
                    </div>
                    <div class="p-2 border-b">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Classes</h4>
                        <a href="#" class="block py-1 text-sm text-blue-600 hover:bg-gray-50">Class 10A</a>
                        <a href="#" class="block py-1 text-sm text-blue-600 hover:bg-gray-50">Class 9B</a>
                    </div>
                    <div class="p-2">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Exams</h4>
                        <a href="#" class="block py-1 text-sm text-blue-600 hover:bg-gray-50">Final Exam 2023</a>
                        <a href="#" class="block py-1 text-sm text-blue-600 hover:bg-gray-50">Mid-term Exam 2023</a>
                    </div>
                `;
            } else {
                resultsContainer.innerHTML = '<p class="text-sm text-gray-500 p-2">No results found for "' + searchTerm + '"</p>';
            }
        }, 500);
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
    
    darkModeToggle.addEventListener('click', toggleDarkMode);
    mobileDarkModeToggle.addEventListener('click', toggleDarkMode);

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const userMenu = document.getElementById('user-menu');
        const userMenuButton = document.getElementById('user-menu-button');
        const notificationDropdown = document.getElementById('notification-dropdown');
        const notificationButton = document.getElementById('notification-button');
        const searchModal = document.getElementById('search-modal');

        if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
            userMenu.classList.add('hidden');
        }

        if (!notificationButton.contains(event.target) && !notificationDropdown.contains(event.target)) {
            notificationDropdown.classList.add('hidden');
        }
        
        if (searchModal.classList.contains('hidden') === false && 
            !searchModal.querySelector('div > div').contains(event.target) && 
            !document.getElementById('search-button').contains(event.target)) {
            searchModal.classList.add('hidden');
        }
    });

</script>
</body>
</html>