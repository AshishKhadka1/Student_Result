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
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

// Get class toppers
$class_toppers = [];

if (!empty($class_id) || !empty($exam_id)) {
    // Check if student_performance table exists
    $table_exists = $conn->query("SHOW TABLES LIKE 'student_performance'");
    
    if ($table_exists->num_rows > 0) {
        // Use student_performance table if it exists
        $query = "
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
                sp.average_marks,
                sp.rank
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
            WHERE 1=1
        ";
        
        if (!empty($class_id)) {
            $query .= " AND c.class_id = $class_id";
        }
        
        if (!empty($exam_id)) {
            $query .= " AND e.exam_id = $exam_id";
        }
        
        $query .= "
            ORDER BY 
                c.class_name, c.section, e.exam_name, sp.rank
            LIMIT $limit
        ";
    } else {
        // Use results table if student_performance doesn't exist
        $query = "
            SELECT 
                c.class_id, 
                c.class_name, 
                c.section, 
                s.student_id, 
                s.roll_number, 
                u.full_name,
                r.exam_id,
                e.exam_name,
                AVG(r.theory_marks + r.practical_marks) as average_marks,
                COUNT(r.subject_id) as subjects_count
            FROM 
                results r
            JOIN 
                students s ON r.student_id = s.student_id
            JOIN 
                users u ON s.user_id = u.user_id
            JOIN 
                classes c ON s.class_id = c.class_id
            JOIN 
                exams e ON r.exam_id = e.exam_id
            WHERE 1=1
        ";
        
        if (!empty($class_id)) {
            $query .= " AND c.class_id = $class_id";
        }
        
        if (!empty($exam_id)) {
            $query .= " AND e.exam_id = $exam_id";
        }
        
        $query .= "
            GROUP BY 
                s.student_id, r.exam_id
            ORDER BY 
                c.class_name, c.section, e.exam_name, average_marks DESC
            LIMIT $limit
        ";
    }
    
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        // If using results table, calculate GPA
        if (!isset($row['gpa'])) {
            $avg_marks = $row['average_marks'];
            $gpa = 0;
            
            if ($avg_marks >= 90) {
                $gpa = 4.0;
            } elseif ($avg_marks >= 80) {
                $gpa = 3.7;
            } elseif ($avg_marks >= 70) {
                $gpa = 3.3;
            } elseif ($avg_marks >= 60) {
                $gpa = 3.0;
            } elseif ($avg_marks >= 50) {
                $gpa = 2.7;
            } elseif ($avg_marks >= 40) {
                $gpa = 2.3;
            } elseif ($avg_marks >= 33) {
                $gpa = 2.0;
            }
            
            $row['gpa'] = $gpa;
        }
        
        $class_toppers[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Toppers | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php
        // Include the file that processes form data
        include 'sidebar.php';
        ?>
        
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
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Class Toppers</h1>
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
                        <div class="mb-6 bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:p-6">
                                <form action="class_toppers.php" method="GET" class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                    <div class="sm:col-span-2">
                                        <label for="class_id" class="block text-sm font-medium text-gray-700">Class</label>
                                        <div class="mt-1">
                                            <select id="class_id" name="class_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                <option value="">All Classes</option>
                                                <?php foreach ($classes as $class): ?>
                                                    <option value="<?php echo $class['class_id']; ?>" <?php echo $class_id == $class['class_id'] ? 'selected' : ''; ?>>
                                                        <?php echo $class['class_name'] . ' ' . $class['section']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="sm:col-span-2">
                                        <label for="exam_id" class="block text-sm font-medium text-gray-700">Exam</label>
                                        <div class="mt-1">
                                            <select id="exam_id" name="exam_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                <option value="">All Exams</option>
                                                <?php foreach ($exams as $exam): ?>
                                                    <option value="<?php echo $exam['exam_id']; ?>" <?php echo $exam_id == $exam['exam_id'] ? 'selected' : ''; ?> data-class="<?php echo $exam['class_id']; ?>">
                                                        <?php echo $exam['exam_name'] . ' (' . ucfirst($exam['exam_type']) . ')'; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="sm:col-span-1">
                                        <label for="limit" class="block text-sm font-medium text-gray-700">Show Top</label>
                                        <div class="mt-1">
                                            <select id="limit" name="limit" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5</option>
                                                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                                                <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                                                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="sm:col-span-1 flex items-end">
                                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 w-full">
                                            <i class="fas fa-filter mr-2"></i> Filter
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Class Toppers Table -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">Class Toppers</h3>
                                    <p class="mt-1 text-sm text-gray-500">Students with highest GPA in each class</p>
                                </div>
                                <div>
                                    <a href="#" id="print-report" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-print mr-2"></i> Print Report
                                    </a>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GPA</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Marks</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($class_toppers)): ?>
                                            <tr>
                                                <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No data available. Please select a class and/or exam to view toppers.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php 
                                            $rank = 1;
                                            $current_class = '';
                                            $current_exam = '';
                                            
                                            foreach ($class_toppers as $index => $topper): 
                                                // Reset rank when class or exam changes
                                                $class_key = $topper['class_name'] . $topper['section'];
                                                $exam_key = $topper['exam_id'];
                                                
                                                if ($current_class != $class_key || $current_exam != $exam_key) {
                                                    $rank = 1;
                                                    $current_class = $class_key;
                                                    $current_exam = $exam_key;
                                                }
                                                
                                                // Use rank from database if available, otherwise use calculated rank
                                                $display_rank = isset($topper['rank']) ? $topper['rank'] : $rank;
                                            ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo $display_rank; ?>
                                                    </td>
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
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo number_format($topper['average_marks'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <a href="view_result.php?student_id=<?php echo $topper['student_id']; ?>&exam_id=<?php echo $topper['exam_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                            <i class="fas fa-eye mr-1"></i> View Result
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php 
                                                $rank++;
                                            endforeach; 
                                            ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
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
    </script>
</body>
</html>