<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Get student details
$student = null;
$stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, c.academic_year, u.full_name, u.email 
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

// If student data isn't found, use placeholder
if (!$student) {
    die("Student record not found. Please contact administrator.");
}

// Get subjects for the student's class
$subjects = [];
$stmt = $conn->prepare("SELECT s.* FROM subjects s 
                       JOIN exams e ON e.class_id = ? 
                       GROUP BY s.subject_id 
                       ORDER BY s.subject_name");
$stmt->bind_param("i", $student['class_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Get upcoming exams
$upcoming_exams = [];
$stmt = $conn->prepare("SELECT e.*, c.class_name, c.section FROM exams e 
                       JOIN classes c ON e.class_id = c.class_id 
                       WHERE e.class_id = ? AND e.status = 'upcoming' 
                       ORDER BY e.start_date ASC LIMIT 5");
$stmt->bind_param("i", $student['class_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $upcoming_exams[] = $row;
}
$stmt->close();

// Get recent results
$recent_results = [];
$stmt = $conn->prepare("SELECT r.*, s.subject_name, e.exam_name 
                       FROM results r 
                       JOIN subjects s ON r.subject_id = s.subject_id 
                       JOIN exams e ON r.exam_id = e.exam_id 
                       WHERE r.student_id = ? 
                       ORDER BY r.created_at DESC LIMIT 10");
$stmt->bind_param("s", $student['student_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_results[] = $row;
}
$stmt->close();

// Get notifications
$notifications = [];
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Calculate overall performance
$overall_performance = [
    'total_subjects' => count($subjects),
    'subjects_with_results' => 0,
    'total_marks' => 0,
    'obtained_marks' => 0,
    'average_percentage' => 0,
    'average_grade' => 'N/A',
    'pass_count' => 0,
    'fail_count' => 0
];

if (!empty($recent_results)) {
    $overall_performance['subjects_with_results'] = count($recent_results);
    
    foreach ($recent_results as $result) {
        $overall_performance['obtained_marks'] += $result['theory_marks'] + ($result['practical_marks'] ?? 0);
        $overall_performance['total_marks'] += 100; // Assuming each subject is out of 100
        
        if ($result['grade'] != 'F') {
            $overall_performance['pass_count']++;
        } else {
            $overall_performance['fail_count']++;
        }
    }
    
    if ($overall_performance['total_marks'] > 0) {
        $overall_performance['average_percentage'] = ($overall_performance['obtained_marks'] / $overall_performance['total_marks']) * 100;
        
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
    <title>Student Dashboard | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a href="student_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="view_result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            My Results
                        </a>
                        <a href="track_progress.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chart-line mr-3"></i>
                            Track Progress
                        </a>
                        <a href="download_options.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-download mr-3"></i>
                            Download Options
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
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Student Dashboard</h1>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
                        <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <span class="sr-only">View notifications</span>
                            <i class="fas fa-bell"></i>
                        </button>

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
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Student Profile Card -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-20 w-20 rounded-full bg-blue-600 flex items-center justify-center">
                                        <span class="text-2xl font-medium text-white"><?php echo substr($student['full_name'], 0, 1); ?></span>
                                    </div>
                                    <div class="ml-6">
                                        <h2 class="text-2xl font-bold text-gray-900"><?php echo $student['full_name']; ?></h2>
                                        <div class="mt-1 flex items-center">
                                            <span class="text-sm text-gray-600"><?php echo $student['email']; ?></span>
                                        </div>
                                        <div class="mt-1 flex items-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Student
                                            </span>
                                            <span class="ml-2 text-sm text-gray-500">Roll No: <?php echo $student['roll_number']; ?></span>
                                            <span class="ml-2 text-sm text-gray-500">Reg No: <?php echo $student['registration_number']; ?></span>
                                            <span class="ml-2 text-sm text-gray-500">Class: <?php echo $student['class_name'] . ' ' . $student['section']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <a href="view_result.php" class="bg-blue-100 hover:bg-blue-200 rounded-lg p-6 flex items-center justify-center flex-col hover-scale">
                                <i class="fas fa-clipboard-list text-blue-600 text-4xl mb-2"></i>
                                <h3 class="text-lg font-medium text-blue-900">My Results</h3>
                                <p class="text-sm text-gray-500 mt-1">View your exam results</p>
                            </a>
                            <a href="track_progress.php" class="bg-green-100 hover:bg-green-200 rounded-lg p-6 flex items-center justify-center flex-col hover-scale">
                                <i class="fas fa-chart-line text-green-600 text-4xl mb-2"></i>
                                <h3 class="text-lg font-medium text-green-900">Track Progress</h3>
                                <p class="text-sm text-gray-500 mt-1">Monitor your academic progress</p>
                            </a>
                            <a href="download_options.php" class="bg-yellow-100 hover:bg-yellow-200 rounded-lg p-6 flex items-center justify-center flex-col hover-scale">
                                <i class="fas fa-download text-yellow-600 text-4xl mb-2"></i>
                                <h3 class="text-lg font-medium text-yellow-900">Download Options</h3>
                                <p class="text-sm text-gray-500 mt-1">Download result sheets and reports</p>
                            </a>
                        </div>

                        <!-- Notifications -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Recent Notifications</h3>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <ul class="divide-y divide-gray-200">
                                    <li class="py-4">
                                        <div class="flex space-x-3">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-info-circle text-blue-500"></i>
                                            </div>
                                            <div class="flex-1 space-y-1">
                                                <div class="flex items-center justify-between">
                                                    <h3 class="text-sm font-medium">New Exam Scheduled</h3>
                                                    <p class="text-sm text-gray-500">Mar 15, 2024</p>
                                                </div>
                                                <p class="text-sm text-gray-500">Your final exam has been scheduled for March 15th.</p>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="py-4">
                                        <div class="flex space-x-3">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-clipboard-list text-green-500"></i>
                                            </div>
                                            <div class="flex-1 space-y-1">
                                                <div class="flex items-center justify-between">
                                                    <h3 class="text-sm font-medium">Results Published</h3>
                                                    <p class="text-sm text-gray-500">Feb 28, 2024</p>
                                                </div>
                                                <p class="text-sm text-gray-500">Results for the mid-term exam have been published.</p>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>

