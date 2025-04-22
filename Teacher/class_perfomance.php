<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Include database connection and helper functions
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db_functions.php';

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$error_message = null;
$teacher = null;
$assigned_classes = [];
$selected_class_id = null;
$selected_subject_id = null;
$selected_exam_id = null;
$class_details = null;
$subject_details = null;
$exam_details = null;
$performance_stats = null;
$student_marks = [];
$grade_distribution = [];

try {
    // Get teacher details
    $teacher = getTeacherDetails($conn, $teacher_id);
    
    if (!$teacher) {
        throw new Exception("Teacher information not found");
    }
    
    // Get assigned classes
    $assigned_classes = getTeacherAssignedClasses($conn, $teacher_id);
    
    // Check if class_id is provided in the URL
    if (isset($_GET['class_id']) && !empty($_GET['class_id'])) {
        $selected_class_id = intval($_GET['class_id']);
        
        // Verify that the teacher is assigned to this class
        if (!isTeacherAssignedToClass($conn, $teacher_id, $selected_class_id)) {
            throw new Exception("You are not assigned to this class");
        }
        
        // Get class details
        $class_details = getClassDetails($conn, $selected_class_id);
        
        // Get subjects taught by the teacher in this class
        $assigned_subjects = getTeacherSubjectsInClass($conn, $teacher_id, $selected_class_id);
        
        // Get exams for this class
        $exams = getExamsForClass($conn, $selected_class_id);
        
        // Check if subject_id and exam_id are provided
        if (isset($_GET['subject_id']) && !empty($_GET['subject_id']) && 
            isset($_GET['exam_id']) && !empty($_GET['exam_id'])) {
            
            $selected_subject_id = intval($_GET['subject_id']);
            $selected_exam_id = intval($_GET['exam_id']);
            
            // Verify that the teacher is assigned to this subject
            if (!isTeacherAssignedToSubject($conn, $teacher_id, $selected_subject_id, $selected_class_id)) {
                throw new Exception("You are not assigned to this subject");
            }
            
            // Get subject details
            $subject_details = getSubjectDetails($conn, $selected_subject_id);
            
            // Get exam details
            $exam_details = getExamDetails($conn, $selected_exam_id);
            
            // Get performance statistics
            $performance_stats = getClassPerformanceStats($conn, $selected_class_id, $selected_subject_id, $selected_exam_id);
            
            // Get student marks
            $student_marks = getStudentMarksForPerformance($conn, $selected_class_id, $selected_subject_id, $selected_exam_id);
            
            // Get grade distribution
            $grade_distribution = getGradeDistribution($conn, $selected_class_id, $selected_subject_id, $selected_exam_id);
            
            // Log activity
            logTeacherActivity($conn, $teacher_id, 'view_performance', "Viewed performance for class ID: $selected_class_id, subject ID: $selected_subject_id, exam ID: $selected_exam_id");
        }
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Class Performance Error: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Performance | Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
            border-radius: 5px;
            padding: 8px 16px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
            border-radius: 10px;
            overflow: hidden;
        }
        .card-header {
            background-color: rgba(0, 0, 0, 0.03);
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            font-weight: 600;
        }
        .table th {
            font-weight: 600;
            background-color: rgba(0, 0, 0, 0.03);
        }
        .stat-card {
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <img src="<?php echo !empty($teacher['profile_image']) ? htmlspecialchars($teacher['profile_image']) : '../assets/images/default-teacher.png'; ?>" 
                             alt="Teacher Profile" class="img-fluid rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                        <h6 class="text-white"><?php echo isset($teacher['name']) ? htmlspecialchars($teacher['name']) : 'Teacher'; ?></h6>
                        <span class="badge bg-success">Teacher</span>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="teacher_dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="class_performance.php">
                                <i class="bi bi-bar-chart me-2"></i>  href="class_performance.php">
                                <i class="bi bi-bar-chart me-2"></i>
                                Class Performance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="edit_marks.php">
                                <i class="bi bi-pencil-square me-2"></i>
                                Edit Marks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person-circle me-2"></i>
                                My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../includes/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Class Performance</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="teacher_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Class Performance</li>
                        </ol>
                    </nav>
                </div>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Selection Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Select Class, Subject and Exam</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="get" class="row g-3">
                            <div class="col-md-4">
                                <label for="class_id" class="form-label">Class</label>
                                <select class="form-select" id="class_id" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($assigned_classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" <?php echo ($selected_class_id == $class['class_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="subject_id" class="form-label">Subject</label>
                                <select class="form-select" id="subject_id" name="subject_id" required <?php echo empty($selected_class_id) ? 'disabled' : ''; ?>>
                                    <option value="">Select Subject</option>
                                    <?php if ($selected_class_id && isset($assigned_subjects)): ?>
                                        <?php foreach ($assigned_subjects as $subject): ?>
                                            <option value="<?php echo $subject['subject_id']; ?>" <?php echo ($selected_subject_id == $subject['subject_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['subject_code'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="exam_id" class="form-label">Exam</label>
                                <select class="form-select" id="exam_id" name="exam_id" required <?php echo empty($selected_class_id) ? 'disabled' : ''; ?>>
                                    <option value="">Select Exam</option>
                                    <?php if ($selected_class_id && isset($exams)): ?>
                                        <?php foreach ($exams as $exam): ?>
                                            <option value="<?php echo $exam['exam_id']; ?>" <?php echo ($selected_exam_id == $exam['exam_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($exam['exam_name'] . ' (' . date('M Y', strtotime($exam['start_date'])) . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-2"></i> View Performance
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($selected_class_id && $selected_subject_id && $selected_exam_id && $performance_stats): ?>
                    <!-- Performance Overview -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title text-muted">Total Students</h6>
                                            <h2 class="mb-0"><?php echo $performance_stats['total_students']; ?></h2>
                                        </div>
                                        <div class="stat-icon bg-primary bg-opacity-10">
                                            <i class="bi bi-people text-primary fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title text-muted">Pass Percentage</h6>
                                            <h2 class="mb-0"><?php echo number_format($performance_stats['pass_percentage'], 1); ?>%</h2>
                                        </div>
                                        <div class="stat-icon bg-success bg-opacity-10">
                                            <i class="bi bi-check-circle text-success fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title text-muted">Average Marks</h6>
                                            <h2 class="mb-0"><?php echo number_format($performance_stats['average_marks'], 1); ?></h2>
                                        </div>
                                        <div class="stat-icon bg-info bg-opacity-10">
                                            <i class="bi bi-calculator text-info fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title text-muted">Highest Marks</h6>
                                            <h2 class="mb-0"><?php echo $performance_stats['highest_marks']; ?></h2>
                                            <small class="text-muted"><?php echo htmlspecialchars($performance_stats['highest_student_name']); ?></small>
                                        </div>
                                        <div class="stat-icon bg-warning bg-opacity-10">
                                            <i class="bi bi-trophy text-warning fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Grade Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="gradeDistributionChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Pass/Fail Ratio</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="passFailChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Marks Table -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Student Marks</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="printTable()">
                                <i class="bi bi-printer me-2"></i> Print
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="marksTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Roll No.</th>
                                            <th>Student Name</th>
                                            <th>Theory Marks</th>
                                            <th>Practical Marks</th>
                                            <th>Total Marks</th>
                                            <th>Percentage</th>
                                            <th>Grade</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($student_marks)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No marks data available.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($student_marks as $mark): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($mark['roll_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($mark['student_name']); ?></td>
                                                    <td><?php echo $mark['theory_marks']; ?></td>
                                                    <td><?php echo $mark['practical_marks']; ?></td>
                                                    <td><?php echo $mark['total_marks']; ?></td>
                                                    <td><?php echo number_format($mark['percentage'], 2); ?>%</td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($mark['final_grade'] == 'A+' || $mark['final_grade'] == 'A') ? 'success' : (($mark['final_grade'] == 'B+' || $mark['final_grade'] == 'B') ? 'info' : (($mark['final_grade'] == 'C+' || $mark['final_grade'] == 'C') ? 'warning' : 'danger')); ?>">
                                                            <?php echo $mark['final_grade']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($mark['status'] == 'pass') ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($mark['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        // Grade Distribution Chart
                        const gradeLabels = ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'E'];
                        const gradeData = [
                            <?php 
                                foreach ($grade_distribution as $grade => $count) {
                                    echo $count . ', ';
                                }
                            ?>
                        ];
                        
                        const gradeColors = [
                            '#28a745', '#20c997', '#17a2b8', '#0d6efd', 
                            '#6f42c1', '#fd7e14', '#ffc107', '#dc3545', '#6c757d'
                        ];
                        
                        const gradeCtx = document.getElementById('gradeDistributionChart').getContext('2d');
                        new Chart(gradeCtx, {
                            type: 'bar',
                            data: {
                                labels: gradeLabels,
                                datasets: [{
                                    label: 'Number of Students',
                                    data: gradeData,
                                    backgroundColor: gradeColors,
                                    borderColor: gradeColors,
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 1
                                        }
                                    }
                                }
                            }
                        });
                        
                        // Pass/Fail Chart
                        const passFailCtx = document.getElementById('passFailChart').getContext('2d');
                        new Chart(passFailCtx, {
                            type: 'pie',
                            data: {
                                labels: ['Pass', 'Fail'],
                                datasets: [{
                                    data: [
                                        <?php echo $performance_stats['pass_count']; ?>, 
                                        <?php echo $performance_stats['total_students'] - $performance_stats['pass_count']; ?>
                                    ],
                                    backgroundColor: ['#28a745', '#dc3545'],
                                    borderColor: ['#28a745', '#dc3545'],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }
                        });
                        
                        // Print function
                        function printTable() {
                            const printContents = document.getElementById('marksTable').outerHTML;
                            const originalContents = document.body.innerHTML;
                            
                            document.body.innerHTML = `
                                <div style="padding: 20px;">
                                    <h2 style="text-align: center; margin-bottom: 20px;">
                                        ${document.title} - Marks Report
                                    </h2>
                                    <div style="margin-bottom: 20px;">
                                        <p><strong>Class:</strong> <?php echo htmlspecialchars($class_details['class_name'] . ' ' . $class_details['section']); ?></p>
                                        <p><strong>Subject:</strong> <?php echo htmlspecialchars($subject_details['subject_name']); ?></p>
                                        <p><strong>Exam:</strong> <?php echo htmlspecialchars($exam_details['exam_name']); ?></p>
                                    </div>
                                    ${printContents}
                                </div>
                            `;
                            
                            window.print();
                            document.body.innerHTML = originalContents;
                        }
                    </script>
                <?php elseif ($selected_class_id && $selected_subject_id && $selected_exam_id): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> No performance data available for the selected class, subject, and exam.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle class selection change
            const classSelect = document.getElementById('class_id');
            const subjectSelect = document.getElementById('subject_id');
            const examSelect = document.getElementById('exam_id');
            
            classSelect.addEventListener('change', function() {
                if (this.value) {
                    // Enable subject select
                    subjectSelect.disabled = false;
                    examSelect.disabled = false;
                    
                    // Redirect to load subjects for this class
                    window.location.href = 'class_performance.php?class_id=' + this.value;
                } else {
                    // Disable subject and exam selects
                    subjectSelect.disabled = true;
                    examSelect.disabled = true;
                }
            });
        });
    </script>
</body>
</html>
