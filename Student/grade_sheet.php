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

// Initialize variables
$student = [];
$exams = [];

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
try {
    // First try to get exams with exam_type field
    $query = "
        SELECT DISTINCT e.exam_id, e.exam_name, e.exam_type, e.academic_year, e.created_at,
               (SELECT COUNT(*) FROM results r WHERE r.exam_id = e.exam_id AND r.student_id = ?) as subject_count,
               (SELECT AVG(r.theory_marks + r.practical_marks) FROM results r WHERE r.exam_id = e.exam_id AND r.student_id = ?) as avg_marks
        FROM exams e
        JOIN results r ON e.exam_id = r.exam_id
        WHERE r.student_id = ?
        ORDER BY e.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("sss", $student['student_id'], $student['student_id'], $student['student_id']);
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
            SELECT DISTINCT e.exam_id, e.exam_name, e.academic_year, e.created_at,
                   (SELECT COUNT(*) FROM results r WHERE r.exam_id = e.exam_id AND r.student_id = ?) as subject_count,
                   (SELECT AVG(r.theory_marks + r.practical_marks) FROM results r WHERE r.exam_id = e.exam_id AND r.student_id = ?) as avg_marks
            FROM exams e
            JOIN results r ON e.exam_id = r.exam_id
            WHERE r.student_id = ?
            ORDER BY e.created_at DESC
        ";
        
        $stmt = $conn->prepare($alt_query);
        if ($stmt === false) {
            throw new Exception("Failed to prepare alternative statement: " . $conn->error);
        }
        
        $stmt->bind_param("sss", $student['student_id'], $student['student_id'], $student['student_id']);
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
        $stmt = $conn->prepare("
            SELECT DISTINCT e.exam_type 
            FROM exams e 
            JOIN results r ON e.exam_id = r.exam_id 
            WHERE r.student_id = ? AND e.exam_type IS NOT NULL 
            ORDER BY e.exam_type
        ");
        if ($stmt === false) {
            throw new Exception("Failed to prepare exam types statement: " . $conn->error);
        }
        
        $stmt->bind_param("s", $student['student_id']);
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
        $stmt = $conn->prepare("
            SELECT DISTINCT e.exam_name 
            FROM exams e 
            JOIN results r ON e.exam_id = r.exam_id 
            WHERE r.student_id = ? 
            ORDER BY e.exam_name
        ");
        if ($stmt === false) {
            throw new Exception("Failed to prepare exam names statement: " . $conn->error);
        }
        
        $stmt->bind_param("s", $student['student_id']);
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

// Get available academic years for this student
$academic_years = [];
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT e.academic_year 
        FROM exams e 
        JOIN results r ON e.exam_id = r.exam_id 
        WHERE r.student_id = ? 
        ORDER BY e.academic_year DESC
    ");
    if ($stmt === false) {
        throw new Exception("Failed to prepare academic years statement: " . $conn->error);
    }
    
    $stmt->bind_param("s", $student['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['academic_year'])) {
            $academic_years[] = $row['academic_year'];
        }
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching academic years: " . $e->getMessage());
}

// Apply filters
$filtered_exams = $exams;
$search_submitted = isset($_GET['search_submitted']) && $_GET['search_submitted'] == '1';

// Get selected filters from URL parameters
$selected_exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';
$selected_academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';
$selected_date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '';

// Apply exam type filter
if (!empty($selected_exam_type)) {
    $filtered_exams = array_filter($filtered_exams, function($exam) use ($selected_exam_type) {
        return $exam['exam_type'] == $selected_exam_type;
    });
}

// Apply academic year filter
if (!empty($selected_academic_year)) {
    $filtered_exams = array_filter($filtered_exams, function($exam) use ($selected_academic_year) {
        return $exam['academic_year'] == $selected_academic_year;
    });
}

// Apply date range filter
if (!empty($selected_date_range)) {
    $current_date = date('Y-m-d');
    $date_condition = '';
    
    switch($selected_date_range) {
        case 'last_month':
            $date_limit = date('Y-m-d', strtotime('-1 month'));
            break;
        case 'last_3months':
            $date_limit = date('Y-m-d', strtotime('-3 months'));
            break;
        case 'last_6months':
            $date_limit = date('Y-m-d', strtotime('-6 months'));
            break;
        case 'last_year':
            $date_limit = date('Y-m-d', strtotime('-1 year'));
            break;
        case 'current_year':
            $date_limit = date('Y-01-01');
            break;
        default:
            $date_limit = '';
    }
    
    if (!empty($date_limit)) {
        $filtered_exams = array_filter($filtered_exams, function($exam) use ($date_limit) {
            return strtotime($exam['created_at']) >= strtotime($date_limit);
        });
    }
}

// Get school settings
$settings = [];
try {
    // Check if settings table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'settings'");
    
    if ($check_table->num_rows > 0) {
        $result = $conn->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } else {
        // Default settings if table doesn't exist
        $settings = [
            'school_name' => 'School Name',
            'result_header' => 'Result Management System',
            'result_footer' => 'This is a computer-generated document. No signature is required.'
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    // Default settings if query fails
    $settings = [
        'school_name' => 'School Name',
        'result_header' => 'Result Management System',
        'result_footer' => 'This is a computer-generated document. No signature is required.'
    ];
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
    /* Card styles */
    .result-card {
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }
    
    .result-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: #3b82f6;
    }
    
    .result-card.featured {
        border-left: 4px solid #1e40af;
    }
    
    .result-icon {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
    }
    
    /* Filter styles */
    .filter-container {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        padding: 20px;
        margin-bottom: 30px;
    }

    .filter-title {
        font-size: 18px;
        font-weight: 600;
        color: #1e40af;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }

    .filter-title i {
        margin-right: 10px;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .filter-item {
        margin-bottom: 10px;
    }

    .filter-label {
        display: block;
        font-size: 14px;
        margin-bottom: 5px;
        color: #4a5568;
        font-weight: 500;
    }

    .filter-select {
        width: 100%;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background-color: white;
        font-size: 14px;
        transition: border-color 0.2s;
    }

    .filter-select:focus {
        border-color: #2563eb;
        outline: none;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .filter-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .filter-button {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .filter-button.active {
        background-color: #1e40af;
        color: white;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
    }

    .filter-button:not(.active) {
        background-color: #f0f7ff;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }

    .filter-button:not(.active):hover {
        background-color: #dbeafe;
    }

    .filter-actions {
        margin-top: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .active-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 15px;
    }

    .active-filter {
        background-color: #dbeafe;
        color: #1e40af;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 13px;
        display: flex;
        align-items: center;
    }

    .active-filter button {
        margin-left: 6px;
        color: #1e40af;
        opacity: 0.7;
        transition: opacity 0.2s;
    }

    .active-filter button:hover {
        opacity: 1;
    }

    /* Quick filters */
    .quick-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 15px;
    }

    .quick-filter {
        padding: 6px 12px;
        background-color: #f0f7ff;
        border: 1px solid #bfdbfe;
        border-radius: 20px;
        font-size: 13px;
        color: #1e40af;
        cursor: pointer;
        transition: all 0.2s;
    }

    .quick-filter:hover {
        background-color: #dbeafe;
        transform: translateY(-2px);
    }

    .quick-filter i {
        margin-right: 5px;
    }
    
    /* Empty state styles */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .empty-state-icon {
        font-size: 60px;
        color: #2563eb;
        margin-bottom: 20px;
    }

    .empty-state-title {
        font-size: 24px;
        font-weight: 600;
        color: #1e40af;
        margin-bottom: 15px;
    }

    .empty-state-message {
        font-size: 16px;
        color: #64748b;
        margin-bottom: 30px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }

    .empty-state-button {
        display: inline-flex;
        align-items: center;
        padding: 10px 20px;
        background-color: #1e40af;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }

    .empty-state-button:hover {
        background-color: #1e3a8a;
        transform: translateY(-2px);
    }

    .empty-state-button i {
        margin-right: 10px;
    }
    
    /* Student info card */
    .student-info-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        padding: 20px;
        margin-bottom: 20px;
        border-left: 4px solid #1e40af;
    }
    
    .student-info-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .student-avatar {
        width: 60px;
        height: 60px;
        background-color: #e0e7ff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        color: #1e40af;
        font-weight: bold;
        font-size: 24px;
    }
    
    .student-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
    }
    
    .student-detail-item {
        margin-bottom: 8px;
    }
    
    .student-detail-label {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 2px;
    }
    
    .student-detail-value {
        font-weight: 500;
        color: #1e3a8a;
    }
    
    /* Stats cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background-color: white;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 14px;
        color: #64748b;
    }
    
    /* Mobile optimizations */
    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .student-details {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
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
                            Grade Sheet
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
                            <h1 class="text-2xl font-bold text-blue-900">
                                <i class="fas fa-file-alt mr-2"></i> My Grade Sheets
                            </h1>
                            <a href="student_dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                            </a>
                        </div>
                        
                        <!-- Student Info Card -->
                        <div class="student-info-card">
                            <div class="student-info-header">
                                <div class="student-avatar">
                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                                    <p class="text-gray-600">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-user-graduate mr-1"></i> Student
                                        </span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 ml-2">
                                            <i class="fas fa-school mr-1"></i> <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="student-details">
                                <div class="student-detail-item">
                                    <div class="student-detail-label">Roll Number</div>
                                    <div class="student-detail-value"><?php echo htmlspecialchars($student['roll_number']); ?></div>
                                </div>
                                <div class="student-detail-item">
                                    <div class="student-detail-label">Registration Number</div>
                                    <div class="student-detail-value"><?php echo htmlspecialchars($student['registration_number']); ?></div>
                                </div>
                                <div class="student-detail-item">
                                    <div class="student-detail-label">Academic Year</div>
                                    <div class="student-detail-value"><?php echo htmlspecialchars($student['academic_year']); ?></div>
                                </div>
                                <div class="student-detail-item">
                                    <div class="student-detail-label">Total Exams</div>
                                    <div class="student-detail-value"><?php echo count($exams); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stats Cards -->
                        <?php if (count($exams) > 0): ?>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon bg-blue-100 text-blue-600">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="stat-value text-blue-600"><?php echo count($exams); ?></div>
                                <div class="stat-label">Total Exams</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon bg-green-100 text-green-600">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="stat-value text-green-600"><?php echo count($academic_years); ?></div>
                                <div class="stat-label">Academic Years</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon bg-purple-100 text-purple-600">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="stat-value text-purple-600"><?php echo count($exam_types); ?></div>
                                <div class="stat-label">Exam Types</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon bg-yellow-100 text-yellow-600">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-value text-yellow-600"><?php echo date('Y-m-d', strtotime($exams[0]['created_at'])); ?></div>
                                <div class="stat-label">Latest Exam</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (count($exams) > 0): ?>
                            
                        <div class="filter-container">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-filter text-blue-600 mr-2"></i>
                                    <h3 class="text-lg font-semibold text-gray-800">Filter Grade Sheets</h3>
                                </div>
                                <button id="toggle-filters" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                                    <i class="fas fa-chevron-down mr-1"></i> <span id="toggle-text">Hide Filters</span>
                                </button>
                            </div>
                            
                            <div id="filter-content">
                                <form action="" method="GET" id="filter-form">
                                    <input type="hidden" name="search_submitted" value="1">
                                    
                                    <!-- Quick Filters -->
                                    <div class="quick-filters">
                                        <button type="button" class="quick-filter" data-year="<?php echo date('Y'); ?>">
                                            <i class="fas fa-calendar-alt"></i> Current Year
                                        </button>
                                        <?php if (count($exam_types) > 0): ?>
                                            <button type="button" class="quick-filter" data-type="<?php echo htmlspecialchars($exam_types[0]); ?>">
                                                <i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($exam_types[0]); ?>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="quick-filter" data-date="current_year">
                                            <i class="fas fa-clock"></i> This Year Only
                                        </button>
                                        <button type="button" class="quick-filter" data-date="last_3months">
                                            <i class="fas fa-history"></i> Last 3 Months
                                        </button>
                                    </div>
                                    
                                    <div class="filter-grid">
                                        <!-- Exam Type Filter -->
                                        <div class="filter-item">
                                            <label for="exam_type" class="filter-label">Exam Type:</label>
                                            <select name="exam_type" id="exam_type" class="filter-select">
                                                <option value="">All Exam Types</option>
                                                <?php foreach ($exam_types as $type): ?>
                                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($selected_exam_type === $type) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($type); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Academic Year Filter -->
                                        <div class="filter-item">
                                            <label for="academic_year" class="filter-label">Academic Year:</label>
                                            <select name="academic_year" id="academic_year" class="filter-select">
                                                <option value="">All Years</option>
                                                <?php foreach ($academic_years as $year): ?>
                                                    <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($selected_academic_year === $year) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($year); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Date Range Filter -->
                                        <div class="filter-item">
                                            <label for="date_range" class="filter-label">Date Range:</label>
                                            <select name="date_range" id="date_range" class="filter-select">
                                                <option value="">Any Time</option>
                                                <option value="current_year" <?php echo ($selected_date_range === 'current_year') ? 'selected' : ''; ?>>This Year</option>
                                                <option value="last_month" <?php echo ($selected_date_range === 'last_month') ? 'selected' : ''; ?>>Last Month</option>
                                                <option value="last_3months" <?php echo ($selected_date_range === 'last_3months') ? 'selected' : ''; ?>>Last 3 Months</option>
                                                <option value="last_6months" <?php echo ($selected_date_range === 'last_6months') ? 'selected' : ''; ?>>Last 6 Months</option>
                                                <option value="last_year" <?php echo ($selected_date_range === 'last_year') ? 'selected' : ''; ?>>Last Year</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="filter-actions">
                                        <button type="button" id="reset-filters" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors flex items-center">
                                            <i class="fas fa-undo mr-2"></i> Reset Filters
                                        </button>
                                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center">
                                            <i class="fas fa-search mr-2"></i> Apply Filters
                                        </button>
                                    </div>
                                    
                                    <!-- Active Filters Display -->
                                    <?php if ($search_submitted && (!empty($selected_exam_type) || !empty($selected_academic_year) || !empty($selected_date_range))): ?>
                                        <div class="active-filters">
                                            <?php if (!empty($selected_exam_type)): ?>
                                                <div class="active-filter">
                                                    <span>Exam Type: <?php echo htmlspecialchars($selected_exam_type); ?></span>
                                                    <button type="button" onclick="removeFilter('exam_type')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($selected_academic_year)): ?>
                                                <div class="active-filter">
                                                    <span>Year: <?php echo htmlspecialchars($selected_academic_year); ?></span>
                                                    <button type="button" onclick="removeFilter('academic_year')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($selected_date_range)): 
                                                $date_range_text = '';
                                                switch($selected_date_range) {
                                                    case 'current_year': $date_range_text = 'This Year'; break;
                                                    case 'last_month': $date_range_text = 'Last Month'; break;
                                                    case 'last_3months': $date_range_text = 'Last 3 Months'; break;
                                                    case 'last_6months': $date_range_text = 'Last 6 Months'; break;
                                                    case 'last_year': $date_range_text = 'Last Year'; break;
                                                }
                                            ?>
                                                <div class="active-filter">
                                                    <span>Date: <?php echo $date_range_text; ?></span>
                                                    <button type="button" onclick="removeFilter('date_range')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <button type="button" onclick="clearAllFilters()" class="active-filter bg-red-100 text-red-700 hover:bg-red-200">
                                                <i class="fas fa-trash-alt mr-1"></i> Clear All
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <!-- Results Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                            <?php if (!empty($filtered_exams)): ?>
                                <?php foreach ($filtered_exams as $index => $exam): ?>
                                    <a href="./view_result.php?exam_id=<?php echo $exam['exam_id']; ?>" class="result-card rounded-lg p-5 bg-white hover:bg-blue-50 <?php echo ($index === 0) ? 'featured' : ''; ?>">
                                        <div class="flex items-start">
                                            <div class="result-icon <?php echo ($index === 0) ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-600'; ?>">
                                                <i class="fas <?php echo ($index === 0) ? 'fa-award' : 'fa-file-alt'; ?>"></i>
                                            </div>
                                            <div class="ml-4 flex-1">
                                                <h3 class="font-semibold text-lg text-gray-800 mb-1"><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                                                <div class="flex flex-wrap gap-2 mb-3">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <?php echo htmlspecialchars($exam['exam_type']); ?>
                                                    </span>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <?php echo htmlspecialchars($exam['academic_year']); ?>
                                                    </span>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                        <?php echo $exam['subject_count']; ?> Subjects
                                                    </span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <div class="text-sm text-gray-500">
                                                        <i class="far fa-calendar-alt mr-1"></i> 
                                                        <?php echo date('M d, Y', strtotime($exam['created_at'])); ?>
                                                    </div>
                                                    <div class="text-blue-600 font-medium">
                                                        View <i class="fas fa-chevron-right ml-1"></i>
                                                    </div>
                                                </div>
                                                <?php if ($index === 0): ?>
                                                <div class="mt-3 pt-3 border-t border-gray-200">
                                                    <div class="flex justify-between">
                                                        <div class="text-sm font-medium text-gray-500">Average Score:</div>
                                                        <div class="text-sm font-semibold text-blue-600"><?php echo number_format($exam['avg_marks'], 2); ?></div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-span-full">
                                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-yellow-700">
                                                    No grade sheets found matching your filters. Please try different filter options.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <h2 class="empty-state-title">No Exam Results Available</h2>
                                <p class="empty-state-message">You don't have any exam results available yet. Please check back later or contact your teacher for more information.</p>
                                <a href="student_dashboard.php" class="empty-state-button">
                                    <i class="fas fa-arrow-left"></i> Return to Dashboard
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

        // Filter toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle filters visibility
            const toggleFilters = document.getElementById('toggle-filters');
            const filterContent = document.getElementById('filter-content');
            const toggleText = document.getElementById('toggle-text');
            
            if (toggleFilters && filterContent) {
                toggleFilters.addEventListener('click', function() {
                    if (filterContent.classList.contains('hidden')) {
                        filterContent.classList.remove('hidden');
                        toggleText.textContent = 'Hide Filters';
                        toggleFilters.querySelector('i').classList.remove('fa-chevron-up');
                        toggleFilters.querySelector('i').classList.add('fa-chevron-down');
                    } else {
                        filterContent.classList.add('hidden');
                        toggleText.textContent = 'Show Filters';
                        toggleFilters.querySelector('i').classList.remove('fa-chevron-down');
                        toggleFilters.querySelector('i').classList.add('fa-chevron-up');
                    }
                });
            }
            
            // Quick filters
            const quickFilters = document.querySelectorAll('.quick-filter');
            quickFilters.forEach(filter => {
                filter.addEventListener('click', function() {
                    const year = this.getAttribute('data-year');
                    const type = this.getAttribute('data-type');
                    const date = this.getAttribute('data-date');
                    
                    if (year) {
                        document.getElementById('academic_year').value = year;
                    }
                    
                    if (type) {
                        document.getElementById('exam_type').value = type;
                    }
                    
                    if (date) {
                        document.getElementById('date_range').value = date;
                    }
                    
                    // Auto-submit the form
                    document.getElementById('filter-form').submit();
                });
            });
            
            // Reset filters button
            document.getElementById('reset-filters').addEventListener('click', function() {
                window.location.href = 'grade_sheet.php';
            });
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Alt+F to focus on filter form
                if (e.altKey && e.key === 'f') {
                    e.preventDefault();
                    document.getElementById('exam_type').focus();
                }
                
                // Alt+R to reset filters
                if (e.altKey && e.key === 'r') {
                    e.preventDefault();
                    window.location.href = 'grade_sheet.php';
                }
                
                // Alt+S to submit form
                if (e.altKey && e.key === 's') {
                    e.preventDefault();
                    document.getElementById('filter-form').submit();
                }
            });
        });
        
        // Remove a specific filter
        function removeFilter(filterName) {
            // Create a new URL object
            const url = new URL(window.location.href);
            
            // Remove the specified parameter
            url.searchParams.delete(filterName);
            
            // Keep the search_submitted parameter
            if (!url.searchParams.has('search_submitted')) {
                url.searchParams.set('search_submitted', '1');
            }
            
            // Redirect to the new URL
            window.location.href = url.toString();
        }
        
        // Clear all filters
        function clearAllFilters() {
            window.location.href = 'grade_sheet.php';
        }
    </script>
</body>
</html>
