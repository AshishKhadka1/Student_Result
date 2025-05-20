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
$result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

// Validate parameters
if (!$result_id || !$student_id || !$subject_id || !$class_id || !$section_id) {
    $_SESSION['error'] = "Invalid parameters provided.";
    header("Location: teacher_dashboard.php");
    exit();
}

// Verify that the teacher is assigned to this subject/class/section
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM TeacherSubjects 
    WHERE teacher_id = ? AND subject_id = ? AND class_id = ? AND section_id = ?
");
$stmt->bind_param("iiii", $teacher_id, $subject_id, $class_id, $section_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Not authorized to view this class
    $_SESSION['error'] = "You are not authorized to edit results for this class.";
    header("Location: teacher_dashboard.php");
    exit();
}

// Get result details
$stmt = $conn->prepare("
    SELECT r.*, e.name as exam_name, e.max_marks, e.exam_date
    FROM Results r
    JOIN Exams e ON r.exam_id = e.id
    WHERE r.id = ? AND r.student_id = ? AND r.subject_id = ?
");
$stmt->bind_param("iii", $result_id, $student_id, $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Result not found.";
    header("Location: student_results.php?student_id=" . $student_id . "&subject_id=" . $subject_id . "&class_id=" . $class_id . "&section_id=" . $section_id);
    exit();
}

$result_data = $result->fetch_assoc();

// Get student details
$stmt = $conn->prepare("
    SELECT name, roll_number FROM Students WHERE id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Get subject, class and section names
$stmt = $conn->prepare("SELECT name FROM Subjects WHERE id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$subject_name = $result->fetch_assoc()['name'];

$stmt = $conn->prepare("SELECT name FROM Classes WHERE id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();
$class_name = $result->fetch_assoc()['name'];

$stmt = $conn->prepare("SELECT name FROM Sections WHERE id = ?");
$stmt->bind_param("i", $section_id);
$stmt->execute();
$result = $stmt->get_result();
$section_name = $result->fetch_assoc()['name'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $marks_obtained = isset($_POST['marks_obtained']) ? floatval($_POST['marks_obtained']) : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate marks
    if ($marks_obtained < 0) {
        $_SESSION['error'] = "Marks cannot be negative.";
    } elseif ($marks_obtained > $result_data['max_marks']) {
        $_SESSION['error'] = "Marks cannot exceed maximum marks for this exam.";
    } else {
        // Calculate grade based on percentage
        $percentage = ($marks_obtained / $result_data['max_marks']) * 100;
        $grade = '';
        
        if ($percentage >= 90) {
            $grade = 'A+';
        } elseif ($percentage >= 80) {
            $grade = 'A';
        } elseif ($percentage >= 70) {
            $grade = 'B+';
        } elseif ($percentage >= 60) {
            $grade = 'B';
        } elseif ($percentage >= 50) {
            $grade = 'C+';
        } elseif ($percentage >= 40) {
            $grade = 'C';
        } elseif ($percentage >= 33) {
            $grade = 'D';
        } else {
            $grade = 'F';
        }
        
        // Update result
        $stmt = $conn->prepare("
            UPDATE Results 
            SET marks_obtained = ?, grade = ?, remarks = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("dssii", $marks_obtained, $grade, $remarks, $teacher_id, $result_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Result updated successfully.";
            header("Location: student_results.php?student_id=" . $student_id . "&subject_id=" . $subject_id . "&class_id=" . $class_id . "&section_id=" . $section_id);
            exit();
        } else {
            $_SESSION['error'] = "Failed to update result: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Result - Teacher Dashboard</title>
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
                <h1 class="text-3xl font-bold text-gray-800">Edit Result</h1>
                <p class="text-gray-600">
                    Student: <?php echo htmlspecialchars($student['name']); ?> (Roll No: <?php echo htmlspecialchars($student['roll_number']); ?>) | 
                    Subject: <?php echo htmlspecialchars($subject_name); ?> | 
                    Class: <?php echo htmlspecialchars($class_name); ?> | 
                    Section: <?php echo htmlspecialchars($section_name); ?>
                </p>
            </div>
            
            <!-- Display Messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Edit Result Form -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="border-b px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Edit Result for <?php echo htmlspecialchars($result_data['exam_name']); ?></h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Exam:</label>
                            <p class="py-2 px-3 bg-gray-100 rounded"><?php echo htmlspecialchars($result_data['exam_name']); ?></p>
                            <p class="text-sm text-gray-500 mt-1">
                                Max Marks: <?php echo $result_data['max_marks']; ?>, 
                                Date: <?php echo date('d M Y', strtotime($result_data['exam_date'])); ?>
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <label for="marks_obtained" class="block text-gray-700 text-sm font-bold mb-2">Marks Obtained:</label>
                            <input type="number" id="marks_obtained" name="marks_obtained" step="0.01" min="0" max="<?php echo $result_data['max_marks']; ?>" value="<?php echo $result_data['marks_obtained']; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            <p class="text-sm text-gray-500 mt-1">Enter marks obtained by the student in this exam (max: <?php echo $result_data['max_marks']; ?>).</p>
                        </div>
                        
                        <div class="mb-4">
                            <label for="remarks" class="block text-gray-700 text-sm font-bold mb-2">Remarks (Optional):</label>
                            <textarea id="remarks" name="remarks" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="3"><?php echo htmlspecialchars($result_data['remarks']); ?></textarea>
                            <p class="text-sm text-gray-500 mt-1">Add any additional comments or feedback for the student.</p>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                <i class="fas fa-save mr-2"></i>Update Result
                            </button>
                            <a href="student_results.php?student_id=<?php echo $student_id; ?>&subject_id=<?php echo $subject_id; ?>&class_id=<?php echo $class_id; ?>&section_id=<?php echo $section_id; ?>" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
</body>
</html>
