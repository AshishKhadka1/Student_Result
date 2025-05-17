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



// Handle individual student deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $student_id = $_GET['delete'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete student's results first
        $stmt = $conn->prepare("DELETE FROM results WHERE student_id = ?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete student's performance records
        $stmt = $conn->prepare("DELETE FROM student_performance WHERE student_id = ?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $stmt->close();
        
        // Get user_id associated with student
        $stmt = $conn->prepare("SELECT user_id FROM students WHERE student_id = ?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) {
            $user_id = $row['user_id'];
            $stmt->close();
            
            // Delete student record
            $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete user account
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Student deleted successfully.";
        } else {
            // Student not found
            $conn->rollback();
            $error_message = "Error: Student not found.";
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error deleting student: " . $e->getMessage();
    }
} else if (isset($_GET['added']) && $_GET['added'] == 'true') {
    // Show success message when student is added successfully
    $success_message = "Student added successfully.";
}

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$class_filter = isset($_GET['class']) ? $_GET['class'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$batch_filter = isset($_GET['batch']) ? $_GET['batch'] : '';

// Base query
$query = "SELECT s.*, u.full_name, u.email, u.status, c.class_name, c.section 
          FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          LEFT JOIN classes c ON s.class_id = c.class_id 
          WHERE 1=1";

// Add filters
if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (u.full_name LIKE ? OR s.student_id LIKE ? OR s.roll_number LIKE ? OR u.email LIKE ?)";
}

if (!empty($class_filter)) {
    $query .= " AND s.class_id = ?";
}

if (!empty($status_filter)) {
    $query .= " AND u.status = ?";
}

if (!empty($batch_filter)) {
    $query .= " AND s.batch_year = ?";
}

// Add order by
$query .= " ORDER BY s.created_at DESC";

// Prepare statement
$stmt = $conn->prepare($query);

// Bind parameters
if (!empty($search) && !empty($class_filter) && !empty($status_filter) && !empty($batch_filter)) {
    $stmt->bind_param("ssssis", $search, $search, $search, $search, $class_filter, $status_filter, $batch_filter);
} elseif (!empty($search) && !empty($class_filter) && !empty($status_filter)) {
    $stmt->bind_param("ssssis", $search, $search, $search, $search, $class_filter, $status_filter);
} elseif (!empty($search) && !empty($class_filter)) {
    $stmt->bind_param("ssssi", $search, $search, $search, $search, $class_filter);
} elseif (!empty($search) && !empty($status_filter)) {
    $stmt->bind_param("sssss", $search, $search, $search, $search, $status_filter);
} elseif (!empty($search) && !empty($batch_filter)) {
    $stmt->bind_param("sssss", $search, $search, $search, $search, $batch_filter);
} elseif (!empty($class_filter) && !empty($status_filter)) {
    $stmt->bind_param("is", $class_filter, $status_filter);
} elseif (!empty($class_filter) && !empty($batch_filter)) {
    $stmt->bind_param("is", $class_filter, $batch_filter);
} elseif (!empty($status_filter) && !empty($batch_filter)) {
    $stmt->bind_param("ss", $status_filter, $batch_filter);
} elseif (!empty($search)) {
    $stmt->bind_param("ssss", $search, $search, $search, $search);
} elseif (!empty($class_filter)) {
    $stmt->bind_param("i", $class_filter);
} elseif (!empty($status_filter)) {
    $stmt->bind_param("s", $status_filter);
} elseif (!empty($batch_filter)) {
    $stmt->bind_param("s", $batch_filter);
}

// Execute query
$stmt->execute();
$result = $stmt->get_result();
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// Get classes for filter dropdown
$classes = [];
$class_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name, section");
while ($row = $class_result->fetch_assoc()) {
    $classes[] = $row;
}

// Get batch years for filter dropdown
$batch_years = [];
$batch_result = $conn->query("SELECT DISTINCT batch_year FROM students ORDER BY batch_year DESC");
while ($row = $batch_result->fetch_assoc()) {
    $batch_years[] = $row['batch_year'];
}

// Get student statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'male' => 0,
    'female' => 0,
    'other' => 0
];

$stats_result = $conn->query("SELECT COUNT(*) as total FROM students");
$stats['total'] = $stats_result->fetch_assoc()['total'];

$stats_result = $conn->query("SELECT COUNT(*) as active FROM students s JOIN users u ON s.user_id = u.user_id WHERE u.status = 'active'");
$stats['active'] = $stats_result->fetch_assoc()['active'];

$stats['inactive'] = $stats['total'] - $stats['active'];

$stats_result = $conn->query("SELECT COUNT(*) as male FROM students WHERE gender = 'male'");
$stats['male'] = $stats_result->fetch_assoc()['male'];

$stats_result = $conn->query("SELECT COUNT(*) as female FROM students WHERE gender = 'female'");
$stats['female'] = $stats_result->fetch_assoc()['female'];

$stats_result = $conn->query("SELECT COUNT(*) as other FROM students WHERE gender = 'other' OR gender IS NULL");
$stats['other'] = $stats_result->fetch_assoc()['other'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management | Result Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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

        /* Hover effect */
        .hover-scale {
            transition: all 0.3s ease;
        }

        .hover-scale:hover {
            transform: scale(1.03);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Dark mode toggle */
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

        /* Action buttons */
        .action-button {
            transition: all 0.2s ease;
        }

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Tooltip styles */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 120px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
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
                        <!-- Alert Messages -->
                        <?php if (isset($success_message)): ?>
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                                <p><?php echo $success_message; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                                <p><?php echo $error_message; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Page Header -->
                        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">Student Management</h2>
                                    <p class="mt-1 text-sm text-gray-500">Manage all students in the system</p>
                                </div>
                                <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                                    <button onclick="showAddStudentModal()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-user-plus mr-2"></i> Add Student
                                    </button>
                                    </button>
                                    <button onclick="printStudentList()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                        <i class="fas fa-print mr-2"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                            <i class="fas fa-users text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Total Students</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $stats['total']; ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                            <i class="fas fa-user-check text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Active Students</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $stats['active']; ?></div>
                                                    <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                                        <?php echo round(($stats['active'] / max(1, $stats['total'])) * 100); ?>%
                                                    </div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                            <i class="fas fa-user-clock text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Inactive Students</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $stats['inactive']; ?></div>
                                                    <div class="ml-2 flex items-baseline text-sm font-semibold text-yellow-600">
                                                        <?php echo round(($stats['inactive'] / max(1, $stats['total'])) * 100); ?>%
                                                    </div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                           
                        </div>

                        <!-- Search and Filters -->
                        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <form action="" method="GET" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username, ID, Roll No, Email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="class" class="block text-sm font-medium text-gray-700">Class</label>
                                        <select name="class" id="class" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">All Classes</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_filter == $class['class_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($class['class_name'] . ' ' . $class['section']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                        <select name="status" id="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">All Status</option>
                                            <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="batch" class="block text-sm font-medium text-gray-700">Batch Year</label>
                                        <select name="batch" id="batch" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">All Batches</option>
                                            <?php foreach ($batch_years as $year): ?>
                                                <option value="<?php echo $year; ?>" <?php echo ($batch_filter == $year) ? 'selected' : ''; ?>>
                                                    <?php echo $year; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <a href="students.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">
                                        Reset
                                    </a>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-search mr-2"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>                        

                        <!-- Students Table -->
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Students List</h3>
                                        <p class="mt-1 text-sm text-gray-500">Showing <?php echo count($students); ?> students</p>
                                    </div>
                                    <div>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" id="selectAll" class="form-checkbox h-5 w-5 text-blue-600">
                                            <span class="ml-2 text-sm text-gray-700">Select All</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <span class="sr-only">Select</span>
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($students)): ?>
                                            <tr>
                                                <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">No students found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students as $student): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <input type="checkbox" name="selected_students[]" value="<?php echo $student['student_id']; ?>" form="bulkActionForm" class="student-checkbox form-checkbox h-5 w-5 text-blue-600">
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($student['student_id']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <a href="#" onclick="showProfileModal('<?php echo $student['student_id']; ?>'); return false;" class="text-blue-600 hover:text-blue-900">
                                                            <?php echo htmlspecialchars($student['full_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($student['roll_number']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($student['email']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($student['batch_year']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($student['status'] == 'active'): ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                Active
                                                            </span>
                                                        <?php elseif ($student['status'] == 'inactive'): ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                                Inactive
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                                Pending
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <div class="flex space-x-1">
                                                            <button onclick="showProfileModal('<?php echo $student['student_id']; ?>')" class="bg-blue-100 hover:bg-blue-200 text-blue-700 py-1 px-2 rounded inline-flex items-center" title="View Profile">
                                                                <i class="fas fa-eye mr-1"></i> View
                                                            </button>
                                                            <button onclick="showEditModal('<?php echo $student['student_id']; ?>')" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 py-1 px-2 rounded inline-flex items-center" title="Edit">
                                                                <i class="fas fa-edit mr-1"></i> Edit
                                                            </button>
                                                            <button onclick="showResultsModal('<?php echo $student['student_id']; ?>')" class="bg-green-100 hover:bg-green-200 text-green-700 py-1 px-2 rounded inline-flex items-center" title="View Results">
                                                                <i class="fas fa-clipboard-list mr-1"></i> Results
                                                            </button>
                                                            <button onclick="confirmDelete('<?php echo $student['student_id']; ?>')" class="bg-red-100 hover:bg-red-200 text-red-700 py-1 px-2 rounded inline-flex items-center" title="Delete">
                                                                <i class="fas fa-trash mr-1"></i> Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                                <div class="flex-1 flex justify-between sm:hidden">
                                    <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                    <a href="#" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                </div>
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($students); ?></span> of <span class="font-medium"><?php echo $stats['total']; ?></span> students
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                            <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Previous</span>
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                            <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                1
                                            </a>
                                            <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Next</span>
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

 

    <!-- Add Student Modal -->
    <div id="addStudentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center border-b pb-3">
                <h3 class="text-lg font-medium text-gray-900">Add New Student</h3>
                <button type="button" onclick="closeAddStudentModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mt-4">
                <form id="addStudentForm" onsubmit="return saveNewStudent()">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <!-- Personal Information -->
                        <div class="md:col-span-2">
                            <h4 class="text-lg font-medium text-gray-900 mb-2">Personal Information</h4>
                        </div>
                        
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700">Username</label>
                            <input type="text" name="full_name" id="full_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" name="password" id="password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" name="phone" id="phone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
                            <select name="gender" id="gender" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="date_of_birth" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                            <textarea name="address" id="address" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                        </div>
                        
                        <!-- Academic Information -->
                        <div class="md:col-span-2 mt-4">
                            <h4 class="text-lg font-medium text-gray-900 mb-2">Academic Information</h4>
                        </div>
                        
                        <div>
                            <label for="roll_number" class="block text-sm font-medium text-gray-700">Roll Number</label>
                            <input type="text" name="roll_number" id="roll_number" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                        </div>
                        
                        <div>
                            <label for="class_id" class="block text-sm font-medium text-gray-700">Class</label>
                            <select name="class_id" id="class_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name'] . ' ' . $class['section']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="batch_year" class="block text-sm font-medium text-gray-700">Batch Year</label>
                            <input type="text" name="batch_year" id="batch_year" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        
                        <!-- Parent/Guardian Information -->
                        <div class="md:col-span-2 mt-4">
                            <h4 class="text-lg font-medium text-gray-900 mb-2">Parent/Guardian Information</h4>
                        </div>
                        
                        <div>
                            <label for="parent_name" class="block text-sm font-medium text-gray-700">Parent/Guardian Name</label>
                            <input type="text" name="parent_name" id="parent_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="parent_phone" class="block text-sm font-medium text-gray-700">Parent/Guardian Phone</label>
                            <input type="text" name="parent_phone" id="parent_phone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="parent_email" class="block text-sm font-medium text-gray-700">Parent/Guardian Email</label>
                            <input type="email" name="parent_email" id="parent_email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeAddStudentModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Add Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profileModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center border-b pb-3">
                <h3 class="text-lg font-medium text-gray-900">Student Profile</h3>
                <button type="button" onclick="closeProfileModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="profileContent" class="mt-4">
                <div class="flex justify-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center border-b pb-3">
                <h3 class="text-lg font-medium text-gray-900">Edit Student</h3>
                <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="editContent" class="mt-4">
                <div class="flex justify-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Modal -->
    <div id="resultsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center border-b pb-3">
                <h3 class="text-lg font-medium text-gray-900">Student Results</h3>
                <button type="button" onclick="closeResultsModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="resultsContent" class="mt-4">
                <div class="flex justify-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Confirm delete function
        function confirmDelete(studentId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this! All student data including results will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'students.php?delete=' + studentId;
                }
            });
        }

        // Confirm bulk action
        function confirmBulkAction() {
            const action = document.getElementById('bulk_action').value;
            const checkboxes = document.querySelectorAll('.student-checkbox:checked');
            
            if (!action) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Please select an action.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }
            
            if (checkboxes.length === 0) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Please select at least one student.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }
            
            if (action === 'change_class') {
                const newClassId = document.getElementById('new_class_id').value;
                if (!newClassId) {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Please select a new class.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
            }
            
            let confirmMessage = '';
            switch (action) {
                case 'activate':
                    confirmMessage = 'Are you sure you want to activate the selected students?';
                    break;
                case 'deactivate':
                    confirmMessage = 'Are you sure you want to deactivate the selected students?';
                    break;
                case 'delete':
                    confirmMessage = 'Are you sure you want to delete the selected students? This action cannot be undone!';
                    break;
                case 'change_class':
                    confirmMessage = 'Are you sure you want to change the class of the selected students?';
                    break;
            }
            
            Swal.fire({
                title: 'Confirm Action',
                text: confirmMessage,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'delete' ? '#d33' : '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, proceed!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('bulkActionForm').submit();
                }
            });
        }

        // Toggle class selector based on bulk action
        function toggleClassSelector(action) {
            const classSelector = document.getElementById('classSelector');
            if (action === 'change_class') {
                classSelector.classList.remove('hidden');
            } else {
                classSelector.classList.add('hidden');
            }
        }

        // Select all checkboxes
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Show success message if status parameter exists
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            
            if (status === 'deleted') {
                Swal.fire({
                    title: 'Deleted!',
                    text: 'Student has been deleted successfully.',
                    icon: 'success',
                    confirmButtonColor: '#3085d6'
                });
            }
        });
        

        
        // Add Student Modal Functions
        function showAddStudentModal() {
            document.getElementById('addStudentModal').classList.remove('hidden');
        }
        
        function closeAddStudentModal() {
            document.getElementById('addStudentModal').classList.add('hidden');
        }
        
        // Save new student
        function saveNewStudent() {
            const form = document.getElementById('addStudentForm');
            const formData = new FormData(form);
            
            // Validate passwords match
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            if (password !== confirmPassword) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Passwords do not match.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
                return false;
            }
            
            fetch('save_new_student.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        closeAddStudentModal();
                        window.location.href = 'students.php?added=true';
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while saving: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            });
            
            return false;
        }
        
        // Print Student List Function
        function printStudentList() {
            // Get selected students
            const checkboxes = document.querySelectorAll('.student-checkbox:checked');
            const selectedStudents = Array.from(checkboxes).map(cb => cb.value);
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Get the table data
            const table = document.querySelector('table');
            const tableClone = table.cloneNode(true);
            
            // Remove checkboxes and action buttons from the print view
            const checkboxCells = tableClone.querySelectorAll('td:first-child, th:first-child');
            checkboxCells.forEach(cell => {
                cell.remove();
            });
            
            const actionCells = tableClone.querySelectorAll('td:last-child, th:last-child');
            actionCells.forEach(cell => {
                cell.remove();
            });
            
            // If students are selected, filter the table to only show selected students
            if (selectedStudents.length > 0) {
                const rows = tableClone.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const studentId = row.cells[0].textContent.trim();
                    if (!selectedStudents.includes(studentId)) {
                        row.remove();
                    }
                });
            }
            
            // Create print HTML
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Student List</title>
                    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
                    <style>
                        @media print {
                            body {
                                font-size: 12px;
                            }
                            table {
                                width: 100%;
                                border-collapse: collapse;
                            }
                            th, td {
                                border: 1px solid #ddd;
                                padding: 8px;
                                text-align: left;
                            }
                            th {
                                background-color: #f2f2f2;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="p-4">
                        <h1 class="text-2xl font-bold mb-4">Student List</h1>
                        <p class="mb-4">Generated on: ${new Date().toLocaleString()}</p>
                        ${tableClone.outerHTML}
                    </div>
                   
                </body>
                </html>
            `;
            
            // Write to the new window and print
            printWindow.document.open();
            printWindow.document.write(printContent);
            printWindow.document.close();
        }
        
        // Profile Modal Functions
        function showProfileModal(studentId) {
            document.getElementById('profileModal').classList.remove('hidden');
            document.getElementById('profileContent').innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>';
            
            // Fetch student profile data
            fetch(`get_student_profile.php?id=${studentId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('profileContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('profileContent').innerHTML = `<div class="text-red-500">Error loading profile: ${error.message}</div>`;
                });
        }
        
        function closeProfileModal() {
            document.getElementById('profileModal').classList.add('hidden');
        }
        
        // Edit Modal Functions
        function showEditModal(studentId) {
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editContent').innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>';
            
            // Fetch student edit form
            fetch(`get_student_edit_form.php?id=${studentId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('editContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('editContent').innerHTML = `<div class="text-red-500">Error loading edit form: ${error.message}</div>`;
                });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        // Results Modal Functions
        function showResultsModal(studentId) {
            document.getElementById('resultsModal').classList.remove('hidden');
            document.getElementById('resultsContent').innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>';
            
            // Fetch student results
            fetch(`get_student_results.php?id=${studentId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('resultsContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('resultsContent').innerHTML = `<div class="text-red-500">Error loading results: ${error.message}</div>`;
                });
        }
        
        function closeResultsModal() {
            document.getElementById('resultsModal').classList.add('hidden');
        }
        
        // Save student edit form
        function saveStudentEdit(formId) {
            const form = document.getElementById(formId);
            const formData = new FormData(form);
            
            fetch('save_student_edit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        closeEditModal();
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while saving: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            });
            
            return false;
        }

// Enhanced modal functions with loading indicators and error handling
function showProfileModal(studentId) {
    document.getElementById('profileModal').classList.remove('hidden');
    const profileContent = document.getElementById('profileContent');
    profileContent.innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>';
    
    // Fetch student profile data with better error handling
    fetch(`get_student_profile.php?id=${studentId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            profileContent.innerHTML = data;
        })
        .catch(error => {
            profileContent.innerHTML = `
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                    <p class="font-bold">Error</p>
                    <p>Failed to load student profile: ${error.message}</p>
                    <button onclick="closeProfileModal()" class="mt-4 bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        Close
                    </button>
                </div>
            `;
        });
}

function showEditModal(studentId) {
    document.getElementById('editModal').classList.remove('hidden');
    const editContent = document.getElementById('editContent');
    editContent.innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>';
    
    // Fetch student edit form with better error handling
    fetch(`get_student_edit_form.php?id=${studentId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            editContent.innerHTML = data;
        })
        .catch(error => {
            editContent.innerHTML = `
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                    <p class="font-bold">Error</p>
                    <p>Failed to load edit form: ${error.message}</p>
                    <button onclick="closeEditModal()" class="mt-4 bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        Close
                    </button>
                </div>
            `;
        });
}

function showResultsModal(studentId) {
    document.getElementById('resultsModal').classList.remove('hidden');
    const resultsContent = document.getElementById('resultsContent');
    resultsContent.innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>';
    
    // Fetch student results with better error handling
    fetch(`get_student_results.php?id=${studentId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            resultsContent.innerHTML = data;
            
            // Set the student ID on the exam selector after content is loaded
            const examSelector = document.getElementById('exam_selector');
            if (examSelector) {
                examSelector.setAttribute('data-student-id', studentId);
            }
        })
        .catch(error => {
            resultsContent.innerHTML = `
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                    <p class="font-bold">Error</p>
                    <p>Failed to load student results: ${error.message}</p>
                    <button onclick="closeResultsModal()" class="mt-4 bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        Close
                    </button>
                </div>
            `;
        });
}

// Enhanced delete confirmation with more detailed warning
function confirmDelete(studentId) {
    Swal.fire({
        title: 'Delete Student?',
        html: `
            <div class="text-left">
                <p class="mb-2">You are about to delete this student. This action:</p>
                <ul class="list-disc pl-5 mb-4">
                    <li>Will permanently remove the student record</li>
                    <li>Will delete all associated results and performance data</li>
                    <li>Will remove the user account</li>
                    <li>Cannot be undone</li>
                </ul>
                <p class="font-bold text-red-600">Are you sure you want to proceed?</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete student',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Deleting...',
                html: 'Please wait while we delete the student record.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Redirect to delete URL
            window.location.href = 'students.php?delete=' + studentId;
        }
    });
}

function changeExam(examId) {
    // Show loading indicator only in the table body
    const tableBody = document.querySelector('#resultsContent table tbody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-4 text-center">
                    <div class="flex justify-center items-center space-x-2">
                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"></div>
                        <span class="text-sm text-gray-500">Loading results...</span>
                    </div>
                </td>
            </tr>
        `;
    } else {
        // Fallback if table body not found
        document.getElementById('resultsContent').innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>';
    }
    
    // Get the student ID from the data attribute
    const studentId = document.getElementById('exam_selector').getAttribute('data-student-id');
    
    // Add a timestamp to prevent caching
    const timestamp = new Date().getTime();
    
    // Use fetch with cache control
    fetch(`get_student_results.php?id=${studentId}&exam_id=${examId}&_=${timestamp}`, {
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.text();
    })
    .then(data => {
        document.getElementById('resultsContent').innerHTML = data;
        
        // Update the exam selector to maintain the selected value
        const newExamSelector = document.getElementById('exam_selector');
        if (newExamSelector) {
            newExamSelector.value = examId;
            newExamSelector.setAttribute('data-student-id', studentId);
        }
    })
    .catch(error => {
        document.getElementById('resultsContent').innerHTML = `
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                <p class="font-bold">Error</p>
                <p>Failed to load results: ${error.message}</p>
                <button onclick="closeResultsModal()" class="mt-4 bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                    Close
                </button>
            </div>
        `;
    });
}
    </script>
</body>

</html>
