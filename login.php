<?php
// Start session at the very beginning
session_start();

// Check for remember-me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_user']) && isset($_COOKIE['remember_role'])) {
    // Connect to database
    $conn = new mysqli('localhost', 'root', '', 'result_management');
    if (!$conn->connect_error) {
        $username = $conn->real_escape_string($_COOKIE['remember_user']);
        $role = $conn->real_escape_string($_COOKIE['remember_role']);
        
        $stmt = $conn->prepare("SELECT * FROM Users WHERE username=? AND role=?");
        $stmt->bind_param("ss", $username, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            
            // Redirect based on role
            if ($role == 'student') {
                header("Location: Student\student_dashboard.php");
                exit();
            } elseif ($role == 'teacher') {
                header("Location: Teacher\teacher_dashboard.php");
                exit();
            } elseif ($role == 'admin') {
                header("Location: Admin\admin_dashboard.php");
                exit();
            }
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Result Management System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f4f8;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            font-weight: 300;
        }
        
        .login-card {
            box-shadow: 0 10px 25px rgba(0, 0, 40, 0.1);
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
    <div class="login-card bg-white rounded-xl p-8 w-full max-w-md mx-4">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-blue-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <h1 class="text-2xl font-bold text-blue-900 mt-3">Result Management System</h1>
            <p class="text-slate-500 font-light">Sign in to your account</p>
        </div>

        <!-- Error Message Display -->
        <?php if(isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <div class="flex">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-red-700">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php elseif(isset($_SESSION['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
            <div class="flex">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-green-700">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Welcome Message -->
        <div class="bg-blue-50 border-l-4 border-blue-900 p-4 mb-6 rounded">
            <div class="flex">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-900" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-blue-900">
                        Welcome back! Please login to access your dashboard.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="php/login_process.php" method="POST" class="space-y-5">
            <div>
                <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-slate-400"></i>
                    </div>
                    <input type="text" id="username" name="username" required class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-slate-400"></i>
                    </div>
                    <input type="password" id="password" name="password" required class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                </div>
            </div>

            <div>
                <label for="role" class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user-tag text-slate-400"></i>
                    </div>
                    <select id="role" name="role" required class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition appearance-none bg-none">
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="admin">Admin</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                        <svg class="w-5 h-5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-blue-900 focus:ring-blue-900 border-slate-300 rounded">
                    <label for="remember-me" class="ml-2 block text-sm text-slate-700">
                        Remember me
                    </label>
                </div>
                <div class="text-sm">
                    <a href="forgot_password.php" class="font-medium text-blue-900 hover:text-blue-800 transition">
                        Forgot password?
                    </a>
                </div>
            </div>

            <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-900 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-900 transition duration-150">
                Login
            </button>
        </form>

        <!-- Footer -->
        <div class="mt-6 text-center text-sm text-slate-500">
            <p>Need an account? <a href="register.php" class="font-medium text-blue-900 hover:text-blue-800 transition">Register here</a></p>
        </div>
    </div>
</body>
</html>