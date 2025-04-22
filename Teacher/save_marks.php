<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

// Include database connection and helper functions
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

require_once 'includes/db_functions.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exam_id = $_POST['exam_id'];
    $subject_id = $_POST['subject_id'];
    $class_id = $_POST['class_id'];
    $full_marks_theory = $_POST['full_marks_theory'];
    $full_marks_practical = $_POST['full_marks_practical'];
    $pass_marks_theory = $_POST['pass_marks_theory'];
    $pass_marks_practical = $_POST['pass_marks_practical'];
    
    // Get teacher ID
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    $stmt->close();
    
    // Verify teacher is assigned to this subject
    $stmt = $conn->prepare("SELECT * FROM teachersubjects WHERE teacher_id = ? AND subject_id = ? AND class_id = ?");
    $stmt->bind_param("iii", $teacher['teacher_id'], $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        $_SESSION['error'] = "You are not authorized to edit marks for this subject.";
        header("Location: edit_marks.php");
        exit();
    }
    $stmt->close();
    
    // Process student marks
    $success = true;
    $students = $_POST['students'];
    
    foreach ($students as $student) {
        if (isset($student['theory_marks']) || isset($student['practical_marks'])) {
            $student_id = $student['student_id'];
            $theory_marks = isset($student['theory_marks']) ? $student['theory_marks'] : 0;
            $practical_marks = isset($student['practical_marks']) ? $student['practical_marks'] : 0;
            
            // Prepare data for saving
            $data = [
                'exam_id' => $exam_id,
                'subject_id' => $subject_id,
                'student_id' => $student_id,
                'theory_marks' => $theory_marks,
                'practical_marks' => $practical_marks,
                'full_marks_theory' => $full_marks_theory,
                'full_marks_practical' => $full_marks_practical,
                'pass_marks_theory' => $pass_marks_theory,
                'pass_marks_practical' => $pass_marks_practical
            ];
            
            // Save result
            if (!saveStudentResult($conn, $data)) {
                $success = false;
            }
        }
    }
    
    if ($success) {
        $_SESSION['success'] = "Marks saved successfully.";
    } else {
        $_SESSION['error'] = "Error saving marks. Please try again.";
    }
    
    // Add notification for admin
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, created_at) 
                           VALUES (1, 'Marks Updated', 'Teacher has updated marks for subject ID: $subject_id, exam ID: $exam_id', 'result', NOW())");
    $stmt->execute();
    $stmt->close();
    
    // Redirect back to edit marks page
    header("Location: edit_marks.php?subject_id=$subject_id&class_id=$class_id&exam_id=$exam_id");
    exit();
}

// If not POST request, redirect to edit marks page
header("Location: edit_marks.php");
exit();
?>

