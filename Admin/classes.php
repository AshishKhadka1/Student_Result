<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Add new class
        if ($_POST['action'] == 'add_class') {
            $class_name = $_POST['class_name'];
            $section = $_POST['section'];
            $academic_year = $_POST['academic_year'];
            $description = $_POST['description'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO classes (class_name, section, academic_year, description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $class_name, $section, $academic_year, $description);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Class added successfully!";
            } else {
                $_SESSION['error'] = "Error adding class: " . $conn->error;
            }
            $stmt->close();
        }
        
        // Update class
        elseif ($_POST['action'] == 'update_class') {
            $class_id = $_POST['class_id'];
            $class_name = $_POST['class_name'];
            $section = $_POST['section'];
            $academic_year = $_POST['academic_year'];
            $description = $_POST['description'] ?? '';
            
            $stmt = $conn->prepare("UPDATE classes SET class_name = ?, section = ?, academic_year = ?, description = ? WHERE class_id = ?");
            $stmt->bind_param("ssssi", $class_name, $section, $academic_year, $description, $class_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Class updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating class: " . $conn->error;
            }
            $stmt->close();
        }
        
        // Delete class
        elseif ($_POST['action'] == 'delete_class') {
            $class_id = $_POST['class_id'];
            
            // Check if there are students in this class
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE class_id = ?");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $student_count = $result->fetch_assoc()['count'];
            $stmt->close();
            
            if ($student_count > 0) {
                $_SESSION['error'] = "Cannot delete class. There are students assigned to this class.";
            } else {
                $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
                $stmt->bind_param("i", $class_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Class deleted successfully!";
                } else {
                    $_SESSION['error'] = "Error deleting class: " . $conn->error;
                }
                $stmt->close();
            }
        }
        
        // Assign students to class
        elseif ($_POST['action'] == 'assign_students') {
            $class_id = $_POST['class_id'];
            $student_ids = $_POST['student_ids'] ?? [];
            
            if (!empty($student_ids)) {
                // Begin transaction
                $conn->begin_transaction();
                try {
                    // First, remove all students from this class
                    $stmt = $conn->prepare("UPDATE students SET class_id = NULL WHERE class_id = ?");
                    $stmt->bind_param("i", $class_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Then, assign selected students to this class
                    $stmt = $conn->prepare("UPDATE students SET class_id = ? WHERE student_id = ?");
                    foreach ($student_ids as $student_id) {
                        $stmt->bind_param("is", $class_id, $student_id);
                        $stmt->execute();
                    }
                    $stmt->close();
                    
                    // Commit transaction
                    $conn->commit();
                    $_SESSION['success'] = "Students assigned to class successfully!";
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $_SESSION['error'] = "Error assigning students: " . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = "No students selected for assignment.";
            }
        }
        
        // Redirect to prevent form resubmission
        header("Location: classes.php");
        exit();
    }
}

// Get all classes
$classes = [];
$result = $conn->query("SELECT * FROM classes ORDER BY academic_year DESC, class_name, section");
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

// Get all students
$students = [];
$result = $conn->query("SELECT s.student_id, s.roll_number, s.registration_number, s.class_id, u.full_name 
                        FROM students s 
                        JOIN users u ON s.user_id = u.user_id 
                        ORDER BY u.full_name");
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

// Get current academic year
$current_year = date('Y');
$next_year = $current_year + 1;
$current_academic_year = $current_year . '-' . $next_year;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 bg-gray-800">
                <div class="flex items-center justify-center h-16 bg-gray-900">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <div class="flex flex-col flex-grow px-4 mt-5">
                    <nav class="flex-1 space-y-1">
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            Results
                        </a>
                        <a href="bulk_upload.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-upload mr-3"></i>
                            Bulk Upload
                        </a>
                        <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-users mr-3"></i>
                            Users
                        </a>
                        <a href="classes.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-chalkboard mr-3"></i>
                            Classes
                        </a>
                        <a href="subject.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-book mr-3"></i>
                            Subjects
                        </a>
                        <a href="teachers.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard-teacher mr-3"></i>
                            Teachers
                        </a>
                        <a href="exams.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            Exams
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-cog mr-3"></i>
                            Settings
                        </a>
                    </nav>
                    <div class="flex-shrink-0 block w-full">
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
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
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            Results
                        </a>
                        <a href="bulk_upload.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-upload mr-3"></i>
                            Bulk Upload
                        </a>
                        <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-users mr-3"></i>
                            Users
                        </a>
                        <a href="classes.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-chalkboard mr-3"></i>
                            Classes
                        </a>
                        <a href="subject.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-book mr-3"></i>
                            Subjects
                        </a>
                        <a href="teachers.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard-teacher mr-3"></i>
                            Teachers
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-cog mr-3"></i>
                            Settings
                        </a>
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
                    </nav>
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
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Manage Classes</h1>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
                        <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <span class="sr-only">View notifications</span>
                            <i class="fas fa-bell"></i>
                        </button>

                        <!-- Profile dropdown -->
                        <div class="ml-3 relative">
                            <div>
                                <button type="button" class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="user-menu-button">
                                    <span class="sr-only">Open user menu</span>
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-600">
                                        <span class="text-sm font-medium leading-none text-white"><?php echo substr($_SESSION['full_name'], 0, 1); ?></span>
                                    </span>
                                </button>
                            </div>

                            <!-- Profile dropdown menu -->
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" id="user-menu" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Sign out</a>
                            </div>
                        </div>
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
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Add Class Section -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Add New Class</h2>
                            <form action="classes.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <input type="hidden" name="action" value="add_class">
                                
                                <div>
                                    <label for="class_name" class="block text-sm font-medium text-gray-700 mb-1">Class Name</label>
                                    <input type="text" id="class_name" name="class_name" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                </div>
                                
                                <div>
                                    <label for="section" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                    <input type="text" id="section" name="section" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                </div>
                                
                                <div>
                                    <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                                    <select id="academic_year" name="academic_year" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <?php 
                                        for ($i = 0; $i < 3; $i++) {
                                            $year = $current_year - $i;
                                            $next_year = $year + 1;
                                            $academic_year = $year . '-' . $next_year;
                                            $selected = ($academic_year == $current_academic_year) ? 'selected' : '';
                                            echo "<option value=\"$academic_year\" $selected>$academic_year</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea id="description" name="description" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-plus mr-2"></i> Add Class
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Classes Table -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                                <h2 class="text-lg font-medium text-gray-900">Class List</h2>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($classes)): ?>
                                            <tr>
                                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No classes found.</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($classes as $class): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $class['class_name']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $class['section']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $class['academic_year']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php 
                                                        // Count students in this class
                                                        $student_count = 0;
                                                        foreach ($students as $student) {
                                                            if ($student['class_id'] == $class['class_id']) {
                                                                $student_count++;
                                                            }
                                                        }
                                                        echo $student_count;
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button type="button" onclick="editClass(<?php echo $class['class_id']; ?>, '<?php echo $class['class_name']; ?>', '<?php echo $class['section']; ?>', '<?php echo $class['academic_year']; ?>', '<?php echo addslashes($class['description'] ?? ''); ?>')" class="text-blue-600 hover:text-blue-900 mr-3">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" onclick="assignStudents(<?php echo $class['class_id']; ?>, '<?php echo $class['class_name']; ?> <?php echo $class['section']; ?>')" class="text-green-600 hover:text-green-900 mr-3">
                                                            <i class="fas fa-user-plus"></i>
                                                        </button>
                                                        <form action="classes.php" method="POST" class="inline-block">
                                                            <input type="hidden" name="action" value="delete_class">
                                                            <input type="hidden" name="class_id" value="<?php echo $class['class_id']; ?>">
                                                            <button type="submit" onclick="return confirm('Are you sure you want to delete this class?')" class="text-red-600 hover:text-red-900">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Class Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Class</h3>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="classes.php" method="POST">
                <input type="hidden" name="action" value="update_class">
                <input type="hidden" name="class_id" id="edit_class_id">
                
                <div class="space-y-4">
                    <div>
                        <label for="edit_class_name" class="block text-sm font-medium text-gray-700 mb-1">Class Name</label>
                        <input type="text" id="edit_class_name" name="class_name" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <div>
                        <label for="edit_section" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <input type="text" id="edit_section" name="section" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <div>
                        <label for="edit_academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                        <select id="edit_academic_year" name="academic_year" required class="w-full rounded-md border-gray-300 shadow-sm focus  name="academic_year" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <select id="edit_academic_year" name="academic_year" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <?php 
                            for ($i = 0; $i < 3; $i++) {
                                $year = $current_year - $i;
                                $next_year = $year + 1;
                                $academic_year = $year . '-' . $next_year;
                                echo "<option value=\"$academic_year\">$academic_year</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="edit_description" name="description" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeModal('editModal')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Update Class
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Students Modal -->
    <div id="assignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Assign Students to <span id="assign_class_name"></span></h3>
                <button onclick="closeModal('assignModal')" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="classes.php" method="POST">
                <input type="hidden" name="action" value="assign_students">
                <input type="hidden" name="class_id" id="assign_class_id">
                
                <div class="space-y-4">
                    <div>
                        <label for="student_ids" class="block text-sm font-medium text-gray-700 mb-1">Select Students</label>
                        <select id="student_ids" name="student_ids[]" multiple class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>" data-class="<?php echo $student['class_id']; ?>">
                                    <?php echo $student['full_name'] . ' (' . $student['roll_number'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Hold Ctrl (or Cmd) to select multiple students</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeModal('assignModal')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Assign Students
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Function to edit class
        function editClass(classId, className, section, academicYear, description) {
            document.getElementById('edit_class_id').value = classId;
            document.getElementById('edit_class_name').value = className;
            document.getElementById('edit_section').value = section;
            
            // Set academic year dropdown
            const academicYearSelect = document.getElementById('edit_academic_year');
            for (let i = 0; i < academicYearSelect.options.length; i++) {
                if (academicYearSelect.options[i].value === academicYear) {
                    academicYearSelect.selectedIndex = i;
                    break;
                }
            }
            
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        // Function to assign students
        function assignStudents(classId, className) {
            document.getElementById('assign_class_id').value = classId;
            document.getElementById('assign_class_name').textContent = className;
            
            // Pre-select students already in this class
            const studentSelect = document.getElementById('student_ids');
            for (let i = 0; i < studentSelect.options.length; i++) {
                const option = studentSelect.options[i];
                option.selected = option.getAttribute('data-class') == classId;
            }
            
            document.getElementById('assignModal').classList.remove('hidden');
        }
        
        // Function to close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }
        
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.remove('-translate-x-full');
        });
        
        document.getElementById('close-sidebar').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });
        
        document.getElementById('sidebar-backdrop').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });
        
        // User menu toggle
        document.getElementById('user-menu-button').addEventListener('click', function() {
            document.getElementById('user-menu').classList.toggle('hidden');
        });
        
        // Initialize Select2 for better dropdown experience
        $(document).ready(function() {
            $('#student_ids').select2({
                placeholder: 'Select students',
                width: '100%'
            });
        });
    </script>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

</body>
</html>