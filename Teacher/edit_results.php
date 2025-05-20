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
$success_message = '';
$error_message = '';

// Get subject ID, class ID, and exam ID from URL parameters
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Verify that the teacher is assigned to this subject and class
$verify_query = "SELECT COUNT(*) as count FROM teacher_subjects 
                WHERE teacher_id = ? AND subject_id = ? AND class_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("iii", $teacher_id, $subject_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row['count'] == 0) {
    // Teacher is not assigned to this subject/class
    header("Location: teacher_dashboard.php?error=unauthorized");
    exit();
}

// Get subject and exam details
$subject_name = '';
$exam_name = '';
$class_name = '';

$details_query = "SELECT s.name as subject_name, e.name as exam_name, c.name as class_name
                 FROM subjects s, exams e, classes c
                 WHERE s.id = ? AND e.id = ? AND c.id = ?";
$stmt = $conn->prepare($details_query);
$stmt->bind_param("iii", $subject_id, $exam_id, $class_id);
$stmt->execute();
$details_result = $stmt->get_result();
if ($details_result->num_rows > 0) {
    $details = $details_result->fetch_assoc();
    $subject_name = $details['subject_name'];
    $exam_name = $details['exam_name'];
    $class_name = $details['class_name'];
}
$stmt->close();

// Get students and their results
$students_query = "SELECT s.id, s.name, s.roll_number, 
                  r.id as result_id, r.marks, r.grade
                  FROM students s
                  LEFT JOIN results r ON s.id = r.student_id AND r.subject_id = ? AND r.exam_id = ?
                  WHERE s.class_id = ?
                  ORDER BY s.roll_number";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("iii", $subject_id, $exam_id, $class_id);
$stmt->execute();
$students_result = $stmt->get_result();
$students = [];
while ($student = $students_result->fetch_assoc()) {
    $students[] = $student;
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    // Begin transaction
    $conn->begin_transaction();
    try {
        foreach ($_POST['student_id'] as $index => $student_id) {
            $marks = $_POST['marks'][$index];
            $result_id = $_POST['result_id'][$index];
            
            // Calculate grade based on marks
            $grade = 'F';
            if ($marks >= 90) {
                $grade = 'A';
            } elseif ($marks >= 80) {
                $grade = 'B';
            } elseif ($marks >= 70) {
                $grade = 'C';
            } elseif ($marks >= 60) {
                $grade = 'D';
            }
            
            if (empty($result_id)) {
                // Insert new result
                $insert_query = "INSERT INTO results (student_id, subject_id, exam_id, marks, grade, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("iiids", $student_id, $subject_id, $exam_id, $marks, $grade);
                $stmt->execute();
                $stmt->close();
            } else {
                // Update existing result
                $update_query = "UPDATE results SET marks = ?, grade = ?, updated_at = NOW()
                               WHERE id = ? AND subject_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("dsii", $marks, $grade, $result_id, $subject_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // Commit transaction
        $conn->commit();
        $success_message = "Results saved successfully!";
        
        // Refresh the student data
        $stmt = $conn->prepare($students_query);
        $stmt->bind_param("iii", $subject_id, $exam_id, $class_id);
        $stmt->execute();
        $students_result = $stmt->get_result();
        $students = [];
        while ($student = $students_result->fetch_assoc()) {
            $students[] = $student;
        }
        $stmt->close();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Results - <?php echo $subject_name; ?></title>
    <link href="../css/tailwind.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'includes/teacher_topbar.php'; ?>
    
    <div class="flex">
        <?php include 'includes/teacher_sidebar.php'; ?>
        
        <div class="flex-1 p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Edit Results</h1>
                <a href="teacher_dashboard.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
            
            <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $success_message; ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $error_message; ?></p>
            </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-500">Subject</h3>
                        <p class="text-lg font-semibold text-blue-700"><?php echo $subject_name; ?></p>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-500">Class</h3>
                        <p class="text-lg font-semibold text-green-700"><?php echo $class_name; ?></p>
                    </div>
                    
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-500">Exam</h3>
                        <p class="text-lg font-semibold text-purple-700"><?php echo $exam_name; ?></p>
                    </div>
                </div>
                
                <?php if (empty($students)): ?>
                <div class="bg-yellow-100 text-yellow-700 p-4 rounded">
                    <p>No students found in this class.</p>
                </div>
                <?php else: ?>
                <form method="POST" action="">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-left">Roll Number</th>
                                    <th class="py-3 px-6 text-left">Student Name</th>
                                    <th class="py-3 px-6 text-center">Marks (0-100)</th>
                                    <th class="py-3 px-6 text-center">Current Grade</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm">
                                <?php foreach ($students as $index => $student): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 text-left"><?php echo $student['roll_number']; ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo $student['name']; ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <input type="hidden" name="student_id[<?php echo $index; ?>]" value="<?php echo $student['id']; ?>">
                                        <input type="hidden" name="result_id[<?php echo $index; ?>]" value="<?php echo $student['result_id']; ?>">
                                        <input type="number" name="marks[<?php echo $index; ?>]" 
                                               value="<?php echo isset($student['marks']) ? $student['marks'] : ''; ?>" 
                                               min="0" max="100" step="0.01" required
                                               class="w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    </td>
                                    <td class="py-3 px-6 text-center">
                                        <?php if (isset($student['grade'])): ?>
                                        <span class="<?php 
                                            if ($student['grade'] == 'A') echo 'bg-green-200 text-green-800';
                                            elseif ($student['grade'] == 'B') echo 'bg-blue-200 text-blue-800';
                                            elseif ($student['grade'] == 'C') echo 'bg-yellow-200 text-yellow-800';
                                            elseif ($student['grade'] == 'D') echo 'bg-orange-200 text-orange-800';
                                            else echo 'bg-red-200 text-red-800';
                                        ?> py-1 px-3 rounded-full text-xs">
                                            <?php echo $student['grade']; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="bg-gray-200 text-gray-800 py-1 px-3 rounded-full text-xs">Not Graded</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-6 flex justify-between">
                        <button type="submit" name="save_results" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-save mr-2"></i> Save Results
                        </button>
                        
                        <a href="teacher_dashboard.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i> Grading Information
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div class="bg-green-100 p-3 rounded-lg text-center">
                        <h3 class="font-bold text-green-800">A Grade</h3>
                        <p class="text-green-700">90-100 Marks</p>
                    </div>
                    
                    <div class="bg-blue-100 p-3 rounded-lg text-center">
                        <h3 class="font-bold text-blue-800">B Grade</h3>
                        <p class="text-blue-700">80-89 Marks</p>
                    </div>
                    
                    <div class="bg-yellow-100 p-3 rounded-lg text-center">
                        <h3 class="font-bold text-yellow-800">C Grade</h3>
                        <p class="text-yellow-700">70-79 Marks</p>
                    </div>
                    
                    <div class="bg-orange-100 p-3 rounded-lg text-center">
                        <h3 class="font-bold text-orange-800">D Grade</h3>
                        <p class="text-orange-700">60-69 Marks</p>
                    </div>
                    
                    <div class="bg-red-100 p-3 rounded-lg text-center">
                        <h3 class="font-bold text-red-800">F Grade</h3>
                        <p class="text-red-700">0-59 Marks</p>
                    </div>
                </div>
                
                <div class="mt-4 text-sm text-gray-600">
                    <p><i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i> Grades are automatically calculated based on the marks entered.</p>
                    <p><i class="fas fa-info-circle text-blue-500 mr-1"></i> All changes are saved immediately when you click the "Save Results" button.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
