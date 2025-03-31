<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get counts for dashboard
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

// Count students
$result = $conn->query("SELECT COUNT(*) as count FROM students");
if ($result) {
    $counts['students'] = $result->fetch_assoc()['count'];
}

// Count teachers
$result = $conn->query("SELECT COUNT(*) as count FROM teachers");
if ($result) {
    $counts['teachers'] = $result->fetch_assoc()['count'];
}

// Count classes
$result = $conn->query("SELECT COUNT(*) as count FROM classes");
if ($result) {
    $counts['classes'] = $result->fetch_assoc()['count'];
}

// Count subjects
$result = $conn->query("SELECT COUNT(*) as count FROM subjects");
if ($result) {
    $counts['subjects'] = $result->fetch_assoc()['count'];
}

// Count exams
$result = $conn->query("SELECT COUNT(*) as count FROM exams");
if ($result) {
    $counts['exams'] = $result->fetch_assoc()['count'];
}

// Count results
$result = $conn->query("SELECT COUNT(*) as count FROM results");
if ($result) {
    $counts['results'] = $result->fetch_assoc()['count'];
}

// Count active users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
if ($result) {
    $counts['active_users'] = $result->fetch_assoc()['count'];
}

// Count inactive users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'");
if ($result) {
    $counts['inactive_users'] = $result->fetch_assoc()['count'];
}

// Get recent notifications
$notifications = [];
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Get recent users
$recent_users = [];
$result = $conn->query("SELECT u.user_id, u.username, u.full_name, u.email, u.role, u.created_at FROM users u ORDER BY u.created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_users[] = $row;
}

// Get upcoming exams
$upcoming_exams = [];
$result = $conn->query("SELECT e.*, c.class_name, c.section FROM exams e JOIN classes c ON e.class_id = c.class_id WHERE e.status = 'upcoming' ORDER BY e.start_date ASC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $upcoming_exams[] = $row;
}

// Get class-wise toppers
$class_toppers = [];
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
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    $class_toppers[] = $row;
}

// Get overall academic progress data
$academic_progress = [];
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
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    $academic_progress[] = $row;
}

// Get recent activities
$recent_activities = [];
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
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    $recent_activities[] = $row;
}

// Get subject-wise performance data for charts
$subject_performance = [];
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
    LIMIT 5
");

while ($row = $result->fetch_assoc()) {
    $subject_performance[] = $row;
}

// Prepare data for charts
$chart_labels = [];
$chart_data = [];
$chart_colors = [
    'rgba(54, 162, 235, 0.2)',
    'rgba(255, 99, 132, 0.2)',
    'rgba(255, 206, 86, 0.2)',
    'rgba(75, 192, 192, 0.2)',
    'rgba(153, 102, 255, 0.2)'
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

// Get class-wise GPA data for charts
$class_gpa = [];
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
    LIMIT 5
");

while ($row = $result->fetch_assoc()) {
    $class_gpa[] = $row;
}

// Prepare data for GPA chart
$gpa_labels = [];
$gpa_data = [];

foreach ($class_gpa as $class) {
    $gpa_labels[] = $class['class_name'] . ' ' . $class['section'];
    $gpa_data[] = $class['avg_gpa'];
}

// Get system health metrics
$system_health = [
    'database_size' => 0,
    'last_backup' => 'Never',
    'pending_tasks' => 0
];

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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Result Management System</title>
    <script src="css\tailwind.css"></script>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdncdn.jsdelivr.net/npm/chart.js"></script>

    <style>
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
            transition: transform 0.3s ease;
        }

        .hover-scale:hover {
            transform: scale(1.03);
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 bg-gray-800">
                <div class="flex items-center justify-center h-16 bg-gray-900">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <div class="flex flex-col flex-grow px-4 mt-5">
                    <nav class="flex-1 space-y-1">
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
                        <a href="students.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-user-graduate mr-3"></i>
                            Students
                        </a>
                        <a href="teachers.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard-teacher mr-3"></i>
                            Teachers
                        </a>
<!-- i added  -->
                        <a href="subject.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard-teacher mr-3"></i>
                            Subjects
                        </a>
<!-- Added -->
                        <a href="classes.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard mr-3"></i>
                            Classes
                        </a>
                        <a href="exams.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            Exams
                        </a>
                        <a href="reports.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chart-bar mr-3"></i>
                            Reports
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-cog mr-3"></i>
                            Settings
                        </a>
                    </nav>
                    <div class="flex-shrink-0 block w-full">
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
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
                                            <p class="text-sm font-medium text-gray-900"><?php echo $notification['title']; ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $notification['message']; ?></p>
                                            <p class="text-xs text-gray-400 mt-1"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="px-4 py-2 border-t border-gray-200">
                                <a href="#" class="text-xs text-blue-600 hover:text-blue-500">View all notifications</a>
                            </div>
                        </div>

                        <!-- Profile dropdown -->
                        <div class="ml-3 relative">
                            <div>
                                <button type="button" class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="user-menu-button">
                                    <span class="sr-only">Open user menu</span>
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-600">
                                        <span class="text-sm font-medium leading-none text-white"><?php echo substr($_SESSION['full_name'], 0, 1); ?></span>
                                    </span>
                                </button>
                            </div>

                            <!-- Profile dropdown menu -->
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" id="user-menu" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Sign out</a>
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

                            <a href="teachers.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-chalkboard-teacher mr-3"></i>
                                Teachers
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
                        <!-- Quick Actions -->
                        <div class="mb-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-3">Quick Actions</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                <a href="bulk_upload.php" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg p-4 text-center hover-scale">
                                    <i class="fas fa-upload text-2xl mb-2"></i>
                                    <p class="text-sm">Bulk Upload</p>
                                </a>
                                <a href="add_user.php" class="bg-green-600 hover:bg-green-700 text-white rounded-lg p-4 text-center hover-scale">
                                    <i class="fas fa-user-plus text-2xl mb-2"></i>
                                    <p class="text-sm">Add User</p>
                                </a>
                                <a href="add_exam.php" class="bg-purple-600 hover:bg-purple-700 text-white rounded-lg p-4 text-center hover-scale">
                                    <i class="fas fa-calendar-plus text-2xl mb-2"></i>
                                    <p class="text-sm">Add Exam</p>
                                </a>
                                <a href="reports.php" class="bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg p-4 text-center hover-scale">
                                    <i class="fas fa-chart-bar text-2xl mb-2"></i>
                                    <p class="text-sm">Reports</p>
                                </a>
                                <a href="settings.php" class="bg-gray-600 hover:bg-gray-700 text-white rounded-lg p-4 text-center hover-scale">
                                    <i class="fas fa-cog text-2xl mb-2"></i>
                                    <p class="text-sm">Settings</p>
                                </a>
                            </div>
                        </div>

                        <!-- Stats Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 stats-grid">
                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
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

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
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

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
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

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
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
                            <div class="bg-white shadow rounded-lg p-4 hover-scale">
                                <h2 class="text-lg font-medium text-gray-900 mb-4">Subject Performance</h2>
                                <div class="h-64">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                            </div>

                            <!-- Class GPA Chart -->
                            <div class="bg-white shadow rounded-lg p-4 hover-scale">
                                <h2 class="text-lg font-medium text-gray-900 mb-4">Class-wise GPA</h2>
                                <div class="h-64">
                                    <canvas id="gpaChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Class Toppers and Academic Progress -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Class Toppers -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Class Toppers</h3>
                                    <p class="mt-1 text-sm text-gray-500">Students with highest GPA in each class</p>
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
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo $topper['class_name'] . ' ' . $topper['section']; ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo $topper['full_name'] . ' (' . $topper['roll_number'] . ')'; ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo $topper['exam_name']; ?>
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
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Academic Progress</h3>
                                    <p class="mt-1 text-sm text-gray-500">Overall class performance metrics</p>
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
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo $progress['class_name'] . ' ' . $progress['section']; ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo $progress['exam_name']; ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo number_format($progress['avg_gpa'], 2); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="flex items-center">
                                                                    <?php
                                                                    $performance_percentage = ($progress['high_performers'] / $progress['student_count']) * 100;
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
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
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
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)'
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
                }
            }
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

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('user-menu');
            const userMenuButton = document.getElementById('user-menu-button');
            const notificationDropdown = document.getElementById('notification-dropdown');
            const notificationButton = document.getElementById('notification-button');

            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }

            if (!notificationButton.contains(event.target) && !notificationDropdown.contains(event.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });
    </script>
</body>

</html>