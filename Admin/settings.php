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

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Admin';

// Function to log actions
function logAction($conn, $user_id, $action, $details) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}

// Get current system settings
$settings = [];
try {
    $sql = "SELECT * FROM system_settings";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading settings: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // General Settings
    if (isset($_POST['action']) && $_POST['action'] == 'update_general') {
        try {
            $school_name = $_POST['school_name'];
            $school_address = $_POST['school_address'];
            $school_phone = $_POST['school_phone'];
            $school_email = $_POST['school_email'];
            $academic_year = $_POST['academic_year'];
            $result_publish_auto = isset($_POST['result_publish_auto']) ? 1 : 0;
            
            // Update settings in database
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            $settings_to_update = [
                'school_name' => $school_name,
                'school_address' => $school_address,
                'school_phone' => $school_phone,
                'school_email' => $school_email,
                'current_academic_year' => $academic_year,
                'result_publish_auto' => $result_publish_auto
            ];
            
            foreach ($settings_to_update as $key => $value) {
                $stmt->bind_param("ss", $key, $value);
                $stmt->execute();
            }
            
            $stmt->close();
            
            // Update logo if uploaded
            if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($_FILES['school_logo']['type'], $allowed_types)) {
                    $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                } elseif ($_FILES['school_logo']['size'] > $max_size) {
                    $_SESSION['error'] = "File size too large. Maximum size is 2MB.";
                } else {
                    $upload_dir = 'uploads/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_name = 'school_logo.' . pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION);
                    $upload_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['school_logo']['tmp_name'], $upload_path)) {
                        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('school_logo', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                        $stmt->bind_param("s", $upload_path);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        $_SESSION['error'] = "Failed to upload logo.";
                    }
                }
            }
            
            $_SESSION['success'] = "General settings updated successfully!";
            logAction($conn, $user_id, "UPDATE_SETTINGS", "Updated general settings");
            
            // Refresh settings
            $sql = "SELECT * FROM system_settings";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error updating settings: " . $e->getMessage();
        }
    }
    
    // Grading System Settings
    elseif (isset($_POST['action']) && $_POST['action'] == 'update_grading') {
        try {
            $min_marks = $_POST['min_marks'];
            $max_marks = $_POST['max_marks'];
            $grades = $_POST['grades'];
            $min_percentages = $_POST['min_percentages'];
            $gpas = $_POST['gpas'];
            
            // Start transaction
            $conn->begin_transaction();
            
            // Clear existing grading system
            $conn->query("DELETE FROM grading_system");
            
            // Insert new grading system
            $stmt = $conn->prepare("INSERT INTO grading_system (grade, min_percentage, gpa) VALUES (?, ?, ?)");
            
            for ($i = 0; $i < count($grades); $i++) {
                $stmt->bind_param("sdd", $grades[$i], $min_percentages[$i], $gpas[$i]);
                $stmt->execute();
            }
            
            $stmt->close();
            
            // Update min and max marks settings
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            $stmt->bind_param("ss", $key, $value);
            
            $key = 'min_marks';
            $value = $min_marks;
            $stmt->execute();
            
            $key = 'max_marks';
            $value = $max_marks;
            $stmt->execute();
            
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Grading system updated successfully!";
            logAction($conn, $user_id, "UPDATE_SETTINGS", "Updated grading system");
            
            // Refresh settings
            $sql = "SELECT * FROM system_settings";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error'] = "Error updating grading system: " . $e->getMessage();
        }
    }
    
    // Email Settings
    elseif (isset($_POST['action']) && $_POST['action'] == 'update_email') {
        try {
            $smtp_host = $_POST['smtp_host'];
            $smtp_port = $_POST['smtp_port'];
            $smtp_username = $_POST['smtp_username'];
            $smtp_password = $_POST['smtp_password'];
            $smtp_encryption = $_POST['smtp_encryption'];
            $email_from_name = $_POST['email_from_name'];
            $email_from_address = $_POST['email_from_address'];
            
            // Update settings in database
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            $settings_to_update = [
                'smtp_host' => $smtp_host,
                'smtp_port' => $smtp_port,
                'smtp_username' => $smtp_username,
                'smtp_encryption' => $smtp_encryption,
                'email_from_name' => $email_from_name,
                'email_from_address' => $email_from_address
            ];
            
            // Only update password if provided
            if (!empty($smtp_password)) {
                $settings_to_update['smtp_password'] = $smtp_password;
            }
            
            foreach ($settings_to_update as $key => $value) {
                $stmt->bind_param("ss", $key, $value);
                $stmt->execute();
            }
            
            $stmt->close();
            
            $_SESSION['success'] = "Email settings updated successfully!";
            logAction($conn, $user_id, "UPDATE_SETTINGS", "Updated email settings");
            
            // Refresh settings
            $sql = "SELECT * FROM system_settings";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error updating email settings: " . $e->getMessage();
        }
    }
    
    // Backup & Restore
    elseif (isset($_POST['action']) && $_POST['action'] == 'create_backup') {
        try {
            // Create backup directory if it doesn't exist
            $backup_dir = 'backups/';
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }
            
            // Generate backup filename with timestamp
            $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            // Get database credentials
            $db_host = 'localhost';
            $db_user = 'root';
            $db_pass = '';
            $db_name = 'result_management';
            
            // Create backup command
            $command = "mysqldump --host={$db_host} --user={$db_user} " . 
                      ($db_pass ? "--password={$db_pass} " : "") . 
                      "{$db_name} > {$backup_file}";
            
            // Execute backup command
            exec($command, $output, $return_var);
            
            if ($return_var === 0) {
                $_SESSION['success'] = "Database backup created successfully! File: " . $backup_file;
                logAction($conn, $user_id, "CREATE_BACKUP", "Created database backup: " . $backup_file);
            } else {
                $_SESSION['error'] = "Failed to create database backup.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error creating backup: " . $e->getMessage();
        }
    }
    
    elseif (isset($_POST['action']) && $_POST['action'] == 'restore_backup') {
        try {
            if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == 0) {
                $allowed_types = ['application/sql', 'text/plain', 'application/octet-stream'];
                $max_size = 10 * 1024 * 1024; // 10MB
                
                if (!in_array($_FILES['backup_file']['type'], $allowed_types) && !strpos($_FILES['backup_file']['name'], '.sql')) {
                    $_SESSION['error'] = "Invalid file type. Only SQL files are allowed.";
                } elseif ($_FILES['backup_file']['size'] > $max_size) {
                    $_SESSION['error'] = "File size too large. Maximum size is 10MB.";
                } else {
                    // Create temp directory if it doesn't exist
                    $temp_dir = 'temp/';
                    if (!file_exists($temp_dir)) {
                        mkdir($temp_dir, 0777, true);
                    }
                    
                    $temp_file = $temp_dir . basename($_FILES['backup_file']['name']);
                    
                    if (move_uploaded_file($_FILES['backup_file']['tmp_name'], $temp_file)) {
                        // Get database credentials
                        $db_host = 'localhost';
                        $db_user = 'root';
                        $db_pass = '';
                        $db_name = 'result_management';
                        
                        // Create restore command
                        $command = "mysql --host={$db_host} --user={$db_user} " . 
                                  ($db_pass ? "--password={$db_pass} " : "") . 
                                  "{$db_name} < {$temp_file}";
                        
                        // Execute restore command
                        exec($command, $output, $return_var);
                        
                        // Delete temp file
                        unlink($temp_file);
                        
                        if ($return_var === 0) {
                            $_SESSION['success'] = "Database restored successfully!";
                            logAction($conn, $user_id, "RESTORE_BACKUP", "Restored database from backup");
                        } else {
                            $_SESSION['error'] = "Failed to restore database.";
                        }
                    } else {
                        $_SESSION['error'] = "Failed to upload backup file.";
                    }
                }
            } else {
                $_SESSION['error'] = "Please select a backup file to restore.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error restoring backup: " . $e->getMessage();
        }
    }
    
    // System Maintenance
    elseif (isset($_POST['action']) && $_POST['action'] == 'clear_logs') {
        try {
            $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
            
            $stmt = $conn->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->bind_param("i", $days);
            $stmt->execute();
            
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            $_SESSION['success'] = "Successfully cleared $affected_rows log entries older than $days days.";
            logAction($conn, $user_id, "CLEAR_LOGS", "Cleared logs older than $days days");
        } catch (Exception $e) {
            $_SESSION['error'] = "Error clearing logs: " . $e->getMessage();
        }
    }
    
    elseif (isset($_POST['action']) && $_POST['action'] == 'optimize_tables') {
        try {
            $tables = $conn->query("SHOW TABLES")->fetch_all(MYSQLI_NUM);
            $optimized = 0;
            
            foreach ($tables as $table) {
                $table_name = $table[0];
                $conn->query("OPTIMIZE TABLE `$table_name`");
                $optimized++;
            }
            
            $_SESSION['success'] = "Successfully optimized $optimized database tables.";
            logAction($conn, $user_id, "OPTIMIZE_TABLES", "Optimized database tables");
        } catch (Exception $e) {
            $_SESSION['error'] = "Error optimizing tables: " . $e->getMessage();
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: settings.php");
    exit();
}

// Get current grading system
$grading_system = [];
try {
    $sql = "SELECT * FROM grading_system ORDER BY min_percentage DESC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $grading_system[] = $row;
    }
} catch (Exception $e) {
    // If table doesn't exist, create default grading system
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS grading_system (
                id INT AUTO_INCREMENT PRIMARY KEY,
                grade VARCHAR(5) NOT NULL,
                min_percentage DECIMAL(5,2) NOT NULL,
                gpa DECIMAL(3,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default grading system
        $default_grades = [
            ['A+', 90, 4.0],
            ['A', 80, 3.7],
            ['B+', 70, 3.3],
            ['B', 60, 3.0],
            ['C+', 50, 2.7],
            ['C', 40, 2.3],
            ['D', 33, 1.0],
            ['F', 0, 0.0]
        ];
        
        $stmt = $conn->prepare("INSERT INTO grading_system (grade, min_percentage, gpa) VALUES (?, ?, ?)");
        foreach ($default_grades as $grade) {
            $stmt->bind_param("sdd", $grade[0], $grade[1], $grade[2]);
            $stmt->execute();
        }
        $stmt->close();
        
        // Fetch the newly created grading system
        $sql = "SELECT * FROM grading_system ORDER BY min_percentage DESC";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $grading_system[] = $row;
        }
    } else {
        $_SESSION['error'] = "Error loading grading system: " . $e->getMessage();
    }
}

// Get available backups
$backups = [];
$backup_dir = 'backups/';
if (file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && strpos($file, 'backup_') === 0) {
            $backups[] = [
                'name' => $file,
                'size' => filesize($backup_dir . $file),
                'date' => date('Y-m-d H:i:s', filemtime($backup_dir . $file))
            ];
        }
    }
    
    // Sort backups by date (newest first)
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        /* Hover effects */
        .hover-scale {
            transition: all 0.3s ease;
        }
        
        .hover-scale:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Dark mode */
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
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 bg-gray-800">
                <div class="flex items-center justify-center h-16 bg-gray-900">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <div class="flex flex-col flex-grow px-4 mt-5 overflow-y-auto">
                    <nav class="flex-1 space-y-1">
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            <span class="truncate">Dashboard</span>
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            <span class="truncate">Manage Results</span>
                        </a>
                        <a href="view_student_results.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-user-graduate mr-3"></i>
                            <span class="truncate">Student Results</span>
                        </a>
                        <a href="bulk_upload.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-upload mr-3"></i>
                            <span class="truncate">Bulk Upload</span>
                        </a>
                        
                        <div class="mt-4">
                            <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Management</p>
                        </div>
                        
                        <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-users mr-3"></i>
                            <span class="truncate">Users</span>
                        </a>
                        <a href="students.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-user-graduate mr-3"></i>
                            <span class="truncate">Students</span>
                        </a>
                        <a href="teachers.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-chalkboard-teacher mr-3"></i>
                            <span class="truncate">Teachers</span>
                        </a>
                        <a href="classes.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-chalkboard mr-3"></i>
                            <span class="truncate">Classes</span>
                        </a>
                        <a href="exams.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            <span class="truncate">Exams</span>
                        </a>
                        <a href="reports.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-chart-bar mr-3"></i>
                            <span class="truncate">Reports</span>
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md group">
                            <i class="fas fa-cog mr-3"></i>
                            <span class="truncate">Settings</span>
                        </a>
                    </nav>
                    <div class="flex-shrink-0 block w-full">
                        <div class="flex items-center justify-between px-4 py-2 mt-2">
                            <span class="text-sm text-gray-400">Dark Mode</span>
                            <button id="dark-mode-toggle" class="w-10 h-5 rounded-full bg-gray-700 flex items-center transition duration-300 focus:outline-none">
                                <div id="dark-mode-toggle-dot" class="w-4 h-4 bg-white rounded-full transform translate-x-0.5 transition duration-300"></div>
                            </button>
                        </div>
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition-colors duration-200">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            <span class="truncate">Logout</span>
                        </a>
                    </div>
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
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Settings</h1>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
                        <!-- User dropdown -->
                        <div class="ml-3 relative">
                            <div>
                                <button type="button" class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                    <span class="sr-only">Open user menu</span>
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-600">
                                        <span class="text-sm font-medium leading-none text-white">
                                            <?php echo substr($user_name, 0, 1); ?>
                                        </span>
                                    </span>
                                </button>
                            </div>
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" id="user-menu" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile sidebar -->
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
                            <!-- Mobile menu items -->
                            <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-tachometer-alt mr-3"></i>
                                Dashboard
                            </a>
                            <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                                <i class="fas fa-cog mr-3"></i>
                                Settings
                            </a>
                            <!-- More mobile menu items -->
                        </nav>
                    </div>
                </div>
            </div>

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
                                <div class="ml-auto pl-3">
                                    <div class="-mx-1.5 -my-1.5">
                                        <button class="inline-flex rounded-md p-1.5 text-green-500 hover:bg-green-100 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                                            <span class="sr-only">Dismiss</span>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
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
                                <div class="ml-auto pl-3">
                                    <div class="-mx-1.5 -my-1.5">
                                        <button class="inline-flex rounded-md p-1.5 text-red-500 hover:bg-red-100 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                                            <span class="sr-only">Dismiss</span>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Settings Tabs -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="border-b border-gray-200">
                                <nav class="-mb-px flex" aria-label="Tabs">
                                    <button class="tab-button w-1/5 py-4 px-1 text-center border-b-2 border-blue-500 font-medium text-sm text-blue-600" data-tab="general">
                                        <i class="fas fa-school mr-2"></i> General
                                    </button>
                                    <button class="tab-button w-1/5 py-4 px-1 text-center border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="grading">
                                        <i class="fas fa-graduation-cap mr-2"></i> Grading System
                                    </button>
                                    <button class="tab-button w-1/5 py-4 px-1 text-center border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="email">
                                        <i class="fas fa-envelope mr-2"></i> Email
                                    </button>
                                    <button class="tab-button w-1/5 py-4 px-1 text-center border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="backup">
                                        <i class="fas fa-database mr-2"></i> Backup & Restore
                                    </button>
                                    <button class="tab-button w-1/5 py-4 px-1 text-center border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="maintenance">
                                        <i class="fas fa-tools mr-2"></i> Maintenance
                                    </button>
                                </nav>
                            </div>

                            <!-- General Settings Tab -->
                            <div id="general-tab" class="tab-content p-6">
                                <h2 class="text-lg font-medium text-gray-900 mb-4">General Settings</h2>
                                <form action="settings.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_general">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="school_name" class="block text-sm font-medium text-gray-700 mb-1">School Name</label>
                                            <input type="text" id="school_name" name="school_name" value="<?php echo $settings['school_name'] ?? ''; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        </div>
                                        
                                        <div>
                                            <label for="school_address" class="block text-sm font-medium text-gray-700 mb-1">School Address</label>
                                            <input type="text" id="school_address" name="school_address" value="<?php echo $settings['school_address'] ?? ''; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        </div>
                                        
                                        <div>
                                            <label for="school_phone" class="block text-sm font-medium text-gray-700 mb-1">School Phone</label>
                                            <input type="text" id="school_phone" name="school_phone" value="<?php echo $settings['school_phone'] ?? ''; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        </div>
                                        
                                        <div>
                                            <label for="school_email" class="block text-sm font-medium text-gray-700 mb-1">School Email</label>
                                            <input type="email" id="school_email" name="school_email" value="<?php echo $settings['school_email'] ?? ''; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        </div>
                                        
                                        <div>
                                            <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Current Academic Year</label>
                                            <input type="text" id="academic_year" name="academic_year" value="<?php echo $settings['current_academic_year'] ?? date('Y') . '-' . (date('Y') + 1); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="e.g. 2023-2024">
                                        </div>
                                        
                                        <div>
                                            <label for="school_logo" class="block text-sm font-medium text-gray-700 mb-1">School Logo</label>
                                            <input type="file" id="school_logo" name="school_logo" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <?php if (!empty($settings['school_logo']) && file_exists($settings['school_logo'])): ?>
                                            <div class="mt-2">
                                                <img src="<?php echo $settings['school_logo']; ?>" alt="School Logo" class="h-16 w-auto">
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="md:col-span-2">
                                            <div class="flex items-center">
                                                <input type="checkbox" id="result_publish_auto" name="result_publish_auto" <?php echo (!empty($settings['result_publish_auto']) && $settings['result_publish_auto'] == 1) ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="result_publish_auto" class="ml-2 block text-sm text-gray-900">
                                                    Automatically publish results when all marks are entered
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-6 flex justify-end">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-save mr-2"></i> Save Settings
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Grading System Tab -->
                            <div id="grading-tab" class="tab-content p-6 hidden">
                                <h2 class="text-lg font-medium text-gray-900 mb-4">Grading System Settings</h2>
                                <form action="settings.php" method="POST">
                                    <input type="hidden" name="action" value="update_grading">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                        <div>
                                            <label for="min_marks" class="block text-sm font-medium text-gray-700 mb-1">Minimum Marks (Pass)</label>
                                            <input type="number" id="min_marks" name="min_marks" value="<?php echo $settings['min_marks'] ?? 33; ?>" min="0" max="100" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        </div>
                                        
                                        <div>
                                            <label for="max_marks" class="block text-sm font-medium text-gray-700 mb-1">Maximum Marks</label>
                                            <input type="number" id="max_marks" name="max_marks" value="<?php echo $settings['max_marks'] ?? 100; ?>" min="0" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        </div>
                                    </div>
                                    
                                    <h3 class="text-md font-medium text-gray-900 mb-2">Grade Scale</h3>
                                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                        <div class="grid grid-cols-3 gap-4 mb-2 font-medium text-sm text-gray-700">
                                            <div>Grade</div>
                                            <div>Minimum Percentage</div>
                                            <div>GPA</div>
                                        </div>
                                        
                                        <div id="grades-container">
                                            <?php if (empty($grading_system)): ?>
                                            <!-- Default grades if none exist -->
                                            <div class="grid grid-cols-3 gap-4 mb-2 grade-row">
                                                <div>
                                                    <input type="text" name="grades[]" value="A+" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                </div>
                                                <div>
                                                    <input type="number" name="min_percentages[]" value="90" min="0" max="100" step="0.01" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                </div>
                                                <div class="flex items-center">
                                                    <input type="number" name="gpas[]" value="4.0" min="0" max="4" step="0.01" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                    <button type="button" class="ml-2 text-red-500 remove-grade"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <!-- Existing grades -->
                                            <?php foreach ($grading_system as $grade): ?>
                                            <div class="grid grid-cols-3 gap-4 mb-2 grade-row">
                                                <div>
                                                    <input type="text" name="grades[]" value="<?php echo $grade['grade']; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                </div>
                                                <div>
                                                    <input type="number" name="min_percentages[]" value="<?php echo $grade['min_percentage']; ?>" min="0" max="100" step="0.01" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                </div>
                                                <div class="flex items-center">
                                                    <input type="number" name="gpas[]" value="<?php echo $grade['gpa']; ?>" min="0" max="4" step="0.01" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                    <button type="button" class="ml-2 text-red-500 remove-grade"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button type="button" id="add-grade" class="mt-2 inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-plus mr-1"></i> Add Grade
                                        </button>
                                    </div>
                                    
                                    <div class="mt-6 flex justify-end">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-save mr-2"></i> Save Grading System
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Email Settings Tab -->
                            <div id="email-tab" class="tab-content p-6 hidden">
                                <h2 class="text-lg font-medium text-gray-900 mb-4">Email Settings</h2>
                                <form action="settings.php" method="POST">
                                    <input type="hidden" name="action" value="update_email">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                                            <input type="text" id="smtp_host" name="smtp_host" value="<?php echo $settings['smtp_host'] ?? ''; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="e.g. smtp.gmail.com">
                                        </div>
                                        
                                        <div>
                                            <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
                                            <input type="number" id="smtp_port" name="smtp_port" value="<?php echo $settings['smtp_port'] ?? 587; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="e.g. 587">
                                        </div>
                                        
                                        <div>
                                            <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                                            <input type="text" id="smtp_username" name="smtp_username" value="<?php echo $settings['smtp_username'] ?? ''; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="e.g. your@email.com">
                                        </div>
                                        
                                        <div>
                                            <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                                            <input type="password" id="smtp_password" name="smtp_password" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="Leave blank to keep current password">
                                        </div>
                                        
                                        <div>
                                            <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                                            <select id="smtp_encryption" name="smtp_encryption" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                <option value="tls" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'tls') ? 'selected' : ''; ?>>TLS</option>
                                                <option value="ssl" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                                <option value="none" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'none') ? 'selected' : ''; ?>>None</option>  && $settings['smtp_encryption'] == 'none') ? 'selected' : ''; ?>>None</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label for="email_from_name" class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                                            <input type="text" id="email_from_name" name="email_from_name" value="<?php echo $settings['email_from_name'] ?? ''; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="e.g. School Name">
                                        </div>
                                        
                                        <div>
                                            <label for="email_from_address" class="block text-sm font-medium text-gray-700 mb-1">From Email Address</label>
                                            <input type="email" id="email_from_address" name="email_from_address" value="<?php echo $settings['email_from_address'] ?? ''; ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="e.g. noreply@school.com">
                                        </div>
                                        
                                        <div class="md:col-span-2">
                                            <button type="button" id="test-email" class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-paper-plane mr-1"></i> Send Test Email
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-6 flex justify-end">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-save mr-2"></i> Save Email Settings
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Backup & Restore Tab -->
                            <div id="backup-tab" class="tab-content p-6 hidden">
                                <h2 class="text-lg font-medium text-gray-900 mb-4">Backup & Restore</h2>
                                
                                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                    <h3 class="text-md font-medium text-gray-900 mb-2">Create Backup</h3>
                                    <p class="text-sm text-gray-500 mb-4">Create a backup of your database. This will export all tables and data.</p>
                                    <form action="settings.php" method="POST">
                                        <input type="hidden" name="action" value="create_backup">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-download mr-2"></i> Create Backup
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                    <h3 class="text-md font-medium text-gray-900 mb-2">Restore Backup</h3>
                                    <p class="text-sm text-gray-500 mb-4">Restore your database from a backup file. This will overwrite all current data.</p>
                                    <form action="settings.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="restore_backup">
                                        <div class="mb-4">
                                            <label for="backup_file" class="block text-sm font-medium text-gray-700 mb-1">Backup File (.sql)</label>
                                            <input type="file" id="backup_file" name="backup_file" accept=".sql" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        </div>
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" onclick="return confirm('WARNING: This will overwrite all current data. Are you sure you want to proceed?')">
                                            <i class="fas fa-upload mr-2"></i> Restore Backup
                                        </button>
                                    </form>
                                </div>
                                
                                <?php if (!empty($backups)): ?>
                                <div class="bg-white shadow overflow-hidden rounded-lg">
                                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                                        <h3 class="text-md font-medium text-gray-900">Available Backups</h3>
                                    </div>
                                    <div class="px-4 py-5 sm:p-6">
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Filename</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($backups as $backup): ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $backup['name']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($backup['size'] / 1024, 2); ?> KB</td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $backup['date']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                            <a href="backups/<?php echo $backup['name']; ?>" download class="text-blue-600 hover:text-blue-900 mr-3">
                                                                <i class="fas fa-download"></i> Download
                                                            </a>
                                                            <a href="#" class="text-red-600 hover:text-red-900 delete-backup" data-file="<?php echo $backup['name']; ?>">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Maintenance Tab -->
                            <div id="maintenance-tab" class="tab-content p-6 hidden">
                                <h2 class="text-lg font-medium text-gray-900 mb-4">System Maintenance</h2>
                                
                                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                    <h3 class="text-md font-medium text-gray-900 mb-2">Clear Activity Logs</h3>
                                    <p class="text-sm text-gray-500 mb-4">Remove old activity logs to free up database space.</p>
                                    <form action="settings.php" method="POST">
                                        <input type="hidden" name="action" value="clear_logs">
                                        <div class="flex items-center mb-4">
                                            <label for="days" class="mr-2 text-sm font-medium text-gray-700">Delete logs older than</label>
                                            <input type="number" id="days" name="days" value="30" min="1" class="w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-700">days</span>
                                        </div>
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" onclick="return confirm('Are you sure you want to delete old logs?')">
                                            <i class="fas fa-trash mr-2"></i> Clear Logs
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                    <h3 class="text-md font-medium text-gray-900 mb-2">Optimize Database</h3>
                                    <p class="text-sm text-gray-500 mb-4">Optimize database tables to improve performance.</p>
                                    <form action="settings.php" method="POST">
                                        <input type="hidden" name="action" value="optimize_tables">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-database mr-2"></i> Optimize Tables
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="text-md font-medium text-gray-900 mb-2">System Information</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm font-medium text-gray-700">PHP Version:</p>
                                            <p class="text-sm text-gray-500"><?php echo phpversion(); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700">MySQL Version:</p>
                                            <p class="text-sm text-gray-500"><?php echo mysqli_get_server_info($conn = new mysqli('localhost', 'root', '', 'result_management')); $conn->close(); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700">Server Software:</p>
                                            <p class="text-sm text-gray-500"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700">Max Upload Size:</p>
                                            <p class="text-sm text-gray-500"><?php echo ini_get('upload_max_filesize'); ?></p>
                                        </div>
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
        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const closeSidebar = document.getElementById('close-sidebar');
            const sidebarBackdrop = document.getElementById('sidebar-backdrop');
            const mobileSidebar = document.getElementById('mobile-sidebar');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    mobileSidebar.classList.remove('-translate-x-full');
                });
            }
            
            if (closeSidebar) {
                closeSidebar.addEventListener('click', function() {
                    mobileSidebar.classList.add('-translate-x-full');
                });
            }
            
            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', function() {
                    mobileSidebar.classList.add('-translate-x-full');
                });
            }
            
            // User menu toggle
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            
            if (userMenuButton && userMenu) {
                userMenuButton.addEventListener('click', function() {
                    userMenu.classList.toggle('hidden');
                });
                
                // Close when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                        userMenu.classList.add('hidden');
                    }
                });
            }
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('dark-mode-toggle');
            const darkModeToggleDot = document.getElementById('dark-mode-toggle-dot');
            const body = document.getElementById('body');
            
            if (darkModeToggle && darkModeToggleDot && body) {
                // Check for saved dark mode preference
                if (localStorage.getItem('darkMode') === 'true') {
                    body.classList.add('dark-mode');
                    darkModeToggleDot.classList.remove('translate-x-0.5');
                    darkModeToggleDot.classList.add('translate-x-5');
                }
                
                darkModeToggle.addEventListener('click', function() {
                    if (body.classList.contains('dark-mode')) {
                        body.classList.remove('dark-mode');
                        darkModeToggleDot.classList.remove('translate-x-5');
                        darkModeToggleDot.classList.add('translate-x-0.5');
                        localStorage.setItem('darkMode', 'false');
                    } else {
                        body.classList.add('dark-mode');
                        darkModeToggleDot.classList.remove('translate-x-0.5');
                        darkModeToggleDot.classList.add('translate-x-5');
                        localStorage.setItem('darkMode', 'true');
                    }
                });
            }
            
            // Tab switching
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    // Hide all tab contents
                    tabContents.forEach(content => {
                        content.classList.add('hidden');
                    });
                    
                    // Remove active class from all tab buttons
                    tabButtons.forEach(btn => {
                        btn.classList.remove('border-blue-500', 'text-blue-600');
                        btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                    });
                    
                    // Show selected tab content
                    document.getElementById(`${tabName}-tab`).classList.remove('hidden');
                    
                    // Add active class to clicked button
                    this.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                    this.classList.add('border-blue-500', 'text-blue-600');
                });
            });
            
            // Grading system - Add grade
            const addGradeButton = document.getElementById('add-grade');
            const gradesContainer = document.getElementById('grades-container');
            
            if (addGradeButton && gradesContainer) {
                addGradeButton.addEventListener('click', function() {
                    const newGradeRow = document.createElement('div');
                    newGradeRow.className = 'grid grid-cols-3 gap-4 mb-2 grade-row';
                    newGradeRow.innerHTML = `
                        <div>
                            <input type="text" name="grades[]" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        </div>
                        <div>
                            <input type="number" name="min_percentages[]" min="0" max="100" step="0.01" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        </div>
                        <div class="flex items-center">
                            <input type="number" name="gpas[]" min="0" max="4" step="0.01" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <button type="button" class="ml-2 text-red-500 remove-grade"><i class="fas fa-trash"></i></button>
                        </div>
                    `;
                    gradesContainer.appendChild(newGradeRow);
                    
                    // Add event listener to the new remove button
                    newGradeRow.querySelector('.remove-grade').addEventListener('click', function() {
                        newGradeRow.remove();
                    });
                });
                
                // Remove grade
                document.addEventListener('click', function(e) {
                    if (e.target.closest('.remove-grade')) {
                        e.target.closest('.grade-row').remove();
                    }
                });
            }
            
            // Test email
            const testEmailButton = document.getElementById('test-email');
            if (testEmailButton) {
                testEmailButton.addEventListener('click', function() {
                    const testEmail = prompt('Enter email address to send test email:');
                    if (testEmail) {
                        // Send AJAX request to test email
                        fetch('test_email.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                email: testEmail,
                                smtp_host: document.getElementById('smtp_host').value,
                                smtp_port: document.getElementById('smtp_port').value,
                                smtp_username: document.getElementById('smtp_username').value,
                                smtp_password: document.getElementById('smtp_password').value,
                                smtp_encryption: document.getElementById('smtp_encryption').value,
                                email_from_name: document.getElementById('email_from_name').value,
                                email_from_address: document.getElementById('email_from_address').value
                            }),
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Test email sent successfully!');
                            } else {
                                alert('Failed to send test email: ' + data.message);
                            }
                        })
                        .catch(error => {
                            alert('Error: ' + error);
                        });
                    }
                });
            }
            
            // Delete backup
            const deleteBackupButtons = document.querySelectorAll('.delete-backup');
            deleteBackupButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const fileName = this.getAttribute('data-file');
                    if (confirm('Are you sure you want to delete this backup?')) {
                        fetch('delete_backup.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                file: fileName
                            }),
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Backup deleted successfully!');
                                this.closest('tr').remove();
                            } else {
                                alert('Failed to delete backup: ' + data.message);
                            }
                        })
                        .catch(error => {
                            alert('Error: ' + error);
                        });
                    }
                });
            });
        });
    </script>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

</body>
</html>