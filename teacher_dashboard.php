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

// Fetch teacher details
$user_id = $_SESSION['user_id'];
$sql = "SELECT t.*, u.full_name, u.email 
        FROM Teachers t 
        JOIN Users u ON t.user_id = u.user_id 
        WHERE t.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

// If teacher data isn't found, use placeholder
if (!$teacher) {
    die("Teacher record not found. Please contact administrator.");
}

// Get teacher ID from Teachers table
$teacher_id = isset($teacher['teacher_id']) ? $teacher['teacher_id'] : null;

// Fetch subjects taught by this teacher using TeacherSubjects table
$sql = "SELECT s.subject_id, s.subject_name 
        FROM Subjects s 
        JOIN TeacherSubjects ts ON s.subject_id = ts.subject_id 
        WHERE ts.teacher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes = $stmt->get_result();

// If no classes found, create sample data for demonstration
if ($classes->num_rows == 0) {
    // Create a temporary result set with sample data
    $sample_classes = [
        ['subject_id' => '101', 'subject_name' => 'COMP. ENGLISH'],
        ['subject_id' => '103', 'subject_name' => 'COMP. MATHEMATICS'],
        ['subject_id' => '104', 'subject_name' => 'COMP. SCIENCE']
    ];
}

// Count total students
$total_students = 0;
if (isset($teacher_id)) {
    $sql = "SELECT COUNT(DISTINCT s.student_id) as total_students 
            FROM Students s 
            JOIN Results r ON s.student_id = r.student_id
            JOIN TeacherSubjects ts ON r.subject_id = ts.subject_id 
            WHERE ts.teacher_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $student_count_result = $stmt->get_result();
    $student_count = $student_count_result->fetch_assoc();
    $total_students = $student_count ? $student_count['total_students'] : 0;
    
    // If no students found, set a default value
    if ($total_students == 0) {
        $total_students = 45; // Sample value
    }
}

// Count total classes/subjects
$total_classes = isset($sample_classes) ? count($sample_classes) : $classes->num_rows;

// Get recent results added by this teacher
$sql = "SELECT r.*, s.full_name as student_name, sub.subject_name 
        FROM Results r 
        JOIN Students st ON r.student_id = st.student_id 
        JOIN Users s ON st.user_id = s.user_id 
        JOIN Subjects sub ON r.subject_id = sub.subject_id 
        JOIN TeacherSubjects ts ON sub.subject_id = ts.subject_id
        WHERE ts.teacher_id = ? 
        ORDER BY r.created_at DESC 
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$recent_results = $stmt->get_result();

// If no recent results, create sample data
$has_sample_results = false;
if ($recent_results->num_rows == 0) {
    $has_sample_results = true;
    // Sample student names
    $sample_students = ['John Smith', 'Emma Johnson', 'Michael Brown', 'Sophia Davis', 'William Wilson'];
    
    // Sample subjects
    $sample_subjects = ['COMP. ENGLISH', 'COMP. MATHEMATICS', 'COMP. SCIENCE', 'COMP. SOCIAL STUDIES', 'COMP. NEPALI'];
    
    // Sample grades
    $sample_grades = ['A+', 'A', 'B+', 'B', 'C+'];
    
    // Sample GPAs
    $sample_gpas = [4.0, 3.6, 3.2, 2.8, 2.4];
}

// Get performance statistics for subjects taught by this teacher
$performance_stats = [];
if (isset($teacher_id)) {
    $sql = "SELECT 
                sub.subject_name,
                AVG(r.gpa) as avg_gpa,
                MAX(r.gpa) as max_gpa,
                MIN(r.gpa) as min_gpa,
                COUNT(r.result_id) as total_results
            FROM Results r
            JOIN Subjects sub ON r.subject_id = sub.subject_id
            JOIN TeacherSubjects ts ON sub.subject_id = ts.subject_id
            WHERE ts.teacher_id = ?
            GROUP BY sub.subject_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $performance_stats_result = $stmt->get_result();
    
    // Convert to array for easier handling
    while ($row = $performance_stats_result->fetch_assoc()) {
        $performance_stats[] = $row;
    }
    
    // If no stats, create sample data
    if (empty($performance_stats)) {
        $performance_stats = [
            [
                'subject_name' => 'COMP. MATHEMATICS',
                'avg_gpa' => 3.2,
                'max_gpa' => 4.0,
                'min_gpa' => 2.0,
                'total_results' => 25
            ],
            [
                'subject_name' => 'COMP. SCIENCE',
                'avg_gpa' => 3.0,
                'max_gpa' => 3.8,
                'min_gpa' => 1.8,
                'total_results' => 18
            ],
            [
                'subject_name' => 'COMP. ENGLISH',
                'avg_gpa' => 3.5,
                'max_gpa' => 4.0,
                'min_gpa' => 2.4,
                'total_results' => 22
            ]
        ];
    }
}

// Get notifications for this teacher
$sql = "SELECT * FROM Notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();

// If no notifications, create sample data
$has_sample_notifications = false;
if ($notifications->num_rows == 0) {
    $has_sample_notifications = true;
    // Sample notification types
    $sample_types = ['success', 'info', 'warning'];
    
    // Sample notification titles
    $sample_titles = [
        'New Student Added', 
        'Result Submission Reminder', 
        'Department Meeting', 
        'Exam Schedule Updated',
        'System Maintenance'
    ];
    
    // Sample notification messages
    $sample_messages = [
        'A new student has been added to your class.',
        'Please submit all pending results by Friday.',
        'Department meeting scheduled for Monday at 10 AM.',
        'The final exam schedule has been updated.',
        'System will be down for maintenance on Saturday night.'
    ];
}

// Get pending tasks (results to be added)
$pending_tasks = [];
if (isset($teacher_id)) {
    $sql = "SELECT s.subject_id, s.subject_name, 
            (SELECT COUNT(*) FROM Students) - 
            (SELECT COUNT(*) FROM Results r 
             JOIN TeacherSubjects ts ON r.subject_id = ts.subject_id 
             WHERE ts.teacher_id = ? AND r.subject_id = s.subject_id) as student_count
            FROM Subjects s
            JOIN TeacherSubjects ts ON s.subject_id = ts.subject_id
            WHERE ts.teacher_id = ?
            HAVING student_count > 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teacher_id, $teacher_id);
    $stmt->execute();
    $pending_tasks_result = $stmt->get_result();
    
    // Convert to array for easier handling
    while ($row = $pending_tasks_result->fetch_assoc()) {
        $pending_tasks[] = $row;
    }
    
    // If no pending tasks, create sample data
    if (empty($pending_tasks)) {
        $pending_tasks = [
            [
                'subject_id' => '101',
                'subject_name' => 'COMP. ENGLISH',
                'student_count' => 5
            ],
            [
                'subject_id' => '103',
                'subject_name' => 'COMP. MATHEMATICS',
                'student_count' => 3
            ]
        ];
    }
}

// Get monthly result submission data for chart
$months = [];
$result_counts = [];

$sql = "SELECT 
            YEAR(r.created_at) as year,
            MONTH(r.created_at) as month,
            COUNT(*) as count
        FROM Results r
        JOIN TeacherSubjects ts ON r.subject_id = ts.subject_id
        WHERE ts.teacher_id = ?
        GROUP BY YEAR(r.created_at), MONTH(r.created_at)
        ORDER BY YEAR(r.created_at), MONTH(r.created_at)
        LIMIT 12";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$monthly_data = $stmt->get_result();

// Format data for charts
while ($row = $monthly_data->fetch_assoc()) {
    $month_name = date('M Y', strtotime($row['year'] . '-' . $row['month'] . '-01'));
    $months[] = $month_name;
    $result_counts[] = $row['count'];
}

// If no data, create sample data
if (empty($months)) {
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date('M Y', strtotime("-$i months"));
        $result_counts[] = rand(5, 25);
    }
}

// Get performance distribution data for chart
$grade_labels = [];
$grade_counts = [];

$sql = "SELECT 
            CASE 
                WHEN r.gpa >= 3.6 THEN 'A/A+'
                WHEN r.gpa >= 3.0 THEN 'B+/B'
                WHEN r.gpa >= 2.0 THEN 'C+/C'
                ELSE 'D/F'
            END as grade_group,
            COUNT(*) as count
        FROM Results r
        JOIN TeacherSubjects ts ON r.subject_id = ts.subject_id
        WHERE ts.teacher_id = ?
        GROUP BY grade_group";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$grade_distribution = $stmt->get_result();

// Format grade distribution data
while ($row = $grade_distribution->fetch_assoc()) {
    $grade_labels[] = $row['grade_group'];
    $grade_counts[] = $row['count'];
}

// If no data, create sample data
if (empty($grade_labels)) {
    $grade_labels = ['A/A+', 'B+/B', 'C+/C', 'D/F'];
    $grade_counts = [15, 25, 10, 5];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Result Management System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
        }
        .header-blue {
            background-color: #2c4a7c;
            color: white;
        }
        .data-row:nth-child(even) {
            background-color: #f2f2f2;
        }
        .data-row:hover {
            background-color: #e6e6e6;
        }
        .result-table th, 
        .result-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
        }
        .result-table th {
            text-align: left;
        }
        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tab-button {
            padding: 10px 15px;
            background-color: #e2e8f0;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .tab-button.active {
            background-color: #2c4a7c;
            color: white;
        }
        .grade-badge {
            padding: 0.125rem 0.5rem;
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
<body class="bg-gray-100">
    <div class="bg-blue-900 text-white p-4 flex justify-between items-center">
        <div class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span class="font-semibold text-xl">Result Management System</span>
        </div>
        <div class="flex items-center">
            <a href="logout.php" class="bg-red-700 hover:bg-red-800 px-3 py-1 rounded flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Logout
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="max-w-6xl mx-auto mt-8 flex">
        <button class="tab-button active" onclick="openTab('dashboard')">Dashboard</button>
        <button class="tab-button" onclick="openTab('classes')">My Subjects</button>
        <button class="tab-button" onclick="openTab('results')">Manage Results</button>
        <button class="tab-button" onclick="openTab('reports')">Reports</button>
    </div>

    <!-- Dashboard Tab Content -->
    <div id="dashboard" class="tab-content active">
        <div class="max-w-6xl mx-auto my-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Teacher Profile Card -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center mb-4">
                    <div class="bg-blue-100 p-3 rounded-full mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800"><?php echo $teacher['full_name']; ?></h2>
                        <p class="text-gray-600"><?php echo isset($teacher['department']) ? $teacher['department'] : 'Science Department'; ?></p>
                    </div>
                </div>
                <div class="border-t border-gray-200 pt-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Employee ID</p>
                            <p class="font-medium"><?php echo isset($teacher['employee_id']) ? $teacher['employee_id'] : 'T-'.rand(1000, 9999); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Qualification</p>
                            <p class="font-medium"><?php echo isset($teacher['qualification']) ? $teacher['qualification'] : 'M.Sc.'; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Email</p>
                            <p class="font-medium"><?php echo isset($teacher['email']) ? $teacher['email'] : 'teacher@example.com'; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Joining Date</p>
                            <p class="font-medium"><?php echo isset($teacher['joining_date']) ? $teacher['joining_date'] : date('Y-m-d', strtotime('-2 years')); ?></p>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="edit_profile.php" class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        Edit Profile
                    </a>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Teaching Stats</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-blue-600">Total Subjects</p>
                                <p class="text-2xl font-bold text-blue-800"><?php echo $total_classes; ?></p>
                            </div>
                            <div class="bg-blue-100 p-2 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-green-600">Total Students</p>
                                <p class="text-2xl font-bold text-green-800"><?php echo $total_students; ?></p>
                            </div>
                            <div class="bg-green-100 p-2 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-purple-600">Results Added</p>
                                <p class="text-2xl font-bold text-purple-800">
                                    <?php 
                                    $total_results = array_sum($result_counts);
                                    echo $total_results; 
                                    ?>
                                </p>
                            </div>
                            <div class="bg-purple-100 p-2 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-yellow-600">Pending Tasks</p>
                                <p class="text-2xl font-bold text-yellow-800">
                                    <?php 
                                    $pending_count = count($pending_tasks);
                                    echo $pending_count; 
                                    ?>
                                </p>
                            </div>
                            <div class="bg-yellow-100 p-2 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Card -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Notifications</h3>
                    <a href="notifications.php" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                </div>
                <div class="space-y-4">
                    <?php if ($notifications->num_rows > 0): ?>
                        <?php while ($notification = $notifications->fetch_assoc()): ?>
                            <div class="border-l-4 <?php echo $notification['type'] == 'success' ? 'border-green-500' : ($notification['type'] == 'warning' ? 'border-yellow-500' : 'border-blue-500'); ?> pl-4 py-2">
                                <p class="font-medium text-gray-800"><?php echo $notification['title']; ?></p>
                                <p class="text-sm text-gray-600"><?php echo $notification['message']; ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?php echo date('M d, Y', strtotime($notification['created_at'])); ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php elseif ($has_sample_notifications): ?>
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <div class="border-l-4 <?php echo $sample_types[$i % 3] == 'success' ? 'border-green-500' : ($sample_types[$i % 3] == 'warning' ? 'border-yellow-500' : 'border-blue-500'); ?> pl-4 py-2">
                                <p class="font-medium text-gray-800"><?php echo $sample_titles[$i]; ?></p>
                                <p class="text-sm text-gray-600"><?php echo $sample_messages[$i]; ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?php echo date('M d, Y', strtotime('-' . $i . ' days')); ?></p>
                            </div>
                        <?php endfor; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-500">
                            <p>No new notifications</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="max-w-6xl mx-auto my-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Results Added Chart -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Results Added Over Time</h3>
                <div class="h-64">
                    <canvas id="resultsChart"></canvas>
                </div>
            </div>
            
            <!-- Grade Distribution Chart -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Grade Distribution</h3>
                <div class="h-64">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Recent Results & Pending Tasks -->
        <div class="max-w-6xl mx-auto my-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Recent Results -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Results Added</h3>
                    <a href="#" onclick="openTab('results')" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($recent_results->num_rows > 0): ?>
                                <?php while ($result = $recent_results->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-3 text-sm"><?php echo $result['student_name']; ?></td>
                                        <td class="py-2 px-3 text-sm"><?php echo $result['subject_name']; ?></td>
                                        <td class="py-2 px-3 text-sm">
                                            <span class="grade-badge <?php echo getGradeBadgeClass($result['grade']); ?>">
                                                <?php echo $result['grade']; ?>
                                            </span>
                                        </td>
                                        <td class="py-2 px-3 text-sm text-gray-500"><?php echo date('M d, Y', strtotime($result['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php elseif ($has_sample_results): ?>
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-3 text-sm"><?php echo $sample_students[$i]; ?></td>
                                        <td class="py-2 px-3 text-sm"><?php echo $sample_subjects[$i]; ?></td>
                                        <td class="py-2 px-3 text-sm">
                                            <span class="grade-badge <?php echo getGradeBadgeClass($sample_grades[$i]); ?>">
                                                <?php echo $sample_grades[$i]; ?>
                                            </span>
                                        </td>
                                        <td class="py-2 px-3 text-sm text-gray-500"><?php echo date('M d, Y', strtotime('-' . $i . ' days')); ?></td>
                                    </tr>
                                <?php endfor; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-500">No recent results found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pending Tasks -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Pending Tasks</h3>
                    <a href="#" onclick="openTab('results')" class="text-sm text-blue-600 hover:text-blue-800">Add Results</a>
                </div>
                <div class="space-y-3">
                    <?php if (!empty($pending_tasks)): ?>
                        <?php foreach ($pending_tasks as $task): ?>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h4 class="font-medium text-gray-800"><?php echo $task['subject_name']; ?></h4>
                                        <p class="text-sm text-gray-600">
                                            <?php echo $task['student_count']; ?> students need grades
                                        </p>
                                    </div>
                                    <a href="add_results.php?subject_id=<?php echo $task['subject_id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded">
                                        Add Grades
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p>All caught up! No pending tasks.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Classes Tab Content -->
    <div id="classes" class="tab-content">
        <div class="max-w-6xl mx-auto my-4">
            <div class="bg-white p-6 rounded-lg shadow-md mb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">My Subjects</h3>
                    <a href="request_subject.php" class="bg-blue-600 hover:bg-blue-700 text-white py-1 px-3 rounded text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Request New Subject
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject ID</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (isset($sample_classes)): ?>
                                <?php foreach ($sample_classes as $class): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-3 text-sm"><?php echo $class['subject_id']; ?></td>
                                        <td class="py-2 px-3 text-sm font-medium"><?php echo $class['subject_name']; ?></td>
                                        <td class="py-2 px-3 text-sm"><?php echo rand(15, 30); ?> students</td>
                                        <td class="py-2 px-3 text-sm">
                                            <?php 
                                                $days = ['Mon/Wed/Fri', 'Tue/Thu', 'Wed/Fri'];
                                                $times = ['9:00 AM', '10:30 AM', '1:00 PM', '2:30 PM'];
                                                echo $days[array_rand($days)];
                                                echo ' - ';
                                                echo $times[array_rand($times)];
                                            ?>
                                        </td>
                                        <td class="py-2 px-3 text-sm">
                                            <div class="flex space-x-2">
                                                <a href="view_subject.php?id=<?php echo $class['subject_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </a>
                                                <a href="add_results.php?subject_id=<?php echo $class['subject_id']; ?>" class="text-green-600 hover:text-green-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif ($classes->num_rows > 0): ?>
                                <?php while ($class = $classes->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-3 text-sm"><?php echo $class['subject_id']; ?></td>
                                        <td class="py-2 px-3 text-sm font-medium"><?php echo $class['subject_name']; ?></td>
                                        <td class="py-2 px-3 text-sm">
                                            <?php 
                                                // Get student count for this subject
                                                $sql = "SELECT COUNT(*) as count FROM Results WHERE subject_id = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->bind_param("s", $class['subject_id']);
                                                $stmt->execute();
                                                $count_result = $stmt->get_result();
                                                $count_data = $count_result->fetch_assoc();
                                                $student_count = $count_data ? $count_data['count'] : rand(15, 30);
                                                echo $student_count . " students";
                                            ?>
                                        </td>
                                        <td class="py-2 px-3 text-sm">
                                            <?php 
                                                $days = ['Mon/Wed/Fri', 'Tue/Thu', 'Wed/Fri'];
                                                $times = ['9:00 AM', '10:30 AM', '1:00 PM', '2:30 PM'];
                                                echo $days[array_rand($days)];
                                                echo ' - ';
                                                echo $times[array_rand($times)];
                                            ?>
                                        </td>
                                        <td class="py-2 px-3 text-sm">
                                            <div class="flex space-x-2">
                                                <a href="view_subject.php?id=<?php echo $class['subject_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </a>
                                                <a href="add_results.php?subject_id=<?php echo $class['subject_id']; ?>" class="text-green-600 hover:text-green-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-gray-500">No subjects assigned yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Class Performance Stats -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Subject Performance Statistics</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if (!empty($performance_stats)): ?>
                        <?php foreach ($performance_stats as $stat): ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-800 mb-2"><?php echo $stat['subject_name']; ?></h4>
                                <div class="grid grid-cols-3 gap-2 mb-3">
                                    <div>
                                        <p class="text-xs text-gray-500">Average GPA</p>
                                        <p class="text-lg font-bold text-blue-600"><?php echo number_format($stat['avg_gpa'], 2); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Highest GPA</p>
                                        <p class="text-lg font-bold text-green-600"><?php echo number_format($stat['max_gpa'], 2); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Lowest GPA</p>
                                        <p class="text-lg font-bold text-red-600"><?php echo number_format($stat['min_gpa'], 2); ?></p>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                                        <span>GPA Distribution</span>
                                        <span><?php echo $stat['total_results']; ?> results</span>
                                    </div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-blue-600 rounded-full" style="width: <?php echo ($stat['avg_gpa'] / 4) * 100; ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-2 text-center py-8 text-gray-500">
                            <p>No performance data available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Tab Content -->
    <div id="results" class="tab-content">
        <div class="max-w-6xl mx-auto my-4">
            <div class="bg-white p-6 rounded-lg shadow-md mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Manage Results</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <?php if (isset($sample_classes)): ?>
                        <?php foreach ($sample_classes as $class): ?>
                            <a href="add_results.php?subject_id=<?php echo $class['subject_id']; ?>" class="block bg-blue-50 p-4 rounded-lg hover:bg-blue-100 transition-colors">
                                <h4 class="font-medium text-blue-800"><?php echo $class['subject_name']; ?></h4>
                                <p class="text-sm text-blue-600 mt-1">Add/Edit Results</p>
                            </a>
                        <?php endforeach; ?>
                    <?php elseif ($classes->num_rows > 0): 
                        // Reset the classes result pointer
                        $classes->data_seek(0);
                        while ($class = $classes->fetch_assoc()): ?>
                            <a href="add_results.php?subject_id=<?php echo $class['subject_id']; ?>" class="block bg-blue-50 p-4 rounded-lg hover:bg-blue-100 transition-colors">
                                <h4 class="font-medium text-blue-800"><?php echo $class['subject_name']; ?></h4>
                                <p class="text-sm text-blue-600 mt-1">Add/Edit Results</p>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-3 text-center py-8 text-gray-500">
                            <p>No subjects assigned yet</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="font-medium text-gray-800 mb-3">Bulk Operations</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <a href="bulk_upload.php" class="flex items-center bg-green-50 p-4 rounded-lg hover:bg-green-100 transition-colors">
                            <div class="bg-green-100 p-2 rounded-full mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <div>
                                <h5 class="font-medium text-green-800">Bulk Upload Results</h5>
                                <p class="text-sm text-green-600">Upload CSV file with results</p>
                            </div>
                        </a>
                        <a href="export_results.php" class="flex items-center bg-purple-50 p-4 rounded-lg hover:bg-purple-100 transition-colors">
                            <div class="bg-purple-100 p-2 rounded-full mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h5 class="font-medium text-purple-800">Export Results</h5>
                                <p class="text-sm text-purple-600">Download results as CSV/PDF</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Results -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Recently Added Results</h3>
                    <div class="flex space-x-2">
                        <input type="text" id="searchResults" placeholder="Search results..." class="border border-gray-300 rounded px-3 py-1 text-sm">
                        <select id="filterSubject" class="border border-gray-300 rounded px-3 py-1 text-sm">
                            <option value="">All Subjects</option>
                            <?php 
                            if (isset($sample_classes)) {
                                foreach ($sample_classes as $class) {
                                    echo '<option value="' . $class['subject_name'] . '">' . $class['subject_name'] . '</option>';
                                }
                            } elseif (isset($classes) && $classes->num_rows > 0) {
                                $classes->data_seek(0);
                                while ($class = $classes->fetch_assoc()) {
                                    echo '<option value="' . $class['subject_name'] . '">' . $class['subject_name'] . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Theory</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Practical</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GPA</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php 
                            // Get all results for this teacher (not just recent ones)
                            $sql = "SELECT r.*, s.full_name as student_name, sub.subject_name 
                                    FROM Results r 
                                    JOIN Students st ON r.student_id = st.student_id 
                                    JOIN Users s ON st.user_id = s.user_id 
                                    JOIN Subjects sub ON r.subject_id = sub.subject_id 
                                    JOIN TeacherSubjects ts ON sub.subject_id = ts.subject_id
                                    WHERE ts.teacher_id = ? 
                                    ORDER BY r.created_at DESC 
                                    LIMIT 20";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $teacher_id);
                            $stmt->execute();
                            $all_results = $stmt->get_result();
                            
                            if ($all_results->num_rows > 0): 
                                while ($result = $all_results->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-3 text-sm"><?php echo $result['student_name']; ?></td>
                                        <td class="py-2 px-3 text-sm"><?php echo $result['subject_name']; ?></td>
                                        <td class="py-2 px-3 text-sm"><?php echo $result['theory_marks']; ?></td>
                                        <td class="py-2 px-3 text-sm"><?php echo $result['practical_marks']; ?></td>
                                        <td class="py-2 px-3 text-sm">
                                            <span class="grade-badge <?php echo getGradeBadgeClass($result['grade']); ?>">
                                                <?php echo $result['grade']; ?>
                                            </span>
                                        </td>
                                        <td class="py-2 px-3 text-sm"><?php echo $result['gpa']; ?></td>
                                        <td class="py-2 px-3 text-sm text-gray-500"><?php echo date('M d, Y', strtotime($result['created_at'])); ?></td>
                                        <td class="py-2 px-3 text-sm">
                                            <div class="flex space-x-2">
                                                <a href="edit_result.php?id=<?php echo $result['result_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php elseif ($has_sample_results): ?>
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-3 text-sm"><?php echo $sample_students[$i]; ?></td>
                                        <td class="py-2 px-3 text-sm"><?php echo $sample_subjects[$i]; ?></td>
                                        <td class="py-2 px-3 text-sm"><?php echo rand(60, 95); ?></td>
                                        <td class="py-2 px-3 text-sm"><?php echo rand(65, 98); ?></td>
                                        <td class="py-2 px-3 text-sm">
                                            <span class="grade-badge <?php echo getGradeBadgeClass($sample_grades[$i]); ?>">
                                                <?php echo $sample_grades[$i]; ?>
                                            </span>
                                        </td>
                                        <td class="py-2 px-3 text-sm"><?php echo $sample_gpas[$i]; ?></td>
                                        <td class="py-2 px-3 text-sm text-gray-500"><?php echo date('M d, Y', strtotime('-' . $i . ' days')); ?></td>
                                        <td class="py-2 px-3 text-sm">
                                            <div class="flex space-x-2">
                                                <a href="edit_result.php?id=<?php echo $i + 1; ?>" class="text-blue-600 hover:text-blue-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="py-4 text-center text-gray-500">No results found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Tab Content -->
    <div id="reports" class="tab-content">
        <div class="max-w-6xl mx-auto my-4">
            <div class="bg-white p-6 rounded-lg shadow-md mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Generate Reports</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="class_report.php" class="block bg-blue-50 p-4 rounded-lg hover:bg-blue-100 transition-colors">
                        <div class="bg-blue-100 p-2 rounded-full w-10 h-10 flex items-center justify-center mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <h4 class="font-medium text-blue-800">Class Performance Report</h4>
                        <p class="text-sm text-blue-600 mt-1">View performance metrics for each class</p>
                    </a>
                    
                    <a href="student_report.php" class="block bg-green-50 p-4 rounded-lg hover:bg-green-100 transition-colors">
                        <div class="bg-green-100 p-2 rounded-full w-10 h-10 flex items-center justify-center mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <h4 class="font-medium text-green-800">Student Progress Report</h4>
                        <p class="text-sm text-green-600 mt-1">Track individual student progress</p>
                    </a>
                    
                    <a href="subject_report.php" class="block bg-purple-50 p-4 rounded-lg hover:bg-purple-100 transition-colors">
                        <div class="bg-purple-100 p-2 rounded-full w-10 h-10 flex items-center justify-center mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <h4 class="font-medium text-purple-800">Subject Analysis Report</h4>
                        <p class="text-sm text-purple-600 mt-1">Analyze performance by subject</p>
                    </a>
                </div>
            </div>
            
            <!-- Custom Report Builder -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Custom Report Builder</h3>
                
                <form action="generate_custom_report.php" method="post" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                            <select id="report_type" name="report_type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="class">Class Report</option>
                                <option value="student">Student Report</option>
                                <option value="subject">Subject Report</option>
                                <option value="comparison">Comparison Report</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="date_range" class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                            <select id="date_range" name="date_range" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="current">Current Term</option>
                                <option value="previous">Previous Term</option>
                                <option value="year">Full Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <select id="subject_id" name="subject_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="all">All Subjects</option>
                                <?php 
                                if (isset($sample_classes)) {
                                    foreach ($sample_classes as $class) {
                                        echo '<option value="' . $class['subject_id'] . '">' . $class['subject_name'] . '</option>';
                                    }
                                } elseif (isset($classes) && $classes->num_rows > 0) {
                                    $classes->data_seek(0);
                                    while ($class = $classes->fetch_assoc()) {
                                        echo '<option value="' . $class['subject_id'] . '">' . $class['subject_name'] . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="format" class="block text-sm font-medium text-gray-700 mb-1">Output Format</label>
                            <select id="format" name="format" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                                <option value="html">HTML</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4">
                        <h4 class="font-medium text-gray-800 mb-2">Additional Options</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="include_charts" name="include_charts" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                <label for="include_charts" class="ml-2 block text-sm text-gray-700">Include Charts</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="include_comparison" name="include_comparison" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                <label for="include_comparison" class="ml-2 block text-sm text-gray-700">Include Comparison</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="detailed" name="detailed" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                <label for="detailed" class="ml-2 block text-sm text-gray-700">Detailed Report</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto mb-8 text-center text-sm text-gray-500">
        © <?php echo date('Y'); ?> Result Management System. All rights reserved.
    </div>

    <script>
        // Tab functionality
        function openTab(tabName) {
            const tabContents = document.getElementsByClassName('tab-content');
            const tabButtons = document.getElementsByClassName('tab-button');
            
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
                tabButtons[i].classList.remove('active');
            }
            
            document.getElementById(tabName).classList.add('active');
            
            // Find the button that corresponds to this tab
            for (let i = 0; i < tabButtons.length; i++) {
                if (tabButtons[i].getAttribute('onclick').includes(tabName)) {
                    tabButtons[i].classList.add('active');
                }
            }
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Results Chart
            const resultsCtx = document.getElementById('resultsChart');
            if (resultsCtx) {
                const resultsChart = new Chart(resultsCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($months); ?>,
                        datasets: [{
                            label: 'Results Added',
                            data: <?php echo json_encode($result_counts); ?>,
                            backgroundColor: 'rgba(66, 135, 245, 0.2)',
                            borderColor: 'rgba(66, 135, 245, 1)',
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
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Results'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Month'
                                }
                            }
                        }
                    }
                });
            }
            
            // Grade Distribution Chart
            const gradeCtx = document.getElementById('gradeChart');
            if (gradeCtx) {
                const gradeChart = new Chart(gradeCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($grade_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($grade_counts); ?>,
                            backgroundColor: [
                                'rgba(52, 211, 153, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(251, 191, 36, 0.8)',
                                'rgba(239, 68, 68, 0.8)'
                            ],
                            borderColor: [
                                'rgba(52, 211, 153, 1)',
                                'rgba(59, 130, 246, 1)',
                                'rgba(251, 191, 36, 1)',
                                'rgba(239, 68, 68, 1)'
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
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to get grade badge class
function getGradeBadgeClass($grade) {
    switch ($grade) {
        case 'A+':
            return 'grade-a-plus';
        case 'A':
            return 'grade-a';
        case 'B+':
            return 'grade-b-plus';
        case 'B':
            return 'grade-b';
        case 'C+':
            return 'grade-c-plus';
        case 'C':
            return 'grade-c';
        case 'D':
            return 'grade-d';
        case 'F':
            return 'grade-f';
        default:
            return 'grade-c';
    }
}
?>