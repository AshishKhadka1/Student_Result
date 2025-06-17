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

// Handle user status toggle
if (isset($_GET['toggle_status']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $current_status = $_GET['current_status'];
    $new_status = ($current_status == 'active') ? 'inactive' : 'active';
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "User status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating user status: " . $conn->error;
    }
    $stmt->close();
    
    header("Location: users.php");
    exit();
}

// Handle user deletion
if (isset($_GET['delete']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    
    // Check if user is an admin
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user['role'] == 'admin' && $user_id != $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete another admin account.";
    } else if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own account.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // If student, delete student records first
            if ($user['role'] == 'student') {
                // First get the student_id
                $stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
                if ($stmt === false) {
                    throw new Exception("Error preparing statement: " . $conn->error);
                }
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $student = $result->fetch_assoc();
                $stmt->close();
                
                // If student_id exists, delete related results first
                if ($student && isset($student['student_id'])) {
                    $student_id = $student['student_id'];
                    
                    // Check if results table exists
                    $result = $conn->query("SHOW TABLES LIKE 'results'");
                    if ($result->num_rows > 0) {
                        $stmt = $conn->prepare("DELETE FROM results WHERE student_id = ?");
                        if ($stmt === false) {
                            throw new Exception("Error preparing results deletion statement: " . $conn->error);
                        }
                        $stmt->bind_param("s", $student_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                // Now delete the student record
                $stmt = $conn->prepare("DELETE FROM students WHERE user_id = ?");
                if ($stmt === false) {
                    throw new Exception("Error preparing statement: " . $conn->error);
                }
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
            }

            // If teacher, delete teacher records
            if ($user['role'] == 'teacher') {
                // Delete the teacher record directly
                $stmt = $conn->prepare("DELETE FROM teachers WHERE user_id = ?");
                if ($stmt === false) {
                    throw new Exception("Error preparing statement: " . $conn->error);
                }
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            if ($stmt === false) {
                throw new Exception("Error preparing statement: " . $conn->error);
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $_SESSION['success'] = "User deleted successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error deleting user: " . $e->getMessage();
        }
    }
    
    header("Location: users.php");
    exit();
}

// Handle add user form submission
if (isset($_POST['add_user'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $username = $full_name; // Set username as full name input
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    $address = isset($_POST['address']) ? $_POST['address'] : '';
    $status = 'active';
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        
        if ($count > 0) {
            throw new Exception("Email already exists");
        }
        
        // Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, status, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssss", $username, $email, $hashed_password, $full_name, $role, $status, $phone, $address);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating user: " . $stmt->error);
        }
        
        $user_id = $stmt->insert_id;
        $stmt->close();
        
        // If role is student, create student record
        if ($role == 'student' && isset($_POST['student_id']) && !empty($_POST['student_id'])) {
            $student_id = $_POST['student_id'];
            $roll_number = isset($_POST['roll_number']) ? $_POST['roll_number'] : '';
            $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : 0;
            $batch_year = isset($_POST['batch_year']) ? $_POST['batch_year'] : date('Y');
            
            $stmt = $conn->prepare("INSERT INTO students (student_id, user_id, roll_number, class_id, batch_year, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sisss", $student_id, $user_id, $roll_number, $class_id, $batch_year);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating student record: " . $stmt->error);
            }
            $stmt->close();
        }
        
        // If role is teacher, create teacher record
        if ($role == 'teacher' && isset($_POST['teacher_id']) && !empty($_POST['teacher_id'])) {
            $teacher_id = $_POST['teacher_id'];
            $qualification = isset($_POST['qualification']) ? $_POST['qualification'] : '';
            $specialization = isset($_POST['specialization']) ? $_POST['specialization'] : '';
            
            $stmt = $conn->prepare("INSERT INTO teachers (teacher_id, user_id, qualification, specialization, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("siss", $teacher_id, $user_id, $qualification, $specialization);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating teacher record: " . $stmt->error);
            }
            $stmt->close();
        }
        
        $conn->commit();
        $_SESSION['success'] = "User added successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: users.php");
    exit();
}

// Get filter values
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "SELECT u.*, 
          s.student_id, s.roll_number, s.class_id, 
          t.teacher_id, t.qualification 
          FROM users u 
          LEFT JOIN students s ON u.user_id = s.user_id 
          LEFT JOIN teachers t ON u.user_id = t.user_id 
          WHERE 1=1";
$params = [];
$types = "";

if (!empty($role_filter)) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR s.student_id LIKE ? OR t.teacher_id LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sssss";
}

$query .= " ORDER BY u.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch all users
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Get counts for each role
$admin_count = 0;
$teacher_count = 0;
$student_count = 0;
$active_count = 0;
$inactive_count = 0;

foreach ($users as $user) {
    if ($user['role'] == 'admin') $admin_count++;
    if ($user['role'] == 'teacher') $teacher_count++;
    if ($user['role'] == 'student') $student_count++;
    if ($user['status'] == 'active') $active_count++;
    if ($user['status'] == 'inactive') $inactive_count++;
}

// Get all classes for the form
$stmt = $conn->prepare("SELECT class_id, class_name FROM classes ORDER BY class_name");
$stmt->execute();
$result = $stmt->get_result();
$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <?php include 'mobile_sidebar.php'; 
        
        ?>

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
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- User Stats -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                            <i class="fas fa-users text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo count($users); ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                            <i class="fas fa-user-shield text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Admins</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $admin_count; ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                            <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Teachers</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $teacher_count; ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                            <i class="fas fa-user-graduate text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Students</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $student_count; ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                            <i class="fas fa-user-slash text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Inactive Users</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $inactive_count; ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add User Form -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Add New User</h2>
                            <form action="users.php" method="POST" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                        <input type="text" id="full_name" name="full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" id="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                        <input type="password" id="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                        <select id="role" name="role" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Role</option>
                                            <option value="admin">Admin</option>
                                            <option value="teacher">Teacher</option>
                                            <option value="student">Student</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                        <input type="text" id="phone" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                        <input type="text" id="address" name="address" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                                
                                <!-- Student specific fields -->
                                <div id="student_fields" class="hidden space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1">Student ID</label>
                                            <input type="text" id="student_id" name="student_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label for="roll_number" class="block text-sm font-medium text-gray-700 mb-1">Roll Number</label>
                                            <input type="text" id="roll_number" name="roll_number" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                            <select id="class_id" name="class_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                <option value="">Select Class</option>
                                                <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['class_id']; ?>"><?php echo $class['class_name']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label for="batch_year" class="block text-sm font-medium text-gray-700 mb-1">Batch Year</label>
                                            <input type="text" id="batch_year" name="batch_year" value="<?php echo date('Y'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Teacher specific fields -->
                                <div id="teacher_fields" class="hidden space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label for="teacher_id" class="block text-sm font-medium text-gray-700 mb-1">Teacher ID</label>
                                            <input type="text" id="teacher_id" name="teacher_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label for="qualification" class="block text-sm font-medium text-gray-700 mb-1">Qualification</label>
                                            <input type="text" id="qualification" name="qualification" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label for="specialization" class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                                            <input type="text" id="specialization" name="specialization" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <button type="submit" name="add_user" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Add User
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Filter and Search -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <form action="users.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                    <select id="role_filter" name="role" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Roles</option>
                                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="teacher" <?php echo $role_filter == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                        <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username, name or email" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                </div>
                                
                                <div class="flex items-end">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-filter mr-2"></i> Filter
                                    </button>
                                    <a href="users.php" class="ml-2 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-times mr-2"></i> Clear
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Users Table -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                                <h2 class="text-lg font-medium text-gray-900">User List</h2>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                                                                <?php if (isset($user['profile_image']) && $user['profile_image']): ?>
                                                                    <img class="h-10 w-10 rounded-full" src="<?php echo $user['profile_image']; ?>" alt="Profile image">
                                                                <?php else: ?>
                                                                    <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-gray-500">
                                                                        <span class="text-sm font-medium leading-none text-white"><?php echo substr($user['full_name'], 0, 1); ?></span>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="ml-4">
                                                                <div class="text-sm font-medium text-gray-900"><?php echo $user['full_name']; ?></div>
                                                                <div class="text-sm text-gray-500"><?php echo $user['phone'] ? $user['phone'] : 'No phone'; ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php 
                                                        if ($user['role'] == 'student' && isset($user['student_id'])) {
                                                            echo $user['student_id'];
                                                        } elseif ($user['role'] == 'teacher' && isset($user['teacher_id'])) {
                                                            echo $user['teacher_id'];
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $user['email']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            <?php 
                                                            if ($user['role'] == 'admin') echo 'bg-purple-100 text-purple-800';
                                                            elseif ($user['role'] == 'teacher') echo 'bg-green-100 text-green-800';
                                                            else echo 'bg-yellow-100 text-yellow-800';
                                                            ?>">
                                                            <?php echo ucfirst($user['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            <?php echo $user['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                            <?php echo ucfirst($user['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <a href="edit_user.php?user_id=<?php echo $user['user_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="users.php?toggle_status=1&user_id=<?php echo $user['user_id']; ?>&current_status=<?php echo $user['status']; ?>" class="text-yellow-600 hover:text-yellow-900 mr-3" title="Toggle status">
                                                            <i class="fas fa-toggle-<?php echo $user['status'] == 'active' ? 'on' : 'off'; ?>"></i>
                                                        </a>
                                                        <?php if ($user['user_id'] != $_SESSION['user_id'] && $user['role'] != 'admin'): ?>
                                                        <a href="users.php?delete=1&user_id=<?php echo $user['user_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this user?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
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

    <script>
        // Toggle role-specific fields
        document.getElementById('role').addEventListener('change', function() {
            const studentFields = document.getElementById('student_fields');
            const teacherFields = document.getElementById('teacher_fields');
            
            if (this.value === 'student') {
                studentFields.classList.remove('hidden');
                teacherFields.classList.add('hidden');
            } else if (this.value === 'teacher') {
                teacherFields.classList.remove('hidden');
                studentFields.classList.add('hidden');
            } else {
                studentFields.classList.add('hidden');
                teacherFields.classList.add('hidden');
            }
        });
        
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
        
        // User menu toggle
        document.getElementById('user-menu-button').addEventListener('click', function() {
            document.getElementById('user-menu').classList.toggle('hidden');
        });
        
        // Close user menu when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('user-menu');
            const userMenuButton = document.getElementById('user-menu-button');
            
            if (userMenu && userMenuButton && !userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });
    </script>
    
</body>
</html>
