<?php
/**
 * Database helper functions for the Result Management System
 */

/**
 * Get subjects assigned to a teacher
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @return array Array of assigned subjects
 */
function getTeacherSubjects($conn, $teacher_id) {
    $subjects = [];
    $stmt = $conn->prepare("SELECT ts.*, s.subject_name, s.full_marks_theory, s.full_marks_practical, 
                           s.pass_marks_theory, s.pass_marks_practical, c.class_id, c.class_name, c.section 
                           FROM teachersubjects ts 
                           JOIN subjects s ON ts.subject_id = s.subject_id 
                           JOIN classes c ON ts.class_id = c.class_id 
                           WHERE ts.teacher_id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
    return $subjects;
}

/**
 * Get students in a class
 * 
 * @param mysqli $conn Database connection
 * @param int $class_id Class ID
 * @return array Array of students
 */
function getClassStudents($conn, $class_id) {
    $students = [];
    $stmt = $conn->prepare("SELECT s.*, u.full_name, u.email 
                           FROM students s 
                           JOIN users u ON s.user_id = u.user_id 
                           WHERE s.class_id = ? 
                           ORDER BY s.roll_number ASC");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    return $students;
}

/**
 * Get exams for a class
 * 
 * @param mysqli $conn Database connection
 * @param int $class_id Class ID
 * @return array Array of exams
 */
function getClassExams($conn, $class_id) {
    $exams = [];
    $stmt = $conn->prepare("SELECT * FROM exams WHERE class_id = ? ORDER BY start_date DESC");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    $stmt->close();
    return $exams;
}

/**
 * Get student results for a specific exam and subject
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $subject_id Subject ID
 * @param int $class_id Class ID
 * @return array Array of results
 */
function getStudentResults($conn, $exam_id, $subject_id, $class_id) {
    $results = [];
    
    // Get all students in the class
    $students = getClassStudents($conn, $class_id);
    
    // Get existing results
    $stmt = $conn->prepare("SELECT * FROM results WHERE exam_id = ? AND subject_id = ?");
    $stmt->bind_param("ii", $exam_id, $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingResults = [];
    while ($row = $result->fetch_assoc()) {
        $existingResults[$row['student_id']] = $row;
    }
    $stmt->close();
    
    // Combine students with their results (or empty results if not found)
    foreach ($students as $student) {
        if (isset($existingResults[$student['student_id']])) {
            $results[] = array_merge($student, $existingResults[$student['student_id']]);
        } else {
            $results[] = array_merge($student, [
                'result_id' => null,
                'theory_marks' => null,
                'practical_marks' => null,
                'total_marks' => null,
                'percentage' => null,
                'grade' => null,
                'grade_point' => null,
                'remarks' => null,
                'status' => 'pending'
            ]);
        }
    }
    
    return $results;
}

/**
 * Get class performance statistics for a subject and exam
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $subject_id Subject ID
 * @param int $class_id Class ID
 * @return array Performance statistics
 */
function getClassPerformance($conn, $exam_id, $subject_id, $class_id) {
    $results = getStudentResults($conn, $exam_id, $subject_id, $class_id);
    
    // Initialize statistics
    $stats = [
        'total_students' => count($results),
        'submitted' => 0,
        'passed' => 0,
        'failed' => 0,
        'average_marks' => 0,
        'highest_marks' => 0,
        'lowest_marks' => PHP_INT_MAX,
        'grade_distribution' => [
            'A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0, 
            'C+' => 0, 'C' => 0, 'D+' => 0, 'D' => 0, 'E' => 0
        ]
    ];
    
    $totalMarks = 0;
    
    foreach ($results as $result) {
        if ($result['result_id'] !== null) {
            $stats['submitted']++;
            $totalMarks += $result['total_marks'];
            
            // Update highest and lowest marks
            if ($result['total_marks'] > $stats['highest_marks']) {
                $stats['highest_marks'] = $result['total_marks'];
            }
            if ($result['total_marks'] < $stats['lowest_marks']) {
                $stats['lowest_marks'] = $result['total_marks'];
            }
            
            // Update grade distribution
            if (isset($stats['grade_distribution'][$result['grade']])) {
                $stats['grade_distribution'][$result['grade']]++;
            }
            
            // Check if passed
            if ($result['status'] == 'pass') {
                $stats['passed']++;
            } else {
                $stats['failed']++;
            }
        }
    }
    
    // Calculate average marks
    if ($stats['submitted'] > 0) {
        $stats['average_marks'] = round($totalMarks / $stats['submitted'], 2);
    }
    
    // If no results, set lowest marks to 0
    if ($stats['lowest_marks'] == PHP_INT_MAX) {
        $stats['lowest_marks'] = 0;
    }
    
    return $stats;
}

/**
 * Save or update student results
 * 
 * @param mysqli $conn Database connection
 * @param array $data Result data
 * @return bool True if successful, false otherwise
 */
function saveStudentResult($conn, $data) {
    require_once 'grade_calculator.php';
    
    // Calculate grade and other metrics
    $gradeData = calculateFinalGrade(
        $data['theory_marks'], 
        $data['practical_marks'], 
        $data['full_marks_theory'], 
        $data['full_marks_practical']
    );
    
    // Determine pass/fail status
    $status = 'pass';
    if ($data['theory_marks'] < $data['pass_marks_theory'] || 
        $data['practical_marks'] < $data['pass_marks_practical'] ||
        $gradeData['grade'] == 'E') {
        $status = 'fail';
    }
    
    // Check if result already exists
    $stmt = $conn->prepare("SELECT result_id FROM results WHERE exam_id = ? AND subject_id = ? AND student_id = ?");
    $stmt->bind_param("iii", $data['exam_id'], $data['subject_id'], $data['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    if ($exists) {
        // Update existing result
        $stmt = $conn->prepare("UPDATE results SET 
                               theory_marks = ?, 
                               practical_marks = ?, 
                               total_marks = ?, 
                               percentage = ?, 
                               grade = ?, 
                               grade_point = ?, 
                               remarks = ?, 
                               status = ?, 
                               updated_at = NOW() 
                               WHERE exam_id = ? AND subject_id = ? AND student_id = ?");
        $stmt->bind_param(
            "ddddsdssiiii", 
            $data['theory_marks'], 
            $data['practical_marks'], 
            $gradeData['total_marks'], 
            $gradeData['percentage'], 
            $gradeData['grade'], 
            $gradeData['grade_point'], 
            $gradeData['remarks'], 
            $status, 
            $data['exam_id'], 
            $data['subject_id'], 
            $data['student_id']
        );
    } else {
        // Insert new result
        $stmt = $conn->prepare("INSERT INTO results (
                               exam_id, subject_id, student_id, theory_marks, practical_marks, 
                               total_marks, percentage, grade, grade_point, remarks, status, created_at, updated_at
                               ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param(
            "iiiddddsdsss", 
            $data['exam_id'], 
            $data['subject_id'], 
            $data['student_id'], 
            $data['theory_marks'], 
            $data['practical_marks'], 
            $gradeData['total_marks'], 
            $gradeData['percentage'], 
            $gradeData['grade'], 
            $gradeData['grade_point'], 
            $gradeData['remarks'], 
            $status
        );
    }
    
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}
?>

