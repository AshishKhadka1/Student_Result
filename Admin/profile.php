<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to log actions
function logAction($conn, $user_id, $action, $details) {
    try {
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Create activity_logs table if it doesn't exist
        $conn->query("
            CREATE TABLE IF NOT EXISTS `activity_logs` (
              `log_id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `action` varchar(255) NOT NULL,
              `details` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`log_id`),
              KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Try again
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}

// Get user data
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $fullName = $_POST['full_name'];
        $email = $_POST['email'];
        
        // Validate inputs
        if (empty($fullName) || empty($email)) {
            $error_message = "Please fill all required fields.";
        } else {
            // Check if email is already in use by another user
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            if ($result->num_rows > 0) {
                $error_message = "Email is already in use by another account.";
            } else {
                // Update user data
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
                $stmt->bind_param("ssi", $fullName, $email, $userId);
                
                if ($stmt->execute()) {
                    $success_message = "Profile updated successfully!";
                    $_SESSION['full_name'] = $fullName;
                    
                    // Log the action
                    logAction($conn, $userId, "PROFILE_UPDATE", "Updated profile information");
                    
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                } else {
                    $error_message = "Error updating profile: " . $conn->error;
                }
                
                $stmt->close();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error_message = "Please fill all password fields.";
        } elseif ($newPassword !== $confirmPassword) {
            $error_message = "New password and confirmation do not match.";
        } elseif (strlen($newPassword) < 8) {
            $error_message = "New password must be at least 8 characters long.";
        } else {
            // Verify current password
            if (password_verify($currentPassword, $user['password'])) {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashedPassword, $userId);
                
                if ($stmt->execute()) {
                    $success_message = "Password changed successfully!";
                    
                    // Log the action
                    logAction($conn, $userId, "PASSWORD_CHANGE", "Changed account password");
                } else {
                    $error_message = "Error changing password: " . $conn->error;
                }
                
                $stmt->close();
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    } elseif (isset($_POST['upload_image'])) {
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['profile_image']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $error_message = "Only JPG, PNG, and GIF images are allowed.";
            } else {
                $maxFileSize = 2 * 1024 * 1024; // 2MB
                if ($_FILES['profile_image']['size'] > $maxFileSize) {
                    $error_message = "File size must be less than 2MB.";
                } else {
                    // Create uploads directory if it doesn't exist
                    $uploadDir = '../uploads/profile_images/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $fileName = 'profile_' . $userId . '_' . time() . '.' . pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $targetFile = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {
                        // Update user profile image in database
                        $relativePath = 'uploads/profile_images/' . $fileName;
                        $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                        $stmt->bind_param("si", $relativePath, $userId);
                        
                        if ($stmt->execute()) {
                            $success_message = "Profile image updated successfully!";
                            
                            // Log the action
                            logAction($conn, $userId, "PROFILE_IMAGE", "Updated profile image");
                            
                            // Refresh user data
                            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                            $stmt->bind_param("i", $userId);
                            $stmt->execute();
                            $user = $stmt->get_result()->fetch_assoc();
                        } else {
                            $error_message = "Error updating profile image in database: " . $conn->error;
                        }
                        
                        $stmt->close();
                    } else {
                        $error_message = "Error uploading image. Please try again.";
                    }
                }
            }
        } else {
            $error_message = "Please select an image to upload.";
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
    <title>My Profile | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include 'topBar.php'; ?>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <h1 class="text-2xl font-semibold text-gray-900">My Profile</h1>
                        
                        <!-- Success/Error Messages -->
                        <?php if (!empty($success_message)): ?>
                            <div class="mt-4 bg-green-50 border-l-4 border-green-400 p-4">
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
                            <div class="mt-4 bg-red-50 border-l-4 border-red-400 p-4">
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
                        
                        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <!-- Profile Information -->
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Profile Information</h3>
                                    <p class="mt-1 text-sm text-gray-500">Update your personal information.</p>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <form action="profile.php" method="POST">
                                        <div class="grid grid-cols-6 gap-6">
                                            <div class="col-span-6 sm:col-span-3">
                                                <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                                <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                            </div>

                                            <div class="col-span-6 sm:col-span-3">
                                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                            </div>

                                            <div class="col-span-6 sm:col-span-3">
                                                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                                                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="mt-1 bg-gray-100 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" readonly>
                                            </div>

                                            <div class="col-span-6 sm:col-span-3">
                                                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                                                <input type="text" name="role" id="role" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" class="mt-1 bg-gray-100 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" readonly>
                                            </div>

                                            <div class="col-span-6">
                                                <button type="submit" name="update_profile" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                    <i class="fas fa-save mr-2"></i> Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Profile Image -->
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Profile Image</h3>
                                    <p class="mt-1 text-sm text-gray-500">Upload a profile picture.</p>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="flex flex-col items-center">
                                        <div class="w-32 h-32 rounded-full overflow-hidden bg-gray-100 mb-4">
                                            <?php if (!empty($user['profile_image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center bg-blue-100 text-blue-500">
                                                    <i class="fas fa-user-circle text-6xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <form action="profile.php" method="POST" enctype="multipart/form-data" class="w-full">
                                            <div class="flex items-center justify-center">
                                                <label for="profile_image" class="cursor-pointer bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                    <span>Choose file</span>
                                                    <input id="profile_image" name="profile_image" type="file" class="sr-only" accept="image/*">
                                                </label>
                                                <span id="file-name" class="ml-2 text-sm text-gray-500">No file selected</span>
                                            </div>
                                            
                                            <div class="mt-4 flex justify-center">
                                                <button type="submit" name="upload_image" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                    <i class="fas fa-upload mr-2"></i> Upload Image
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Change Password -->
                            <div class="bg-white shadow rounded-lg overflow-hidden lg:col-span-2">
                                <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Change Password</h3>
                                    <p class="mt-1 text-sm text-gray-500">Update your account password.</p>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <form action="profile.php" method="POST">
                                        <div class="grid grid-cols-6 gap-6">
                                            <div class="col-span-6 sm:col-span-2">
                                                <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                                                <input type="password" name="current_password" id="current_password" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                            </div>

                                            <div class="col-span-6 sm:col-span-2">
                                                <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                                                <input type="password" name="new_password" id="new_password" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                            </div>

                                            <div class="col-span-6 sm:col-span-2">
                                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                                <input type="password" name="confirm_password" id="confirm_password" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                            </div>

                                            <div class="col-span-6">
                                                <button type="submit" name="change_password" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                    <i class="fas fa-key mr-2"></i> Change Password
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Display selected file name
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file selected';
            document.getElementById('file-name').textContent = fileName;
        });
    </script>
</body>
</html>
