<?php
session_start();
require_once '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get teacher information
$stmt = $conn->prepare("SELECT t.*, u.full_name, u.email, u.phone, u.status, u.username 
                       FROM teachers t 
                       JOIN users u ON t.user_id = u.user_id 
                       WHERE t.user_id = ?");
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // If teacher record doesn't exist, redirect to login
    header("Location: ../login.php");
    exit();
}

$teacher = $result->fetch_assoc();
$stmt->close();

// Get assigned subjects
$stmt = $conn->prepare("SELECT ts.*, s.subject_name, s.subject_code, c.class_name, c.section
                       FROM teachersubjects ts 
                       JOIN subjects s ON ts.subject_id = s.subject_id 
                       JOIN classes c ON ts.class_id = c.class_id 
                       WHERE ts.teacher_id = ? AND ts.is_active = 1
                       ORDER BY c.class_name, s.subject_name");
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param("i", $teacher['teacher_id']);
$stmt->execute();
$subjects_result = $stmt->get_result();
$assigned_subjects = [];
while ($subject = $subjects_result->fetch_assoc()) {
    $assigned_subjects[] = $subject;
}
$stmt->close();

// Count total students in assigned classes
$total_students = 0;
$assigned_classes = [];
$class_counts = [];

if (!empty($assigned_subjects)) {
    $class_ids = array_unique(array_column($assigned_subjects, 'class_id'));
    
    foreach ($class_ids as $class_id) {
        $stmt = $conn->prepare("SELECT c.*, COUNT(s.student_id) as student_count 
                               FROM classes c 
                               LEFT JOIN students s ON c.class_id = s.class_id 
                               WHERE c.class_id = ? 
                               GROUP BY c.class_id");
        if (!$stmt) {
            continue; // Skip this class if query fails
        }
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $class_result = $stmt->get_result();
        
        if ($class_result->num_rows > 0) {
            $class_data = $class_result->fetch_assoc();
            $assigned_classes[] = $class_data;
            $class_counts[$class_id] = $class_data['student_count'];
            $total_students += $class_data['student_count'];
        }
        $stmt->close();
    }
}

// Get recent results - simplified query
$recent_results = [];
$stmt = $conn->prepare("SELECT r.*, s.subject_name, c.class_name, c.section, 
                       st.roll_number, u.full_name as student_name, e.exam_name
                       FROM results r 
                       JOIN subjects s ON r.subject_id = s.subject_id 
                       JOIN students st ON r.student_id = st.student_id 
                       JOIN users u ON st.user_id = u.user_id 
                       JOIN classes c ON r.class_id = c.class_id 
                       JOIN exams e ON r.exam_id = e.exam_id
                       WHERE r.created_by = ? OR r.updated_by = ?
                       ORDER BY r.updated_at DESC LIMIT 5");
if ($stmt) {
    $stmt->bind_param("ii", $teacher['teacher_id'], $teacher['teacher_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $recent_results = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// Get upcoming exams - simplified query
$upcoming_exams = [];
// Check if exams table exists and has the required columns
$check_table = $conn->query("SHOW TABLES LIKE 'exams'");
if ($check_table && $check_table->num_rows > 0) {
    // Check for columns
    $check_columns = $conn->query("SHOW COLUMNS FROM exams LIKE 'start_date'");
    if ($check_columns && $check_columns->num_rows > 0) {
        $stmt = $conn->prepare("SELECT e.*, c.class_name, c.section
                              FROM exams e 
                              JOIN classes c ON e.class_id = c.class_id 
                              WHERE e.class_id IN (
                                  SELECT DISTINCT ts.class_id 
                                  FROM teachersubjects ts 
                                  WHERE ts.teacher_id = ?
                              ) 
                              AND e.start_date >= CURDATE()
                              ORDER BY e.start_date ASC LIMIT 5");
        if ($stmt) {
            $stmt->bind_param("i", $teacher['teacher_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $upcoming_exams = $result->fetch_all(MYSQLI_ASSOC);
            }
            $stmt->close();
        }
    } else {
        // Alternative query if start_date column doesn't exist
        $check_columns = $conn->query("SHOW COLUMNS FROM exams LIKE 'exam_date'");
        if ($check_columns && $check_columns->num_rows > 0) {
            $stmt = $conn->prepare("SELECT e.*, c.class_name, c.section
                                  FROM exams e 
                                  JOIN classes c ON e.class_id = c.class_id 
                                  WHERE e.class_id IN (
                                      SELECT DISTINCT ts.class_id 
                                      FROM teachersubjects ts 
                                      WHERE ts.teacher_id = ?
                                  ) 
                                  AND e.exam_date >= CURDATE()
                                  ORDER BY e.exam_date ASC LIMIT 5");
            if ($stmt) {
                $stmt->bind_param("i", $teacher['teacher_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $upcoming_exams = $result->fetch_all(MYSQLI_ASSOC);
                }
                $stmt->close();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | Result Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'includes/teacher_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include 'includes/teacher_topbar.php'; ?>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Welcome Banner -->
                        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-lg shadow-lg mb-6">
                            <div class="px-6 py-8 md:flex md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-white sm:text-2xl">
                                        Welcome back, <?php echo htmlspecialchars($teacher['full_name']); ?>!
                                    </h2>
                                    <p class="mt-2 text-sm text-blue-100 max-w-md">
                                        Here's what's happening with your classes and students today.
                                    </p>
                                </div>
                                <div class="mt-4 md:mt-0 flex space-x-3">
                                  
                                    <a href="view_students.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-indigo-600 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <i class="fas fa-users mr-2"></i> View Students
                                    </a>
                                </div>
                            </div>
                       
                        </div>

                        <!-- Stats Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <div class="bg-white overflow-hidden shadow rounded-lg stat-card">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                            <i class="fas fa-user-graduate text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Total Students</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo $total_students; ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="view_students.php" class="font-medium text-blue-600 hover:text-blue-500">View all students</a>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg stat-card">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                            <i class="fas fa-book text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Assigned Subjects</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo count($assigned_subjects); ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="#assigned-subjects" class="font-medium text-blue-600 hover:text-blue-500">View all subjects</a>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow rounded-lg stat-card">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                            <i class="fas fa-chalkboard text-white text-xl"></i>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Assigned Classes</dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900"><?php echo count($assigned_classes); ?></div>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-5 py-3">
                                    <div class="text-sm">
                                        <a href="#assigned-classes" class="font-medium text-blue-600 hover:text-blue-500">View all classes</a>
                                    </div>
                                </div>
                            </div>
                        </div>

              

                        <!-- Assigned Subjects Section -->
                        <div id="assigned-subjects" class="bg-white shadow rounded-lg mb-6">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">Assigned Subjects</h3>
                                    <p class="mt-1 text-sm text-gray-500">Subjects you are currently teaching</p>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Code</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($assigned_subjects)): ?>
                                            <tr>
                                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No subjects assigned yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($assigned_subjects as $subject): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($subject['subject_code'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($subject['class_name'] . ' ' . $subject['section']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <a href="view_students.php?class_id=<?php echo $subject['class_id']; ?>&subject_id=<?php echo $subject['subject_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                            <i class="fas fa-users"></i> View Students
                                                        </a>
                                                       
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Assigned Classes Section -->
                        <div id="assigned-classes" class="bg-white shadow rounded-lg mb-6">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">Assigned Classes</h3>
                                    <p class="mt-1 text-sm text-gray-500">Classes you are currently teaching</p>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Students</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($assigned_classes)): ?>
                                            <tr>
                                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No classes assigned yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($assigned_classes as $class): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($class['section']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $class['student_count']; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <a href="view_students.php?class_id=<?php echo $class['class_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                            <i class="fas fa-users"></i> View Students
                                                        </a>
                                                       
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
            </main>
        </div>
    </div>

</body>
</html>
