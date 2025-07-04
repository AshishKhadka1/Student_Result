<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure exam_type field supports the terminal types
try {
    // Check if the exam_type column is properly set up
    $checkTypeQuery = "SHOW COLUMNS FROM exams LIKE 'exam_type'";
    $typeResult = $conn->query($checkTypeQuery);
    if ($typeResult && $typeResult->num_rows > 0) {
        $typeInfo = $typeResult->fetch_assoc();
        // If it's an enum type, check if it includes our terminal types
        if (strpos($typeInfo['Type'], 'enum') === 0) {
            // If it doesn't contain 'First Terminal', we need to modify it
            if (strpos($typeInfo['Type'], 'First Terminal') === false) {
                $alterQuery = "ALTER TABLE exams MODIFY COLUMN exam_type ENUM('midterm','final','quiz','assignment','project','other','First Terminal','Second Terminal','Third Terminal','Final Terminal') NOT NULL";
                $conn->query($alterQuery);
            }
        }
    }
} catch (Exception $e) {
    // Silently handle any errors
    error_log("Error updating exam_type column: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Admin';

// Get all classes for dropdown
$classes = [];
try {
    $sql = "SELECT class_id, class_name, section, academic_year 
            FROM classes 
            ORDER BY academic_year DESC, class_name, section";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading classes: " . $e->getMessage();
}

// Function to log actions
function logAction($conn, $user_id, $action, $details)
{
    try {
        // Check if activity_logs table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($tableCheck->num_rows == 0) {
            // Table doesn't exist, create it
            $conn->query("CREATE TABLE IF NOT EXISTS `activity_logs` (
                `log_id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `action` varchar(50) NOT NULL,
                `details` text DEFAULT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`log_id`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        }

        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt->bind_param("isss", $user_id, $action, $details, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Silently fail - don't let logging errors affect the main functionality
        error_log("Error logging action: " . $e->getMessage());
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new exam
    if (isset($_POST['action']) && $_POST['action'] == 'add_exam') {
        try {
            $exam_name = $_POST['exam_name'];
            $exam_type = $_POST['exam_type'];
            $class_id = $_POST['class_id'];
            $academic_year = $_POST['academic_year'];
            $exam_date = $_POST['exam_date'];
            $description = $_POST['description'] ?? '';
            $status = $_POST['status'];

            // Check if the exam_type is valid for the database enum
            $valid_types = ['First Terminal', 'Second Terminal', 'Third Terminal', 'Final Terminal'];
            // Don't convert to lowercase anymore since the terminal types use uppercase first letters
            $exam_type_db = in_array($exam_type, $valid_types) ? $exam_type : 'other';

            // Set default values for required fields in the database
            $total_marks = 100;
            $passing_marks = 40;

            $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_type, class_id, academic_year, exam_date, description, status, total_marks, passing_marks, results_published, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
            $stmt->bind_param("ssissssii", $exam_name, $exam_type_db, $class_id, $academic_year, $exam_date, $description, $status, $total_marks, $passing_marks);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Exam added successfully!";
                logAction($conn, $user_id, "ADD_EXAM", "Added exam: $exam_name");
            } else {
                $_SESSION['error'] = "Failed to add exam: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error adding exam: " . $e->getMessage();
        }
    }

    // Update exam
    elseif (isset($_POST['action']) && $_POST['action'] == 'update_exam') {
        try {
            $exam_id = $_POST['exam_id'];
            $exam_name = $_POST['exam_name'];
            $exam_type = $_POST['exam_type'];
            $class_id = $_POST['class_id'];
            $academic_year = $_POST['academic_year'];
            $exam_date = $_POST['exam_date'];
            $description = $_POST['description'] ?? '';
            $status = $_POST['status'];

            // Check if the exam_type is valid for the database enum
            $valid_types = ['First Terminal', 'Second Terminal', 'Third Terminal', 'Final Terminal'];
            // Don't convert to lowercase anymore since the terminal types use uppercase first letters
            $exam_type_db = in_array($exam_type, $valid_types) ? $exam_type : 'other';

            $stmt = $conn->prepare("UPDATE exams SET exam_name = ?, exam_type = ?, class_id = ?, academic_year = ?, exam_date = ?, description = ?, status = ?, updated_at = NOW() WHERE exam_id = ?");
            $stmt->bind_param("ssissssi", $exam_name, $exam_type_db, $class_id, $academic_year, $exam_date, $description, $status, $exam_id);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Exam updated successfully!";
                logAction($conn, $user_id, "UPDATE_EXAM", "Updated exam ID: $exam_id");
            } else {
                $_SESSION['error'] = "Failed to update exam: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error updating exam: " . $e->getMessage();
        }
    }

    // Delete exam
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete_exam') {
        try {
            $exam_id = $_POST['exam_id'];

            // Check if there are results associated with this exam
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM results WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row['count'] > 0) {
                $_SESSION['error'] = "Cannot delete exam because there are results associated with it.";
            } else {
                $stmt = $conn->prepare("DELETE FROM exams WHERE exam_id = ?");
                $stmt->bind_param("i", $exam_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Exam deleted successfully!";
                    logAction($conn, $user_id, "DELETE_EXAM", "Deleted exam ID: $exam_id");
                } else {
                    $_SESSION['error'] = "Failed to delete exam: " . $stmt->error;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error deleting exam: " . $e->getMessage();
        }
    }

    // Publish results
    elseif (isset($_POST['action']) && $_POST['action'] == 'publish_results') {
        try {
            $exam_id = $_POST['exam_id'];

            // Check if results_published column exists
            $columnCheck = $conn->query("SHOW COLUMNS FROM `exams` LIKE 'results_published'");
            if ($columnCheck->num_rows == 0) {
                // Column doesn't exist, add it
                $conn->query("ALTER TABLE `exams` ADD COLUMN `results_published` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`");
            }

            $stmt = $conn->prepare("UPDATE exams SET results_published = 1, updated_at = NOW() WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Results published successfully!";
                logAction($conn, $user_id, "PUBLISH_RESULTS", "Published results for exam ID: $exam_id");
            } else {
                $_SESSION['error'] = "Failed to publish results: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error publishing results: " . $e->getMessage();
        }
    }

    // Unpublish results
    elseif (isset($_POST['action']) && $_POST['action'] == 'unpublish_results') {
        try {
            $exam_id = $_POST['exam_id'];

            // Check if results_published column exists
            $columnCheck = $conn->query("SHOW COLUMNS FROM `exams` LIKE 'results_published'");
            if ($columnCheck->num_rows == 0) {
                // Column doesn't exist, add it
                $conn->query("ALTER TABLE `exams` ADD COLUMN `results_published` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`");
            }

            $stmt = $conn->prepare("UPDATE exams SET results_published = 0, updated_at = NOW() WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Results unpublished successfully!";
                logAction($conn, $user_id, "UNPUBLISH_RESULTS", "Unpublished results for exam ID: $exam_id");
            } else {
                $_SESSION['error'] = "Failed to unpublish results: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error unpublishing results: " . $e->getMessage();
        }
    }

    // Redirect to prevent form resubmission
    header("Location: exams.php");
    exit();
}

// Get all exams with class information
$exams = [];
$filter_class = isset($_GET['filter_class']) ? $_GET['filter_class'] : '';
$filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

try {
    // Check if results_published column exists
    $columnCheck = $conn->query("SHOW COLUMNS FROM `exams` LIKE 'results_published'");
    if ($columnCheck->num_rows == 0) {
        // Column doesn't exist, add it
        $conn->query("ALTER TABLE `exams` ADD COLUMN `results_published` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`");
    }

    $query = "SELECT e.*, c.class_name, c.section 
              FROM exams e 
              LEFT JOIN classes c ON e.class_id = c.class_id 
              WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($filter_class)) {
        $query .= " AND e.class_id = ?";
        $params[] = $filter_class;
        $types .= "i";
    }

    if (!empty($filter_year)) {
        $query .= " AND e.academic_year = ?";
        $params[] = $filter_year;
        $types .= "s";
    }

    if (!empty($filter_type)) {
        $query .= " AND e.exam_type = ?";
        $params[] = $filter_type;
        $types .= "s";
    }

    if (!empty($filter_status)) {
        $query .= " AND e.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }

    $query .= " ORDER BY e.exam_date DESC";

    $stmt = $conn->prepare($query);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Set default value for results_published if it doesn't exist
        if (!isset($row['results_published'])) {
            $row['results_published'] = 0;
        }
        $exams[] = $row;
    }

    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading exams: " . $e->getMessage();
}

// Get unique academic years for filter
$academic_years = [];
try {
    $sql = "SELECT DISTINCT academic_year FROM exams ORDER BY academic_year DESC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $academic_years[] = $row['academic_year'];
    }
} catch (Exception $e) {
    // Silently fail
}

// Get unique exam types for filter
$exam_types = [];
try {
    $sql = "SELECT DISTINCT exam_type FROM exams ORDER BY exam_type";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $exam_types[] = $row['exam_type'];
    }
} catch (Exception $e) {
    // Silently fail
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams | Result Management System</title>
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
    </style>
</head>

<body class="bg-gray-100" id="body">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php
        // Include the file that processes form data
        include 'sidebar.php';
        ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php
        // Include the file that processes form data
        include 'topBar.php';

        include 'mobile_sidebar.php'
        ?>

           

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Notification Messages -->
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700">
                                            <?php echo $_SESSION['success'];
                                            unset($_SESSION['success']); ?>
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

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700">
                                            <?php echo $_SESSION['error'];
                                            unset($_SESSION['error']); ?>
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

                        <!-- Add Exam Button -->
                        <div class="mb-6 flex justify-end">
                            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" onclick="openAddExamModal()">
                                <i class="fas fa-plus mr-2"></i> Add New Exam
                            </button>
                        </div>

                        <!-- Filter Section -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Filter Exams</h2>
                            <form action="exams.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label for="filter_class" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                    <select id="filter_class" name="filter_class" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Classes</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['class_id']; ?>" <?php echo ($filter_class == $class['class_id']) ? 'selected' : ''; ?>>
                                                <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="filter_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                                    <select id="filter_year" name="filter_year" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Years</option>
                                        <?php foreach ($academic_years as $year): ?>
                                            <option value="<?php echo $year; ?>" <?php echo ($filter_year == $year) ? 'selected' : ''; ?>>
                                                <?php echo $year; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="filter_type" class="block text-sm font-medium text-gray-700 mb-1">Exam Type</label>
                                    <select id="filter_type" name="filter_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Types</option>
                                        <option value="First Terminal" <?php echo ($filter_type == 'First Terminal') ? 'selected' : ''; ?>>First Terminal</option>
                                        <option value="Second Terminal" <?php echo ($filter_type == 'Second Terminal') ? 'selected' : ''; ?>>Second Terminal</option>
                                        <option value="Third Terminal" <?php echo ($filter_type == 'Third Terminal') ? 'selected' : ''; ?>>Third Terminal</option>
                                        <option value="Final Terminal" <?php echo ($filter_type == 'Final Terminal') ? 'selected' : ''; ?>>Final Terminal</option>
                                    </select>
                                </div>


                                <div>
                                    <label for="filter_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select id="filter_status" name="filter_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="completed" <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="upcoming" <?php echo ($filter_status == 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                    </select>
                                </div>

                                <div class="md:col-span-4 flex items-center">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-filter mr-2"></i> Apply Filters
                                    </button>
                                    <a href="exams.php" class="ml-2 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-redo mr-2"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Exams Table -->
                        <div class="bg-white shadow rounded-lg overflow-hidden hover-scale">
                            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                                <h2 class="text-lg font-medium text-gray-900">All Exams</h2>
                                <p class="mt-1 text-sm text-gray-500">
                                    Manage all exams, their schedules, and result publication status
                                </p>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam Date</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Results</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($exams)): ?>
                                                <tr>
                                                    <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">No exams found.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($exams as $exam): ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $exam['exam_name']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $exam['exam_type']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            <?php echo $exam['class_name'] . ' ' . $exam['section']; ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $exam['academic_year']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            <?php echo date('M d, Y', strtotime($exam['exam_date'])); ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php if ($exam['status'] == 'active'): ?>
                                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                    Active
                                                                </span>
                                                            <?php elseif ($exam['status'] == 'completed'): ?>
                                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                                    Completed
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                                    Upcoming
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php if ($exam['results_published']): ?>
                                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                    Published
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                                    Not Published
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                            <div class="flex space-x-2">
                                                                <button onclick="openEditExamModal(<?php echo htmlspecialchars(json_encode($exam)); ?>)" class="text-blue-600 hover:text-blue-900">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>

                                                                <?php if ($exam['results_published']): ?>
                                                                    <form action="exams.php" method="POST" class="inline">
                                                                        <input type="hidden" name="action" value="unpublish_results">
                                                                        <input type="hidden" name="exam_id" value="<?php echo $exam['exam_id']; ?>">
                                                                        <button type="submit" class="text-yellow-600 hover:text-yellow-900" onclick="return confirm('Are you sure you want to unpublish the results?')">
                                                                            <i class="fas fa-eye-slash"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <form action="exams.php" method="POST" class="inline">
                                                                        <input type="hidden" name="action" value="publish_results">
                                                                        <input type="hidden" name="exam_id" value="<?php echo $exam['exam_id']; ?>">
                                                                        <button type="submit" class="text-green-600 hover:text-green-900" onclick="return confirm('Are you sure you want to publish the results?')">
                                                                            <i class="fas fa-eye"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>

                                                                <form action="exams.php" method="POST" class="inline">
                                                                    <input type="hidden" name="action" value="delete_exam">
                                                                    <input type="hidden" name="exam_id" value="<?php echo $exam['exam_id']; ?>">
                                                                    <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this exam?')">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>

                                                                <a href="result.php?exam_id=<?php echo $exam['exam_id']; ?>" class="text-purple-600 hover:text-purple-900">
                                                                    <i class="fas fa-clipboard-list"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Exam Modal -->
    <div id="addExamModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add New Exam</h3>
                <button onclick="closeAddExamModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="exams.php" method="POST">
                <input type="hidden" name="action" value="add_exam">

                <div class="space-y-4">
                    <div>
                        <label for="exam_name" class="block text-sm font-medium text-gray-700 mb-1">Exam Name</label>
                        <input type="text" id="exam_name" name="exam_name" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="exam_type" class="block text-sm font-medium text-gray-700 mb-1">Exam Type</label>
                        <select id="exam_type" name="exam_type" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="">Select Type</option>
                            <option value="First Terminal">First Terminal</option>
                            <option value="Second Terminal">Second Terminal</option>
                            <option value="Third Terminal">Third Terminal</option>
                            <option value="Final Terminal">Final Terminal</option>
                        </select>
                    </div>

                    <div>
                        <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select id="class_id" name="class_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                        <input type="text" id="academic_year" name="academic_year" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="e.g. 2023-2024">
                    </div>

                    <div>
                        <label for="exam_date" class="block text-sm font-medium text-gray-700 mb-1">Exam Date</label>
                        <input type="date" id="exam_date" name="exam_date" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                        <textarea id="description" name="description" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="upcoming">Upcoming</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeAddExamModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Add Exam
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Exam Modal -->
    <div id="editExamModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Exam</h3>
                <button onclick="closeEditExamModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="exams.php" method="POST">
                <input type="hidden" name="action" value="update_exam">
                <input type="hidden" id="edit_exam_id" name="exam_id">

                <div class="space-y-4">
                    <div>
                        <label for="edit_exam_name" class="block text-sm font-medium text-gray-700 mb-1">Exam Name</label>
                        <input type="text" id="edit_exam_name" name="exam_name" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="edit_exam_type" class="block text-sm font-medium text-gray-700 mb-1">Exam Type</label>
                        <select id="edit_exam_type" name="exam_type" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="">Select Type</option>
                            <option value="First Terminal">First Terminal</option>
                            <option value="Second Terminal">Second Terminal</option>
                            <option value="Third Terminal">Third Terminal</option>
                            <option value="Final Terminal">Final Terminal</option>
                        </select>
                    </div>

                    <div>
                        <label for="edit_class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select id="edit_class_id" name="class_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="edit_academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                        <input type="text" id="edit_academic_year" name="academic_year" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="e.g. 2023-2024">
                    </div>

                    <div>
                        <label for="edit_exam_date" class="block text-sm font-medium text-gray-700 mb-1">Exam Date</label>
                        <input type="date" id="edit_exam_date" name="exam_date" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                        <textarea id="edit_description" name="description" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                    </div>

                    <div>
                        <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="edit_status" name="status" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="upcoming">Upcoming</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeEditExamModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Update Exam
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const closeSidebar = document.getElementById('close-sidebar');
            const sidebarBackdrop = document.getElementById('sidebar-backdrop');
            const mobileSidebar = document.getElementById('mobile-sidebar');

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    mobileSidebar.classList.remove('-translate-x-full');
                });
            }

            if (closeSidebar) {
                closeSidebar.addEventListener('click', function() {
                    mobileSidebar.classList.add('-translate-x-full');
                });
            }

            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', function() {
                    mobileSidebar.classList.add('-translate-x-full');
                });
            }

            // User menu toggle
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');

            if (userMenuButton && userMenu) {
                userMenuButton.addEventListener('click', function() {
                    userMenu.classList.toggle('hidden');
                });

                // Close when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                        userMenu.classList.add('hidden');
                    }
                });
            }


        });

        // Add Exam Modal
        function openAddExamModal() {
            document.getElementById('addExamModal').classList.remove('hidden');
        }

        function closeAddExamModal() {
            document.getElementById('addExamModal').classList.add('hidden');
        }

        // Edit Exam Modal
        function openEditExamModal(exam) {
            document.getElementById('edit_exam_id').value = exam.exam_id;
            document.getElementById('edit_exam_name').value = exam.exam_name;
            document.getElementById('edit_exam_type').value = exam.exam_type;
            document.getElementById('edit_class_id').value = exam.class_id;
            document.getElementById('edit_academic_year').value = exam.academic_year;
            document.getElementById('edit_exam_date').value = exam.exam_date;
            document.getElementById('edit_description').value = exam.description || '';
            document.getElementById('edit_status').value = exam.status;

            document.getElementById('editExamModal').classList.remove('hidden');
        }

        function closeEditExamModal() {
            document.getElementById('editExamModal').classList.add('hidden');
        }
    </script>

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

</body>

</html>
