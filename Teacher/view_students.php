<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connetc.php';

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$teacher_record_id = '';

$stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $teacher_record_id = $row['teacher_id'];
}
$stmt->close();

// Get filter values
$class_filter = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$subject_filter = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$show_all = isset($_GET['show_all']) ? $_GET['show_all'] : 'no'; // Default to showing only teacher's students

// Get all classes for filter dropdown
$all_classes = [];
$classes_query = "SELECT class_id, class_name, section FROM classes ORDER BY class_name, section";
$classes_result = $conn->query($classes_query);
while ($class = $classes_result->fetch_assoc()) {
    $all_classes[] = [
        'class_id' => $class['class_id'],
        'class_name' => $class['class_name'] . ' ' . $class['section']
    ];
}

// Get classes taught by this teacher
$teacher_classes = [];
$stmt = $conn->prepare("
    SELECT DISTINCT c.class_id, CONCAT(c.class_name, ' ', c.section) as class_name
    FROM classes c
    JOIN teachersubjects ts ON c.class_id = ts.class_id
    WHERE ts.teacher_id = ?
    ORDER BY c.class_name
");

if ($stmt === false) {
    // If the query fails, try a simpler approach
    $teacher_classes = $all_classes; // Fallback to all classes
} else {
    $stmt->bind_param("s", $teacher_record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $teacher_classes[] = $row;
    }
    $stmt->close();
}

// Get subjects taught by this teacher
$teacher_subjects = [];
$subjects_query = "
    SELECT DISTINCT s.subject_id, s.subject_name
    FROM subjects s
    JOIN teachersubjects ts ON s.subject_id = ts.subject_id
    WHERE ts.teacher_id = ?
    ORDER BY s.subject_name
";

$subjects_stmt = $conn->prepare($subjects_query);
if ($subjects_stmt) {
    $subjects_stmt->bind_param("s", $teacher_record_id);
    $subjects_stmt->execute();
    $subjects_result = $subjects_stmt->get_result();
    while ($subject = $subjects_result->fetch_assoc()) {
        $teacher_subjects[] = $subject;
    }
    $subjects_stmt->close();
}

// Build query based on filters
if ($show_all == 'yes') {
    // Show all students
    $query = "
        SELECT u.user_id, u.full_name, u.email, u.status, u.phone,
               s.student_id, s.roll_number, s.batch_year,
               c.class_id, CONCAT(c.class_name, ' ', c.section) as class_name
        FROM users u
        JOIN students s ON u.user_id = s.user_id
        LEFT JOIN classes c ON s.class_id = c.class_id
        WHERE u.role = 'student'
    ";
    
    $params = [];
    $types = "";
    
    // Add filters
    if (!empty($class_filter)) {
        $query .= " AND c.class_id = ?";
        $params[] = $class_filter;
        $types .= "s";
    }
} else {
    // Show only students in classes taught by this teacher
    $query = "
        SELECT DISTINCT u.user_id, u.full_name, u.email, u.status, u.phone,
               s.student_id, s.roll_number, s.batch_year,
               c.class_id, CONCAT(c.class_name, ' ', c.section) as class_name
        FROM users u
        JOIN students s ON u.user_id = s.user_id
        JOIN classes c ON s.class_id = c.class_id
        JOIN teachersubjects ts ON c.class_id = ts.class_id
        WHERE u.role = 'student' AND ts.teacher_id = ?
    ";
    
    $params = [$teacher_record_id];
    $types = "s";
    
    if (!empty($class_filter)) {
        $query .= " AND c.class_id = ?";
        $params[] = $class_filter;
        $types .= "s";
    }
    
    if (!empty($subject_filter)) {
        $query .= " AND ts.subject_id = ?";
        $params[] = $subject_filter;
        $types .= "s";
    }
}

if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR s.student_id LIKE ? OR s.roll_number LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$query .= " ORDER BY c.class_name, s.roll_number";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch all users
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Students | Teacher Dashboard</title>
    <link href="../css/tailwind.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                            <h1 class="text-2xl font-semibold text-gray-900">Students</h1>
                            <div class="mt-4 md:mt-0">
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-3 py-1.5 rounded-full">
                                    Total: <?php echo count($students); ?> Students
                                </span>
                            </div>
                        </div>

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

                        <!-- Filter and Search -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <form action="view_students.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Filter by Class</label>
                                    <select id="class_id" name="class_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Classes</option>
                                        <?php foreach ($teacher_classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" <?php echo $class_filter == $class['class_id'] ? 'selected' : ''; ?>>
                                            <?php echo $class['class_name']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Filter by Subject</label>
                                    <select id="subject_id" name="subject_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">All Subjects</option>
                                        <?php foreach ($teacher_subjects as $subject): ?>
                                        <option value="<?php echo $subject['subject_id']; ?>" <?php echo $subject_filter == $subject['subject_id'] ? 'selected' : ''; ?>>
                                            <?php echo $subject['subject_name']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Students</label>
                                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, ID, or Roll Number" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                </div>
                                
                                <div class="flex items-end">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-filter mr-2"></i> Filter
                                    </button>
                                    <a href="view_students.php" class="ml-2 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-times mr-2"></i> Clear
                                    </a>
                                </div>
                           </form>
                       </div>

                        <!-- Students Table -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Students List</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($students)): ?>
                                            <tr>
                                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No students found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['student_id']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($student['class_name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($student['email']); ?></td>
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
<?php

?>
