<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get classes for dropdown
$classes = [];
$result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name, section");
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

// Get exams for dropdown
$exams = [];
$result = $conn->query("SELECT exam_id, exam_name, exam_type, class_id FROM exams ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}

// Get filter values
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : '';
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : '';

// Get academic progress data
$academic_progress = [];
$subject_performance = [];
$grade_distribution = [];

if (!empty($class_id) || !empty($exam_id)) {
    // Check if student_performance table exists
    $table_exists = $conn->query("SHOW TABLES LIKE 'student_performance'");
    
    if ($table_exists->num_rows > 0) {
        // Use student_performance table if it exists
        $query = "
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
            WHERE 1=1
        ";
        
        if (!empty($class_id)) {
            $query .= " AND c.class_id = " . $conn->real_escape_string($class_id);
        }
        
        if (!empty($exam_id)) {
            $query .= " AND e.exam_id = " . $conn->real_escape_string($exam_id);
        }
        
        $query .= "
            GROUP BY 
                c.class_id, e.exam_id
            ORDER BY 
                c.class_name, c.section, e.exam_name
        ";
    } else {
        // Use results table if student_performance doesn't exist
        $query = "
            SELECT 
                c.class_name, 
                c.section, 
                e.exam_name,
                AVG(r.theory_marks + r.practical_marks) as avg_marks,
                COUNT(DISTINCT r.student_id) as student_count,
                SUM(CASE WHEN (r.theory_marks + r.practical_marks) >= 80 THEN 1 ELSE 0 END) as high_performers,
                SUM(CASE WHEN (r.theory_marks + r.practical_marks) < 33 THEN 1 ELSE 0 END) as low_performers
            FROM 
                results r
            JOIN 
                students s ON r.student_id = s.student_id
            JOIN 
                classes c ON s.class_id = c.class_id
            JOIN 
                exams e ON r.exam_id = e.exam_id
            WHERE 1=1
        ";
        
        if (!empty($class_id)) {
            $query .= " AND c.class_id = " . $conn->real_escape_string($class_id);
        }
        
        if (!empty($exam_id)) {
            $query .= " AND e.exam_id = " . $conn->real_escape_string($exam_id);
        }
        
        $query .= "
            GROUP BY 
                c.class_id, e.exam_id
            ORDER BY 
                c.class_name, c.section, e.exam_name
        ";
    }
    
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        // If using results table, calculate GPA
        if (!isset($row['avg_gpa'])) {
            $avg_marks = $row['avg_marks'];
            $avg_gpa = 0;
            
            if ($avg_marks >= 90) {
                $avg_gpa = 4.0;
            } elseif ($avg_marks >= 80) {
                $avg_gpa = 3.7;
            } elseif ($avg_marks >= 70) {
                $avg_gpa = 3.3;
            } elseif ($avg_marks >= 60) {
                $avg_gpa = 3.0;
            } elseif ($avg_marks >= 50) {
                $avg_gpa = 2.7;
            } elseif ($avg_marks >= 40) {
                $avg_gpa = 2.3;
            } elseif ($avg_marks >= 33) {
                $avg_gpa = 2.0;
            }
            
            $row['avg_gpa'] = $avg_gpa;
        }
        
        $academic_progress[] = $row;
    }

    // Get subject-wise performance data for charts
    $query = "
        SELECT 
            sub.subject_name,
            AVG(r.theory_marks + r.practical_marks) as avg_marks,
            COUNT(DISTINCT r.student_id) as student_count
        FROM 
            results r
        JOIN 
            students s ON r.student_id = s.student_id
        JOIN 
            subjects sub ON r.subject_id = sub.subject_id
        WHERE 1=1
    ";
    
    if (!empty($class_id)) {
        $query .= " AND s.class_id = " . $conn->real_escape_string($class_id);
    }
    
    if (!empty($exam_id)) {
        $query .= " AND r.exam_id = " . $conn->real_escape_string($exam_id);
    }
    
    $query .= "
        GROUP BY 
            r.subject_id
        ORDER BY 
            avg_marks DESC
        LIMIT 10
    ";
    
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $subject_performance[] = $row;
    }

    // Get grade distribution data
    $query = "
        SELECT 
            r.grade,
            COUNT(*) as count
        FROM 
            results r
        JOIN 
            students s ON r.student_id = s.student_id
        WHERE 1=1
    ";
    
    if (!empty($class_id)) {
        $query .= " AND s.class_id = " . $conn->real_escape_string($class_id);
    }
    
    if (!empty($exam_id)) {
        $query .= " AND r.exam_id = " . $conn->real_escape_string($exam_id);
    }
    
    $query .= "
        GROUP BY 
            r.grade
        ORDER BY 
            CASE
                WHEN r.grade = 'A+' THEN 1
                WHEN r.grade = 'A' THEN 2
                WHEN r.grade = 'B+' THEN 3
                WHEN r.grade = 'B' THEN 4
                WHEN r.grade = 'C+' THEN 5
                WHEN r.grade = 'C' THEN 6
                WHEN r.grade = 'D' THEN 7
                WHEN r.grade = 'F' THEN 8
                ELSE 9
            END
    ";
    
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $grade_distribution[] = $row;
    }
}

// Prepare data for charts
$chart_labels = [];
$chart_data = [];
$chart_colors = [
    'rgba(54, 162, 235, 0.2)',
    'rgba(255, 99, 132, 0.2)',
    'rgba(255, 206, 86, 0.2)',
    'rgba(75, 192, 192, 0.2)',
    'rgba(153, 102, 255, 0.2)',
    'rgba(255, 159, 64, 0.2)',
    'rgba(201, 203, 207, 0.2)',
    'rgba(255, 99, 132, 0.2)',
    'rgba(54, 162, 235, 0.2)',
    'rgba(255, 206, 86, 0.2)'
];
$chart_borders = [
    'rgba(54, 162, 235, 1)',
    'rgba(255, 99, 132, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(153, 102, 255, 1)',
    'rgba(255, 159, 64, 1)',
    'rgba(201, 203, 207, 1)',
    'rgba(255, 99, 132, 1)',
    'rgba(54, 162, 235, 1)',
    'rgba(255, 206, 86, 1)'
];

foreach ($subject_performance as $subject) {
    $chart_labels[] = $subject['subject_name'];
    $chart_data[] = $subject['avg_marks'];
}

// Prepare data for grade distribution chart
$grade_labels = [];
$grade_data = [];
$grade_colors = [
    'A+' => 'rgba(0, 200, 83, 0.7)',
    'A' => 'rgba(76, 175, 80, 0.7)',
    'B+' => 'rgba(139, 195, 74, 0.7)',
    'B' => 'rgba(205, 220, 57, 0.7)',
    'C+' => 'rgba(255, 235, 59, 0.7)',
    'C' => 'rgba(255, 193, 7, 0.7)',
    'D' => 'rgba(255, 152, 0, 0.7)',
    'F' => 'rgba(244, 67, 54, 0.7)'
];

foreach ($grade_distribution as $grade) {
    $grade_labels[] = $grade['grade'];
    $grade_data[] = $grade['count'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Progress | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 20px;
                background: white;
            }
            .shadow, .shadow-sm {
                box-shadow: none !important;
            }
            .bg-gray-100 {
                background: white !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
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
                            <a href="students.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-user-graduate mr-3"></i>
                                Students
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
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow no-print">
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Academic Progress</h1>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
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
                        <!-- Filter Form -->
                        <div class="mb-6 bg-white shadow rounded-lg overflow-hidden no-print">
                            <div class="px-4 py-5 sm:p-6">
                                <form action="academic_progress.php" method="GET" class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                    <div class="sm:col-span-3">
                                        <label for="class_id" class="block text-sm font-medium text-gray-700">Class</label>
                                        <div class="mt-1">
                                            <select id="class_id" name="class_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                <option value="">All Classes</option>
                                                <?php foreach ($classes as $class): ?>
                                                    <option value="<?php echo htmlspecialchars($class['class_id']); ?>" <?php echo $class_id == $class['class_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($class['class_name'] . ' ' . $class['section']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="sm:col-span-3">
                                        <label for="exam_id" class="block text-sm font-medium text-gray-700">Exam</label>
                                        <div class="mt-1">
                                            <select id="exam_id" name="exam_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                <option value="">All Exams</option>
                                                <?php foreach ($exams as $exam): ?>
                                                    <option value="<?php echo htmlspecialchars($exam['exam_id']); ?>" <?php echo $exam_id == $exam['exam_id'] ? 'selected' : ''; ?> data-class="<?php echo htmlspecialchars($exam['class_id']); ?>">
                                                        <?php echo htmlspecialchars($exam['exam_name'] . ' (' . ucfirst($exam['exam_type']) . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="sm:col-span-6 flex justify-end">
                                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-filter mr-2"></i> Filter
                                        </button>
                                        <a href="academic_progress.php" class="ml-3 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-times mr-2"></i> Clear
                                        </a>
                                        <a href="#" id="print-report" class="ml-3 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-print mr-2"></i> Print Report
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if (empty($academic_progress) && empty($subject_performance)): ?>
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-4 py-5 sm:p-6 text-center">
                                    <i class="fas fa-chart-line text-gray-400 text-5xl mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900">No Data Available</h3>
                                    <p class="mt-1 text-sm text-gray-500">Please select a class and/or exam to view academic progress data.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Charts -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                                <!-- Subject Performance Chart -->
                                <div class="bg-white shadow rounded-lg p-4">
                                    <h2 class="text-lg font-medium text-gray-900 mb-4">Subject Performance</h2>
                                    <div class="h-64">
                                        <canvas id="subjectChart"></canvas>
                                    </div>
                                </div>

                                <!-- Grade Distribution Chart -->
                                <div class="bg-white shadow rounded-lg p-4">
                                    <h2 class="text-lg font-medium text-gray-900 mb-4">Grade Distribution</h2>
                                    <div class="h-64">
                                        <canvas id="gradeChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Academic Progress Table -->
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Academic Progress</h3>
                                    <p class="mt-1 text-sm text-gray-500">Overall class performance metrics</p>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. GPA</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Marks</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">High Performers</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Low Performers</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($academic_progress as $progress): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($progress['class_name'] . ' ' . $progress['section']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($progress['exam_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo number_format($progress['avg_gpa'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo number_format($progress['avg_marks'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($progress['student_count']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            <?php echo htmlspecialchars($progress['high_performers']); ?> (<?php echo $progress['student_count'] > 0 ? number_format(($progress['high_performers'] / $progress['student_count']) * 100, 1) : 0; ?>%)
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                            <?php echo htmlspecialchars($progress['low_performers']); ?> (<?php echo $progress['student_count'] > 0 ? number_format(($progress['low_performers'] / $progress['student_count']) * 100, 1) : 0; ?>%)
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <?php 
                                                            $performance_percentage = $progress['student_count'] > 0 ? ($progress['high_performers'] / $progress['student_count']) * 100 : 0;
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
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Filter exams by class
        document.getElementById('class_id').addEventListener('change', function() {
            const classId = this.value;
            const examSelect = document.getElementById('exam_id');
            const examOptions = examSelect.querySelectorAll('option');
            
            // Reset exam selection
            examSelect.selectedIndex = 0;
            
            // Show/hide exam options based on class
            if (classId) {
                examOptions.forEach(option => {
                    if (option.value === '') return; // Skip the placeholder option
                    
                    const examClassId = option.getAttribute('data-class');
                    if (examClassId === classId) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                });
            } else {
                // Show all exams if no class is selected
                examOptions.forEach(option => {
                    option.style.display = '';
                });
            }
        });
        
        // Print report
        document.getElementById('print-report').addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
        });
        
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            sidebar.classList.toggle('hidden');
        });
        
        <?php if (!empty($subject_performance)): ?>
        // Subject Performance Chart
        const subjectCtx = document.getElementById('subjectChart').getContext('2d');
        const subjectChart = new Chart(subjectCtx, {
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
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Average Marks'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Subjects'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toFixed(2) + '%';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        <?php if (!empty($grade_distribution)): ?>
        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        const gradeChart = new Chart(gradeCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($grade_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($grade_data); ?>,
                    backgroundColor: [
                        'rgba(0, 200, 83, 0.7)',
                        'rgba(76, 175, 80, 0.7)',
                        'rgba(139, 195, 74, 0.7)',
                        'rgba(205, 220, 57, 0.7)',
                        'rgba(255, 235, 59, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(255, 152, 0, 0.7)',
                        'rgba(244, 67, 54, 0.7)'
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
                        labels: {
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>