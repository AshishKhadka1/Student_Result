<?php
session_start();
include_once('../includes/db_connetc.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Process class creation
if (isset($_POST['create_class'])) {
    $class_name = mysqli_real_escape_string($conn, $_POST['class_name']);
    $class_numeric = mysqli_real_escape_string($conn, $_POST['class_numeric']);
    $class_teacher = mysqli_real_escape_string($conn, $_POST['class_teacher']);
    $academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);

    // Check if class already exists
    $check_query = "SELECT * FROM classes WHERE class_name = '$class_name' AND academic_year = '$academic_year'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $error_msg = "Class already exists for the selected academic year!";
    } else {
        $insert_query = "INSERT INTO classes (class_name, class_numeric, class_teacher_id, academic_year, created_at) 
                        VALUES ('$class_name', '$class_numeric', '$class_teacher', '$academic_year', NOW())";

        if (mysqli_query($conn, $insert_query)) {
            $success_msg = "Class created successfully!";
        } else {
            $error_msg = "Error creating class: " . mysqli_error($conn);
        }
    }
}

// Process class update
if (isset($_POST['update_class'])) {
    $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
    $class_name = mysqli_real_escape_string($conn, $_POST['class_name']);
    $class_numeric = mysqli_real_escape_string($conn, $_POST['class_numeric']);
    $class_teacher = mysqli_real_escape_string($conn, $_POST['class_teacher']);
    $academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);

    // Check if class already exists (excluding the current class)
    $check_query = "SELECT * FROM classes WHERE class_name = '$class_name' AND academic_year = '$academic_year' AND class_id != '$class_id'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $error_msg = "Another class with the same name already exists for the selected academic year!";
    } else {
        $update_query = "UPDATE classes SET 
                        class_name = '$class_name', 
                        class_numeric = '$class_numeric', 
                        class_teacher_id = '$class_teacher', 
                        academic_year = '$academic_year', 
                        updated_at = NOW() 
                        WHERE class_id = '$class_id'";

        if (mysqli_query($conn, $update_query)) {
            $success_msg = "Class updated successfully!";
        } else {
            $error_msg = "Error updating class: " . mysqli_error($conn);
        }
    }
}

// Process section creation
if (isset($_POST['create_section'])) {
    $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
    $section_name = mysqli_real_escape_string($conn, $_POST['section_name']);
    $section_capacity = mysqli_real_escape_string($conn, $_POST['section_capacity']);
    $section_teacher = mysqli_real_escape_string($conn, $_POST['section_teacher']);

    // Check if section already exists for this class
    $check_query = "SELECT * FROM sections WHERE class_id = '$class_id' AND section_name = '$section_name'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $error_msg = "Section already exists for the selected class!";
    } else {
        $insert_query = "INSERT INTO sections (class_id, section_name, capacity, teacher_id, created_at) 
                        VALUES ('$class_id', '$section_name', '$section_capacity', '$section_teacher', NOW())";

        if (mysqli_query($conn, $insert_query)) {
            $success_msg = "Section created successfully!";
        } else {
            $error_msg = "Error creating section: " . mysqli_error($conn);
        }
    }
}

// Process section update
if (isset($_POST['update_section'])) {
    $section_id = mysqli_real_escape_string($conn, $_POST['section_id']);
    $section_name = mysqli_real_escape_string($conn, $_POST['section_name']);
    $section_capacity = mysqli_real_escape_string($conn, $_POST['section_capacity']);
    $section_teacher = mysqli_real_escape_string($conn, $_POST['section_teacher']);
    $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);

    // Check if section already exists (excluding the current section)
    $check_query = "SELECT * FROM sections WHERE class_id = '$class_id' AND section_name = '$section_name' AND id != '$section_id'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $error_msg = "Another section with the same name already exists for the selected class!";
    } else {
        $update_query = "UPDATE sections SET 
                        section_name = '$section_name', 
                        capacity = '$section_capacity', 
                        teacher_id = '$section_teacher', 
                        updated_at = NOW() 
                        WHERE id = '$section_id'";

        if (mysqli_query($conn, $update_query)) {
            $success_msg = "Section updated successfully!";
        } else {
            $error_msg = "Error updating section: " . mysqli_error($conn);
        }
    }
}

// Delete class
if (isset($_GET['delete_class'])) {
    $class_id = mysqli_real_escape_string($conn, $_GET['delete_class']);

    // Check if there are students in this class
    $check_query = "SELECT COUNT(*) as student_count FROM students WHERE class_id = '$class_id'";
    $check_result = mysqli_query($conn, $check_query);
    $row = mysqli_fetch_assoc($check_result);

    if ($row['student_count'] > 0) {
        $error_msg = "Cannot delete class. There are students assigned to this class!";
    } else {
        // Delete sections first
        $delete_sections = "DELETE FROM sections WHERE class_id = '$class_id'";
        mysqli_query($conn, $delete_sections);

        // Delete class
        $delete_query = "DELETE FROM classes WHERE class_id = '$class_id'";
        if (mysqli_query($conn, $delete_query)) {
            $success_msg = "Class deleted successfully!";
        } else {
            $error_msg = "Error deleting class: " . mysqli_error($conn);
        }
    }
}

// Delete section
if (isset($_GET['delete_section'])) {
    $section_id = mysqli_real_escape_string($conn, $_GET['delete_section']);

    // Check if there are students in this section
    $check_query = "SELECT COUNT(*) as student_count FROM students WHERE section_id = '$section_id'";
    $check_result = mysqli_query($conn, $check_query);
    $row = mysqli_fetch_assoc($check_result);

    if ($row['student_count'] > 0) {
        $error_msg = "Cannot delete section. There are students assigned to this section!";
    } else {
        $delete_query = "DELETE FROM sections WHERE id = '$section_id'";
        if (mysqli_query($conn, $delete_query)) {
            $success_msg = "Section deleted successfully!";
        } else {
            $error_msg = "Error deleting section: " . mysqli_error($conn);
        }
    }
}

// Load class for editing
$edit_class = null;
if (isset($_GET['edit_class'])) {
    $class_id = mysqli_real_escape_string($conn, $_GET['edit_class']);
    $query = "SELECT * FROM classes WHERE class_id = '$class_id'";
    $result = mysqli_query($conn, $query);
    $edit_class = mysqli_fetch_assoc($result);
}

// Load section for editing
$edit_section = null;
if (isset($_GET['edit_section'])) {
    $section_id = mysqli_real_escape_string($conn, $_GET['edit_section']);
    $query = "SELECT * FROM sections WHERE id = '$section_id'";
    $result = mysqli_query($conn, $query);
    $edit_section = mysqli_fetch_assoc($result);
}

// Get all teachers for dropdown
$teachers_query = "SELECT user_id, full_name FROM users WHERE role = 'teacher'";
$teachers_result = mysqli_query($conn, $teachers_query);

// Get all classes
$classes_query = "SELECT c.*, u.full_name 
                 FROM classes c 
                 LEFT JOIN users u ON c.class_teacher_id = u.user_id 
                 ORDER BY c.class_numeric ASC";
$classes_result = mysqli_query($conn, $classes_query);

// Get academic years
$years_query = "SELECT DISTINCT academic_year FROM academic_years ORDER BY academic_year DESC";
$years_result = mysqli_query($conn, $years_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - Result Management System</title>
    <link rel="stylesheet" href="../css/tailwind.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 overflow-hidden">
        <!-- Top Navigation -->
            <?php
        // Include the file that processes form data
        include 'topBar.php';
        ?>

        <!-- Main Content Area -->
        <main class="flex-1 relative overflow-y-auto focus:outline-none w-full">
            <div class="py-6">
                <div class="w-full px-4 sm:px-6 md:px-8">
                    <h1 class="text-2xl font-semibold text-gray-900">Manage Classes & Sections</h1>
                </div>
                <div class="w-full px-4 sm:px-6 md:px-8">
                        <!-- Notification Messages -->
                        <?php if (isset($success_msg)): ?>
                            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4 rounded" role="alert">
                                <p><?php echo $success_msg; ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($error_msg)): ?>
                            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded" role="alert">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700">
                                            <?php echo $error_msg; ?>
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

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- Create/Edit Class Card -->
                            <div class="bg-white shadow rounded-lg p-6 hover-scale">
                                <h2 class="text-lg font-medium text-gray-900 mb-4">
                                    <?php echo $edit_class ? 'Edit Class' : 'Create New Class'; ?>
                                </h2>
                                <form action="" method="POST">
                                    <?php if ($edit_class): ?>
                                        <input type="hidden" name="class_id" value="<?php echo $edit_class['class_id']; ?>">
                                    <?php endif; ?>

                                    <div class="mb-4">
                                        <label for="class_name" class="block text-sm font-medium text-gray-700 mb-1">Class Name*</label>
                                        <input type="text" name="class_name" id="class_name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" value="<?php echo $edit_class ? $edit_class['class_name'] : ''; ?>" required>
                                        <p class="mt-1 text-sm text-gray-500">Example: Class 10, Grade 12, etc.</p>
                                    </div>

                                    <div class="mb-4">
                                        <label for="class_numeric" class="block text-sm font-medium text-gray-700 mb-1">Class Numeric Value*</label>
                                        <input type="number" name="class_numeric" id="class_numeric" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" value="<?php echo $edit_class ? $edit_class['class_numeric'] : ''; ?>" required>
                                        <p class="mt-1 text-sm text-gray-500">For sorting purposes (e.g., 10 for Class 10)</p>
                                    </div>

                                    <div class="mb-4">
                                        <label for="class_teacher" class="block text-sm font-medium text-gray-700 mb-1">Class Teacher</label>
                                        <select name="class_teacher" id="class_teacher" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <option value="">Select Class Teacher</option>
                                            <?php
                                            mysqli_data_seek($teachers_result, 0);
                                            while ($teacher = mysqli_fetch_assoc($teachers_result)):
                                                $selected = ($edit_class && $edit_class['class_teacher_id'] == $teacher['user_id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $teacher['user_id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo $teacher['full_name']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year*</label>
                                        <select name="academic_year" id="academic_year" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            <?php
                                            mysqli_data_seek($years_result, 0);
                                            while ($year = mysqli_fetch_assoc($years_result)):
                                                $selected = ($edit_class && $edit_class['academic_year'] == $year['academic_year']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $year['academic_year']; ?>" <?php echo $selected; ?>>
                                                    <?php echo $year['academic_year']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="flex items-center justify-end">
                                        <?php if ($edit_class): ?>
                                            <a href="classes.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-times mr-2"></i> Cancel
                                            </a>
                                            <button type="submit" name="update_class" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-save mr-2"></i> Update Class
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="create_class" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-plus mr-2"></i> Create Class
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <!-- Create/Edit Section Card -->
                            <div class="bg-white shadow rounded-lg p-6 hover-scale">
                                <h2 class="text-lg font-medium text-gray-900 mb-4">
                                    <?php echo $edit_section ? 'Edit Section' : 'Create New Section'; ?>
                                </h2>
                                <form action="" method="POST">
                                    <?php if ($edit_section): ?>
                                        <input type="hidden" name="section_id" value="<?php echo $edit_section['id']; ?>">
                                    <?php endif; ?>

                                    <div class="mb-4">
                                        <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Select Class*</label>
                                        <select name="class_id" id="class_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            <option value="">Select Class</option>
                                            <?php
                                            mysqli_data_seek($classes_result, 0);
                                            while ($class = mysqli_fetch_assoc($classes_result)):
                                                $selected = ($edit_section && $edit_section['class_id'] == $class['class_id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $class['class_id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo $class['class_name'] . ' (' . $class['academic_year'] . ')'; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label for="section_name" class="block text-sm font-medium text-gray-700 mb-1">Section Name*</label>
                                        <input type="text" name="section_name" id="section_name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" value="<?php echo $edit_section ? $edit_section['section_name'] : ''; ?>" required>
                                        <p class="mt-1 text-sm text-gray-500">Example: A, B, Science, Commerce, etc.</p>
                                    </div>

                                    <div class="mb-4">
                                        <label for="section_capacity" class="block text-sm font-medium text-gray-700 mb-1">Section Capacity</label>
                                        <input type="number" name="section_capacity" id="section_capacity" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" value="<?php echo $edit_section ? $edit_section['capacity'] : ''; ?>">
                                        <p class="mt-1 text-sm text-gray-500">Maximum number of students</p>
                                    </div>

                                    <div class="mb-4">
                                        <label for="section_teacher" class="block text-sm font-medium text-gray-700 mb-1">Section Teacher</label>
                                        <select name="section_teacher" id="section_teacher" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <option value="">Select Section Teacher</option>
                                            <?php
                                            mysqli_data_seek($teachers_result, 0);
                                            while ($teacher = mysqli_fetch_assoc($teachers_result)):
                                                $selected = ($edit_section && $edit_section['teacher_id'] == $teacher['user_id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $teacher['user_id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo $teacher['full_name']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="flex items-center justify-end">
                                        <?php if ($edit_section): ?>
                                            <a href="classes.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-times mr-2"></i> Cancel
                                            </a>
                                            <button type="submit" name="update_section" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-save mr-2"></i> Update Section
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="create_section" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-plus mr-2"></i> Create Section
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Classes List -->
                        <div class="bg-white shadow rounded-lg overflow-hidden hover-scale">
                            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                                <h3 class="text-lg font-medium text-gray-900">Existing Classes</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Manage your classes and sections here.
                                </p>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numeric Value</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class Teacher</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sections</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php
                                            mysqli_data_seek($classes_result, 0);
                                            if (mysqli_num_rows($classes_result) > 0) {
                                                while ($class = mysqli_fetch_assoc($classes_result)):
                                                    // Get sections for this class
                                                    $class_id = $class['class_id'];
                                                    $sections_query = "SELECT s.*, u.full_name 
                                                                      FROM sections s 
                                                                      LEFT JOIN users u ON s.teacher_id = u.user_id 
                                                                      WHERE s.class_id = '$class_id'";
                                                    $sections_result = mysqli_query($conn, $sections_query);
                                                    $sections_count = mysqli_num_rows($sections_result);
                                            ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $class['class_name']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $class['class_numeric']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            <?php
                                                            if ($class['full_name']) {
                                                                echo $class['full_name'];
                                                            } else {
                                                                echo '<span class="text-gray-400">Not Assigned</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $class['academic_year']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                                <?php echo $sections_count; ?> section(s)
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                            <div class="flex space-x-2">
                                                                <a href="?edit_class=<?php echo $class_id; ?>" class="text-blue-600 hover:text-blue-900">
                                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                                </a>
                                                                <button type="button" onclick="toggleSections('sections-<?php echo $class_id; ?>')" class="text-green-600 hover:text-green-900">
                                                                    <i class="fas fa-eye mr-1"></i> View Sections
                                                                </button>
                                                                <a href="?delete_class=<?php echo $class_id; ?>" onclick="return confirm('Are you sure you want to delete this class? This will also delete all sections in this class.')" class="text-red-600 hover:text-red-900">
                                                                    <i class="fas fa-trash mr-1"></i> Delete
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <!-- Sections for this class -->
                                                    <tr id="sections-<?php echo $class_id; ?>" class="hidden bg-gray-50">
                                                        <td colspan="6" class="py-3 px-4 border-b border-gray-200">
                                                            <div class="pl-4 border-l-2 border-blue-500">
                                                                <h3 class="font-semibold text-sm text-gray-700 mb-2">Sections for <?php echo $class['class_name']; ?></h3>
                                                                <?php if ($sections_count > 0): ?>
                                                                    <table class="min-w-full divide-y divide-gray-200">
                                                                        <thead class="bg-gray-50">
                                                                            <tr>
                                                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section Name</th>
                                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="bg-white divide-y divide-gray-200">
                                                                <?php while ($section = mysqli_fetch_assoc($sections_result)): ?>
                                                                    <tr class="hover:bg-gray-50">
                                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $section['section_name']; ?></td>
                                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                            <?php
                                                                            if ($section['capacity']) {
                                                                                echo $section['capacity'];
                                                                            } else {
                                                                                echo '<span class="text-gray-400">Not Set</span>';
                                                                            }
                                                                            ?>
                                                                        </td>
                                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                            <?php
                                                                            if ($section['full_name']) {
                                                                                echo $section['full_name'];
                                                                            } else {
                                                                                echo '<span class="text-gray-400">Not Assigned</span>';
                                                                            }
                                                                            ?>
                                                                        </td>
                                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                                            <div class="flex space-x-2">
                                                                                <a href="?edit_section=<?php echo $section['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                                                    <i class="fas fa-edit"></i> Edit
                                                                                </a>
                                                                                <a href="?delete_section=<?php echo $section['id']; ?>" onclick="return confirm('Are you sure you want to delete this section?')" class="text-red-600 hover:text-red-900">
                                                                                    <i class="fas fa-trash"></i> Delete
                                                                                </a>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php endwhile; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php else: ?>
                                                        <p class="text-sm text-gray-500">No sections created for this class yet.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php
                                    endwhile;
                                } else {
                                    ?>
                                    <tr>
                                        <td colspan="6" class="py-4 px-4 text-center text-gray-500">No classes found. Create your first class using the form above.</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
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
        });
        
        // Toggle sections visibility
        function toggleSections(id) {
            const element = document.getElementById(id);
            if (element) {
                if (element.classList.contains('hidden')) {
                    element.classList.remove('hidden');
                } else {
                    element.classList.add('hidden');
                }
            } else {
                console.error("Element with ID " + id + " not found");
            }
        }
    </script>
</body>

</html>
