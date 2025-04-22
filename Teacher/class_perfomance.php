<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/grade_calculator.php';

// Check if teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$teacher = getTeacherDetails($conn, $teacher_id);

// Get class ID from URL
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Validate that teacher is assigned to this class
if (!isTeacherAssignedToClass($conn, $teacher_id, $class_id)) {
    $_SESSION['error'] = "You are not authorized to view performance for this class.";
    header("Location: teacher_dashboard.php");
    exit();
}

// Get class details
$class = getClassDetails($conn, $class_id);

// Get subjects taught by this teacher in this class
$subjects = getTeacherSubjectsInClass($conn, $teacher_id, $class_id);

// If subject_id is not provided or invalid, use the first subject
if ($subject_id == 0 && !empty($subjects)) {
    $subject_id = $subjects[0]['subject_id'];
}

// Get subject details
$subject = getSubjectDetails($conn, $subject_id);

// Get available exams for this class
$exams = getExamsForClass($conn, $class_id);

// If exam_id is not provided or invalid, use the most recent exam
if ($exam_id == 0 && !empty($exams)) {
    $exam_id = $exams[0]['exam_id'];
}

// Get exam details
$exam = getExamDetails($conn, $exam_id);

// Get performance statistics
$performance = [];
$student_marks = [];
$grade_distribution = [];

if ($class_id > 0 && $subject_id > 0 && $exam_id > 0) {
    $performance = getClassPerformanceStats($conn, $class_id, $subject_id, $exam_id);
    $student_marks = getStudentMarksForPerformance($conn, $class_id, $subject_id, $exam_id);
    $grade_distribution = getGradeDistribution($conn, $class_id, $subject_id, $exam_id);
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Performance | Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/teacher_sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Class Performance</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="teacher_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="assigned_classes.php">Assigned Classes</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Class Performance</li>
                        </ol>
                    </nav>
                </div>

                <!-- Class Info Card -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="card-title"><?php echo htmlspecialchars($class['class_name'] . ' ' . $class['section']); ?></h5>
                                <p class="text-muted mb-0">
                                    Academic Year: <?php echo htmlspecialchars($class['academic_year']); ?> | 
                                    Students: <?php echo $class['student_count']; ?> | 
                                    Class Teacher: <?php echo htmlspecialchars($class['class_teacher_name']); ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <a href="edit_marks.php?class_id=<?php echo $class_id; ?>&subject_id=<?php echo $subject_id; ?>&exam_id=<?php echo $exam_id; ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil-square"></i> Edit Marks
                                </a>
                                <a href="print_result.php?class_id=<?php echo $class_id; ?>&subject_id=<?php echo $subject_id; ?>&exam_id=<?php echo $exam_id; ?>" class="btn btn-outline-secondary" target="_blank">
                                    <i class="bi bi-printer"></i> Print Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <form method="GET" action="class_performance.php" class="row g-3">
                            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                            
                            <div class="col-md-5">
                                <label for="subject_id" class="form-label">Subject</label>
                                <select class="form-select" id="subject_id" name="subject_id" required onchange="this.form.submit()">
                                    <?php foreach ($subjects as $sub): ?>
                                        <option value="<?php echo $sub['subject_id']; ?>" <?php echo ($subject_id == $sub['subject_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sub['subject_name'] . ' (' . $sub['subject_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-5">
                                <label for="exam_id" class="form-label">Exam</label>
                                <select class="form-select" id="exam_id" name="exam_id" required onchange="this.form.submit()">
                                    <?php foreach ($exams as $ex): ?>
                                        <option value="<?php echo $ex['exam_id']; ?>" <?php echo ($exam_id == $ex['exam_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ex['exam_name'] . ' (' . $ex['exam_type'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($performance)): ?>
                    <!-- Performance Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title text-muted">Average Marks</h6>
                                            <h2 class="mb-0"><?php echo number_format($performance['average_marks'], 2); ?></h2>
                                            <small class="text-muted">out of <?php echo $subject['theory_marks'] + $subject['practical_marks']; ?></small>
                                        </div>
                                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                                            <i class="bi bi-calculator text-primary fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title text-muted">Pass Rate</h6>
                                            <h2 class="mb-0"><?php echo number_format($performance['pass_percentage'], 1); ?>%</h2>
                                            <small class="text-muted"><?php echo $performance['pass_count']; ?> out of <?php echo $performance['total_students']; ?></small>
                                        </div>
                                        <div class="bg-success bg-opacity-10 p-3 rounded">
                                            <i class="bi bi-check-circle text-success fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title text-muted">Highest Marks</h6>
                                            <h2 class="mb-0"><?php echo number_format($performance['highest_marks'], 2); ?></h2>
                                            <small class="text-muted"><?php echo htmlspecialchars($performance['highest_student_name']); ?></small>
                                        </div>
                                        <div class="bg-info bg-opacity-10 p-3 rounded">
                                            <i class="bi bi-trophy text-info fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title text-muted">Lowest Marks</h6>
                                            <h2 class="mb-0"><?php echo number_format($performance['lowest_marks'], 2); ?></h2>
                                            <small class="text-muted"><?php echo htmlspecialchars($performance['lowest_student_name']); ?></small>
                                        </div>
                                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                                            <i class="bi bi-exclamation-triangle text-warning fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Grade Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="gradeDistributionChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Pass/Fail Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="passFailChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Student Marks Table -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Student Marks</h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary" id="exportCSV">
                                        <i class="bi bi-file-earmark-excel"></i> Export CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered" id="studentMarksTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Roll No.</th>
                                            <th>Student Name</th>
                                            <th class="text-center">Theory</th>
                                            <th class="text-center">Practical</th>
                                            <th class="text-center">Total</th>
                                            <th class="text-center">Grade</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($student_marks)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No student marks available.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($student_marks as $mark): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($mark['roll_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($mark['student_name']); ?></td>
                                                    <td class="text-center"><?php echo number_format($mark['theory_marks'], 2); ?></td>
                                                    <td class="text-center"><?php echo number_format($mark['practical_marks'], 2); ?></td>
                                                    <td class="text-center fw-bold"><?php echo number_format($mark['total_marks'], 2); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($mark['final_grade']); ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($mark['status'] == 'pass'): ?>
                                                            <span class="badge bg-success">Pass</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Fail</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No performance data available for the selected subject and exam. Please make sure marks have been entered.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Only initialize charts if performance data exists
            <?php if (!empty($performance) && !empty($grade_distribution)): ?>
            
            // Grade Distribution Chart
            const gradeDistributionCtx = document.getElementById('gradeDistributionChart').getContext('2d');
            const gradeDistributionChart = new Chart(gradeDistributionCtx, {
                type: 'bar',
                data: {
                    labels: ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'E'],
                    datasets: [{
                        label: 'Number of Students',
                        data: [
                            <?php echo $grade_distribution['A+'] ?? 0; ?>,
                            <?php echo $grade_distribution['A'] ?? 0; ?>,
                            <?php echo $grade_distribution['B+'] ?? 0; ?>,
                            <?php echo $grade_distribution['B'] ?? 0; ?>,
                            <?php echo $grade_distribution['C+'] ?? 0; ?>,
                            <?php echo $grade_distribution['C'] ?? 0; ?>,
                            <?php echo $grade_distribution['D+'] ?? 0; ?>,
                            <?php echo $grade_distribution['D'] ?? 0; ?>,
                            <?php echo $grade_distribution['E'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(255, 206, 86, 0.6)',
                            'rgba(255, 159, 64, 0.7)',
                            'rgba(255, 159, 64, 0.6)',
                            'rgba(255, 99, 132, 0.7)'
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(255, 159, 64, 1)',
                            'rgba(255, 159, 64, 1)',
                            'rgba(255, 99, 132, 1)'
                        ],
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
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Pass/Fail Chart
            const passFailCtx = document.getElementById('passFailChart').getContext('2d');
            const passFailChart = new Chart(passFailCtx, {
                type: 'pie',
                data: {
                    labels: ['Pass', 'Fail'],
                    datasets: [{
                        data: [
                            <?php echo $performance['pass_count']; ?>,
                            <?php echo $performance['total_students'] - $performance['pass_count']; ?>
                        ],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(255, 99, 132, 0.7)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 99, 132, 1)'
                        ],
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
            <?php endif; ?>
            
            // Export to CSV functionality
            document.getElementById('exportCSV')?.addEventListener('click', function() {
                const table = document.getElementById('studentMarksTable');
                if (!table) return;
                
                let csv = [];
                const rows = table.querySelectorAll('tr');
                
                for (const row of rows) {
                    const cols = row.querySelectorAll('td, th');
                    const rowData = Array.from(cols).map(col => {
                        // Get text content and remove any HTML
                        let content = col.textContent.trim();
                        // Escape double quotes
                        content = content.replace(/"/g, '""');
                        // Wrap with quotes to handle commas
                        return `"${content}"`;
                    });
                    csv.push(rowData.join(','));
                }
                
                const csvContent = csv.join('\n');
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                
                const link = document.createElement('a');
                link.setAttribute('href', url);
                link.setAttribute('download', 'student_marks.csv');
                link.style.visibility = 'hidden';
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
    </script>
</body>
</html>

