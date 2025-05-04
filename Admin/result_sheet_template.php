<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get exam types
$exam_types = [
    'first_terminal' => 'First Terminal Exam',
    'second_terminal' => 'Second Terminal Exam',
    'third_terminal' => 'Third Terminal Exam',
    'final_terminal' => 'Final Terminal Exam'
];

// Get class and exam information
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : '';

// Get classes for dropdown
$classes = [];
$result = $conn->query("SELECT class_id, class_name, section, academic_year FROM classes ORDER BY academic_year DESC, class_name, section");
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

// Get exams for dropdown
$exams = [];
if (!empty($class_id)) {
    $stmt = $conn->prepare("SELECT exam_id, exam_name, exam_type FROM exams WHERE class_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    $stmt->close();
}

// Get class information
$class_info = null;
if (!empty($class_id)) {
    $stmt = $conn->prepare("SELECT class_name, section, academic_year FROM classes WHERE class_id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $class_info = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get exam information
$exam_info = null;
if (!empty($exam_id)) {
    $stmt = $conn->prepare("SELECT exam_name, exam_type, start_date, end_date FROM exams WHERE exam_id = ?");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $exam_info = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get subjects for the class
$subjects = [];
if (!empty($class_id)) {
    // Get all subjects for the class
    $stmt = $conn->prepare("SELECT s.subject_id, s.subject_name 
                           FROM subjects s 
                           JOIN teachersubjects ts ON s.subject_id = ts.subject_id 
                           WHERE ts.academic_year = ?
                           ORDER BY s.subject_id");
    $stmt->bind_param("s", $class_info['academic_year']);
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
}

// Get students in the class
$students = [];
if (!empty($class_id) && !empty($exam_id)) {
    $stmt = $conn->prepare("SELECT s.student_id, s.roll_number, u.full_name 
                           FROM students s 
                           JOIN users u ON s.user_id = u.user_id 
                           WHERE s.class_id = ? 
                           ORDER BY s.roll_number");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $student_id = $row['student_id'];
        $student_results = [];
        
        // Get results for this student
        $stmt2 = $conn->prepare("SELECT r.subject_id, r.theory_marks, r.practical_marks, r.grade, r.gpa 
                               FROM results r 
                               WHERE r.student_id = ? AND r.exam_id = ?");
        $stmt2->bind_param("si", $student_id, $exam_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        while ($result_row = $result2->fetch_assoc()) {
            $student_results[$result_row['subject_id']] = [
                'theory_marks' => $result_row['theory_marks'],
                'practical_marks' => $result_row['practical_marks'],
                'grade' => $result_row['grade'],
                'gpa' => $result_row['gpa']
            ];
        }
        $stmt2->close();
        
        // Get overall performance
        $stmt2 = $conn->prepare("SELECT sp.average_marks, sp.gpa, sp.rank 
                               FROM student_performance sp 
                               WHERE sp.student_id = ? AND sp.exam_id = ?");
        $stmt2->bind_param("si", $student_id, $exam_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        $overall_performance = null;
        if ($result2->num_rows > 0) {
            $overall_performance = $result2->fetch_assoc();
        }
        $stmt2->close();
        
        $students[] = [
            'student_id' => $student_id,
            'roll_number' => $row['roll_number'],
            'full_name' => $row['full_name'],
            'results' => $student_results,
            'overall_performance' => $overall_performance
        ];
    }
    $stmt->close();
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
    <title>Result Sheet Template | Result Management System</title>
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
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #343a40 0%, #212529 100%);
            color: #fff;
            min-height: 100vh;
            position: sticky;
            top: 0;
            padding-top: 1rem;
            transition: all 0.3s;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar-header {
            padding: 0.875rem 1.25rem;
            font-size: 1.2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
        }
        .sidebar-menu {
            padding: 0;
            list-style: none;
            margin-bottom: 0;
        }
        .sidebar-menu li {
            margin: 0;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-left: 3px solid #0d6efd;
        }
        .sidebar-menu a.active {
            background-color: #0d6efd;
            color: #fff;
            border-left: 3px solid #fff;
        }
        .sidebar-menu i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }
        .content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: auto;
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
        .grade-select {
            min-width: 80px;
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
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            .sidebar .sidebar-header {
                text-align: center;
                padding: 0.875rem 0;
            }
            .sidebar .sidebar-header span {
                display: none;
            }
            .sidebar .sidebar-menu a {
                padding: 0.75rem 0;
                justify-content: center;
            }
            .sidebar .sidebar-menu i {
                margin-right: 0;
                font-size: 1.3rem;
            }
            .sidebar .sidebar-menu span {
                display: none;
            }
            .content {
                padding: 1rem;
                margin: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        <!-- Content -->
        <div class="content">
            <!-- Print Header (visible only when printing) -->
            <div class="print-header">
                <h2>Result Management System</h2>
                <h4><?php echo $exam_info ? $exam_info['exam_name'] : 'Exam'; ?> Result Sheet</h4>
                <?php if ($class_info): ?>
                    <p>Class: <?php echo $class_info['class_name'] . ' ' . $class_info['section']; ?> | Academic Year: <?php echo $class_info['academic_year']; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Result Sheet Template</h4>
                    <div class="no-print">
                        <button class="btn btn-sm btn-light" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <a href="admin_dashboard.php" class="btn btn-sm btn-light ms-2 d-md-inline-block d-none">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Class and Exam Selection Form -->
                    <form action="result_sheet_template.php" method="GET" class="row g-3 mb-4 no-print">
                        <div class="col-md-5">
                            <label for="class_id" class="form-label">Select Class</label>
                            <select class="form-select" id="class_id" name="class_id" required onchange="this.form.submit()">
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_id == $class['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="exam_id" class="form-label">Select Exam</label>
                            <select class="form-select" id="exam_id" name="exam_id" <?php echo empty($class_id) ? 'disabled' : ''; ?> onchange="this.form.submit()">
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['exam_id']; ?>" <?php echo ($exam_id == $exam['exam_id']) ? 'selected' : ''; ?>>
                                        <?php echo $exam['exam_name'] . ' (' . ucfirst($exam['exam_type']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> View Results
                            </button>
                        </div>
                    </form>

                    <?php if ($class_info && $exam_info): ?>
                        <!-- Class and Exam Information -->
                        <div class="alert alert-info mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5><?php echo $class_info['class_name'] . ' ' . $class_info['section']; ?></h5>
                                    <p class="mb-1"><strong>Academic Year:</strong> <?php echo $class_info['academic_year']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h5><?php echo $exam_info['exam_name']; ?></h5>
                                    <p class="mb-1"><strong>Exam Type:</strong> <?php echo ucfirst($exam_info['exam_type']); ?></p>
                                    <?php if ($exam_info['start_date'] && $exam_info['end_date']): ?>
                                        <p class="mb-1"><strong>Exam Period:</strong> <?php echo date('M d, Y', strtotime($exam_info['start_date'])) . ' to ' . date('M d, Y', strtotime($exam_info['end_date'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($students)): ?>
                            <!-- Results Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-4">
                                    <thead class="sticky-header">
                                        <tr class="table-light">
                                            <th rowspan="2" class="text-center align-middle">Roll No.</th>
                                            <th rowspan="2" class="align-middle">Student Name</th>
                                            <?php foreach ($subjects as $subject): ?>
                                                <th colspan="3" class="text-center"><?php echo $subject['name']; ?></th>
                                            <?php endforeach; ?>
                                            <th rowspan="2" class="text-center align-middle">GPA</th>
                                            <th rowspan="2" class="text-center align-middle">Rank</th>
                                            <th rowspan="2" class="text-center align-middle">Result</th>
                                        </tr>
                                        <tr class="table-light">
                                            <?php foreach ($subjects as $subject): ?>
                                                <th class="text-center">Theory</th>
                                                <th class="text-center">Practical</th>
                                                <th class="text-center">Grade</th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td class="text-center"><?php echo $student['roll_number']; ?></td>
                                                <td><?php echo $student['full_name']; ?></td>
                                                
                                                <?php foreach ($subjects as $subject): 
                                                    $subject_result = isset($student['results'][$subject['code']]) 
                                                        ? $student['results'][$subject['code']] 
                                                        : null;
                                                    
                                                    $theory_marks = $subject_result ? $subject_result['theory_marks'] : '-';
                                                    $practical_marks = $subject_result ? $subject_result['practical_marks'] : '-';
                                                    $grade = $subject_result ? $subject_result['grade'] : '-';
                                                ?>
                                                    <td class="text-center"><?php echo $theory_marks; ?></td>
                                                    <td class="text-center"><?php echo $practical_marks; ?></td>
                                                    <td class="text-center">
                                                        <?php if ($grade != '-'): ?>
                                                            <span class="badge bg-primary"><?php echo $grade; ?></span>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                
                                                <td class="text-center">
                                                    <?php 
                                                    echo isset($student['overall_performance']['gpa']) 
                                                        ? number_format($student['overall_performance']['gpa'], 2) 
                                                        : '-'; 
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php 
                                                    echo isset($student['overall_performance']['rank']) 
                                                        ? $student['overall_performance']['rank'] 
                                                        : '-'; 
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php 
                                                    if (isset($student['overall_performance']['gpa'])) {
                                                        $gpa = $student['overall_performance']['gpa'];
                                                        $result_status = '';
                                                        $result_class = '';
                                                        
                                                        if ($gpa >= 3.6) {
                                                            $result_status = 'Distinction';
                                                            $result_class = 'bg-success';
                                                        } else if ($gpa >= 3.2) {
                                                            $result_status = 'First';
                                                            $result_class = 'bg-success';
                                                        } else if ($gpa >= 2.8) {
                                                            $result_status = 'Second';
                                                            $result_class = 'bg-primary';
                                                        } else if ($gpa >= 2.0) {
                                                            $result_status = 'Pass';
                                                            $result_class = 'bg-info';
                                                        } else {
                                                            $result_status = 'Fail';
                                                            $result_class = 'bg-danger';
                                                        }
                                                        
                                                        echo '<span class="badge ' . $result_class . '">' . $result_status . '</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Class Statistics -->
                            <div class="card mt-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Class Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php
                                        // Calculate class statistics
                                        $total_students = count($students);
                                        $passed_students = 0;
                                        $failed_students = 0;
                                        $distinction_students = 0;
                                        $first_div_students = 0;
                                        $second_div_students = 0;
                                        $pass_students = 0;
                                        $total_gpa = 0;
                                        
                                        foreach ($students as $student) {
                                            if (isset($student['overall_performance']['gpa'])) {
                                                $gpa = $student['overall_performance']['gpa'];
                                                $total_gpa += $gpa;
                                                
                                                if ($gpa >= 2.0) {
                                                    $passed_students++;
                                                    
                                                    if ($gpa >= 3.6) {
                                                        $distinction_students++;
                                                    } else if ($gpa >= 3.2) {
                                                        $first_div_students++;
                                                    } else if ($gpa >= 2.8) {
                                                        $second_div_students++;
                                                    } else {
                                                        $pass_students++;
                                                    }
                                                } else {
                                                    $failed_students++;
                                                }
                                            }
                                        }
                                        
                                        $avg_gpa = $total_students > 0 ? $total_gpa / $total_students : 0;
                                        $pass_percentage = $total_students > 0 ? ($passed_students / $total_students) * 100 : 0;
                                        ?>
                                        
                                        <div class="col-md-3 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title">Total Students</h6>
                                                    <h2 class="mb-0"><?php echo $total_students; ?></h2>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <div class="card bg-success text-white">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title">Passed</h6>
                                                    <h2 class="mb-0"><?php echo $passed_students; ?></h2>
                                                    <small><?php echo number_format($pass_percentage, 1); ?>%</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <div class="card bg-danger text-white">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title">Failed</h6>
                                                    <h2 class="mb-0"><?php echo $failed_students; ?></h2>
                                                    <small><?php echo number_format(100 - $pass_percentage, 1); ?>%</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <div class="card bg-primary text-white">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title">Average GPA</h6>
                                                    <h2 class="mb-0"><?php echo number_format($avg_gpa, 2); ?></h2>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <h6>Result Distribution</h6>
                                            <table class="table table-sm table-bordered">
                                                <thead>
                                                    <tr class="table-light">
                                                        <th>Result</th>
                                                        <th class="text-center">Count</th>
                                                        <th class="text-center">Percentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>Distinction (GPA ≥ 3.6)</td>
                                                        <td class="text-center"><?php echo $distinction_students; ?></td>
                                                        <td class="text-center"><?php echo $total_students > 0 ? number_format(($distinction_students / $total_students) * 100, 1) : 0; ?>%</td>
                                                    </tr>
                                                    <tr>
                                                        <td>First Division (GPA 3.2-3.59)</td>
                                                        <td class="text-center"><?php echo $first_div_students; ?></td>
                                                        <td class="text-center"><?php echo $total_students > 0 ? number_format(($first_div_students / $total_students) * 100, 1) : 0; ?>%</td>
                                                    </tr>
                                                    <tr>
                                                        <td>Second Division (GPA 2.8-3.19)</td>
                                                        <td class="text-center"><?php echo $second_div_students; ?></td>
                                                        <td class="text-center"><?php echo $total_students > 0 ? number_format(($second_div_students / $total_students) * 100, 1) : 0; ?>%</td>
                                                    </tr>
                                                    <tr>
                                                        <td>Pass (GPA 2.0-2.79)</td>
                                                        <td class="text-center"><?php echo $pass_students; ?></td>
                                                        <td class="text-center"><?php echo $total_students > 0 ? number_format(($pass_students / $total_students) * 100, 1) : 0; ?>%</td>
                                                    </tr>
                                                    <tr>
                                                        <td>Fail (GPA < 2.0)</td>
                                                        <td class="text-center"><?php echo $failed_students; ?></td>
                                                        <td class="text-center"><?php echo $total_students > 0 ? number_format(($failed_students / $total_students) * 100, 1) : 0; ?>%</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6>Top 5 Students</h6>
                                            <table class="table table-sm table-bordered">
                                                <thead>
                                                    <tr class="table-light">
                                                        <th>Rank</th>
                                                        <th>Name</th>
                                                        <th class="text-center">Roll No.</th>
                                                        <th class="text-center">GPA</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    // Sort students by GPA
                                                    usort($students, function($a, $b) {
                                                        $a_gpa = isset($a['overall_performance']['gpa']) ? $a['overall_performance']['gpa'] : 0;
                                                        $b_gpa = isset($b['overall_performance']['gpa']) ? $b['overall_performance']['gpa'] : 0;
                                                        return $b_gpa <=> $a_gpa;
                                                    });
                                                    
                                                    // Display top 5 students
                                                    $top_students = array_slice($students, 0, 5);
                                                    $rank = 1;
                                                    
                                                    foreach ($top_students as $student):
                                                        $gpa = isset($student['overall_performance']['gpa']) ? $student['overall_performance']['gpa'] : 0;
                                                    ?>
                                                    <tr>
                                                        <td class  : 0;
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?php echo $rank++; ?></td>
                                                        <td><?php echo $student['full_name']; ?></td>
                                                        <td class="text-center"><?php echo $student['roll_number']; ?></td>
                                                        <td class="text-center"><?php echo number_format($gpa, 2); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                No students found for this class and exam. Please make sure results have been entered.
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Please select a class and exam to view the result sheet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
