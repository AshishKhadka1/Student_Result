<?php
session_start();
// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: {$role}_dashboard.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    // Basic validation
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "Username already exists";
    }
    $stmt->close();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    $stmt->close();
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $username, $hashed_password, $full_name, $email, $role);
            $stmt->execute();
            $user_id = $conn->insert_id;
            $stmt->close();
            
            // Role-specific data
            if ($role == 'student') {
                // Get role-specific data
                $roll_number = $_POST['roll_number'] ?? '';
                $registration_number = $_POST['registration_number'] ?? '';
                $class_id = $_POST['class_id'] ?? null;
                $batch_year = date('Y');
                
                // Generate student ID if not provided
                $student_id = 'S' . str_pad($user_id, 3, '0', STR_PAD_LEFT);
                
                // Insert student data
                $stmt = $conn->prepare("INSERT INTO students (student_id, user_id, roll_number, registration_number, class_id, batch_year, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sisssi", $student_id, $user_id, $roll_number, $registration_number, $class_id, $batch_year);
                $stmt->execute();
                $stmt->close();
            } elseif ($role == 'teacher') {
                // Get role-specific data
                $employee_id = $_POST['employee_id'] ?? 'T' . str_pad($user_id, 3, '0', STR_PAD_LEFT);
                $department = $_POST['department'] ?? '';
                $qualification = $_POST['qualification'] ?? '';
                
                // Insert teacher data
                $stmt = $conn->prepare("INSERT INTO teachers (user_id, employee_id, department, qualification, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("isss", $user_id, $employee_id, $department, $qualification);
                $stmt->execute();
                $stmt->close();
            }
            
            // Create welcome notification
            $title = "Welcome to Result Management System";
            $message = "Thank you for registering. Your account has been created successfully.";
            $notification_type = "system";
            
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isss", $user_id, $title, $message, $notification_type);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success = true;
            
            // Auto login after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['full_name'] = $full_name;
            
            // Redirect to appropriate dashboard
            header("Location: {$role}_dashboard.php");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}

// Get classes for student registration
$classes = [];
$result = $conn->query("SELECT class_id, class_name, section, academic_year FROM classes ORDER BY class_name, section");
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-blue-600 py-4 px-6">
            <h2 class="text-2xl font-bold text-white text-center">Result Management System</h2>
        </div>
        
        <div class="p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4 text-center">Create an Account</h3>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <ul class="list-disc list-inside text-sm text-red-700">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">Registration successful! Redirecting to login...</p>
                        </div>
                    </div>
                </div>
                <script>
                    setTimeout(function() {
                        window.location.href = "login.php";
                    }, 2000);
                </script>
            <?php endif; ?>
            
            <form action="register.php" method="POST" class="space-y-4">
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-500">Password must be at least 6 characters long</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Register as</label>
                    <select id="role" name="role" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" onchange="toggleRoleFields()">
                        <option value="">Select Role</option>
                        <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : ''; ?>>Student</option>
                        <option value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                    </select>
                </div>
                
                <!-- Student-specific fields -->
                <div id="student-fields" class="space-y-4 hidden">
                    <div>
                        <label for="roll_number" class="block text-sm font-medium text-gray-700 mb-1">Roll Number</label>
                        <input type="text" id="roll_number" name="roll_number" value="<?php echo isset($_POST['roll_number']) ? htmlspecialchars($_POST['roll_number']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="registration_number" class="block text-sm font-medium text-gray-700 mb-1">Registration Number</label>
                        <input type="text" id="registration_number" name="registration_number" value="<?php echo isset($_POST['registration_number']) ? htmlspecialchars($_POST['registration_number']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select id="class_id" name="class_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                                    <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Teacher-specific fields -->
                <div id="teacher-fields" class="space-y-4 hidden">
                    <div>
                        <label for="employee_id" class="block text-sm font-medium text-gray-700 mb-1">Employee ID</label>
                        <input type="text" id="employee_id" name="employee_id" value="<?php echo isset($_POST['employee_id']) ? htmlspecialchars($_POST['employee_id']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select id="department" name="department" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Department</option>
                            <option value="Mathematics" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Mathematics') ? 'selected' : ''; ?>>Mathematics</option>
                            <option value="Science" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Science') ? 'selected' : ''; ?>>Science</option>
                            <option value="English" <?php echo (isset($_POST['department']) && $_POST['department'] == 'English') ? 'selected' : ''; ?>>English</option>
                            <option value="Social Studies" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Social Studies') ? 'selected' : ''; ?>>Social Studies</option>
                            <option value="Computer Science" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="qualification" class="block text-sm font-medium text-gray-700 mb-1">Qualification</label>
                        <input type="text" id="qualification" name="qualification" value="<?php echo isset($_POST['qualification']) ? htmlspecialchars($_POST['qualification']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div>
                    <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Register
                    </button>
                </div>
            </form>
            
            <div class="mt-4 text-center">
                <p class="text-sm text-gray-600">
                    Already have an account? <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">Login here</a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            const studentFields = document.getElementById('student-fields');
            const teacherFields = document.getElementById('teacher-fields');
            
            if (role === 'student') {
                studentFields.classList.remove('hidden');
                teacherFields.classList.add('hidden');
            } else if (role === 'teacher') {
                studentFields.classList.add('hidden');
                teacherFields.classList.remove('hidden');
            } else {
                studentFields.classList.add('hidden');
                teacherFields.classList.add('hidden');
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleRoleFields();
        });
    </script>
</body>
</html>