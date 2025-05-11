<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Check if teacher ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
            <p class="font-bold">Error</p>
            <p>Teacher ID is required.</p>
          </div>';
    exit();
}

$teacher_id = $_GET['id'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get teacher details with user information
$query = "SELECT t.*, u.full_name, u.email, u.phone, u.status, u.created_at as user_created_at
          FROM teachers t 
          JOIN users u ON t.user_id = u.user_id 
          WHERE t.teacher_id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
            <p class="font-bold">Database Error</p>
            <p>Failed to prepare statement: ' . $conn->error . '</p>
          </div>';
    exit();
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
            <p class="font-bold">Error</p>
            <p>Teacher not found.</p>
          </div>';
    exit();
}

$teacher = $result->fetch_assoc();
$stmt->close();

// Check if teachersubjects table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'teachersubjects'");
$subjects = [];

if ($table_exists && $table_exists->num_rows > 0) {
    // Get teacher's subjects
    $subjects_query = "SELECT ts.*, s.subject_name, s.subject_code, c.class_name, c.section 
                      FROM teachersubjects ts 
                      JOIN subjects s ON ts.subject_id = s.subject_id 
                      JOIN classes c ON ts.class_id = c.class_id 
                      WHERE ts.teacher_id = ? AND ts.is_active = 1";
    $subjects_stmt = $conn->prepare($subjects_query);
    if ($subjects_stmt) {
        $subjects_stmt->bind_param("i", $teacher_id);
        $subjects_stmt->execute();
        $subjects_result = $subjects_stmt->get_result();
        
        while ($subject = $subjects_result->fetch_assoc()) {
            $subjects[] = $subject;
        }
        $subjects_stmt->close();
    }
}

// Check if activities table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'activities'");
$activities = [];

if ($table_exists && $table_exists->num_rows > 0) {
    // Get teacher's recent activities
    $activities_query = "SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
    $activities_stmt = $conn->prepare($activities_query);
    if ($activities_stmt) {
        $user_id = $teacher['user_id'];
        $activities_stmt->bind_param("i", $user_id);
        $activities_stmt->execute();
        $activities_result = $activities_stmt->get_result();
        
        while ($activity = $activities_result->fetch_assoc()) {
            $activities[] = $activity;
        }
        $activities_stmt->close();
    }
}

$conn->close();
?>

<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
        <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900">Teacher Information</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Personal and professional details.</p>
        </div>
        <div class="flex space-x-2">
            <button onclick="showEditModal('<?php echo $teacher_id; ?>')" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-edit mr-1"></i> Edit
            </button>
            <button onclick="showSubjectsModal('<?php echo $teacher_id; ?>')" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <i class="fas fa-book mr-1"></i> Subjects
            </button>
            <button onclick="printProfile()" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                <i class="fas fa-print mr-1"></i> Print
            </button>
        </div>
    </div>
    
    <div class="border-t border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4">
            <!-- Left Column: Basic Info -->
            <div class="md:col-span-1 bg-gray-50 p-4 rounded-lg">
                <div class="flex flex-col items-center">
                    <div class="w-32 h-32 bg-gray-300 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-user-tie text-gray-600 text-5xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($teacher['full_name']); ?></h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($teacher['department']); ?></p>
                    <div class="mt-2">
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
                    </div>
                </div>
                
                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Contact Information</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-envelope text-gray-500"></i>
                            </div>
                            <div class="ml-3 text-sm text-gray-700">
                                <?php echo htmlspecialchars($teacher['email']); ?>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-phone text-gray-500"></i>
                            </div>
                            <div class="ml-3 text-sm text-gray-700">
                                <?php echo htmlspecialchars($teacher['phone'] ?? 'Not provided'); ?>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-id-card text-gray-500"></i>
                            </div>
                            <div class="ml-3 text-sm text-gray-700">
                                Employee ID: <?php echo htmlspecialchars($teacher['employee_id']); ?>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Middle Column: Professional Details -->
            <div class="md:col-span-1 bg-white p-4 rounded-lg border border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Professional Details</h3>
                
                <div class="space-y-4">
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Department</h4>
                        <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($teacher['department']); ?></p>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Qualification</h4>
                        <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($teacher['qualification'] ?? 'Not specified'); ?></p>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Joining Date</h4>
                        <p class="mt-1 text-sm text-gray-900">
                            <?php 
                                echo !empty($teacher['joining_date']) 
                                    ? date('F j, Y', strtotime($teacher['joining_date'])) 
                                    : 'Not specified'; 
                            ?>
                        </p>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Experience</h4>
                        <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($teacher['experience'] ?? 'Not specified'); ?></p>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Specialization</h4>
                        <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($teacher['specialization'] ?? 'Not specified'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Subjects and Activities -->
            <div class="md:col-span-1 space-y-6">
                <!-- Assigned Subjects -->
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Assigned Subjects</h3>
                    
                    <?php if (empty($subjects)): ?>
                        <p class="text-sm text-gray-500">No subjects assigned yet.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-gray-200">
                            <?php foreach ($subjects as $subject): ?>
                                <li class="py-2">
                                    <div class="flex justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($subject['subject_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($subject['subject_code']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($subject['class_name']); ?></p>
                                            <p class="text-xs text-gray-500">Section: <?php echo htmlspecialchars($subject['section']); ?></p>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Activities -->
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Activities</h3>
                    
                    <?php if (empty($activities)): ?>
                        <p class="text-sm text-gray-500">No recent activities recorded.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-gray-200">
                            <?php foreach ($activities as $activity): ?>
                                <li class="py-2">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($activity['activity_type']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('F j, Y, g:i a', strtotime($activity['created_at'])); ?></p>
                                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function printProfile() {
        const printContent = document.querySelector('.bg-white.shadow').innerHTML;
        const originalContent = document.body.innerHTML;
        
        document.body.innerHTML = `
            <html>
            <head>
                <title>Teacher Profile</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
                <style>
                    @media print {
                        body {
                            font-size: 12px;
                        }
                        .no-print {
                            display: none;
                        }
                    }
                </style>
            </head>
            <body class="p-4">
                <div class="max-w-4xl mx-auto">
                    <h1 class="text-2xl font-bold mb-4">Teacher Profile</h1>
                    ${printContent}
                </div>
            </body>
            </html>
        `;
        
        window.print();
        document.body.innerHTML = originalContent;
    }
</script>
