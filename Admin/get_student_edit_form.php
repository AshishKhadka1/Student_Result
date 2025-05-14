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
           c.class_name, c.section
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

// Get all classes for dropdown
$classes = [];
$class_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name, section");
while ($row = $class_result->fetch_assoc()) {
    $classes[] = $row;
}

$conn->close();
?>

<form id="editStudentForm" onsubmit="return saveStudentEdit('editStudentForm')">
    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>">
    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($student['user_id']); ?>">
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <!-- Personal Information -->
        <div class="md:col-span-2">
            <h4 class="text-lg font-medium text-gray-900 mb-2">Personal Information</h4>
        </div>
        
        
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($student['email']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
        </div>
        
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($student['username']); ?>" class="mt-1 block w-full bg-gray-100 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly>
        </div>
        
        <div>
            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
            <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($student['phone'] ?? $student['user_phone'] ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div>
            <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
            <select name="gender" id="gender" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                <option value="male" <?php echo ($student['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo ($student['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                <option value="other" <?php echo ($student['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
        
        <div>
            <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of Birth</label>
            <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo !empty($student['date_of_birth']) ? date('Y-m-d', strtotime($student['date_of_birth'])) : ''; ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div class="md:col-span-2">
            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
            <textarea name="address" id="address" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"><?php echo htmlspecialchars($student['address'] ?? $student['user_address'] ?? ''); ?></textarea>
        </div>
        
        <!-- Parent Information (New Section) -->
        <div class="md:col-span-2 mt-4">
            <h4 class="text-lg font-medium text-gray-900 mb-2">Parent/Guardian Information</h4>
        </div>
        
        <div>
            <label for="parent_name" class="block text-sm font-medium text-gray-700">Parent Name</label>
            <input type="text" name="parent_name" id="parent_name" value="<?php echo htmlspecialchars($student['parent_name'] ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div>
            <label for="parent_phone" class="block text-sm font-medium text-gray-700">Parent Phone</label>
            <input type="text" name="parent_phone" id="parent_phone" value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div>
            <label for="parent_email" class="block text-sm font-medium text-gray-700">Parent Email</label>
            <input type="email" name="parent_email" id="parent_email" value="<?php echo htmlspecialchars($student['parent_email'] ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <!-- Academic Information -->
        <div class="md:col-span-2 mt-4">
            <h4 class="text-lg font-medium text-gray-900 mb-2">Academic Information</h4>
        </div>
        
        <div>
            <label for="roll_number" class="block text-sm font-medium text-gray-700">Roll Number</label>
            <input type="text" name="roll_number" id="roll_number" value="<?php echo htmlspecialchars($student['roll_number']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
        </div>
        
        <div>
            <label for="registration_number" class="block text-sm font-medium text-gray-700">Registration Number</label>
            <input type="text" name="registration_number" id="registration_number" value="<?php echo htmlspecialchars($student['registration_number'] ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div>
            <label for="class_id" class="block text-sm font-medium text-gray-700">Class</label>
            <select name="class_id" id="class_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                <option value="">Select Class</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['class_id']; ?>" <?php echo ($student['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['class_name'] . ' ' . $class['section']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label for="batch_year" class="block text-sm font-medium text-gray-700">Batch Year</label>
            <input type="text" name="batch_year" id="batch_year" value="<?php echo htmlspecialchars($student['batch_year']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
        </div>
        
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" id="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                <option value="active" <?php echo ($student['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo ($student['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                <option value="pending" <?php echo ($student['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
            </select>
        </div>
        
        <!-- Password Reset Section -->
        <div class="md:col-span-2 mt-4">
            <h4 class="text-lg font-medium text-gray-900 mb-2">Password Reset</h4>
            <div class="bg-yellow-50 p-4 rounded-md mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Password Reset Information</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>Leave the password fields empty if you don't want to change the password.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div>
            <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
            <input type="password" name="new_password" id="new_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div>
            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirm_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
    </div>
    
    <div class="flex justify-end space-x-3 mt-6">
        <button type="button" onclick="closeEditModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Cancel
        </button>
        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Save Changes
        </button>
    </div>
</form>
