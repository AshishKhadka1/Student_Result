<?php
// Start session for potential authentication check
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'teacher' && $_SESSION['role'] != 'admin')) {
    header("Location: ../login.php");
    exit();
}

// Connect to database
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Exact GPA Calculation Functions - SAME as view_student_result.php
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

// Function to check if subject is failed - Updated to match view_student_result.php (33% minimum)
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

// Initialize variables
$student = [];
$subjects = [];
$gpa = 0;
$percentage = 0;
$division = '';
$total_marks = 0;
$max_marks = 0;
$prepared_by = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'System Administrator';
$issue_date = date('Y-m-d');

// Get all classes for filter
$classes = [];
$class_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name, section");
if ($class_result) {
    while ($class_row = $class_result->fetch_assoc()) {
        $classes[] = $class_row;
    }
}

// Get all academic years for filter
$academic_years = [];
$year_result = $conn->query("SELECT DISTINCT academic_year FROM exams ORDER BY academic_year DESC");
if ($year_result) {
    while ($year_row = $year_result->fetch_assoc()) {
        $academic_years[] = $year_row['academic_year'];
    }
}

// Get all exam types for filter
$exam_types = [];
// First check if exam_type column exists
$column_check = $conn->query("SHOW COLUMNS FROM exams LIKE 'exam_type'");
if ($column_check && $column_check->num_rows > 0) {
    $type_result = $conn->query("SELECT DISTINCT exam_type FROM exams WHERE exam_type IS NOT NULL ORDER BY exam_type");
    if ($type_result) {
        while ($type_row = $type_result->fetch_assoc()) {
            if (!empty($type_row['exam_type'])) {
                $exam_types[] = $type_row['exam_type'];
            }
        }
    }
}

// If no exam types found, add default ones
if (empty($exam_types)) {
    $exam_types = ['Final Exam', 'Mid-Term Exam', 'Unit Test', 'Quarterly Exam', 'Half-Yearly Exam'];
}

// Check if viewing a specific student result
if (isset($_GET['student_id']) && isset($_GET['exam_id'])) {
    // Get student information
    $student_id = $_GET['student_id'];
    $exam_id = $_GET['exam_id'];

    // Get student details
    $stmt = $conn->prepare("
        SELECT s.student_id, s.roll_number, s.registration_number, u.full_name, 
               c.class_name, c.section, e.exam_name, e.exam_type, e.academic_year,
               e.start_date, e.end_date
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        JOIN classes c ON s.class_id = c.class_id
        JOIN exams e ON e.exam_id = ?
        WHERE s.student_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("is", $exam_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            die("Student or exam not found");
        }

        $student = $result->fetch_assoc();
        $stmt->close();
    } else {
        die("Database error: " . $conn->error);
    }

    // Get results for this student and exam using EXACT same logic as view_student_result.php
    $stmt = $conn->prepare("
        SELECT r.*, s.subject_name, s.subject_code, s.full_marks_theory, s.full_marks_practical
        FROM results r
        JOIN subjects s ON r.subject_id = s.subject_id
        WHERE r.student_id = ? AND r.exam_id = ? AND r.is_published = 1
        ORDER BY s.subject_name
    ");
    
    if ($stmt) {
        $stmt->bind_param("si", $student_id, $exam_id);
        $stmt->execute();
        $results_data = $stmt->get_result();

        $subjects = [];
        $total_marks_obtained = 0;
        $total_full_marks = 0;
        $total_subjects = 0;
        $failed_subjects = 0;
        $total_gpa_points = 0;

        while ($row = $results_data->fetch_assoc()) {
            $theory_marks = floatval($row['theory_marks'] ?? 0);
            $practical_marks = floatval($row['practical_marks'] ?? 0);

            // Determine if subject has practical based on whether practical_marks > 0
            $has_practical = !is_null($row['practical_marks']) && $row['practical_marks'] > 0;

            // Determine full marks based on whether practical exists
            $theory_full_marks = $has_practical ? 75 : 100;
            $practical_full_marks = $has_practical ? 25 : 0;
            $subject_full_marks = 100; // Total is always 100

            $subject_total_obtained = $theory_marks + $practical_marks;
            $total_marks_obtained += $subject_total_obtained;
            $total_full_marks += $subject_full_marks;
            $total_subjects++;

            // Calculate using exact GPA functions - SAME as view_student_result.php
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

                    // Check for failure condition (either theory or practical below 33%)
                    $is_failed = ($theory_percentage < 33) || ($practical_percentage < 33);

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

                    // Check for failure condition (theory below 33%)
                    $is_failed = ($theory_percentage < 33);

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

            if ($is_failed) {
                $failed_subjects++;
            }

            $total_gpa_points += $final_gpa;

            $subjects[] = [
                'code' => $row['subject_code'] ?? $row['subject_id'],
                'name' => $row['subject_name'],
                'credit_hour' => $row['credit_hours'] ?? 4,
                'theory_marks' => $theory_marks,
                'practical_marks' => $has_practical ? $practical_marks : null,
                'full_marks_theory' => $theory_full_marks,
                'full_marks_practical' => $practical_full_marks,
                'total_marks' => $subject_total_obtained,
                'grade' => $row['grade'],
                'remarks' => $row['remarks'] ?? '',
                'calculated_grade' => $final_grade,
                'calculated_gpa' => $final_gpa,
                'has_practical' => $has_practical,
                'theory_percentage' => $theory_percentage,
                'practical_percentage' => $practical_percentage,
                'theory_grade_info' => $theory_grade_info,
                'practical_grade_info' => $practical_grade_info,
                'final_grade_class' => $final_grade_class,
                'is_failed' => $is_failed
            ];
        }

        $stmt->close();

        // Calculate overall performance using EXACT same logic as view_student_result.php
        $overall_percentage = $total_full_marks > 0 ? ($total_marks_obtained / $total_full_marks) * 100 : 0;
        $overall_gpa = $total_subjects > 0 ? ($total_gpa_points / $total_subjects) : 0;
        $is_pass = ($failed_subjects == 0);

        // Determine overall grade - EXACT same logic as view_student_result.php
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

        // Determine division - EXACT same logic as view_student_result.php
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

        // Set final values
        $total_marks = $total_marks_obtained;
        $max_marks = $total_full_marks;
        $percentage = $overall_percentage;
        $gpa = $overall_gpa;

    } else {
        die("Database error: " . $conn->error);
    }
} else {
    // If no specific student is requested, show a list of students to select
    $show_student_list = true;

    // Get available exams based on filters
    $exams = [];
    $filter_class = isset($_GET['class_id']) ? $_GET['class_id'] : '';
    $filter_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';
    $filter_exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';
    $search_submitted = isset($_GET['search_submitted']) && $_GET['search_submitted'] == '1';

    // Only apply filters if search was submitted
    if ($search_submitted) {
        // Build the query with filters
        $exam_query = "SELECT e.exam_id, e.exam_name, e.exam_type, e.academic_year 
                      FROM exams e 
                      WHERE e.is_active = 1";

        $params = [];
        $param_types = "";

        if (!empty($filter_class)) {
            // Check if the exams table has a class_id column
            $column_check = $conn->query("SHOW COLUMNS FROM exams LIKE 'class_id'");
            if ($column_check && $column_check->num_rows > 0) {
                $exam_query .= " AND e.class_id = ?";
                $params[] = $filter_class;
                $param_types .= "i";
            } else {
                // If no class_id in exams table, we need to join with results and students
                $exam_query = "SELECT DISTINCT e.exam_id, e.exam_name, e.exam_type, e.academic_year 
                              FROM exams e 
                              JOIN results r ON e.exam_id = r.exam_id
                              JOIN students s ON r.student_id = s.student_id
                              WHERE e.is_active = 1 AND s.class_id = ?";
                $params[] = $filter_class;
                $param_types .= "i";
            }
        }

        if (!empty($filter_year)) {
            $exam_query .= " AND e.academic_year = ?";
            $params[] = $filter_year;
            $param_types .= "s";
        }

        // Check if exam_type column exists
        $column_check = $conn->query("SHOW COLUMNS FROM exams LIKE 'exam_type'");
        if ($column_check && $column_check->num_rows > 0 && !empty($filter_exam_type)) {
            $exam_query .= " AND e.exam_type = ?";
            $params[] = $filter_exam_type;
            $param_types .= "s";
        }

        $exam_query .= " ORDER BY e.created_at DESC";
        
        if (!empty($params)) {
            $stmt = $conn->prepare($exam_query);
            if ($stmt) {
                $stmt->bind_param($param_types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $exams[] = $row;
                }
                $stmt->close();
            } else {
                // Fallback to simple query if prepare fails
                $result = $conn->query("SELECT exam_id, exam_name, exam_type, academic_year FROM exams WHERE is_active = 1 ORDER BY created_at DESC");
                while ($row = $result->fetch_assoc()) {
                    $exams[] = $row;
                }
            }
        } else {
            // No filters, get all exams
            $result = $conn->query($exam_query);
            while ($row = $result->fetch_assoc()) {
                $exams[] = $row;
            }
        }
    } else {
        // If search not submitted, just get recent exams for the dropdown
        $result = $conn->query("SELECT exam_id, exam_name, exam_type, academic_year FROM exams WHERE is_active = 1 ORDER BY created_at DESC LIMIT 20");
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
    }

    // Get students if an exam is selected
    $students = [];
    if (isset($_GET['exam_id'])) {
        $selected_exam_id = $_GET['exam_id'];

        // Get students with published results only
        $students_query = "
            SELECT DISTINCT s.student_id, s.roll_number, u.full_name, c.class_name, c.section
            FROM students s
            JOIN users u ON s.user_id = u.user_id
            JOIN classes c ON s.class_id = c.class_id
            JOIN results r ON s.student_id = r.student_id
            JOIN exams e ON r.exam_id = e.exam_id
            WHERE (r.is_published = 1 OR e.results_published = 1)
            AND e.exam_id = ?
            ORDER BY s.roll_number
        ";
        
        $stmt = $conn->prepare($students_query);
        
        if ($stmt) {
            $stmt->bind_param("i", $selected_exam_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            $stmt->close();
        } else {
            die("Database error: " . $conn->error);
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
    <title>Grade Sheet | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        .grade-sheet-container {
            width: 21cm;
            min-height: 29.7cm;
            padding: 1cm;
            margin: 20px auto;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            box-sizing: border-box;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(0, 0, 0, 0.03);
            z-index: 0;
            pointer-events: none;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            border-bottom: 2px solid #1a5276;
            padding-bottom: 10px;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 10px;
            background-color: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #555;
        }

        .title {
            font-weight: bold;
            font-size: 22px;
            margin-bottom: 5px;
            color: #1a5276;
        }

        .subtitle {
            font-size: 18px;
            margin-bottom: 5px;
            color: #2874a6;
        }

        .exam-title {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
            color: #1a5276;
            border: 2px solid #1a5276;
            display: inline-block;
            padding: 5px 15px;
            border-radius: 5px;
        }

        .student-info {
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .info-item {
            margin-bottom: 8px;
        }

        .info-label {
            font-weight: bold;
            color: #2874a6;
        }

        .grade-sheet-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .grade-sheet-table,
        .grade-sheet-table th,
        .grade-sheet-table td {
            border: 1px solid #bdc3c7;
        }

        .grade-sheet-table th,
        .grade-sheet-table td {
            padding: 10px;
            text-align: center;
        }

        .grade-sheet-table th {
            background-color: #1a5276;
            color: white;
            font-weight: bold;
        }

        .grade-sheet-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .summary {
            margin: 20px 0;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }

        .summary-item {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
        }

        .summary-label {
            font-weight: bold;
            color: #2874a6;
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 18px;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .signature {
            text-align: center;
            margin-top: 50px;
        }

        .signature-line {
            width: 80%;
            margin: 50px auto 10px;
            border-top: 1px solid #333;
        }

        .signature-title {
            font-weight: bold;
        }

        .grade-scale {
            margin-top: 20px;
            font-size: 12px;
            border: 1px solid #bdc3c7;
            padding: 10px;
            background-color: #f9f9f9;
        }

        .grade-title {
            font-weight: bold;
            margin-bottom: 5px;
            text-align: center;
        }

        .grade-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .grade-table th,
        .grade-table td {
            padding: 3px;
            text-align: center;
            border: 1px solid #ddd;
        }

        .qr-code {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 80px;
            height: 80px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #555;
        }

        @media print {
            body {
                background-color: white !important;
            }

            .grade-sheet-container {
                width: 100%;
                min-height: auto;
                padding: 0.5cm;
                margin: 0;
                box-shadow: none;
            }

            .print-button,
            .back-button,
            .sidebar,
            .top-navigation,
            .selection-container {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }
        
        /* Filter styles */
        .filter-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .filter-title {
            font-weight: 600;
            margin-bottom: 12px;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }
        
        .filter-title i {
            margin-right: 8px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .filter-item {
            margin-bottom: 8px;
        }
        
        .filter-label {
            display: block;
            font-size: 14px;
            margin-bottom: 4px;
            color: #4a5568;
        }
        
        .filter-select {
            width: 100%;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            background-color: white;
            font-size: 14px;
        }
        
        .filter-button {
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .filter-button:hover {
            background-color: #1a5276;
        }
        
        .filter-reset {
            background-color: #e2e8f0;
            color: #4a5568;
            margin-left: 8px;
        }
        
        .filter-reset:hover {
            background-color: #cbd5e0;
        }
        
        .filter-actions {
            margin-top: 12px;
            display: flex;
            justify-content: flex-end;
        }
        
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        
        .filter-tag {
            background-color: #e2e8f0;
            color: #4a5568;
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 12px;
            display: flex;
            align-items: center;
        }
        
        .filter-tag i {
            margin-left: 6px;
            cursor: pointer;
        }
        
        .filter-tag i:hover {
            color: #e53e3e;
        }

        /* Search button styles */
        .search-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .search-button:hover {
            background-color: #2980b9;
        }
        
        .search-button i {
            margin-right: 8px;
        }
        
        .exam-type-container {
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 12px;
            background-color: #f8fafc;
        }
        
        .exam-type-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .exam-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 8px;
        }
        
        .exam-type-option {
            display: flex;
            align-items: center;
        }
        
        .exam-type-option input {
            margin-right: 6px;
        }
        
        .exam-type-option label {
            font-size: 14px;
            color: #4a5568;
            cursor: pointer;
        }

        /* Simple Overall Result Summary styles */
        .simple-summary {
            margin: 20px 0;
            position: relative;
            z-index: 1;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
            padding: 20px;
        }

        .simple-summary h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
            font-weight: bold;
        }

        .simple-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .simple-summary-item {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }

        .simple-summary-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .simple-summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .simple-additional-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .simple-info-item {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }

        .simple-info-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .simple-info-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .simple-info-value.pass {
            color: #28a745;
        }

        .simple-info-value.fail {
            color: #dc3545;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        <?php include 'mobile_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include 'topBar.php'; ?>

            <!-- Main Content Area -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <?php if (isset($show_student_list)): ?>
                            <!-- Selection Form Container -->
                            <div class="bg-white shadow rounded-lg p-6 mb-6">
                                <h1 class="text-2xl font-bold text-gray-900 mb-4">Grade Sheet</h1>
                                
                                <!-- Advanced Filters -->
                                <div class="filter-container">
                                    <div class="filter-title">
                                        <i class="fas fa-filter"></i> Filter Grade Sheets
                                    </div>
                                    <form action="" method="GET" id="filter-form">
                                        <input type="hidden" name="search_submitted" value="1">
                                        <div class="filter-grid">
                                            <div class="filter-item">
                                                <label for="class_id" class="filter-label">Class:</label>
                                                <select name="class_id" id="class_id" class="filter-select">
                                                    <option value="">All Classes</option>
                                                    <?php foreach ($classes as $class): ?>
                                                        <option value="<?php echo $class['class_id']; ?>" <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                                                            <?php echo $class['class_name'] . ' ' . $class['section']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="filter-item">
                                                <label for="academic_year" class="filter-label">Academic Year:</label>
                                                <select name="academic_year" id="academic_year" class="filter-select">
                                                    <option value="">All Years</option>
                                                    <?php foreach ($academic_years as $year): ?>
                                                        <option value="<?php echo $year; ?>" <?php echo (isset($_GET['academic_year']) && $_GET['academic_year'] == $year) ? 'selected' : ''; ?>>
                                                            <?php echo $year; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <!-- Enhanced Exam Type Selection -->
                                        <div class="exam-type-container mt-4">
                                            <div class="exam-type-title">Exam Type:</div>
                                            <div class="exam-type-grid">
                                                <div class="exam-type-option">
                                                    <input type="radio" id="exam_type_all" name="exam_type" value="" <?php echo (!isset($_GET['exam_type']) || empty($_GET['exam_type'])) ? 'checked' : ''; ?>>
                                                    <label for="exam_type_all">All Types</label>
                                                </div>
                                                <?php foreach ($exam_types as $index => $type): ?>
                                                    <div class="exam-type-option">
                                                        <input type="radio" id="exam_type_<?php echo $index; ?>" name="exam_type" value="<?php echo $type; ?>" <?php echo (isset($_GET['exam_type']) && $_GET['exam_type'] == $type) ? 'checked' : ''; ?>>
                                                        <label for="exam_type_<?php echo $index; ?>"><?php echo $type; ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="filter-actions mt-6">
                                            <button type="button" id="reset-filters" class="filter-button filter-reset">
                                                <i class="fas fa-undo mr-2"></i> Reset
                                            </button>
                                            <button type="submit" class="search-button ml-3">
                                                <i class="fas fa-search"></i> Search Grade Sheets
                                            </button>
                                        </div>
                                        
                                        <?php if ($search_submitted && (isset($_GET['class_id']) || isset($_GET['academic_year']) || isset($_GET['exam_type']))): ?>
                                            <div class="active-filters">
                                                <div class="text-sm text-gray-600 mr-2">Active filters:</div>
                                                <?php if (isset($_GET['class_id']) && !empty($_GET['class_id'])): 
                                                    $class_name = '';
                                                    foreach ($classes as $class) {
                                                        if ($class['class_id'] == $_GET['class_id']) {
                                                            $class_name = $class['class_name'] . ' ' . $class['section'];
                                                            break;
                                                        }
                                                    }
                                                ?>
                                                    <div class="filter-tag">
                                                        Class: <?php echo $class_name; ?>
                                                        <i class="fas fa-times-circle" onclick="removeFilter('class_id')"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($_GET['academic_year']) && !empty($_GET['academic_year'])): ?>
                                                    <div class="filter-tag">
                                                        Year: <?php echo $_GET['academic_year']; ?>
                                                        <i class="fas fa-times-circle" onclick="removeFilter('academic_year')"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($_GET['exam_type']) && !empty($_GET['exam_type'])): ?>
                                                    <div class="filter-tag">
                                                        Type: <?php echo $_GET['exam_type']; ?>
                                                        <i class="fas fa-times-circle" onclick="removeFilter('exam_type')"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                </div>

                                <?php if ($search_submitted): ?>
                                    <?php if (!empty($exams)): ?>
                                        <div class="mt-6">
                                            <h3 class="text-lg font-medium text-gray-900 mb-3">Select Exam:</h3>
                                            <div class="bg-white overflow-hidden shadow rounded-lg divide-y divide-gray-200">
                                                <div class="px-4 py-5 sm:px-6">
                                                    <h3 class="text-md font-medium text-gray-900">Found <?php echo count($exams); ?> exam(s) matching your criteria</h3>
                                                </div>
                                                <div class="px-4 py-5 sm:p-6">
                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                                        <?php foreach ($exams as $exam): ?>
                                                            <div class="bg-gray-50 overflow-hidden shadow-sm rounded-md hover:bg-blue-50 transition-colors">
                                                                <a href="?search_submitted=1&exam_id=<?php echo $exam['exam_id']; ?>&class_id=<?php echo isset($_GET['class_id']) ? $_GET['class_id'] : ''; ?>&academic_year=<?php echo isset($_GET['academic_year']) ? $_GET['academic_year'] : ''; ?>&exam_type=<?php echo isset($_GET['exam_type']) ? $_GET['exam_type'] : ''; ?>" class="block p-4">
                                                                    <div class="flex items-center">
                                                                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                                                            <i class="fas fa-file-alt text-white"></i>
                                                                        </div>
                                                                        <div class="ml-4">
                                                                            <div class="text-sm font-medium text-gray-900"><?php echo $exam['exam_name']; ?></div>
                                                                            <div class="text-sm text-gray-500"><?php echo $exam['academic_year']; ?></div>
                                                                            <?php if (!empty($exam['exam_type'])): ?>
                                                                                <div class="text-xs text-gray-500 mt-1">
                                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                                        <?php echo $exam['exam_type']; ?>
                                                                                    </span>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </a>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mt-6">
                                            <div class="flex">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm text-yellow-700">No exams found matching the selected filters.</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (isset($_GET['exam_id']) && !empty($students)): ?>
                                    <div class="mt-6">
                                        <h3 class="text-md font-medium text-gray-700 mb-3">Select Student:</h3>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll Number</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($students as $student): ?>
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['roll_number']; ?></td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['full_name']; ?></td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['class_name'] . ' ' . $student['section']; ?></td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                                <a href="?student_id=<?php echo $student['student_id']; ?>&exam_id=<?php echo $_GET['exam_id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                                    <i class="fas fa-eye mr-1"></i> View Grade Sheet
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php elseif (isset($_GET['exam_id'])): ?>
                                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mt-6">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-yellow-700">No students found with results for this exam.</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Grade Sheet View -->
                            <div class="mb-4 flex justify-between">
                                <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                                </a>
                                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <i class="fas fa-print mr-2"></i> Print Grade Sheet
                                </button>
                            </div>

                            <div class="grade-sheet-container">
                                <div class="watermark">OFFICIAL</div>

                                <div class="header">
                                    <div class="logo">LOGO</div>
                                    <div class="title"><?php echo isset($settings['school_name']) ? strtoupper($settings['school_name']) : 'GOVERNMENT OF NEPAL'; ?></div>
                                    <div class="title"><?php echo isset($settings['result_header']) ? strtoupper($settings['result_header']) : 'NATIONAL EXAMINATION BOARD'; ?></div>
                                    <div class="subtitle">SECONDARY EDUCATION EXAMINATION</div>
                                    <div class="exam-title">GRADE SHEET</div>
                                </div>

                                <div class="student-info">
                                    <div class="info-item">
                                        <span class="info-label">Student Name:</span>
                                        <span><?php echo $student['full_name']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Roll No:</span>
                                        <span><?php echo $student['roll_number']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Registration No:</span>
                                        <span><?php echo $student['registration_number']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Class:</span>
                                        <span><?php echo $student['class_name'] . ' ' . $student['section']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Examination:</span>
                                        <span><?php echo $student['exam_name']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Academic Year:</span>
                                        <span><?php echo $student['academic_year']; ?></span>
                                    </div>
                                    <?php if (!empty($student['exam_type'])): ?>
                                    <div class="info-item">
                                        <span class="info-label">Exam Type:</span>
                                        <span><?php echo $student['exam_type']; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <table class="grade-sheet-table">
                                    <thead>
                                        <tr>
                                            <th>SUBJECT CODE</th>
                                            <th>SUBJECTS</th>
                                            <th>CREDIT HOUR</th>
                                            <th>THEORY GRADE</th>
                                            <th>PRACTICAL GRADE</th>
                                            <th>FINAL GRADE</th>
                                            <th>GRADE POINT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($subjects)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">No results found for this student.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($subjects as $subject): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($subject['code']); ?></td>
                                                    <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($subject['credit_hour']); ?></td>
                                                    <td>
                                                        <?php echo $subject['theory_grade_info']['grade']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $subject['practical_grade_info']['grade']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($subject['calculated_grade']); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo number_format($subject['calculated_gpa'], 2); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>

                                <!-- Simple Overall Result Summary -->
                                <?php
                                // Check if any subject has NG grade
                                $has_ng_grade = false;
                                $failed_subjects = 0;
                                
                                foreach ($subjects as $subject) {
                                    if ($subject['is_failed']) {
                                        $has_ng_grade = true;
                                        $failed_subjects++;
                                    }
                                }
                                
                                $is_pass = ($failed_subjects == 0 && $percentage >= 35);
                                ?>

                                <div class="simple-summary">
                                    <h2>Overall Result Summary</h2>
                                    
                                    <!-- Summary Cards -->
                                    <div class="simple-summary-grid">
                                        <div class="simple-summary-item">
                                            <div class="simple-summary-label">Total Marks</div>
                                            <div class="simple-summary-value">
                                                <?php echo number_format($total_marks, 0); ?> / <?php echo number_format($max_marks, 0); ?>
                                            </div>
                                        </div>
                                        <div class="simple-summary-item">
                                            <div class="simple-summary-label">Percentage</div>
                                            <div class="simple-summary-value"><?php echo number_format($percentage, 2); ?>%</div>
                                        </div>
                                        <div class="simple-summary-item">
                                            <div class="simple-summary-label">GPA</div>
                                            <div class="simple-summary-value"><?php echo number_format($gpa, 2); ?> / 4.0</div>
                                        </div>
                                        <?php if (!$has_ng_grade): ?>
                                        <div class="simple-summary-item">
                                            <div class="simple-summary-label">Grade</div>
                                            <div class="simple-summary-value">
                                                <?php
                                                // Calculate grade based on percentage using exact same logic
                                                if ($percentage >= 91) echo 'A+';
                                                elseif ($percentage >= 81) echo 'A';
                                                elseif ($percentage >= 71) echo 'B+';
                                                elseif ($percentage >= 61) echo 'B';
                                                elseif ($percentage >= 51) echo 'C+';
                                                elseif ($percentage >= 41) echo 'C';
                                                elseif ($percentage >= 35) echo 'D+';
                                                else echo 'NG';
                                                ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Additional Information -->
                                    <div class="simple-additional-info">
                                        <div class="simple-info-item">
                                            <div class="simple-info-label">Result Status</div>
                                            <div class="simple-info-value <?php echo $is_pass ? 'pass' : 'fail'; ?>">
                                                <?php echo $is_pass ? 'PASS' : 'FAIL'; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="simple-info-item">
                                            <div class="simple-info-label">Division</div>
                                            <div class="simple-info-value"><?php echo $division; ?></div>
                                        </div>
                                        
                                        <div class="simple-info-item">
                                            <div class="simple-info-label">Failed Subjects</div>
                                            <div class="simple-info-value <?php echo $failed_subjects > 0 ? 'fail' : 'pass'; ?>">
                                                <?php echo $failed_subjects; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grade-scale">
                                    <div class="grade-title">GRADING SCALE</div>
                                    <table class="grade-table">
                                        <tr>
                                            <th>Grade</th>
                                            <th>A+</th>
                                            <th>A</th>
                                            <th>B+</th>
                                            <th>B</th>
                                            <th>C+</th>
                                            <th>C</th>
                                            <th>D+</th>
                                            <th>NG</th>
                                        </tr>
                                        <tr>
                                            <th>Marks Range</th>
                                            <td>91-100</td>
                                            <td>81-90</td>
                                            <td>71-80</td>
                                            <td>61-70</td>
                                            <td>51-60</td>
                                            <td>41-50</td>
                                            <td>35-40</td>
                                            <td>Below 35</td>
                                        </tr>
                                        <tr>
                                            <th>Grade Point</th>
                                            <td>3.6-4.0</td>
                                            <td>3.2-3.6</td>
                                            <td>2.8-3.2</td>
                                            <td>2.6-2.8</td>
                                            <td>2.2-2.6</td>
                                            <td>1.6-2.2</td>
                                            <td>1.6</td>
                                            <td>0.0</td>
                                        </tr>
                                        <tr>
                                            <th>Description</th>
                                            <td>Excellent</td>
                                            <td>Very Good</td>
                                            <td>Good</td>
                                            <td>Satisfactory</td>
                                            <td>Acceptable</td>
                                            <td>Partially Acceptable</td>
                                            <td>Borderline</td>
                                            <td>Not Graded</td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="footer">
                                    <div class="signature">
                                        <div class="signature-line"></div>
                                        <div class="signature-title">PREPARED BY</div>
                                        <div><?php echo $prepared_by; ?></div>
                                    </div>
                                    <div class="signature">
                                        <div class="signature-line"></div>
                                        <div class="signature-title">PRINCIPAL</div>
                                        <div>SCHOOL PRINCIPAL</div>
                                    </div>
                                </div>

                                <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #777;">
                                    <p><?php echo isset($settings['result_footer']) ? $settings['result_footer'] : 'This is a computer-generated document. No signature is required.'; ?></p>
                                    <p>Issue Date: <?php echo date('d-m-Y', strtotime($issue_date)); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.remove('-translate-x-full');
        });
        
        document.getElementById('close-sidebar').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });
        
        document.getElementById('sidebar-backdrop').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });
        
        // Filter functionality
        document.getElementById('reset-filters').addEventListener('click', function() {
            window.location.href = 'grade_sheet.php';
        });
        
        // Remove a specific filter
        function removeFilter(filterName) {
            // Create a new URL object
            const url = new URL(window.location.href);
            
            // Remove the specified parameter
            url.searchParams.delete(filterName);
            
            // If we're removing a filter that affects exams, also reset exam_id
            if (filterName === 'class_id' || filterName === 'academic_year' || filterName === 'exam_type') {
                url.searchParams.delete('exam_id');
            }
            
            // Keep the search_submitted parameter
            if (!url.searchParams.has('search_submitted')) {
                url.searchParams.set('search_submitted', '1');
            }
            
            // Redirect to the new URL
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
