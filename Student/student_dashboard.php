<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Get student details
$student = null;
$stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, c.academic_year, u.full_name, u.email 
                      FROM students s 
                      JOIN classes c ON s.class_id = c.class_id 
                      JOIN users u ON s.user_id = u.user_id 
                      WHERE s.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
}
$stmt->close();

// Get subjects for the student's class
$subjects = [];
$stmt = $conn->prepare("SELECT DISTINCT s.* FROM subjects s 
                      JOIN teachersubjects ts ON ts.subject_id = s.subject_id 
                      WHERE ts.academic_year = ?
                      ORDER BY s.subject_name");
$stmt->bind_param("s", $student['academic_year']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Get upcoming exams
$upcoming_exams = [];
$stmt = $conn->prepare("SELECT e.*, c.class_name, c.section FROM exams e 
                      JOIN classes c ON e.class_id = c.class_id 
                      WHERE e.class_id = ? AND e.status = 'upcoming' 
                      ORDER BY e.exam_date ASC LIMIT 5");
$stmt->bind_param("i", $student['class_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $upcoming_exams[] = $row;
}
$stmt->close();

// Get recent results
$recent_results = [];
$stmt = $conn->prepare("SELECT r.*, s.subject_name, e.exam_name 
                      FROM results r 
                      JOIN subjects s ON r.subject_id = s.subject_id 
                      JOIN exams e ON r.exam_id = e.exam_id 
                      WHERE r.student_id = ? 
                      ORDER BY r.created_at DESC LIMIT 10");
$stmt->bind_param("s", $student['student_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_results[] = $row;
}
$stmt->close();

// Get notifications
$notifications = [];
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Calculate overall performance
$overall_performance = [
    'total_subjects' => count($subjects),
    'subjects_with_results' => 0,
    'total_marks' => 0,
    'obtained_marks' => 0,
    'average_percentage' => 0,
    'average_grade' => 'N/A',
    'pass_count' => 0,
    'fail_count' => 0
];

if (!empty($recent_results)) {
    $overall_performance['subjects_with_results'] = count($recent_results);
    
    foreach ($recent_results as $result) {
        $overall_performance['obtained_marks'] += $result['theory_marks'] + ($result['practical_marks'] ?? 0);
        $overall_performance['total_marks'] += 100; // Assuming each subject is out of 100
        
        if ($result['grade'] != 'F') {
            $overall_performance['pass_count']++;
        } else {
            $overall_performance['fail_count']++;
        }
    }
    
    if ($overall_performance['total_marks'] > 0) {
        $overall_performance['average_percentage'] = ($overall_performance['obtained_marks'] / $overall_performance['total_marks']) * 100;
        
        // Determine average grade
        if ($overall_performance['average_percentage'] >= 90) {
            $overall_performance['average_grade'] = 'A+';
        } elseif ($overall_performance['average_percentage'] >= 80) {
            $overall_performance['average_grade'] = 'A';
        } elseif ($overall_performance['average_percentage'] >= 70) {
            $overall_performance['average_grade'] = 'B+';
        } elseif ($overall_performance['average_percentage'] >= 60) {
            $overall_performance['average_grade'] = 'B';
        } elseif ($overall_performance['average_percentage'] >= 50) {
            $overall_performance['average_grade'] = 'C+';
        } elseif ($overall_performance['average_percentage'] >= 40) {
            $overall_performance['average_grade'] = 'C';
        } elseif ($overall_performance['average_percentage'] >= 33) {
            $overall_performance['average_grade'] = 'D';
        } else {
            $overall_performance['average_grade'] = 'F';
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
    <title>Student Dashboard | Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include('student_sidebar.php'); ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include('top_navigation.php'); ?>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Student Profile Card -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-20 w-20 rounded-full bg-blue-600 flex items-center justify-center">
                                        <span class="text-2xl font-medium text-white"><?php echo substr($student['full_name'], 0, 1); ?></span>
                                    </div>
                                    <div class="ml-6">
                                        <h2 class="text-2xl font-bold text-gray-900"><?php echo $student['full_name']; ?></h2>
                                        <div class="mt-1 flex items-center">
                                            <span class="text-sm text-gray-600"><?php echo $student['email']; ?></span>
                                        </div>
                                        <div class="mt-1 flex items-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Student</span>
                                            <span class="ml-2 text-sm text-gray-500">Roll No: <?php echo $student['roll_number']; ?></span>
                                            <span class="ml-2 text-sm text-gray-500">Class: <?php echo $student['class_name'] . ' ' . $student['section']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Performance Overview -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Performance Overview</h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="bg-blue-50 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-blue-800">Average Grade</h4>
                                        <p class="text-3xl font-bold text-blue-600"><?php echo $overall_performance['average_grade']; ?></p>
                                    </div>
                                    <div class="bg-green-50 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-green-800">Average Percentage</h4>
                                        <p class="text-3xl font-bold text-green-600"><?php echo number_format($overall_performance['average_percentage'], 2); ?>%</p>
                                    </div>
                                    <div class="bg-yellow-50 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-yellow-800">Subjects Passed</h4>
                                        <p class="text-3xl font-bold text-yellow-600"><?php echo $overall_performance['pass_count']; ?>/<?php echo $overall_performance['subjects_with_results']; ?></p>
                                    </div>
                                    <div class="bg-purple-50 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-purple-800">Total Subjects</h4>
                                        <p class="text-3xl font-bold text-purple-600"><?php echo $overall_performance['total_subjects']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Results -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Recent Results</h3>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <?php if (empty($recent_results)): ?>
                                <p class="text-gray-500">No results available yet.</p>
                                <?php else: ?>
                                <ul class="divide-y divide-gray-200">
                                    <?php foreach ($recent_results as $result): ?>
                                    <li class="py-4">
                                        <div class="flex space-x-3">
                                            <div class="flex-1 space-y-1">
                                                <div class="flex items-center justify-between">
                                                    <h3 class="text-sm font-medium"><?php echo $result['subject_name']; ?></h3>
                                                    <p class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($result['created_at'])); ?></p>
                                                </div>
                                                <p class="text-sm text-gray-500">
                                                    <?php echo $result['exam_name']; ?> | 
                                                    Theory: <?php echo $result['theory_marks']; ?> | 
                                                    Practical: <?php echo $result['practical_marks'] ?? 'N/A'; ?>
                                                </p>
                                                <div class="mt-1">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        Grade: <?php echo $result['grade']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>

</html>
