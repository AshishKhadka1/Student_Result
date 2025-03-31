<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Get teacher details
$teacher = null;
$stmt = $conn->prepare("SELECT t.*, u.full_name, u.email FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE t.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $teacher = $result->fetch_assoc();
}
$stmt->close();

// Get assigned subjects
$subjects = [];
$stmt = $conn->prepare("SELECT ts.*, s.subject_name, c.class_name, c.section FROM teachersubjects ts 
                       JOIN subjects s ON ts.subject_id = s.subject_id 
                       JOIN classes c ON ts.academic_year = c.academic_year 
                       WHERE ts.teacher_id = ?");
$stmt->bind_param("i", $teacher['teacher_id']);
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
                       WHERE e.status = 'upcoming' 
                       ORDER BY e.start_date ASC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $upcoming_exams[] = $row;
}
$stmt->close();

// Get recent notifications
$notifications = [];
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Get recent results
$recent_results = [];
$stmt = $conn->prepare("SELECT r.*, s.subject_name, e.exam_name, st.roll_number, u.full_name as student_name 
                       FROM results r 
                       JOIN subjects s ON r.subject_id = s.subject_id 
                       JOIN exams e ON r.exam_id = e.exam_id 
                       JOIN students st ON r.student_id = st.student_id 
                       JOIN users u ON st.user_id = u.user_id 
                       JOIN teachersubjects ts ON s.subject_id = ts.subject_id 
                       WHERE ts.teacher_id = ? 
                       ORDER BY r.created_at DESC LIMIT 5");
$stmt->bind_param("i", $teacher['teacher_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_results[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <a href="teacher_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            Results
                        </a>
                        <a href="subject.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-book mr-3"></i>
                            Subjects
                        </a>
                        <a href="#" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-user-graduate mr-3"></i>
                            Students
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
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Teacher Dashboard</h1>
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
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-green-600">
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
                        <!-- Teacher Profile Card -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-20 w-20 rounded-full bg-green-600 flex items-center justify-center">
                                        <span class="text-2xl font-medium text-white"><?php echo substr($teacher['full_name'], 0, 1); ?></span>
                                    </div>
                                    <div class="ml-6">
                                        <h2 class="text-2xl font-bold text-gray-900"><?php echo $teacher['full_name']; ?></h2>
                                        <div class="mt-1 flex items-center">
                                            <span class="text-sm text-gray-600"><?php echo $teacher['email']; ?></span>
                                        </div>
                                        <div class="mt-1 flex items-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Teacher
                                            </span>
                                            <span class="ml-2 text-sm text-gray-500">Employee ID: <?php echo $teacher['employee_id']; ?></span>
                                            <span class="ml-2 text-sm text-gray-500">Department: <?php echo $teacher['department']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                            <i class="fas fa-book text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Assigned Subjects</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo count($subjects); ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="subject.php" class="font-medium text-blue-600 hover:text-blue-500">View all</a>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                            <i class="fas fa-calendar-alt text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Upcoming Exams</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo count($upcoming_exams); ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="#" class="font-medium text-blue-600 hover:text-blue-500">View all</a>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                            <i class="fas fa-clipboard-list text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Recent Results</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo count($recent_results); ?></div>
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
                        </div>

                        <!-- Assigned Subjects and Upcoming Exams -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Assigned Subjects -->
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Assigned Subjects</h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($subjects)): ?>
                                    <p class="text-gray-500">No subjects assigned yet.</p>
                                    <?php else: ?>
                                    <ul class="divide-y divide-gray-200">
                                        <?php foreach ($subjects as $subject): ?>
                                        <li class="py-4">
                                            <div class="flex space-x-3">
                                                <div class="flex-1 space-y-1">
                                                    <div class="flex items-center justify-between">
                                                        <h3 class="text-sm font-medium"><?php echo $subject['subject_name']; ?></h3>
                                                        <p class="text-sm text-gray-500"><?php echo $subject['academic_year']; ?></p>
                                                    </div>
                                                    <p class="text-sm text-gray-500">
                                                        <?php echo $subject['class_name'] . ' ' . $subject['section']; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Upcoming Exams -->
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Upcoming Exams</h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($upcoming_exams)): ?>
                                    <p class="text-gray-500">No upcoming exams scheduled.</p>
                                    <?php else: ?>
                                    <ul class="divide-y divide-gray-200">
                                        <?php foreach ($upcoming_exams as $exam): ?>
                                        <li class="py-4">
                                            <div class="flex space-x-3">
                                                <div class="flex-1 space-y-1">
                                                    <div class="flex items-center justify-between">
                                                        <h3 class="text-sm font-medium"><?php echo $exam['exam_name']; ?></h3>
                                                        <p class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($exam['start_date'])); ?></p>
                                                    </div>
                                                    <p class="text-sm text-gray-500">
                                                        <?php echo $exam['class_name'] . ' ' . $exam['section']; ?> | 
                                                        <?php echo ucfirst($exam['exam_type']); ?> | 
                                                        Total Marks: <?php echo $exam['total_marks']; ?>
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

                        <!-- Recent Results and Notifications -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Recent Results -->
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Recent Results</h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($recent_results)): ?>
                                    <p class="text-gray-500">No results added yet.</p>
                                    <?php else: ?>
                                    <ul class="divide-y divide-gray-200">
                                        <?php foreach ($recent_results as $result): ?>
                                        <li class="py-4">
                                            <div class="flex space-x-3">
                                                <div class="flex-1 space-y-1">
                                                    <div class="flex items-center justify-between">
                                                        <h3 class="text-sm font-medium"><?php echo $result['student_name']; ?> (<?php echo $result['roll_number']; ?>)</h3>
                                                        <p class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($result['created_at'])); ?></p>
                                                    </div>
                                                    <p class="text-sm text-gray-500">
                                                        <?php echo $result['subject_name']; ?> | 
                                                        <?php echo $result['exam_name']; ?> | 
                                                        Grade: <?php echo $result['grade']; ?> | 
                                                        Theory: <?php echo $result['theory_marks']; ?> | 
                                                        Practical: <?php echo $result['practical_marks']; ?>
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
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Recent Notifications</h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($notifications)): ?>
                                    <p class="text-gray-500">No notifications.</p>
                                    <?php else: ?>
                                    <ul class="divide-y divide-gray-200">
                                        <?php foreach ($notifications as $notification): ?>
                                        <li class="py-4">
                                            <div class="flex space-x-3">
                                                <div class="flex-shrink-0">
                                                    <?php if ($notification['notification_type'] == 'system'): ?>
                                                    <i class="fas fa-cog text-gray-400"></i>
                                                    <?php elseif ($notification['notification_type'] == 'exam'): ?>
                                                    <i class="fas fa-calendar-alt text-blue-500"></i>
                                                    <?php elseif ($notification['notification_type'] == 'result'): ?>
                                                    <i class="fas fa-clipboard-list text-green-500"></i>
                                                    <?php else: ?>
                                                    <i class="fas fa-bell text-yellow-500"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-1 space-y-1">
                                                    <div class="flex items-center justify-between">
                                                        <h3 class="text-sm font-medium"><?php echo $notification['title']; ?></h3>
                                                        <p class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($notification['created_at'])); ?></p>
                                                    </div>
                                                    <p class="text-sm text-gray-500"><?php echo $notification['message']; ?></p>
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
                </div>
            </main>
        </div>
    </div>
</body>
</html>

