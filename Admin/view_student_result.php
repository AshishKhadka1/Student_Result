<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection with error handling
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

// Get all sections (unique values)
$sections = [];
try {
    $sql = "SELECT DISTINCT section FROM Classes ORDER BY section";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row['section'];
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading sections: " . $e->getMessage();
}

// Initialize filter variables
$selected_class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$selected_section = isset($_GET['section']) ? $_GET['section'] : '';
$search_name = isset($_GET['student_name']) ? $_GET['student_name'] : '';
$search_roll = isset($_GET['roll_number']) ? $_GET['roll_number'] : '';
$selected_student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';

// Get students based on filters
$students = [];
$query_params = [];
$query_types = "";

$query = "SELECT s.student_id, s.roll_number, u.full_name, c.class_name, c.section, c.academic_year
          FROM Students s
          JOIN Users u ON s.user_id = u.user_id
          JOIN Classes c ON s.class_id = c.class_id
          WHERE 1=1";

if (!empty($selected_class_id)) {
    $query .= " AND c.class_id = ?";
    $query_params[] = $selected_class_id;
    $query_types .= "i";
}

if (!empty($selected_section)) {
    $query .= " AND c.section = ?";
    $query_params[] = $selected_section;
    $query_types .= "s";
}

if (!empty($search_name)) {
    $query .= " AND u.full_name LIKE ?";
    $query_params[] = "%$search_name%";
    $query_types .= "s";
}

if (!empty($search_roll)) {
    $query .= " AND s.roll_number LIKE ?";
    $query_params[] = "%$search_roll%";
    $query_types .= "s";
}

$query .= " ORDER BY c.class_name, c.section, s.roll_number";

try {
    $stmt = $conn->prepare($query);
    
    if (!empty($query_params)) {
        $stmt->bind_param($query_types, ...$query_params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading students: " . $e->getMessage();
}

// Get student details and results if a student is selected
$student_details = null;
$student_results = [];
$exams_taken = [];

if ($selected_student_id) {
    try {
        // Get student details
        $stmt = $conn->prepare("
            SELECT s.student_id, s.roll_number, u.full_name, u.email, u.phone, 
                   c.class_id, c.class_name, c.section, c.academic_year
            FROM Students s
            JOIN Users u ON s.user_id = u.user_id
            JOIN Classes c ON s.class_id = c.class_id
            WHERE s.student_id = ?
        ");
        $stmt->bind_param("s", $selected_student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $student_details = $result->fetch_assoc();
        }
        $stmt->close();
        
        // Get exams taken by the student
        $stmt = $conn->prepare("
            SELECT DISTINCT e.exam_id, e.exam_name, e.exam_type, e.academic_year, e.exam_date
            FROM Results r
            JOIN Exams e ON r.exam_id = e.exam_id
            WHERE r.student_id = ?
            ORDER BY e.exam_date DESC
        ");
        $stmt->bind_param("s", $selected_student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $exams_taken[] = $row;
        }
        $stmt->close();
        
        // Get student results for all exams
        $stmt = $conn->prepare("
            SELECT r.*, s.subject_name, s.subject_code, e.exam_name, e.exam_type
            FROM Results r
            JOIN Subjects s ON r.subject_id = s.subject_id
            JOIN Exams e ON r.exam_id = e.exam_id
            WHERE r.student_id = ?
            ORDER BY e.exam_date DESC, s.subject_name
        ");
        $stmt->bind_param("s", $selected_student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $student_results[] = $row;
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error loading student details: " . $e->getMessage();
    }
}

// Get performance summary
$performance_summary = [];
if ($selected_student_id && !empty($exams_taken)) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                sp.exam_id,
                e.exam_name,
                sp.average_marks,
                sp.gpa,
                sp.rank,
                (SELECT COUNT(*) FROM student_performance sp2 JOIN students s2 ON sp2.student_id = s2.student_id WHERE sp2.exam_id = sp.exam_id AND s2.class_id = ?) as total_students
            FROM 
                student_performance sp
            JOIN 
                Exams e ON sp.exam_id = e.exam_id
            WHERE 
                sp.student_id = ?
            ORDER BY 
                e.exam_date DESC
        ");
        $stmt->bind_param("is", $student_details['class_id'], $selected_student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $performance_summary[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error loading performance summary: " . $e->getMessage();
    }
}

// Calculate overall statistics
$overall_stats = [
    'total_exams' => count($exams_taken),
    'total_subjects' => 0,
    'average_gpa' => 0,
    'average_percentage' => 0,
    'highest_marks' => 0,
    'lowest_marks' => 100,
    'pass_count' => 0,
    'fail_count' => 0
];

if (!empty($student_results)) {
    $total_gpa = 0;
    $total_percentage = 0;
    $subject_count = 0;
    $exam_subject_count = [];
    
    foreach ($student_results as $result) {
        $subject_count++;
        
        // Track subjects per exam for accurate averaging
        if (!isset($exam_subject_count[$result['exam_id']])) {
            $exam_subject_count[$result['exam_id']] = 0;
        }
        $exam_subject_count[$result['exam_id']]++;
        
        // Calculate percentage
        $total_marks = $result['theory_marks'] + $result['practical_marks'];
        $percentage = ($total_marks / ($result['total_marks'] ?? 100)) * 100;
        $total_percentage += $percentage;
        
        // Track highest and lowest marks
        if ($percentage > $overall_stats['highest_marks']) {
            $overall_stats['highest_marks'] = $percentage;
        }
        if ($percentage < $overall_stats['lowest_marks']) {
            $overall_stats['lowest_marks'] = $percentage;
        }
        
        // Track pass/fail
        if ($result['is_pass']) {
            $overall_stats['pass_count']++;
        } else {
            $overall_stats['fail_count']++;
        }
        
        // Add to GPA total
        $total_gpa += $result['gpa'] ?? 0;
    }
    
    $overall_stats['total_subjects'] = count($exam_subject_count);
    
    if ($subject_count > 0) {
        $overall_stats['average_percentage'] = $total_percentage / $subject_count;
        $overall_stats['average_gpa'] = $total_gpa / $subject_count;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student Results | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }
        
        .print-only {
            display: none;
        }
        
        /* Grade colors */
        .grade-a-plus {
            background-color: #c6f6d5;
            color: #22543d;
        }
        
        .grade-a {
            background-color: #d0f0fd;
            color: #2c5282;
        }
        
        .grade-b-plus {
            background-color: #e9d8fd;
            color: #553c9a;
        }
        
        .grade-b {
            background-color: #fefcbf;
            color: #744210;
        }
        
        .grade-c-plus, .grade-c {
            background-color: #fed7aa;
            color: #7b341e;
        }
        
        .grade-d {
            background-color: #fed7d7;
            color: #822727;
        }
        
        .grade-f {
            background-color: #feb2b2;
            color: #822727;
        }
    </style>
</head>
<body class="bg-gray-100" id="body">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0 no-print">
            <div class="flex flex-col w-64 bg-gray-800">
                <div class="flex items-center justify-center h-16 bg-gray-900">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <div class="flex flex-col flex-grow px-4 mt-5 overflow-y-auto">
                    <nav class="flex-1 space-y-1">
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            <span class="truncate">Dashboard</span>
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            <span class="truncate">Manage Results</span>
                        </a>
                        <a href="view_student_results.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md group">
                            <i class="fas fa-user-graduate mr-3"></i>
                            <span class="truncate">Student Results</span>
                        </a>
                        <a href="bulk_upload.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-upload mr-3"></i>
                            <span class="truncate">Bulk Upload</span>
                        </a>
                        
                        <div class="mt-4">
                            <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Management</p>
                        </div>
                        
                        <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-users mr-3"></i>
                            <span class="truncate">Users</span>
                        </a>
                        <a href="students.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-user-graduate mr-3"></i>
                            <span class="truncate">Students</span>
                        </a>
                        <a href="teachers.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-chalkboard-teacher mr-3"></i>
                            <span class="truncate">Teachers</span>
                        </a>
                        <a href="classes.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-chalkboard mr-3"></i>
                            <span class="truncate">Classes</span>
                        </a>
                        <a href="exams.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            <span class="truncate">Exams</span>
                        </a>
                        <a href="reports.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-chart-bar mr-3"></i>
                            <span class="truncate">Reports</span>
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-cog mr-3"></i>
                            <span class="truncate">Settings</span>
                        </a>
                    </nav>
                    <div class="flex-shrink-0 block w-full">
                        <div class="flex items-center justify-between px-4 py-2 mt-2">
                            <span class="text-sm text-gray-400">Dark Mode</span>
                            <button id="dark-mode-toggle" class="w-10 h-5 rounded-full bg-gray-700 flex items-center transition duration-300 focus:outline-none">
                                <div id="dark-mode-toggle-dot" class="w-4 h-4 bg-white rounded-full transform translate-x-0.5 transition duration-300"></div>
                            </button>
                        </div>
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            <span class="truncate">Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

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
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">View Student Results</h1>
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
                            <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-tachometer-alt mr-3"></i>
                                Dashboard
                            </a>
                            <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-clipboard-list mr-3"></i>
                                Manage Results
                            </a>
                            <a href="view_student_results.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                                <i class="fas fa-user-graduate mr-3"></i>
                                Student Results
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

                        <!-- Filter Section -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale no-print">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Search Student Results</h2>
                            <form action="view_student_results.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                    <select id="class_id" name="class_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Classes</option>
                                        <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" <?php echo ($selected_class_id == $class['class_id']) ? 'selected' : ''; ?>>
                                            <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="section" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                    <select id="section" name="section" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Sections</option>
                                        <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo $section; ?>" <?php echo ($selected_section == $section) ? 'selected' : ''; ?>>
                                            <?php echo $section; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="student_name" class="block text-sm font-medium text-gray-700 mb-1">Student Name</label>
                                    <input type="text" id="student_name" name="student_name" value="<?php echo $search_name; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="Search by name">
                                </div>
                                
                                <div>
                                    <label for="roll_number" class="block text-sm font-medium text-gray-700 mb-1">Roll Number</label>
                                    <input type="text" id="roll_number" name="roll_number" value="<?php echo $search_roll; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="Search by roll number">
                                </div>
                                
                                <div class="flex items-end">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-search mr-2"></i> Search
                                    </button>
                                    <a href="view_student_results.php" class="ml-2 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-redo mr-2"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Students List -->
                        <?php if (!empty($students) && empty($selected_student_id)): ?>
                        <div class="bg-white shadow rounded-lg overflow-hidden hover-scale no-print">
                            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                                <h2 class="text-lg font-medium text-gray-900">Students List</h2>
                                <p class="mt-1 text-sm text-gray-500">
                                    Select a student to view their results
                                </p>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($students as $student): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['roll_number']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['full_name']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['class_name']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['section']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['academic_year']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="view_student_results.php?student_id=<?php echo $student['student_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                        <i class="fas fa-eye mr-1"></i> View Results
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Student Details and Results -->
                        <?php if ($student_details): ?>
                        <div class="mb-4 flex justify-end space-x-2 no-print">
                            <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-print mr-2"></i> Print Results
                            </button>
                            <a href="view_student_results.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-arrow-left mr-2"></i> Back to List
                            </a>
                        </div>
                        
                        <!-- Print Header (Only visible when printing) -->
                        <div class="print-only mb-6">
                            <div class="text-center">
                                <h1 class="text-2xl font-bold">School Result Management System</h1>
                                <p class="text-lg">Student Result Report</p>
                                <p>Printed on: <?php echo date('F j, Y'); ?></p>
                            </div>
                        </div>
                        
                        <!-- Student Information Card -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale">
                            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900"><?php echo $student_details['full_name']; ?></h2>
                                    <p class="text-sm text-gray-500">Roll Number: <?php echo $student_details['roll_number']; ?></p>
                                </div>
                                <div class="mt-4 md:mt-0">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        <?php echo $student_details['class_name'] . ' ' . $student_details['section']; ?>
                                    </span>
                                    <span class="ml-2 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        <?php echo $student_details['academic_year']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="text-sm font-medium text-gray-500 mb-1">Email</h3>
                                    <p class="text-base font-medium text-gray-900"><?php echo $student_details['email'] ?? 'Not Available'; ?></p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="text-sm font-medium text-gray-500 mb-1">Phone</h3>
                                    <p class="text-base font-medium text-gray-900"><?php echo $student_details['phone'] ?? 'Not Available'; ?></p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="text-sm font-medium text-gray-500 mb-1">Total Exams</h3>
                                    <p class="text-base font-medium text-gray-900"><?php echo $overall_stats['total_exams']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Performance Summary -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Performance Summary</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <h3 class="text-sm font-medium text-blue-800 mb-1">Average GPA</h3>
                                    <p class="text-2xl font-bold text-blue-900"><?php echo number_format($overall_stats['average_gpa'], 2); ?></p>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <h3 class="text-sm font-medium text-green-800 mb-1">Average Percentage</h3>
                                    <p class="text-2xl font-bold text-green-900"><?php echo number_format($overall_stats['average_percentage'], 2); ?>%</p>
                                </div>
                                <div class="bg-purple-50 p-4 rounded-lg">
                                    <h3 class="text-sm font-medium text-purple-800 mb-1">Subjects Passed</h3>
                                    <p class="text-2xl font-bold text-purple-900"><?php echo $overall_stats['pass_count']; ?>/<?php echo $overall_stats['pass_count'] + $overall_stats['fail_count']; ?></p>
                                </div>
                                <div class="bg-yellow-50 p-4 rounded-lg">
                                    <h3 class="text-sm font-medium text-yellow-800 mb-1">Highest Marks</h3>
                                    <p class="text-2xl font-bold text-yellow-900"><?php echo number_format($overall_stats['highest_marks'], 2); ?>%</p>
                                </div>
                            </div>
                            
                            <?php if (!empty($performance_summary)): ?>
                            <h3 class="text-md font-medium text-gray-900 mb-2">Exam Performance</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Marks</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GPA</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($performance_summary as $performance): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $performance['exam_name']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($performance['average_marks'], 2); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($performance['gpa'], 2); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $performance['rank']; ?> / <?php echo $performance['total_students']; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Performance Chart (Only visible in non-print mode) -->
                            <div class="mt-6 no-print">
                                <h3 class="text-md font-medium text-gray-900 mb-2">Performance Trend</h3>
                                <div class="h-64">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Exam Results -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Exam Results</h2>
                            
                            <?php if (empty($exams_taken)): ?>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <p class="text-sm text-yellow-700">No exam results found for this student.</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($exams_taken as $exam): ?>
                                <div class="mb-6">
                                    <h3 class="text-md font-medium text-gray-900 mb-2"><?php echo $exam['exam_name']; ?> (<?php echo $exam['exam_type']; ?> - <?php echo $exam['academic_year']; ?>)</h3>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Theory</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Practical</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php 
                                                $exam_results = array_filter($student_results, function($result) use ($exam) {
                                                    return $result['exam_id'] == $exam['exam_id'];
                                                });
                                                
                                                if (empty($exam_results)): 
                                                ?>
                                                <tr>
                                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No results found for this exam.</td>
                                                </tr>
                                                <?php else: ?>
                                                    <?php foreach ($exam_results as $result): ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                            <?php echo $result['subject_name']; ?> (<?php echo $result['subject_code']; ?>)
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            <?php echo $result['theory_marks']; ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            <?php echo $result['practical_marks']; ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            <?php echo $result['theory_marks'] + $result['practical_marks']; ?> / <?php echo $result['total_marks']; ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php 
                                                            $grade_class = '';
                                                            switch ($result['grade']) {
                                                                case 'A+': $grade_class = 'grade-a-plus'; break;
                                                                case 'A': $grade_class = 'grade-a'; break;
                                                                case 'B+': $grade_class = 'grade-b-plus'; break;
                                                                case 'B': $grade_class = 'grade-b'; break;
                                                                case 'C+': $grade_class = 'grade-c-plus'; break;
                                                                case 'C': $grade_class = 'grade-c'; break;
                                                                case 'D': $grade_class = 'grade-d'; break;
                                                                case 'F': $grade_class = 'grade-f'; break;
                                                            }
                                                            ?>
                                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $grade_class; ?>">
                                                                <?php echo $result['grade']; ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php if ($result['is_pass']): ?>
                                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                Pass
                                                            </span>
                                                            <?php else: ?>
                                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                                Fail
                                                            </span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Print Footer -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale print-only">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                                <div class="text-center">
                                    <div class="border-t border-gray-300 mt-16 pt-1">
                                        <p class="text-sm text-gray-600">Class Teacher's Signature</p>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="border-t border-gray-300 mt-16 pt-1">
                                        <p class="text-sm text-gray-600">Principal's Signature</p>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="border-t border-gray-300 mt-16 pt-1">
                                        <p class="text-sm text-gray-600">Parent's Signature</p>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mt-4 text-center">This is a computer-generated result card and does not require a signature.</p>
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
            
            // Performance Chart
            <?php if (isset($performance_summary) && !empty($performance_summary)): ?>
            const ctx = document.getElementById('performanceChart');
            if (ctx) {
                const examNames = <?php echo json_encode(array_column($performance_summary, 'exam_name')); ?>;
                const gpas = <?php echo json_encode(array_column($performance_summary, 'gpa')); ?>;
                const avgMarks = <?php echo json_encode(array_column($performance_summary, 'average_marks')); ?>;
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: examNames,
                        datasets: [
                            {
                                label: 'GPA',
                                data: gpas,
                                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                borderColor: 'rgba(59, 130, 246, 1)',
                                borderWidth: 2,
                                yAxisID: 'y',
                                tension: 0.3
                            },
                            {
                                label: 'Average Marks',
                                data: avgMarks,
                                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                                borderColor: 'rgba(16, 185, 129, 1)',
                                borderWidth: 2,
                                yAxisID: 'y1',
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'GPA'
                                },
                                min: 0,
                                max: 4
                            },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Average Marks'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>