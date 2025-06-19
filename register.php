<?php
// Start session at the very beginning
session_start();

// Only redirect non-admin users if they're already logged in
// Admins can access registration to create accounts for others
if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    if ($role == 'teacher') {
        header("Location: Teacher/teacher_dashboard.php");
    } elseif ($role == 'student') {
        header("Location: Student/student_dashboard.php");
    }
    exit();
}

// Connect to database to get classes and other data
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get classes for student registration
$classes = [];
$result = $conn->query("SELECT class_id, class_name, section, academic_year FROM Classes ORDER BY class_name, section");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Get departments for teacher registration
$departments = [
    'Mathematics', 'Science', 'English', 'Social Studies', 'Computer Science',
    'Physical Education', 'Arts', 'Music', 'Languages', 'Other'
];

// Get batch years (current year and previous 5 years)
$current_year = date('Y');
$batch_years = [];
for ($i = 0; $i <= 5; $i++) {
    $batch_years[] = $current_year - $i;
}

$conn->close();


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Result Management System</title>
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
        
        .register-card {
            box-shadow: 0 10px 25px rgba(0, 0, 40, 0.1);
        }
        
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        
        .role-tab {
            transition: all 0.3s ease;
        }
        
        .role-tab.active {
            background-color: #1e40af;
            color: white;
        }
        
        .role-tab:hover:not(.active) {
            background-color: #e2e8f0;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center py-8">
    <div class="register-card bg-white rounded-xl p-8 w-full max-w-2xl mx-4 my-8">
        <!-- Logo/Header -->
        <div class="text-center mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-blue-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <h1 class="text-2xl font-bold text-blue-900 mt-3">Result Management System</h1>
            <p class="text-slate-500 font-light">Create a new account</p>
        </div>
        
        <!-- Error/Success Messages -->
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
        
        <!-- Role Selection Tabs -->
        <div class="flex mb-6 border rounded-lg overflow-hidden">
            <button type="button" class="role-tab flex-1 py-3 px-4 text-center font-medium active" data-role="student">
                <i class="fas fa-user-graduate mr-2"></i>Student
            </button>
            <button type="button" class="role-tab flex-1 py-3 px-4 text-center font-medium" data-role="teacher">
                <i class="fas fa-chalkboard-teacher mr-2"></i>Teacher
            </button>
            <!-- <button type="button" class="role-tab flex-1 py-3 px-4 text-center font-medium" data-role="admin">
                <i class="fas fa-user-shield mr-2"></i>Admin
            </button> -->
        </div>
        
        <!-- Registration Form -->
        <form action="php/register_process.php" method="POST" class="space-y-4" id="registrationForm">
            <!-- Hidden role field that will be set by the tabs -->
            <input type="hidden" id="role" name="role" value="student">
            
            <!-- Common Fields Section -->
            <div class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-slate-700 required-field">Full Name</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-slate-400"></i>
                            </div>
                            <input type="text" id="full_name" name="full_name" required 
                                class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                        </div>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 required-field">Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-slate-400"></i>
                            </div>
                            <input type="email" id="email" name="email" required 
                                class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700 required-field">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user-tag text-slate-400"></i>
                        </div>
                        <input type="text" id="username" name="username" required 
                            class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 required-field">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-slate-400"></i>
                            </div>
                            <input type="password" id="password" name="password" required minlength="6" 
                                class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Password must be at least 6 characters long</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-slate-700 required-field">Confirm Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-slate-400"></i>
                            </div>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" 
                                class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Role-specific fields -->
            <div id="role-specific-fields" class="mt-6">
                <!-- Student-specific fields -->
                <div id="student-fields" class="space-y-3">
                    <h3 class="text-md font-medium text-blue-900 mb-2 border-b pb-2">Student Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="roll_number" class="block text-sm font-medium text-slate-700 required-field">Roll Number</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-id-card text-slate-400"></i>
                                </div>
                                <input type="text" id="roll_number" name="roll_number" required
                                    class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                            </div>
                        </div>
                        
                        <div>
                            <label for="batch_year" class="block text-sm font-medium text-slate-700 required-field">Batch Year</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-calendar-alt text-slate-400"></i>
                                </div>
                                <select id="batch_year" name="batch_year" required
                                    class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition appearance-none">
                                    <option value="">Select Batch Year</option>
                                    <?php foreach ($batch_years as $year): ?>
                                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="class_id" class="block text-sm font-medium text-slate-700 required-field">Class</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-chalkboard text-slate-400"></i>
                            </div>
                            <select id="class_id" name="class_id" required
                                class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition appearance-none">
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>">
                                        <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                <svg class="w-5 h-5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                
                <!-- Teacher-specific fields -->
                <div id="teacher-fields" class="space-y-3 hidden">
                    <h3 class="text-md font-medium text-blue-900 mb-2 border-b pb-2">Teacher Information</h3>
                    
                    <div>
                        <label for="employee_id" class="block text-sm font-medium text-slate-700 required-field">Employee ID</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-id-badge text-slate-400"></i>
                            </div>
                            <input type="text" id="employee_id" name="employee_id" 
                                class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="department" class="block text-sm font-medium text-slate-700 required-field">Department</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-building text-slate-400"></i>
                                </div>
                                <select id="department" name="department" 
                                    class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition appearance-none">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="qualification" class="block text-sm font-medium text-slate-700 required-field">Qualification</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-graduation-cap text-slate-400"></i>
                                </div>
                                <input type="text" id="qualification" name="qualification" 
                                    class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Admin-specific fields -->
                <div id="admin-fields" class="space-y-3 hidden">
                    <h3 class="text-md font-medium text-blue-900 mb-2 border-b pb-2">Administrator Information</h3>
                    
                    <div>
                        <label for="admin_code" class="block text-sm font-medium text-slate-700 required-field">Admin Registration Code</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-key text-slate-400"></i>
                            </div>
                            <input type="password" id="admin_code" name="admin_code" 
                                class="pl-10 w-full py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-transparent transition">
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Required for administrator registration (Default: ADMIN123)</p>
                    </div>
                </div>
            </div>
            
            <div class="pt-2">
                <button type="submit" id="submitBtn"
                    class="w-full py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-900 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-900 transition duration-150">
                    Create Account
                </button>
            </div>
        </form>
        
        <!-- Footer -->
        <div class="mt-6 text-center">
            <p class="text-sm text-slate-500">
                Already have an account? <a href="login.php" class="font-medium text-blue-900 hover:text-blue-800 transition">Login here</a>
            </p>
        </div>
    </div>

    <script>
        // Function to toggle role-specific fields
        function toggleRoleFields(role) {
            const studentFields = document.getElementById('student-fields');
            const teacherFields = document.getElementById('teacher-fields');
            const adminFields = document.getElementById('admin-fields');
            const roleInput = document.getElementById('role');
            
            // Update hidden role input
            roleInput.value = role;
            
            // Hide all role-specific fields first
            studentFields.classList.add('hidden');
            teacherFields.classList.add('hidden');
            adminFields.classList.add('hidden');
            
            // Show fields based on selected role
            if (role === 'student') {
                studentFields.classList.remove('hidden');
                // Make student fields required
                document.getElementById('roll_number').required = true;
                document.getElementById('class_id').required = true;
                document.getElementById('batch_year').required = true;
                // Make other fields not required
                if (document.getElementById('employee_id')) document.getElementById('employee_id').required = false;
                if (document.getElementById('department')) document.getElementById('department').required = false;
                if (document.getElementById('qualification')) document.getElementById('qualification').required = false;
                if (document.getElementById('admin_code')) document.getElementById('admin_code').required = false;
            } else if (role === 'teacher') {
                teacherFields.classList.remove('hidden');
                // Make teacher fields required
                document.getElementById('employee_id').required = true;
                document.getElementById('department').required = true;
                document.getElementById('qualification').required = true;
                // Make other fields not required
                if (document.getElementById('roll_number')) document.getElementById('roll_number').required = false;
                if (document.getElementById('class_id')) document.getElementById('class_id').required = false;
                if (document.getElementById('batch_year')) document.getElementById('batch_year').required = false;
                if (document.getElementById('admin_code')) document.getElementById('admin_code').required = false;
            } else if (role === 'admin') {
                adminFields.classList.remove('hidden');
                // Make admin fields required
                document.getElementById('admin_code').required = true;
                // Make other fields not required
                if (document.getElementById('roll_number')) document.getElementById('roll_number').required = false;
                if (document.getElementById('class_id')) document.getElementById('class_id').required = false;
                if (document.getElementById('batch_year')) document.getElementById('batch_year').required = false;
                if (document.getElementById('employee_id')) document.getElementById('employee_id').required = false;
                if (document.getElementById('department')) document.getElementById('department').required = false;
                if (document.getElementById('qualification')) document.getElementById('qualification').required = false;
            }
            
            // Update tab styling
            document.querySelectorAll('.role-tab').forEach(tab => {
                if (tab.getAttribute('data-role') === role) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }
        
        // Password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set up role tab click handlers
            document.querySelectorAll('.role-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const role = this.getAttribute('data-role');
                    toggleRoleFields(role);
                });
            });
            
            // Initialize with student role selected
            toggleRoleFields('student');
            
            // Form validation
            const form = document.getElementById('registrationForm');
            form.addEventListener('submit', function(event) {
                const role = document.getElementById('role').value;
                
                if (role === 'student') {
                    const rollNumber = document.getElementById('roll_number').value;
                    const classId = document.getElementById('class_id').value;
                    const batchYear = document.getElementById('batch_year').value;
                    
                    if (!rollNumber || !classId || !batchYear) {
                        event.preventDefault();
                        alert('Please fill in all required student fields');
                    }
                } else if (role === 'teacher') {
                    const employeeId = document.getElementById('employee_id').value;
                    const department = document.getElementById('department').value;
                    const qualification = document.getElementById('qualification').value;
                    
                    if (!employeeId || !department || !qualification) {
                        event.preventDefault();
                        alert('Please fill in all required teacher fields');
                    }
                } else if (role === 'admin') {
                    const adminCode = document.getElementById('admin_code').value;
                    
                    if (!adminCode) {
                        event.preventDefault();
                        alert('Please enter the admin registration code');
                    }
                }
                
                // Check if passwords match
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    event.preventDefault();
                    alert('Passwords do not match');
                }
            });
        });
    
// Add click handler for register link in login page
document.addEventListener('DOMContentLoaded', function() {
    // Existing code...
    
    // Add form validation feedback
    const form = document.getElementById('registrationForm');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('submit', function(event) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Account...';
        
        // Re-enable button after 5 seconds in case of issues
        setTimeout(function() {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
        }, 5000);
    });
});
    </script>
</body>
</html>
