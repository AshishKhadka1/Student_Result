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

// Get classes for dropdown
$classes = [];
$result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name, section");
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    // Get form data
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Additional fields based on role
    $class_id = $_POST['class_id'] ?? null;
    $roll_number = $_POST['roll_number'] ?? '';
    $registration_number = $_POST['registration_number'] ?? '';
    $teacher_id = $_POST['teacher_id'] ?? '';
    $department = $_POST['department'] ?? '';
    
    // Validate required fields
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required.";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Username already exists.";
        }
        $stmt->close();
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists.";
        }
        $stmt->close();
    }
    
    if (empty($role)) {
        $errors[] = "Role is required.";
    }
    
    // Role-specific validations
    if ($role == 'student') {
        if (empty($class_id)) {
            $errors[] = "Class is required for students.";
        }
        
        if (empty($roll_number)) {
            $errors[] = "Roll number is required for students.";
        } else {
            // Check if roll number already exists in the same class
            $stmt = $conn->prepare("SELECT s.student_id FROM students s WHERE s.roll_number = ? AND s.class_id = ?");
            $stmt->bind_param("si", $roll_number, $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "Roll number already exists in this class.";
            }
            $stmt->close();
        }
    } elseif ($role == 'teacher') {
        if (empty($teacher_id)) {
            $errors[] = "Teacher ID is required for teachers.";
        } else {
            // Check if teacher ID already exists
            $stmt = $conn->prepare("SELECT t.teacher_id FROM teachers t WHERE t.teacher_id = ?");
            $stmt->bind_param("s", $teacher_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "Teacher ID already exists.";
            }
            $stmt->close();
        }
    }
    
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ssssss", $username, $hashed_password, $full_name, $email, $role, $status);
            $stmt->execute();
            $user_id = $conn->insert_id;
            $stmt->close();
            
            // Insert role-specific data
            if ($role == 'student') {
                // Generate student ID if not provided
                $student_id = 'S' . str_pad($user_id, 3, '0', STR_PAD_LEFT);
                
                $stmt = $conn->prepare("INSERT INTO students (student_id, user_id, class_id, roll_number, registration_number, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("siiss", $student_id, $user_id, $class_id, $roll_number, $registration_number);
                $stmt->execute();
                $stmt->close();
            } elseif ($role == 'teacher') {
                $stmt = $conn->prepare("INSERT INTO teachers (teacher_id, user_id, department, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("sis", $teacher_id, $user_id, $department);
                $stmt->execute();
                $stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "User added successfully!";
            
            // Clear form data
            $username = $full_name = $email = $roll_number = $registration_number = $teacher_id = $department = '';
            $role = $status = 'active';
            $class_id = null;
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = "Error adding user: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
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
                            <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-md">
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
                            <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
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
                            <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
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
            <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Add New User</h1>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
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

                        <!-- User Form -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Add New User</h3>
                                <p class="mt-1 text-sm text-gray-500">Fill in the details to create a new user account.</p>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <form action="add_user.php" method="POST">
                                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                        <!-- Basic Information -->
                                        <div class="sm:col-span-3">
                                            <label for="username" class="block text-sm font-medium text-gray-700">Username *</label>
                                            <div class="mt-1">
                                                <input type="text" name="username" id="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                        </div>

                                        <div class="sm:col-span-3">
                                            <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name *</label>
                                            <div class="mt-1">
                                                <input type="text" name="full_name" id="full_name" value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                        </div>

                                        <div class="sm:col-span-3">
                                            <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                                            <div class="mt-1">
                                                <input type="email" name="email" id="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                        </div>

                                        <div class="sm:col-span-3">
                                            <label for="role" class="block text-sm font-medium text-gray-700">Role *</label>
                                            <div class="mt-1">
                                                <select id="role" name="role" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                    <option value="">-- Select Role --</option>
                                                    <option value="admin" <?php echo isset($role) && $role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                    <option value="teacher" <?php echo isset($role) && $role == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                                    <option value="student" <?php echo isset($role) && $role == 'student' ? 'selected' : ''; ?>>Student</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="sm:col-span-3">
                                            <label for="password" class="block text-sm font-medium text-gray-700">Password *</label>
                                            <div class="mt-1">
                                                <input type="password" name="password" id="password" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">Minimum 6 characters</p>
                                        </div>

                                        <div class="sm:col-span-3">
                                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password *</label>
                                            <div class="mt-1">
                                                <input type="password" name="confirm_password" id="confirm_password" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                        </div>

                                        <div class="sm:col-span-3">
                                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                            <div class="mt-1">
                                                <select id="status" name="status" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                    <option value="active" <?php echo isset($status) && $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo isset($status) && $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Student-specific fields -->
                                        <div id="student-fields" class="sm:col-span-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6 hidden">
                                            <div class="sm:col-span-6">
                                                <h3 class="text-lg font-medium text-gray-900 mb-3">Student Information</h3>
                                            </div>
                                            
                                            <div class="sm:col-span-3">
                                                <label for="class_id" class="block text-sm font-medium text-gray-700">Class *</label>
                                                <div class="mt-1">
                                                    <select id="class_id" name="class_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                        <option value="">-- Select Class --</option>
                                                        <?php foreach ($classes as $class): ?>
                                                            <option value="<?php echo $class['class_id']; ?>" <?php echo isset($class_id) && $class_id == $class['class_id'] ? 'selected' : ''; ?>>
                                                                <?php echo $class['class_name'] . ' ' . $class['section']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="sm:col-span-3">
                                                <label for="roll_number" class="block text-sm font-medium text-gray-700">Roll Number *</label>
                                                <div class="mt-1">
                                                    <input type="text" name="roll_number" id="roll_number" value="<?php echo isset($roll_number) ? htmlspecialchars($roll_number) : ''; ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                </div>
                                            </div>
                                            
                                            <div class="sm:col-span-3">
                                                <label for="registration_number" class="block text-sm font-medium text-gray-700">Registration Number</label>
                                                <div class="mt-1">
                                                    <input type="text" name="registration_number" id="registration_number" value="<?php echo isset($registration_number) ? htmlspecialchars($registration_number) : ''; ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Teacher-specific fields -->
                                        <div id="teacher-fields" class="sm:col-span-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6 hidden">
                                            <div class="sm:col-span-6">
                                                <h3 class="text-lg font-medium text-gray-900 mb-3">Teacher Information</h3>
                                            </div>
                                            
                                            <div class="sm:col-span-3">
                                                <label for="teacher_id" class="block text-sm font-medium text-gray-700">Teacher ID *</label>
                                                <div class="mt-1">
                                                    <input type="text" name="teacher_id" id="teacher_id" value="<?php echo isset($teacher_id) ? htmlspecialchars($teacher_id) : ''; ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                </div>
                                            </div>
                                            
                                            <div class="sm:col-span-3">
                                                <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                                                <div class="mt-1">
                                                    <input type="text" name="department" id="department" value="<?php echo isset($department) ? htmlspecialchars($department) : ''; ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-6 flex justify-end space-x-3">
                                        <a href="users.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Cancel
                                        </a>
                                        <button type="submit" name="add_user" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Add User
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Show/hide role-specific fields
        document.getElementById('role').addEventListener('change', function() {
            const role = this.value;
            const studentFields = document.getElementById('student-fields');
            const teacherFields = document.getElementById('teacher-fields');
            
            // Hide all role-specific fields
            studentFields.classList.add('hidden');
            teacherFields.classList.add('hidden');
            
            // Show fields based on selected role
            if (role === 'student') {
                studentFields.classList.remove('hidden');
                
                // Make student-specific fields required
                document.getElementById('class_id').required = true;
                document.getElementById('roll_number').required = true;
                
                // Make teacher-specific fields not required
                document.getElementById('teacher_id').required = false;
            } else if (role === 'teacher') {
                teacherFields.classList.remove('hidden');
                
                // Make teacher-specific fields required
                document.getElementById('teacher_id').required = true;
                
                // Make student-specific fields not required
                document.getElementById('class_id').required = false;
                document.getElementById('roll_number').required = false;
            } else {
                // Make all role-specific fields not required
                document.getElementById('class_id').required = false;
                document.getElementById('roll_number').required = false;
                document.getElementById('teacher_id').required = false;
            }
        });
        
        // Trigger change event to initialize form based on selected role
        document.getElementById('role').dispatchEvent(new Event('change'));
        
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            sidebar.classList.toggle('hidden');
        });
    </script>
</body>
</html>