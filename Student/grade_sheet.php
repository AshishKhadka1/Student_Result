<?php
// Start session for authentication check
session_start();

// Check if user is logged in as a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Connect to database with error handling
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get student information
try {
    $stmt = $conn->prepare("
        SELECT s.student_id, s.roll_number, s.registration_number, u.full_name, 
               c.class_name, c.section, c.academic_year
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        JOIN classes c ON s.class_id = c.class_id
        WHERE s.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        die("Student record not found. Please contact administrator.");
    }

    $student = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching student details: " . $e->getMessage());
    die("An error occurred while retrieving student information. Please try again later.");
}

// Get available exams for this student
$exams = [];
try {
    // First try to get exams with exam_type field
    $query = "
        SELECT DISTINCT e.exam_id, e.exam_name, e.exam_type, e.academic_year, e.start_date, e.end_date
        FROM exams e
        JOIN results r ON e.exam_id = r.exam_id
        WHERE r.student_id = ?
        ORDER BY e.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("s", $student['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // If exam_type is NULL or empty, use exam_name as the type
        if (empty($row['exam_type'])) {
            $row['exam_type'] = $row['exam_name'];
        }
        $exams[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching exams: " . $e->getMessage());
    
    // Try an alternative query if the first one fails (e.g., if exam_type column doesn't exist)
    try {
        $alt_query = "
            SELECT DISTINCT e.exam_id, e.exam_name, e.academic_year, e.start_date, e.end_date
            FROM exams e
            JOIN results r ON e.exam_id = r.exam_id
            WHERE r.student_id = ?
            ORDER BY e.created_at DESC
        ";
        
        $stmt = $conn->prepare($alt_query);
        if ($stmt === false) {
            throw new Exception("Failed to prepare alternative statement: " . $conn->error);
        }
        
        $stmt->bind_param("s", $student['student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Add exam_type field with the same value as exam_name
            $row['exam_type'] = $row['exam_name'];
            $exams[] = $row;
        }
        $stmt->close();
    } catch (Exception $e2) {
        error_log("Error fetching exams (alternative query): " . $e2->getMessage());
    }
}

// Get available exam types for filtering
$exam_types = [];
try {
    // Check if exam_type column exists in exams table
    $check_column = $conn->query("SHOW COLUMNS FROM exams LIKE 'exam_type'");
    
    if ($check_column->num_rows > 0) {
        // Column exists, get distinct exam types
        $stmt = $conn->prepare("SELECT DISTINCT exam_type FROM exams WHERE exam_type IS NOT NULL ORDER BY exam_type");
        if ($stmt === false) {
            throw new Exception("Failed to prepare exam types statement: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['exam_type'])) {
                $exam_types[] = $row['exam_type'];
            }
        }
        $stmt->close();
    } else {
        // Column doesn't exist, use exam names instead
        $stmt = $conn->prepare("SELECT DISTINCT exam_name FROM exams ORDER BY exam_name");
        if ($stmt === false) {
            throw new Exception("Failed to prepare exam names statement: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $exam_types[] = $row['exam_name'];
        }
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching exam types: " . $e->getMessage());
}

// If no exam types found, add some defaults
if (empty($exam_types)) {
    $exam_types = ['Yearly Exam', 'Term Exam', 'Mid-Term Exam', 'Final Exam'];
}

// Get selected exam type from URL parameter
$selected_exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';

// Get selected academic year from URL parameter
$selected_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';

// Get all available academic years
$academic_years = [];
foreach ($exams as $exam) {
    if (!in_array($exam['academic_year'], $academic_years)) {
        $academic_years[] = $exam['academic_year'];
    }
}
sort($academic_years);

// Filter exams based on selected filters
$filtered_exams = $exams;

if (!empty($selected_exam_type)) {
    $filtered_exams = array_filter($filtered_exams, function($exam) use ($selected_exam_type) {
        return $exam['exam_type'] == $selected_exam_type;
    });
}

if (!empty($selected_year)) {
    $filtered_exams = array_filter($filtered_exams, function($exam) use ($selected_year) {
        return $exam['academic_year'] == $selected_year;
    });
}

// Get student performance data for each exam
$exam_performances = [];
try {
    // Check if student_performance table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'student_performance'");
    
    if ($check_table->num_rows > 0) {
        foreach ($exams as $exam) {
            $stmt = $conn->prepare("
                SELECT * FROM student_performance 
                WHERE student_id = ? AND exam_id = ?
            ");
            if ($stmt === false) {
                throw new Exception("Failed to prepare performance statement: " . $conn->error);
            }
            
            $stmt->bind_param("si", $student['student_id'], $exam['exam_id']);
            $stmt->execute();
            $performance_result = $stmt->get_result();

            if ($performance_result->num_rows > 0) {
                $performance = $performance_result->fetch_assoc();
                $exam_performances[$exam['exam_id']] = [
                    'gpa' => $performance['gpa'],
                    'percentage' => $performance['average_marks'],
                    'rank' => $performance['rank'] ?? 'N/A'
                ];
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Error fetching performance data: " . $e->getMessage());
}

// If no performance data is available, calculate basic stats
if (empty($exam_performances)) {
    foreach ($exams as $exam) {
        try {
            $stmt = $conn->prepare("
                SELECT r.*, s.full_marks_theory, s.full_marks_practical
                FROM results r
                JOIN subjects s ON r.subject_id = s.subject_id
                WHERE r.student_id = ? AND r.exam_id = ?
            ");
            if ($stmt === false) {
                throw new Exception("Failed to prepare results statement: " . $conn->error);
            }
            
            $stmt->bind_param("si", $student['student_id'], $exam['exam_id']);
            $stmt->execute();
            $results_data = $stmt->get_result();

            $total_marks = 0;
            $max_marks = 0;

            while ($row = $results_data->fetch_assoc()) {
                $theory_marks = $row['theory_marks'] ?? 0;
                $practical_marks = $row['practical_marks'] ?? 0;
                $total_subject_marks = $theory_marks + $practical_marks;
                $subject_max_marks = $row['full_marks_theory'] + $row['full_marks_practical'];
                
                $total_marks += $total_subject_marks;
                $max_marks += $subject_max_marks;
            }

            $stmt->close();
            
            // Calculate percentage
            $percentage = $max_marks > 0 ? ($total_marks / $max_marks) * 100 : 0;
            
            $exam_performances[$exam['exam_id']] = [
                'percentage' => $percentage,
                'total_marks' => $total_marks,
                'max_marks' => $max_marks
            ];
        } catch (Exception $e) {
            error_log("Error calculating exam stats: " . $e->getMessage());
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
    <title>Grade Sheets | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filter-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: #e5e7eb;
            color: #374151;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .filter-badge button {
            margin-left: 0.25rem;
            color: #6b7280;
        }
        
        .filter-badge button:hover {
            color: #ef4444;
        }
        
        .exam-card {
            transition: all 0.3s ease;
        }
        
        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.pass {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.fail {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .status-badge i {
            margin-right: 0.25rem;
        }
        
        @media (max-width: 640px) {
            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-container > div {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include('./includes/student_sidebar.php'); ?>

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
                        <a href="student_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="grade_sheet.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-file-alt mr-3"></i>
                            Grade Sheets
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
            <?php include('./includes/top_navigation.php'); ?>

            <!-- Main Content Area -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-bold text-gray-900">
                                <i class="fas fa-file-alt mr-2"></i> Grade Sheets
                            </h1>
                            <a href="student_dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                            </a>
                        </div>

                        <!-- Student Info Card -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                                    <div class="mt-2 text-sm text-gray-600">
                                        <p><span class="font-medium">Roll Number:</span> <?php echo htmlspecialchars($student['roll_number']); ?></p>
                                        <p><span class="font-medium">Registration Number:</span> <?php echo htmlspecialchars($student['registration_number']); ?></p>
                                        <p><span class="font-medium">Class:</span> <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?></p>
                                    </div>
                                </div>
                                <div class="mt-4 md:mt-0">
                                    <div class="inline-flex items-center px-4 py-2 bg-blue-50 rounded-full text-sm font-medium text-blue-700">
                                        <i class="fas fa-graduation-cap mr-2"></i>
                                        Academic Year: <?php echo htmlspecialchars($student['academic_year']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (count($exams) > 0): ?>
                            <!-- Filters -->
                            <div class="bg-white shadow rounded-lg p-6 mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Grade Sheets</h3>
                                
                                <form action="" method="get" class="filter-container flex flex-wrap gap-4 mb-4">
                                    <!-- Exam Type Filter -->
                                    <div class="w-full sm:w-auto">
                                        <label for="exam_type" class="block text-sm font-medium text-gray-700 mb-1">Exam Type</label>
                                        <select id="exam_type" name="exam_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                            <option value="">All Exam Types</option>
                                            <?php foreach ($exam_types as $type): ?>
                                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $selected_exam_type === $type ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($type); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Academic Year Filter -->
                                    <div class="w-full sm:w-auto">
                                        <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                                        <select id="academic_year" name="academic_year" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                            <option value="">All Years</option>
                                            <?php foreach ($academic_years as $year): ?>
                                                <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $selected_year === $year ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($year); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Filter Button -->
                                    <div class="w-full sm:w-auto flex items-end">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-filter mr-2"></i> Apply Filters
                                        </button>
                                    </div>
                                    
                                    <!-- Reset Filters -->
                                    <?php if (!empty($selected_exam_type) || !empty($selected_year)): ?>
                                        <div class="w-full sm:w-auto flex items-end">
                                            <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-times mr-2"></i> Reset Filters
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </form>
                                
                                <!-- Active Filters -->
                                <?php if (!empty($selected_exam_type) || !empty($selected_year)): ?>
                                    <div class="mt-4">
                                        <h4 class="text-sm font-medium text-gray-500 mb-2">Active Filters:</h4>
                                        <div>
                                            <?php if (!empty($selected_exam_type)): ?>
                                                <span class="filter-badge">
                                                    Exam Type: <?php echo htmlspecialchars($selected_exam_type); ?>
                                                    <a href="?<?php echo !empty($selected_year) ? 'academic_year=' . urlencode($selected_year) : ''; ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                                        <i class="fas fa-times-circle"></i>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($selected_year)): ?>
                                                <span class="filter-badge">
                                                    Academic Year: <?php echo htmlspecialchars($selected_year); ?>
                                                    <a href="?<?php echo !empty($selected_exam_type) ? 'exam_type=' . urlencode($selected_exam_type) : ''; ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                                        <i class="fas fa-times-circle"></i>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Exam Results Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                                <?php if (empty($filtered_exams)): ?>
                                    <div class="col-span-full bg-white shadow rounded-lg p-6 text-center">
                                        <i class="fas fa-search text-gray-400 text-4xl mb-3"></i>
                                        <h3 class="text-lg font-medium text-gray-900 mb-1">No Results Found</h3>
                                        <p class="text-gray-500">No exam results match your filter criteria. Try adjusting your filters or view all results.</p>
                                        <a href="grade_sheet.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-list mr-2"></i> View All Results
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($filtered_exams as $exam): ?>
                                        <div class="bg-white shadow rounded-lg overflow-hidden exam-card">
                                            <div class="bg-blue-600 text-white px-4 py-3">
                                                <div class="flex justify-between items-center">
                                                    <h3 class="font-semibold"><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                                                    <span class="text-xs bg-blue-500 px-2 py-1 rounded-full">
                                                        <?php echo htmlspecialchars($exam['exam_type']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="p-4">
                                                <div class="flex justify-between items-center mb-3">
                                                    <span class="text-sm text-gray-500">
                                                        <i class="far fa-calendar-alt mr-1"></i> 
                                                        <?php echo htmlspecialchars($exam['academic_year']); ?>
                                                    </span>
                                                    
                                                    <?php if (isset($exam_performances[$exam['exam_id']])): ?>
                                                        <?php 
                                                        $performance = $exam_performances[$exam['exam_id']];
                                                        $percentage = isset($performance['percentage']) ? $performance['percentage'] : 0;
                                                        $isPassed = $percentage >= 33;
                                                        ?>
                                                        <span class="status-badge <?php echo $isPassed ? 'pass' : 'fail'; ?>">
                                                            <i class="fas <?php echo $isPassed ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                                            <?php echo $isPassed ? 'Pass' : 'Fail'; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="space-y-2 mb-4">
                                                    <?php if (isset($exam['start_date']) && isset($exam['end_date'])): ?>
                                                        <div class="text-sm">
                                                            <span class="font-medium text-gray-700">Exam Period:</span>
                                                            <span class="text-gray-600">
                                                                <?php 
                                                                echo date('M d, Y', strtotime($exam['start_date'])); 
                                                                echo ' - '; 
                                                                echo date('M d, Y', strtotime($exam['end_date']));
                                                                ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (isset($exam_performances[$exam['exam_id']])): ?>
                                                        <?php $performance = $exam_performances[$exam['exam_id']]; ?>
                                                        
                                                        <?php if (isset($performance['percentage'])): ?>
                                                            <div class="text-sm">
                                                                <span class="font-medium text-gray-700">Percentage:</span>
                                                                <span class="text-gray-600"><?php echo number_format($performance['percentage'], 2); ?>%</span>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (isset($performance['gpa'])): ?>
                                                            <div class="text-sm">
                                                                <span class="font-medium text-gray-700">GPA:</span>
                                                                <span class="text-gray-600"><?php echo number_format($performance['gpa'], 2); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (isset($performance['rank']) && $performance['rank'] !== 'N/A'): ?>
                                                            <div class="text-sm">
                                                                <span class="font-medium text-gray-700">Rank:</span>
                                                                <span class="text-gray-600"><?php echo htmlspecialchars($performance['rank']); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (isset($performance['total_marks']) && isset($performance['max_marks'])): ?>
                                                            <div class="text-sm">
                                                                <span class="font-medium text-gray-700">Marks:</span>
                                                                <span class="text-gray-600">
                                                                    <?php echo $performance['total_marks']; ?> / <?php echo $performance['max_marks']; ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mt-4">
                                                    <a href="view_grade_sheet.php?exam_id=<?php echo $exam['exam_id']; ?>" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        <i class="fas fa-eye mr-2"></i> View Detailed Result
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- No exams available -->
                            <div class="bg-white shadow rounded-lg p-8 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 text-yellow-500 mb-4">
                                    <i class="fas fa-exclamation-circle text-3xl"></i>
                                </div>
                                <h2 class="text-xl font-medium text-gray-900 mb-2">No Exam Results Available</h2>
                                <p class="text-gray-600 mb-6 max-w-md mx-auto">You don't have any exam results available yet. Please check back later or contact your teacher for more information.</p>
                                <a href="student_dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-arrow-left mr-2"></i> Return to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>
