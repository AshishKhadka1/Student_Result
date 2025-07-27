<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get student details
$stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, c.academic_year, u.full_name, u.email, u.phone, u.status, u.created_at as account_created 
                      FROM students s 
                      JOIN classes c ON s.class_id = c.class_id 
                      JOIN users u ON s.user_id = u.user_id 
                      WHERE s.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Get form data
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update phone and address
        $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE user_id = ?");
        $stmt->bind_param("si", $phone, $user_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("UPDATE students SET address = ? WHERE user_id = ?");
        $stmt->bind_param("si", $address, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Update password if provided
        if (!empty($current_password) && !empty($new_password)) {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (password_verify($current_password, $user['password'])) {
                // Check if new password and confirm password match
                if ($new_password === $confirm_password) {
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    throw new Exception("New password and confirm password do not match.");
                }
            } else {
                throw new Exception("Current password is incorrect.");
            }
        }

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (in_array($_FILES['profile_picture']['type'], $allowed_types) && $_FILES['profile_picture']['size'] <= $max_size) {
                $upload_dir = '../uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $new_filename = 'student_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    // Delete old profile picture if exists
                    if (!empty($student['profile_picture']) && file_exists($upload_dir . $student['profile_picture'])) {
                        unlink($upload_dir . $student['profile_picture']);
                    }
                    
                    // Update database with new profile picture
                    $stmt = $conn->prepare("UPDATE students SET profile_picture = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $new_filename, $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $success_message = "Profile updated successfully.";
        
        // Refresh student data
        $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, c.academic_year, u.full_name, u.email, u.phone, u.status, u.created_at as account_created 
                              FROM students s 
                              JOIN classes c ON s.class_id = c.class_id 
                              JOIN users u ON s.user_id = u.user_id 
                              WHERE s.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}

// Get attendance data
$attendance_data = [
    'present' => 0,
    'absent' => 0,
    'leave' => 0,
    'total_days' => 0,
    'percentage' => 0
];

// Check if attendance table exists
$table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
$attendance_table_exists = ($table_check && $table_check->num_rows > 0);

if ($attendance_table_exists) {
    $stmt = $conn->prepare("SELECT 
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                            SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count,
                            COUNT(*) as total_days
                          FROM attendance 
                          WHERE student_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("s", $student['student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $attendance_data['present'] = $row['present'] ?? 0;
            $attendance_data['absent'] = $row['absent'] ?? 0;
            $attendance_data['leave'] = $row['leave_count'] ?? 0;
            $attendance_data['total_days'] = $row['total_days'] ?? 0;
            
            if ($attendance_data['total_days'] > 0) {
                $attendance_data['percentage'] = round(($attendance_data['present'] / $attendance_data['total_days']) * 100, 2);
            }
        }
        $stmt->close();
    } else {
        // Log the error for debugging
        error_log("Attendance query preparation failed: " . $conn->error);
    }
}

// Get recent results
$recent_results = [];
$stmt = $conn->prepare("SELECT r.*, s.subject_name, e.exam_name 
                      FROM results r 
                      JOIN subjects s ON r.subject_id = s.subject_id 
                      JOIN exams e ON r.exam_id = e.exam_id
                      WHERE r.student_id = ? 
                      ORDER BY r.created_at DESC LIMIT 5");

if ($stmt) {
    $stmt->bind_param("s", $student['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_results[] = $row;
    }
    $stmt->close();
} else {
    // Log the error for debugging
    error_log("Recent results query preparation failed: " . $conn->error);
}

// Calculate overall performance
$overall_performance = [
    'total_subjects' => 0,
    'subjects_with_results' => 0,
    'total_marks' => 0,
    'obtained_marks' => 0,
    'average_percentage' => 0,
    'average_grade' => 'N/A',
    'average_gpa' => 0,
    'pass_count' => 0,
    'fail_count' => 0
];

if (!empty($recent_results)) {
    $overall_performance['subjects_with_results'] = count($recent_results);
    $total_gpa = 0;
    
    foreach ($recent_results as $result) {
        $overall_performance['obtained_marks'] += $result['theory_marks'] + ($result['practical_marks'] ?? 0);
        $overall_performance['total_marks'] += 100; // Assuming each subject is out of 100
        
        // Calculate GPA based on grade if not provided
        $gpa = 0;
        switch ($result['grade']) {
            case 'A+': $gpa = 4.0; break;
            case 'A': $gpa = 3.7; break;
            case 'B+': $gpa = 3.3; break;
            case 'B': $gpa = 3.0; break;
            case 'C+': $gpa = 2.7; break;
            case 'C': $gpa = 2.3; break;
            case 'D': $gpa = 2.0; break;
            case 'F': $gpa = 0.0; break;
            default: $gpa = 0.0;
        }
        $total_gpa += $gpa;
        
        if ($result['grade'] != 'F') {
            $overall_performance['pass_count']++;
        } else {
            $overall_performance['fail_count']++;
        }
    }
    
    if ($overall_performance['total_marks'] > 0) {
        $overall_performance['average_percentage'] = ($overall_performance['obtained_marks'] / $overall_performance['total_marks']) * 100;
        $overall_performance['average_gpa'] = $total_gpa / $overall_performance['subjects_with_results'];
        
        // Determine average grade
        if ($overall_performance['average_percentage'] >= 91) {
            $overall_performance['average_grade'] = 'A+';
        } elseif ($overall_performance['average_percentage'] >= 81) {
            $overall_performance['average_grade'] = 'A';
        } elseif ($overall_performance['average_percentage'] >= 71) {
            $overall_performance['average_grade'] = 'B+';
        } elseif ($overall_performance['average_percentage'] >= 61) {
            $overall_performance['average_grade'] = 'B';
        } elseif ($overall_performance['average_percentage'] >= 51) {
            $overall_performance['average_grade'] = 'C+';
        } elseif ($overall_performance['average_percentage'] >= 41) {
            $overall_performance['average_grade'] = 'C';
        } elseif ($overall_performance['average_percentage'] >= 33) {
            $overall_performance['average_grade'] = 'D';
        } else {
            $overall_performance['average_grade'] = 'F';
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | Result Management System</title>
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

        /* Grade badge styles */
        .grade-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .grade-a-plus {
            background-color: #dcfce7;
            color: #166534;
        }

        .grade-a {
            background-color: #dcfce7;
            color: #166534;
        }

        .grade-b-plus {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .grade-b {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .grade-c-plus {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .grade-c {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .grade-d {
            background-color: #ffedd5;
            color: #9a3412;
        }

        .grade-f {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        /* Tab styles */
        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
        }
    </style>
</head>

<body class="bg-gray-100" id="body">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include('./includes/student_sidebar.php'); ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include('./includes/top_navigation.php'); ?>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Alert Messages -->
                        <?php if (!empty($success_message)): ?>
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                                <p><?php echo $success_message; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                                <p><?php echo $error_message; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Page Header -->
                        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">My Profile</h2>
                                    <p class="mt-1 text-sm text-gray-500">View and manage your personal information</p>
                                </div>
                                <div class="mt-4 md:mt-0">
                                    <button id="edit-profile-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-edit mr-2"></i> Edit Profile
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Content -->
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-6">
                            <!-- Profile Tabs -->
                            <div class="border-b border-gray-200">
                                <nav class="flex -mb-px">
                                    <button id="tab-personal" class="tab-active py-4 px-6 text-sm font-medium">
                                        Personal Information
                                    </button>
                                    <button id="tab-academic" class="text-gray-500 hover:text-gray-700 py-4 px-6 text-sm font-medium">
                                        Academic Information
                                    </button>
                                </nav>
                            </div>

                            <!-- Personal Information Tab -->
                            <div id="content-personal" class="p-6">
                                <div class="flex flex-col md:flex-row">
                                    <!-- Profile Image -->
                                    <div class="flex-shrink-0 mb-6 md:mb-0 md:mr-6">
                                        <div class="relative">
                                            <?php if (!empty($student['profile_picture']) && file_exists("../uploads/profiles/" . $student['profile_picture'])): ?>
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                                     alt="Profile Picture" 
                                                     class="h-32 w-32 rounded-full object-cover border-4 border-white shadow-lg">
                                            <?php else: ?>
                                                <div class="h-32 w-32 rounded-full bg-blue-600 flex items-center justify-center text-white text-4xl font-bold border-4 border-white shadow-lg">
                                                    <?php echo substr($student['full_name'], 0, 1); ?>
                                                </div>
                                            <?php endif; ?>
                                            <button type="button" id="change-photo-btn" class="absolute bottom-0 right-0 bg-blue-600 text-white rounded-full p-2 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-camera text-sm"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Profile Details -->
                                    <div class="flex-grow">
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Details</h3>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Full Name</p>
                                                <p class="text-base text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Email</p>
                                                <p class="text-base text-gray-900"><?php echo htmlspecialchars($student['email']); ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Phone</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['phone']) ? htmlspecialchars($student['phone']) : 'Not provided'; ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Gender</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['gender']) ? ucfirst(htmlspecialchars($student['gender'])) : 'Not specified'; ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Date of Birth</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['date_of_birth']) ? date('F j, Y', strtotime($student['date_of_birth'])) : 'Not provided'; ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Address</p>
                                                <p class="text-base text-gray-900"><?php echo !empty($student['address']) ? htmlspecialchars($student['address']) : 'Not provided'; ?></p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Account Status</p>
                                                <p class="text-base">
                                                    <?php if ($student['status'] == 'active'): ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            Active
                                                        </span>
                                                    <?php elseif ($student['status'] == 'inactive'): ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                            Inactive
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                            Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Account Created</p>
                                                <p class="text-base text-gray-900"><?php echo date('F j, Y', strtotime($student['account_created'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Academic Information Tab -->
                            <div id="content-academic" class="p-6 hidden">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Academic Details</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Student ID</p>
                                        <p class="text-base text-gray-900"><?php echo htmlspecialchars($student['student_id']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Roll Number</p>
                                        <p class="text-base text-gray-900"><?php echo htmlspecialchars($student['roll_number']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Class</p>
                                        <p class="text-base text-gray-900"><?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Academic Year</p>
                                        <p class="text-base text-gray-900"><?php echo htmlspecialchars($student['academic_year']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Batch Year</p>
                                        <p class="text-base text-gray-900"><?php echo htmlspecialchars($student['batch_year']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Admission Date</p>
                                        <p class="text-base text-gray-900"><?php echo !empty($student['admission_date']) ? date('F j, Y', strtotime($student['admission_date'])) : 'Not available'; ?></p>
                                    </div>
                                </div>
                                
                                <!-- Attendance Information -->
                                <h3 class="text-lg font-medium text-gray-900 mt-8 mb-4">Attendance Overview</h3>

                                <?php if (!$attendance_table_exists): ?>
                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-info-circle text-blue-400"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-blue-700">
                                                    Attendance tracking is not yet available. Your attendance data will appear here once the system is updated.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                        <div class="bg-blue-50 rounded-lg p-4 text-center">
                                            <div class="text-3xl font-bold text-blue-600"><?php echo $attendance_data['present']; ?></div>
                                            <div class="text-sm font-medium text-blue-800">Days Present</div>
                                        </div>
                                        
                                        <div class="bg-red-50 rounded-lg p-4 text-center">
                                            <div class="text-3xl font-bold text-red-600"><?php echo $attendance_data['absent']; ?></div>
                                            <div class="text-sm font-medium text-red-800">Days Absent</div>
                                        </div>
                                        
                                        <div class="bg-yellow-50 rounded-lg p-4 text-center">
                                            <div class="text-3xl font-bold text-yellow-600"><?php echo $attendance_data['leave']; ?></div>
                                            <div class="text-sm font-medium text-yellow-800">Days on Leave</div>
                                        </div>
                                        
                                        <div class="bg-green-50 rounded-lg p-4 text-center">
                                            <div class="text-3xl font-bold text-green-600"><?php echo $attendance_data['percentage']; ?>%</div>
                                            <div class="text-sm font-medium text-green-800">Attendance Rate</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <h4 class="text-md font-medium text-gray-700 mb-2">Attendance Percentage</h4>
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <?php 
                                            $color_class = 'bg-red-600';
                                            if ($attendance_data['percentage'] >= 90) {
                                                $color_class = 'bg-green-600';
                                            } elseif ($attendance_data['percentage'] >= 75) {
                                                $color_class = 'bg-blue-600';
                                            } elseif ($attendance_data['percentage'] >= 60) {
                                                $color_class = 'bg-yellow-600';
                                            }
                                            ?>
                                            <div class="<?php echo $color_class; ?> h-2.5 rounded-full" style="width: <?php echo $attendance_data['percentage']; ?>%"></div>
                                        </div>
                                        
                                        <?php if ($attendance_data['percentage'] < 75 && $attendance_data['total_days'] > 0): ?>
                                            <div class="mt-2 bg-red-50 border-l-4 border-red-400 p-4">
                                                <div class="flex">
                                                    <div class="flex-shrink-0">
                                                        <i class="fas fa-exclamation-circle text-red-400"></i>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm text-red-700">
                                                            Your attendance is below the required 75%. Please improve your attendance to avoid academic penalties.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center border-b pb-3">
                <h3 class="text-lg font-medium text-gray-900">Edit Profile</h3>
                <button type="button" id="close-edit-modal" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mt-4">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-700 mb-2">Personal Information</h4>
                            <p class="text-sm text-gray-500 mb-4">Update your personal details below. Some fields cannot be changed and require administrator assistance.</p>
                        </div>
                        
                        <div class="md:col-span-2 mb-4">
                            <label for="profile_picture" class="block text-sm font-medium text-gray-700">Profile Picture</label>
                            <div class="mt-1 flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <?php if (!empty($student['profile_picture']) && file_exists("../uploads/profiles/" . $student['profile_picture'])): ?>
                                        <img id="preview-image" src="../uploads/profiles/<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                             alt="Profile Picture" 
                                             class="h-16 w-16 rounded-full object-cover">
                                    <?php else: ?>
                                        <div id="preview-image" class="h-16 w-16 rounded-full bg-blue-600 flex items-center justify-center text-white text-xl font-bold">
                                            <?php echo substr($student['full_name'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow">
                                    <input type="file" name="profile_picture" id="profile_picture" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-100 text-gray-700 cursor-not-allowed" disabled>
                            <p class="mt-1 text-xs text-gray-500">Contact administrator to change your name</p>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($student['email']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-100 text-gray-700 cursor-not-allowed" disabled>
                            <p class="mt-1 text-xs text-gray-500">Contact administrator to change your email</p>
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                            <textarea name="address" id="address" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="md:col-span-2 mt-4">
                            <h4 class="text-md font-medium text-gray-700 mb-2">Change Password</h4>
                            <p class="text-sm text-gray-500 mb-4">Leave these fields empty if you don't want to change your password.</p>
                        </div>
                        
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                            <input type="password" name="current_password" id="current_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" name="new_password" id="new_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" id="cancel-edit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" name="update_profile" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        document.getElementById('tab-personal').addEventListener('click', function() {
            document.getElementById('tab-personal').classList.add('tab-active');
            document.getElementById('tab-personal').classList.remove('text-gray-500', 'hover:text-gray-700');
            
            document.getElementById('tab-academic').classList.remove('tab-active');
            document.getElementById('tab-academic').classList.add('text-gray-500', 'hover:text-gray-700');
            
            document.getElementById('content-personal').classList.remove('hidden');
            document.getElementById('content-academic').classList.add('hidden');
        });
        
        document.getElementById('tab-academic').addEventListener('click', function() {
            document.getElementById('tab-academic').classList.add('tab-active');
            document.getElementById('tab-academic').classList.remove('text-gray-500', 'hover:text-gray-700');
            
            document.getElementById('tab-personal').classList.remove('tab-active');
            document.getElementById('tab-personal').classList.add('text-gray-500', 'hover:text-gray-700');
            
            document.getElementById('content-academic').classList.remove('hidden');
            document.getElementById('content-personal').classList.add('hidden');
        });
        
        // Edit profile modal
        document.getElementById('edit-profile-btn').addEventListener('click', function() {
            document.getElementById('editProfileModal').classList.remove('hidden');
        });
        
        document.getElementById('close-edit-modal').addEventListener('click', function() {
            document.getElementById('editProfileModal').classList.add('hidden');
        });
        
        document.getElementById('cancel-edit').addEventListener('click', function() {
            document.getElementById('editProfileModal').classList.add('hidden');
        });
        
        // Password validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;
            
            // If trying to change password
            if (newPassword || confirmPassword || currentPassword) {
                // Check if all password fields are filled
                if (!newPassword || !confirmPassword || !currentPassword) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Error!',
                        text: 'All password fields are required to change your password.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
                
                // Check if passwords match
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Error!',
                        text: 'New password and confirm password do not match.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
                
                // Check password strength
                if (newPassword.length < 8) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Error!',
                        text: 'Password must be at least 8 characters long.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
            }
        });
        
        // Show success message if status parameter exists
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($success_message)): ?>
                Swal.fire({
                    title: 'Success!',
                    text: '<?php echo $success_message; ?>',
                    icon: 'success',
                    confirmButtonColor: '#3085d6'
                });
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                Swal.fire({
                    title: 'Error!',
                    text: '<?php echo $error_message; ?>',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            <?php endif; ?>
        });

        // Profile picture preview
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('preview-image');
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="h-16 w-16 rounded-full object-cover">';
                };
                reader.readAsDataURL(file);
            }
        });

        // Change photo button
        document.getElementById('change-photo-btn').addEventListener('click', function() {
            document.getElementById('editProfileModal').classList.remove('hidden');
        });
    </script>
</body>

</html>
