<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.html");
    exit();
}
// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Fetch teacher details
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM Teachers WHERE user_id='$user_id'";
$result = $conn->query($sql);
$teacher = $result->fetch_assoc();
// Fetch class performance
$sql = "SELECT Students.name, Results.theory_marks, Results.practical_marks, Results.grade, Results.gpa, Students.student_id
        FROM Results 
        JOIN Students ON Results.student_id = Students.student_id 
        WHERE Results.subject_id='{$teacher['subject_id']}'";
$class_performance = $conn->query($sql);

// Calculate class averages and statistics
$total_students = 0;
$total_theory = 0;
$total_practical = 0;
$total_gpa = 0;
$grade_distribution = [
    'A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0, 
    'C+' => 0, 'C' => 0, 'D' => 0, 'F' => 0
];

// Clone the result set for calculations
$stats_result = $conn->query("SELECT Students.name, Results.theory_marks, Results.practical_marks, Results.grade, Results.gpa 
                             FROM Results 
                             JOIN Students ON Results.student_id = Students.student_id 
                             WHERE Results.subject_id='{$teacher['subject_id']}'");

while ($row = $stats_result->fetch_assoc()) {
    $total_theory += $row['theory_marks'];
    $total_practical += $row['practical_marks'];
    $total_gpa += $row['gpa'];
    if (isset($grade_distribution[$row['grade']])) {
        $grade_distribution[$row['grade']]++;
    }
    $total_students++;
}

$avg_theory = $total_students > 0 ? round($total_theory / $total_students, 1) : 0;
$avg_practical = $total_students > 0 ? round($total_practical / $total_students, 1) : 0;
$avg_gpa = $total_students > 0 ? round($total_gpa / $total_students, 2) : 0;

// Find top performer
$top_student = $conn->query("SELECT Students.name, Results.gpa 
                            FROM Results 
                            JOIN Students ON Results.student_id = Students.student_id 
                            WHERE Results.subject_id='{$teacher['subject_id']}' 
                            ORDER BY Results.gpa DESC LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Result Management System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            font-weight: 300;
        }
        
        .performance-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .performance-table th,
        .performance-table td {
            border: 1px solid #e2e8f0;
        }
        
        .performance-table th {
            border-bottom: 2px solid #2d3748;
        }
        
        .performance-table tr:hover {
            background-color: #f8fafc;
        }
        
        .grade-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">
    <nav class="bg-blue-900 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span class="font-semibold text-xl">Result Management System</span>
                </div>
                <div class="flex items-center">
                    <div class="mr-4 text-sm">
                        <span class="text-slate-300">Subject: </span>
                        <span class="font-medium"><?php echo $teacher['subject_id']; ?></span>
                    </div>
                    <a href="logout.php" class="flex items-center px-3 py-2 rounded-md text-sm font-medium bg-red-700 hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Teacher Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-blue-900">Welcome, <?php echo $teacher['name']; ?></h1>
                    <p class="text-slate-500">Manage and monitor your class performance</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <a href="add_result.php" class="flex items-center px-4 py-2 bg-blue-900 text-white rounded-md text-sm font-medium hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-900 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New Result
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Performance Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-blue-900">
                <p class="text-sm text-slate-500 mb-1">Total Students</p>
                <p class="text-2xl font-bold text-blue-900"><?php echo $total_students; ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-green-600">
                <p class="text-sm text-slate-500 mb-1">Class Average GPA</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $avg_gpa; ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-purple-600">
                <p class="text-sm text-slate-500 mb-1">Average Theory</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo $avg_theory; ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-yellow-600">
                <p class="text-sm text-slate-500 mb-1">Average Practical</p>
                <p class="text-2xl font-bold text-yellow-600"><?php echo $avg_practical; ?></p>
            </div>
        </div>
        
        <!-- Top Performers & Distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Top Performer -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="bg-blue-900 text-white px-6 py-3">
                    <h3 class="font-medium">Top Performer</h3>
                </div>
                <div class="p-6">
                    <?php if (isset($top_student['name'])): ?>
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-medium text-blue-900"><?php echo $top_student['name']; ?></h4>
                            <p class="text-sm text-slate-500">GPA: <span class="font-semibold text-green-600"><?php echo $top_student['gpa']; ?></span></p>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-slate-500">No data available</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Grade Distribution -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden lg:col-span-2">
                <div class="bg-blue-900 text-white px-6 py-3">
                    <h3 class="font-medium">Grade Distribution</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-4 gap-3">
                        <?php foreach($grade_distribution as $grade => $count): ?>
                        <div class="bg-slate-50 rounded-lg p-3 text-center">
                            <div class="text-lg font-bold 
                                <?php 
                                if ($grade == 'A+' || $grade == 'A') echo 'text-green-600';
                                else if ($grade == 'B+' || $grade == 'B') echo 'text-blue-600';
                                else if ($grade == 'C+' || $grade == 'C') echo 'text-yellow-600';
                                else echo 'text-red-600';
                                ?>">
                                <?php echo $grade; ?>
                            </div>
                            <div class="text-sm text-slate-500"><?php echo $count; ?> students</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Class Performance Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-blue-900">Class Performance</h2>
                <div class="flex items-center space-x-2">
                    <button onclick="window.print()" class="flex items-center text-sm text-blue-900 hover:text-blue-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print
                    </button>
                    <button class="flex items-center text-sm text-blue-900 hover:text-blue-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="performance-table w-full rounded-lg overflow-hidden">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">Student Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">Theory Marks</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">Practical Marks</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">Grade</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">GPA</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-slate-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php if ($class_performance->num_rows > 0): ?>
                                <?php while ($row = $class_performance->fetch_assoc()) { ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap font-medium text-blue-900"><?php echo $row['name']; ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo $row['theory_marks']; ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo $row['practical_marks']; ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="grade-pill 
                                                <?php
                                                switch ($row['grade']) {
                                                    case 'A+':
                                                    case 'A':
                                                        echo 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'B+':
                                                    case 'B':
                                                        echo 'bg-blue-100 text-blue-800';
                                                        break;
                                                    case 'C+':
                                                    case 'C':
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    default:
                                                        echo 'bg-red-100 text-red-800';
                                                        break;
                                                }
                                                ?>">
                                                <?php echo $row['grade']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-medium
                                            <?php echo $row['gpa'] >= 3.5 ? 'text-green-600' : ($row['gpa'] >= 2.5 ? 'text-blue-600' : 'text-red-600'); ?>">
                                            <?php echo $row['gpa']; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <a href="edit_result.php?student_id=<?php echo $row['student_id']; ?>&subject_id=<?php echo $teacher['subject_id']; ?>" class="text-blue-900 hover:text-blue-700 mx-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <a href="view_student.php?id=<?php echo $row['student_id']; ?>" class="text-blue-900 hover:text-blue-700 mx-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-3 text-center text-slate-500">No student records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <footer class="bg-white mt-12 border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <p class="text-center text-sm text-slate-500">© 2025 Result Management System. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>