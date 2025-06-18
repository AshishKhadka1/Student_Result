<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if Sections table exists, if not create it
$tableExists = $conn->query("SHOW TABLES LIKE 'Sections'");
if ($tableExists->num_rows == 0) {
    // Create Sections table
    $createTableSQL = "CREATE TABLE `Sections` (
        `section_id` int(11) NOT NULL AUTO_INCREMENT,
        `class_id` int(11) NOT NULL,
        `section_name` varchar(50) NOT NULL,
        `capacity` int(11) DEFAULT 40,
        `teacher_id` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`section_id`),
        UNIQUE KEY `unique_section_class` (`class_id`,`section_name`),
        KEY `class_id` (`class_id`),
        KEY `teacher_id` (`teacher_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($createTableSQL);
    $_SESSION['success'] = "Sections table created successfully.";
}

// Check if section_id column exists in Students table
$columnsResult = $conn->query("SHOW COLUMNS FROM Students LIKE 'section_id'");
if ($columnsResult->num_rows == 0) {
    // Add section_id column to Students table
    $alterTableSQL = "ALTER TABLE Students ADD COLUMN `section_id` INT(11) DEFAULT NULL";
    $conn->query($alterTableSQL);
    $_SESSION['success'] = "Added section_id column to Students table.";
}

// Process actions
if (isset($_POST['action'])) {
    // Add new class
    if ($_POST['action'] == 'add_class') {
        $class_name = trim($_POST['class_name']);
        $academic_year = trim($_POST['academic_year']);
        $description = trim($_POST['description'] ?? '');

        if (empty($class_name) || empty($academic_year)) {
            $_SESSION['error'] = "Class name and academic year are required.";
        } else {
            // Check if class already exists
            $stmt = $conn->prepare("SELECT class_id FROM Classes WHERE class_name = ? AND academic_year = ?");
            $stmt->bind_param("ss", $class_name, $academic_year);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Class already exists for this academic year.";
            } else {
                // Insert new class
                $stmt = $conn->prepare("INSERT INTO Classes (class_name, academic_year, description) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $class_name, $academic_year, $description);

                if ($stmt->execute()) {
                    $class_id = $conn->insert_id;

                    // Auto-create sections if specified
                    if (isset($_POST['auto_create_sections']) && $_POST['auto_create_sections'] == 1) {
                        $section_count = intval($_POST['section_count']);
                        $section_prefix = trim($_POST['section_prefix'] ?? '');

                        for ($i = 0; $i < $section_count; $i++) {
                            $section_name = $section_prefix . chr(65 + $i); // A, B, C, etc.
                            $stmt = $conn->prepare("INSERT INTO Sections (class_id, section_name) VALUES (?, ?)");
                            $stmt->bind_param("is", $class_id, $section_name);
                            $stmt->execute();
                        }

                        $_SESSION['success'] = "Class added successfully with $section_count sections.";
                    } else {
                        $_SESSION['success'] = "Class added successfully.";
                    }
                } else {
                    $_SESSION['error'] = "Error adding class: " . $conn->error;
                }
            }
            $stmt->close();
        }

        header("Location: classes.php");
        exit();
    }

    // Add new section
    if ($_POST['action'] == 'add_section') {
        $class_id = intval($_POST['class_id']);
        $section_name = trim($_POST['section_name']);
        $capacity = intval($_POST['capacity'] ?? 40);
        $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;

        if (empty($section_name) || $class_id <= 0) {
            $_SESSION['error'] = "Section name and valid class are required.";
        } else {
            // Check if section already exists for this class
            $stmt = $conn->prepare("SELECT section_id FROM Sections WHERE class_id = ? AND section_name = ?");
            $stmt->bind_param("is", $class_id, $section_name);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Section already exists for this class.";
            } else {
                // Insert new section
                $stmt = $conn->prepare("INSERT INTO Sections (class_id, section_name, capacity, teacher_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isii", $class_id, $section_name, $capacity, $teacher_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Section added successfully.";
                } else {
                    $_SESSION['error'] = "Error adding section: " . $conn->error;
                }
            }
            $stmt->close();
        }

        header("Location: classes.php?view_sections=" . $class_id);
        exit();
    }

    // Update class
    if ($_POST['action'] == 'update_class') {
        $class_id = intval($_POST['class_id']);
        $class_name = trim($_POST['class_name']);
        $academic_year = trim($_POST['academic_year']);
        $description = trim($_POST['description'] ?? '');

        if (empty($class_name) || empty($academic_year) || $class_id <= 0) {
            $_SESSION['error'] = "Class name, academic year, and valid class ID are required.";
        } else {
            // Check if class already exists (excluding current class)
            $stmt = $conn->prepare("SELECT class_id FROM Classes WHERE class_name = ? AND academic_year = ? AND class_id != ?");
            $stmt->bind_param("ssi", $class_name, $academic_year, $class_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Another class with this name and academic year already exists.";
            } else {
                // Update class
                $stmt = $conn->prepare("UPDATE Classes SET class_name = ?, academic_year = ?, description = ? WHERE class_id = ?");
                $stmt->bind_param("sssi", $class_name, $academic_year, $description, $class_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Class updated successfully.";
                } else {
                    $_SESSION['error'] = "Error updating class: " . $conn->error;
                }
            }
            $stmt->close();
        }

        header("Location: classes.php");
        exit();
    }

    // Update section
    if ($_POST['action'] == 'update_section') {
        $section_id = intval($_POST['section_id']);
        $section_name = trim($_POST['section_name']);
        $capacity = intval($_POST['capacity'] ?? 40);
        $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;

        if (empty($section_name) || $section_id <= 0) {
            $_SESSION['error'] = "Section name and valid section ID are required.";
        } else {
            // Get class_id for this section
            $stmt = $conn->prepare("SELECT class_id FROM Sections WHERE section_id = ?");
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $section_data = $result->fetch_assoc();
            $class_id = $section_data['class_id'];
            $stmt->close();

            // Check if section already exists for this class (excluding current section)
            $stmt = $conn->prepare("SELECT section_id FROM Sections WHERE class_id = ? AND section_name = ? AND section_id != ?");
            $stmt->bind_param("isi", $class_id, $section_name, $section_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Another section with this name already exists for this class.";
            } else {
                // Update section
                $stmt = $conn->prepare("UPDATE Sections SET section_name = ?, capacity = ?, teacher_id = ? WHERE section_id = ?");
                $stmt->bind_param("siii", $section_name, $capacity, $teacher_id, $section_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Section updated successfully.";
                } else {
                    $_SESSION['error'] = "Error updating section: " . $conn->error;
                }
            }
            $stmt->close();
        }

        header("Location: classes.php?view_sections=" . $class_id);
        exit();
    }

    // Delete class
    if ($_POST['action'] == 'delete_class') {
        $class_id = intval($_POST['class_id']);

        if ($class_id <= 0) {
            $_SESSION['error'] = "Invalid class ID.";
        } else {
            // Check if class has students
            $stmt = $conn->prepare("SELECT COUNT(*) as student_count FROM Students WHERE class_id = ?");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $student_count = $row['student_count'];
            $stmt->close();

            if ($student_count > 0) {
                $_SESSION['error'] = "Cannot delete class with students. Please move or delete students first.";
            } else {
                // First delete sections
                $stmt = $conn->prepare("DELETE FROM Sections WHERE class_id = ?");
                $stmt->bind_param("i", $class_id);
                $stmt->execute();
                $stmt->close();

                // Then delete class
                $stmt = $conn->prepare("DELETE FROM Classes WHERE class_id = ?");
                $stmt->bind_param("i", $class_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Class and its sections deleted successfully.";
                } else {
                    $_SESSION['error'] = "Error deleting class: " . $conn->error;
                }
                $stmt->close();
            }
        }

        header("Location: classes.php");
        exit();
    }

    // Delete section
    if ($_POST['action'] == 'delete_section') {
        $section_id = intval($_POST['section_id']);

        if ($section_id <= 0) {
            $_SESSION['error'] = "Invalid section ID.";
        } else {
            // Get class_id for this section
            $stmt = $conn->prepare("SELECT class_id FROM Sections WHERE section_id = ?");
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $section_data = $result->fetch_assoc();
            $class_id = $section_data['class_id'];
            $stmt->close();

            // Check if section has students
            $stmt = $conn->prepare("SELECT COUNT(*) as student_count FROM Students WHERE section_id = ?");
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $student_count = $row['student_count'];
            $stmt->close();

            if ($student_count > 0) {
                $_SESSION['error'] = "Cannot delete section with students. Please move or delete students first.";
            } else {
                // Delete section
                $stmt = $conn->prepare("DELETE FROM Sections WHERE section_id = ?");
                $stmt->bind_param("i", $section_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Section deleted successfully.";
                } else {
                    $_SESSION['error'] = "Error deleting section: " . $conn->error;
                }
                $stmt->close();
            }
        }

        header("Location: classes.php?view_sections=" . $class_id);
        exit();
    }

    // Assign students to section
    if ($_POST['action'] == 'assign_students') {
        $section_id = intval($_POST['section_id']);
        $student_ids = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];

        if ($section_id <= 0) {
            $_SESSION['error'] = "Invalid section ID.";
        } else {
            // Get class_id for this section
            $stmt = $conn->prepare("SELECT class_id FROM Sections WHERE section_id = ?");
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $section_data = $result->fetch_assoc();
            $class_id = $section_data['class_id'];
            $stmt->close();

            // First, reset section_id for all students in this class who are in this section
            $stmt = $conn->prepare("UPDATE Students SET section_id = NULL WHERE class_id = ? AND section_id = ?");
            $stmt->bind_param("ii", $class_id, $section_id);
            $stmt->execute();
            $stmt->close();

            // Then, assign selected students to this section
            if (!empty($student_ids)) {
                $success_count = 0;
                foreach ($student_ids as $student_id) {
                    $student_id = intval($student_id);
                    $stmt = $conn->prepare("UPDATE Students SET section_id = ? WHERE student_id = ? AND class_id = ?");
                    $stmt->bind_param("iii", $section_id, $student_id, $class_id);
                    if ($stmt->execute()) {
                        $success_count++;
                    }
                    $stmt->close();
                }

                $_SESSION['success'] = "$success_count students assigned to section successfully.";
            } else {
                $_SESSION['info'] = "All students have been removed from this section.";
            }
        }

        header("Location: classes.php?view_sections=" . $class_id);
        exit();
    }

    // Assign teacher to section
    if ($_POST['action'] == 'assign_teacher') {
        $section_id = intval($_POST['section_id']);
        $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;

        if ($section_id <= 0) {
            $_SESSION['error'] = "Invalid section ID.";
        } else {
            // Get class_id for this section
            $stmt = $conn->prepare("SELECT class_id FROM Sections WHERE section_id = ?");
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $section_data = $result->fetch_assoc();
            $class_id = $section_data['class_id'];
            $stmt->close();

            // Update section with teacher
            $stmt = $conn->prepare("UPDATE Sections SET teacher_id = ? WHERE section_id = ?");
            $stmt->bind_param("ii", $teacher_id, $section_id);

            if ($stmt->execute()) {
                if ($teacher_id) {
                    $_SESSION['success'] = "Teacher assigned to section successfully.";
                } else {
                    $_SESSION['success'] = "Teacher removed from section successfully.";
                }
            } else {
                $_SESSION['error'] = "Error assigning teacher: " . $conn->error;
            }
            $stmt->close();
        }

        header("Location: classes.php?view_sections=" . $class_id);
        exit();
    }
}

// Get all classes with section counts
$classes = [];
try {
    $query = "SELECT c.*, 
                    (SELECT COUNT(*) FROM Sections s WHERE s.class_id = c.class_id) as section_count,
                    (SELECT COUNT(*) FROM Students st WHERE st.class_id = c.class_id) as student_count
              FROM Classes c
              ORDER BY c.academic_year DESC, c.class_name";

    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading classes: " . $e->getMessage();
}

// Get sections for a specific class if requested
$sections = [];
$selected_class = null;
$unassigned_students = [];
$teachers = [];

if (isset($_GET['view_sections']) && !empty($_GET['view_sections'])) {
    $class_id = intval($_GET['view_sections']);

    try {
        // Get class details
        $stmt = $conn->prepare("SELECT * FROM Classes WHERE class_id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_class = $result->fetch_assoc();
        $stmt->close();

        if ($selected_class) {
            // Get sections for this class with teacher and student count
            $stmt = $conn->prepare("SELECT s.*, 
                                        (SELECT COUNT(*) FROM Students st WHERE st.section_id = s.section_id) as student_count,
                                        u.full_name as teacher_name
                                    FROM Sections s 
                                    LEFT JOIN Users u ON s.teacher_id = u.user_id
                                    WHERE s.class_id = ?
                                    ORDER BY s.section_name");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $sections[] = $row;
            }

            $stmt->close();

            // Get unassigned students for this class
            $stmt = $conn->prepare("SELECT s.student_id, s.roll_number, u.full_name 
                                   FROM Students s 
                                   JOIN Users u ON s.user_id = u.user_id 
                                   WHERE s.class_id = ? AND (s.section_id IS NULL OR s.section_id = 0)
                                   ORDER BY s.roll_number");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $unassigned_students[] = $row;
            }

            $stmt->close();

            // Get all teachers
            $stmt = $conn->prepare("SELECT u.user_id, u.full_name 
                                   FROM Users u 
                                   WHERE u.role = 'teacher'
                                   ORDER BY u.full_name");
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $teachers[] = $row;
            }

            $stmt->close();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error loading sections: " . $e->getMessage();
    }
}

// Get section details for editing
$edit_section = null;
if (isset($_GET['edit_section']) && !empty($_GET['edit_section'])) {
    $section_id = intval($_GET['edit_section']);

    try {
        $stmt = $conn->prepare("SELECT s.*, c.class_name, c.academic_year, u.full_name as teacher_name
                               FROM Sections s 
                               JOIN Classes c ON s.class_id = c.class_id 
                               LEFT JOIN Users u ON s.teacher_id = u.user_id
                               WHERE s.section_id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_section = $result->fetch_assoc();
        $stmt->close();

        if ($edit_section) {
            // Get all teachers for dropdown
            $stmt = $conn->prepare("SELECT u.user_id, u.full_name 
                                   FROM Users u 
                                   WHERE u.role = 'teacher'
                                   ORDER BY u.full_name");
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $teachers[] = $row;
            }

            $stmt->close();

            // Get students in this section
            $stmt = $conn->prepare("SELECT s.student_id, s.roll_number, u.full_name 
                                   FROM Students s 
                                   JOIN Users u ON s.user_id = u.user_id 
                                   WHERE s.section_id = ?
                                   ORDER BY s.roll_number");
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $section_students = [];
            while ($row = $result->fetch_assoc()) {
                $section_students[] = $row;
            }

            $edit_section['students'] = $section_students;
            $stmt->close();

            // Get all students in this class for assignment
            $stmt = $conn->prepare("SELECT s.student_id, s.roll_number, u.full_name, s.section_id
                                   FROM Students s 
                                   JOIN Users u ON s.user_id = u.user_id 
                                   WHERE s.class_id = ?
                                   ORDER BY s.roll_number");
            $stmt->bind_param("i", $edit_section['class_id']);
            $stmt->execute();
            $result = $stmt->get_result();

            $all_class_students = [];
            while ($row = $result->fetch_assoc()) {
                $all_class_students[] = $row;
            }

            $edit_section['all_students'] = $all_class_students;
            $stmt->close();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error loading section details: " . $e->getMessage();
    }
}

// Get class details for editing
$edit_class = null;
if (isset($_GET['edit_class']) && !empty($_GET['edit_class'])) {
    $class_id = intval($_GET['edit_class']);

    try {
        $stmt = $conn->prepare("SELECT * FROM Classes WHERE class_id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_class = $result->fetch_assoc();
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error loading class details: " . $e->getMessage();
    }
}

// Get section details for viewing
$view_section = null;
if (isset($_GET['section_id']) && !empty($_GET['section_id'])) {
    $section_id = intval($_GET['section_id']);

    try {
        $stmt = $conn->prepare("SELECT s.*, c.class_name, c.academic_year, u.full_name as teacher_name
                               FROM Sections s 
                               JOIN Classes c ON s.class_id = c.class_id 
                               LEFT JOIN Users u ON s.teacher_id = u.user_id
                               WHERE s.section_id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $view_section = $result->fetch_assoc();
        $stmt->close();

        if ($view_section) {
            // Get students in this section
            $stmt = $conn->prepare("SELECT s.student_id, s.roll_number, u.full_name, u.email, u.phone
                                   FROM Students s 
                                   JOIN Users u ON s.user_id = u.user_id 
                                   WHERE s.section_id = ?
                                   ORDER BY s.roll_number");
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $section_students = [];
            while ($row = $result->fetch_assoc()) {
                $section_students[] = $row;
            }

            $view_section['students'] = $section_students;
            $stmt->close();

            // Get performance metrics for this section
            $stmt = $conn->prepare("SELECT 
                                    AVG(r.percentage) as avg_percentage,
                                    COUNT(CASE WHEN r.is_pass = 1 THEN 1 END) as pass_count,
                                    COUNT(r.result_id) as total_results,
                                    MAX(r.percentage) as highest_percentage,
                                    MIN(r.percentage) as lowest_percentage
                                FROM Results r
                                JOIN Students s ON r.student_id = s.student_id
                                WHERE s.section_id = ?");
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $performance = $result->fetch_assoc();

            $view_section['performance'] = $performance;
            $stmt->close();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error loading section details: " . $e->getMessage();
    }
}

// Get academic years for filter
$academic_years = [];
$result = $conn->query("SELECT DISTINCT academic_year FROM Classes ORDER BY academic_year DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $academic_years[] = $row['academic_year'];
    }
}

// Apply filters
$filter_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';
$filter_search = isset($_GET['search']) ? $_GET['search'] : '';

if (!empty($filter_year) || !empty($filter_search)) {
    // Filter the classes array
    $filtered_classes = [];
    foreach ($classes as $class) {
        $year_match = empty($filter_year) || $class['academic_year'] == $filter_year;
        $search_match = empty($filter_search) ||
            stripos($class['class_name'], $filter_search) !== false ||
            stripos($class['academic_year'], $filter_search) !== false;

        if ($year_match && $search_match) {
            $filtered_classes[] = $class;
        }
    }
    $classes = $filtered_classes;
}

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

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        /* Dark mode for modal */
        .dark-mode .modal-content {
            background-color: #2d3748;
            color: #e2e8f0;
            border-color: #4a5568;
        }

        .dark-mode .close {
            color: #e2e8f0;
        }

        .dark-mode .close:hover,
        .dark-mode .close:focus {
            color: #fff;
        }

        /* Badge styles */
        .badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
        }

        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background-color: white;
                color: black;
            }
        }
    </style>
</head>

<body class="bg-gray-100" id="body">
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
                        <!-- Notification Messages -->
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700">
                                            <?php echo $_SESSION['success'];
                                            unset($_SESSION['success']); ?>
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

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700">
                                            <?php echo $_SESSION['error'];
                                            unset($_SESSION['error']); ?>
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

                        <?php if (isset($_SESSION['info'])): ?>
                            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700">
                                            <?php echo $_SESSION['info'];
                                            unset($_SESSION['info']); ?>
                                        </p>
                                    </div>
                                    <div class="ml-auto pl-3">
                                        <div class="-mx-1.5 -my-1.5">
                                            <button class="inline-flex rounded-md p-1.5 text-blue-500 hover:bg-blue-100 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                                                <span class="sr-only">Dismiss</span>
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Page Title and Add Button -->
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-semibold text-gray-900">Manage Classes</h1>
                            <button type="button" onclick="openAddClassModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-plus mr-2"></i> Add Class
                            </button>
                        </div>

                        <!-- Filter Section -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6 hover-scale">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Filter Classes</h2>
                            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                                    <select id="academic_year" name="academic_year" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Academic Years</option>
                                        <?php foreach ($academic_years as $year): ?>
                                            <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($filter_year == $year) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($year); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                    <div class="flex">
                                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($filter_search); ?>" class="w-full rounded-l-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" placeholder="Class name or academic year">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-r-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="flex items-end">
                                    <a href="classes.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-sync-alt mr-2"></i> Reset Filters
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Classes Table -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6 hover-scale">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">All Classes</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sections</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($classes)): ?>
                                            <tr>
                                                <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No classes found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($classes as $class): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($class['academic_year']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900">
                                                            <span class="badge bg-blue-100 text-blue-800"><?php echo $class['section_count']; ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900">
                                                            <span class="badge bg-green-100 text-green-800"><?php echo $class['student_count']; ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm text-gray-900 truncate max-w-xs"><?php echo htmlspecialchars($class['description'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex space-x-3">
                                                            <a href="classes.php?view_sections=<?php echo $class['class_id']; ?>" class="text-indigo-600 hover:text-indigo-900 transition-colors duration-200">
                                                                <i class="fas fa-eye"></i> View Sections
                                                            </a>
                                                            <a href="classes.php?edit_class=<?php echo $class['class_id']; ?>" class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                            <button type="button" onclick="confirmDeleteClass(<?php echo $class['class_id']; ?>, '<?php echo htmlspecialchars($class['class_name']); ?>')" class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Edit Class Form (if editing) -->
                        <?php if ($edit_class): ?>
                            <div class="bg-white shadow rounded-lg overflow-hidden mb-6 hover-scale">
                                <div class="px-6 py-4 border-b border-gray-200">
                                    <h2 class="text-lg font-medium text-gray-900">Edit Class</h2>
                                </div>
                                <div class="p-6">
                                    <form action="" method="POST">
                                        <input type="hidden" name="action" value="update_class">
                                        <input type="hidden" name="class_id" value="<?php echo $edit_class['class_id']; ?>">

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label for="class_name" class="block text-sm font-medium text-gray-700 mb-1">Class Name</label>
                                                <input type="text" id="class_name" name="class_name" value="<?php echo htmlspecialchars($edit_class['class_name']); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            </div>
                                            <div>
                                                <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                                                <input type="text" id="academic_year" name="academic_year" value="<?php echo htmlspecialchars($edit_class['academic_year']); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                            <textarea id="description" name="description" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?php echo htmlspecialchars($edit_class['description'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mt-6 flex justify-end">
                                            <a href="classes.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2">
                                                Cancel
                                            </a>
                                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                Update Class
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Edit Section Form (if editing) -->
                        <?php if ($edit_section): ?>
                            <div class="bg-white shadow rounded-lg overflow-hidden mb-6 hover-scale">
                                <div class="px-6 py-4 border-b border-gray-200">
                                    <h2 class="text-lg font-medium text-gray-900">Edit Section</h2>
                                </div>
                                <div class="p-6">
                                    <form action="" method="POST">
                                        <input type="hidden" name="action" value="update_section">
                                        <input type="hidden" name="section_id" value="<?php echo $edit_section['section_id']; ?>">

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                                <input type="text" value="<?php echo htmlspecialchars($edit_section['class_name'] . ' (' . $edit_section['academic_year'] . ')'); ?>" class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50" readonly>
                                            </div>
                                            <div>
                                                <label for="section_name" class="block text-sm font-medium text-gray-700 mb-1">Section Name</label>
                                                <input type="text" id="section_name" name="section_name" value="<?php echo htmlspecialchars($edit_section['section_name']); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            </div>
                                            <div>
                                                <label for="capacity" class="block text-sm font-medium text-gray-700 mb-1">Capacity</label>
                                                <input type="number" id="capacity" name="capacity" value="<?php echo htmlspecialchars($edit_section['capacity'] ?? 40); ?>" min="1" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <label for="teacher_id" class="block text-sm font-medium text-gray-700 mb-1">Assigned Teacher</label>
                                            <select id="teacher_id" name="teacher_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                <option value="">-- Select Teacher --</option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                    <option value="<?php echo $teacher['user_id']; ?>" <?php echo ($edit_section['teacher_id'] == $teacher['user_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mt-6 flex justify-end">
                                            <a href="classes.php?view_sections=<?php echo $edit_section['class_id']; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2">
                                                Cancel
                                            </a>
                                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                Update Section
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Student Assignment -->
                                <div class="px-6 py-4 border-t border-gray-200">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Assign Students to Section</h3>
    
    <!-- Current Students in Section -->
    <?php if (!empty($edit_section['students'])): ?>
        <div class="mb-6">
            <h4 class="text-md font-medium text-gray-700 mb-2">Currently Assigned Students (<?php echo count($edit_section['students']); ?>)</h4>
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                    <?php foreach ($edit_section['students'] as $student): ?>
                        <div class="flex items-center text-sm text-blue-800">
                            <i class="fas fa-user-check mr-2"></i>
                            <?php echo htmlspecialchars($student['roll_number'] . ' - ' . $student['full_name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form action="" method="POST" id="assignStudentsForm">
        <input type="hidden" name="action" value="assign_students">
        <input type="hidden" name="section_id" value="<?php echo $edit_section['section_id']; ?>">

        <div class="mb-4">
            <div class="flex justify-between items-center mb-2">
                <label class="block text-sm font-medium text-gray-700">Select Students for This Section</label>
                <div class="flex space-x-2">
                    <button type="button" onclick="selectAllStudents()" class="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                        Select All
                    </button>
                    <button type="button" onclick="deselectAllStudents()" class="text-xs px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                        Deselect All
                    </button>
                    <button type="button" onclick="selectUnassignedOnly()" class="text-xs px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200">
                        Unassigned Only
                    </button>
                </div>
            </div>
            
            <div class="max-h-80 overflow-y-auto border border-gray-300 rounded-md p-4 bg-gray-50">
                <?php if (empty($edit_section['all_students'])): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-users text-gray-400 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-500">No students found in this class.</p>
                        <p class="text-xs text-gray-400 mt-1">Students need to be added to the class first.</p>
                        <a href="students.php" class="inline-flex items-center mt-3 px-3 py-1 text-xs font-medium text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i> Add Students to Class
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-3">
                        <?php 
                        $assigned_count = 0;
                        $unassigned_count = 0;
                        foreach ($edit_section['all_students'] as $student): 
                            $is_assigned = ($student['section_id'] == $edit_section['section_id']);
                            $is_in_other_section = (!empty($student['section_id']) && $student['section_id'] != $edit_section['section_id']);
                            
                            if ($is_assigned) $assigned_count++;
                            if (empty($student['section_id'])) $unassigned_count++;
                        ?>
                            <div class="flex items-center p-3 rounded-lg border <?php echo $is_assigned ? 'bg-blue-50 border-blue-200' : ($is_in_other_section ? 'bg-yellow-50 border-yellow-200' : 'bg-white border-gray-200'); ?>">
                                <input type="checkbox" 
                                       id="student_<?php echo $student['student_id']; ?>" 
                                       name="student_ids[]" 
                                       value="<?php echo $student['student_id']; ?>" 
                                       <?php echo $is_assigned ? 'checked' : ''; ?>
                                       data-assigned="<?php echo $is_assigned ? '1' : '0'; ?>"
                                       data-unassigned="<?php echo empty($student['section_id']) ? '1' : '0'; ?>"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                
                                <label for="student_<?php echo $student['student_id']; ?>" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($student['roll_number']); ?>
                                            </span>
                                            <span class="text-sm text-gray-700 ml-2">
                                                <?php echo htmlspecialchars($student['full_name']); ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <?php if ($is_assigned): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <i class="fas fa-check mr-1"></i> Current Section
                                                </span>
                                            <?php elseif ($is_in_other_section): ?>
                                                <?php
                                                // Get the section name for this student
                                                $other_section_query = $conn->prepare("SELECT section_name FROM Sections WHERE section_id = ?");
                                                $other_section_query->bind_param("i", $student['section_id']);
                                                $other_section_query->execute();
                                                $other_section_result = $other_section_query->get_result();
                                                $other_section = $other_section_result->fetch_assoc();
                                                $other_section_query->close();
                                                ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <i class="fas fa-users mr-1"></i> Section <?php echo htmlspecialchars($other_section['section_name'] ?? 'Unknown'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    <i class="fas fa-user-slash mr-1"></i> Unassigned
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Summary Statistics -->
                    <div class="mt-4 p-3 bg-white rounded-lg border border-gray-200">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <div class="text-lg font-semibold text-blue-600"><?php echo $assigned_count; ?></div>
                                <div class="text-xs text-gray-500">Currently Assigned</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-gray-600"><?php echo $unassigned_count; ?></div>
                                <div class="text-xs text-gray-500">Unassigned</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-green-600"><?php echo count($edit_section['all_students']); ?></div>
                                <div class="text-xs text-gray-500">Total Students</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($edit_section['all_students'])): ?>
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-1"></i>
                    Students will be moved from their current sections to this section.
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-save mr-2"></i> Save Student Assignments
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>
                            </div>
                        <?php endif; ?>

                        <!-- View Section Details (if viewing a section) -->
                        <?php if ($view_section): ?>
                            <div class="bg-white shadow rounded-lg overflow-hidden mb-6 hover-scale">
                                <div class="px-6 py-4 border-b border-gray-200">
                                    <h2 class="text-lg font-medium text-gray-900">
                                        Section Details: <?php echo htmlspecialchars($view_section['section_name']); ?>
                                    </h2>
                                </div>
                                <div class="p-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                        <div>
                                            <h3 class="text-md font-medium text-gray-700 mb-2">Basic Information</h3>
                                            <div class="bg-gray-50 p-4 rounded-lg">
                                                <p class="mb-2"><span class="font-medium">Class:</span> <?php echo htmlspecialchars($view_section['class_name']); ?></p>
                                                <p class="mb-2"><span class="font-medium">Academic Year:</span> <?php echo htmlspecialchars($view_section['academic_year']); ?></p>
                                                <p class="mb-2"><span class="font-medium">Section:</span> <?php echo htmlspecialchars($view_section['section_name']); ?></p>
                                                <p class="mb-2"><span class="font-medium">Capacity:</span> <?php echo htmlspecialchars($view_section['capacity'] ?? 'N/A'); ?></p>
                                                <p><span class="font-medium">Teacher:</span> <?php echo htmlspecialchars($view_section['teacher_name'] ?? 'Not Assigned'); ?></p>
                                            </div>
                                        </div>

                                        <div>
                                            <h3 class="text-md font-medium text-gray-700 mb-2">Performance Metrics</h3>
                                            <div class="bg-gray-50 p-4 rounded-lg">
                                                <?php if (isset($view_section['performance']) && $view_section['performance']['total_results'] > 0): ?>
                                                    <p class="mb-2"><span class="font-medium">Average Score:</span> <?php echo number_format($view_section['performance']['avg_percentage'], 2); ?>%</p>
                                                    <p class="mb-2"><span class="font-medium">Pass Rate:</span> <?php echo number_format(($view_section['performance']['pass_count'] / $view_section['performance']['total_results']) * 100, 2); ?>%</p>
                                                    <p class="mb-2"><span class="font-medium">Highest Score:</span> <?php echo number_format($view_section['performance']['highest_percentage'], 2); ?>%</p>
                                                    <p><span class="font-medium">Lowest Score:</span> <?php echo number_format($view_section['performance']['lowest_percentage'], 2); ?>%</p>
                                                <?php else: ?>
                                                    <p class="text-gray-500">No performance data available yet.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <h3 class="text-md font-medium text-gray-700 mb-2">Students in this Section</h3>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll Number</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php if (empty($view_section['students'])): ?>
                                                    <tr>
                                                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No students assigned to this section</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($view_section['students'] as $student): ?>
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['roll_number']); ?></div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['email']); ?></div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-6 flex justify-end">
                                        <a href="classes.php?view_sections=<?php echo $view_section['class_id']; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-arrow-left mr-2"></i> Back to Sections
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Sections Table (if viewing sections) -->
                        <?php if ($selected_class): ?>
                            <div class="bg-white shadow rounded-lg overflow-hidden mb-6 hover-scale">
                                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                                    <h2 class="text-lg font-medium text-gray-900">
                                        Sections for <?php echo htmlspecialchars($selected_class['class_name'] . ' (' . $selected_class['academic_year'] . ')'); ?>
                                    </h2>
                                    <div class="flex space-x-2">

                                        <button type="button" onclick="openAddSectionModal(<?php echo $selected_class['class_id']; ?>, '<?php echo htmlspecialchars($selected_class['class_name']); ?>')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-plus mr-2"></i> Add Section
                                        </button>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($sections)): ?>
                                                <tr>
                                                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No sections found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($sections as $section): ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($section['section_name']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900">
                                                                <span class="badge bg-green-100 text-green-800"><?php echo $section['student_count']; ?></span>
                                                                <?php if (isset($section['capacity']) && $section['capacity'] > 0): ?>
                                                                    <span class="text-xs text-gray-500">/ <?php echo $section['capacity']; ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($section['capacity'] ?? 'N/A'); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($section['teacher_name'] ?? 'Not Assigned'); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                            <div class="flex space-x-3">
                                                                <a href="classes.php?section_id=<?php echo $section['section_id']; ?>" class="text-indigo-600 hover:text-indigo-900 transition-colors duration-200">
                                                                    <i class="fas fa-eye"></i> View
                                                                </a>
                                                                <a href="classes.php?edit_section=<?php echo $section['section_id']; ?>" class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                                                    <i class="fas fa-edit"></i> Edit
                                                                </a>
                                                                <button type="button" onclick="confirmDeleteSection(<?php echo $section['section_id']; ?>, '<?php echo htmlspecialchars($section['section_name']); ?>')" class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Unassigned Students -->
                                <?php if (!empty($unassigned_students)): ?>
                                    <div class="px-6 py-4 border-t border-gray-200">
                                        <h3 class="text-md font-medium text-gray-700 mb-2">Unassigned Students</h3>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach ($unassigned_students as $student): ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                                        <?php echo htmlspecialchars($student['roll_number'] . ' - ' . $student['full_name']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="px-6 py-4 border-t border-gray-200">
                                    <a href="classes.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-arrow-left mr-2"></i> Back to Classes
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Class Modal -->
    <div id="addClassModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddClassModal()">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Add New Class</h2>
            <form id="addClassForm" action="" method="POST">
                <input type="hidden" name="action" value="add_class">

                <div class="mb-4">
                    <label for="add_class_name" class="block text-sm font-medium text-gray-700 mb-1">Class Name</label>
                    <input type="text" id="add_class_name" name="class_name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                </div>

                <div class="mb-4">
                    <label for="add_academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                    <input type="text" id="add_academic_year" name="academic_year" placeholder="e.g. 2023-2024" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                </div>

                <div class="mb-4">
                    <label for="add_description" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                    <textarea id="add_description" name="description" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                </div>

                <div class="mb-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="auto_create_sections" name="auto_create_sections" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" onchange="toggleSectionOptions()">
                        <label for="auto_create_sections" class="ml-2 block text-sm text-gray-900">
                            Automatically create sections
                        </label>
                    </div>
                </div>

                <div id="section_options" class="mb-4 hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="section_count" class="block text-sm font-medium text-gray-700 mb-1">Number of Sections</label>
                            <input type="number" id="section_count" name="section_count" value="3" min="1" max="10" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        </div>
                        <div>
                            <label for="section_prefix" class="block text-sm font-medium text-gray-700 mb-1">Section Prefix (Optional)</label>
                            <input type="text" id="section_prefix" name="section_prefix" placeholder="e.g. Section " class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Sections will be named: A, B, C, etc. or with the prefix if provided.</p>
                </div>

                <div class="flex justify-end">
                    <button type="button" onclick="closeAddClassModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Add Class
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Section Modal -->
    <div id="addSectionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddSectionModal()">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Add New Section</h2>
            <form id="addSectionForm" action="" method="POST">
                <input type="hidden" name="action" value="add_section">
                <input type="hidden" id="add_section_class_id" name="class_id" value="">

                <div class="mb-4">
                    <label for="add_section_class" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                    <input type="text" id="add_section_class" class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50" readonly>
                </div>

                <div class="mb-4">
                    <label for="add_section_name" class="block text-sm font-medium text-gray-700 mb-1">Section Name</label>
                    <input type="text" id="add_section_name" name="section_name" placeholder="e.g. A, B, C" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                </div>

                <div class="mb-4">
                    <label for="add_section_capacity" class="block text-sm font-medium text-gray-700 mb-1">Capacity</label>
                    <input type="number" id="add_section_capacity" name="capacity" value="40" min="1" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                </div>

                <div class="mb-4">
                    <label for="add_section_teacher" class="block text-sm font-medium text-gray-700 mb-1">Assign Teacher (Optional)</label>
                    <select id="add_section_teacher" name="teacher_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['user_id']; ?>">
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-end">
                    <button type="button" onclick="closeAddSectionModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Add Section
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Class Form (hidden) -->
    <form id="deleteClassForm" action="" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_class">
        <input type="hidden" id="delete_class_id" name="class_id" value="">
    </form>

    <!-- Delete Section Form (hidden) -->
    <form id="deleteSectionForm" action="" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_section">
        <input type="hidden" id="delete_section_id" name="section_id" value="">
    </form>

    <script>
        // Modal functions for Add Class
        function openAddClassModal() {
            document.getElementById('addClassModal').style.display = 'block';
        }

        function closeAddClassModal() {
            document.getElementById('addClassModal').style.display = 'none';
        }

        // Modal functions for Add Section
        function openAddSectionModal(classId, className) {
            document.getElementById('add_section_class_id').value = classId;
            document.getElementById('add_section_class').value = className;
            document.getElementById('addSectionModal').style.display = 'block';
        }

        function closeAddSectionModal() {
            document.getElementById('addSectionModal').style.display = 'none';
        }


        // Toggle section options in Add Class modal
        function toggleSectionOptions() {
            const checkbox = document.getElementById('auto_create_sections');
            const options = document.getElementById('section_options');

            if (checkbox.checked) {
                options.classList.remove('hidden');
            } else {
                options.classList.add('hidden');
            }
        }

        // Confirm delete class
        function confirmDeleteClass(classId, className) {
            Swal.fire({
                title: 'Delete Class',
                html: `Are you sure you want to delete the class <strong>${className}</strong>?<br><br>This will also delete all sections associated with this class.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_class_id').value = classId;
                    document.getElementById('deleteClassForm').submit();
                }
            });
        }

        // Confirm delete section
        function confirmDeleteSection(sectionId, sectionName) {
            Swal.fire({
                title: 'Delete Section',
                html: `Are you sure you want to delete the section <strong>${sectionName}</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_section_id').value = sectionId;
                    document.getElementById('deleteSectionForm').submit();
                }
            });
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addClassModal = document.getElementById('addClassModal');
            const addSectionModal = document.getElementById('addSectionModal');
            const bulkSectionsModal = document.getElementById('bulkSectionsModal');

            if (event.target === addClassModal) {
                closeAddClassModal();
            }

            if (event.target === addSectionModal) {
                closeAddSectionModal();
            }

            if (event.target === bulkSectionsModal) {
                closeBulkSectionsModal();
            }
        }

// Student selection functions
function selectAllStudents() {
    const checkboxes = document.querySelectorAll('input[name="student_ids[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllStudents() {
    const checkboxes = document.querySelectorAll('input[name="student_ids[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

function selectUnassignedOnly() {
    const checkboxes = document.querySelectorAll('input[name="student_ids[]"]');
    checkboxes.forEach(checkbox => {
        // Only check students who are unassigned (data-unassigned="1")
        if (checkbox.getAttribute('data-unassigned') === '1') {
            checkbox.checked = true;
        } else {
            checkbox.checked = false;
        }
    });
}

// Form validation
document.getElementById('assignStudentsForm').addEventListener('submit', function(e) {
    const checkedBoxes = document.querySelectorAll('input[name="student_ids[]"]:checked');
    const currentlyAssigned = document.querySelectorAll('input[name="student_ids[]"][data-assigned="1"]:checked');
    
    if (checkedBoxes.length === 0) {
        e.preventDefault();
        Swal.fire({
            title: 'No Students Selected',
            text: 'Please select at least one student to assign to this section, or leave all unchecked to remove all students from this section.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    // Show confirmation if moving students from other sections
    const movingStudents = checkedBoxes.length - currentlyAssigned.length;
    if (movingStudents > 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Confirm Student Assignment',
            html: `You are about to assign <strong>${checkedBoxes.length}</strong> students to this section.<br><br>` +
                  `<strong>${movingStudents}</strong> students will be moved from their current sections.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, assign students',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Remove the event listener temporarily and submit
                this.removeEventListener('submit', arguments.callee);
                this.submit();
            }
        });
        return false;
    }
});
    </script>
</body>

</html>
