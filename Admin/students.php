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

// Process actions
$success_message = '';
$error_message = '';

// Handle user deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Check if user exists and is not the current user
    if ($user_id == $_SESSION['user_id']) {
        $error_message = "You cannot delete your own account.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete related records first (maintain referential integrity)
            // For students
            $stmt = $conn->prepare("DELETE FROM students WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            // For teachers
            $stmt = $conn->prepare("DELETE FROM teachers WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "User deleted successfully.";
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Handle status toggle
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Get current status
    $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $new_status = ($user['status'] == 'active') ? 'inactive' : 'active';
        
        // Update status
        $stmt = $conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        $stmt->execute();
        $stmt->close();
        
        $success_message = "User status updated successfully.";
    } else {
        $error_message = "User not found.";
    }
}

// Get users with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Build query
$query = "SELECT u.*, 
          CASE 
            WHEN u.role = 'student' THEN (SELECT s.roll_number FROM students s WHERE s.user_id = u.user_id) 
            WHEN u.role = 'teacher' THEN (SELECT t.employee_id FROM teachers t WHERE t.user_id = u.user_id)
            ELSE ''
          END as identifier
          FROM users u WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM users u WHERE 1=1";

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $count_query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
}

if (!empty($role_filter)) {
    $query .= " AND u.role = ?";
    $count_query .= " AND u.role = ?";
}

$query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";

// Prepare and execute count query
$stmt = $conn->prepare($count_query);

if (!empty($search) && !empty($role_filter)) {
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $role_filter);
} elseif (!empty($search)) {
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} elseif (!empty($role_filter)) {
    $stmt->bind_param("s", $role_filter);
}

$stmt->execute();
$count_result = $stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_records / $limit);

// Prepare and execute main query
$stmt = $conn->prepare($query);

if (!empty($search) && !empty($role_filter)) {
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $role_filter, $limit, $offset);
} elseif (!empty($search)) {
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $limit, $offset);
} elseif (!empty($role_filter)) {
    $stmt->bind_param("sii", $role_filter, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 bg-gray-800">
                <div class="flex items-center justify-center h-16 bg-gray-900">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <div class="flex flex-col flex-grow px-4 mt-5">
                    <nav class="flex-1 space-y-1">
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            Results
                        </a>
                        <a href="bulk_upload.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-upload mr-3"></i>
                            Bulk Upload
                        </a>
                        <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-users mr-3"></i>
                            Users
                        </a>
                        <a href="students.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-user-graduate mr-3"></i>
                            Students
                        </a>
                        <a href="teachers.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard-teacher mr-3"></i>
                            Teachers
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-cog mr-3"></i>
                            Settings
                        </a>
                    </nav>
                    <div class="flex-shrink-0 block w-full">
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">User Management</h1>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
                        <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <span class="sr-only">View notifications</span>
                            <i class="fas fa-bell"></i>
                        </button>

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
                        <!-- Success/Error Messages -->
                        <?php if (!empty($success_message)): ?>
                            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700"><?php echo $success_message; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700"><?php echo $error_message; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Search and Filter -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                            <div class="px-4 py-5 sm:p-6">
                                <form action="users.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="md:col-span-2">
                                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, username, or email" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                    <div>
                                        <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                        <select name="role" id="role" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            <option value="">All Roles</option>
                                            <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="teacher" <?php echo $role_filter == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                            <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                                        </select>
                                    </div>
                                    <div class="flex items-end">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-search mr-2"></i> Search
                                        </button>
                                        <a href="users.php" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-redo mr-2"></i> Reset
                                        </a>
                                        <a href="add_user.php" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                            <i class="fas fa-plus mr-2"></i> Add User
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Users Table -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Users</h3>
                                <p class="mt-1 text-sm text-gray-500">Manage all users in the system.</p>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($users)): ?>
                                                <tr>
                                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No users found.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($users as $user): ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="flex items-center">
                                                                <div class="flex-shrink-0 h-10 w-10">
                                                                    <?php if (!empty($user['profile_image'])): ?>
                                                                        <img class="h-10 w-10 rounded-full" src="<?php echo $user['profile_image']; ?>" alt="">
                                                                    <?php else: ?>
                                                                        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-gray-500">
                                                                            <span class="text-sm font-medium leading-none text-white"><?php echo substr($user['full_name'], 0, 1); ?></span>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="ml-4">
                                                                    <div class="text-sm font-medium text-gray-900"><?php echo $user['full_name']; ?></div>
                                                                    <?php if (!empty($user['identifier'])): ?>
                                                                        <div class="text-sm text-gray-500">ID: <?php echo $user['identifier']; ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $user['username']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $user['email']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                                <?php 
                                                                if ($user['role'] == 'admin') echo 'bg-purple-100 text-purple-800';
                                                                elseif ($user['role'] == 'teacher') echo 'bg-green-100 text-green-800';
                                                                else echo 'bg-blue-100 text-blue-800';
                                                                ?>">
                                                                <?php echo ucfirst($user['role']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($user['status'] == 'active') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                                <?php echo ucfirst($user['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="users.php?action=toggle_status&id=<?php echo $user['user_id']; ?>" class="text-yellow-600 hover:text-yellow-900 mr-3" onclick="return confirm('Are you sure you want to <?php echo ($user['status'] == 'active') ? 'deactivate' : 'activate'; ?> this user?')">
                                                                <i class="fas fa-<?php echo ($user['status'] == 'active') ? 'ban' : 'check-circle'; ?>"></i>
                                                            </a>
                                                            <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                                <a href="users.php?action=delete&id=<?php echo $user['user_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <div class="mt-6">
                                        <nav class="flex items-center justify-between">
                                            <div class="flex-1 flex justify-between sm:hidden">
                                                <?php if ($page > 1): ?>
                                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                        Previous
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($page < $total_pages): ?>
                                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                        Next
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                                <div>
                                                    <p class="text-sm text-gray-700">
                                                        Showing <span class="font-medium"><?php echo min(($page - 1) * $limit + 1, $total_records); ?></span> to <span class="font-medium"><?php echo min($page * $limit, $total_records); ?></span> of <span class="font-medium"><?php echo $total_records; ?></span> results
                                                    </p>
                                                </div>
                                                <div>
                                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                                        <?php if ($page > 1): ?>
                                                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                                <span class="sr-only">Previous</span>
                                                                <i class="fas fa-chevron-left"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php
                                                        $start_page = max(1, $page - 2);
                                                        $end_page = min($total_pages, $page + 2);
                                                        
                                                        if ($start_page > 1) {
                                                            echo '<a href="?page=1&search=' . urlencode($search) . '&role=' . urlencode($role_filter) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                                                            if ($start_page > 2) {
                                                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                                                            }
                                                        }
                                                        
                                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                                            echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '&role=' . urlencode($role_filter) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 ' . ($i == $page ? 'bg-blue-50 text-blue-600' : 'bg-white text-gray-700') . ' text-sm font-medium hover:bg-gray-50">' . $i . '</a>';
                                                        }
                                                        
                                                        if ($end_page < $total_pages) {
                                                            if ($end_page < $total_pages - 1) {
                                                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                                                            }
                                                            echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&role=' . urlencode($role_filter) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
                                                        }
                                                        ?>
                                                        
                                                        <?php if ($page < $total_pages): ?>
                                                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                                <span class="sr-only">Next</span>
                                                                <i class="fas fa-chevron-right"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </nav>
                                                </div>
                                            </div>
                                        </nav>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>