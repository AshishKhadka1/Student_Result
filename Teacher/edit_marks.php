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
$success_message = null;
$teacher = null;
$assigned_classes = [];
$assigned_subjects = [];
$selected_class_id = null;
$selected_subject_id = null;
$selected_exam_id = null;
$exams = [];
$students = [];
$student_marks = [];
$subject_details = null;

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
        
        // Get subjects taught by the teacher in this class
        $assigned_subjects = getTeacherSubjectsInClass($conn, $teacher_id, $selected_class_id);
        
        // Get exams for this class
        $exams = getExamsForClass($conn, $selected_class_id);
    } else {
        // If no class_id is provided, get all assigned subjects
        $assigned_subjects = getTeacherAssignedSubjects($conn, $teacher_id);
    }
    
    // Check if subject_id is provided in the URL
    if (isset($_GET['subject_id']) && !empty($_GET['subject_id'])) {
        $selected_subject_id = intval($_GET['subject_id']);
        
        // Verify that the teacher is assigned to this subject
        if ($selected_class_id && !isTeacherAssignedToSubject($conn, $teacher_id, $selected_subject_id, $selected_class_id)) {
            throw new Exception("You are not assigned to this subject");
        }
        
        // Get subject details
        $subject_details = getSubjectDetails($conn, $selected_subject_id);
        
        // If class_id is also provided, get students and their marks
        if ($selected_class_id) {
            // Get students in this class
            $students = getStudentsInClass($conn, $selected_class_id);
            
            // Check if exam_id is provided
            if (isset($_GET['exam_id']) && !empty($_GET['exam_id'])) {
                $selected_exam_id = intval($_GET['exam_id']);
                
                // Get student marks for this exam and subject
                $marks = getStudentMarks($conn, $selected_exam_id, $selected_subject_id, $selected_class_id);
                
                // Combine students with their marks
                foreach ($students as &$student) {
                    $student['marks'] = isset($marks[$student['student_id']]) ? $marks[$student['student_id']] : null;
                }
            }
        }
    }
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
        $result = saveStudentMarks($conn, $_POST, $teacher_id);
        
        if ($result['success']) {
            $success_message = $result['message'];
            
            // Log activity
            logTeacherActivity($conn, $teacher_id, 'marks_update', "Updated marks for subject ID: {$_POST['subject_id']}, exam ID: {$_POST['exam_id']}");
            
            // Redirect to avoid form resubmission
            header("Location: edit_marks.php?class_id={$_POST['class_id']}&subject_id={$_POST['subject_id']}&exam_id={$_POST['exam_id']}&success=1");
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
    
    // Check for success message in URL
    if (isset($_GET['success']) && $_GET['success'] == 1) {
        $success_message = "Marks saved successfully.";
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Edit Marks Error: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Marks | Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
        .form-control:focus, .form-select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        .marks-input {
            width: 80px;
        }
        .grade-badge {
            width: 30px;
            display: inline-block;
            text-align: center;
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
                            <a class="nav-link" href="class_performance.php">
                                <i class="bi bi-bar-chart me-2"></i>
                                Class Performance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="edit_marks.php">
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
                    <h1 class="h2">Edit Marks</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="teacher_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit Marks</li>
                        </ol>
                    </nav>
                </div>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
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
                                    <?php if ($selected_class_id): ?>
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
                                    <?php if ($selected_class_id): ?>
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
                                    <i class="bi bi-search me-2"></i> Load Students
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($selected_class_id && $selected_subject_id && $selected_exam_id && !empty($students)): ?>
                    <!-- Marks Entry Form -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                Enter Marks: 
                                <?php echo htmlspecialchars($subject_details['subject_name']); ?>
                            </h5>
                            <div>
                                <span class="badge bg-info">Theory: <?php echo $subject_details['full_marks_theory']; ?></span>
                                <span class="badge bg-info">Practical: <?php echo $subject_details['full_marks_practical']; ?></span>
                                <span class="badge bg-warning">Pass Marks (Theory): <?php echo $subject_details['theory_pass_marks']; ?></span>
                                <span class="badge bg-warning">Pass Marks (Practical): <?php echo $subject_details['practical_pass_marks']; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="" method="post">
                                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                                <input type="hidden" name="subject_id" value="<?php echo $selected_subject_id; ?>">
                                <input type="hidden" name="exam_id" value="<?php echo $selected_exam_id; ?>">
                                <input type="hidden" name="full_marks_theory" value="<?php echo $subject_details['full_marks_theory']; ?>">
                                <input type="hidden" name="full_marks_practical" value="<?php echo $subject_details['full_marks_practical']; ?>">
                                <input type="hidden" name="pass_marks_theory" value="<?php echo $subject_details['theory_pass_marks']; ?>">
                                <input type="hidden" name="pass_marks_practical" value="<?php echo $subject_details['practical_pass_marks']; ?>">
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Roll No.</th>
                                                <th>Student Name</th>
                                                <th>Theory Marks (<?php echo $subject_details['full_marks_theory']; ?>)</th>
                                                <th>Practical Marks (<?php echo $subject_details['full_marks_practical']; ?>)</th>
                                                <th>Total</th>
                                                <th>Grade</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm marks-input theory-marks" 
                                                               name="marks[<?php echo $student['student_id']; ?>][theory_marks]" 
                                                               value="<?php echo isset($student['marks']['theory_marks']) ? $student['marks']['theory_marks'] : ''; ?>"
                                                               min="0" max="<?php echo $subject_details['full_marks_theory']; ?>" step="0.01"
                                                               data-student-id="<?php echo $student['student_id']; ?>">
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm marks-input practical-marks" 
                                                               name="marks[<?php echo $student['student_id']; ?>][practical_marks]" 
                                                               value="<?php echo isset($student['marks']['practical_marks']) ? $student['marks']['practical_marks'] : ''; ?>"
                                                               min="0" max="<?php echo $subject_details['full_marks_practical']; ?>" step="0.01"
                                                               data-student-id="<?php echo $student['student_id']; ?>">
                                                    </td>
                                                    <td>
                                                        <span id="total-<?php echo $student['student_id']; ?>">
                                                            <?php 
                                                                if (isset($student['marks']['total_marks'])) {
                                                                    echo $student['marks']['total_marks'];
                                                                } else {
                                                                    echo '-';
                                                                }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span id="grade-<?php echo $student['student_id']; ?>" class="badge bg-secondary grade-badge">
                                                            <?php 
                                                                if (isset($student['marks']['final_grade'])) {
                                                                    echo $student['marks']['final_grade'];
                                                                } else {
                                                                    echo '-';
                                                                }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span id="status-<?php echo $student['student_id']; ?>" class="badge <?php echo (isset($student['marks']['status']) && $student['marks']['status'] == 'pass') ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php 
                                                                if (isset($student['marks']['status'])) {
                                                                    echo ucfirst($student['marks']['status']);
                                                                } else {
                                                                    echo '-';
                                                                }
                                                            ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                                    <button type="submit" name="save_marks" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i> Save Marks
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif ($selected_class_id && $selected_subject_id && $selected_exam_id): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> No students found in this class.
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
                    window.location.href = 'edit_marks.php?class_id=' + this.value;
                } else {
                    // Disable subject and exam selects
                    subjectSelect.disabled = true;
                    examSelect.disabled = true;
                }
            });
            
            // Calculate total marks and grade on input change
            const theoryInputs = document.querySelectorAll('.theory-marks');
            const practicalInputs = document.querySelectorAll('.practical-marks');
            
            function updateTotalAndGrade(studentId) {
                const theoryInput = document.querySelector(`.theory-marks[data-student-id="${studentId}"]`);
                const practicalInput = document.querySelector(`.practical-marks[data-student-id="${studentId}"]`);
                const totalSpan = document.getElementById(`total-${studentId}`);
                const gradeSpan = document.getElementById(`grade-${studentId}`);
                const statusSpan = document.getElementById(`status-${studentId}`);
                
                const theoryValue = parseFloat(theoryInput.value) || 0;
                const practicalValue = parseFloat(practicalInput.value) || 0;
                
                if (theoryValue === 0 && practicalValue === 0) {
                    totalSpan.textContent = '-';
                    gradeSpan.textContent = '-';
                    statusSpan.textContent = '-';
                    statusSpan.className = 'badge bg-secondary';
                    return;
                }
                
                const total = theoryValue + practicalValue;
                totalSpan.textContent = total.toFixed(2);
                
                // Simple grade calculation (this should match your server-side logic)
                const fullMarksTheory = <?php echo $subject_details ? $subject_details['full_marks_theory'] : 0; ?>;
                const fullMarksPractical = <?php echo $subject_details ? $subject_details['full_marks_practical'] : 0; ?>;
                const passMarksTheory = <?php echo $subject_details ? $subject_details['theory_pass_marks'] : 0; ?>;
                const passMarksPractical = <?php echo $subject_details ? $subject_details['practical_pass_marks'] : 0; ?>;
                
                const totalPossible = fullMarksTheory + fullMarksPractical;
                const percentage = (total / totalPossible) * 100;
                
                let grade;
                if (percentage >= 90) grade = 'A+';
                else if (percentage >= 80) grade = 'A';
                else if (percentage >= 70) grade = 'B+';
                else if (percentage >= 60) grade = 'B';
                else if (percentage >= 50) grade = 'C+';
                else if (percentage >= 40) grade = 'C';
                else if (percentage >= 30) grade = 'D+';
                else if (percentage >= 20) grade = 'D';
                else grade = 'E';
                
                gradeSpan.textContent = grade;
                
                // Determine pass/fail status
                const isPassed = theoryValue >= passMarksTheory && 
                                practicalValue >= passMarksPractical && 
                                grade !== 'E';
                
                statusSpan.textContent = isPassed ? 'Pass' : 'Fail';
                statusSpan.className = isPassed ? 'badge bg-success' : 'badge bg-danger';
            }
            
            theoryInputs.forEach(input => {
                input.addEventListener('input', function() {
                    updateTotalAndGrade(this.dataset.studentId);
                });
            });
            
            practicalInputs.forEach(input => {
                input.addEventListener('input', function() {
                    updateTotalAndGrade(this.dataset.studentId);
                });
            });
        });
    </script>
</body>
</html>
