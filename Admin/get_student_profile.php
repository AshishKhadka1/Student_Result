<?php
session_start();
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

// Get student data with user information - UPDATED QUERY to include all fields
$stmt = $conn->prepare("
    SELECT s.*, u.full_name, u.email, u.username, u.status, u.phone as user_phone, 
           u.created_at as account_created, u.address as user_address,
           c.class_name, c.section, c.academic_year
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN classes c ON s.class_id = c.class_id
    WHERE s.student_id = ?
");

$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='text-red-500 p-4'>Student not found</div>";
    exit();
}

$student = $result->fetch_assoc();

// Get student's academic performance summary
$performance_query = "
    SELECT 
        e.exam_name, 
        e.exam_type,
        COUNT(r.subject_id) as subjects_count,
        SUM(CASE WHEN r.grade != 'F' THEN 1 ELSE 0 END) as subjects_passed,
        AVG((r.theory_marks + COALESCE(r.practical_marks, 0))) as average_marks,
        AVG(r.gpa) as average_gpa
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE r.student_id = ?
    GROUP BY r.exam_id
    ORDER BY e.created_at DESC
    LIMIT 5
";

$stmt = $conn->prepare($performance_query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$performance_result = $stmt->get_result();
$performance_data = [];

while ($row = $performance_result->fetch_assoc()) {
    $performance_data[] = $row;
}

// Get student's attendance if available
$attendance_data = null;
$attendance_query = "
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
    FROM attendance
    WHERE student_id = ?
";

$stmt = $conn->prepare($attendance_query);
if ($stmt) {
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    if ($attendance_result->num_rows > 0) {
        $attendance_data = $attendance_result->fetch_assoc();
    }
}

$conn->close();
?>

<div class="bg-white rounded-lg overflow-hidden">
    <div class="md:flex">
        <!-- Student Profile Image and Basic Info -->
        <div class="md:w-1/3 bg-gray-50 p-6 border-r border-gray-200">
            <div class="flex flex-col items-center">
                <div class="w-32 h-32 rounded-full overflow-hidden bg-gray-200 mb-4">
                    <?php if (!empty($student['profile_image'])): ?>
                        <img src="../<?php echo htmlspecialchars($student['profile_image']); ?>" alt="Profile Image" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-blue-100 text-blue-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($student['student_id']); ?></p>
                
                <div class="mt-2 flex items-center">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                        <?php echo $student['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo ucfirst(htmlspecialchars($student['status'])); ?>
                    </span>
                </div>
                
                <div class="mt-4 w-full">
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-500">Roll Number:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($student['roll_number']); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-500">Registration:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($student['registration_number'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-500">Class:</span>
                        <span class="font-medium">
                            <?php 
                            echo !empty($student['class_name']) 
                                ? htmlspecialchars($student['class_name'] . ' ' . $student['section']) 
                                : 'Not Assigned'; 
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-500">Academic Year:</span>
                        <span class="font-medium">
                            <?php echo !empty($student['academic_year']) ? htmlspecialchars($student['academic_year']) : 'N/A'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-500">Batch Year:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($student['batch_year']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Student Details -->
        <div class="md:w-2/3 p-6">
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Personal Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="font-medium"><?php echo htmlspecialchars($student['email']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Username</p>
                        <p class="font-medium"><?php echo htmlspecialchars($student['username']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Gender</p>
                        <p class="font-medium"><?php echo !empty($student['gender']) ? ucfirst(htmlspecialchars($student['gender'])) : 'Not specified'; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Date of Birth</p>
                        <p class="font-medium">
                            <?php echo !empty($student['date_of_birth']) ? date('F j, Y', strtotime($student['date_of_birth'])) : 'Not specified'; ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Phone</p>
                        <p class="font-medium">
                            <?php 
                            // Use student's phone if available, otherwise use user's phone
                            $phone = !empty($student['phone']) ? $student['phone'] : (!empty($student['user_phone']) ? $student['user_phone'] : 'Not specified');
                            echo htmlspecialchars($phone);
                            ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Address</p>
                        <p class="font-medium">
                            <?php 
                            // Use student's address if available, otherwise use user's address
                            $address = !empty($student['address']) ? $student['address'] : (!empty($student['user_address']) ? $student['user_address'] : 'Not specified');
                            echo htmlspecialchars($address);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Parent Information (New Section) -->
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Parent/Guardian Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Parent Name</p>
                        <p class="font-medium"><?php echo !empty($student['parent_name']) ? htmlspecialchars($student['parent_name']) : 'Not specified'; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Parent Phone</p>
                        <p class="font-medium"><?php echo !empty($student['parent_phone']) ? htmlspecialchars($student['parent_phone']) : 'Not specified'; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Parent Email</p>
                        <p class="font-medium"><?php echo !empty($student['parent_email']) ? htmlspecialchars($student['parent_email']) : 'Not specified'; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Academic Performance</h3>
                <?php if (empty($performance_data)): ?>
                    <p class="text-gray-500">No performance data available.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjects</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Marks</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GPA</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($performance_data as $exam): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($exam['exam_name']); ?>
                                            <div class="text-xs text-gray-500"><?php echo ucfirst(htmlspecialchars($exam['exam_type'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $exam['subjects_passed'] . '/' . $exam['subjects_count']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo number_format($exam['average_marks'], 2); ?>%
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo number_format($exam['average_gpa'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $passed = $exam['subjects_passed'] == $exam['subjects_count'];
                                            $class = $passed ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                            $result = $passed ? 'PASS' : 'FAIL';
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $class; ?>">
                                                <?php echo $result; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($attendance_data): ?>
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Attendance Summary</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Present Days</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $attendance_data['present_days']; ?></p>
                        <p class="text-sm text-gray-500">
                            <?php 
                            $present_percentage = ($attendance_data['total_days'] > 0) 
                                ? round(($attendance_data['present_days'] / $attendance_data['total_days']) * 100) 
                                : 0;
                            echo $present_percentage . '%';
                            ?>
                        </p>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Absent Days</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $attendance_data['absent_days']; ?></p>
                        <p class="text-sm text-gray-500">
                            <?php 
                            $absent_percentage = ($attendance_data['total_days'] > 0) 
                                ? round(($attendance_data['absent_days'] / $attendance_data['total_days']) * 100) 
                                : 0;
                            echo $absent_percentage . '%';
                            ?>
                        </p>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Late Days</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $attendance_data['late_days']; ?></p>
                        <p class="text-sm text-gray-500">
                            <?php 
                            $late_percentage = ($attendance_data['total_days'] > 0) 
                                ? round(($attendance_data['late_days'] / $attendance_data['total_days']) * 100) 
                                : 0;
                            echo $late_percentage . '%';
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Account Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Account Created</p>
                        <p class="font-medium">
                            <?php echo date('F j, Y', strtotime($student['account_created'])); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Last Updated</p>
                        <p class="font-medium">
                            <?php echo date('F j, Y', strtotime($student['updated_at'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3 border-t border-gray-200">
        <button onclick="showEditModal('<?php echo $student['student_id']; ?>')" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
            </svg>
            Edit Profile
        </button>
        <button onclick="showResultsModal('<?php echo $student['student_id']; ?>')" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            View Results
        </button>
        <button onclick="closeProfileModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Close
        </button>
    </div>
</div>
