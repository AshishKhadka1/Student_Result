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

// Get classes and subjects taught by this teacher
// Updated column names to match database schema (class_id instead of id)
$query = "
    SELECT DISTINCT c.class_id, c.name AS class_name, s.subject_id, s.name AS subject_name
    FROM Classes c
    JOIN Sections sec ON c.class_id = sec.class_id
    JOIN TeacherSubjects ts ON sec.section_id = ts.section_id
    JOIN Subjects s ON ts.subject_id = s.subject_id
    WHERE ts.teacher_id = ?
    ORDER BY c.name, s.name
";

// Check if query preparation was successful
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

$classes = [];
while ($row = $result->fetch_assoc()) {
    $class_id = $row['class_id'];
    if (!isset($classes[$class_id])) {
        $classes[$class_id] = [
            'id' => $class_id,
            'name' => $row['class_name'],
            'subjects' => []
        ];
    }
    $classes[$class_id]['subjects'][] = [
        'id' => $row['subject_id'],
        'name' => $row['subject_name']
    ];
}

// Get selected class and subject
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : (count($classes) > 0 ? array_key_first($classes) : 0);
$selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 
    (isset($classes[$selected_class_id]) && count($classes[$selected_class_id]['subjects']) > 0 ? 
    $classes[$selected_class_id]['subjects'][0]['id'] : 0);

// Get students in the selected class
$students = [];
if ($selected_class_id > 0) {
    // Get all sections for this class
    $sections_query = "
        SELECT sec.section_id
        FROM Sections sec
        JOIN TeacherSubjects ts ON sec.section_id = ts.section_id
        WHERE sec.class_id = ? AND ts.teacher_id = ? AND ts.subject_id = ?
    ";
    $sections_stmt = $conn->prepare($sections_query);
    if ($sections_stmt === false) {
        die("Error preparing sections statement: " . $conn->error);
    }
    
    $sections_stmt->bind_param("iii", $selected_class_id, $teacher_id, $selected_subject_id);
    $sections_stmt->execute();
    $sections_result = $sections_stmt->get_result();
    
    $section_ids = [];
    while ($section = $sections_result->fetch_assoc()) {
        $section_ids[] = $section['section_id'];
    }
    
    if (!empty($section_ids)) {
        // Convert array to comma-separated string for IN clause
        $section_ids_str = implode(',', $section_ids);
        
        // Get students in these sections
        $students_query = "
            SELECT s.student_id, s.roll_number, u.full_name, sec.name AS section_name, sec.section_id
            FROM Students s
            JOIN Users u ON s.user_id = u.id
            JOIN Sections sec ON s.section_id = sec.section_id
            WHERE s.section_id IN ($section_ids_str)
            ORDER BY sec.name, s.roll_number
        ";
        
        $students_result = $conn->query($students_query);
        if ($students_result === false) {
            die("Error executing students query: " . $conn->error);
        }
        
        while ($student = $students_result->fetch_assoc()) {
            $students[] = $student;
        }
        
        // Get performance data for each student in the selected subject
        if (!empty($students) && $selected_subject_id > 0) {
            foreach ($students as &$student) {
                // Get average marks for this student in this subject
                $performance_query = "
                    SELECT 
                        AVG(sr.marks) AS avg_marks,
                        COUNT(sr.result_id) AS exam_count,
                        MAX(CASE WHEN e.date = (SELECT MIN(date) FROM Exams WHERE exam_id IN (
                            SELECT exam_id FROM StudentResults WHERE student_id = ? AND subject_id = ?
                        )) THEN sr.marks END) AS first_exam_marks,
                        MAX(CASE WHEN e.date = (SELECT MAX(date) FROM Exams WHERE exam_id IN (
                            SELECT exam_id FROM StudentResults WHERE student_id = ? AND subject_id = ?
                        )) THEN sr.marks END) AS last_exam_marks
                    FROM StudentResults sr
                    JOIN Exams e ON sr.exam_id = e.exam_id
                    WHERE sr.student_id = ? AND sr.subject_id = ?
                ";
                
                $performance_stmt = $conn->prepare($performance_query);
                if ($performance_stmt === false) {
                    die("Error preparing performance statement: " . $conn->error);
                }
                
                $performance_stmt->bind_param("iiiiii", 
                    $student['student_id'], $selected_subject_id,
                    $student['student_id'], $selected_subject_id,
                    $student['student_id'], $selected_subject_id
                );
                $performance_stmt->execute();
                $performance_result = $performance_stmt->get_result();
                $performance = $performance_result->fetch_assoc();
                
                $student['performance'] = [
                    'avg_marks' => $performance['avg_marks'] ? round($performance['avg_marks'], 1) : null,
                    'exam_count' => $performance['exam_count'],
                    'first_exam_marks' => $performance['first_exam_marks'],
                    'last_exam_marks' => $performance['last_exam_marks'],
                    'progress' => $performance['first_exam_marks'] && $performance['last_exam_marks'] ? 
                        round($performance['last_exam_marks'] - $performance['first_exam_marks'], 1) : null
                ];
                
                // Get attendance data if available
                $attendance_query = "
                    SELECT 
                        COUNT(CASE WHEN status = 'present' THEN 1 END) AS present_count,
                        COUNT(CASE WHEN status = 'absent' THEN 1 END) AS absent_count,
                        COUNT(*) AS total_classes
                    FROM Attendance
                    WHERE student_id = ? AND subject_id = ?
                ";
                
                $attendance_stmt = $conn->prepare($attendance_query);
                if ($attendance_stmt !== false) {
                    $attendance_stmt->bind_param("ii", $student['student_id'], $selected_subject_id);
                    $attendance_stmt->execute();
                    $attendance_result = $attendance_stmt->get_result();
                    $attendance = $attendance_result->fetch_assoc();
                    
                    $student['attendance'] = [
                        'present' => $attendance['present_count'],
                        'absent' => $attendance['absent_count'],
                        'total' => $attendance['total_classes'],
                        'percentage' => $attendance['total_classes'] > 0 ? 
                            round(($attendance['present_count'] / $attendance['total_classes']) * 100, 1) : null
                    ];
                } else {
                    // Attendance table might not exist
                    $student['attendance'] = null;
                }
            }
        }
    }
}

// Get class average for comparison
$class_avg = null;
if ($selected_class_id > 0 && $selected_subject_id > 0) {
    $avg_query = "
        SELECT AVG(sr.marks) AS class_avg
        FROM StudentResults sr
        JOIN Students s ON sr.student_id = s.student_id
        JOIN Sections sec ON s.section_id = sec.section_id
        WHERE sec.class_id = ? AND sr.subject_id = ?
    ";
    
    $avg_stmt = $conn->prepare($avg_query);
    if ($avg_stmt !== false) {
        $avg_stmt->bind_param("ii", $selected_class_id, $selected_subject_id);
        $avg_stmt->execute();
        $avg_result = $avg_stmt->get_result();
        $avg_data = $avg_result->fetch_assoc();
        $class_avg = $avg_data['class_avg'] ? round($avg_data['class_avg'], 1) : null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Students - Teacher Dashboard</title>
    <link rel="stylesheet" href="../css/tailwind.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/teacher_topbar.php'; ?>
    
    <div class="flex">
        <?php include 'includes/teacher_sidebar.php'; ?>
        
        <div class="w-full p-4 md:ml-64">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">View Students</h1>
                <p class="text-gray-600">Manage and view students in your classes</p>
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
            
            <!-- Filter Form -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <form method="GET" action="" class="md:flex items-center space-y-4 md:space-y-0 md:space-x-4">
                    <div class="flex-1">
                        <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select id="class_id" name="class_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $selected_class_id == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex-1">
                        <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <select id="subject_id" name="subject_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                            <?php if (isset($classes[$selected_class_id])): ?>
                                <?php foreach ($classes[$selected_class_id]['subjects'] as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo $selected_subject_id == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Class Performance Summary -->
            <?php if ($class_avg !== null): ?>
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <h2 class="text-xl font-semibold mb-3">Class Performance Summary</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-blue-800">Class Average</h3>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $class_avg; ?>%</p>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-green-800">Top Performer</h3>
                        <?php
                        $top_performer = null;
                        $top_marks = 0;
                        foreach ($students as $student) {
                            if (isset($student['performance']['avg_marks']) && $student['performance']['avg_marks'] > $top_marks) {
                                $top_performer = $student;
                                $top_marks = $student['performance']['avg_marks'];
                            }
                        }
                        ?>
                        <?php if ($top_performer): ?>
                            <p class="text-lg font-semibold text-green-600"><?php echo htmlspecialchars($top_performer['full_name']); ?></p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $top_performer['performance']['avg_marks']; ?>%</p>
                        <?php else: ?>
                            <p class="text-lg text-green-600">No data available</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-yellow-800">Most Improved</h3>
                        <?php
                        $most_improved = null;
                        $highest_progress = 0;
                        foreach ($students as $student) {
                            if (isset($student['performance']['progress']) && $student['performance']['progress'] > $highest_progress) {
                                $most_improved = $student;
                                $highest_progress = $student['performance']['progress'];
                            }
                        }
                        ?>
                        <?php if ($most_improved): ?>
                            <p class="text-lg font-semibold text-yellow-600"><?php echo htmlspecialchars($most_improved['full_name']); ?></p>
                            <p class="text-2xl font-bold text-yellow-600">+<?php echo $most_improved['performance']['progress']; ?>%</p>
                        <?php else: ?>
                            <p class="text-lg text-yellow-600">No data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Students Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b">
                    <h2 class="text-xl font-semibold">Students List</h2>
                    <p class="text-gray-600">
                        <?php if (isset($classes[$selected_class_id])): ?>
                            <?php echo htmlspecialchars($classes[$selected_class_id]['name']); ?> - 
                            <?php 
                                $subject_name = '';
                                foreach ($classes[$selected_class_id]['subjects'] as $subject) {
                                    if ($subject['id'] == $selected_subject_id) {
                                        $subject_name = $subject['name'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($subject_name);
                            ?>
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if (empty($students)): ?>
                    <div class="p-6 text-center">
                        <p class="text-gray-500">No students found for the selected class and subject.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($student['roll_number']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($student['full_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($student['section_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (isset($student['performance']['avg_marks'])): ?>
                                                <div class="flex items-center">
                                                    <?php 
                                                        $avg_marks = $student['performance']['avg_marks'];
                                                        $color_class = '';
                                                        $icon_class = '';
                                                        
                                                        if ($avg_marks >= 80) {
                                                            $color_class = 'text-green-600 bg-green-100';
                                                            $icon_class = 'fas fa-arrow-up text-green-600';
                                                        } elseif ($avg_marks >= 60) {
                                                            $color_class = 'text-blue-600 bg-blue-100';
                                                            $icon_class = 'fas fa-equals text-blue-600';
                                                        } elseif ($avg_marks >= 40) {
                                                            $color_class = 'text-yellow-600 bg-yellow-100';
                                                            $icon_class = 'fas fa-exclamation text-yellow-600';
                                                        } else {
                                                            $color_class = 'text-red-600 bg-red-100';
                                                            $icon_class = 'fas fa-arrow-down text-red-600';
                                                        }
                                                    ?>
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $color_class; ?>">
                                                        <?php echo $avg_marks; ?>%
                                                    </span>
                                                    <span class="ml-2 text-xs text-gray-500">
                                                        (<?php echo $student['performance']['exam_count']; ?> exams)
                                                    </span>
                                                    
                                                    <?php if (isset($student['performance']['progress']) && $student['performance']['progress'] != 0): ?>
                                                        <span class="ml-2">
                                                            <?php if ($student['performance']['progress'] > 0): ?>
                                                                <i class="fas fa-arrow-up text-green-600"></i>
                                                                <span class="text-xs text-green-600">+<?php echo $student['performance']['progress']; ?>%</span>
                                                            <?php else: ?>
                                                                <i class="fas fa-arrow-down text-red-600"></i>
                                                                <span class="text-xs text-red-600"><?php echo $student['performance']['progress']; ?>%</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">No data</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if (isset($student['attendance']) && $student['attendance']['total'] > 0): ?>
                                                <div class="flex items-center">
                                                    <?php 
                                                        $attendance_pct = $student['attendance']['percentage'];
                                                        $att_color_class = '';
                                                        
                                                        if ($attendance_pct >= 90) {
                                                            $att_color_class = 'text-green-600 bg-green-100';
                                                        } elseif ($attendance_pct >= 75) {
                                                            $att_color_class = 'text-blue-600 bg-blue-100';
                                                        } elseif ($attendance_pct >= 60) {
                                                            $att_color_class = 'text-yellow-600 bg-yellow-100';
                                                        } else {
                                                            $att_color_class = 'text-red-600 bg-red-100';
                                                        }
                                                    ?>
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $att_color_class; ?>">
                                                        <?php echo $attendance_pct; ?>%
                                                    </span>
                                                    <span class="ml-2 text-xs text-gray-500">
                                                        (<?php echo $student['attendance']['present']; ?>/<?php echo $student['attendance']['total']; ?>)
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">No data</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="student_results.php?student_id=<?php echo $student['student_id']; ?>&subject_id=<?php echo $selected_subject_id; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-chart-line mr-1"></i> Results
                                            </a>
                                            <a href="student_profile.php?student_id=<?php echo $student['student_id']; ?>" class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-user mr-1"></i> Profile
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
</body>
</html>
