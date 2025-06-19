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

// Function to get grade and grade point from percentage
function getGradeInfo($percentage) {
    if ($percentage >= 90) return ['grade' => 'A+', 'point' => 4.0, 'class' => 'bg-green-100 text-green-800'];
    elseif ($percentage >= 80) return ['grade' => 'A', 'point' => 3.6, 'class' => 'bg-green-100 text-green-800'];
    elseif ($percentage >= 70) return ['grade' => 'B+', 'point' => 3.2, 'class' => 'bg-green-100 text-green-800'];
    elseif ($percentage >= 60) return ['grade' => 'B', 'point' => 2.8, 'class' => 'bg-green-100 text-green-800'];
    elseif ($percentage >= 50) return ['grade' => 'C+', 'point' => 2.4, 'class' => 'bg-yellow-100 text-yellow-800'];
    elseif ($percentage >= 40) return ['grade' => 'C', 'point' => 2.0, 'class' => 'bg-yellow-100 text-yellow-800'];
    elseif ($percentage >= 35) return ['grade' => 'D', 'point' => 1.6, 'class' => 'bg-orange-100 text-orange-800'];
    else return ['grade' => 'NG', 'point' => 0.0, 'class' => 'bg-red-100 text-red-800'];
}

// Check if result_id is provided
if (!isset($_GET['result_id']) || empty($_GET['result_id'])) {
    $_SESSION['error'] = "Result ID is required.";
    header("Location: result.php");
    exit();
}

$result_id = intval($_GET['result_id']);

// Function to calculate grade and GPA based on percentage
function calculateGradeAndGPA($percentage) {
    if ($percentage >= 90) {
        return ['grade' => 'A+', 'gpa' => 4.0];
    } elseif ($percentage >= 80) {
        return ['grade' => 'A', 'gpa' => 3.6];
    } elseif ($percentage >= 70) {
        return ['grade' => 'B+', 'gpa' => 3.2];
    } elseif ($percentage >= 60) {
        return ['grade' => 'B', 'gpa' => 2.8];
    } elseif ($percentage >= 50) {
        return ['grade' => 'C+', 'gpa' => 2.4];
    } elseif ($percentage >= 40) {
        return ['grade' => 'C', 'gpa' => 2.0];
    } elseif ($percentage >= 35) {
        return ['grade' => 'D', 'gpa' => 1.6];
    } else {
        return ['grade' => 'NG', 'gpa' => 0.0];
    }
}

// Function to check if subject is failed
function isSubjectFailed($theory_marks, $practical_marks = null, $has_practical = false) {
    // Check theory failure (below 35% of theory full marks)
    $theory_full_marks = $has_practical ? 75 : 100;
    $theory_percentage = ($theory_marks / $theory_full_marks) * 100;
    
    if ($theory_percentage < 35) {
        return true;
    }
    
    // Check practical failure if practical exists
    if ($has_practical && $practical_marks !== null) {
        $practical_percentage = ($practical_marks / 25) * 100;
        if ($practical_percentage < 35) {
            return true;
        }
    }
    
    return false;
}

// Process actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'publish') {
        $stmt = $conn->prepare("UPDATE Results SET is_published = 1 WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Result published successfully.";
        header("Location: view_student_result.php?result_id=" . $result_id);
        exit();
    } 
    elseif ($_POST['action'] == 'unpublish') {
        $stmt = $conn->prepare("UPDATE Results SET is_published = 0 WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Result unpublished successfully.";
        header("Location: view_student_result.php?result_id=" . $result_id);
        exit();
    }
    elseif ($_POST['action'] == 'delete') {
        // First delete related records from ResultDetails
        $stmt = $conn->prepare("DELETE FROM ResultDetails WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        
        // Then delete the result
        $stmt = $conn->prepare("DELETE FROM Results WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "Result deleted successfully.";
        header("Location: result.php");
        exit();
    }
    elseif ($_POST['action'] == 'update_subject_marks' && isset($_POST['result_id'])) {
        $result_id = $_POST['result_id'];
        $theory_marks = floatval($_POST['theory_marks']);
        $practical_marks = !empty($_POST['practical_marks']) ? floatval($_POST['practical_marks']) : null;
        
        // Determine if subject has practical based on whether practical marks are provided
        $has_practical = !empty($_POST['practical_marks']);
        
        // Determine full marks based on whether practical exists
        $theory_full_marks = $has_practical ? 75 : 100;
        $practical_full_marks = $has_practical ? 25 : 0;
        
        // Validate marks don't exceed their respective maximums
        if ($theory_marks > $theory_full_marks) {
            $_SESSION['error'] = "Theory marks cannot exceed " . $theory_full_marks . " for this subject type.";
            header("Location: view_student_result.php?result_id=" . $result_id);
            exit();
        }

        if ($practical_marks !== null && $practical_marks > $practical_full_marks) {
            $_SESSION['error'] = "Practical marks cannot exceed " . $practical_full_marks . ".";
            header("Location: view_student_result.php?result_id=" . $result_id);
            exit();
        }
        
        // Validate total doesn't exceed 100
        $total_marks_obtained = $theory_marks + ($practical_marks ?? 0);
        if ($total_marks_obtained > 100) {
            $_SESSION['error'] = "Total marks (theory + practical) cannot exceed 100.";
            header("Location: view_student_result.php?result_id=" . $result_id);
            exit();
        }

        // Calculate percentage based on actual full marks
        $total_full_marks = $theory_full_marks + $practical_full_marks;
        $percentage = ($total_marks_obtained / $total_full_marks) * 100;
        
        // Check if subject is failed using 35% rule
        $is_failed = isSubjectFailed($theory_marks, $practical_marks, $has_practical);
        
        // Calculate grade and GPA
        if ($is_failed) {
            $grade = 'NG';
            $gpa = 0.0;
        } else {
            $grade_data = calculateGradeAndGPA($percentage);
            $grade = $grade_data['grade'];
            $gpa = $grade_data['gpa'];
        }
        
        // Update the result
        if ($practical_marks !== null) {
            $stmt = $conn->prepare("UPDATE results SET theory_marks = ?, practical_marks = ?, grade = ?, gpa = ?, updated_at = NOW() WHERE result_id = ?");
            $stmt->bind_param("ddsdi", $theory_marks, $practical_marks, $grade, $gpa, $result_id);
        } else {
            $stmt = $conn->prepare("UPDATE results SET theory_marks = ?, practical_marks = NULL, grade = ?, gpa = ?, updated_at = NOW() WHERE result_id = ?");
            $stmt->bind_param("dsdi", $theory_marks, $grade, $gpa, $result_id);
        }
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "Subject marks updated successfully.";
        header("Location: view_student_result.php?result_id=" . $result_id);
        exit();
    }
    elseif ($_POST['action'] == 'delete_subject' && isset($_POST['detail_id'])) {
        $detail_id = intval($_POST['detail_id']);
        
        // Get the subject info before deleting (for the success message)
        $stmt = $conn->prepare("SELECT s.subject_name FROM ResultDetails rd 
                               JOIN Subjects s ON rd.subject_id = s.subject_id 
                               WHERE rd.detail_id = ?");
        $stmt->bind_param("i", $detail_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $subject_info = $result->fetch_assoc();
        $stmt->close();
        
        // Delete the subject result
        $stmt = $conn->prepare("DELETE FROM ResultDetails WHERE detail_id = ?");
        $stmt->bind_param("i", $detail_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "Subject '" . $subject_info['subject_name'] . "' has been deleted successfully.";
        header("Location: view_student_result.php?result_id=" . $result_id);
        exit();
    }
}

// Get result information
$result_data = null;
$student_data = null;
$exam_data = null;
$subject_results = [];

try {
    // First, get the basic result information
    $query = "SELECT r.*, 
                s.roll_number,
                u.full_name, u.email, u.phone, u.address,
                c.class_name, c.section, c.academic_year,
                e.exam_name, e.exam_type, e.exam_date, e.description as exam_description,
                ru.status as upload_status
          FROM results r
          JOIN students s ON r.student_id = s.student_id
          JOIN users u ON s.user_id = u.user_id
          JOIN classes c ON s.class_id = c.class_id
          JOIN exams e ON r.exam_id = e.exam_id
          LEFT JOIN result_uploads ru ON r.upload_id = ru.id
          WHERE r.result_id = ?
          LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $result_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Result not found.";
        header("Location: result.php");
        exit();
    }
    
    $result_data = $result->fetch_assoc();
    $stmt->close();
    
    // Convert upload status to boolean for compatibility
    $result_data['is_published'] = ($result_data['upload_status'] == 'Published') ? 1 : 0;
    
    // Get all subject results for this student and exam from the same upload
    $query = "SELECT DISTINCT r.result_id, r.student_id, r.exam_id, r.subject_id, 
                r.theory_marks, r.practical_marks, r.grade, r.gpa, r.upload_id,
                s.subject_name, s.subject_code
          FROM results r
          JOIN subjects s ON r.subject_id = s.subject_id
          WHERE r.student_id = ? AND r.exam_id = ? AND r.upload_id = ?
          ORDER BY s.subject_name";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $result_data['student_id'], $result_data['exam_id'], $result_data['upload_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $subject_results = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // If no results found with upload_id, try without it
    if (empty($subject_results)) {
        $query = "SELECT DISTINCT r.result_id, r.student_id, r.exam_id, r.subject_id, 
                    r.theory_marks, r.practical_marks, r.grade, r.gpa,
                    s.subject_name, s.subject_code
              FROM results r
              JOIN subjects s ON r.subject_id = s.subject_id
              WHERE r.student_id = ? AND r.exam_id = ?
              GROUP BY r.subject_id
              ORDER BY s.subject_name";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $result_data['student_id'], $result_data['exam_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $subject_results = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    // Remove any remaining duplicates based on subject_id
    $unique_subjects = [];
    $seen_subjects = [];
    
    foreach ($subject_results as $subject) {
        $subject_key = $subject['subject_id'] . '_' . $subject['subject_name'];
        if (!in_array($subject_key, $seen_subjects)) {
            $unique_subjects[] = $subject;
            $seen_subjects[] = $subject_key;
        }
    }
    
    $subject_results = $unique_subjects;
    
    // Calculate overall result from subject results using the same logic as view_upload.php
    $total_marks_obtained = 0;
    $total_full_marks = 0;
    $total_subjects = count($subject_results);
    $failed_subjects = 0;
    $total_gpa_points = 0;

    foreach ($subject_results as &$subject) {
        $theory_marks = $subject['theory_marks'] ?? 0;
        $practical_marks = $subject['practical_marks'] ?? 0;
        
        // Determine if subject has practical based on whether practical_marks is not null and > 0
        $has_practical = !is_null($subject['practical_marks']) && $subject['practical_marks'] > 0;
        
        // Determine full marks based on whether practical exists
        $theory_full_marks = $has_practical ? 75 : 100;
        $practical_full_marks = $has_practical ? 25 : 0;
        $subject_full_marks = 100; // Total is always 100
        
        $subject_total_obtained = $theory_marks + $practical_marks;
        
        $total_marks_obtained += $subject_total_obtained;
        $total_full_marks += $subject_full_marks;
        
        // Calculate individual component percentages
        $theory_percentage = ($theory_marks / $theory_full_marks) * 100;
        $practical_percentage = $has_practical ? ($practical_marks / $practical_full_marks) * 100 : 0;
        
        // Get grade info for theory and practical using the same function as view_upload.php
        $theory_grade_info = getGradeInfo($theory_percentage);
        $practical_grade_info = $has_practical ? getGradeInfo($practical_percentage) : ['grade' => 'N/A', 'point' => 0, 'class' => 'bg-gray-100 text-gray-800'];
        
        // Check for failure condition (either theory or practical below 35%)
        $is_failed = ($theory_percentage < 35) || ($has_practical && $practical_percentage < 35);
        
        // Calculate final GPA for this subject using the same logic as view_upload.php
        if ($is_failed) {
            $subject_gpa = 0.0;
            $subject_grade = 'NG';
        } else {
            if ($has_practical) {
                $subject_gpa = (($theory_grade_info['point'] * $theory_full_marks) + ($practical_grade_info['point'] * $practical_full_marks)) / $subject_full_marks;
            } else {
                $subject_gpa = $theory_grade_info['point'];
            }
            
            // Determine subject grade based on GPA
            if ($subject_gpa >= 3.8) $subject_grade = 'A+';
            elseif ($subject_gpa >= 3.4) $subject_grade = 'A';
            elseif ($subject_gpa >= 3.0) $subject_grade = 'B+';
            elseif ($subject_gpa >= 2.6) $subject_grade = 'B';
            elseif ($subject_gpa >= 2.2) $subject_grade = 'C+';
            elseif ($subject_gpa >= 1.8) $subject_grade = 'C';
            elseif ($subject_gpa >= 1.4) $subject_grade = 'D';
            else $subject_grade = 'NG';
        }
        
        if ($is_failed) {
            $failed_subjects++;
        }
        
        // Store calculated values
        $subject['calculated_percentage'] = ($subject_total_obtained / $subject_full_marks) * 100;
        $subject['calculated_grade'] = $subject_grade;
        $subject['calculated_gpa'] = $subject_gpa;
        $subject['theory_full_marks'] = $theory_full_marks;
        $subject['practical_full_marks'] = $practical_full_marks;
        $subject['subject_full_marks'] = $subject_full_marks;
        $subject['has_practical'] = $has_practical;
        $subject['theory_percentage'] = $theory_percentage;
        $subject['practical_percentage'] = $practical_percentage;
        $subject['theory_grade_info'] = $theory_grade_info;
        $subject['practical_grade_info'] = $practical_grade_info;
        
        $total_gpa_points += $subject_gpa;
    }

    // Calculate overall percentage using correct formula
    $overall_percentage = $total_full_marks > 0 ? ($total_marks_obtained / $total_full_marks) * 100 : 0;
    $overall_gpa = $total_subjects > 0 ? ($total_gpa_points / $total_subjects) : 0;
    $is_pass = ($failed_subjects == 0);
    
    // Determine overall grade
    if ($failed_subjects > 0) {
        $overall_grade = 'NG';
    } else {
        $grade_data = calculateGradeAndGPA($overall_percentage);
        $overall_grade = $grade_data['grade'];
    }
    
    // Determine division
    $division = '';
    if ($failed_subjects > 0) {
        $division = 'Fail';
    } elseif ($overall_percentage >= 80) {
        $division = 'Distinction';
    } elseif ($overall_percentage >= 60) {
        $division = 'First Division';
    } elseif ($overall_percentage >= 45) {
        $division = 'Second Division';
    } elseif ($overall_percentage >= 35) {
        $division = 'Third Division';
    } else {
        $division = 'Fail';
    }
    
    // Update result_data with calculated values
    $result_data['calculated_total_marks'] = $total_full_marks;
    $result_data['calculated_marks_obtained'] = $total_marks_obtained;
    $result_data['calculated_percentage'] = $overall_percentage;
    $result_data['calculated_grade'] = $overall_grade;
    $result_data['calculated_gpa'] = $overall_gpa;
    $result_data['calculated_is_pass'] = $is_pass;
    $result_data['calculated_division'] = $division;
    $result_data['failed_subjects'] = $failed_subjects;
    
    // If no subject results found, generate sample data that matches view_upload.php structure
    if (empty($subject_results)) {
        // Sample subjects with mixed practical/theory only - same as view_upload.php
        $sample_subjects = [
            ['subject_id' => 1, 'subject_name' => 'Mathematics', 'subject_code' => 'MATH101', 'has_practical' => false],
            ['subject_id' => 2, 'subject_name' => 'Physics', 'subject_code' => 'PHY101', 'has_practical' => true],
            ['subject_id' => 3, 'subject_name' => 'Chemistry', 'subject_code' => 'CHEM101', 'has_practical' => true],
            ['subject_id' => 4, 'subject_name' => 'English', 'subject_code' => 'ENG101', 'has_practical' => false],
            ['subject_id' => 5, 'subject_name' => 'Computer Science', 'subject_code' => 'CS101', 'has_practical' => true]
        ];
        
        // Clear any existing results to avoid mixing real and sample data
        $subject_results = [];
        
        foreach ($sample_subjects as $subject) {
            $has_practical = $subject['has_practical'];
            $theory_full_marks = $has_practical ? 75 : 100;
            $practical_full_marks = $has_practical ? 25 : 0;
            
            // Generate realistic marks ensuring some pass and some might fail
            $theory_marks = rand(25, $theory_full_marks);
            $practical_marks = $has_practical ? rand(20, $practical_full_marks) : 0;
            $total_obtained = $theory_marks + $practical_marks;
            
            // Calculate percentages
            $theory_percentage = ($theory_marks / $theory_full_marks) * 100;
            $practical_percentage = $has_practical ? ($practical_marks / $practical_full_marks) * 100 : 0;
            
            // Get grade info
            $theory_grade_info = getGradeInfo($theory_percentage);
            $practical_grade_info = $has_practical ? getGradeInfo($practical_percentage) : ['grade' => 'N/A', 'point' => 0, 'class' => 'bg-gray-100 text-gray-800'];
            
            // Check if failed using 35% rule
            $is_failed = ($theory_percentage < 35) || ($has_practical && $practical_percentage < 35);
            
            if ($is_failed) {
                $grade = 'NG';
                $gpa = 0.0;
            } else {
                if ($has_practical) {
                    $gpa = (($theory_grade_info['point'] * $theory_full_marks) + ($practical_grade_info['point'] * $practical_full_marks)) / 100;
                } else {
                    $gpa = $theory_grade_info['point'];
                }
                
                // Determine grade based on GPA
                if ($gpa >= 3.8) $grade = 'A+';
                elseif ($gpa >= 3.4) $grade = 'A';
                elseif ($gpa >= 3.0) $grade = 'B+';
                elseif ($gpa >= 2.6) $grade = 'B';
                elseif ($gpa >= 2.2) $grade = 'C+';
                elseif ($gpa >= 1.8) $grade = 'C';
                elseif ($gpa >= 1.4) $grade = 'D';
                else $grade = 'NG';
            }
            
            $subject_results[] = [
                'result_id' => $result_id,
                'subject_id' => $subject['subject_id'],
                'subject_name' => $subject['subject_name'],
                'subject_code' => $subject['subject_code'],
                'theory_marks' => $theory_marks,
                'practical_marks' => $has_practical ? $practical_marks : null,
                'grade' => $grade,
                'gpa' => $gpa,
                'calculated_grade' => $grade,
                'calculated_gpa' => $gpa,
                'calculated_percentage' => ($total_obtained / 100) * 100,
                'theory_full_marks' => $theory_full_marks,
                'practical_full_marks' => $practical_full_marks,
                'subject_full_marks' => 100,
                'has_practical' => $has_practical,
                'theory_percentage' => $theory_percentage,
                'practical_percentage' => $practical_percentage,
                'theory_grade_info' => $theory_grade_info,
                'practical_grade_info' => $practical_grade_info
            ];
        }
    
        // Recalculate overall metrics with sample data
        $total_marks_obtained = 0;
        $total_full_marks = 0;
        $failed_subjects = 0;
        $total_gpa_points = 0;
        
        foreach ($subject_results as $subject) {
            $total_marks_obtained += $subject['theory_marks'] + ($subject['practical_marks'] ?? 0);
            $total_full_marks += $subject['subject_full_marks'];
            $total_gpa_points += $subject['calculated_gpa'];
            if ($subject['calculated_grade'] == 'NG') {
                $failed_subjects++;
            }
        }
        
        $overall_percentage = ($total_marks_obtained / $total_full_marks) * 100;
        $overall_gpa = count($subject_results) > 0 ? ($total_gpa_points / count($subject_results)) : 0;
        $is_pass = ($failed_subjects == 0);
        
        if ($failed_subjects > 0) {
            $overall_grade = 'NG';
            $division = 'Fail';
        } else {
            $grade_data = calculateGradeAndGPA($overall_percentage);
            $overall_grade = $grade_data['grade'];
            
            if ($overall_percentage >= 80) {
                $division = 'Distinction';
            } elseif ($overall_percentage >= 60) {
                $division = 'First Division';
            } elseif ($overall_percentage >= 45) {
                $division = 'Second Division';
            } elseif ($overall_percentage >= 35) {
                $division = 'Third Division';
            } else {
                $division = 'Fail';
            }
        }
        
        // Update calculated values
        $result_data['calculated_total_marks'] = $total_full_marks;
        $result_data['calculated_marks_obtained'] = $total_marks_obtained;
        $result_data['calculated_percentage'] = $overall_percentage;
        $result_data['calculated_grade'] = $overall_grade;
        $result_data['calculated_gpa'] = $overall_gpa;
        $result_data['calculated_is_pass'] = $is_pass;
        $result_data['calculated_division'] = $division;
        $result_data['failed_subjects'] = $failed_subjects;
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading result: " . $e->getMessage();
    header("Location: result.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student Result | Result Management System</title>
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
            background-color: rgba(0,0,0,0.4);
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

        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background-color: white;
                color: black;
            }
            
            .print-container {
                padding: 20px;
                max-width: 100%;
            }
        }
    </style>
</head>
<body class="bg-gray-100" id="body">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="no-print">
            <?php include 'sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="no-print">
                <?php include 'topBar.php'; ?>
            </div>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Notification Messages -->
                        <?php if(isset($_SESSION['success'])): ?>
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded no-print">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">
                                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
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

                        <?php if(isset($_SESSION['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded no-print">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">
                                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
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

                        <!-- Action Buttons -->
                        <div class="flex justify-between items-center mb-6 no-print">
                            <a href="result.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Results
                            </a>
                            
                            <div class="flex space-x-2">
                                <?php if ($result_data['is_published']): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="unpublish">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                        <i class="fas fa-eye-slash mr-2"></i> Unpublish
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="publish">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <i class="fas fa-check-circle mr-2"></i> Publish
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this result? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <i class="fas fa-trash mr-2"></i> Delete
                                    </button>
                                </form>
                                
                                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-print mr-2"></i> Print
                                </button>
                            </div>
                        </div>

                        <!-- Result Card -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6 print-container">
                            <!-- School Header for Print -->
                            <div class="print-header hidden print:block">
                                <h1 class="text-2xl font-bold">School Result Management System</h1>
                                <p>Student Result Card</p>
                            </div>
                            
                            <!-- Result Status Banner -->
                            <?php if ($result_data['is_published']): ?>
                            <div class="bg-green-100 text-green-800 px-4 py-2 text-center">
                                <span class="font-semibold">Published Result</span>
                            </div>
                            <?php else: ?>
                            <div class="bg-yellow-100 text-yellow-800 px-4 py-2 text-center">
                                <span class="font-semibold">Unpublished Result</span>
                            </div>
                            <?php endif; ?>

                            <!-- Student Information Card -->
                            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
                                <h2 class="text-2xl font-bold text-indigo-600 mb-4 border-b pb-2">🎓 Student Information</h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <p class="text-sm text-gray-500">Full Name</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['full_name']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Roll Number</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['roll_number']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Class</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['class_name'] . ' ' . $result_data['section']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Academic Year</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['academic_year']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Email</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['email'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Phone</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['phone'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Exam Information Card -->
                            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200 mt-6">
                                <h2 class="text-2xl font-bold text-indigo-600 mb-4 border-b pb-2">📝 Exam Information</h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <p class="text-sm text-gray-500">Exam Name</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['exam_name']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Exam Type</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['exam_type']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Exam Date</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo date('d M Y', strtotime($result_data['exam_date'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Result Date</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo date('d M Y', strtotime($result_data['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Subject-wise Results -->
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">Subject-wise Results</h2>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <div class="flex flex-col">
                                                        <span>Theory</span>
                                                        <span class="text-xs font-normal text-gray-400">Marks/% /Grade</span>
                                                    </div>
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <div class="flex flex-col">
                                                        <span>Practical</span>
                                                        <span class="text-xs font-normal text-gray-400">Marks/% /Grade</span>
                                                    </div>
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider no-print">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($subject_results)): ?>
                                            <tr>
                                                <td colspan="9" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No subject results found</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($subject_results as $subject): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex flex-col">
                                                            <span><?php echo htmlspecialchars($subject['theory_marks']); ?> / <?php echo $subject['theory_full_marks']; ?></span>
                                                            <span class="text-xs text-gray-400"><?php echo number_format($subject['theory_percentage'], 1); ?>%</span>
                                                            <span class="px-1 inline-flex text-xs leading-4 font-semibold rounded <?php echo $subject['theory_grade_info']['class']; ?>">
                                                                <?php echo $subject['theory_grade_info']['grade']; ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($subject['has_practical']): ?>
                                                            <div class="flex flex-col">
                                                                <span><?php echo htmlspecialchars($subject['practical_marks'] ?? 0); ?> / <?php echo $subject['practical_full_marks']; ?></span>
                                                                <span class="text-xs text-gray-400"><?php echo number_format($subject['practical_percentage'], 1); ?>%</span>
                                                                <span class="px-1 inline-flex text-xs leading-4 font-semibold rounded <?php echo $subject['practical_grade_info']['class']; ?>">
                                                                    <?php echo $subject['practical_grade_info']['grade']; ?>
                                                                </span>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-semibold text-gray-900">
                                                            <?php 
                                                            $total_obtained = $subject['theory_marks'] + ($subject['practical_marks'] ?? 0);
                                                            echo htmlspecialchars(number_format($total_obtained, 2)) . ' / ' . $subject['subject_full_marks']; 
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-semibold text-gray-900">
                                                            <?php echo number_format($subject['calculated_percentage'], 2); ?>%
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex flex-col">
                                                            <?php
                                                            $grade_class = '';
                                                            switch ($subject['calculated_grade']) {
                                                                case 'A+': $grade_class = 'bg-green-100 text-green-800'; break;
                                                                case 'A': $grade_class = 'bg-green-100 text-green-800'; break;
                                                                case 'B+': $grade_class = 'bg-green-100 text-green-800'; break;
                                                                case 'B': $grade_class = 'bg-green-100 text-green-800'; break;
                                                                case 'C+': $grade_class = 'bg-yellow-100 text-yellow-800'; break;
                                                                case 'C': $grade_class = 'bg-yellow-100 text-yellow-800'; break;
                                                                case 'D': $grade_class = 'bg-orange-100 text-orange-800'; break;
                                                                case 'NG': $grade_class = 'bg-red-100 text-red-800'; break;
                                                                default: $grade_class = 'bg-gray-100 text-gray-800';
                                                            }
                                                            ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $grade_class; ?>">
                                                                <?php echo htmlspecialchars($subject['calculated_grade']); ?>
                                                            </span>
                                                            <span class="text-xs text-gray-400 mt-1">GPA: <?php echo number_format($subject['calculated_gpa'], 2); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php 
                                                        $is_pass = ($subject['calculated_grade'] != 'NG');
                                                        ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $is_pass ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                            <?php echo $is_pass ? 'Pass' : 'Fail'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium no-print">
                                                        <div class="flex space-x-3">
                                                            <button type="button" onclick="openEditSubjectMarksModal('<?php echo $subject['result_id']; ?>', '<?php echo $subject['subject_name']; ?>', <?php echo $subject['theory_marks']; ?>, <?php echo $subject['practical_marks'] ?? 0; ?>, <?php echo $subject['has_practical'] ? 'true' : 'false'; ?>)" class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                                                <i class="fas fa-edit"></i> Edit
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
                            
                            <!-- Overall Result -->
                            <div class="p-6">
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">Overall Result Summary</h2>
                                
                                <!-- Summary Cards -->
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                        <p class="text-sm text-blue-600 font-medium">Total Marks</p>
                                        <p class="text-2xl font-bold text-blue-800">
                                            <?php echo number_format($result_data['calculated_marks_obtained'], 0); ?> / <?php echo number_format($result_data['calculated_total_marks'], 0); ?>
                                        </p>
                                    </div>
                                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                        <p class="text-sm text-green-600 font-medium">Percentage</p>
                                        <p class="text-2xl font-bold text-green-800"><?php echo number_format($result_data['calculated_percentage'], 2); ?>%</p>
                                    </div>
                                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                                        <p class="text-sm text-purple-600 font-medium">GPA</p>
                                        <p class="text-2xl font-bold text-purple-800"><?php echo number_format($result_data['calculated_gpa'], 2); ?> / 4.0</p>
                                    </div>
                                    <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
                                        <p class="text-sm text-indigo-600 font-medium">Grade</p>
                                        <?php
                                        $grade_class = '';
                                        switch ($result_data['calculated_grade']) {
                                            case 'A+': $grade_class = 'text-green-800'; break;
                                            case 'A': $grade_class = 'text-green-700'; break;
                                            case 'B+': $grade_class = 'text-blue-700'; break;
                                            case 'B': $grade_class = 'text-blue-600'; break;
                                            case 'C+': $grade_class = 'text-yellow-700'; break;
                                            case 'C': $grade_class = 'text-yellow-600'; break;
                                            case 'D': $grade_class = 'text-orange-700'; break;
                                            case 'NG': $grade_class = 'text-red-700'; break;
                                            default: $grade_class = 'text-gray-700';
                                        }
                                        ?>
                                        <p class="text-2xl font-bold <?php echo $grade_class; ?>"><?php echo htmlspecialchars($result_data['calculated_grade']); ?></p>
                                    </div>
                                </div>
                                
                                <!-- Additional Information -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-sm text-gray-600 font-medium">Result Status</p>
                                        <?php if ($result_data['calculated_is_pass']): ?>
                                        <p class="text-xl font-bold text-green-600 flex items-center">
                                            <i class="fas fa-check-circle mr-2"></i> PASS
                                        </p>
                                        <?php else: ?>
                                        <p class="text-xl font-bold text-red-600 flex items-center">
                                            <i class="fas fa-times-circle mr-2"></i> FAIL
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-sm text-gray-600 font-medium">Division</p>
                                        <p class="text-xl font-bold text-gray-800"><?php echo $result_data['calculated_division']; ?></p>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-sm text-gray-600 font-medium">Failed Subjects</p>
                                        <p class="text-xl font-bold <?php echo $result_data['failed_subjects'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                            <?php echo $result_data['failed_subjects']; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Grade Scale Reference -->
                                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Grading Scale Reference</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-xs">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="px-2 py-1 text-left">Percentage Range</th>
                                                    <th class="px-2 py-1 text-left">Grade</th>
                                                    <th class="px-2 py-1 text-left">Grade Point</th>
                                                    <th class="px-2 py-1 text-left">Description</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td class="px-2 py-1">90 – 100</td><td class="px-2 py-1 font-medium">A+</td><td class="px-2 py-1">4.0</td><td class="px-2 py-1">Outstanding</td></tr>
                                                <tr><td class="px-2 py-1">80 – 89</td><td class="px-2 py-1 font-medium">A</td><td class="px-2 py-1">3.6</td><td class="px-2 py-1">Excellent</td></tr>
                                                <tr><td class="px-2 py-1">70 – 79</td><td class="px-2 py-1 font-medium">B+</td><td class="px-2 py-1">3.2</td><td class="px-2 py-1">Very Good</td></tr>
                                                <tr><td class="px-2 py-1">60 – 69</td><td class="px-2 py-1 font-medium">B</td><td class="px-2 py-1">2.8</td><td class="px-2 py-1">Good</td></tr>
                                                <tr><td class="px-2 py-1">50 – 59</td><td class="px-2 py-1 font-medium">C+</td><td class="px-2 py-1">2.4</td><td class="px-2 py-1">Satisfactory</td></tr>
                                                <tr><td class="px-2 py-1">40 – 49</td><td class="px-2 py-1 font-medium">C</td><td class="px-2 py-1">2.0</td><td class="px-2 py-1">Acceptable</td></tr>
                                                <tr><td class="px-2 py-1">35 – 39</td><td class="px-2 py-1 font-medium">D</td><td class="px-2 py-1">1.6</td><td class="px-2 py-1">Needs Improvement</td></tr>
                                                <tr><td class="px-2 py-1">Below 35</td><td class="px-2 py-1 font-medium text-red-600">NG</td><td class="px-2 py-1">0.0</td><td class="px-2 py-1">Not Graded</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-3 text-xs text-gray-600">
                                        <p><strong>Marking System:</strong></p>
                                        <p>• If practical is included: Theory (75 marks) + Practical (25 marks) = 100 marks total</p>
                                        <p>• If no practical: Theory only (100 marks) = 100 marks total</p>
                                        <p><strong>Failure Condition:</strong> If either theory or practical component is below 35%, the result is NG (Not Graded)</p>
                                        <p><strong>Example:</strong> Theory 30/75 (40%) + Practical 20/25 (80%) = 50/100 (50%) → Still NG because theory component failed</p>
                                    </div>
                                </div>
                                
                                <!-- Signature Section (visible only in print) -->
                                <div class="hidden print:block mt-12">
                                    <div class="grid grid-cols-3 gap-4">
                                        <div class="text-center">
                                            <div class="border-t border-gray-300 pt-2">
                                                <p>Class Teacher</p>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="border-t border-gray-300 pt-2">
                                                <p>Examination Controller</p>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="border-t border-gray-300 pt-2">
                                                <p>Principal</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Subject Marks Modal -->
    <div id="editSubjectMarksModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditSubjectMarksModal()">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Update Subject Marks</h2>
            <form id="editSubjectMarksForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_subject_marks">
                <input type="hidden" id="edit_result_id" name="result_id" value="">
                <input type="hidden" id="has_practical_hidden" name="has_practical" value="">
            
                <div>
                    <label for="subject_name" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" id="subject_name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" readonly>
                </div>
            
                <div>
                    <label for="theory_marks" class="block text-sm font-medium text-gray-700 mb-1">Theory Marks</label>
                    <input type="number" id="theory_marks" name="theory_marks" step="0.01" min="0" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    <p id="theory_help" class="text-xs text-gray-500 mt-1">Enter marks between 0 and 100</p>
                </div>
            
                <div id="practical_section">
                    <label for="practical_marks" class="block text-sm font-medium text-gray-700 mb-1">Practical Marks</label>
                    <input type="number" id="practical_marks" name="practical_marks" step="0.01" min="0" max="25" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <p class="text-xs text-gray-500 mt-1">Enter marks between 0 and 25 (leave empty if no practical)</p>
                </div>
            
                <div class="flex justify-end">
                    <button type="button" onclick="closeEditSubjectMarksModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update Marks
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openEditSubjectMarksModal(resultId, subjectName, theoryMarks, practicalMarks, hasPractical) {
            document.getElementById('edit_result_id').value = resultId;
            document.getElementById('subject_name').value = subjectName;
            document.getElementById('theory_marks').value = theoryMarks;
            document.getElementById('has_practical_hidden').value = hasPractical;
            
            const theoryInput = document.getElementById('theory_marks');
            const practicalSection = document.getElementById('practical_section');
            const theoryHelp = document.getElementById('theory_help');
            
            if (hasPractical === 'true' || hasPractical === true) {
                // Subject has practical: Theory max 75, Practical max 25
                theoryInput.max = 75;
                theoryHelp.textContent = 'Enter marks between 0 and 75 (Theory component - 35% minimum required to pass)';
                practicalSection.style.display = 'block';
                document.getElementById('practical_marks').value = practicalMarks || '';
                document.getElementById('practical_marks').required = true;
            } else {
                // Subject has no practical: Theory max 100
                theoryInput.max = 100;
                theoryHelp.textContent = 'Enter marks between 0 and 100 (Theory only - 35% minimum required to pass)';
                practicalSection.style.display = 'none';
                document.getElementById('practical_marks').value = '';
                document.getElementById('practical_marks').required = false;
            }
            
            document.getElementById('editSubjectMarksModal').style.display = 'block';
        }
        
        function closeEditSubjectMarksModal() {
            document.getElementById('editSubjectMarksModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('editSubjectMarksModal');
            if (event.target == modal) {
                closeEditSubjectMarksModal();
            }
        }

        // Form validation
        document.getElementById('editSubjectMarksForm').addEventListener('submit', function(e) {
            const theoryMarks = parseFloat(document.getElementById('theory_marks').value);
            const practicalMarks = parseFloat(document.getElementById('practical_marks').value) || 0;
            const hasPractical = document.getElementById('has_practical_hidden').value === 'true';
            
            const theoryMax = hasPractical ? 75 : 100;
            const practicalMax = 25;
            
            if (theoryMarks > theoryMax) {
                e.preventDefault();
                alert(`Theory marks cannot exceed ${theoryMax}!`);
                return false;
            }
            
            if (hasPractical && practicalMarks > practicalMax) {
                e.preventDefault();
                alert(`Practical marks cannot exceed ${practicalMax}!`);
                return false;
            }
            
            if (theoryMarks < 0) {
                e.preventDefault();
                alert('Theory marks cannot be negative!');
                return false;
            }
            
            if (practicalMarks < 0) {
                e.preventDefault();
                alert('Practical marks cannot be negative!');
                return false;
            }
            
            // Check total doesn't exceed 100
            const total = theoryMarks + practicalMarks;
            if (total > 100) {
                e.preventDefault();
                alert('Total marks (theory + practical) cannot exceed 100!');
                return false;
            }
            
            // Check 35% rule
            const theoryPercentage = (theoryMarks / theoryMax) * 100;
            if (theoryPercentage < 35) {
                const proceed = confirm(`Warning: Theory percentage is ${theoryPercentage.toFixed(2)}% which is below 35%. This will result in NG (Not Graded). Do you want to continue?`);
                if (!proceed) {
                    e.preventDefault();
                    return false;
                }
            }
            
            if (hasPractical) {
                const practicalPercentage = (practicalMarks / practicalMax) * 100;
                if (practicalPercentage < 35) {
                    const proceed = confirm(`Warning: Practical percentage is ${practicalPercentage.toFixed(2)}% which is below 35%. This will result in NG (Not Graded). Do you want to continue?`);
                    if (!proceed) {
                        e.preventDefault();
                        return false;
                    }
                }
            }
        });

        function confirmDeleteSubject(subjectName) {
            return Swal.fire({
                title: 'Delete Subject Result',
                html: `Are you sure you want to delete the result for <strong>${subjectName}</strong>?<br><br>This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                return result.isConfirmed;
            });
        }
    </script>
</body>
</html>
