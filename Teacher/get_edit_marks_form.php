<?php
session_start();
include '../includes/config.php';
include '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get parameters
$result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// Validate required parameters
if (!$result_id || !$student_id || !$exam_id || !$subject_id) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
            <p class="font-bold">Error</p>
            <p>Missing required parameters.</p>
          </div>';
    exit();
}

// Get teacher ID
$user_id = $_SESSION['user_id'];
$teacher_query = "SELECT teacher_id FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($teacher_query);
if (!$stmt) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
            <p class="font-bold">Error</p>
            <p>Database error: ' . $conn->error . '</p>
          </div>';
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
            <p class="font-bold">Error</p>
            <p>Teacher record not found.</p>
          </div>';
    exit();
}
$teacher = $result->fetch_assoc();
$teacher_id = $teacher['teacher_id'];
$stmt->close();

// Check if teacher is assigned to this subject
$check_query = "SELECT ts.id 
                FROM teachersubjects ts 
                JOIN students s ON ts.class_id = s.class_id
                WHERE ts.teacher_id = ? 
                AND ts.subject_id = ? 
                AND s.student_id = ?";
$stmt = $conn->prepare($check_query);
if (!$stmt) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
            <p class="font-bold">Error</p>
            <p>Database error: ' . $conn->error . '</p>
          </div>';
    exit();
}

$stmt->bind_param("iis", $teacher_id, $subject_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
            <p class="font-bold">Error</p>
            <p>You are not authorized to edit marks for this student in this subject.</p>
          </div>';
    exit();
}
$stmt->close();

// Get student details
$student_query = "SELECT s.student_id, s.roll_number, u.full_name
                 FROM students s
                 JOIN users u ON s.user_id = u.user_id
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

// Get subject details
$subject_query = "SELECT subject_name, subject_code FROM subjects WHERE subject_id = ?";
$stmt = $conn->prepare($subject_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$subject = $result->fetch_assoc();
$stmt->close();

if (!$subject) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p class="font-bold">Error</p>
            <p>Subject not found.</p>
          </div>';
    exit();
}

// Get exam details
$exam_query = "SELECT exam_name FROM exams WHERE exam_id = ?";
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

// Get result details
$result_query = "SELECT theory_marks, practical_marks, total_marks, percentage, grade, gpa, remarks
                FROM results
                WHERE result_id = ?";
$stmt = $conn->prepare($result_query);
$stmt->bind_param("i", $result_id);
$stmt->execute();
$result = $stmt->get_result();
$result_data = $result->fetch_assoc();
$stmt->close();

if (!$result_data) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p class="font-bold">Error</p>
            <p>Result not found.</p>
          </div>';
    exit();
}

$conn->close();
?>

<form id="editMarksForm" onsubmit="return updateStudentMarks('editMarksForm')">
    <input type="hidden" name="result_id" value="<?php echo $result_id; ?>">
    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
    <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
    <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
    
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></h3>
                <p class="text-sm text-gray-500">Roll Number: <?php echo htmlspecialchars($student['roll_number']); ?></p>
            </div>
            <div class="text-right">
                <h4 class="text-md font-medium text-gray-900"><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($exam['exam_name']); ?></p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="theory_marks" class="block text-sm font-medium text-gray-700 mb-1">Theory Marks</label>
                <input type="number" id="theory_marks" name="theory_marks" value="<?php echo $result_data['theory_marks']; ?>" 
                       min="0" max="100" step="0.01" required
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                <p class="text-xs text-gray-500 mt-1">Enter marks between 0 and 100</p>
            </div>
            
            <div>
                <label for="practical_marks" class="block text-sm font-medium text-gray-700 mb-1">Practical Marks</label>
                <input type="number" id="practical_marks" name="practical_marks" value="<?php echo $result_data['practical_marks']; ?>" 
                       min="0" max="100" step="0.01"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                <p class="text-xs text-gray-500 mt-1">Leave empty or 0 if not applicable</p>
            </div>
        </div>
        
        <div class="bg-gray-50 p-4 rounded-md mb-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Current Grade Information</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs text-gray-500">Total Marks</p>
                    <p class="text-sm font-medium"><?php echo $result_data['total_marks']; ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Percentage</p>
                    <p class="text-sm font-medium"><?php echo $result_data['percentage']; ?>%</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Grade</p>
                    <p class="text-sm font-medium"><?php echo $result_data['grade']; ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Status</p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $result_data['remarks'] == 'Pass' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo $result_data['remarks']; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        <strong>Note:</strong> Updating marks will automatically recalculate the grade, GPA, and pass/fail status.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="flex justify-end space-x-3">
        <button type="button" onclick="closeEditMarksModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Cancel
        </button>
        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-save mr-2"></i> Update Marks
        </button>
    </div>
</form>
