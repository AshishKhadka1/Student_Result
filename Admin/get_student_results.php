<?php
session_start();

// Set headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo "Unauthorized access";
    exit();
}

// Check if student ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='text-red-500 p-4'>Error: Student ID is required</div>";
    exit();
}

$student_id = $_GET['id'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    echo "<div class='text-red-500 p-4'>Connection failed: " . $conn->connect_error . "</div>";
    exit();
}

// Get student basic info
$stmt = $conn->prepare("
    SELECT s.*, u.full_name, c.class_name, c.section
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN classes c ON s.class_id = c.class_id
    WHERE s.student_id = ?
");

$stmt->bind_param("s", $student_id);
$stmt->execute();
$student_result = $stmt->get_result();

if ($student_result->num_rows === 0) {
    echo "<div class='text-red-500 p-4'>Student not found</div>";
    exit();
}

$student = $student_result->fetch_assoc();

// Get all exams for this student
$exams_query = "
    SELECT DISTINCT e.exam_id, e.exam_name, e.exam_type, e.created_at
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE r.student_id = ?
    ORDER BY e.created_at DESC
";

$stmt = $conn->prepare($exams_query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$exams_result = $stmt->get_result();

$exams = [];
while ($row = $exams_result->fetch_assoc()) {
    $exams[] = $row;
}

// If no exams found
if (count($exams) === 0) {
    echo "
    <div class='bg-yellow-50 border-l-4 border-yellow-400 p-4'>
        <div class='flex'>
            <div class='flex-shrink-0'>
                <svg class='h-5 w-5 text-yellow-400' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor'>
                    <path fill-rule='evenodd' d='M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z' clip-rule='evenodd' />
                </svg>
            </div>
            <div class='ml-3'>
                <p class='text-sm text-yellow-700'>
                    No exam results found for this student.
                </p>
            </div>
        </div>
    </div>
    ";
    exit();
}

// Get the first exam by default or the selected one
$selected_exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : $exams[0]['exam_id'];

// Get results for the selected exam
$results_query = "
    SELECT r.result_id, r.student_id, r.exam_id, r.subject_id, r.theory_marks, r.practical_marks, 
           r.grade, r.gpa, r.remarks, s.subject_name, s.subject_code, s.credit_hours
    FROM results r
    JOIN subjects s ON r.subject_id = s.subject_id
    WHERE r.student_id = ? AND r.exam_id = ?
    ORDER BY s.subject_name
";

$stmt = $conn->prepare($results_query);
$stmt->bind_param("si", $student_id, $selected_exam_id);
$stmt->execute();
$results_result = $stmt->get_result();

$results = [];
$total_marks = 0;
$total_subjects = 0;
$total_credit_hours = 0;
$total_grade_points = 0;
$failed_subjects = 0;

while ($row = $results_result->fetch_assoc()) {
    $results[] = $row;
    $total_marks += ($row['theory_marks'] + ($row['practical_marks'] ?? 0));
    $total_subjects++;
    
    // Calculate GPA if not already set
    if (!isset($row['gpa']) || $row['gpa'] === null) {
        $grade = $row['grade'];
        $gpa = 0;
        
        switch ($grade) {
            case 'A+': $gpa = 4.0; break;
            case 'A': $gpa = 3.7; break;
            case 'B+': $gpa = 3.3; break;
            case 'B': $gpa = 3.0; break;
            case 'C+': $gpa = 2.7; break;
            case 'C': $gpa = 2.3; break;
            case 'D': $gpa = 1.0; break;
            case 'F': $gpa = 0.0; break;
        }
        
        $row['gpa'] = $gpa;
    }
    
    $credit = $row['credit_hours'] ?? 1;
    $total_credit_hours += $credit;
    $total_grade_points += ($row['gpa'] * $credit);
    
    if ($row['grade'] == 'F') {
        $failed_subjects++;
    }
}

// Calculate overall GPA and percentage
$overall_gpa = $total_credit_hours > 0 ? ($total_grade_points / $total_credit_hours) : 0;
$percentage = $total_subjects > 0 ? ($total_marks / ($total_subjects * 100)) * 100 : 0;

// Determine result status and division
$result_status = $failed_subjects > 0 ? 'FAIL' : 'PASS';
$division = '';

if ($percentage >= 80) {
    $division = 'Distinction';
} elseif ($percentage >= 60) {
    $division = 'First Division';
} elseif ($percentage >= 45) {
    $division = 'Second Division';
} elseif ($percentage >= 33) {
    $division = 'Third Division';
} else {
    $division = 'Fail';
}

// Get exam details
$exam_details = null;
foreach ($exams as $exam) {
    if ($exam['exam_id'] == $selected_exam_id) {
        $exam_details = $exam;
        break;
    }
}

$conn->close();
?>

<div class="bg-white rounded-lg overflow-hidden">
    <!-- Student and Exam Info Header -->
    <div class="bg-gray-50 p-4 border-b border-gray-200">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center">
            <div>
                <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></h3>
                <p class="text-sm text-gray-500">
                    ID: <?php echo htmlspecialchars($student['student_id']); ?> | 
                    Roll: <?php echo htmlspecialchars($student['roll_number']); ?> | 
                    Class: <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?>
                </p>
            </div>
            
            <div class="mt-2 md:mt-0">
                <label for="exam_selector" class="block text-sm font-medium text-gray-700 mb-1">Select Exam:</label>
                <select id="exam_selector" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" onchange="changeExam(this.value)" data-student-id="<?php echo $student_id; ?>">
                    <?php foreach ($exams as $exam): ?>
                        <option value="<?php echo $exam['exam_id']; ?>" <?php echo ($exam['exam_id'] == $selected_exam_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($exam['exam_name'] . ' (' . ucfirst($exam['exam_type']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Results Table -->
    <div class="p-4">
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
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No results found for this exam.</td>
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
                                    <?php echo isset($result['practical_marks']) && $result['practical_marks'] > 0 ? number_format($result['practical_marks'], 2) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    $total = $result['theory_marks'] + (isset($result['practical_marks']) ? $result['practical_marks'] : 0);
                                    echo number_format($total, 2); 
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $result['grade'] == 'F' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo $result['grade']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($result['gpa'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($result['remarks'] ?? ''); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-500">Total:</td>
                        <td class="px-6 py-3 text-sm font-medium text-gray-900"><?php echo number_format($total_marks, 2); ?></td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-500">Percentage:</td>
                        <td class="px-6 py-3 text-sm font-medium text-gray-900"><?php echo number_format($percentage, 2); ?>%</td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-500">GPA:</td>
                        <td class="px-6 py-3 text-sm font-medium text-gray-900"><?php echo number_format($overall_gpa, 2); ?></td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-500">Division:</td>
                        <td class="px-6 py-3 text-sm font-medium text-gray-900"><?php echo $division; ?></td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-500">Result:</td>
                        <td class="px-6 py-3">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $result_status == 'PASS' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $result_status; ?>
                            </span>
                        </td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- Grading Scale Reference -->
    <div class="p-4 bg-gray-50 border-t border-gray-200">
        <h4 class="text-sm font-medium text-gray-700 mb-2">Grading Scale Reference</h4>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <div class="text-xs">
                <span class="font-medium">A+:</span> 90-100% (GPA: 4.0)
            </div>
            <div class="text-xs">
                <span class="font-medium">A:</span> 80-89% (GPA: 3.7)
            </div>
            <div class="text-xs">
                <span class="font-medium">B+:</span> 70-79% (GPA: 3.3)
            </div>
            <div class="text-xs">
                <span class="font-medium">B:</span> 60-69% (GPA: 3.0)
            </div>
            <div class="text-xs">
                <span class="font-medium">C+:</span> 50-59% (GPA: 2.7)
            </div>
            <div class="text-xs">
                <span class="font-medium">C:</span> 40-49% (GPA: 2.3)
            </div>
            <div class="text-xs">
                <span class="font-medium">D:</span> 33-39% (GPA: 1.0)
            </div>
            <div class="text-xs">
                <span class="font-medium">F:</span> Below 33% (GPA: 0.0)
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3 border-t border-gray-200">
        <button onclick="printStudentResult('<?php echo $student['student_id']; ?>', <?php echo $selected_exam_id; ?>)" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print
        </button>
        <button onclick="closeResultsModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Close
        </button>
    </div>
</div>
