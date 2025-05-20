<?php
session_start();
include '../includes/config.php';
include '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get teacher details - Fixed query to match database structure
$query = "
    SELECT u.full_name, u.email, u.phone, u.profile_image, 
           t.qualification, t.experience, t.joining_date
    FROM Users u
    LEFT JOIN Teachers t ON u.id = t.user_id
    WHERE u.id = ?
";

// Check if query preparation was successful
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $qualification = isset($_POST['qualification']) ? trim($_POST['qualification']) : '';
    $experience = isset($_POST['experience']) ? trim($_POST['experience']) : '';
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Check if email already exists (excluding current user)
    if (!empty($email)) {
        $check_stmt = $conn->prepare("SELECT id FROM Users WHERE email = ? AND id != ?");
        if ($check_stmt === false) {
            $errors[] = "Database error: " . $conn->error;
        } else {
            $check_stmt->bind_param("si", $email, $teacher_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $errors[] = "Email already in use by another account.";
            }
            $check_stmt->close();
        }
    }
    
    // Handle password change if requested
    if (!empty($current_password)) {
        // Verify current password
        $pwd_stmt = $conn->prepare("SELECT password FROM Users WHERE id = ?");
        if ($pwd_stmt === false) {
            $errors[] = "Database error: " . $conn->error;
        } else {
            $pwd_stmt->bind_param("i", $teacher_id);
            $pwd_stmt->execute();
            $pwd_result = $pwd_stmt->get_result();
            $user = $pwd_result->fetch_assoc();
            
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Current password is incorrect.";
            } elseif (empty($new_password)) {
                $errors[] = "New password is required.";
            } elseif (strlen($new_password) < 6) {
                $errors[] = "New password must be at least 6 characters.";
            } elseif ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match.";
            }
            $pwd_stmt->close();
        }
    }
    
    // Handle profile image upload
    $profile_image = $teacher['profile_image']; // Default to current image
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        } else {
            $upload_dir = '../uploads/profile_images/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = 'teacher_' . $teacher_id . '_' . time() . '_' . basename($_FILES['profile_image']['name']);
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                $profile_image = 'uploads/profile_images/' . $file_name;
                
                // Delete old profile image if it exists and is not the default
                if (!empty($teacher['profile_image']) && $teacher['profile_image'] !== 'assets/img/default-profile.jpg' && file_exists('../' . $teacher['profile_image'])) {
                    unlink('../' . $teacher['profile_image']);
                }
            } else {
                $errors[] = "Failed to upload profile image.";
            }
        }
    }
    
    // Update profile if no errors
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update Users table
            $update_user_stmt = $conn->prepare("
                UPDATE Users 
                SET full_name = ?, email = ?, phone = ?, profile_image = ?
                WHERE id = ?
            ");
            if ($update_user_stmt === false) {
                throw new Exception("Error preparing user update statement: " . $conn->error);
            }
            $update_user_stmt->bind_param("ssssi", $name, $email, $phone, $profile_image, $teacher_id);
            $update_user_stmt->execute();
            $update_user_stmt->close();
            
            // Check if Teachers table has a record for this user
            $check_teacher_stmt = $conn->prepare("SELECT user_id FROM Teachers WHERE user_id = ?");
            if ($check_teacher_stmt === false) {
                throw new Exception("Error checking teacher record: " . $conn->error);
            }
            $check_teacher_stmt->bind_param("i", $teacher_id);
            $check_teacher_stmt->execute();
            $check_teacher_result = $check_teacher_stmt->get_result();
            $teacher_exists = $check_teacher_result->num_rows > 0;
            $check_teacher_stmt->close();
            
            if ($teacher_exists) {
                // Update Teachers table - removed bio field
                $update_teacher_stmt = $conn->prepare("
                    UPDATE Teachers 
                    SET qualification = ?, experience = ?
                    WHERE user_id = ?
                ");
                if ($update_teacher_stmt === false) {
                    throw new Exception("Error preparing teacher update statement: " . $conn->error);
                }
                $update_teacher_stmt->bind_param("ssi", $qualification, $experience, $teacher_id);
                $update_teacher_stmt->execute();
                $update_teacher_stmt->close();
            } else {
                // Insert into Teachers table - removed bio field
                $insert_teacher_stmt = $conn->prepare("
                    INSERT INTO Teachers (user_id, qualification, experience, joining_date)
                    VALUES (?, ?, ?, CURRENT_DATE())
                ");
                if ($insert_teacher_stmt === false) {
                    throw new Exception("Error preparing teacher insert statement: " . $conn->error);
                }
                $insert_teacher_stmt->bind_param("iss", $teacher_id, $qualification, $experience);
                $insert_teacher_stmt->execute();
                $insert_teacher_stmt->close();
            }
            
            // Update password if requested
            if (!empty($current_password) && !empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_pwd_stmt = $conn->prepare("UPDATE Users SET password = ? WHERE id = ?");
                if ($update_pwd_stmt === false) {
                    throw new Exception("Error preparing password update statement: " . $conn->error);
                }
                $update_pwd_stmt->bind_param("si", $hashed_password, $teacher_id);
                $update_pwd_stmt->execute();
                $update_pwd_stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Profile updated successfully.";
            
            // Update session variables
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            
            // Refresh page to show updated data
            header("Location: teacher_profile.php");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error'] = "Failed to update profile: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile - Teacher Dashboard</title>
    <link rel="stylesheet" href="../css/tailwind.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'includes/teacher_topbar.php'; ?>
    
    <div class="flex">
        <?php include 'includes/teacher_sidebar.php'; ?>
        
        <div class="w-full p-4 md:ml-64">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">My Profile</h1>
                <p class="text-gray-600">View and update your profile information</p>
            </div>
            
            <!-- Display Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="md:flex">
                    <!-- Profile Image Section -->
                    <div class="md:w-1/3 bg-gray-50 p-6 border-r">
                        <div class="text-center">
                            <div class="mb-4">
                                <img src="../<?php echo !empty($teacher['profile_image']) ? $teacher['profile_image'] : 'assets/img/default-profile.jpg'; ?>" alt="Profile Image" class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-white shadow">
                            </div>
                            <h2 class="text-xl font-semibold"><?php echo isset($teacher['full_name']) ? htmlspecialchars($teacher['full_name']) : 'Teacher Name'; ?></h2>
                            <p class="text-gray-600">Teacher</p>
                            <p class="text-gray-500 mt-1"><?php echo isset($teacher['email']) ? htmlspecialchars($teacher['email']) : 'email@example.com'; ?></p>
                            <?php if (isset($teacher['phone']) && !empty($teacher['phone'])): ?>
                                <p class="text-gray-500"><?php echo htmlspecialchars($teacher['phone']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-6">
                            <h3 class="text-lg font-semibold mb-2">Qualifications</h3>
                            <p><?php echo isset($teacher['qualification']) && !empty($teacher['qualification']) ? htmlspecialchars($teacher['qualification']) : 'Not specified'; ?></p>
                            
                            <h3 class="text-lg font-semibold mt-4 mb-2">Experience</h3>
                            <p><?php echo isset($teacher['experience']) && !empty($teacher['experience']) ? htmlspecialchars($teacher['experience']) : 'Not specified'; ?></p>
                            
                            <h3 class="text-lg font-semibold mt-4 mb-2">Joining Date</h3>
                            <p><?php echo isset($teacher['joining_date']) && !empty($teacher['joining_date']) ? date('d M Y', strtotime($teacher['joining_date'])) : 'Not specified'; ?></p>
                        </div>
                    </div>
                    
                    <!-- Profile Edit Form -->
                    <div class="md:w-2/3 p-6">
                        <h2 class="text-xl font-semibold mb-4">Edit Profile</h2>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="grid md:grid-cols-2 gap-4">
                                <div class="mb-4">
                                    <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Full Name:</label>
                                    <input type="text" id="name" name="name" value="<?php echo isset($teacher['full_name']) ? htmlspecialchars($teacher['full_name']) : ''; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                                    <input type="email" id="email" name="email" value="<?php echo isset($teacher['email']) ? htmlspecialchars($teacher['email']) : ''; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="phone" class="block text-gray-700 text-sm font-bold mb-2">Phone:</label>
                                    <input type="text" id="phone" name="phone" value="<?php echo isset($teacher['phone']) ? htmlspecialchars($teacher['phone']) : ''; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="qualification" class="block text-gray-700 text-sm font-bold mb-2">Qualification:</label>
                                    <input type="text" id="qualification" name="qualification" value="<?php echo isset($teacher['qualification']) ? htmlspecialchars($teacher['qualification']) : ''; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="experience" class="block text-gray-700 text-sm font-bold mb-2">Experience:</label>
                                    <input type="text" id="experience" name="experience" value="<?php echo isset($teacher['experience']) ? htmlspecialchars($teacher['experience']) : ''; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="profile_image" class="block text-gray-700 text-sm font-bold mb-2">Profile Image:</label>
                                    <input type="file" id="profile_image" name="profile_image" class="w-full py-2 px-3 text-gray-700 leading-tight">
                                    <p class="text-xs text-gray-500 mt-1">Upload a new profile image (JPG, PNG, or GIF)</p>
                                </div>
                            </div>
                            
                            <hr class="my-6">
                            
                            <h3 class="text-lg font-semibold mb-4">Change Password</h3>
                            <p class="text-sm text-gray-500 mb-4">Leave blank if you don't want to change your password</p>
                            
                            <div class="grid md:grid-cols-2 gap-4">
                                <div class="mb-4">
                                    <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Current Password:</label>
                                    <input type="password" id="current_password" name="current_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div></div>
                                
                                <div class="mb-4">
                                    <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password:</label>
                                    <input type="password" id="new_password" name="new_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password:</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-end mt-6">
                                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                    <i class="fas fa-save mr-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
</body>
</html>
