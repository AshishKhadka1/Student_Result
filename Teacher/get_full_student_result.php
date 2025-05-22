<?php
session_start();
include '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p class="font-bold">Error</p>
            <p>Unauthorized access. Please log in as a teacher.</p>
          </div>';
    exit();
}

// Get parameters
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

// Validate required parameters
if (!$class_id || !$exam_id || !$student_id) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p class="font-bold">Error</p>
            <p>Missing required parameters.</p>
          </div>';
    exit();
}

// Get student details
$student_query = "SELECT s.student_id, s.roll_number, s.batch_year, u.full_name, u.email, u.phone,
                  c.class_name, c.section, c.academic_year
                 FROM students s
                 JOIN users u ON s.user_id = u.user_id
                 JOIN classes c ON s.class_id = c.class_id
                 WHERE s.student_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p class="font-bold">Error</p>
            <p>Student not found.</p>
          </div>';
    exit();
}

// Get exam details
$exam_query = "SELECT exam_name, exam_type, start_date, end_date FROM exams WHERE exam_id = ?";
$stmt = $conn->prepare($exam_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();
$exam = $result->fetch_assoc();
$stmt->close();

if (!$exam) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p class="font-bold">Error</p>
            <p>Exam not found.</p>
          </div>';
    exit();
}

// Get student results for all subjects
$results_query = "SELECT r.result_id, r.theory_marks, r.practical_marks, r.total_marks, 
                  r.percentage, r.grade, r.gpa, r.remarks,
                  s.subject_id, s.subject_name, s.subject_code, s.full_marks, s.pass_marks
                 FROM results r
                 JOIN subjects s ON r.subject_id = s.subject_id
                 WHERE r.student_id = ? AND r.exam_id = ?
                 ORDER BY s.subject_name";
$stmt = $conn->prepare($results_query);
$stmt->bind_param("ii", $student_id, $exam_id);
$stmt->execute();
$results_data = $stmt->get_result();
$results = [];
while ($row = $results_data->fetch_assoc()) {
    $results[] = $row;
}
$stmt->close();

// Calculate overall statistics
$total_subjects = count($results);
$total_marks = 0;
$obtained_marks = 0;
$total_gpa = 0;
$pass_count = 0;
$fail_count = 0;

foreach ($results as $result) {
    $total_marks += $result['full_marks'];
    $obtained_marks += $result['total_marks'];
    $total_gpa += $result['gpa'];
    
    if ($result['remarks'] == 'Pass') {
        $pass_count++;
    } else {
        $fail_count++;
    }
}

$overall_percentage = $total_marks > 0 ? ($obtained_marks / $total_marks) * 100 : 0;
$average_gpa = $total_subjects > 0 ? $total_gpa / $total_subjects : 0;
$overall_status = $fail_count > 0 ? 'Fail' : 'Pass';

// Determine overall grade
$overall_grade = '';
if ($overall_percentage >= 90) {
    $overall_grade = 'A+';
} elseif ($overall_percentage >= 80) {
    $overall_grade = 'A';
} elseif ($overall_percentage >= 70) {
    $overall_grade = 'B+';
} elseif ($overall_percentage >= 60) {
    $overall_grade = 'B';
} elseif ($overall_percentage >= 50) {
    $overall_grade = 'C+';
} elseif ($overall_percentage >= 40) {
    $overall_grade = 'C';
} elseif ($overall_percentage >= 33) {
    $overall_grade = 'D';
} else {
    $overall_grade = 'F';
}

$conn->close();
?>

<div class="p-4">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">Student Result</h2>
        <button onclick="closeResultsModal()" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- Student and Exam Info -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($student['full_name']); ?></h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Roll Number</p>
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($student['roll_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Class</p>
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Batch Year</p>
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($student['batch_year']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Academic Year</p>
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($student['academic_year']); ?></p>
                    </div>
                </div>
            </div>
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Exam Type</p>
                        <p class="text-sm font-medium"><?php echo htmlspecialchars(ucfirst($exam['exam_type'])); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Date</p>
                        <p class="text-sm font-medium">
                            <?php 
                            if ($exam['start_date'] && $exam['end_date']) {
                                echo date('d M Y', strtotime($exam['start_date'])) . ' - ' . date('d M Y', strtotime($exam['end_date']));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Results Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-medium text-gray-900">Subject Marks</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Theory</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Practical</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GPA</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                No results found for this student.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($result['subject_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($result['subject_code']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($result['theory_marks'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo isset($result['practical_marks']) && $result['practical_marks'] > 0 ? 
                                        number_format($result['practical_marks'], 2) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo number_format($result['total_marks'], 2); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo number_format($result['percentage'], 2); ?>%</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $grade_class = 'pending';
                                    switch ($result['grade']) {
                                        case 'A+': $grade_class = 'a-plus'; break;
                                        case 'A': $grade_class = 'a'; break;
                                        case 'B+': $grade_class = 'b-plus'; break;
                                        case 'B': $grade_class = 'b'; break;
                                        case 'C+': $grade_class = 'c-plus'; break;
                                        case 'C': $grade_class = 'c'; break;
                                        case 'D': $grade_class = 'd'; break;
                                        case 'F': $grade_class = 'f'; break;
                                    }
                                    ?>
                                    <span class="grade-badge <?php echo $grade_class; ?>">
                                        <?php echo $result['grade']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($result['gpa'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $result['remarks'] == 'Pass' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $result['remarks']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="editStudentMarks('<?php echo $result['result_id']; ?>', '<?php echo $student_id; ?>', '<?php echo $exam_id; ?>', '<?php echo $result['subject_id']; ?>')" 
                                            class="text-blue-600 hover:text-blue-900" title="Edit Marks">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Overall Result -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Overall Result</h3>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-4">
            <div>
                <p class="text-xs text-gray-500">Total Marks</p>
                <p class="text-lg font-semibold"><?php echo number_format($obtained_marks, 2); ?> / <?php echo number_format($total_marks, 2); ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Percentage</p>
                <p class="text-lg font-semibold"><?php echo number_format($overall_percentage, 2); ?>%</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Grade</p>
                <p class="text-lg font-semibold"><?php echo $overall_grade; ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">GPA</p>
                <p class="text-lg font-semibold"><?php echo number_format($average_gpa, 2); ?></p>
            </div>
        </div>
        
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500">Subjects Passed</p>
                <p class="text-sm font-medium"><?php echo $pass_count; ?> out of <?php echo $total_subjects; ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Overall Status</p>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $overall_status == 'Pass' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $overall_status; ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="flex justify-between">
        <button onclick="closeResultsModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-times mr-2"></i> Close
        </button>
        
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-print mr-2"></i> Print Result
        </button>
    </div>
</div>
