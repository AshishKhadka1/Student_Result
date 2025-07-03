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

// Exact GPA Calculation Functions - SAME as view_upload.php
function calculateExactGPA($percentage)
{
    $percentage = round($percentage, 2);

    if ($percentage >= 91) {
        // A+ grade: 3.6 - 4.0 range
        $gpa = 3.6 + (($percentage - 91) / 9) * (4.0 - 3.6);
        return ['grade' => 'A+', 'gpa' => round($gpa, 2), 'class' => 'bg-green-100 text-green-800'];
    } elseif ($percentage >= 81) {
        // A grade: 3.2 - 3.6 range
        $gpa = 3.2 + (($percentage - 81) / 9) * (3.6 - 3.2);
        return ['grade' => 'A', 'gpa' => round($gpa, 2), 'class' => 'bg-green-100 text-green-800'];
    } elseif ($percentage >= 71) {
        // B+ grade: 2.8 - 3.2 range
        $gpa = 2.8 + (($percentage - 71) / 9) * (3.2 - 2.8);
        return ['grade' => 'B+', 'gpa' => round($gpa, 2), 'class' => 'bg-green-100 text-green-800'];
    } elseif ($percentage >= 61) {
        // B grade: 2.6 - 2.8 range
        $gpa = 2.6 + (($percentage - 61) / 9) * (2.8 - 2.6);
        return ['grade' => 'B', 'gpa' => round($gpa, 2), 'class' => 'bg-green-100 text-green-800'];
    } elseif ($percentage >= 51) {
        // C+ grade: 2.2 - 2.6 range
        $gpa = 2.2 + (($percentage - 51) / 9) * (2.6 - 2.2);
        return ['grade' => 'C+', 'gpa' => round($gpa, 2), 'class' => 'bg-yellow-100 text-yellow-800'];
    } elseif ($percentage >= 41) {
        // C grade: 1.6 - 2.2 range
        $gpa = 1.6 + (($percentage - 41) / 9) * (2.2 - 1.6);
        return ['grade' => 'C', 'gpa' => round($gpa, 2), 'class' => 'bg-yellow-100 text-yellow-800'];
    } elseif ($percentage >= 35) {
        // D+ grade: 1.6 (fixed value)
        return ['grade' => 'D+', 'gpa' => 1.6, 'class' => 'bg-orange-100 text-orange-800'];
    } else {
        // NG grade: 0.0
        return ['grade' => 'NG', 'gpa' => 0.0, 'class' => 'bg-red-100 text-red-800'];
    }
}

function calculateTheoryOnlyGPA($theoryMarks, $theoryFullMarks = 100)
{
    // Validate marks don't exceed full marks
    if ($theoryMarks > $theoryFullMarks) {
        return ['error' => 'Theory marks cannot exceed full marks'];
    }

    $percentage = ($theoryMarks / $theoryFullMarks) * 100;
    $result = calculateExactGPA($percentage);

    return [
        'percentage' => round($percentage, 2),
        'grade' => $result['grade'],
        'gpa' => $result['gpa'],
        'class' => $result['class']
    ];
}

function calculateTheoryPracticalGPA($theoryMarks, $practicalMarks, $theoryFullMarks = 75, $practicalFullMarks = 25)
{
    // Validate marks don't exceed full marks
    if ($theoryMarks > $theoryFullMarks || $practicalMarks > $practicalFullMarks) {
        return ['error' => 'Marks cannot exceed respective full marks'];
    }

    $theoryPercentage = ($theoryMarks / $theoryFullMarks) * 100;
    $practicalPercentage = ($practicalMarks / $practicalFullMarks) * 100;

    $theoryResult = calculateExactGPA($theoryPercentage);
    $practicalResult = calculateExactGPA($practicalPercentage);

    // Calculate weighted final GPA
    $finalGPA = ($theoryResult['gpa'] * 75 + $practicalResult['gpa'] * 25) / 100;

    return [
        'theory' => [
            'percentage' => round($theoryPercentage, 2),
            'grade' => $theoryResult['grade'],
            'gpa' => $theoryResult['gpa'],
            'class' => $theoryResult['class']
        ],
        'practical' => [
            'percentage' => round($practicalPercentage, 2),
            'grade' => $practicalResult['grade'],
            'gpa' => $practicalResult['gpa'],
            'class' => $practicalResult['class']
        ],
        'final_gpa' => round($finalGPA, 2)
    ];
}

// Function to get grade and grade point from percentage (kept for backward compatibility)
function getGradeInfo($percentage)
{
    $result = calculateExactGPA($percentage);
    return ['grade' => $result['grade'], 'point' => $result['gpa'], 'class' => $result['class']];
}

// Function to check if subject is failed - Updated to match view_upload.php (33% minimum)
function isSubjectFailed($theory_marks, $practical_marks = null, $has_practical = false)
{
    // Check theory failure (below 33% of theory full marks)
    $theory_full_marks = $has_practical ? 75 : 100;
    $theory_percentage = ($theory_marks / $theory_full_marks) * 100;

    if ($theory_percentage < 33) {
        return true;
    }

    // Check practical failure if practical exists (below 33%)
    if ($has_practical && $practical_marks !== null) {
        $practical_percentage = ($practical_marks / 25) * 100;
        if ($practical_percentage < 33) {
            return true;
        }
    }

    return false;
}

// Check if result_id is provided
if (!isset($_GET['result_id']) || empty($_GET['result_id'])) {
    $_SESSION['error'] = "Result ID is required.";
    header("Location: result.php");
    exit();
}

$result_id = intval($_GET['result_id']);

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
    } elseif ($_POST['action'] == 'unpublish') {
        $stmt = $conn->prepare("UPDATE Results SET is_published = 0 WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Result unpublished successfully.";
        header("Location: view_student_result.php?result_id=" . $result_id);
        exit();
    } elseif ($_POST['action'] == 'delete') {
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
    } elseif ($_POST['action'] == 'update_subject_marks' && isset($_POST['result_id'])) {
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

        // Check if subject is failed using 33% rule
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
    } elseif ($_POST['action'] == 'delete_subject' && isset($_POST['detail_id'])) {
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

    // Get all subject results for this student and exam - EXACT same logic as view_upload.php
    $subject_results = [];

    // Use the exact same query structure as view_upload.php
    $query = "SELECT r.*, s.subject_name, s.subject_code
          FROM results r
          JOIN subjects s ON r.subject_id = s.subject_id
          WHERE r.student_id = ? AND r.exam_id = ?";

    $params = [$result_data['student_id'], $result_data['exam_id']];
    $param_types = "ii"; // Changed to string types to match view_upload.php

    // If we have an upload_id, add it to the query for more specific results
    if (!empty($result_data['upload_id'])) {
        $query .= " AND r.upload_id = ?";
        $params[] = $result_data['upload_id'];
        $param_types .= "i";
    }

    $query .= " ORDER BY s.subject_name, r.result_id DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Collect all results first - EXACT same as view_upload.php
    $all_results = [];
    while ($row = $result->fetch_assoc()) {
        $all_results[] = $row;
    }
    $stmt->close();

    // Process results using EXACT same logic as view_upload.php
    $student_results = [];

    // Group by student first (even though we only have one student)
    $results_by_student = [];
    foreach ($all_results as $row) {
        $student_id = $row['student_id'];
        if (!isset($results_by_student[$student_id])) {
            $results_by_student[$student_id] = [];
        }
        $results_by_student[$student_id][] = $row;
    }

    // Process each student's results (we only have one)
    foreach ($results_by_student as $student_id => $student_subjects) {
        // Remove duplicates by keeping only the first (most recent) result for each subject
        $seen_subjects = [];
        $unique_subjects = [];

        foreach ($student_subjects as $row) {
            $subject_key = $row['subject_id'];
            if (!isset($seen_subjects[$subject_key])) {
                $seen_subjects[$subject_key] = true;
                $unique_subjects[] = $row;
            }
        }

    // Process each unique subject with EXACT same calculation as view_upload.php
    foreach ($unique_subjects as $row) {
        $theory_marks = floatval($row['theory_marks'] ?? 0);
        $practical_marks = floatval($row['practical_marks'] ?? 0);

        // Determine if subject has practical based on whether practical_marks > 0 - EXACT same logic
        $has_practical = $practical_marks > 0;

        // Determine full marks based on whether practical exists - EXACT same logic
        $theory_full_marks = $has_practical ? 75 : 100;
        $practical_full_marks = $has_practical ? 25 : 0;
        $subject_full_marks = 100; // Total is always 100

        $subject_total_obtained = $theory_marks + $practical_marks;

        // Calculate using exact GPA functions - SAME as view_upload.php
        if ($has_practical) {
            // Theory + Practical case (75 + 25)
            $gpaResult = calculateTheoryPracticalGPA($theory_marks, $practical_marks, 75, 25);
            if (isset($gpaResult['error'])) {
                $theory_percentage = 0;
                $practical_percentage = 0;
                $theory_grade_info = ['grade' => 'Error', 'gpa' => 0, 'class' => 'bg-red-100 text-red-800'];
                $practical_grade_info = ['grade' => 'Error', 'gpa' => 0, 'class' => 'bg-red-100 text-red-800'];
                $final_gpa = 0.0;
                $final_grade = 'Error';
                $final_grade_class = 'bg-red-100 text-red-800';
                $is_failed = true;
            } else {
                $theory_percentage = $gpaResult['theory']['percentage'];
                $practical_percentage = $gpaResult['practical']['percentage'];
                $theory_grade_info = ['grade' => $gpaResult['theory']['grade'], 'gpa' => $gpaResult['theory']['gpa'], 'class' => $gpaResult['theory']['class']];
                $practical_grade_info = ['grade' => $gpaResult['practical']['grade'], 'gpa' => $gpaResult['practical']['gpa'], 'class' => $gpaResult['practical']['class']];
                $final_gpa = $gpaResult['final_gpa'];

                // Check for failure condition (either theory or practical below 35%)
                $is_failed = ($theory_percentage < 35) || ($practical_percentage < 35);

                if ($is_failed) {
                    $final_gpa = 0.0;
                    $final_grade = 'NG';
                    $final_grade_class = 'bg-red-100 text-red-800';
                } else {
                    // Determine final grade based on exact GPA
                    if ($final_gpa >= 3.6) {
                        $final_grade = 'A+';
                    } elseif ($final_gpa >= 3.2) {
                        $final_grade = 'A';
                    } elseif ($final_gpa >= 2.8) {
                        $final_grade = 'B+';
                    } elseif ($final_gpa >= 2.6) {
                        $final_grade = 'B';
                    } elseif ($final_gpa >= 2.2) {
                        $final_grade = 'C+';
                    } elseif ($final_gpa > 1.6) {
                        $final_grade = 'C';
                    } elseif ($final_gpa == 1.6) {
                        $final_grade = 'D+';
                    } else {
                        $final_grade = 'NG';
                    }

                    $final_grade_class = $final_grade == 'NG' ? 'bg-red-100 text-red-800' : ($final_gpa >= 3.0 ? 'bg-green-100 text-green-800' : ($final_gpa >= 2.0 ? 'bg-yellow-100 text-yellow-800' : 'bg-orange-100 text-orange-800'));
                }
            }
        } else {
            // Theory only case (100 marks)
            $gpaResult = calculateTheoryOnlyGPA($theory_marks, 100);
            if (isset($gpaResult['error'])) {
                $theory_percentage = 0;
                $theory_grade_info = ['grade' => 'Error', 'gpa' => 0, 'class' => 'bg-red-100 text-red-800'];
                $final_gpa = 0.0;
                $final_grade = 'Error';
                $final_grade_class = 'bg-red-100 text-red-800';
                $is_failed = true;
            } else {
                $theory_percentage = $gpaResult['percentage'];
                $theory_grade_info = ['grade' => $gpaResult['grade'], 'gpa' => $gpaResult['gpa'], 'class' => $gpaResult['class']];
                $final_gpa = $gpaResult['gpa'];

                // Check for failure condition (theory below 35%)
                $is_failed = ($theory_percentage < 35);

                if ($is_failed) {
                    $final_gpa = 0.0;
                    $final_grade = 'NG';
                    $final_grade_class = 'bg-red-100 text-red-800';
                } else {
                    $final_grade = $gpaResult['grade'];
                    $final_grade_class = $gpaResult['class'];
                }
            }
            $practical_percentage = 0;
            $practical_grade_info = ['grade' => 'N/A', 'gpa' => 0, 'class' => 'bg-gray-100 text-gray-800'];
        }

        // Store calculated values - EXACT same structure as view_upload.php
        $subject_results[] = [
            'result_id' => $row['result_id'],
            'subject_id' => $row['subject_id'],
            'subject_name' => $row['subject_name'],
            'subject_code' => $row['subject_code'],
            'theory_marks' => $theory_marks,
            'practical_marks' => $has_practical ? $practical_marks : null,
            'grade' => $row['grade'], // Keep original grade from DB
            'gpa' => $row['gpa'], // Keep original GPA from DB
            'calculated_grade' => $final_grade,
            'calculated_gpa' => $final_gpa,
            'calculated_percentage' => ($subject_total_obtained / $subject_full_marks) * 100,
            'theory_full_marks' => $theory_full_marks,
            'practical_full_marks' => $practical_full_marks,
            'subject_full_marks' => $subject_full_marks,
            'has_practical' => $has_practical,
            'theory_percentage' => $theory_percentage,
            'practical_percentage' => $practical_percentage,
            'theory_grade_info' => $theory_grade_info,
            'practical_grade_info' => $practical_grade_info,
            'final_grade_class' => $final_grade_class,
            'is_failed' => $is_failed
        ];
    }
}

    // Calculate overall result from subject results using EXACT same logic as view_upload.php
    $total_marks_obtained = 0;
    $total_full_marks = 0;
    $total_subjects = count($subject_results);
    $failed_subjects = 0;
    $total_gpa_points = 0;

    foreach ($subject_results as $subject) {
        $subject_total_obtained = $subject['theory_marks'] + ($subject['practical_marks'] ?? 0);
        $total_marks_obtained += $subject_total_obtained;
        $total_full_marks += $subject['subject_full_marks'];
        $total_gpa_points += $subject['calculated_gpa'];

        if ($subject['is_failed']) {
            $failed_subjects++;
        }
    }

    // Calculate overall percentage and GPA - EXACT same logic as view_upload.php
    $overall_percentage = $total_full_marks > 0 ? ($total_marks_obtained / $total_full_marks) * 100 : 0;
    $overall_gpa = $total_subjects > 0 ? ($total_gpa_points / $total_subjects) : 0;
    $is_pass = ($failed_subjects == 0);

    // Determine overall grade - EXACT same logic as view_upload.php
    if ($failed_subjects > 0) {
        $overall_grade = 'NG';
    } else {
        if ($overall_percentage >= 91) {
            $overall_grade = 'A+';
        } elseif ($overall_percentage >= 81) {
            $overall_grade = 'A';
        } elseif ($overall_percentage >= 71) {
            $overall_grade = 'B+';
        } elseif ($overall_percentage >= 61) {
            $overall_grade = 'B';
        } elseif ($overall_percentage >= 51) {
            $overall_grade = 'C+';
        } elseif ($overall_percentage >= 41) {
            $overall_grade = 'C';
        } elseif ($overall_percentage >= 35) {
            $overall_grade = 'D+';
        } else {
            $overall_grade = 'NG';
        }
    }

    // Determine division - EXACT same logic as view_upload.php
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
            background-color: rgba(0, 0, 0, 0.4);
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
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded no-print">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700">
                                            <?php echo $_SESSION['success'];
                                            unset($_SESSION['success']); ?>
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

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded no-print">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700">
                                            <?php echo $_SESSION['error'];
                                            unset($_SESSION['error']); ?>
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

                            <!-- Student Information -->
                            <div class="border border-gray-200 rounded-lg p-4 mb-6 bg-white">
                                <h2 class="text-lg font-semibold text-gray-800 mb-3 border-b pb-2">Student Information</h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500">Full Name</p>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($result_data['full_name']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Roll Number</p>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($result_data['roll_number']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Class</p>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($result_data['class_name'] . ' ' . $result_data['section']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Academic Year</p>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($result_data['academic_year']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Email</p>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($result_data['email'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Phone</p>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($result_data['phone'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Exam Information -->
                            <div class="border border-gray-200 rounded-lg p-4 bg-white">
                                <h2 class="text-lg font-semibold text-gray-800 mb-3 border-b pb-2">Exam Information</h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500">Exam Name</p>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($result_data['exam_name']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Exam Type</p>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($result_data['exam_type']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Exam Date</p>
                                        <p class="font-medium text-gray-800"><?php echo date('d M Y', strtotime($result_data['exam_date'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Result Date</p>
                                        <p class="font-medium text-gray-800"><?php echo date('d M Y', strtotime($result_data['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>



                            <!-- Subject-wise Results -->
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">Results </h2>
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
                                                            <div class="text-xs text-gray-500">ID: <?php echo $subject['subject_id']; ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($subject['subject_code'] ?? 'N/A'); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="flex flex-col">
                                                                <span><?php echo number_format($subject['theory_marks'], 1); ?>/<?php echo $subject['theory_full_marks']; ?></span>
                                                                <span class="text-xs text-gray-400"><?php echo number_format($subject['theory_percentage'], 1); ?>%</span>
                                                                <span class="px-1 inline-flex text-xs leading-4 font-semibold rounded <?php echo $subject['theory_grade_info']['class']; ?>">
                                                                    <?php echo $subject['theory_grade_info']['grade']; ?>
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php if ($subject['has_practical']): ?>
                                                                <div class="flex flex-col">
                                                                    <span><?php echo number_format($subject['practical_marks'], 1); ?>/<?php echo $subject['practical_full_marks']; ?></span>
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
                                                            <div class="flex flex-col">
                                                                <span class="font-medium"><?php echo number_format($subject['theory_marks'] + $subject['practical_marks'], 1); ?>/100</span>
                                                                <span class="text-xs text-gray-400"><?php echo number_format($subject['calculated_percentage'], 1); ?>%</span>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm font-semibold text-gray-900">
                                                                <?php echo number_format($subject['calculated_percentage'], 2); ?>%
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="flex flex-col">
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $subject['final_grade_class']; ?>">
                                                                    <?php echo $subject['calculated_grade']; ?>
                                                                </span>
                                                                <span class="text-xs text-gray-400 mt-1">GPA: <?php echo number_format($subject['calculated_gpa'], 2); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php $is_pass = ($subject['calculated_grade'] != 'NG'); ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $is_pass ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                                <?php echo $is_pass ? 'Pass' : 'Fail'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium no-print">
                                                            <div class="flex space-x-3">
                                                                <button type="button" onclick="openEditSubjectMarksModal('<?php echo $subject['result_id']; ?>', '<?php echo htmlspecialchars($subject['subject_name']); ?>', <?php echo $subject['theory_marks']; ?>, <?php echo $subject['practical_marks'] ?? 0; ?>, <?php echo $subject['has_practical'] ? 'true' : 'false'; ?>)" class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
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
                            <div class="p-6 bg-white rounded-md border border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-800 mb-4">Overall Result Summary</h2>

                                <!-- Summary Cards -->
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 text-sm">
                                    <div class="border p-4 rounded text-center">
                                        <p class="text-gray-500">Total Marks</p>
                                        <p class="text-xl font-bold text-gray-800">
                                            <?php echo number_format($result_data['calculated_marks_obtained'], 0); ?> /
                                            <?php echo number_format($result_data['calculated_total_marks'], 0); ?>
                                        </p>
                                    </div>
                                    <div class="border p-4 rounded text-center">
                                        <p class="text-gray-500">Percentage</p>
                                        <p class="text-xl font-bold text-gray-800">
                                            <?php echo number_format($result_data['calculated_percentage'], 2); ?>%
                                        </p>
                                    </div>
                                    <div class="border p-4 rounded text-center">
                                        <p class="text-gray-500">GPA</p>
                                        <p class="text-xl font-bold text-gray-800">
                                            <?php echo number_format($result_data['calculated_gpa'], 2); ?> / 4.0
                                        </p>
                                    </div>
                                    <div class="border p-4 rounded text-center">
                                        <p class="text-gray-500">Grade</p>
                                        <?php
                                        $grade_class = match ($result_data['calculated_grade']) {
                                            'A+', 'A' => 'text-green-700',
                                            'B+', 'B' => 'text-blue-700',
                                            'C+', 'C' => 'text-yellow-700',
                                            'D'        => 'text-orange-700',
                                            'NG'        => 'text-red-700',
                                            default    => 'text-gray-700'
                                        };
                                        ?>
                                        <p class="text-xl font-bold <?php echo $grade_class; ?>">
                                            <?php echo htmlspecialchars($result_data['calculated_grade']); ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Additional Info -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                    <div class="border p-4 rounded text-center">
                                        <p class="text-gray-500">Result Status</p>
                                        <p class="text-lg font-bold <?php echo $result_data['calculated_is_pass'] ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $result_data['calculated_is_pass'] ? 'PASS' : 'FAIL'; ?>
                                        </p>
                                    </div>
                                    <div class="border p-4 rounded text-center">
                                        <p class="text-gray-500">Division</p>
                                        <p class="text-lg font-bold text-gray-800">
                                            <?php echo htmlspecialchars($result_data['calculated_division']); ?>
                                        </p>
                                    </div>
                                    <div class="border p-4 rounded text-center">
                                        <p class="text-gray-500">Failed Subjects</p>
                                        <p class="text-lg font-bold <?php echo $result_data['failed_subjects'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                            <?php echo $result_data['failed_subjects']; ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Print-Only Signature Section -->
                                <div class="hidden print:block mt-12">
                                    <div class="grid grid-cols-3 gap-4 text-center">
                                        <div>
                                            <div class="border-t border-gray-400 pt-2">
                                                <p>Class Teacher</p>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="border-t border-gray-400 pt-2">
                                                <p>Examination Controller</p>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="border-t border-gray-400 pt-2">
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
            <h2 class="text-xl font-bold mb-4">Edit Subject Marks</h2>
            <form method="POST" id="editSubjectMarksForm">
                <input type="hidden" name="action" value="update_subject_marks">
                <input type="hidden" name="result_id" id="edit_result_id">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                    <p id="edit_subject_name" class="text-lg font-semibold text-gray-900"></p>
                </div>

                <div class="mb-4">
                    <label for="edit_theory_marks" class="block text-sm font-medium text-gray-700 mb-2">Theory Marks</label>
                    <input type="number" name="theory_marks" id="edit_theory_marks" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <p class="text-xs text-gray-500 mt-1">Maximum: <span id="theory_max_marks"></span> marks</p>
                </div>

                <div class="mb-4" id="practical_marks_section">
                    <label for="edit_practical_marks" class="block text-sm font-medium text-gray-700 mb-2">Practical Marks</label>
                    <input type="number" name="practical_marks" id="edit_practical_marks" step="0.01" min="0" max="25" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Maximum: 25 marks (leave empty if no practical)</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditSubjectMarksModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Update Marks</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openEditSubjectMarksModal(resultId, subjectName, theoryMarks, practicalMarks, hasPractical) {
            document.getElementById('edit_result_id').value = resultId;
            document.getElementById('edit_subject_name').textContent = subjectName;
            document.getElementById('edit_theory_marks').value = theoryMarks;

            if (hasPractical) {
                document.getElementById('edit_practical_marks').value = practicalMarks || '';
                document.getElementById('practical_marks_section').style.display = 'block';
                document.getElementById('theory_max_marks').textContent = '75';
                document.getElementById('edit_theory_marks').max = '75';
            } else {
                document.getElementById('edit_practical_marks').value = '';
                document.getElementById('practical_marks_section').style.display = 'none';
                document.getElementById('theory_max_marks').textContent = '100';
                document.getElementById('edit_theory_marks').max = '100';
            }

            document.getElementById('editSubjectMarksModal').style.display = 'block';
        }

        function closeEditSubjectMarksModal() {
            document.getElementById('editSubjectMarksModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editSubjectMarksModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Form validation
        document.getElementById('editSubjectMarksForm').addEventListener('submit', function(e) {
            const theoryMarks = parseFloat(document.getElementById('edit_theory_marks').value);
            const practicalMarks = parseFloat(document.getElementById('edit_practical_marks').value) || 0;
            const theoryMax = parseFloat(document.getElementById('edit_theory_marks').max);

            if (theoryMarks > theoryMax) {
                e.preventDefault();
                alert('Theory marks cannot exceed ' + theoryMax);
                return;
            }

            if (practicalMarks > 25) {
                e.preventDefault();
                alert('Practical marks cannot exceed 25');
                return;
            }

            if ((theoryMarks + practicalMarks) > 100) {
                e.preventDefault();
                alert('Total marks cannot exceed 100');
                return;
            }
        });
    </script>
</body>

</html>
