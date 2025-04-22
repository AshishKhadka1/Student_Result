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

// Get subject and class IDs from URL
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Validate that teacher is assigned to this subject and class
if (!isTeacherAssignedToSubject($conn, $teacher_id, $subject_id, $class_id)) {
    $_SESSION['error'] = "You are not authorized to edit marks for this subject and class.";
    header("Location: teacher_dashboard.php");
    exit();
}

// Get subject details
$subject = getSubjectDetails($conn, $subject_id);

// Get class details
$class = getClassDetails($conn, $class_id);

// Get available exams for this class
$exams = getExamsForClass($conn, $class_id);

// Get students in this class
$students = getStudentsInClass($conn, $class_id);

// Get existing marks if exam is selected
$marks = [];
if ($exam_id > 0) {
    $marks = getStudentMarks($conn, $exam_id, $subject_id, $class_id);
}

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid form submission.";
    } else {
        // Process and save marks
        $result = saveStudentMarks($conn, $_POST, $teacher_id);
        
        if ($result['success']) {
            $success_message = "Marks saved successfully!";
            // Refresh marks data
            $marks = getStudentMarks($conn, $exam_id, $subject_id, $class_id);
            
            // Log activity
            logTeacherActivity($conn, $teacher_id, 'marks_update', "Updated marks for {$subject['subject_name']} - {$class['class_name']} {$class['section']}");
        } else {
            $error_message = "Error: " . $result['message'];
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Marks | Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
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
        .form-control-sm {
            min-width: 80px;
        }
        .grade-badge {
            min-width: 40px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/teacher_sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit Marks</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="teacher_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="assigned_subjects.php">Assigned Subjects</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit Marks</li>
                        </ol>
                    </nav>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Subject and Class Info Card -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="card-title"><?php echo htmlspecialchars($subject['subject_name']); ?> (<?php echo htmlspecialchars($subject['subject_code']); ?>)</h5>
                                <p class="text-muted mb-0">
                                    Class: <?php echo htmlspecialchars($class['class_name'] . ' ' . $class['section']); ?> | 
                                    Credit Hours: <?php echo htmlspecialchars($subject['credit_hours']); ?> | 
                                    Students: <?php echo count($students); ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-0">
                                    <span class="badge bg-primary">Theory: <?php echo $subject['theory_marks']; ?></span>
                                    <span class="badge bg-info">Practical: <?php echo $subject['practical_marks']; ?></span>
                                    <span class="badge bg-success">Total: <?php echo $subject['theory_marks'] + $subject['practical_marks']; ?></span>
                                </p>
                                <p class="text-muted mb-0">
                                    Pass Marks: Theory <?php echo $subject['theory_pass_marks']; ?>, 
                                    Practical <?php echo $subject['practical_pass_marks']; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exam Selection Form -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <form method="GET" action="edit_marks.php" class="row g-3">
                            <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                            
                            <div class="col-md-6">
                                <label for="exam_id" class="form-label">Select Exam</label>
                                <select class="form-select" id="exam_id" name="exam_id" required onchange="this.form.submit()">
                                    <option value="">-- Select Exam --</option>
                                    <?php foreach ($exams as $exam): ?>
                                        <option value="<?php echo $exam['exam_id']; ?>" <?php echo ($exam_id == $exam['exam_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($exam['exam_name'] . ' (' . $exam['exam_type'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 d-flex align-items-end">
                                <a href="class_performance.php?class_id=<?php echo $class_id; ?>&subject_id=<?php echo $subject_id; ?>" class="btn btn-outline-info">
                                    <i class="bi bi-bar-chart"></i> View Class Performance
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($exam_id > 0): ?>
                    <!-- Marks Entry Form -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Student Marks</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="calculateAllGrades">
                                        <i class="bi bi-calculator"></i> Calculate All Grades
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="edit_marks.php?subject_id=<?php echo $subject_id; ?>&class_id=<?php echo $class_id; ?>&exam_id=<?php echo $exam_id; ?>" id="marksForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="sticky-header">
                                            <tr class="table-light">
                                                <th rowspan="2" class="text-center align-middle">Roll No.</th>
                                                <th rowspan="2" class="align-middle">Student Name</th>
                                                <th colspan="3" class="text-center">Theory (<?php echo $subject['theory_marks']; ?>)</th>
                                                <th colspan="3" class="text-center">Practical (<?php echo $subject['practical_marks']; ?>)</th>
                                                <th colspan="3" class="text-center">Final</th>
                                            </tr>
                                            <tr class="table-light">
                                                <th class="text-center">Marks</th>
                                                <th class="text-center">Grade</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-center">Marks</th>
                                                <th class="text-center">Grade</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-center">Total</th>
                                                <th class="text-center">Grade</th>
                                                <th class="text-center">Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($students)): ?>
                                                <tr>
                                                    <td colspan="11" class="text-center">No students found in this class.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($students as $index => $student): ?>
                                                    <?php 
                                                        $student_id = $student['student_id'];
                                                        $student_marks = isset($marks[$student_id]) ? $marks[$student_id] : [
                                                            'theory_marks' => '',
                                                            'theory_grade' => '',
                                                            'practical_marks' => '',
                                                            'practical_grade' => '',
                                                            'total_marks' => '',
                                                            'final_grade' => '',
                                                            'remarks' => ''
                                                        ];
                                                    ?>
                                                    <tr>
                                                        <td class="text-center">
                                                            <?php echo htmlspecialchars($student['roll_number']); ?>
                                                            <input type="hidden" name="marks[<?php echo $student_id; ?>][student_id]" value="<?php echo $student_id; ?>">
                                                        </td>
                                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                        
                                                        <!-- Theory Marks -->
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm theory-marks" 
                                                                   name="marks[<?php echo $student_id; ?>][theory_marks]" 
                                                                   value="<?php echo htmlspecialchars($student_marks['theory_marks']); ?>" 
                                                                   min="0" max="<?php echo $subject['theory_marks']; ?>" step="0.01"
                                                                   data-student-id="<?php echo $student_id; ?>"
                                                                   data-row="<?php echo $index; ?>">
                                                        </td>
                                                        <td class="text-center theory-grade">
                                                            <span class="badge bg-secondary grade-badge">
                                                                <?php echo htmlspecialchars($student_marks['theory_grade'] ?: '-'); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center theory-status">
                                                            <?php 
                                                                $theory_status = '';
                                                                if (!empty($student_marks['theory_marks'])) {
                                                                    $theory_status = $student_marks['theory_marks'] >= $subject['theory_pass_marks'] ? 'Pass' : 'Fail';
                                                                    $theory_status_class = $theory_status == 'Pass' ? 'success' : 'danger';
                                                                    echo "<span class='badge bg-$theory_status_class'>{$theory_status}</span>";
                                                                } else {
                                                                    echo "<span class='badge bg-secondary'>-</span>";
                                                                }
                                                            ?>
                                                        </td>
                                                        
                                                        <!-- Practical Marks -->
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm practical-marks" 
                                                                   name="marks[<?php echo $student_id; ?>][practical_marks]" 
                                                                   value="<?php echo htmlspecialchars($student_marks['practical_marks']); ?>" 
                                                                   min="0" max="<?php echo $subject['practical_marks']; ?>" step="0.01"
                                                                   data-student-id="<?php echo $student_id; ?>"
                                                                   data-row="<?php echo $index; ?>">
                                                        </td>
                                                        <td class="text-center practical-grade">
                                                            <span class="badge bg-secondary grade-badge">
                                                                <?php echo htmlspecialchars($student_marks['practical_grade'] ?: '-'); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center practical-status">
                                                            <?php 
                                                                $practical_status = '';
                                                                if (!empty($student_marks['practical_marks'])) {
                                                                    $practical_status = $student_marks['practical_marks'] >= $subject['practical_pass_marks'] ? 'Pass' : 'Fail';
                                                                    $practical_status_class = $practical_status == 'Pass' ? 'success' : 'danger';
                                                                    echo "<span class='badge bg-$practical_status_class'>{$practical_status}</span>";
                                                                } else {
                                                                    echo "<span class='badge bg-secondary'>-</span>";
                                                                }
                                                            ?>
                                                        </td>
                                                        
                                                        <!-- Final Marks -->
                                                        <td class="text-center total-marks">
                                                            <span class="fw-bold">
                                                                <?php echo !empty($student_marks['total_marks']) ? htmlspecialchars($student_marks['total_marks']) : '-'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center final-grade">
                                                            <span class="badge bg-primary grade-badge">
                                                                <?php echo htmlspecialchars($student_marks['final_grade'] ?: '-'); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center remarks">
                                                            <?php echo htmlspecialchars($student_marks['remarks'] ?: '-'); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="assigned_subjects.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Back to Subjects
                                    </a>
                                    <button type="submit" name="save_marks" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Marks
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Grade Information Card -->
                    <div class="card mt-4 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Grade Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr class="table-light">
                                                    <th>Grade</th>
                                                    <th>Point</th>
                                                    <th>Percentage</th>
                                                    <th>Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td>A+</td><td>4.0</td><td>90% and above</td><td>Outstanding</td></tr>
                                                <tr><td>A</td><td>3.6</td><td>80-89%</td><td>Excellent</td></tr>
                                                <tr><td>B+</td><td>3.2</td><td>70-79%</td><td>Very Good</td></tr>
                                                <tr><td>B</td><td>2.8</td><td>60-69%</td><td>Good</td></tr>
                                                <tr><td>C+</td><td>2.4</td><td>50-59%</td><td>Satisfactory</td></tr>
                                                <tr><td>C</td><td>2.0</td><td>40-49%</td><td>Acceptable</td></tr>
                                                <tr><td>D+</td><td>1.6</td><td>30-39%</td><td>Partially Acceptable</td></tr>
                                                <tr><td>D</td><td>1.2</td><td>20-29%</td><td>Insufficient</td></tr>
                                                <tr><td>E</td><td>0.8</td><td>Below 20%</td><td>Very Insufficient</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Grading Instructions</h6>
                                        <hr>
                                        <ol class="mb-0">
                                            <li>Enter theory and practical marks for each student.</li>
                                            <li>Grades will be automatically calculated based on NEB guidelines.</li>
                                            <li>Students must pass both theory and practical components separately.</li>
                                            <li>Final grade is calculated as per the weightage defined by the admin.</li>
                                            <li>Click "Calculate All Grades" to update all grades at once.</li>
                                            <li>Don't forget to save your changes after entering marks.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif (!empty($exams)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Please select an exam from the dropdown above to edit marks.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> No exams have been scheduled for this class yet. Please contact the administrator.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Calculate grade based on marks and total marks
            function calculateGrade(marks, totalMarks) {
                if (marks === '' || marks === null) return '-';
                
                const percentage = (marks / totalMarks) * 100;
                
                if (percentage >= 90) return 'A+';
                if (percentage >= 80) return 'A';
                if (percentage >= 70) return 'B+';
                if (percentage >= 60) return 'B';
                if (percentage >= 50) return 'C+';
                if (percentage >= 40) return 'C';
                if (percentage >= 30) return 'D+';
                if (percentage >= 20) return 'D';
                return 'E';
            }
            
            // Get remarks based on grade
            function getRemarks(grade) {
                switch (grade) {
                    case 'A+': return 'Outstanding';
                    case 'A': return 'Excellent';
                    case 'B+': return 'Very Good';
                    case 'B': return 'Good';
                    case 'C+': return 'Satisfactory';
                    case 'C': return 'Acceptable';
                    case 'D+': return 'Partially Acceptable';
                    case 'D': return 'Insufficient';
                    case 'E': return 'Very Insufficient';
                    default: return '-';
                }
            }
            
            // Update a single row's grades and status
            function updateRowGrades(row) {
                const theoryMarksInput = row.querySelector('.theory-marks');
                const practicalMarksInput = row.querySelector('.practical-marks');
                
                if (!theoryMarksInput || !practicalMarksInput) return;
                
                const theoryMarks = parseFloat(theoryMarksInput.value) || 0;
                const practicalMarks = parseFloat(practicalMarksInput.value) || 0;
                const theoryTotal = <?php echo $subject['theory_marks']; ?>;
                const practicalTotal = <?php echo $subject['practical_marks']; ?>;
                const theoryPassMarks = <?php echo $subject['theory_pass_marks']; ?>;
                const practicalPassMarks = <?php echo $subject['practical_pass_marks']; ?>;
                
                // Calculate theory grade and status
                const theoryGrade = calculateGrade(theoryMarks, theoryTotal);
                const theoryStatus = theoryMarks >= theoryPassMarks ? 'Pass' : 'Fail';
                
                // Calculate practical grade and status
                const practicalGrade = calculateGrade(practicalMarks, practicalTotal);
                const practicalStatus = practicalMarks >= practicalPassMarks ? 'Pass' : 'Fail';
                
                // Calculate total and final grade
                const totalMarks = theoryMarks + practicalMarks;
                const totalPossible = theoryTotal + practicalTotal;
                const finalGrade = calculateGrade(totalMarks, totalPossible);
                const remarks = getRemarks(finalGrade);
                
                // Update the UI
                const theoryGradeCell = row.querySelector('.theory-grade');
                const theoryStatusCell = row.querySelector('.theory-status');
                const practicalGradeCell = row.querySelector('.practical-grade');
                const practicalStatusCell = row.querySelector('.practical-status');
                const totalMarksCell = row.querySelector('.total-marks');
                const finalGradeCell = row.querySelector('.final-grade');
                const remarksCell = row.querySelector('.remarks');
                
                if (theoryMarksInput.value) {
                    theoryGradeCell.innerHTML = `<span class="badge bg-secondary grade-badge">${theoryGrade}</span>`;
                    theoryStatusCell.innerHTML = `<span class="badge bg-${theoryStatus === 'Pass' ? 'success' : 'danger'}">${theoryStatus}</span>`;
                } else {
                    theoryGradeCell.innerHTML = `<span class="badge bg-secondary grade-badge">-</span>`;
                    theoryStatusCell.innerHTML = `<span class="badge bg-secondary">-</span>`;
                }
                
                if (practicalMarksInput.value) {
                    practicalGradeCell.innerHTML = `<span class="badge bg-secondary grade-badge">${practicalGrade}</span>`;
                    practicalStatusCell.innerHTML = `<span class="badge bg-${practicalStatus === 'Pass' ? 'success' : 'danger'}">${practicalStatus}</span>`;
                } else {
                    practicalGradeCell.innerHTML = `<span class="badge bg-secondary grade-badge">-</span>`;
                    practicalStatusCell.innerHTML = `<span class="badge bg-secondary">-</span>`;
                }
                
                if (theoryMarksInput.value && practicalMarksInput.value) {
                    totalMarksCell.innerHTML = `<span class="fw-bold">${totalMarks.toFixed(2)}</span>`;
                    finalGradeCell.innerHTML = `<span class="badge bg-primary grade-badge">${finalGrade}</span>`;
                    remarksCell.textContent = remarks;
                } else {
                    totalMarksCell.innerHTML = `<span class="fw-bold">-</span>`;
                    finalGradeCell.innerHTML = `<span class="badge bg-secondary grade-badge">-</span>`;
                    remarksCell.textContent = '-';
                }
            }
            
            // Add event listeners to all mark inputs
            document.querySelectorAll('.theory-marks, .practical-marks').forEach(input => {
                input.addEventListener('input', function() {
                    const row = this.closest('tr');
                    updateRowGrades(row);
                });
            });
            
            // Calculate all grades button
            document.getElementById('calculateAllGrades').addEventListener('click', function() {
                document.querySelectorAll('tbody tr').forEach(row => {
                    updateRowGrades(row);
                });
            });
        });
    </script>
</body>
</html>

