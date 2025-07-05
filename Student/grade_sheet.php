<?php
// Start session for authentication check
session_start();

// Check if user is logged in as a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
   header("Location: ../login.php");
   exit();
}

// Connect to database with error handling
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
   die("Connection failed: " . $conn->connect_error);
}

// Enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Exact GPA Calculation Functions - SAME as view_grade_sheet.php
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

// Get student information
try {
   $stmt = $conn->prepare("
       SELECT s.student_id, s.roll_number, s.registration_number, u.full_name, 
              c.class_name, c.section, c.academic_year
       FROM students s
       JOIN users u ON s.user_id = u.user_id
       JOIN classes c ON s.class_id = c.class_id
       WHERE s.user_id = ?
   ");
   $stmt->bind_param("i", $user_id);
   $stmt->execute();
   $result = $stmt->get_result();

   if ($result->num_rows == 0) {
       die("Student record not found. Please contact administrator.");
   }

   $student = $result->fetch_assoc();
   $stmt->close();
} catch (Exception $e) {
   error_log("Error fetching student details: " . $e->getMessage());
   die("An error occurred while retrieving student information. Please try again later.");
}

// Define default table and publication settings
$results_table = 'results';
$publication_field = 'is_published';
$publication_condition = "AND (r.is_published = 1 OR r.status = 'published' OR e.results_published = 1)";

// Get available exams for this student - ONLY PUBLISHED RESULTS
$exams = [];
try {
    // Use a comprehensive query that checks multiple publication sources
    $query = "
        SELECT DISTINCT e.exam_id, e.exam_name, e.exam_type, e.academic_year, e.start_date, e.end_date
        FROM exams e
        JOIN results r ON e.exam_id = r.exam_id
        WHERE r.student_id = ? 
        AND (
            (r.is_published = 1) OR 
            (e.results_published = 1) OR 
            (r.status = 'published') OR
            (EXISTS (
                SELECT 1 FROM result_uploads ru 
                WHERE ru.exam_id = e.exam_id 
                AND ru.status = 'Published'
                AND r.upload_id = ru.id
            ))
        )
        ORDER BY e.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("s", $student['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // If exam_type is NULL or empty, use exam_name as the type
        if (empty($row['exam_type'])) {
            $row['exam_type'] = $row['exam_name'];
        }
        $exams[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching exams: " . $e->getMessage());
}

// Get available exam types for filtering - ONLY FROM PUBLISHED RESULTS
$exam_types = [];
try {
   // Check if exam_type column exists in exams table
   $check_column = $conn->query("SHOW COLUMNS FROM exams LIKE 'exam_type'");
   
   if ($check_column->num_rows > 0) {
       // Column exists, get distinct exam types from published results only
       $stmt = $conn->prepare("
           SELECT DISTINCT e.exam_type 
           FROM exams e
           JOIN $results_table r ON e.exam_id = r.exam_id
           WHERE r.student_id = ? 
           AND (
               (r.is_published = 1) OR 
               (e.results_published = 1) OR 
               (r.status = 'published')
           )
           AND e.exam_type IS NOT NULL 
           ORDER BY e.exam_type
       ");
       if ($stmt === false) {
           throw new Exception("Failed to prepare exam types statement: " . $conn->error);
       }
       
       $stmt->bind_param("s", $student['student_id']);
       $stmt->execute();
       $result = $stmt->get_result();
       
       while ($row = $result->fetch_assoc()) {
           if (!empty($row['exam_type'])) {
               $exam_types[] = $row['exam_type'];
           }
       }
       $stmt->close();
   } else {
       // Column doesn't exist, use exam names from published results instead
       $stmt = $conn->prepare("
           SELECT DISTINCT e.exam_name 
           FROM exams e
           JOIN $results_table r ON e.exam_id = r.exam_id
           WHERE r.student_id = ? 
           AND (
               (r.is_published = 1) OR 
               (e.results_published = 1) OR 
               (r.status = 'published')
           )
           ORDER BY e.exam_name
       ");
       if ($stmt === false) {
           throw new Exception("Failed to prepare exam names statement: " . $conn->error);
       }
       
       $stmt->bind_param("s", $student['student_id']);
       $stmt->execute();
       $result = $stmt->get_result();
       
       while ($row = $result->fetch_assoc()) {
           $exam_types[] = $row['exam_name'];
       }
       $stmt->close();
   }
} catch (Exception $e) {
   error_log("Error fetching exam types: " . $e->getMessage());
}

// If no exam types found, add some defaults (but only if there are published results)
if (empty($exam_types) && !empty($exams)) {
   $exam_types = ['Yearly Exam', 'Term Exam', 'Mid-Term Exam', 'Final Exam'];
}

// Get selected exam type from URL parameter
$selected_exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';

// Get selected academic year from URL parameter
$selected_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';

// Get all available academic years from published results
$academic_years = [];
foreach ($exams as $exam) {
   if (!in_array($exam['academic_year'], $academic_years)) {
       $academic_years[] = $exam['academic_year'];
   }
}
sort($academic_years);

// Filter exams based on selected filters
$filtered_exams = $exams;

if (!empty($selected_exam_type)) {
   $filtered_exams = array_filter($filtered_exams, function($exam) use ($selected_exam_type) {
       return $exam['exam_type'] == $selected_exam_type;
   });
}

if (!empty($selected_year)) {
   $filtered_exams = array_filter($filtered_exams, function($exam) use ($selected_year) {
       return $exam['academic_year'] == $selected_year;
   });
}

// Get student performance data for each exam - ONLY PUBLISHED RESULTS with ACCURATE calculations
$exam_performances = [];
try {
    foreach ($exams as $exam) {
        // Get results for this exam using the same logic as view_grade_sheet.php
        $stmt = $conn->prepare("
            SELECT r.*, s.subject_name, s.subject_code, s.full_marks_theory, s.full_marks_practical, s.credit_hours
            FROM $results_table r
            JOIN subjects s ON r.subject_id = s.subject_id
            WHERE r.student_id = ? AND r.exam_id = ?
            AND (
                (r.is_published = 1) OR 
                (r.status = 'published') OR
                (EXISTS (SELECT 1 FROM exams e WHERE e.exam_id = r.exam_id AND e.results_published = 1))
            )
            ORDER BY s.subject_id
        ");
        if ($stmt === false) {
            throw new Exception("Failed to prepare results statement: " . $conn->error);
        }
        
        $stmt->bind_param("si", $student['student_id'], $exam['exam_id']);
        $stmt->execute();
        $results_data = $stmt->get_result();

        if ($results_data->num_rows > 0) {
            $total_marks = 0;
            $total_subjects = 0;
            $max_marks = 0;
            $total_credit_hours = 0;
            $total_grade_points = 0;
            $failed_subjects = 0;

            while ($row = $results_data->fetch_assoc()) {
                $theory_marks = floatval($row['theory_marks'] ?? 0);
                $practical_marks = floatval($row['practical_marks'] ?? 0);
                $credit_hours = $row['credit_hours'] ?? 1;

                // Determine if subject has practical based on whether practical_marks > 0
                $has_practical = $practical_marks > 0;

                // Determine full marks based on whether practical exists
                $theory_full_marks = $has_practical ? 75 : 100;
                $practical_full_marks = $has_practical ? 25 : 0;
                $subject_full_marks = 100; // Total is always 100

                $subject_total_obtained = $theory_marks + $practical_marks;

                // Calculate using exact GPA functions - SAME as view_grade_sheet.php
                if ($has_practical) {
                    // Theory + Practical case (75 + 25)
                    $gpaResult = calculateTheoryPracticalGPA($theory_marks, $practical_marks, 75, 25);
                    if (isset($gpaResult['error'])) {
                        $final_gpa = 0.0;
                        $is_failed = true;
                    } else {
                        $theory_percentage = $gpaResult['theory']['percentage'];
                        $practical_percentage = $gpaResult['practical']['percentage'];
                        $final_gpa = $gpaResult['final_gpa'];

                        // Check for failure condition (either theory or practical below 35%)
                        $is_failed = ($theory_percentage < 35) || ($practical_percentage < 35);

                        if ($is_failed) {
                            $final_gpa = 0.0;
                        }
                    }
                } else {
                    // Theory only case (100 marks)
                    $gpaResult = calculateTheoryOnlyGPA($theory_marks, 100);
                    if (isset($gpaResult['error'])) {
                        $final_gpa = 0.0;
                        $is_failed = true;
                    } else {
                        $theory_percentage = $gpaResult['percentage'];
                        $final_gpa = $gpaResult['gpa'];

                        // Check for failure condition (theory below 35%)
                        $is_failed = ($theory_percentage < 35);

                        if ($is_failed) {
                            $final_gpa = 0.0;
                        }
                    }
                }

                // Count failed subjects
                if ($is_failed) {
                    $failed_subjects++;
                }

                $total_grade_points += ($final_gpa * $credit_hours);
                $total_credit_hours += $credit_hours;

                $total_marks += $subject_total_obtained;
                $total_subjects++;
                $max_marks += $subject_full_marks;
            }

            // Calculate overall GPA - exact same logic as view_grade_sheet.php
            $gpa = $total_credit_hours > 0 ? ($total_grade_points / $total_credit_hours) : 0;

            // Calculate percentage - exact same logic as view_grade_sheet.php
            $percentage = $max_marks > 0 ? ($total_marks / $max_marks) * 100 : 0;

            // Determine overall grade based on GPA
            $overall_grade = 'NG'; // Default

            if ($gpa >= 3.6 && $gpa <= 4.0) {
                $overall_grade = 'A+';
            } elseif ($gpa >= 3.2) {
                $overall_grade = 'A';
            } elseif ($gpa >= 2.8) {
                $overall_grade = 'B+';
            } elseif ($gpa >= 2.4) {
                $overall_grade = 'B';
            } elseif ($gpa >= 2.0) {
                $overall_grade = 'C+';
            } elseif ($gpa >= 1.6) {
                $overall_grade = 'C';
            } elseif ($gpa >= 1.2) {
                $overall_grade = 'D+';
            } elseif ($gpa >= 0.8) {
                $overall_grade = 'D';
            } else {
                $overall_grade = 'NG';
            }

            // Determine division
            if ($failed_subjects > 0) {
                $division = 'Fail';
            } elseif ($percentage >= 80) {
                $division = 'Distinction';
            } elseif ($percentage >= 60) {
                $division = 'First Division';
            } elseif ($percentage >= 45) {
                $division = 'Second Division';
            } elseif ($percentage >= 35) {
                $division = 'Third Division';
            } else {
                $division = 'Fail';
            }

            $exam_performances[$exam['exam_id']] = [
                'gpa' => round($gpa, 2),
                'percentage' => round($percentage, 2),
                'total_marks' => $total_marks,
                'max_marks' => $max_marks,
                'failed_subjects' => $failed_subjects,
                'overall_grade' => $overall_grade,
                'division' => $division,
                'rank' => 'N/A' // Will be updated if performance table exists
            ];
        }
        $stmt->close();
    }

    // Check if student_performance table exists and get rank data
    $check_table = $conn->query("SHOW TABLES LIKE 'student_performance'");
    
    if ($check_table->num_rows > 0) {
        foreach ($exams as $exam) {
            if (isset($exam_performances[$exam['exam_id']])) {
                $stmt = $conn->prepare("
                    SELECT sp.* FROM student_performance sp
                    JOIN results r ON sp.student_id = r.student_id AND sp.exam_id = r.exam_id
                    WHERE sp.student_id = ? AND sp.exam_id = ? 
                    AND (
                        (r.is_published = 1) OR 
                        (r.status = 'published') OR
                        (EXISTS (SELECT 1 FROM exams e WHERE e.exam_id = sp.exam_id AND e.results_published = 1))
                    )
                    LIMIT 1
                ");
                if ($stmt === false) {
                    throw new Exception("Failed to prepare performance statement: " . $conn->error);
                }
                
                $stmt->bind_param("si", $student['student_id'], $exam['exam_id']);
                $stmt->execute();
                $performance_result = $stmt->get_result();

                if ($performance_result->num_rows > 0) {
                    $performance = $performance_result->fetch_assoc();
                    $exam_performances[$exam['exam_id']]['rank'] = $performance['rank'] ?? 'N/A';
                }
                $stmt->close();
            }
        }
    }
} catch (Exception $e) {
    error_log("Error calculating exam performances: " . $e->getMessage());
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Grade Sheets | Result Management System</title>
   <link href="https://cdn.tailwindcss.com" rel="stylesheet">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <style>
       .filter-badge {
           display: inline-flex;
           align-items: center;
           padding: 0.25rem 0.5rem;
           border-radius: 9999px;
           font-size: 0.75rem;
           font-weight: 500;
           background-color: #e5e7eb;
           color: #374151;
           margin-right: 0.5rem;
           margin-bottom: 0.5rem;
       }
       
       .filter-badge button {
           margin-left: 0.25rem;
           color: #6b7280;
       }
       
       .filter-badge button:hover {
           color: #ef4444;
       }
       
       .exam-card {
           transition: all 0.3s ease;
       }
       
       .exam-card:hover {
           transform: translateY(-5px);
           box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
       }
       
       .status-badge {
           display: inline-flex;
           align-items: center;
           padding: 0.25rem 0.75rem;
           border-radius: 9999px;
           font-size: 0.75rem;
           font-weight: 500;
       }
       
       .status-badge.pass {
           background-color: #d1fae5;
           color: #065f46;
       }
       
       .status-badge.fail {
           background-color: #fee2e2;
           color: #b91c1c;
       }
       
       .status-badge i {
           margin-right: 0.25rem;
       }

       .published-badge {
           background-color: #d1fae5;
           color: #065f46;
           padding: 0.25rem 0.5rem;
           border-radius: 0.375rem;
           font-size: 0.75rem;
           font-weight: 500;
           display: inline-flex;
           align-items: center;
       }

       .published-badge i {
           margin-right: 0.25rem;
       }

       .debug-info {
           background-color: #f3f4f6;
           border: 1px solid #d1d5db;
           border-radius: 0.375rem;
           padding: 1rem;
           margin-bottom: 1rem;
           font-family: monospace;
           font-size: 0.875rem;
       }

       .grade-badge {
           display: inline-flex;
           align-items: center;
           padding: 0.25rem 0.5rem;
           border-radius: 0.375rem;
           font-size: 0.75rem;
           font-weight: 600;
           text-transform: uppercase;
       }

       .grade-a-plus { background-color: #dcfce7; color: #166534; }
       .grade-a { background-color: #dcfce7; color: #15803d; }
       .grade-b-plus { background-color: #fef3c7; color: #92400e; }
       .grade-b { background-color: #fef3c7; color: #a16207; }
       .grade-c-plus { background-color: #fed7aa; color: #c2410c; }
       .grade-c { background-color: #fed7aa; color: #ea580c; }
       .grade-d-plus { background-color: #fecaca; color: #dc2626; }
       .grade-d { background-color: #fecaca; color: #ef4444; }
       .grade-ng { background-color: #fee2e2; color: #b91c1c; }
       
       @media (max-width: 640px) {
           .filter-container {
               flex-direction: column;
               align-items: stretch;
           }
           
           .filter-container > div {
               margin-bottom: 1rem;
           }
       }
   </style>
</head>

<body class="bg-gray-100">
   <div class="flex h-screen overflow-hidden">
       <!-- Sidebar -->
       <?php include('./includes/student_sidebar.php'); ?>

       <!-- Mobile sidebar -->
       <div class="fixed inset-0 flex z-40 md:hidden transform -translate-x-full transition-transform duration-300 ease-in-out" id="mobile-sidebar">
           <div class="fixed inset-0 bg-gray-600 bg-opacity-75" id="sidebar-backdrop"></div>
           <div class="relative flex-1 flex flex-col max-w-xs w-full bg-gray-800">
               <div class="absolute top-0 right-0 -mr-12 pt-2">
                   <button class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" id="close-sidebar">
                       <span class="sr-only">Close sidebar</span>
                       <i class="fas fa-times text-white"></i>
                   </button>
               </div>
               <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
                   <div class="flex-shrink-0 flex items-center px-4">
                       <span class="text-white text-lg font-semibold">Result Management</span>
                   </div>
                   <nav class="mt-5 px-2 space-y-1">
                       <a href="student_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                           <i class="fas fa-tachometer-alt mr-3"></i>
                           Dashboard
                       </a>
                       <a href="grade_sheet.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                           <i class="fas fa-file-alt mr-3"></i>
                           Grade Sheets
                       </a>
                       <a href="../includes/logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                           <i class="fas fa-sign-out-alt mr-3"></i>
                           Logout
                       </a>
                   </nav>
               </div>
           </div>
       </div>

       <!-- Main Content -->
       <div class="flex flex-col flex-1 w-0 overflow-hidden">
           <!-- Top Navigation -->
           <?php include('./includes/top_navigation.php'); ?>

           <!-- Main Content Area -->
           <main class="flex-1 relative overflow-y-auto focus:outline-none">
               <div class="py-6">
                   <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                       <div class="flex justify-between items-center mb-6">
                           <h1 class="text-2xl font-bold text-gray-900">
                               <i class="fas fa-file-alt mr-2"></i> Published Grade Sheets
                           </h1>
                           <a href="student_dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                               <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                           </a>
                       </div>

                       <!-- Debug Information (Remove in production) -->
                       <?php if (isset($_GET['debug'])): ?>
                       <div class="debug-info">
                           <strong>Debug Information:</strong><br>
                           Results Table: <?php echo $results_table; ?><br>
                           Publication Field: <?php echo $publication_field; ?><br>
                           Publication Condition: <?php echo htmlspecialchars($publication_condition); ?><br>
                           Total Exams Found: <?php echo count($exams); ?><br>
                           Student ID: <?php echo $student['student_id']; ?>
                       </div>
                       <?php endif; ?>

                       <?php if (count($exams) > 0): ?>
                           <!-- Filters -->
                           <div class="bg-white shadow rounded-lg p-6 mb-6">
                               <h3 class="text-lg font-medium text-gray-900 mb-4">
                                   <i class="fas fa-filter mr-2"></i>Filter Published Results
                               </h3>
                               
                               <form action="" method="get" class="filter-container flex flex-wrap gap-4 mb-4">
                                   <!-- Exam Type Filter -->
                                   <?php if (!empty($exam_types)): ?>
                                   <div class="w-full sm:w-auto">
                                       <label for="exam_type" class="block text-sm font-medium text-gray-700 mb-1">Exam Type</label>
                                       <select id="exam_type" name="exam_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                           <option value="">All Exam Types</option>
                                           <?php foreach ($exam_types as $type): ?>
                                               <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $selected_exam_type === $type ? 'selected' : ''; ?>>
                                                   <?php echo htmlspecialchars($type); ?>
                                               </option>
                                           <?php endforeach; ?>
                                       </select>
                                   </div>
                                   <?php endif; ?>
                                   
                                   <!-- Academic Year Filter -->
                                   <?php if (!empty($academic_years)): ?>
                                   <div class="w-full sm:w-auto">
                                       <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                                       <select id="academic_year" name="academic_year" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                           <option value="">All Years</option>
                                           <?php foreach ($academic_years as $year): ?>
                                               <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $selected_year === $year ? 'selected' : ''; ?>>
                                                   <?php echo htmlspecialchars($year); ?>
                                               </option>
                                           <?php endforeach; ?>
                                       </select>
                                   </div>
                                   <?php endif; ?>
                                   
                                   <!-- Filter Button -->
                                   <div class="w-full sm:w-auto flex items-end">
                                       <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                           <i class="fas fa-filter mr-2"></i> Apply Filters
                                       </button>
                                   </div>
                                   
                                   <!-- Reset Filters -->
                                   <?php if (!empty($selected_exam_type) || !empty($selected_year)): ?>
                                       <div class="w-full sm:w-auto flex items-end">
                                           <a href="grade_sheet.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                               <i class="fas fa-times mr-2"></i> Reset Filters
                                           </a>
                                       </div>
                                   <?php endif; ?>
                               </form>
                               
                               <!-- Active Filters -->
                               <?php if (!empty($selected_exam_type) || !empty($selected_year)): ?>
                                   <div class="mt-4">
                                       <h4 class="text-sm font-medium text-gray-500 mb-2">Active Filters:</h4>
                                       <div>
                                           <?php if (!empty($selected_exam_type)): ?>
                                               <span class="filter-badge">
                                                   Exam Type: <?php echo htmlspecialchars($selected_exam_type); ?>
                                                   <a href="?<?php echo !empty($selected_year) ? 'academic_year=' . urlencode($selected_year) : ''; ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                                       <i class="fas fa-times-circle"></i>
                                                   </a>
                                               </span>
                                           <?php endif; ?>
                                           
                                           <?php if (!empty($selected_year)): ?>
                                               <span class="filter-badge">
                                                   Academic Year: <?php echo htmlspecialchars($selected_year); ?>
                                                   <a href="?<?php echo !empty($selected_exam_type) ? 'exam_type=' . urlencode($selected_exam_type) : ''; ?>" class="ml-1 text-gray-500 hover:text-red-500">
                                                       <i class="fas fa-times-circle"></i>
                                                   </a>
                                               </span>
                                           <?php endif; ?>
                                       </div>
                                   </div>
                               <?php endif; ?>
                           </div>
                           
                           <!-- Exam Results Grid -->
                           <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                               <?php if (empty($filtered_exams)): ?>
                                   <div class="col-span-full bg-white shadow rounded-lg p-6 text-center">
                                       <i class="fas fa-search text-gray-400 text-4xl mb-3"></i>
                                       <h3 class="text-lg font-medium text-gray-900 mb-1">No Published Results Found</h3>
                                       <p class="text-gray-500">No published exam results match your filter criteria. Try adjusting your filters or check back later for newly published results.</p>
                                       <a href="grade_sheet.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                           <i class="fas fa-list mr-2"></i> View All Published Results
                                       </a>
                                   </div>
                               <?php else: ?>
                                   <?php foreach ($filtered_exams as $exam): ?>
                                       <div class="bg-white shadow rounded-lg overflow-hidden exam-card">
                                           <div class="bg-blue-600 text-white px-4 py-3">
                                               <div class="flex justify-between items-center">
                                                   <h3 class="font-semibold"><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                                                   <span class="text-xs bg-blue-500 px-2 py-1 rounded-full">
                                                       <?php echo htmlspecialchars($exam['exam_type']); ?>
                                                   </span>
                                               </div>
                                           </div>
                                           
                                           <div class="p-4">
                                               <div class="flex justify-between items-center mb-3">
                                                   <span class="text-sm text-gray-500">
                                                       <i class="far fa-calendar-alt mr-1"></i> 
                                                       <?php echo htmlspecialchars($exam['academic_year']); ?>
                                                   </span>
                                                   
                                                   <!-- Published Status Badge -->
                                                   <span class="published-badge">
                                                       <i class="fas fa-check-circle"></i>
                                                       Published
                                                   </span>
                                               </div>
                                               
                                               <div class="space-y-2 mb-4">
                                                   <?php if (isset($exam['start_date']) && isset($exam['end_date'])): ?>
                                                       <div class="text-sm">
                                                           <span class="font-medium text-gray-700">Exam Period:</span>
                                                           <span class="text-gray-600">
                                                               <?php 
                                                               echo date('M d, Y', strtotime($exam['start_date'])); 
                                                               echo ' - '; 
                                                               echo date('M d, Y', strtotime($exam['end_date']));
                                                               ?>
                                                           </span>
                                                       </div>
                                                   <?php endif; ?>
                                                   
                                                   <?php if (isset($exam_performances[$exam['exam_id']])): ?>
                                                       <?php 
                                                       $performance = $exam_performances[$exam['exam_id']];
                                                       $percentage = $performance['percentage'];
                                                       $gpa = $performance['gpa'];
                                                       $overall_grade = $performance['overall_grade'];
                                                       $division = $performance['division'];
                                                       $failed_subjects = $performance['failed_subjects'];
                                                       $isPassed = ($failed_subjects == 0 && $percentage >= 35);
                                                       ?>
                                                       
                                                       <div class="flex justify-between items-center">
                                                           <span class="status-badge <?php echo $isPassed ? 'pass' : 'fail'; ?>">
                                                               <i class="fas <?php echo $isPassed ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                                               <?php echo $isPassed ? 'Pass' : 'Fail'; ?>
                                                           </span>
                                                           
                                                           <?php if ($isPassed): ?>
                                                               <span class="grade-badge grade-<?php echo strtolower(str_replace('+', '-plus', $overall_grade)); ?>">
                                                                   <?php echo $overall_grade; ?>
                                                               </span>
                                                           <?php endif; ?>
                                                       </div>
                                                       
                                                       <div class="text-sm">
                                                           <span class="font-medium text-gray-700">Percentage:</span>
                                                           <span class="text-gray-600"><?php echo number_format($percentage, 2); ?>%</span>
                                                       </div>
                                                       
                                                       <div class="text-sm">
                                                           <span class="font-medium text-gray-700">GPA:</span>
                                                           <span class="text-gray-600"><?php echo number_format($gpa, 2); ?> / 4.0</span>
                                                       </div>
                                                       
                                                       <div class="text-sm">
                                                           <span class="font-medium text-gray-700">Division:</span>
                                                           <span class="text-gray-600"><?php echo $division; ?></span>
                                                       </div>
                                                       
                                                       <?php if ($performance['rank'] !== 'N/A'): ?>
                                                           <div class="text-sm">
                                                               <span class="font-medium text-gray-700">Rank:</span>
                                                               <span class="text-gray-600"><?php echo htmlspecialchars($performance['rank']); ?></span>
                                                           </div>
                                                       <?php endif; ?>
                                                       
                                                       <div class="text-sm">
                                                           <span class="font-medium text-gray-700">Marks:</span>
                                                           <span class="text-gray-600">
                                                               <?php echo $performance['total_marks']; ?> / <?php echo $performance['max_marks']; ?>
                                                           </span>
                                                       </div>

                                                       <?php if ($failed_subjects > 0): ?>
                                                           <div class="text-sm">
                                                               <span class="font-medium text-red-700">Failed Subjects:</span>
                                                               <span class="text-red-600 font-semibold"><?php echo $failed_subjects; ?></span>
                                                           </div>
                                                       <?php endif; ?>
                                                   <?php endif; ?>
                                               </div>
                                               
                                               <div class="mt-4">
                                                   <a href="view_grade_sheet.php?exam_id=<?php echo $exam['exam_id']; ?>" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                       <i class="fas fa-eye mr-2"></i> View Detailed Result
                                                   </a>
                                               </div>
                                           </div>
                                       </div>
                                   <?php endforeach; ?>
                               <?php endif; ?>
                           </div>
                       <?php else: ?>
                           <!-- No published exams available -->
                           <div class="bg-white shadow rounded-lg p-8 text-center">
                               <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 text-yellow-500 mb-4">
                                   <i class="fas fa-clock text-3xl"></i>
                               </div>
                               <h2 class="text-xl font-medium text-gray-900 mb-2">No Published Results Available</h2>
                               <p class="text-gray-600 mb-6 max-w-md mx-auto">
                                   You don't have any published exam results available yet. Results will appear here once they are officially published by the administration.
                               </p>
                               <div class="space-y-2 text-sm text-gray-500 mb-6">
                                   <p><i class="fas fa-info-circle mr-1"></i> Results are published after review and approval</p>
                                   <p><i class="fas fa-bell mr-1"></i> You will be notified when new results are available</p>
                                   <p><i class="fas fa-database mr-1"></i> Checking table: <code><?php echo $results_table; ?></code></p>
                               </div>
                               <a href="student_dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                   <i class="fas fa-arrow-left mr-2"></i> Return to Dashboard
                               </a>
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
   </script>
</body>
</html>
