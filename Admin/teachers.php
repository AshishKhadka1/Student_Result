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

// Handle teacher deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $teacher_id = $_GET['delete'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete teacher's subject assignments first
        $stmt = $conn->prepare("DELETE FROM teachersubjects WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $stmt->close();
        
        // Get user_id associated with teacher
        $stmt = $conn->prepare("SELECT user_id FROM teachers WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_id = $result->fetch_assoc()['user_id'];
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
        
        // Redirect to prevent resubmission
        header("Location: teachers.php?status=deleted");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error deleting teacher: " . $e->getMessage();
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Base query
$query = "SELECT t.*, u.full_name, u.email, u.status 
          FROM teachers t 
          JOIN users u ON t.user_id = u.user_id 
          WHERE 1=1";

// Add filters
if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (u.full_name LIKE ? OR t.employee_id LIKE ? OR u.email LIKE ? OR t.department LIKE ?)";
}

if (!empty($department_filter)) {
    $query .= " AND t.department = ?";
}

if (!empty($status_filter)) {
    $query .= " AND u.status = ?";
}

// Add order by
$query .= " ORDER BY t.created_at DESC";

// Prepare statement
$stmt = $conn->prepare($query);

// Bind parameters
if (!empty($search) && !empty($department_filter) && !empty($status_filter)) {
    $stmt->bind_param("sssss", $search, $search, $search, $search, $department_filter, $status_filter);
} elseif (!empty($search) && !empty($department_filter)) {
    $stmt->bind_param("sssss", $search, $search, $search, $search, $department_filter);
} elseif (!empty($search) && !empty($status_filter)) {
    $stmt->bind_param("sssss", $search, $search, $search, $search, $status_filter);
} elseif (!empty($department_filter) && !empty($status_filter)) {
    $stmt->bind_param("ss", $department_filter, $status_filter);
} elseif (!empty($search)) {
    $stmt->bind_param("ssss", $search, $search, $search, $search);
} elseif (!empty($department_filter)) {
    $stmt->bind_param("s", $department_filter);
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
$stats['total'] = $stats_result->fetch_assoc()['total'];

$stats_result = $conn->query("SELECT COUNT(*) as active FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.status = 'active'");
$stats['active'] = $stats_result->fetch_assoc()['active'];

$stats['inactive'] = $stats['total'] - $stats['active'];

$stats_result = $conn->query("SELECT COUNT(*) as subjects_assigned FROM teachersubjects");
$stats['subjects_assigned'] = $stats_result->fetch_assoc()['subjects_assigned'];

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
                        <!-- Page Header -->
                        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">Teacher Management</h2>
                                    <p class="mt-1 text-sm text-gray-500">Manage all teachers in the system</p>
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

                           
                        </div>

                        <!-- Search and Filters -->
                        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <form action="" method="GET" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, ID, Email, Department" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                                        <select name="department" id="department" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">All Departments</option>
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?php echo $department; ?>" <?php echo ($department_filter == $department) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($department); ?>
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
                                <h3 class="text-lg font-medium text-gray-900">Teachers List</h3>
                                <p class="mt-1 text-sm text-gray-500">Showing <?php echo count($teachers); ?> teachers</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qualification</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjects</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($teachers)): ?>
                                            <tr>
                                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">No teachers found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($teacher['employee_id']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <a href="teacher_profile.php?id=<?php echo $teacher['teacher_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($teacher['email']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($teacher['department']); ?>
                                                    </td>
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
                                                        <div class="flex space-x-2">
                                                            <a href="teacher_profile.php?id=<?php echo $teacher['teacher_id']; ?>" class="text-blue-600 hover:text-blue-900" title="View Profile">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="edit_teacher.php?id=<?php echo $teacher['teacher_id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="assign_subjects.php?teacher_id=<?php echo $teacher['teacher_id']; ?>" class="text-green-600 hover:text-green-900" title="Assign Subjects">
                                                                <i class="fas fa-book"></i>
                                                            </a>
                                                            <a href="#" onclick="confirmDelete('<?php echo $teacher['teacher_id']; ?>')" class="text-red-600 hover:text-red-900" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
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

    <script>
        // Confirm delete function
        function confirmDelete(teacherId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this! All teacher data including subject assignments will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'teachers.php?delete=' + teacherId;
                }
            });
        }

        // Show success message if status parameter exists
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            
            if (status === 'deleted') {
                Swal.fire({
                    title: 'Deleted!',
                    text: 'Teacher has been deleted successfully.',
                    icon: 'success',
                    confirmButtonColor: '#3085d6'
                });
            }
        });
    </script>
</body>

</html>