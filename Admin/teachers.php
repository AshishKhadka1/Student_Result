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

// Handle individual teacher deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $teacher_id = $_GET['delete'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First check if the teacher exists
        $check_stmt = $conn->prepare("SELECT user_id FROM teachers WHERE teacher_id = ?");
        $check_stmt->bind_param("i", $teacher_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            // Teacher doesn't exist - this could be because it was already deleted
            // or the ID is invalid. Either way, we'll show a success message.
            $conn->rollback(); // No need for a transaction
            $success_message = "Teacher with ID $teacher_id has been removed from the system.";
            $check_stmt->close();
        } else {
            // Teacher exists, proceed with deletion
            $teacher_data = $check_result->fetch_assoc();
            $user_id = $teacher_data['user_id'];
            $check_stmt->close();
            
            // Delete teacher's subject assignments first
            $stmt = $conn->prepare("DELETE FROM teachersubjects WHERE teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete teacher record
            $stmt = $conn->prepare("DELETE FROM teachers WHERE teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete user account
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Teacher deleted successfully.";
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error deleting teacher: " . $e->getMessage();
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
// Department filter removed
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Base query
$query = "SELECT t.*, u.full_name, u.email, u.status, u.phone 
          FROM teachers t 
          JOIN users u ON t.user_id = u.user_id 
          WHERE 1=1";

// Add filters
if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (u.full_name LIKE ? OR t.employee_id LIKE ? OR u.email LIKE ? OR t.department LIKE ?)";
}

// Department filter condition removed

if (!empty($status_filter)) {
    $query .= " AND u.status = ?";
}

// Add order by
$query .= " ORDER BY t.created_at DESC";

// Prepare statement
$stmt = $conn->prepare($query);

// Bind parameters
if (!empty($search) && !empty($status_filter)) {
    $stmt->bind_param("sssss", $search, $search, $search, $search, $status_filter);
} elseif (!empty($search)) {
    $stmt->bind_param("ssss", $search, $search, $search, $search);
} elseif (!empty($status_filter)) {
    $stmt->bind_param("s", $status_filter);
}

// Execute query
$stmt->execute();
$result = $stmt->get_result();
$teachers = [];
while ($row = $result->fetch_assoc()) {
    $teachers[] = $row;
}
$stmt->close();

// Get departments for filter dropdown
$departments = [];
$dept_result = $conn->query("SELECT DISTINCT department FROM teachers WHERE department IS NOT NULL ORDER BY department");
while ($row = $dept_result->fetch_assoc()) {
    $departments[] = $row['department'];
}

// Get teacher statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'subjects_assigned' => 0
];

$stats_result = $conn->query("SELECT COUNT(*) as total FROM teachers");
if ($stats_result) {
    $stats['total'] = $stats_result->fetch_assoc()['total'];
}

$stats_result = $conn->query("SELECT COUNT(*) as active FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.status = 'active'");
if ($stats_result) {
    $stats['active'] = $stats_result->fetch_assoc()['active'];
}

$stats['inactive'] = $stats['total'] - $stats['active'];

$stats_result = $conn->query("SELECT COUNT(*) as subjects_assigned FROM teachersubjects");
if ($stats_result) {
    $stats['subjects_assigned'] = $stats_result->fetch_assoc()['subjects_assigned'];
}

// Check if gender column exists in teachers table
$column_check = $conn->query("SHOW COLUMNS FROM teachers LIKE 'gender'");
$gender_exists = $column_check && $column_check->num_rows > 0;

// Only query gender statistics if the column exists
if ($gender_exists) {
    $stats['male'] = 0;
    $stats['female'] = 0;
    $stats['other'] = 0;
    
    $stats_result = $conn->query("SELECT COUNT(*) as male FROM teachers WHERE gender = 'male'");
    if ($stats_result) {
        $stats['male'] = $stats_result->fetch_assoc()['male'];
    }
    
    $stats_result = $conn->query("SELECT COUNT(*) as female FROM teachers WHERE gender = 'female'");
    if ($stats_result) {
        $stats['female'] = $stats_result->fetch_assoc()['female'];
    }
    
    $stats_result = $conn->query("SELECT COUNT(*) as other FROM teachers WHERE gender = 'other' OR gender IS NULL");
    if ($stats_result) {
        $stats['other'] = $stats_result->fetch_assoc()['other'];
    }
}

// Get subject assignments for each teacher
foreach ($teachers as &$teacher) {
    $teacher_id = $teacher['teacher_id'];
    $subjects_query = "SELECT ts.*, s.subject_name, c.class_name, c.section 
                      FROM teachersubjects ts 
                      JOIN subjects s ON ts.subject_id = s.subject_id 
                      JOIN classes c ON ts.class_id = c.class_id 
                      WHERE ts.teacher_id = ? AND ts.is_active = 1";
    $subjects_stmt = $conn->prepare($subjects_query);
    $subjects_stmt->bind_param("i", $teacher_id);
    $subjects_stmt->execute();
    $subjects_result = $subjects_stmt->get_result();
    
    $teacher['subjects'] = [];
    while ($subject = $subjects_result->fetch_assoc()) {
        $teacher['subjects'][] = $subject;
    }
    $subjects_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management | Result Management System</title>
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
                                    <h2 class="text-xl font-bold text-gray-900">Teacher Management</h2>
                                    <p class="mt-1 text-sm text-gray-500">Manage all teachers in the system</p>
                                </div>
                                <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                                    <button onclick="showAddTeacherModal()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-user-plus mr-2"></i> Add Teacher
                                    </button>
                                   
                                    <button onclick="printTeacherList()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
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
                                        <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                            <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Total Teachers</dt>
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
                                                <dt class="text-sm font-medium text-gray-500 truncate">Active Teachers</dt>
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
                                                <dt class="text-sm font-medium text-gray-500 truncate">Inactive Teachers</dt>
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

                            <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                            <i class="fas fa-book text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Subjects Assigned</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $stats['subjects_assigned']; ?></div>
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
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, ID, Email, Department" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    <!-- Department filter removed -->
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                        <select name="status" id="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">All Status</option>
                                            <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <a href="teachers.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">
                                        Reset
                                    </a>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-search mr-2"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Teachers Table -->
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Teachers List</h3>
                                        <p class="mt-1 text-sm text-gray-500">Showing <?php echo count($teachers); ?> teachers</p>
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
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <!-- Department column removed -->
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qualification</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjects</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <!-- Add data-phone attribute to each teacher row for printing -->
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($teachers)): ?>
                                            <tr>
                                                <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">No teachers found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <tr class="hover:bg-gray-50" data-phone="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <input type="checkbox" name="selected_teachers[]" value="<?php echo $teacher['teacher_id']; ?>" form="bulkActionForm" class="teacher-checkbox form-checkbox h-5 w-5 text-blue-600">
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($teacher['employee_id']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <a href="#" onclick="showProfileModal('<?php echo $teacher['teacher_id']; ?>'); return false;" class="text-blue-600 hover:text-blue-900">
                                                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($teacher['email']); ?>
                                                    </td>
                                                    <!-- Department cell removed -->
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($teacher['qualification']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php if (empty($teacher['subjects'])): ?>
                                                            <span class="text-gray-400">No subjects assigned</span>
                                                        <?php else: ?>
                                                            <div class="flex flex-wrap gap-1">
                                                                <?php foreach (array_slice($teacher['subjects'], 0, 2) as $subject): ?>
                                                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                                <?php if (count($teacher['subjects']) > 2): ?>
                                                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                                                        +<?php echo count($teacher['subjects']) - 2; ?> more
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($teacher['status'] == 'active'): ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                Active
                                                            </span>
                                                        <?php elseif ($teacher['status'] == 'inactive'): ?>
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
                                                            <button onclick="showProfileModal('<?php echo $teacher['teacher_id']; ?>')" class="bg-blue-100 hover:bg-blue-200 text-blue-700 py-1 px-2 rounded inline-flex items-center" title="View Profile">
                                                                <i class="fas fa-eye mr-1"></i> View
                                                            </button>
                                                            <button onclick="showEditModal('<?php echo $teacher['teacher_id']; ?>')" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 py-1 px-2 rounded inline-flex items-center" title="Edit">
                                                                <i class="fas fa-edit mr-1"></i> Edit
                                                            </button>
                                                            <button onclick="showSubjectsModal('<?php echo $teacher['teacher_id']; ?>')" class="bg-green-100 hover:bg-green-200 text-green-700 py-1 px-2 rounded inline-flex items-center" title="Manage Subjects">
                                                                <i class="fas fa-book mr-1"></i> Subjects
                                                            </button>
                                                            <button onclick="confirmDelete('<?php echo $teacher['teacher_id']; ?>')" class="bg-red-100 hover:bg-red-200 text-red-700 py-1 px-2 rounded inline-flex items-center" title="Delete">
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
                                            Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($teachers); ?></span> of <span class="font-medium"><?php echo $stats['total']; ?></span> teachers
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



    <!-- Add Teacher Modal -->
    <div id="addTeacherModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center border-b pb-3">
                <h3 class="text-lg font-medium text-gray-900">Add New Teacher</h3>
                <button type="button" onclick="closeAddTeacherModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mt-4">
                <form id="addTeacherForm" onsubmit="return saveNewTeacher()">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <!-- Personal Information -->
                        <div class="md:col-span-2">
                            <h4 class="text-lg font-medium text-gray-900 mb-2">Personal Information</h4>
                        </div>
                        
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
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
                        
                        <!-- Professional Information -->
                        <div class="md:col-span-2 mt-4">
                            <h4 class="text-lg font-medium text-gray-900 mb-2">Professional Information</h4>
                        </div>
                        
                        <div>
                            <label for="employee_id" class="block text-sm font-medium text-gray-700">Employee ID</label>
                            <input type="text" name="employee_id" id="employee_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                        </div>
                        
                        <!-- Department field removed -->
                        
                        <div>
                            <label for="qualification" class="block text-sm font-medium text-gray-700">Qualification</label>
                            <input type="text" name="qualification" id="qualification" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="joining_date" class="block text-sm font-medium text-gray-700">Joining Date</label>
                            <input type="date" name="joining_date" id="joining_date" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="experience" class="block text-sm font-medium text-gray-700">Experience (years)</label>
                            <input type="number" name="experience" id="experience" min="0" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeAddTeacherModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Add Teacher
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
                <h3 class="text-lg font-medium text-gray-900">Teacher Profile</h3>
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
                <h3 class="text-lg font-medium text-gray-900">Edit Teacher</h3>
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

    <!-- Subjects Modal -->
    <div id="subjectsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center border-b pb-3">
                <h3 class="text-lg font-medium text-gray-900">Manage Subjects</h3>
                <button type="button" onclick="closeSubjectsModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="subjectsContent" class="mt-4">
                <div class="flex justify-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/teachers.js"></script>
<script>
    // Print Teacher List Function
    function printTeacherList() {
        // Get selected teachers
        const checkboxes = document.querySelectorAll('.teacher-checkbox:checked');
        const selectedTeachers = Array.from(checkboxes).map(cb => cb.value);
        
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
        
        // If teachers are selected, filter the table to only show selected teachers
        if (selectedTeachers.length > 0) {
            const rows = tableClone.querySelectorAll('tbody tr');
            rows.forEach(row => {
                // Get the teacher ID from the checkbox value
                const checkboxInOriginalRow = document.querySelectorAll('.teacher-checkbox')[Array.from(rows).indexOf(row)];
                if (checkboxInOriginalRow && !selectedTeachers.includes(checkboxInOriginalRow.value)) {
                    row.remove();
                }
            });
        }
        
        // Create print HTML
        const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Teacher List</title>
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
                    <h1 class="text-2xl font-bold mb-4">Teacher List</h1>
                    <p class="mb-4">Generated on: ${new Date().toLocaleString()}</p>
                    ${tableClone.outerHTML}
                </div>
                <script>
                    // Auto print when the page loads
                    window.onload = function() {
                        window.print();
                    }
                <\/script>
            </body>
            </html>
        `;
        
        // Write to the new window and print
        printWindow.document.open();
        printWindow.document.write(printContent);
        printWindow.document.close();
    }

    // Function to handle password change
    

    // Show password change modal
    

    // Close password modal
    
</script>

</body>

</html>
