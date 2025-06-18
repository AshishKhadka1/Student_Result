<?php
session_start();
include '../includes/config.php';
include '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get teacher ID from the teachers table
$teacher_query = "SELECT t.teacher_id, u.full_name 
                 FROM teachers t
                 JOIN users u ON t.user_id = u.user_id
                 WHERE t.user_id = ?";
$stmt = $conn->prepare($teacher_query);
if (!$stmt) {
    $error_message = "Database error: " . $conn->error;
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $teacher = $result->fetch_assoc();
        $teacher_id = $teacher['teacher_id'];
        $teacher_name = $teacher['full_name'];
    } else {
        $error_message = "Teacher record not found. Please contact the administrator.";
    }
    $stmt->close();
}

// Get filter parameters
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$selected_subject = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$selected_academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';

// Get all classes
$classes = [];
$classes_query = "SELECT c.class_id, c.class_name, c.section, c.academic_year
                 FROM classes c
                 ORDER BY c.class_name, c.section";
$stmt = $conn->prepare($classes_query);
if (!$stmt) {
    $error_message = "Database error: " . $conn->error;
} else {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt->close();
}

// Get all academic years
$academic_years = [];
foreach ($classes as $class) {
    if (!in_array($class['academic_year'], $academic_years)) {
        $academic_years[] = $class['academic_year'];
    }
}
sort($academic_years);

// Get subjects assigned to this teacher
$subjects = [];
$subjects_query = "SELECT s.subject_id, s.subject_name, s.subject_code 
                  FROM subjects s
                  JOIN teachersubjects ts ON s.subject_id = ts.subject_id
                  WHERE ts.teacher_id = ?
                  ORDER BY s.subject_name";
$stmt = $conn->prepare($subjects_query);
if (!$stmt) {
    // Try alternative query if the first one fails
    $alt_subjects_query = "SELECT s.subject_id, s.subject_name, s.subject_code 
                          FROM subjects s
                          ORDER BY s.subject_name";
    $stmt = $conn->prepare($alt_subjects_query);
    if (!$stmt) {
        $error_message = "Database error: " . $conn->error;
    } else {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    }
} else {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
}

// Get all exams
$exams = [];
$exams_query = "SELECT e.exam_id, e.exam_name, e.exam_type, e.academic_year, e.start_date, e.end_date
               FROM exams e
               ORDER BY e.start_date DESC";
$stmt = $conn->prepare($exams_query);
if (!$stmt) {
    $error_message = "Database error: " . $conn->error;
} else {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    $stmt->close();
}

// Get grade sheets based on filters
$grade_sheets = [];
$filter_conditions = [];
$filter_params = [];
$param_types = "";

$base_query = "SELECT 
                e.exam_id, e.exam_name, e.exam_type, e.academic_year, e.start_date, e.end_date,
                c.class_id, c.class_name, c.section,
                s.subject_id, s.subject_name, s.subject_code,
                COUNT(DISTINCT r.student_id) as students_count,
                COUNT(r.result_id) as results_count,
                AVG(r.percentage) as average_percentage,
                SUM(CASE WHEN r.remarks = 'Pass' THEN 1 ELSE 0 END) as pass_count
              FROM exams e
              JOIN results r ON e.exam_id = r.exam_id
              JOIN students st ON r.student_id = st.student_id
              JOIN classes c ON st.class_id = c.class_id
              JOIN subjects s ON r.subject_id = s.subject_id
              JOIN teachersubjects ts ON s.subject_id = ts.subject_id AND c.class_id = ts.class_id";

// Add teacher filter
$base_query .= " WHERE ts.teacher_id = ?";
$param_types .= "i";
$filter_params[] = $teacher_id;

// Add filters based on selection
if ($selected_class) {
    $filter_conditions[] = "c.class_id = ?";
    $param_types .= "i";
    $filter_params[] = $selected_class;
}

if ($selected_subject) {
    $filter_conditions[] = "s.subject_id = ?";
    $param_types .= "i";
    $filter_params[] = $selected_subject;
}

if ($selected_exam) {
    $filter_conditions[] = "e.exam_id = ?";
    $param_types .= "i";
    $filter_params[] = $selected_exam;
}

if ($selected_academic_year) {
    $filter_conditions[] = "e.academic_year = ?";
    $param_types .= "s";
    $filter_params[] = $selected_academic_year;
}

// Add filter conditions to query
if (!empty($filter_conditions)) {
    $base_query .= " AND " . implode(" AND ", $filter_conditions);
}

// Group by and order by
$base_query .= " GROUP BY e.exam_id, c.class_id, s.subject_id
                ORDER BY e.start_date DESC, c.class_name, s.subject_name";

// Execute query
$stmt = $conn->prepare($base_query);
if (!$stmt) {
    $error_message = "Database error: " . $conn->error;
    
    // Try alternative query with different join structure
    $alt_base_query = "SELECT 
                        e.exam_id, e.exam_name, e.exam_type, e.academic_year, e.start_date, e.end_date,
                        c.class_id, c.class_name, c.section,
                        s.subject_id, s.subject_name, s.subject_code,
                        COUNT(DISTINCT r.student_id) as students_count,
                        COUNT(r.result_id) as results_count,
                        AVG(IFNULL(r.percentage, 0)) as average_percentage,
                        SUM(CASE WHEN r.remarks = 'Pass' THEN 1 ELSE 0 END) as pass_count
                      FROM teachersubjects ts
                      JOIN subjects s ON ts.subject_id = s.subject_id
                      JOIN classes c ON ts.class_id = c.class_id
                      JOIN exams e ON e.academic_year = c.academic_year
                      LEFT JOIN results r ON e.exam_id = r.exam_id AND s.subject_id = r.subject_id
                      LEFT JOIN students st ON r.student_id = st.student_id AND st.class_id = c.class_id
                      WHERE ts.teacher_id = ?";
    
    // Add filter conditions to alternative query
    if (!empty($filter_conditions)) {
        $alt_base_query .= " AND " . implode(" AND ", $filter_conditions);
    }
    
    // Group by and order by for alternative query
    $alt_base_query .= " GROUP BY e.exam_id, c.class_id, s.subject_id
                        ORDER BY e.start_date DESC, c.class_name, s.subject_name";
    
    $stmt = $conn->prepare($alt_base_query);
    if (!$stmt) {
        // If alternative query also fails, keep the error message
    } else {
        if (!empty($filter_params)) {
            $stmt->bind_param($param_types, ...$filter_params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $grade_sheets[] = $row;
        }
        $stmt->close();
        $error_message = ''; // Clear error if alternative query succeeds
    }
} else {
    if (!empty($filter_params)) {
        $stmt->bind_param($param_types, ...$filter_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $grade_sheets[] = $row;
    }
    $stmt->close();
}

// Get recent activity (last 5 results entered)
$recent_activity = [];
$activity_query = "SELECT 
                    r.result_id, r.updated_at, 
                    s.subject_name, 
                    c.class_name, c.section,
                    e.exam_name,
                    u.full_name as student_name,
                    r.grade, r.remarks
                  FROM results r
                  JOIN subjects s ON r.subject_id = s.subject_id
                  JOIN students st ON r.student_id = st.student_id
                  JOIN classes c ON st.class_id = c.class_id
                  JOIN exams e ON r.exam_id = e.exam_id
                  JOIN users u ON st.user_id = u.user_id
                  JOIN teachersubjects ts ON s.subject_id = ts.subject_id AND c.class_id = ts.class_id
                  WHERE ts.teacher_id = ?
                  ORDER BY r.updated_at DESC
                  LIMIT 5";

$stmt = $conn->prepare($activity_query);
if (!$stmt) {
    // Skip recent activity if query fails
} else {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
    $stmt->close();
}

// Get pending tasks (classes/subjects with no results)
$pending_tasks = [];
$pending_query = "SELECT 
                    c.class_id, c.class_name, c.section,
                    s.subject_id, s.subject_name,
                    e.exam_id, e.exam_name, e.end_date
                  FROM teachersubjects ts
                  JOIN classes c ON ts.class_id = c.class_id
                  JOIN subjects s ON ts.subject_id = s.subject_id
                  JOIN exams e ON e.academic_year = c.academic_year
                  LEFT JOIN (
                    SELECT DISTINCT exam_id, class_id, subject_id
                    FROM results
                  ) r ON e.exam_id = r.exam_id AND c.class_id = r.class_id AND s.subject_id = r.subject_id
                  WHERE ts.teacher_id = ? AND r.exam_id IS NULL
                  ORDER BY e.end_date DESC
                  LIMIT 5";

$stmt = $conn->prepare($pending_query);
if (!$stmt) {
    // Skip pending tasks if query fails
} else {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pending_tasks[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Sheets | Teacher Dashboard</title>
    <link href="../css/tailwind.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .grade-card {
            transition: all 0.3s ease;
        }
        
        .grade-card:hover {
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
        
        .status-badge.good {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.average {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.poor {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
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
    
    
    <div class="flex">
        <?php include 'includes/teacher_sidebar.php'; ?>
        
        <div class="flex-1 p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Grade Sheets</h1>
                <div class="flex space-x-3">
                    <a href="teacher_dashboard.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            
            <?php if (!empty($success_message)): ?>
            <div id="success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 fade-in" role="alert">
                <div class="flex items-center">
                    <div class="py-1"><i class="fas fa-check-circle text-green-500 mr-3"></i></div>
                    <div>
                        <p class="font-bold">Success!</p>
                        <p><?php echo $success_message; ?></p>
                    </div>
                    <button type="button" class="ml-auto" onclick="document.getElementById('success-alert').style.display='none'">
                        <i class="fas fa-times text-green-500"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div id="error-alert" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 fade-in" role="alert">
                <div class="flex items-center">
                    <div class="py-1"><i class="fas fa-exclamation-circle text-red-500 mr-3"></i></div>
                    <div>
                        <p class="font-bold">Error!</p>
                        <p><?php echo $error_message; ?></p>
                    </div>
                    <button type="button" class="ml-auto" onclick="document.getElementById('error-alert').style.display='none'">
                        <i class="fas fa-times text-red-500"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Teacher Info Card -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($teacher_name ?? 'Teacher'); ?></h2>
                        <p class="mt-1 text-sm text-gray-600">Manage and view grade sheets for your assigned classes and subjects.</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <a href="edit_results.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-plus-circle mr-2"></i> Add New Results
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Grade Sheets</h3>
                
                <form action="" method="get" class="filter-container flex flex-wrap gap-4 mb-4">
                    <!-- Class Filter -->
                    <div class="w-full sm:w-auto">
                        <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select id="class_id" name="class_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>" <?php echo $selected_class == $class['class_id'] ? 'selected' : ''; ?>>
                                    <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Subject Filter -->
                    <div class="w-full sm:w-auto">
                        <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <select id="subject_id" name="subject_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_id']; ?>" <?php echo $selected_subject == $subject['subject_id'] ? 'selected' : ''; ?>>
                                    <?php echo $subject['subject_name'] . ' (' . $subject['subject_code'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Exam Filter -->
                    <div class="w-full sm:w-auto">
                        <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-1">Exam</label>
                        <select id="exam_id" name="exam_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">All Exams</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['exam_id']; ?>" <?php echo $selected_exam == $exam['exam_id'] ? 'selected' : ''; ?>>
                                    <?php echo $exam['exam_name'] . ' (' . $exam['academic_year'] . ')'; ?>
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
                                <option value="<?php echo $year; ?>" <?php echo $selected_academic_year == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
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
                    <?php if ($selected_class || $selected_subject || $selected_exam || $selected_academic_year): ?>
                        <div class="w-full sm:w-auto flex items-end">
                            <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-times mr-2"></i> Reset Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
                
                <!-- Active Filters -->
                <?php if ($selected_class || $selected_subject || $selected_exam || $selected_academic_year): ?>
                    <div class="mt-4">
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Active Filters:</h4>
                        <div>
                            <?php if ($selected_class): ?>
                                <?php 
                                $class_name = '';
                                foreach ($classes as $class) {
                                    if ($class['class_id'] == $selected_class) {
                                        $class_name = $class['class_name'] . ' ' . $class['section'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="filter-badge">
                                    Class: <?php echo $class_name; ?>
                                    <a href="?<?php 
                                        $params = $_GET;
                                        unset($params['class_id']);
                                        echo http_build_query($params);
                                    ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($selected_subject): ?>
                                <?php 
                                $subject_name = '';
                                foreach ($subjects as $subject) {
                                    if ($subject['subject_id'] == $selected_subject) {
                                        $subject_name = $subject['subject_name'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="filter-badge">
                                    Subject: <?php echo $subject_name; ?>
                                    <a href="?<?php 
                                        $params = $_GET;
                                        unset($params['subject_id']);
                                        echo http_build_query($params);
                                    ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($selected_exam): ?>
                                <?php 
                                $exam_name = '';
                                foreach ($exams as $exam) {
                                    if ($exam['exam_id'] == $selected_exam) {
                                        $exam_name = $exam['exam_name'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="filter-badge">
                                    Exam: <?php echo $exam_name; ?>
                                    <a href="?<?php 
                                        $params = $_GET;
                                        unset($params['exam_id']);
                                        echo http_build_query($params);
                                    ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($selected_academic_year): ?>
                                <span class="filter-badge">
                                    Academic Year: <?php echo $selected_academic_year; ?>
                                    <a href="?<?php 
                                        $params = $_GET;
                                        unset($params['academic_year']);
                                        echo http_build_query($params);
                                    ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Grade Sheets Grid -->
            <div class="mb-8">
                <?php if (empty($grade_sheets)): ?>
                    <div class="bg-white shadow rounded-lg p-8 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 text-yellow-500 mb-4">
                            <i class="fas fa-exclamation-circle text-3xl"></i>
                        </div>
                        <h2 class="text-xl font-medium text-gray-900 mb-2">No Grade Sheets Found</h2>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">
                            <?php if ($selected_class || $selected_subject || $selected_exam || $selected_academic_year): ?>
                                No grade sheets match your filter criteria. Try adjusting your filters or view all grade sheets.
                            <?php else: ?>
                                You don't have any grade sheets available yet. Start by adding results for your assigned classes and subjects.
                            <?php endif; ?>
                        </p>
                        
                        <?php if ($selected_class || $selected_subject || $selected_exam || $selected_academic_year): ?>
                            <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-list mr-2"></i> View All Grade Sheets
                            </a>
                        <?php else: ?>
                            <a href="edit_results.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-plus-circle mr-2"></i> Add New Results
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($grade_sheets as $sheet): ?>
                            <div class="bg-white shadow rounded-lg overflow-hidden grade-card">
                                <div class="bg-blue-600 text-white px-4 py-3">
                                    <div class="flex justify-between items-center">
                                        <h3 class="font-semibold"><?php echo $sheet['exam_name']; ?></h3>
                                        <span class="text-xs bg-blue-500 px-2 py-1 rounded-full">
                                            <?php echo $sheet['exam_type'] ?? 'Exam'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="p-4">
                                    <div class="flex justify-between items-center mb-3">
                                        <span class="text-sm text-gray-500">
                                            <i class="far fa-calendar-alt mr-1"></i> 
                                            <?php echo $sheet['academic_year']; ?>
                                        </span>
                                        
                                        <?php 
                                        $percentage = $sheet['average_percentage'] ?? 0;
                                        $status_class = 'poor';
                                        $status_text = 'Poor';
                                        
                                        if ($percentage >= 70) {
                                            $status_class = 'good';
                                            $status_text = 'Good';
                                        } elseif ($percentage >= 40) {
                                            $status_class = 'average';
                                            $status_text = 'Average';
                                        }
                                        ?>
                                        
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="text-sm font-medium text-gray-700">
                                            <i class="fas fa-book mr-1 text-blue-500"></i> 
                                            <?php echo $sheet['subject_name']; ?> (<?php echo $sheet['subject_code']; ?>)
                                        </p>
                                        <p class="text-sm font-medium text-gray-700">
                                            <i class="fas fa-users mr-1 text-green-500"></i> 
                                            <?php echo $sheet['class_name'] . ' ' . $sheet['section']; ?>
                                        </p>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-2 mb-4">
                                        <div class="bg-gray-50 p-2 rounded">
                                            <p class="text-xs text-gray-500">Students</p>
                                            <p class="font-semibold"><?php echo $sheet['students_count']; ?></p>
                                        </div>
                                        <div class="bg-gray-50 p-2 rounded">
                                            <p class="text-xs text-gray-500">Pass Rate</p>
                                            <p class="font-semibold">
                                                <?php 
                                                $pass_rate = ($sheet['students_count'] > 0) ? 
                                                    round(($sheet['pass_count'] / $sheet['students_count']) * 100) : 0;
                                                echo $pass_rate . '%'; 
                                                ?>
                                            </p>
                                        </div>
                                        <div class="bg-gray-50 p-2 rounded">
                                            <p class="text-xs text-gray-500">Average</p>
                                            <p class="font-semibold"><?php echo round($sheet['average_percentage'], 1); ?>%</p>
                                        </div>
                                        <div class="bg-gray-50 p-2 rounded">
                                            <p class="text-xs text-gray-500">Exam Date</p>
                                            <p class="font-semibold text-xs">
                                                <?php 
                                                if (isset($sheet['start_date'])) {
                                                    echo date('d M Y', strtotime($sheet['start_date']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <a href="edit_results.php?subject_id=<?php echo $sheet['subject_id']; ?>&class_id=<?php echo $sheet['class_id']; ?>&exam_id=<?php echo $sheet['exam_id']; ?>" 
                                           class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-edit mr-2"></i> Edit Results
                                        </a>
                                        <a href="view_students.php?subject_id=<?php echo $sheet['subject_id']; ?>&class_id=<?php echo $sheet['class_id']; ?>&exam_id=<?php echo $sheet['exam_id']; ?>" 
                                           class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-users mr-2"></i> Students
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        
            </div>
        </div>
    </div>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const successAlert = document.getElementById('success-alert');
            const errorAlert = document.getElementById('error-alert');
            
            if (successAlert) {
                successAlert.style.display = 'none';
            }
            
            if (errorAlert) {
                errorAlert.style.display = 'none';
            }
        }, 5000);
        
        // View Student Results
        function viewStudentResults(classId, examId, studentId) {
            const modal = document.getElementById('studentResultsModal');
            const content = document.getElementById('studentResultsContent');
            
            // Show modal with loading spinner
            modal.classList.remove('hidden');
            
            // Fetch student results
            fetch(`../Admin/get_student_results.php?id=${studentId}&exam_id=${examId}`)
                .then(response => response.text())
                .then(data => {
                    content.innerHTML = data;
                })
                .catch(error => {
                    content.innerHTML = `<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                        <p class="font-bold">Error</p>
                        <p>Failed to load student results. Please try again.</p>
                    </div>`;
                    console.error('Error:', error);
                });
        }
        
        // Close Student Results Modal
        function closeResultsModal() {
            const modal = document.getElementById('studentResultsModal');
            modal.classList.add('hidden');
        }
        
        // Edit Student Marks
        function editStudentMarks(resultId, studentId, examId, subjectId) {
            const modal = document.getElementById('editMarksModal');
            const content = document.getElementById('editMarksContent');
            
            // Show modal with loading spinner
            modal.classList.remove('hidden');
            
            // Fetch the edit form
            fetch(`get_edit_marks_form.php?result_id=${resultId}&student_id=${studentId}&exam_id=${examId}&subject_id=${subjectId}`)
                .then(response => response.text())
                .then(data => {
                    content.innerHTML = data;
                })
                .catch(error => {
                    content.innerHTML = `<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                        <p class="font-bold">Error</p>
                        <p>Failed to load edit form. Please try again.</p>
                    </div>`;
                    console.error('Error:', error);
                });
        }
        
        // Close Edit Marks Modal
        function closeEditMarksModal() {
            const modal = document.getElementById('editMarksModal');
            modal.classList.add('hidden');
        }
        
        // Update Student Marks
        function updateStudentMarks(formId) {
            const form = document.getElementById(formId);
            const formData = new FormData(form);
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
            
            // Clear previous error messages
            const errorElements = form.querySelectorAll('.error-message');
            errorElements.forEach(el => el.remove());
            
            fetch('update_student_marks.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const successMsg = document.createElement('div');
                    successMsg.className = 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4';
                    successMsg.innerHTML = `
                        <div class="flex items-center">
                            <div class="py-1"><i class="fas fa-check-circle text-green-500 mr-3"></i></div>
                            <div>
                                <p class="font-bold">Success!</p>
                                <p>${data.message}</p>
                            </div>
                        </div>
                    `;
                    form.prepend(successMsg);
                    
                    // Close modal after 2 seconds and refresh the page
                    setTimeout(() => {
                        closeEditMarksModal();
                        location.reload();
                    }, 2000);
                } else {
                    // Show error message
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4';
                    errorMsg.innerHTML = `
                        <div class="flex items-center">
                            <div class="py-1"><i class="fas fa-exclamation-circle text-red-500 mr-3"></i></div>
                            <div>
                                <p class="font-bold">Error!</p>
                                <p>${data.message}</p>
                            </div>
                        </div>
                    `;
                    form.prepend(errorMsg);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Show error message
                const errorMsg = document.createElement('div');
                errorMsg.className = 'bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4';
                errorMsg.innerHTML = `
                    <div class="flex items-center">
                        <div class="py-1"><i class="fas fa-exclamation-circle text-red-500 mr-3"></i></div>
                        <div>
                            <p class="font-bold">Error!</p>
                            <p>An unexpected error occurred. Please try again.</p>
                        </div>
                    </div>
                `;
                form.prepend(errorMsg);
            })
            .finally(() => {
                // Restore button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
            
            return false; // Prevent form submission
        }
    </script>
    
    <!-- Student Results Modal -->
    <div id="studentResultsModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-4/5 lg:w-3/4 max-h-screen overflow-y-auto">
            <div id="studentResultsContent" class="p-4">
                <!-- Content will be loaded dynamically -->
                <div class="flex justify-center items-center h-64">
                    <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-500"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Marks Modal -->
    <div id="editMarksModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/5 lg:w-2/5 max-h-screen overflow-y-auto">
            <div class="bg-blue-600 text-white px-4 py-3 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Edit Student Marks</h3>
                <button onclick="closeEditMarksModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="editMarksContent" class="p-6">
                <!-- Content will be loaded dynamically -->
                <div class="flex justify-center items-center h-64">
                    <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-500"></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
