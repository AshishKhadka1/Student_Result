<?php
// Include configuration file (which will handle session start)
require_once '../includes/config.php';
require_once '../includes/db_connetc.php';
// require_once '../includes/grade_calculator.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT t.*, u.full_name, u.email, u.profile_image 
                      FROM teachers t 
                      JOIN users u ON t.user_id = u.user_id 
                      WHERE t.user_id = ?");
if (!$stmt) {
    die("Error in query preparation: " . $conn->error);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$stmt->close();

// If teacher record doesn't exist, create one
if (!$teacher) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'teacher'");
    if (!$stmt) {
        die("Error in query preparation: " . $conn->error);
    }
    
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        $employee_id = 'T' . str_pad($teacher_id, 3, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("INSERT INTO teachers (user_id, department, designation, employee_id) 
                              VALUES (?, 'General', 'Teacher', ?)");
        if (!$stmt) {
            die("Error in query preparation: " . $conn->error);
        }
        
        $stmt->bind_param("is", $teacher_id, $employee_id);
        $stmt->execute();
        $stmt->close();
        
        // Get the newly created teacher record
        $stmt = $conn->prepare("SELECT t.*, u.full_name, u.email, u.profile_image 
                              FROM teachers t 
                              JOIN users u ON t.user_id = u.user_id 
                              WHERE t.user_id = ?");
        if (!$stmt) {
            die("Error in query preparation: " . $conn->error);
        }
        
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $teacher = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Get assigned classes
$stmt = $conn->prepare("SELECT DISTINCT c.*, 
                      (SELECT COUNT(*) FROM students s WHERE s.class_id = c.class_id) as student_count
                      FROM classes c 
                      JOIN teachersubjects ts ON c.class_id = ts.class_id 
                      WHERE ts.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)
                      ORDER BY c.class_name ASC");
if (!$stmt) {
    die("Error in query preparation: " . $conn->error);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assigned_classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get assigned subjects
$stmt = $conn->prepare("SELECT ts.*, s.subject_name, s.subject_code, c.class_name, c.section
                      FROM teachersubjects ts 
                      JOIN subjects s ON ts.subject_id = s.subject_id 
                      JOIN classes c ON ts.class_id = c.class_id 
                      WHERE ts.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)
                      ORDER BY c.class_name ASC, s.subject_name ASC");
if (!$stmt) {
    die("Error in query preparation: " . $conn->error);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assigned_subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get recent results
$stmt = $conn->prepare("SELECT r.*, s.subject_name, c.class_name, c.section, 
                      st.roll_number, u.full_name as student_name, e.exam_name
                      FROM results r 
                      JOIN subjects s ON r.subject_id = s.subject_id 
                      JOIN students st ON r.student_id = st.student_id 
                      JOIN users u ON st.user_id = u.user_id 
                      JOIN classes c ON st.class_id = c.class_id 
                      JOIN exams e ON r.exam_id = e.exam_id
                      WHERE r.created_by = (SELECT teacher_id FROM teachers WHERE user_id = ?)
                         OR r.updated_by = (SELECT teacher_id FROM teachers WHERE user_id = ?)
                      ORDER BY r.updated_at DESC LIMIT 5");
if (!$stmt) {
    die("Error in query preparation: " . $conn->error);
}

$stmt->bind_param("ii", $teacher_id, $teacher_id);
$stmt->execute();
$recent_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get upcoming exams
$stmt = $conn->prepare("SELECT e.*, c.class_name, c.section
                      FROM exams e 
                      JOIN classes c ON e.class_id = c.class_id 
                      WHERE e.class_id IN (
                          SELECT DISTINCT ts.class_id 
                          FROM teachersubjects ts 
                          WHERE ts.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)
                      ) 
                      AND e.start_date >= CURDATE()
                      ORDER BY e.start_date ASC LIMIT 5");
if (!$stmt) {
    die("Error in query preparation: " . $conn->error);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$upcoming_exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get pending tasks (results to be entered or updated)
$stmt = $conn->prepare("SELECT e.exam_id, e.exam_name, c.class_id, c.class_name, c.section, 
                      s.subject_id, s.subject_name, 
                      (SELECT COUNT(*) FROM students st WHERE st.class_id = c.class_id) as total_students,
                      (SELECT COUNT(*) FROM results r WHERE r.exam_id = e.exam_id AND r.subject_id = s.subject_id) as entered_results
                      FROM exams e 
                      JOIN classes c ON e.class_id = c.class_id
                      JOIN teachersubjects ts ON c.class_id = ts.class_id
                      JOIN subjects s ON ts.subject_id = s.subject_id
                      WHERE ts.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)
                      AND e.status = 'active'
                      HAVING entered_results < total_students OR entered_results = 0
                      ORDER BY e.start_date ASC");
if (!$stmt) {
    die("Error in query preparation: " . $conn->error);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$pending_tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get class performance statistics
$class_performance = [];
if (!empty($assigned_classes)) {
    $class_ids = array_column($assigned_classes, 'class_id');
    $class_ids_str = implode(',', $class_ids);
    
    $query = "SELECT c.class_id, c.class_name, c.section,
             AVG(r.percentage) as avg_percentage,
             COUNT(DISTINCT r.student_id) as students_with_results,
             SUM(CASE WHEN r.status = 'pass' THEN 1 ELSE 0 END) as passed_students,
             COUNT(DISTINCT r.student_id) as total_students,
             ROUND(AVG(r.grade_point), 2) as avg_gpa
             FROM classes c
             JOIN students s ON c.class_id = s.class_id
             JOIN results r ON s.student_id = r.student_id
             WHERE c.class_id IN ($class_ids_str)
             GROUP BY c.class_id
             ORDER BY avg_percentage DESC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        // If the query fails, try a simpler approach
        foreach ($class_ids as $class_id) {
            $class_info = null;
            foreach ($assigned_classes as $class) {
                if ($class['class_id'] == $class_id) {
                    $class_info = $class;
                    break;
                }
            }
            
            if ($class_info) {
                $class_performance[] = [
                    'class_id' => $class_id,
                    'class_name' => $class_info['class_name'],
                    'section' => $class_info['section'],
                    'avg_percentage' => 0,
                    'students_with_results' => 0,
                    'passed_students' => 0,
                    'total_students' => $class_info['student_count'],
                    'avg_gpa' => 0
                ];
            }
        }
    } else {
        $class_performance = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Get grade distribution for all assigned classes
$grade_distribution = [
    'A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0, 
    'C+' => 0, 'C' => 0, 'D+' => 0, 'D' => 0, 'F' => 0
];

if (!empty($assigned_classes)) {
    $class_ids = array_column($assigned_classes, 'class_id');
    $class_ids_str = implode(',', $class_ids);
    
    $query = "SELECT r.grade, COUNT(*) as count
             FROM results r
             JOIN students s ON r.student_id = s.student_id
             WHERE s.class_id IN ($class_ids_str)
             GROUP BY r.grade";
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (isset($grade_distribution[$row['grade']])) {
                $grade_distribution[$row['grade']] = $row['count'];
            }
        }
    }
}

// Count statistics
$total_students = 0;
foreach ($assigned_classes as $class) {
    $total_students += $class['student_count'];
}

$total_subjects = count($assigned_subjects);
$total_classes = count($assigned_classes);
$total_pending = count($pending_tasks);

// Get recent marks updates
$stmt = $conn->prepare("SELECT r.*, s.subject_name, c.class_name, c.section, 
                      st.roll_number, u.full_name as student_name, e.exam_name,
                      r.updated_at
                      FROM results r 
                      JOIN subjects s ON r.subject_id = s.subject_id 
                      JOIN students st ON r.student_id = st.student_id 
                      JOIN users u ON st.user_id = u.user_id 
                      JOIN classes c ON st.class_id = c.class_id 
                      JOIN exams e ON r.exam_id = e.exam_id
                      WHERE r.updated_by = (SELECT teacher_id FROM teachers WHERE user_id = ?)
                      ORDER BY r.updated_at DESC LIMIT 10");
if (!$stmt) {
    die("Error in query preparation: " . $conn->error);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$recent_updates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Log activity
$teacher_record_id = $teacher['teacher_id'] ?? 0;
$stmt = $conn->prepare("INSERT INTO teacher_activities (teacher_id, activity_type, description, timestamp) 
                      VALUES (?, 'login', 'Accessed teacher dashboard', NOW())");
if (!$stmt) {
    // If the query fails, just continue without logging
} else {
    $stmt->bind_param("i", $teacher_record_id);
    $stmt->execute();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | Result Management System</title>
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
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .grade-a-plus {
            background-color: #dcfce7;
            color: #166534;
        }
        .grade-a {
            background-color: #d1fae5;
            color: #065f46;
        }
        .grade-b-plus {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .grade-b {
            background-color: #bfdbfe;
            color: #1e3a8a;
        }
        .grade-c-plus {
            background-color: #fef9c3;
            color: #854d0e;
        }
        .grade-c {
            background-color: #fef3c7;
            color: #92400e;
        }
        .grade-d-plus {
            background-color: #ffedd5;
            color: #9a3412;
        }
        .grade-d {
            background-color: #fed7aa;
            color: #7c2d12;
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
        <?php include 'includes/teacher_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include 'includes/teacher_topbar.php'; ?>

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
                            <a href="teacher_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-md">
                                <i class="fas fa-tachometer-alt mr-3"></i>
                                Dashboard
                            </a>
                            <a href="edit_marks.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-edit mr-3"></i>
                                Edit Marks
                            </a>
                            <a href="class_performance.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-chart-bar mr-3"></i>
                                Class Performance
                            </a>
                            <a href="student_details.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-user-graduate mr-3"></i>
                                Student Details
                            </a>
                            <a href="profile.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-user-circle mr-3"></i>
                                Profile
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
                                        Welcome back, <?php echo isset($teacher['full_name']) ? htmlspecialchars($teacher['full_name']) : 'Teacher'; ?>!
                                    </h2>
                                    <p class="mt-2 text-sm text-blue-100 max-w-md">
                                        Here's what's happening with your classes and students today.
                                    </p>
                                </div>
                                <div class="mt-4 md:mt-0 flex space-x-3">
                                    <a href="edit_marks.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-500 hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-400">
                                        <i class="fas fa-edit mr-2"></i> Edit Marks
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
                                <a href="edit_marks.php" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg p-4 text-center hover-scale card-hover">
                                    <i class="fas fa-edit text-2xl mb-2"></i>
                                    <p class="text-sm">Edit Marks</p>
                                </a>
                                <a href="class_performance.php" class="bg-green-600 hover:bg-green-700 text-white rounded-lg p-4 text-center hover-scale card-hover">
                                    <i class="fas fa-chart-line text-2xl mb-2"></i>
                                    <p class="text-sm">Performance</p>
                                </a>
                                <a href="student_details.php" class="bg-purple-600 hover:bg-purple-700 text-white rounded-lg p-4 text-center hover-scale card-hover">
                                    <i class="fas fa-user-graduate text-2xl mb-2"></i>
                                    <p class="text-sm">Students</p>
                                </a>
                                <a href="profile.php" class="bg-gray-600 hover:bg-gray-700 text-white rounded-lg p-4 text-center hover-scale card-hover">
                                    <i class="fas fa-user-circle text-2xl mb-2"></i>
                                    <p class="text-sm">Profile</p>
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
                                                <dt class="text-sm font-medium text-gray-500 truncate">Total Students</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $total_students; ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="student_details.php" class="font-medium text-blue-600 hover:text-blue-500">View all</a>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                            <i class="fas fa-book text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Assigned Subjects</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $total_subjects; ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="edit_marks.php" class="font-medium text-blue-600 hover:text-blue-500">View all</a>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale card-hover">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                            <i class="fas fa-chalkboard text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Assigned Classes</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $total_classes; ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="class_performance.php" class="font-medium text-blue-600 hover:text-blue-500">View all</a>
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
                                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Tasks</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $total_pending; ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="#pending-tasks" class="font-medium text-blue-600 hover:text-blue-500">View all</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Tasks and Upcoming Exams -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Pending Tasks -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover" id="pending-tasks">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Pending Tasks</h3>
                                        <p class="mt-1 text-sm text-gray-500">Results that need to be entered or updated</p>
                                    </div>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php if (empty($pending_tasks)): ?>
                                                    <tr>
                                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No pending tasks.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($pending_tasks as $task): ?>
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($task['class_name'] . ' ' . $task['section']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($task['subject_name']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($task['exam_name']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="flex items-center">
                                                                    <?php 
                                                                    $progress = ($task['entered_results'] / $task['total_students']) * 100;
                                                                    ?>
                                                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                                                    </div>
                                                                    <span class="ml-2 text-xs text-gray-500">
                                                                        <?php echo $task['entered_results']; ?>/<?php echo $task['total_students']; ?>
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <a href="edit_marks.php?exam_id=<?php echo $task['exam_id']; ?>&subject_id=<?php echo $task['subject_id']; ?>&class_id=<?php echo $task['class_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                                    Edit Marks
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Upcoming Exams -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Upcoming Exams</h3>
                                    <p class="mt-1 text-sm text-gray-500">Scheduled examinations for your classes</p>
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
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($upcoming_exams as $exam): ?>
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($exam['exam_name']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($exam['class_name'] . ' ' . $exam['section']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo date('M d, Y', strtotime($exam['start_date'])); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo date('M d, Y', strtotime($exam['end_date'])); ?>
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

                        <!-- Recent Results and Class Performance -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Recent Results -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Recent Results</h3>
                                        <p class="mt-1 text-sm text-gray-500">Latest results entered or updated by you</p>
                                    </div>
                                    <a href="edit_marks.php" class="text-sm text-blue-600 hover:text-blue-500">View all</a>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="overflow-x-auto">
                                        <?php if (empty($recent_results)): ?>
                                            <p class="text-center text-sm text-gray-500">No results entered yet.</p>
                                        <?php else: ?>
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($recent_results as $result): ?>
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($result['student_name'] . ' (' . $result['roll_number'] . ')'); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($result['subject_name']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo $result['total_marks']; ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php 
                                                                $grade = $result['grade'] ?? '';
                                                                $grade_class = '';
                                                                
                                                                switch ($grade) {
                                                                    case 'A+': $grade_class = 'grade-a-plus'; break;
                                                                    case 'A': $grade_class = 'grade-a'; break;
                                                                    case 'B+': $grade_class = 'grade-b-plus'; break;
                                                                    case 'B': $grade_class = 'grade-b'; break;
                                                                    case 'C+': $grade_class = 'grade-c-plus'; break;
                                                                    case 'C': $grade_class = 'grade-c'; break;
                                                                    case 'D+': $grade_class = 'grade-d-plus'; break;
                                                                    case 'D': $grade_class = 'grade-d'; break;
                                                                    default: $grade_class = 'grade-f'; break;
                                                                }
                                                                ?>
                                                                <span class="grade-badge <?php echo $grade_class; ?>">
                                                                    <?php echo $grade; ?>
                                                                </span>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <a href="edit_marks.php?exam_id=<?php echo $result['exam_id']; ?>&subject_id=<?php echo $result['subject_id']; ?>&student_id=<?php echo $result['student_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                                    Edit
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Class Performance -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Class Performance</h3>
                                        <p class="mt-1 text-sm text-gray-500">Average performance by class</p>
                                    </div>
                                    <a href="class_performance.php" class="text-sm text-blue-600 hover:text-blue-500">View details</a>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($class_performance)): ?>
                                        <p class="text-center text-sm text-gray-500">No performance data available yet.</p>
                                    <?php else: ?>
                                        <div class="h-64">
                                            <canvas id="classPerformanceChart"></canvas>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Grade Distribution and Recent Updates -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Grade Distribution -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Grade Distribution</h3>
                                    <p class="mt-1 text-sm text-gray-500">Distribution of grades across all classes</p>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="h-64">
                                        <canvas id="gradeDistributionChart"></canvas>
                                    </div>
                                    <div class="mt-4 grid grid-cols-3 sm:grid-cols-5 gap-2 text-center">
                                        <div class="grade-badge grade-a-plus">A+</div>
                                        <div class="grade-badge grade-a">A</div>
                                        <div class="grade-badge grade-b-plus">B+</div>
                                        <div class="grade-badge grade-b">B</div>
                                        <div class="grade-badge grade-c-plus">C+</div>
                                        <div class="grade-badge grade-c">C</div>
                                        <div class="grade-badge grade-d-plus">D+</div>
                                        <div class="grade-badge grade-d">D</div>
                                        <div class="grade-badge grade-f">F</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Mark Updates -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Recent Mark Updates</h3>
                                    <p class="mt-1 text-sm text-gray-500">Latest marks updated by you</p>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="overflow-y-auto max-h-64">
                                        <?php if (empty($recent_updates)): ?>
                                            <p class="text-center text-sm text-gray-500">No recent updates.</p>
                                        <?php else: ?>
                                            <ul class="divide-y divide-gray-200">
                                                <?php foreach ($recent_updates as $update): ?>
                                                    <li class="py-3">
                                                        <div class="flex items-center space-x-4">
                                                            <div class="flex-shrink-0">
                                                                <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-blue-500 text-white">
                                                                    <i class="fas fa-edit"></i>
                                                                </span>
                                                            </div>
                                                            <div class="flex-1 min-w-0">
                                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                                    <?php echo htmlspecialchars($update['student_name']); ?>
                                                                </p>
                                                                <p class="text-sm text-gray-500 truncate">
                                                                    <?php echo htmlspecialchars($update['subject_name'] . ' - ' . $update['exam_name']); ?>
                                                                </p>
                                                            </div>
                                                            <div class="flex-shrink-0 text-right">
                                                                <p class="text-sm text-gray-500">
                                                                    <?php echo date('M d, H:i', strtotime($update['updated_at'])); ?>
                                                                </p>
                                                                <p class="text-sm font-medium">
                                                                    <span class="grade-badge <?php 
                                                                        $grade = $update['grade'] ?? '';
                                                                        switch ($grade) {
                                                                            case 'A+': echo 'grade-a-plus'; break;
                                                                            case 'A': echo 'grade-a'; break;
                                                                            case 'B+': echo 'grade-b-plus'; break;
                                                                            case 'B': echo 'grade-b'; break;
                                                                            case 'C+': echo 'grade-c-plus'; break;
                                                                            case 'C': echo 'grade-c'; break;
                                                                            case 'D+': echo 'grade-d-plus'; break;
                                                                            case 'D': echo 'grade-d'; break;
                                                                            default: echo 'grade-f'; break;
                                                                        }
                                                                    ?>">
                                                                        <?php echo $grade; ?>
                                                                    </span>
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
                        </div>

                        <!-- Assigned Classes and Subjects -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Assigned Classes -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Assigned Classes</h3>
                                    <p class="mt-1 text-sm text-gray-500">Classes you are teaching</p>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="overflow-x-auto">
                                        <?php if (empty($assigned_classes)): ?>
                                            <p class="text-center text-sm text-gray-500">No classes assigned yet.</p>
                                        <?php else: ?>
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($assigned_classes as $class): ?>
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($class['section']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo $class['student_count']; ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <a href="class_performance.php?class_id=<?php echo $class['class_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                                    View Performance
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Assigned Subjects -->
                            <div class="bg-white shadow rounded-lg overflow-hidden hover-scale card-hover">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Assigned Subjects</h3>
                                    <p class="mt-1 text-sm text-gray-500">Subjects you are teaching</p>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="overflow-x-auto">
                                        <?php if (empty($assigned_subjects)): ?>
                                            <p class="text-center text-sm text-gray-500">No subjects assigned yet.</p>
                                        <?php else: ?>
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($assigned_subjects as $subject): ?>
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($subject['subject_code']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($subject['class_name'] . ' ' . $subject['section']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <a href="edit_marks.php?subject_id=<?php echo $subject['subject_id']; ?>&class_id=<?php echo $subject['class_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                                    Edit Marks
                                                                </a>
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

        // Class Performance Chart
        <?php if (!empty($class_performance)): ?>
        const ctx = document.getElementById('classPerformanceChart').getContext('2d');
        const classPerformanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    foreach ($class_performance as $class) {
                        echo "'" . ($class['class_name'] ?? '') . " " . ($class['section'] ?? '') . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Average Percentage',
                    data: [
                        <?php 
                        foreach ($class_performance as $class) {
                            echo round(($class['avg_percentage'] ?? 0), 2) . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Average GPA',
                    data: [
                        <?php 
                        foreach ($class_performance as $class) {
                            echo round(($class['avg_gpa'] ?? 0), 2) . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    // Use a secondary y-axis for GPA
                    yAxisID: 'y1'
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
                            text: 'Percentage'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        max: 4,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        title: {
                            display: true,
                            text: 'GPA'
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeDistributionChart').getContext('2d');
        const gradeDistributionChart = new Chart(gradeCtx, {
            type: 'pie',
            data: {
                labels: ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'F'],
                datasets: [{
                    data: [
                        <?php echo $grade_distribution['A+'] . ', ' . 
                        $grade_distribution['A'] . ', ' . 
                        $grade_distribution['B+'] . ', ' . 
                        $grade_distribution['B'] . ', ' . 
                        $grade_distribution['C+'] . ', ' . 
                        $grade_distribution['C'] . ', ' . 
                        $grade_distribution['D+'] . ', ' . 
                        $grade_distribution['D'] . ', ' . 
                        $grade_distribution['F']; ?>
                    ],
                    backgroundColor: [
                        'rgba(0, 200, 83, 0.8)',   // A+
                        'rgba(76, 175, 80, 0.8)',  // A
                        'rgba(33, 150, 243, 0.8)', // B+
                        'rgba(3, 169, 244, 0.8)',  // B
                        'rgba(255, 193, 7, 0.8)',  // C+
                        'rgba(255, 152, 0, 0.8)',  // C
                        'rgba(255, 87, 34, 0.8)',  // D+
                        'rgba(244, 67, 54, 0.8)',  // D
                        'rgba(183, 28, 28, 0.8)'   // F
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
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Dark mode toggle
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        if (darkModeToggle) {
            const darkModeToggleDot = document.getElementById('dark-mode-toggle-dot');
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
        }
    </script>
</body>
</html>
