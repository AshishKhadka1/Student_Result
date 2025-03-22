<?php
// Start session at the very beginning
session_start();

// Check if user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role == 'student') {
        header("Location: student_dashboard.php");
        exit();
    } elseif ($role == 'teacher') {
        header("Location: teacher_dashboard.php");
        exit();
    } elseif ($role == 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    }
}

// Get available classes for student registration
$classes = [];
try {
    $conn = new mysqli('localhost', 'root', '', 'result_management');
    if (!$conn->connect_error) {
        $query = "SELECT class_id, class_name, section, academic_year FROM Classes ORDER BY class_name, section";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $classes[] = $row;
            }
        }
        $conn->close();
    }
} catch (Exception $e) {
    // Silently handle the error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Result Management System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #f0f4f8;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            font-weight: 300;
        }
        
        .register-card {
            box-shadow: 0 10px 25px rgba(0, 0, 40, 0.1);
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center py-12">
    <div class="register-card bg-white rounded-xl p-8 w-full max-w-md mx-4">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-blue-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <h1 class="text-2xl font-bold text-blue-900 mt-3">Result Management System</h1>
            <p class="text-slate-500 font-light">Create a new account</p>
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
        <?php endif; ?>

        <!-- Success Message Display -->
        <?php if(isset($_SESSION['success'])): ?>
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
        <?php endif; ?>

        <!-- Registration Form -->
        <form action="php/register_process.php" method="POST" class="space-y-4">
            <div>
                <label for="full_name" class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                <input type="text" id="full_name" name="full_name" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
            </div>

            <div>
                <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                <input type="text" id="username" name="username" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" id="email" name="email" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" id="password" name="password" required minlength="6" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                <p class="text-xs text-slate-500 mt-1">Password must be at least 6 characters long</p>
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
            </div>

            <div>
                <label for="role" class="block text-sm font-medium text-slate-700 mb-1">Register as</label>
                <select id="role" name="role" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition" onchange="toggleAdditionalFields()">
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <!-- Student-specific fields -->
            <div id="student-fields" class="space-y-4">
                <div>
                    <label for="roll_number" class="block text-sm font-medium text-slate-700 mb-1">Roll Number</label>
                    <input type="text" id="roll_number" name="roll_number" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                </div>

                <div>
                    <label for="class_id" class="block text-sm font-medium text-slate-700 mb-1">Class</label>
                    <select id="class_id" name="class_id" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section'] . ' (' . $class['academic_year'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="batch_year" class="block text-sm font-medium text-slate-700 mb-1">Batch Year</label>
                    <select id="batch_year" name="batch_year" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                        <?php 
                        $currentYear = date('Y');
                        for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
                            echo "<option value=\"$year\">$year</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Teacher-specific fields -->
            <div id="teacher-fields" class="space-y-4 hidden">
                <div>
                    <label for="employee_id" class="block text-sm font-medium text-slate-700 mb-1">Employee ID</label>
                    <input type="text" id="employee_id" name="employee_id" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                </div>

                <div>
                    <label for="department" class="block text-sm font-medium text-slate-700 mb-1">Department</label>
                    <select id="department" name="department" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                        <option value="Mathematics">Mathematics</option>
                        <option value="Science">Science</option>
                        <option value="English">English</option>
                        <option value="Social Studies">Social Studies</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Physical Education">Physical Education</option>
                        <option value="Arts">Arts</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label for="qualification" class="block text-sm font-medium text-slate-700 mb-1">Qualification</label>
                    <select id="qualification" name="qualification" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                        <option value="Bachelors">Bachelors</option>
                        <option value="Masters">Masters</option>
                        <option value="Ph.D.">Ph.D.</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <!-- Admin-specific fields -->
            <div id="admin-fields" class="space-y-4 hidden">
                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                    <div class="flex">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Admin registration requires approval. Your account will be pending until approved by an existing administrator.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="admin_code" class="block text-sm font-medium text-slate-700 mb-1">Admin Registration Code</label>
                    <input type="password" id="admin_code" name="admin_code" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                    <p class="text-xs text-slate-500 mt-1">Enter the admin registration code provided by your institution</p>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-900 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-900 transition duration-150">
                    Register
                </button>
            </div>
        </form>

        <!-- Footer -->
        <div class="mt-6 text-center text-sm text-slate-500">
            <p>Already have an account? <a href="index.php" class="font-medium text-blue-900 hover:text-blue-800 transition">Sign in</a></p>
        </div>
    </div>

    <script>
        function toggleAdditionalFields() {
            const role = document.getElementById('role').value;
            
            // Hide all role-specific fields first
            document.getElementById('student-fields').classList.add('hidden');
            document.getElementById('teacher-fields').classList.add('hidden');
            document.getElementById('admin-fields').classList.add('hidden');
            
            // Show fields based on selected role
            if (role === 'student') {
                document.getElementById('student-fields').classList.remove('hidden');
            } else if (role === 'teacher') {
                document.getElementById('teacher-fields').classList.remove('hidden');
            } else if (role === 'admin') {
                document.getElementById('admin-fields').classList.remove('hidden');
            }
        }
        
        // Initialize fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleAdditionalFields();
        });
    </script>
</body>
</html>

