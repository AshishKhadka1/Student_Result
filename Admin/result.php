<?php
// At the beginning of your result.php file, add this code to check and create the missing columns if needed
// This ensures the columns exist before any queries try to access them

// Check if the phone and address columns exist in the Users table
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check for phone column
$phoneColumnExists = false;
$columnsResult = $conn->query("SHOW COLUMNS FROM Users LIKE 'phone'");
if ($columnsResult->num_rows > 0) {
    $phoneColumnExists = true;
}

// Check for address column
$addressColumnExists = false;
$columnsResult = $conn->query("SHOW COLUMNS FROM Users LIKE 'address'");
if ($columnsResult->num_rows > 0) {
    $addressColumnExists = true;
}

// Add missing columns if they don't exist
if (!$phoneColumnExists) {
    $alterTableSQL = "ALTER TABLE Users ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL";
    $conn->query($alterTableSQL);
}

if (!$addressColumnExists) {
    $alterTableSQL = "ALTER TABLE Users ADD COLUMN `address` VARCHAR(255) DEFAULT NULL";
    $conn->query($alterTableSQL);
}

$conn->close();

// Continue with the rest of your result.php code
// The existing code that references u.phone and u.address will now work correctly
?>

<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if Results table exists, if not create it
$tableExists = $conn->query("SHOW TABLES LIKE 'Results'");
if ($tableExists->num_rows == 0) {
    // Create Results table
    $createTableSQL = "CREATE TABLE `Results` (
        `result_id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `exam_id` int(11) NOT NULL,
        `total_marks` decimal(10,2) NOT NULL DEFAULT 0.00,
        `marks_obtained` decimal(10,2) NOT NULL DEFAULT 0.00,
        `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
        `grade` varchar(5) NOT NULL,
        `is_pass` tinyint(1) NOT NULL DEFAULT 0,
        `is_published` tinyint(1) NOT NULL DEFAULT 0,
        `remarks` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`result_id`),
        UNIQUE KEY `unique_student_exam` (`student_id`,`exam_id`),
        KEY `student_id` (`student_id`),
        KEY `exam_id` (`exam_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($createTableSQL);
    $_SESSION['success'] = "Results table created successfully.";
}

// Check if ResultDetails table exists, if not create it
$tableExists = $conn->query("SHOW TABLES LIKE 'ResultDetails'");
if ($tableExists->num_rows == 0) {
    // Create ResultDetails table
    $createTableSQL = "CREATE TABLE `ResultDetails` (
        `detail_id` int(11) NOT NULL AUTO_INCREMENT,
        `result_id` int(11) NOT NULL,
        `subject_id` int(11) NOT NULL,
        `marks_obtained` decimal(10,2) NOT NULL DEFAULT 0.00,
        `total_marks` decimal(10,2) NOT NULL DEFAULT 100.00,
        `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
        `grade` varchar(5) DEFAULT NULL,
        `is_pass` tinyint(1) NOT NULL DEFAULT 0,
        `remarks` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`detail_id`),
        UNIQUE KEY `unique_result_subject` (`result_id`,`subject_id`),
        KEY `result_id` (`result_id`),
        KEY `subject_id` (`subject_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($createTableSQL);
    $_SESSION['success'] = "ResultDetails table created successfully.";
}

// Check if required columns exist in the Results table
$requiredColumns = [
    'result_id' => 'INT(11) NOT NULL AUTO_INCREMENT',
    'student_id' => 'INT(11) NOT NULL',
    'exam_id' => 'INT(11) NOT NULL',
    'total_marks' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
    'marks_obtained' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
    'percentage' => 'DECIMAL(5,2) NOT NULL DEFAULT 0.00',
    'grade' => 'VARCHAR(5) NOT NULL',
    'is_pass' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'is_published' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'remarks' => 'TEXT DEFAULT NULL',
    'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
];

$columnsAdded = [];
$columnsResult = $conn->query("SHOW COLUMNS FROM Results");
$existingColumns = [];
while ($column = $columnsResult->fetch_assoc()) {
    $existingColumns[] = $column['Field'];
}

foreach ($requiredColumns as $column => $definition) {
    if (!in_array($column, $existingColumns)) {
        $conn->query("ALTER TABLE Results ADD COLUMN `$column` $definition");
        $columnsAdded[] = $column;
    }
}

if (!empty($columnsAdded)) {
    $_SESSION['success'] = "Database updated successfully. Added columns: " . implode(", ", $columnsAdded);
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Admin';

// Process actions (publish, unpublish, delete, update marks)
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'publish' && isset($_POST['result_id'])) {
        $result_id = intval($_POST['result_id']);
        $stmt = $conn->prepare("UPDATE Results SET is_published = 1 WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Result published successfully.";
        header("Location: result.php");
        exit();
    } 
    elseif ($_POST['action'] == 'unpublish' && isset($_POST['result_id'])) {
        $result_id = intval($_POST['result_id']);
        $stmt = $conn->prepare("UPDATE Results SET is_published = 0 WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Result unpublished successfully.";
        header("Location: result.php");
        exit();
    }
    elseif ($_POST['action'] == 'delete' && isset($_POST['result_id'])) {
        $result_id = intval($_POST['result_id']);
        
        // First delete related records from ResultDetails
        $stmt = $conn->prepare("DELETE FROM ResultDetails WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        
        // Then delete the result
        $stmt = $conn->prepare("DELETE FROM Results WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "Result deleted successfully.";
        header("Location: result.php");
        exit();
    }
    elseif ($_POST['action'] == 'update_marks' && isset($_POST['result_id'])) {
        $result_id = intval($_POST['result_id']);
        $marks_obtained = floatval($_POST['marks_obtained']);
        $total_marks = floatval($_POST['total_marks']);
        
        // Calculate percentage
        $percentage = ($marks_obtained / $total_marks) * 100;
        
        // Determine grade based on percentage
        $grade = '';
        $is_pass = 0;
        
        if ($percentage >= 90) {
            $grade = 'A+';
            $is_pass = 1;
        } elseif ($percentage >= 80) {
            $grade = 'A';
            $is_pass = 1;
        } elseif ($percentage >= 70) {
            $grade = 'B+';
            $is_pass = 1;
        } elseif ($percentage >= 60) {
            $grade = 'B';
            $is_pass = 1;
        } elseif ($percentage >= 50) {
            $grade = 'C+';
            $is_pass = 1;
        } elseif ($percentage >= 40) {
            $grade = 'C';
            $is_pass = 1;
        } elseif ($percentage >= 33) {
            $grade = 'D';
            $is_pass = 1;
        } else {
            $grade = 'F';
            $is_pass = 0;
        }
        
        // Update the result
        $stmt = $conn->prepare("UPDATE Results SET marks_obtained = ?, total_marks = ?, percentage = ?, grade = ?, is_pass = ? WHERE result_id = ?");
        $stmt->bind_param("dddsii", $marks_obtained, $total_marks, $percentage, $grade, $is_pass, $result_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "Marks updated successfully.";
        header("Location: result.php");
        exit();
    }
}

// Get all classes for dropdown
$classes = [];
try {
    $sql = "SELECT class_id, class_name, section, academic_year 
            FROM Classes 
            ORDER BY academic_year DESC, class_name, section";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading classes: " . $e->getMessage();
}

// Get all exams for dropdown
$exams = [];
try {
    $sql = "SELECT exam_id, exam_name, exam_type, academic_year 
            FROM Exams 
            ORDER BY academic_year DESC, exam_date DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading exams: " . $e->getMessage();
}

// Initialize filters
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : null;
$selected_exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : null;
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Get students with their results
$students = [];
try {
    // First, get all students based on filters
    $query = "SELECT DISTINCT s.student_id, s.roll_number, u.full_name, c.class_id, c.class_name, c.section, c.academic_year
              FROM Students s
              JOIN Users u ON s.user_id = u.user_id
              JOIN Classes c ON s.class_id = c.class_id
              LEFT JOIN Results r ON s.student_id = r.student_id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($selected_class_id) {
        $query .= " AND c.class_id = ?";
        $params[] = $selected_class_id;
        $types .= "i";
    }
    
    if (!empty($search_term)) {
        $query .= " AND (u.full_name LIKE ? OR s.roll_number LIKE ?)";
        $search_param = "%$search_term%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }
    
    $query .= " ORDER BY c.class_name, c.section, u.full_name";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $students[$row['student_id']] = [
            'student_id' => $row['student_id'],
            'roll_number' => $row['roll_number'],
            'full_name' => $row['full_name'],
            'class_id' => $row['class_id'],
            'class_name' => $row['class_name'],
            'section' => $row['section'],
            'academic_year' => $row['academic_year'],
            'exams' => []
        ];
    }
    
    $stmt->close();
    
    // Now get exam results for each student
    if (!empty($students)) {
        $student_ids = array_keys($students);
        
        $query = "SELECT r.result_id, r.student_id, r.exam_id, r.total_marks, r.marks_obtained, 
                        r.percentage, r.grade, r.is_pass, r.is_published, r.created_at,
                        e.exam_name, e.exam_type, e.exam_date, e.academic_year
                  FROM Results r
                  JOIN Exams e ON r.exam_id = e.exam_id
                  WHERE r.student_id IN (" . implode(',', array_fill(0, count($student_ids), '?')) . ")";
        
        if ($selected_exam_id) {
            $query .= " AND r.exam_id = ?";
            $student_ids[] = $selected_exam_id;
        }
        
        $query .= " ORDER BY e.exam_date DESC";
        
        $stmt = $conn->prepare($query);
        
        $types = str_repeat('i', count($student_ids));
        $stmt->bind_param($types, ...$student_ids);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $students[$row['student_id']]['exams'][$row['exam_id']] = [
                'result_id' => $row['result_id'],
                'exam_id' => $row['exam_id'],
                'exam_name' => $row['exam_name'],
                'exam_type' => $row['exam_type'],
                'exam_date' => $row['exam_date'],
                'academic_year' => $row['academic_year'],
                'total_marks' => $row['total_marks'],
                'marks_obtained' => $row['marks_obtained'],
                'percentage' => $row['percentage'],
                'grade' => $row['grade'],
                'is_pass' => $row['is_pass'],
                'is_published' => $row['is_published'],
                'created_at' => $row['created_at']
            ];
        }
        
        $stmt->close();
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading students and results: " . $e->getMessage();
}

// Get statistics for dashboard
$stats = [
    'total_students' => 0,
    'published_results' => 0,
    'average_percentage' => 0,
    'pass_percentage' => 0
];

try {
    // Total students
    $sql = "SELECT COUNT(DISTINCT student_id) as count FROM Results";
    $result = $conn->query($sql);
    if ($result) {
        $stats['total_students'] = $result->fetch_assoc()['count'];
    }
    
    // Published results
    $sql = "SELECT COUNT(*) as count FROM Results WHERE is_published = 1";
    $result = $conn->query($sql);
    if ($result) {
        $stats['published_results'] = $result->fetch_assoc()['count'];
    }
    
    // Average percentage
    $sql = "SELECT AVG(percentage) as avg_percentage FROM Results";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['average_percentage'] = $row['avg_percentage'] ?? 0;
    }
    
    // Pass percentage
    $sql = "SELECT 
                (SELECT COUNT(*) FROM Results WHERE is_pass = 1) / 
                (SELECT COUNT(*) FROM Results) * 100 as pass_percentage";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['pass_percentage'] = $row['pass_percentage'] ?? 0;
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading statistics: " . $e->getMessage();
}

// Check if Results table is empty and insert sample data if needed
$countResults = $conn->query("SELECT COUNT(*) as count FROM Results")->fetch_assoc()['count'];
if ($countResults == 0) {
    try {
        // Insert sample data
        $sampleDataSQL = "
        INSERT INTO Results (student_id, exam_id, total_marks, marks_obtained, percentage, grade, is_pass, is_published, remarks)
        SELECT 
            s.student_id, 
            e.exam_id, 
            500.00 AS total_marks, 
            FLOOR(RAND() * 300) + 200 AS marks_obtained, 
            (FLOOR(RAND() * 300) + 200) / 5 AS percentage,
            CASE 
                WHEN (FLOOR(RAND() * 300) + 200) / 5 >= 90 THEN 'A+'
                WHEN (FLOOR(RAND() * 300) + 200) / 5 >= 80 THEN 'A'
                WHEN (FLOOR(RAND() * 300) + 200) / 5 >= 70 THEN 'B+'
                WHEN (FLOOR(RAND() * 300) + 200) / 5 >= 60 THEN 'B'
                WHEN (FLOOR(RAND() * 300) + 200) / 5 >= 50 THEN 'C+'
                WHEN (FLOOR(RAND() * 300) + 200) / 5 >= 40 THEN 'C'
                WHEN (FLOOR(RAND() * 300) + 200) / 5 >= 33 THEN 'D'
                ELSE 'F'
            END AS grade,
            CASE 
                WHEN (FLOOR(RAND() * 300) + 200) / 5 >= 33 THEN 1
                ELSE 0
            END AS is_pass,
            FLOOR(RAND() * 2) AS is_published,
            'Sample result data' AS remarks
        FROM 
            Students s
        CROSS JOIN 
            Exams e
        LIMIT 50
        ";
        
        $conn->query($sampleDataSQL);
        $_SESSION['success'] = "Sample data inserted successfully.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error inserting sample data: " . $e->getMessage();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Results | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Hover effects */
        .hover-scale {
            transition: all 0.3s ease;
        }
        
        .hover-scale:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Dark mode */
        .dark-mode {
            background-color: #1a202c;
            color: #e2e8f0;
        }
        
        .dark-mode .bg-white {
            background-color: #2d3748 !important;
            color: #e2e8f0;
        }
        
        .dark-mode .bg-gray-50 {
            background-color: #4a5568 !important;
            color: #e2e8f0;
        }
        
        .dark-mode .text-gray-900 {
            color: #e2e8f0 !important;
        }
        
        .dark-mode .text-gray-500 {
            color: #a0aec0 !important;
        }
        
        .dark-mode .border-gray-200 {
            border-color: #4a5568 !important;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        /* Dark mode for modal */
        .dark-mode .modal-content {
            background-color: #2d3748;
            color: #e2e8f0;
            border-color: #4a5568;
        }
        
        .dark-mode .close {
            color: #e2e8f0;
        }
        
        .dark-mode .close:hover,
        .dark-mode .close:focus {
            color: #fff;
        }
    </style>
</head>
<body class="bg-gray-100" id="body">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include 'topBar.php'; ?>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Notification Messages -->
                        <?php if(isset($_SESSION['success'])): ?>
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">
                                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                    </p>
                                </div>
                                <div class="ml-auto pl-3">
                                    <div class="-mx-1.5 -my-1.5">
                                        <button class="inline-flex rounded-md p-1.5 text-green-500 hover:bg-green-100 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                                            <span class="sr-only">Dismiss</span>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">
                                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                    </p>
                                </div>
                                <div class="ml-auto pl-3">
                                    <div class="-mx-1.5 -my-1.5">
                                        <button class="inline-flex rounded-md p-1.5 text-red-500 hover:bg-red-100 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                                            <span class="sr-only">Dismiss</span>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['info'])): ?>
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">
                                        <?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
                                    </p>
                                </div>
                                <div class="ml-auto pl-3">
                                    <div class="-mx-1.5 -my-1.5">
                                        <button class="inline-flex rounded-md p-1.5 text-blue-500 hover:bg-blue-100 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                                            <span class="sr-only">Dismiss</span>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Dashboard Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <div class="bg-white rounded-lg shadow-md p-6 hover-scale">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                        <i class="fas fa-user-graduate text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500">Total Students</p>
                                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_students']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white rounded-lg shadow-md p-6 hover-scale">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                                        <i class="fas fa-check-circle text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500">Published Results</p>
                                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['published_results']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white rounded-lg shadow-md p-6 hover-scale">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                                        <i class="fas fa-percentage text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500">Average Score</p>
                                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['average_percentage'], 2); ?>%</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white rounded-lg shadow-md p-6 hover-scale">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                                        <i class="fas fa-award text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500">Pass Rate</p>
                                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['pass_percentage'], 2); ?>%</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Section -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Filter Results</h2>
                            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                    <select id="class_id" name="class_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Classes</option>
                                        <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" <?php echo ($selected_class_id == $class['class_id']) ? 'selected' : ''; ?>>
                                            <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-1">Exam</label>
                                    <select id="exam_id" name="exam_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Exams</option>
                                        <?php foreach ($exams as $exam): ?>
                                        <option value="<?php echo $exam['exam_id']; ?>" <?php echo ($selected_exam_id == $exam['exam_id']) ? 'selected' : ''; ?>>
                                            <?php echo $exam['exam_name'] . ' (' . $exam['exam_type'] . ' - ' . $exam['academic_year'] . ')'; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                    <div class="flex">
                                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" class="w-full rounded-l-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="Student name or roll number">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-r-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Students and Results Table -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6 hover-scale">
                            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">Student Results</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="9" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No students found</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($students as $student): ?>
                                                <?php if (empty($student['exams'])): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['roll_number']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['academic_year']); ?></div>
                                                    </td>
                                                    <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No exam results found</td>
                                                </tr>
                                                <?php else: ?>
                                                    <?php foreach ($student['exams'] as $exam): ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['roll_number']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?></div>
                                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['academic_year']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($exam['exam_type']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($exam['marks_obtained'] . '/' . $exam['total_marks']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars(number_format($exam['percentage'], 2)); ?>%</div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php
                                                            $grade_class = '';
                                                            switch ($exam['grade']) {
                                                                case 'A+': $grade_class = 'bg-green-100 text-green-800'; break;
                                                                case 'A': $grade_class = 'bg-green-100 text-green-800'; break;
                                                                case 'B+': $grade_class = 'bg-blue-100 text-blue-800'; break;
                                                                case 'B': $grade_class = 'bg-blue-100 text-blue-800'; break;
                                                                case 'C+': $grade_class = 'bg-yellow-100 text-yellow-800'; break;
                                                                case 'C': $grade_class = 'bg-yellow-100 text-yellow-800'; break;
                                                                case 'D': $grade_class = 'bg-orange-100 text-orange-800'; break;
                                                                case 'F': $grade_class = 'bg-red-100 text-red-800'; break;
                                                                default: $grade_class = 'bg-gray-100 text-gray-800';
                                                            }
                                                            ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $grade_class; ?>">
                                                                <?php echo htmlspecialchars($exam['grade']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php if ($exam['is_published']): ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                Published
                                                            </span>
                                                            <?php else: ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                                Unpublished
                                                            </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                            <div class="flex space-x-2">
                                                                <a href="view_student_result.php?result_id=<?php echo $exam['result_id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="View Result">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                
                                                                
                                                                <?php if ($exam['is_published']): ?>
                                                                <form method="POST" class="inline">
                                                                    <input type="hidden" name="result_id" value="<?php echo $exam['result_id']; ?>">
                                                                    <input type="hidden" name="action" value="unpublish">
                                                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900" title="Unpublish">
                                                                        <i class="fas fa-eye-slash"></i>
                                                                    </button>
                                                                </form>
                                                                <?php else: ?>
                                                                <form method="POST" class="inline">
                                                                    <input type="hidden" name="result_id" value="<?php echo $exam['result_id']; ?>">
                                                                    <input type="hidden" name="action" value="publish">
                                                                    <button type="submit" class="text-green-600 hover:text-green-900" title="Publish">
                                                                        <i class="fas fa-check-circle"></i>
                                                                    </button>
                                                                </form>
                                                                <?php endif; ?>
                                                                
                                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this result? This action cannot be undone.');">
                                                                    <input type="hidden" name="result_id" value="<?php echo $exam['result_id']; ?>">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
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

    <!-- Edit Marks Modal -->
    <div id="editMarksModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditMarksModal()">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Update Marks</h2>
            <form id="editMarksForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_marks">
                <input type="hidden" id="edit_result_id" name="result_id" value="">
                
                <div>
                    <label for="marks_obtained" class="block text-sm font-medium text-gray-700 mb-1">Marks Obtained</label>
                    <input type="number" id="marks_obtained" name="marks_obtained" step="0.01" min="0" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                </div>
                
                <div>
                    <label for="total_marks" class="block text-sm font-medium text-gray-700 mb-1">Total Marks</label>
                    <input type="number" id="total_marks" name="total_marks" step="0.01" min="0.01" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="closeEditMarksModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openEditMarksModal(resultId, marksObtained, totalMarks) {
            document.getElementById('edit_result_id').value = resultId;
            document.getElementById('marks_obtained').value = marksObtained;
            document.getElementById('total_marks').value = totalMarks;
            document.getElementById('editMarksModal').style.display = 'block';
        }
        
        function closeEditMarksModal() {
            document.getElementById('editMarksModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('editMarksModal');
            if (event.target == modal) {
                closeEditMarksModal();
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Dark mode toggle
            const darkModeToggle = document.getElementById('dark-mode-toggle');
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function() {
                    document.getElementById('body').classList.toggle('dark-mode');
                });
            }
            
            // Auto-submit form when filters change
            const filterForm = document.querySelector('form');
            const filterSelects = document.querySelectorAll('select[name="class_id"], select[name="exam_id"]');
            
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        });
    </script>
</body>
</html>
