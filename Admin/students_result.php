<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user role and redirect if necessary
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// This page can be accessed by both admin and students
// For students, we show their own results
// For admin, we allow selection of student

$student_id = null;
$student_info = null;
$class_info = null;

if ($role == 'student') {
    // Get student information for the logged-in student
    $stmt = $conn->prepare("SELECT s.*, u.full_name, c.class_name, c.section, c.academic_year 
                           FROM students s 
                           JOIN users u ON s.user_id = u.user_id 
                           JOIN classes c ON s.class_id = c.class_id 
                           WHERE s.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student_info = $result->fetch_assoc();
        $student_id = $student_info['student_id'];
        $class_info = [
            'class_name' => $student_info['class_name'],
            'section' => $student_info['section'],
            'academic_year' => $student_info['academic_year']
        ];
    } else {
        // No student record found
        $_SESSION['error'] = "Student record not found. Please contact administrator.";
        if ($role == 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: ../Student/student_dashboard.php");
        }
        exit();
    }
    $stmt->close();
} else if ($role == 'admin') {
    // Admin can view any student's results
    $selected_student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
    
    if ($selected_student_id) {
        $stmt = $conn->prepare("SELECT s.*, u.full_name, c.class_name, c.section, c.academic_year 
                               FROM students s 
                               JOIN users u ON s.user_id = u.user_id 
                               JOIN classes c ON s.class_id = c.class_id 
                               WHERE s.student_id = ?");
        $stmt->bind_param("i", $selected_student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $student_info = $result->fetch_assoc();
            $student_id = $student_info['student_id'];
            $class_info = [
                'class_name' => $student_info['class_name'],
                'section' => $student_info['section'],
                'academic_year' => $student_info['academic_year']
            ];
        }
        $stmt->close();
    }
    
    // Get all students for admin selection
    $all_students = [];
    $result = $conn->query("SELECT s.student_id, s.roll_number, u.full_name, c.class_name, c.section 
                           FROM students s 
                           JOIN users u ON s.user_id = u.user_id 
                           JOIN classes c ON s.class_id = c.class_id 
                           ORDER BY c.class_name, c.section, s.roll_number");
    while ($row = $result->fetch_assoc()) {
        $all_students[] = $row;
    }
} else {
    // Unauthorized role
    header("Location: ../login.php");
    exit();
}

// Get exam types
$exam_types = [
    'first_terminal' => 'First Terminal Exam',
    'second_terminal' => 'Second Terminal Exam',
    'third_terminal' => 'Third Terminal Exam',
    'final_terminal' => 'Final Terminal Exam'
];

// Get selected exam type
$selected_exam = isset($_GET['exam_type']) ? $_GET['exam_type'] : 'final_terminal';

// Only proceed if we have a student selected
if ($student_id) {
    // Get all subjects for the class
    $subjects = [];
    $stmt = $conn->prepare("SELECT s.subject_id, s.subject_name 
                           FROM subjects s 
                           JOIN teachersubjects ts ON s.subject_id = ts.subject_id 
                           WHERE ts.academic_year = ?
                           ORDER BY s.subject_id");
    $stmt->bind_param("s", $student_info['academic_year']);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $subjects[] = [
            'code' => $row['subject_id'],
            'name' => $row['subject_name'],
            'credit' => 5 // Default credit value, adjust as needed
        ];
    }
    $stmt->close();

    // If no subjects found in teachersubjects, get all subjects
    if (empty($subjects)) {
        $result = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_id");
        while ($row = $result->fetch_assoc()) {
            $subjects[] = [
                'code' => $row['subject_id'],
                'name' => $row['subject_name'],
                'credit' => 5 // Default credit value, adjust as needed
            ];
        }
    }

    // Get exam results for each exam type
    $exam_results = [];
    foreach ($exam_types as $exam_type => $exam_name) {
        // Get exam ID for this exam type
        $stmt = $conn->prepare("SELECT exam_id FROM exams WHERE exam_type = ? AND class_id = ? ORDER BY created_at DESC LIMIT 1");
        $exam_type_db = str_replace('_', ' ', $exam_type);
        $stmt->bind_param("si", $exam_type_db, $student_info['class_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $exam_id = $result->fetch_assoc()['exam_id'];
            
            // Get results for this exam
            $stmt = $conn->prepare("SELECT r.subject_id, r.theory_marks, r.practical_marks, r.grade, r.gpa 
                                   FROM results r 
                                   WHERE r.student_id = ? AND r.exam_id = ?");
            $stmt->bind_param("si", $student_id, $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $subject_results = [];
            while ($row = $result->fetch_assoc()) {
                $subject_results[$row['subject_id']] = [
                    'theory_marks' => $row['theory_marks'],
                    'practical_marks' => $row['practical_marks'],
                    'grade' => $row['grade'],
                    'gpa' => $row['gpa']
                ];
            }
            
            // Get overall performance for this exam
            $stmt = $conn->prepare("SELECT sp.average_marks, sp.gpa, sp.rank 
                                   FROM student_performance sp 
                                   WHERE sp.student_id = ? AND sp.exam_id = ?");
            $stmt->bind_param("si", $student_id, $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $overall_performance = null;
            if ($result->num_rows > 0) {
                $overall_performance = $result->fetch_assoc();
            }
            
            $exam_results[$exam_type] = [
                'exam_id' => $exam_id,
                'exam_name' => $exam_name,
                'subject_results' => $subject_results,
                'overall_performance' => $overall_performance
            ];
        }
        $stmt->close();
    }
}

// Grade options for dropdowns
$grades = [
    'A+' => 4.0, 'A' => 3.6, 'B+' => 3.2, 
    'B' => 2.8, 'C+' => 2.4, 'C' => 2.0, 
    'D+' => 1.6, 'D' => 1.2, 'E' => 0.8
];

// Function to get grade letter from GPA
function getGradeLetter($gpa) {
    if ($gpa >= 3.6) return 'A+';
    if ($gpa >= 3.2) return 'A';
    if ($gpa >= 2.8) return 'B+';
    if ($gpa >= 2.4) return 'B';
    if ($gpa >= 2.0) return 'C+';
    if ($gpa >= 1.6) return 'C';
    if ($gpa >= 1.2) return 'D+';
    if ($gpa >= 0.8) return 'D';
    return 'E';
}

// Function to get remarks based on GPA
function getRemarks($gpa) {
    if ($gpa >= 3.6) return 'Excellent';
    if ($gpa >= 2.8) return 'Good';
    if ($gpa >= 2.0) return 'Satisfactory';
    if ($gpa >= 1.2) return 'Needs Improvement';
    return 'Poor';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Results | Result Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f5f5f5;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }
        .content {
            flex: 1;
            padding: 1.5rem;
            background-color: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            border-radius: 0.5rem;
            margin: 1rem;
        }
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .sticky-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 100;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
        }
        .result-card {
            transition: all 0.3s ease;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        .nav-pills .nav-link {
            color: #0d6efd;
            border-radius: 0.5rem;
            margin-right: 0.5rem;
            padding: 0.5rem 1rem;
        }
        .grade-badge {
            font-size: 1.2rem;
            padding: 0.5rem 1rem;
        }
        .student-info {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .print-header {
            display: none;
        }
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        .card-header {
            border-top-left-radius: 0.5rem !important;
            border-top-right-radius: 0.5rem !important;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-header {
                display: block;
                text-align: center;
                margin-bottom: 20px;
            }
            .wrapper {
                display: block;
            }
            .sidebar {
                display: none;
            }
            .content {
                width: 100%;
                padding: 0;
                margin: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
            }
            .card {
                border: none !important;
                box-shadow: none;
            }
            .card-header {
                background-color: white !important;
                color: black !important;
                border-bottom: 2px solid #dee2e6 !important;
            }
            .table {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- <?php if ($role == 'admin'): ?> -->
            <!-- Include Admin Sidebar -->
            <?php
             include('sidebar.php');
             ?>
        <?php endif; ?>

        <div class="content">
            <!-- Print Header (visible only when printing) -->
            <div class="print-header">
                <h2>Result Management System</h2>
                <h4>Student Result Sheet</h4>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?php echo ($role == 'admin') ? 'Student Results' : 'My Results'; ?></h4>
                    <div class="no-print">
                        <button class="btn btn-sm btn-light" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <?php if ($role == 'admin'): ?>
                            <a href="admin_dashboard.php" class="btn btn-sm btn-light ms-2">
                                <i class="bi bi-house"></i> Dashboard
                            </a>
                        <?php else: ?>
                            <a href="../Student/student_dashboard.php" class="btn btn-sm btn-light ms-2">
                                <i class="bi bi-house"></i> Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($role == 'admin'): ?>
                        <!-- Student Selection Form for Admin -->
                        <form action="students_result.php" method="GET" class="row g-3 mb-4 no-print">
                            <div class="col-md-6">
                                <label for="student_id" class="form-label">Select Student</label>
                                <select class="form-select" id="student_id" name="student_id" required onchange="this.form.submit()">
                                    <option value="">-- Select Student --</option>
                                    <?php foreach ($all_students as $student): ?>
                                        <option value="<?php echo $student['student_id']; ?>" <?php echo ($student_id == $student['student_id']) ? 'selected' : ''; ?>>
                                            <?php echo $student['roll_number'] . ' - ' . $student['full_name'] . ' (' . $student['class_name'] . ' ' . $student['section'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($student_id): ?>
                                <div class="col-md-4">
                                    <label for="exam_type" class="form-label">Select Exam</label>
                                    <select class="form-select" id="exam_type" name="exam_type" onchange="this.form.submit()">
                                        <?php foreach ($exam_types as $type => $name): ?>
                                            <option value="<?php echo $type; ?>" <?php echo ($selected_exam == $type) ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> View Results
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>

                    <?php if ($student_info): ?>
                        <!-- Student Information -->
                        <div class="student-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5><?php echo $student_info['full_name']; ?></h5>
                                    <p class="mb-1"><strong>Roll Number:</strong> <?php echo $student_info['roll_number']; ?></p>
                                    <p class="mb-1"><strong>Registration Number:</strong> <?php echo $student_info['registration_number']; ?></p>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <p class="mb-1"><strong>Class:</strong> <?php echo $class_info['class_name'] . ' ' . $class_info['section']; ?></p>
                                    <p class="mb-1"><strong>Academic Year:</strong> <?php echo $class_info['academic_year']; ?></p>
                                </div>
                            </div>
                        </div>

                        <?php if ($role == 'student'): ?>
                            <!-- Exam Navigation Tabs for Students -->
                            <ul class="nav nav-pills mb-4 no-print">
                                <?php foreach ($exam_types as $type => $name): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo ($selected_exam == $type) ? 'active' : ''; ?>" 
                                           href="students_result.php?exam_type=<?php echo $type; ?>">
                                            <?php echo $name; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (isset($exam_results[$selected_exam])): ?>
                            <!-- Exam Results -->
                            <h5 class="mb-3"><?php echo $exam_results[$selected_exam]['exam_name']; ?> Results</h5>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-4">
                                    <thead class="sticky-header">
                                        <tr class="table-light">
                                            <th class="text-center">CODE</th>
                                            <th>SUBJECT</th>
                                            <th class="text-center">CREDIT</th>
                                            <th class="text-center">THEORY</th>
                                            <th class="text-center">PRACTICAL</th>
                                            <th class="text-center">TOTAL</th>
                                            <th class="text-center">GRADE</th>
                                            <th class="text-center">GPA</th>
                                            <th class="text-center">REMARKS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_credits = 0;
                                        $total_grade_points = 0;
                                        $failed_subjects = 0;
                                        
                                        foreach ($subjects as $subject): 
                                            $subject_result = isset($exam_results[$selected_exam]['subject_results'][$subject['code']]) 
                                                ? $exam_results[$selected_exam]['subject_results'][$subject['code']] 
                                                : null;
                                            
                                            $theory_marks = $subject_result ? $subject_result['theory_marks'] : '-';
                                            $practical_marks = $subject_result ? $subject_result['practical_marks'] : '-';
                                            $total_marks = $subject_result ? ($theory_marks + $practical_marks) : '-';
                                            $grade = $subject_result ? $subject_result['grade'] : '-';
                                            $gpa = $subject_result ? $subject_result['gpa'] : 0;
                                            
                                            if ($subject_result) {
                                                $total_credits += $subject['credit'];
                                                $total_grade_points += ($gpa * $subject['credit']);
                                                
                                                if ($gpa < 1.2) {
                                                    $failed_subjects++;
                                                }
                                            }
                                            
                                            $remarks = $subject_result ? getRemarks($gpa) : '-';
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $subject['code']; ?></td>
                                            <td><?php echo $subject['name']; ?></td>
                                            <td class="text-center"><?php echo $subject['credit']; ?></td>
                                            <td class="text-center"><?php echo $theory_marks; ?></td>
                                            <td class="text-center"><?php echo $practical_marks; ?></td>
                                            <td class="text-center"><?php echo $total_marks; ?></td>
                                            <td class="text-center">
                                                <?php if ($grade != '-'): ?>
                                                    <span class="badge bg-primary"><?php echo $grade; ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?php echo $gpa > 0 ? number_format($gpa, 2) : '-'; ?></td>
                                            <td class="text-center"><?php echo $remarks; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Overall Performance -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Grade Information</h5>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Grade</th>
                                                            <th>Point</th>
                                                            <th>Percentage</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr><td>A+</td><td>4.0</td><td>90% and above</td></tr>
                                                        <tr><td>A</td><td>3.6</td><td>80-89%</td></tr>
                                                        <tr><td>B+</td><td>3.2</td><td>70-79%</td></tr>
                                                        <tr><td>B</td><td>2.8</td><td>60-69%</td></tr>
                                                        <tr><td>C+</td><td>2.4</td><td>50-59%</td></tr>
                                                        <tr><td>C</td><td>2.0</td><td>40-49%</td></tr>
                                                        <tr><td>D+</td><td>1.6</td><td>30-39%</td></tr>
                                                        <tr><td>D</td><td>1.2</td><td>20-29%</td></tr>
                                                        <tr><td>E</td><td>0.8</td><td>Below 20%</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Results Summary</h5>
                                            <?php
                                            $overall_gpa = $total_credits > 0 ? ($total_grade_points / $total_credits) : 0;
                                            $overall_gpa = number_format($overall_gpa, 2);
                                            
                                            $result_status = 'Incomplete';
                                            $result_class = 'bg-secondary';
                                            
                                            if ($total_credits > 0) {
                                                if ($failed_subjects > 0) {
                                                    $result_status = 'Fail';
                                                    $result_class = 'bg-danger';
                                                } else if ($overall_gpa >= 3.6) {
                                                    $result_status = 'Distinction';
                                                    $result_class = 'bg-success';
                                                } else if ($overall_gpa >= 3.2) {
                                                    $result_status = 'First Division';
                                                    $result_class = 'bg-success';
                                                } else if ($overall_gpa >= 2.8) {
                                                    $result_status = 'Second Division';
                                                    $result_class = 'bg-primary';
                                                } else if ($overall_gpa >= 2.0) {
                                                    $result_status = 'Pass';
                                                    $result_class = 'bg-info';
                                                } else {
                                                    $result_status = 'Fail';
                                                    $result_class = 'bg-danger';
                                                }
                                            }
                                            
                                            // Get rank if available
                                            $rank = isset($exam_results[$selected_exam]['overall_performance']['rank']) 
                                                ? $exam_results[$selected_exam]['overall_performance']['rank'] 
                                                : '-';
                                            ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="fw-bold">Grade Point Average (GPA):</span>
                                                <span class="badge bg-primary fs-6"><?php echo $overall_gpa; ?></span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="fw-bold">Final Result:</span>
                                                <span class="badge <?php echo $result_class; ?> fs-6"><?php echo $result_status; ?></span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold">Class Rank:</span>
                                                <span class="badge bg-dark fs-6"><?php echo $rank; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Progress Comparison (if multiple exams have results) -->
                            <?php
                            $has_multiple_exams = false;
                            $exam_gpas = [];
                            
                            foreach ($exam_results as $type => $exam) {
                                if (isset($exam['overall_performance']) && $exam['overall_performance']) {
                                    $has_multiple_exams = true;
                                    $exam_gpas[$type] = [
                                        'name' => $exam['exam_name'],
                                        'gpa' => $exam['overall_performance']['gpa']
                                    ];
                                }
                            }
                            
                            if ($has_multiple_exams && count($exam_gpas) > 1):
                            ?>
                            <div class="card mt-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Academic Progress</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <canvas id="progressChart" height="250"></canvas>
                                        </div>
                                        <div class="col-md-4">
                                            <h6 class="mb-3">GPA Comparison</h6>
                                            <ul class="list-group">
                                                <?php foreach ($exam_gpas as $type => $data): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?php echo $data['name']; ?>
                                                        <span class="badge bg-primary rounded-pill"><?php echo number_format($data['gpa'], 2); ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    
                                            ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                No results found for <?php echo $exam_types[$selected_exam]; ?>. Please select another exam or contact your administrator.
                            </div>
                        <?php endif; ?>
                    <?php elseif ($role == 'admin' && empty($student_id)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Please select a student to view their results.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($has_multiple_exams) && $has_multiple_exams && count($exam_gpas) > 1): ?>
            // Progress Chart
            const progressCtx = document.getElementById('progressChart').getContext('2d');
            const progressChart = new Chart(progressCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php 
                        foreach ($exam_gpas as $type => $data) {
                            echo "'" . $data['name'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'GPA Progress',
                        data: [
                            <?php 
                            foreach ($exam_gpas as $type => $data) {
                                echo $data['gpa'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 4,
                            title: {
                                display: true,
                                text: 'GPA'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Exams'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `GPA: ${context.parsed.y.toFixed(2)}`;
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
