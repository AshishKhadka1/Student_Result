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

// Get teacher details
$query = "
    SELECT u.full_name, u.email, u.phone, u.profile_image, 
           t.qualification, t.experience, t.joining_date
    FROM users u
    LEFT JOIN teachers t ON u.user_id = t.user_id
    WHERE u.user_id = ?
";

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
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
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
        $pwd_stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
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
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, profile_image = ?
                WHERE user_id = ?
            ");
            if ($update_user_stmt === false) {
                throw new Exception("Error preparing user update statement: " . $conn->error);
            }
            $update_user_stmt->bind_param("ssssi", $name, $email, $phone, $profile_image, $teacher_id);
            $update_user_stmt->execute();
            $update_user_stmt->close();

            // Check if Teachers table has a record for this user
            $check_teacher_stmt = $conn->prepare("SELECT user_id FROM teachers WHERE user_id = ?");
            if ($check_teacher_stmt === false) {
                throw new Exception("Error checking teacher record: " . $conn->error);
            }
            $check_teacher_stmt->bind_param("i", $teacher_id);
            $check_teacher_stmt->execute();
            $check_teacher_result = $check_teacher_stmt->get_result();
            $teacher_exists = $check_teacher_result->num_rows > 0;
            $check_teacher_stmt->close();

            if ($teacher_exists) {
                // Update Teachers table
                $update_teacher_stmt = $conn->prepare("
                    UPDATE teachers 
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
                // Insert into Teachers table
                $insert_teacher_stmt = $conn->prepare("
                    INSERT INTO teachers (user_id, qualification, experience, joining_date)
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
                $update_pwd_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
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

// Get assigned subjects
$subjects_query = "
    SELECT s.subject_name, c.class_name, c.section
    FROM teacher_subjects ts
    JOIN subjects s ON ts.subject_id = s.subject_id
    JOIN classes c ON ts.class_id = c.class_id
    WHERE ts.teacher_id = ?
    ORDER BY c.class_name, s.subject_name
";

$subjects_stmt = $conn->prepare($subjects_query);
if ($subjects_stmt) {
    $subjects_stmt->bind_param("i", $teacher_id);
    $subjects_stmt->execute();
    $subjects_result = $subjects_stmt->get_result();
    $assigned_subjects = $subjects_result->fetch_all(MYSQLI_ASSOC);
    $subjects_stmt->close();
} else {
    $assigned_subjects = [];
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

    <!-- Root layout: full screen height with flex -->
    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar: hidden on small screens, fixed on larger -->
        <div class="hidden md:block fixed inset-y-0 left-0 w-64 z-30">
            <?php include('./includes/teacher_sidebar.php'); ?>
        </div>

        <!-- Main content area (shifted right when sidebar is visible) -->
        <div class="flex flex-col flex-1 md:ml-64 w-full">

            <!-- Main section with scrollable content -->
            <main class="flex-1 overflow-y-auto p-6">

                <!-- Centered container for page content -->
                <div class="max-w-7xl mx-auto">

                    <!-- Header -->
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">My Profile</h1>
                        <p class="text-gray-600">Manage your personal information</p>
                    </div>

                    <!-- Alerts -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
                            <div class="flex justify-between items-center">
                                <span><?php echo $_SESSION['success']; ?></span>
                                <button onclick="this.parentElement.parentElement.remove()" class="text-green-700 hover:text-green-900">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                            <div class="flex justify-between items-center">
                                <span><?php echo $_SESSION['error']; ?></span>
                                <button onclick="this.parentElement.parentElement.remove()" class="text-red-700 hover:text-red-900">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Profile Card -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-lg shadow p-6">
                                <div class="text-center">
                                    <div class="mb-4">
                                        <img
                                            src="../<?php echo !empty($teacher['profile_image']) ? $teacher['profile_image'] : 'assets/img/default-profile.jpg'; ?>"
                                            alt="Profile Image"
                                            class="w-24 h-24 rounded-full mx-auto object-cover border-4 border-gray-200"
                                            onerror="this.src='../assets/img/default-profile.jpg';">
                                    </div>
                                    <h2 class="text-xl font-semibold text-gray-800"><?php echo isset($teacher['full_name']) ? htmlspecialchars($teacher['full_name']) : 'Teacher Name'; ?></h2>
                                    <p class="text-gray-600">Teacher</p>
                                    <p class="text-sm text-gray-500 mt-2"><?php echo isset($teacher['email']) ? htmlspecialchars($teacher['email']) : 'email@example.com'; ?></p>
                                    <?php if (isset($teacher['phone']) && !empty($teacher['phone'])): ?>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($teacher['phone']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-6 space-y-4">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Qualification</h4>
                                        <p class="text-gray-800"><?php echo isset($teacher['qualification']) && !empty($teacher['qualification']) ? htmlspecialchars($teacher['qualification']) : 'Not specified'; ?></p>
                                    </div>

                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Experience</h4>
                                        <p class="text-gray-800"><?php echo isset($teacher['experience']) && !empty($teacher['experience']) ? htmlspecialchars($teacher['experience']) : 'Not specified'; ?></p>
                                    </div>

                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Joining Date</h4>
                                        <p class="text-gray-800"><?php echo isset($teacher['joining_date']) && !empty($teacher['joining_date']) ? date('d M Y', strtotime($teacher['joining_date'])) : 'Not specified'; ?></p>
                                    </div>
                                </div>

                                <!-- Quick Stats -->
                                <div class="mt-6 pt-6 border-t border-gray-200">
                                    <div class="grid grid-cols-2 gap-4 text-center">
                                        <div>
                                            <div class="text-2xl font-bold text-blue-600"><?php echo count($assigned_subjects); ?></div>
                                            <div class="text-xs text-gray-500">Subjects</div>
                                        </div>
                                        <div>
                                            <div class="text-2xl font-bold text-green-600">
                                                <?php
                                                // Count unique classes
                                                $classes = [];
                                                foreach ($assigned_subjects as $subject) {
                                                    $class_key = $subject['class_name'] . '-' . $subject['section'];
                                                    $classes[$class_key] = true;
                                                }
                                                echo count($classes);
                                                ?>
                                            </div>
                                            <div class="text-xs text-gray-500">Classes</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Edit Profile Form -->
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Profile</h3>

                                <form method="POST" action="" enctype="multipart/form-data">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                        <div>
                                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                            <input type="text" id="name" name="name" value="<?php echo isset($teacher['full_name']) ? htmlspecialchars($teacher['full_name']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        </div>

                                        <div>
                                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                            <input type="email" id="email" name="email" value="<?php echo isset($teacher['email']) ? htmlspecialchars($teacher['email']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        </div>

                                        <div>
                                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                            <input type="text" id="phone" name="phone" value="<?php echo isset($teacher['phone']) ? htmlspecialchars($teacher['phone']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>

                                        <div>
                                            <label for="qualification" class="block text-sm font-medium text-gray-700 mb-1">Qualification</label>
                                            <input type="text" id="qualification" name="qualification" value="<?php echo isset($teacher['qualification']) ? htmlspecialchars($teacher['qualification']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>

                                        <div>
                                            <label for="experience" class="block text-sm font-medium text-gray-700 mb-1">Experience</label>
                                            <input type="text" id="experience" name="experience" value="<?php echo isset($teacher['experience']) ? htmlspecialchars($teacher['experience']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>

                                        <div>
                                            <label for="profile_image" class="block text-sm font-medium text-gray-700 mb-1">Profile Image</label>
                                            <input type="file" id="profile_image" name="profile_image" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" accept="image/jpeg,image/png,image/gif">
                                            <p class="text-xs text-gray-500 mt-1">JPG, PNG or GIF. Max 2MB.</p>
                                        </div>
                                    </div>

                                    <!-- Password Change Section -->
                                    <div class="border-t border-gray-200 pt-6">
                                        <h4 class="text-md font-medium text-gray-700 mb-4">Change Password (Optional)</h4>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                                <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>

                                            <div>
                                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                                <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>

                                            <div>
                                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                                                <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-end mt-6">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium">
                                            <i class="fas fa-save mr-2"></i>Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Assigned Subjects -->
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">My Subjects</h3>

                                <?php if (count($assigned_subjects) > 0): ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($assigned_subjects as $subject): ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($subject['class_name'] . ' ' . $subject['section']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <i class="fas fa-book-open text-3xl mb-3"></i>
                                        <p>No subjects assigned yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
</body>

</html>