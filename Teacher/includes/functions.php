<?php
/**
 * Helper functions for the Teacher Dashboard
 */

/**
 * Get teacher details by user ID
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher user ID
 * @return array Teacher details
 */
function getTeacherDetails($conn, $teacher_id) {
    // First check if the teacher exists in the users table
    $stmt = $conn->prepare("SELECT user_id, full_name, email, profile_image FROM users WHERE user_id = ? AND role = 'teacher'");
    if (!$stmt) {
        error_log("Database error in getTeacherDetails: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        error_log("Teacher user not found with ID: " . $teacher_id);
        return null;
    }
    
    // Now get teacher-specific details
    $stmt = $conn->prepare("SELECT t.* FROM teachers t WHERE t.user_id = ?");
    if (!$stmt) {
        error_log("Database error in getTeacherDetails: " . $conn->error);
        return $user; // Return just the user data if we can't get teacher data
    }
    
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    $stmt->close();
    
    // Merge user and teacher data
    if ($teacher) {
        return array_merge($user, $teacher);
    } else {
        // If no teacher record exists, create a basic structure with user data
        error_log("Teacher record not found for user ID: " . $teacher_id . ". Using basic user data.");
        return [
            'user_id' => $user['user_id'],
            'name' => $user['full_name'],
            'email' => $user['email'],
            'profile_image' => $user['profile_image'],
            'teacher_id' => null,
            'department' => 'Not Assigned',
            'designation' => 'Teacher',
            'employee_id' => 'T' . str_pad($user['user_id'], 3, '0', STR_PAD_LEFT)
        ];
    }
}

/**
 * Get classes assigned to a teacher
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher user ID
 * @return array Assigned classes
 */
function getTeacherAssignedClasses($conn, $teacher_id) {
    $stmt = $conn->prepare("SELECT DISTINCT c.*, 
                           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.class_id) as student_count,
                           (SELECT u.full_name FROM users u JOIN teachers t ON u.user_id = t.user_id WHERE t.teacher_id = c.class_teacher_id) as class_teacher_name
                           FROM classes c 
                           JOIN teachersubjects ts ON c.class_id = ts.class_id 
                           JOIN teachers t ON ts.teacher_id = t.teacher_id 
                           WHERE t.user_id = ?
                           ORDER BY c.academic_year DESC, c.class_name ASC");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt->close();
    
    return $classes;
}

/**
 * Get subjects assigned to a teacher
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher user ID
 * @return array Assigned subjects
 */
function getTeacherAssignedSubjects($conn, $teacher_id) {
    $stmt = $conn->prepare("SELECT ts.*, s.subject_name, s.subject_code, c.class_name, c.section, c.academic_year,
                           (SELECT COUNT(*) FROM results r 
                            JOIN exams e ON r.exam_id = e.exam_id 
                            WHERE r.subject_id = s.subject_id AND e.class_id = c.class_id) as marks_count,
                           (SELECT COUNT(*) FROM students st WHERE st.class_id = c.class_id) as student_count,
                           CASE 
                               WHEN (SELECT COUNT(*) FROM results r 
                                    JOIN exams e ON r.exam_id = e.exam_id 
                                    WHERE r.subject_id = s.subject_id AND e.class_id = c.class_id) = 0 THEN 'pending'
                               WHEN (SELECT COUNT(*) FROM results r 
                                    JOIN exams e ON r.exam_id = e.exam_id 
                                    WHERE r.subject_id = s.subject_id AND e.class_id = c.class_id) < 
                                   (SELECT COUNT(*) FROM students st WHERE st.class_id = c.class_id) THEN 'in_progress'
                               ELSE 'completed'
                           END as marks_status
                           FROM teachersubjects ts 
                           JOIN subjects s ON ts.subject_id = s.subject_id 
                           JOIN classes c ON ts.class_id = c.class_id 
                           JOIN teachers t ON ts.teacher_id = t.teacher_id 
                           WHERE t.user_id = ?
                           ORDER BY c.academic_year DESC, c.class_name ASC, s.subject_name ASC");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
    
    return $subjects;
}

/**
 * Get recent activities for a teacher
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher user ID
 * @param int $limit Number of activities to return
 * @return array Recent activities
 */
function getTeacherRecentActivities($conn, $teacher_id, $limit = 5) {
    $stmt = $conn->prepare("SELECT * FROM teacher_activities 
                           WHERE teacher_id = ? 
                           ORDER BY timestamp DESC 
                           LIMIT ?");
    $stmt->bind_param("ii", $teacher_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    $stmt->close();
    
    return $activities;
}

/**
 * Get pending tasks for a teacher
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher user ID
 * @return array Pending tasks
 */
function getTeacherPendingTasks($conn, $teacher_id) {
    $tasks = [];
    
    // Get subjects with pending marks
    $stmt = $conn->prepare("SELECT ts.*, s.subject_name, c.class_name, c.section, e.exam_id, e.exam_name, e.end_date,
                           (SELECT COUNT(*) FROM students st WHERE st.class_id = c.class_id) as student_count,
                           (SELECT COUNT(*) FROM results r WHERE r.exam_id = e.exam_id AND r.subject_id = s.subject_id) as marks_count
                           FROM teachersubjects ts 
                           JOIN subjects s ON ts.subject_id = s.subject_id 
                           JOIN classes c ON ts.class_id = c.class_id 
                           JOIN teachers t ON ts.teacher_id = t.teacher_id 
                           JOIN exams e ON c.class_id = e.class_id
                           WHERE t.user_id = ? AND e.status = 'completed' AND
                           (SELECT COUNT(*) FROM results r WHERE r.exam_id = e.exam_id AND r.subject_id = s.subject_id) <
                           (SELECT COUNT(*) FROM students st WHERE st.class_id = c.class_id)
                           ORDER BY e.end_date ASC");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $remaining = $row['student_count'] - $row['marks_count'];
        $tasks[] = [
            'description' => "Enter marks for {$row['subject_name']} - {$row['class_name']} {$row['section']} ({$row['exam_name']}) - {$remaining} students remaining",
            'due_date' => date('M d, Y', strtotime($row['end_date'] . ' + 7 days')),
            'action_url' => "edit_marks.php?subject_id={$row['subject_id']}&class_id={$row['class_id']}&exam_id={$row['exam_id']}"
        ];
    }
    $stmt->close();
    
    return $tasks;
}

/**
 * Get total number of students assigned to a teacher
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher user ID
 * @return int Total number of students
 */
function getTotalStudentsForTeacher($conn, $teacher_id) {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT s.student_id) as total_students
                           FROM students s
                           JOIN classes c ON s.class_id = c.class_id
                           JOIN teachersubjects ts ON c.class_id = ts.class_id
                           JOIN teachers t ON ts.teacher_id = t.teacher_id
                           WHERE t.user_id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['total_students'];
}

/**
 * Check if a teacher is assigned to a subject and class
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher user ID
 * @param int $subject_id Subject ID
 * @param int $class_id Class ID
 * @return bool True if assigned, false otherwise
 */
function isTeacherAssignedToSubject($conn, $teacher_id, $subject_id, $class_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count
                           FROM teachersubjects ts
                           JOIN teachers t ON ts.teacher_id = t.teacher_id
                           WHERE t.user_id = ? AND ts.subject_id = ? AND ts.class_id = ?");
    $stmt->bind_param("iii", $teacher_id, $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] > 0;
}

/**
 * Check if a teacher is assigned to a class
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher user ID
 * @param int $class_id Class ID
 * @return bool True if assigned, false otherwise
 */
function isTeacherAssignedToClass($conn, $teacher_id, $class_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count
                           FROM teachersubjects ts
                           JOIN teachers t ON ts.teacher_id = t.teacher_id
                           WHERE t.user_id = ? AND ts.class_id = ?");
    $stmt->bind_param("ii", $teacher_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] > 0;
}

/**
 * Get subject details
 * 
 * @param mysqli $conn Database connection
 * @param int $subject_id Subject ID
 * @return array Subject details
 */
function getSubjectDetails($conn, $subject_id) {
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE subject_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subject = $result->fetch_assoc();
    $stmt->close();
    
    return $subject;
}

/**
 * Get class details
 * 
 * @param mysqli $conn Database connection
 * @param int $class_id Class ID
 * @return array Class details
 */
function getClassDetails($conn, $class_id) {
    $stmt = $conn->prepare("SELECT c.*, 
                           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.class_id) as student_count,
                           (SELECT u.full_name FROM users u JOIN teachers t ON u.user_id = t.user_id WHERE t.teacher_id = c.class_teacher_id) as class_teacher_name
                           FROM classes c 
                           WHERE c.class_id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $class = $result->fetch_assoc();
    $stmt->close();
    
    return $class;
}

/**
 * Get exams for a class
 * 
 * @param mysqli $conn Database connection
 * @param int $class_id Class ID
 * @return array Exams
 */
function getExamsForClass($conn, $class_id) {
    $stmt = $conn->prepare("SELECT * FROM exams WHERE class_id = ? ORDER BY start_date DESC");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exams = [];
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    $stmt->close();
    
    return $exams;
}

/**
 * Get exam details
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @return array Exam details
 */
function getExamDetails($conn, $exam_id) {
    $stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exam = $result->fetch_assoc();
    $stmt->close();
    
    return $exam;
}

/**
 * Get students in a class
 * 
 * @param mysqli $conn Database connection
 * @param int $class_id Class ID
 * @return array Students
 */
function getStudentsInClass($conn, $class_id) {
    $stmt = $conn->prepare("SELECT s.*, u.full_name 
                           FROM students s 
                           JOIN users u ON s.user_id = u.user_id 
                           WHERE s.class_id = ? 
                           ORDER BY s.roll_number ASC");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    
    return $students;
}

/**
 * Get student marks for a subject and exam
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $subject_id Subject ID
 * @param int $class_id Class ID
 * @return array Student marks
 */
function getStudentMarks($conn, $exam_id, $subject_id, $class_id) {
    $stmt = $conn->prepare("SELECT r.* 
                           FROM results r 
                           JOIN students s ON r.student_id = s.student_id 
                           WHERE r.exam_id = ? AND r.subject_id = ? AND s.class_id = ?");
    $stmt->bind_param("iii", $exam_id, $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $marks = [];
    while ($row = $result->fetch_assoc()) {
        $marks[$row['student_id']] = $row;
    }
    $stmt->close();
    
    return $marks;
}

/**
 * Save student marks
 * 
 * @param mysqli $conn Database connection
 * @param array $post_data POST data
 * @param int $teacher_id Teacher user ID
 * @return array Result with success status and message
 */
function saveStudentMarks($conn, $post_data, $teacher_id) {
    require_once 'grade_calculator.php';
    
    $exam_id = $post_data['exam_id'];
    $subject_id = $post_data['subject_id'];
    $class_id = $post_data['class_id'];
    
    // Get subject details for pass marks
    $subject = getSubjectDetails($conn, $subject_id);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Get teacher ID from user ID
        $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $teacher = $result->fetch_assoc();
        $stmt->close();
        
        // Process each student's marks
        foreach ($post_data['marks'] as $student_id => $mark_data) {
            $student_id = intval($student_id);
            $theory_marks = !empty($mark_data['theory_marks']) ? floatval($mark_data['theory_marks']) : null;
            $practical_marks = !empty($mark_data['practical_marks']) ? floatval($mark_data['practical_marks']) : null;
            
            // Skip if both marks are empty
            if ($theory_marks === null && $practical_marks === null) {
                continue;
            }
            
            // Calculate grades
            $theory_grade = calculateGrade($theory_marks, $subject['theory_marks']);
            $practical_grade = calculateGrade($practical_marks, $subject['practical_marks']);
            
            // Calculate total marks
            $total_marks = ($theory_marks ?? 0) + ($practical_marks ?? 0);
            
            // Calculate percentage
            $total_possible = $subject['theory_marks'] + $subject['practical_marks'];
            $percentage = ($total_marks / $total_possible) * 100;
            
            // Calculate final grade
            $final_grade = calculateGrade($total_marks, $total_possible);
            
            // Calculate grade point
            $grade_point = calculateGradePoint($final_grade);
            
            // Get remarks
            $remarks = getRemarks($final_grade);
            
            // Determine pass/fail status
            $status = 'pass';
            if (($theory_marks !== null && $theory_marks < $subject['theory_pass_marks']) || 
                ($practical_marks !== null && $practical_marks < $subject['practical_pass_marks']) || 
                $final_grade == 'E') {
                $status = 'fail';
            }
            
            // Check if result already exists
            $stmt = $conn->prepare("SELECT result_id FROM results WHERE exam_id = ? AND subject_id = ? AND student_id = ?");
            $stmt->bind_param("iii", $exam_id, $subject_id, $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->num_rows > 0;
            $stmt->close();
            
            if ($exists) {
                // Update existing result
                $stmt = $conn->prepare("UPDATE results SET 
                                       theory_marks = ?, theory_grade = ?,
                                       practical_marks = ?, practical_grade = ?,
                                       total_marks = ?, percentage = ?,
                                       final_grade = ?, grade_point = ?,
                                       remarks = ?, status = ?,
                                       updated_by = ?, updated_at = NOW()
                                       WHERE exam_id = ? AND subject_id = ? AND student_id = ?");
                $stmt->bind_param(
                    "dsdsddsdssiiiii",
                    $theory_marks, $theory_grade,
                    $practical_marks, $practical_grade,
                    $total_marks, $percentage,
                    $final_grade, $grade_point,
                    $remarks, $status,
                    $teacher['teacher_id'], $exam_id, $subject_id, $student_id
                );
            } else {
                // Insert new result
                $stmt = $conn->prepare("INSERT INTO results (
                                       exam_id, subject_id, student_id,
                                       theory_marks, theory_grade,
                                       practical_marks, practical_grade,
                                       total_marks, percentage,
                                       final_grade, grade_point,
                                       remarks, status,
                                       created_by, updated_by,
                                       created_at, updated_at
                                       ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param(
                    "iiidsdsddsssiiii",
                    $exam_id, $subject_id, $student_id,
                    $theory_marks, $theory_grade,
                    $practical_marks, $practical_grade,
                    $total_marks, $percentage,
                    $final_grade, $grade_point,
                    $remarks, $status,
                    $teacher['teacher_id'], $teacher['teacher_id']
                );
            }
            
            $stmt->execute();
            $stmt->close();
        }
        
        // Log activity
        $stmt = $conn->prepare("INSERT INTO teacher_activities (
                               teacher_id, activity_type, description, timestamp
                               ) VALUES (?, 'marks_update', ?, NOW())");
        $description = "Updated marks for subject ID: $subject_id, exam ID: $exam_id";
        $stmt->bind_param("is", $teacher['teacher_id'], $description);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        return ['success' => true, 'message' => 'Marks saved successfully.'];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get subjects taught by a teacher in a class
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher user ID
 * @param int $class_id Class ID
 * @return array Subjects
 */
function getTeacherSubjectsInClass($conn, $teacher_id, $class_id) {
    $stmt = $conn->prepare("SELECT ts.*, s.subject_name, s.subject_code 
                           FROM teachersubjects ts 
                           JOIN subjects s ON ts.subject_id = s.subject_id 
                           JOIN teachers t ON ts.teacher_id = t.teacher_id 
                           WHERE t.user_id = ? AND ts.class_id = ?
                           ORDER BY s.subject_name ASC");
    $stmt->bind_param("ii", $teacher_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
    
    return $subjects;
}

/**
 * Get class performance statistics
 * 
 * @param mysqli $conn Database connection
 * @param int $class_id Class ID
 * @param int $subject_id Subject ID
 * @param int $exam_id Exam ID
 * @return array Performance statistics
 */
function getClassPerformanceStats($conn, $class_id, $subject_id, $exam_id) {
    // Get total students in class
    $stmt = $conn->prepare("SELECT COUNT(*) as total_students FROM students WHERE class_id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_students = $row['total_students'];
    $stmt->close();
    
    // Get pass count
    $stmt = $conn->prepare("SELECT COUNT(*) as pass_count 
                           FROM results r 
                           JOIN students s ON r.student_id = s.student_id 
                           WHERE r.exam_id = ? AND r.subject_id = ? AND s.class_id = ? AND r.status = 'pass'");
    $stmt->bind_param("iii", $exam_id, $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $pass_count = $row['pass_count'];
    $stmt->close();
    
    // Get average marks
    $stmt = $conn->prepare("SELECT AVG(r.total_marks) as average_marks 
                           FROM results r 
                           JOIN students s ON r.student_id = s.student_id 
                           WHERE r.exam_id = ? AND r.subject_id = ? AND s.class_id = ?");
    $stmt->bind_param("iii", $exam_id, $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $average_marks = $row['average_marks'] ?? 0;
    $stmt->close();
    
    // Get highest marks and student
    $stmt = $conn->prepare("SELECT r.total_marks as highest_marks, u.full_name as highest_student_name 
                           FROM results r 
                           JOIN students s ON r.student_id = s.student_id 
                           JOIN users u ON s.user_id = u.user_id 
                           WHERE r.exam_id = ? AND r.subject_id = ? AND s.class_id = ? 
                           ORDER BY r.total_marks DESC LIMIT 1");
    $stmt->bind_param("iii", $exam_id, $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $highest_marks = $row['highest_marks'] ?? 0;
    $highest_student_name = $row['highest_student_name'] ?? 'N/A';
    $stmt->close();
    
    // Get lowest marks and student
    $stmt = $conn->prepare("SELECT r.total_marks as lowest_marks, u.full_name as lowest_student_name 
                           FROM results r 
                           JOIN students s ON r.student_id = s.student_id 
                           JOIN users u ON s.user_id = u.user_id 
                           WHERE r.exam_id = ? AND r.subject_id = ? AND s.class_id = ? 
                           ORDER BY r.total_marks ASC LIMIT 1");
    $stmt->bind_param("iii", $exam_id, $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $lowest_marks = $row['lowest_marks'] ?? 0;
    $lowest_student_name = $row['lowest_student_name'] ?? 'N/A';
    $stmt->close();
    
    // Calculate pass percentage
    $pass_percentage = $total_students > 0 ? ($pass_count / $total_students) * 100 : 0;
    
    return [
        'total_students' => $total_students,
        'pass_count' => $pass_count,
        'pass_percentage' => $pass_percentage,
        'average_marks' => $average_marks,
        'highest_marks' => $highest_marks,
        'highest_student_name' => $highest_student_name,
        'lowest_marks' => $lowest_marks,
        'lowest_student_name' => $lowest_student_name
    ];
}

/**
 * Get student marks for performance view
 * 
 * @param mysqli $conn Database connection
 * @param int $class_id Class ID
 * @param int $subject_id Subject ID
 * @param int $exam_id Exam ID
 * @return array Student marks
 */
function getStudentMarksForPerformance($conn, $class_id, $subject_id, $exam_id) {
    $stmt = $conn->prepare("SELECT r.*, s.roll_number, u.full_name as student_name 
                           FROM results r 
                           JOIN students s ON r.student_id = s.student_id 
                           JOIN users u ON s.user_id = u.user_id 
                           WHERE r.exam_id = ? AND r.subject_id = ? AND s.class_id = ? 
                           ORDER BY s.roll_number ASC");
    $stmt->bind_param("iii", $exam_id, $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $marks = [];
    while ($row = $result->fetch_assoc()) {
        $marks[] = $row;
    }
    $stmt->close();
    
    return $marks;
}

/**
 * Get grade distribution for a class, subject and exam
 * 
 * @param mysqli $conn Database connection
 * @param int $class_id Class ID
 * @param int $subject_id Subject ID
 * @param int $exam_id Exam ID
 * @return array Grade distribution
 */
function getGradeDistribution($conn, $class_id, $subject_id, $exam_id) {
    $grades = ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'E'];
    $distribution = array_fill_keys($grades, 0);
    
    $stmt = $conn->prepare("SELECT final_grade, COUNT(*) as count 
                           FROM results r 
                           JOIN students s ON r.student_id = s.student_id 
                           WHERE r.exam_id = ? AND r.subject_id = ? AND s.class_id = ? 
                           GROUP BY final_grade");
    $stmt->bind_param("iii", $exam_id, $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (isset($distribution[$row['final_grade']])) {
            $distribution[$row['final_grade']] = $row['count'];
        }
    }
    $stmt->close();
    
    return $distribution;
}

/**
 * Log teacher activity
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher user ID
 * @param string $activity_type Activity type
 * @param string $description Activity description
 * @return bool True if successful, false otherwise
 */
function logTeacherActivity($conn, $teacher_id, $activity_type, $description) {
    // Get teacher ID from user ID
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    $stmt->close();
    
    if (!$teacher) {
        return false;
    }
    
    $stmt = $conn->prepare("INSERT INTO teacher_activities (
                           teacher_id, activity_type, description, timestamp
                           ) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $teacher['teacher_id'], $activity_type, $description);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

/**
 * Get activity icon based on activity type
 * 
 * @param string $activity_type Activity type
 * @return string Icon class
 */
function getActivityIcon($activity_type) {
    switch ($activity_type) {
        case 'login':
            return 'bi-box-arrow-in-right text-success';
        case 'logout':
            return 'bi-box-arrow-right text-danger';
        case 'marks_update':
            return 'bi-pencil-square text-primary';
        case 'view_performance':
            return 'bi-bar-chart text-info';
        case 'print_report':
            return 'bi-printer text-secondary';
        default:
            return 'bi-activity text-warning';
    }
}

/**
 * Get time ago string from timestamp
 * 
 * @param string $timestamp Timestamp
 * @return string Time ago string
 */
function getTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>
