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
                        <a href="#" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            My Results
                        </a>
                        <a href="#" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            Exam Schedule
                        </a>
                        <a href="#" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-book mr-3"></i>
                            Subjects
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-cog mr-3"></i>
                            Settings
                        </a>
                    </nav>
                    <div class="flex-shrink-0 block w-full">
                        <a href="../login.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
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

                        <!-- Performance Overview -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Performance Overview</h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="bg-blue-50 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-blue-800 mb-2">Average Grade</h4>
                                        <p class="text-3xl font-bold text-blue-600"><?php echo $overall_performance['average_grade']; ?></p>
                                    </div>
                                    <div class="bg-green-50 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-green-800 mb-2">Average Percentage</h4>
                                        <p class="text-3xl font-bold text-green-600"><?php echo number_format($overall_performance['average_percentage'], 2); ?>%</p>
                                    </div>
                                    <div class="bg-yellow-50 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-yellow-800 mb-2">Subjects Passed</h4>
                                        <p class="text-3xl font-bold text-yellow-600"><?php echo $overall_performance['pass_count']; ?>/<?php echo $overall_performance['subjects_with_results']; ?></p>
                                    </div>
                                    <div class="bg-purple-50 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-purple-800 mb-2">Total Subjects</h4>
                                        <p class="text-3xl font-bold text-purple-600"><?php echo $overall_performance['total_subjects']; ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($recent_results)): ?>
                                <div class="mt-6">
                                    <canvas id="performanceChart" height="100"></canvas>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Results and Upcoming Exams -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Recent Results -->
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Recent Results</h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($recent_results)): ?>
                                    <p class="text-gray-500">No results available yet.</p>
                                    <?php else: ?>
                                    <ul class="divide-y divide-gray-200">
                                        <?php foreach ($recent_results as $result): ?>
                                        <li class="py-4">
                                            <div class="flex space-x-3">
                                                <div class="flex-1 space-y-1">
                                                    <div class="flex items-center justify-between">
                                                        <h3 class="text-sm font-medium"><?php echo $result['subject_name']; ?></h3>
                                                        <p class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($result['created_at'])); ?></p>
                                                    </div>
                                                    <p class="text-sm text-gray-500">
                                                        <?php echo $result['exam_name']; ?> | 
                                                        Theory: <?php echo $result['theory_marks']; ?> | 
                                                        Practical: <?php echo $result['practical_marks'] ?? 'N/A'; ?>
                                                    </p>
                                                    <div class="mt-1">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            <?php 
                                                            $grade = $result['grade'];
                                                            if ($grade == 'A+' || $grade == 'A') echo 'bg-green-100 text-green-800';
                                                            elseif ($grade == 'B+' || $grade == 'B') echo 'bg-blue-100 text-blue-800';
                                                            elseif ($grade == 'C+' || $grade == 'C') echo 'bg-yellow-100 text-yellow-800';
                                                            elseif ($grade == 'D') echo 'bg-orange-100 text-orange-800';
                                                            else echo 'bg-red-100 text-red-800';
                                                            ?>">
                                                            Grade: <?php echo $grade; ?>
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
                                                        <?php echo ucfirst($exam['exam_type']); ?> | 
                                                        Total Marks: <?php echo $exam['total_marks']; ?> | 
                                                        Passing Marks: <?php echo $exam['passing_marks']; ?>
                                                    </p>
                                                    <?php if ($exam['start_date'] && $exam['end_date']): ?>
                                                    <p class="text-sm text-gray-500">
                                                        Duration: <?php echo date('M d', strtotime($exam['start_date'])); ?> - <?php echo date('M d, Y', strtotime($exam['end_date'])); ?>
                                                    </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Subjects and Notifications -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Subjects -->
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">My Subjects</h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <?php if (empty($subjects)): ?>
                                    <p class="text-gray-500">No subjects assigned yet.</p>
                                    <?php else: ?>
                                    <ul class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <?php foreach ($subjects as $subject): ?>
                                        <li class="bg-gray-50 rounded-lg p-4">
                                            <h3 class="text-sm font-medium text-gray-900"><?php echo $subject['subject_name']; ?></h3>
                                            <p class="text-xs text-gray-500 mt-1">Subject ID: <?php echo $subject['subject_id']; ?></p>
                                            <?php if (!empty($subject['description'])): ?>
                                            <p class="text-xs text-gray-500 mt-1"><?php echo $subject['description']; ?></p>
                                            <?php endif; ?>
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

    <script>
        <?php if (!empty($recent_results)): ?>
        // Performance Chart
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    foreach ($recent_results as $result) {
                        echo "'" . $result['subject_name'] . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Theory Marks',
                    data: [
                        <?php 
                        foreach ($recent_results as $result) {
                            echo $result['theory_marks'] . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Practical Marks',
                    data: [
                        <?php 
                        foreach ($recent_results as $result) {
                            echo ($result['practical_marks'] ?? 0) . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
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
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>

