<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Check if teacher ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
            <p class="font-bold">Error</p>
            <p>Teacher ID is required.</p>
          </div>';
    exit();
}

$teacher_id = $_GET['id'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get teacher details with user information
$query = "SELECT t.*, u.full_name, u.email, u.phone, u.status, u.created_at as user_created_at
          FROM teachers t 
          JOIN users u ON t.user_id = u.user_id 
          WHERE t.teacher_id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
            <p class="font-bold">Database Error</p>
            <p>Failed to prepare statement: ' . $conn->error . '</p>
          </div>';
    exit();
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
            <p class="font-bold">Error</p>
            <p>Teacher not found.</p>
          </div>';
    exit();
}

$teacher = $result->fetch_assoc();
$stmt->close();

// Departments query removed

$conn->close();
?>

<form id="editTeacherForm" onsubmit="return saveTeacherEdit()">
    <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
    <input type="hidden" name="user_id" value="<?php echo $teacher['user_id']; ?>">
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <!-- Personal Information -->
        <div class="md:col-span-2">
            <h4 class="text-lg font-medium text-gray-900 mb-2">Personal Information</h4>
        </div>
        
        <div>
            <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
            <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($teacher['full_name']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
        </div>
        
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
        </div>
        
        <div>
            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
            <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div>
            <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
            <select name="gender" id="gender" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                <option value="male" <?php echo (isset($teacher['gender']) && $teacher['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo (isset($teacher['gender']) && $teacher['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                <option value="other" <?php echo (isset($teacher['gender']) && $teacher['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
        
        <div>
            <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of Birth</label>
            <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo !empty($teacher['date_of_birth']) ? date('Y-m-d', strtotime($teacher['date_of_birth'])) : ''; ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div>
            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
            <textarea name="address" id="address" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"><?php echo htmlspecialchars($teacher['address'] ?? ''); ?></textarea>
        </div>
        
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">New Password (leave blank to keep current)</label>
            <input type="password" name="password" id="password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <!-- Professional Information -->
        <div class="md:col-span-2 mt-4">
            <h4 class="text-lg font-medium text-gray-900 mb-2">Professional Information</h4>
        </div>
        
        <div>
            <label for="employee_id" class="block text-sm font-medium text-gray-700">Employee ID</label>
            <input type="text" name="employee_id" id="employee_id" value="<?php echo htmlspecialchars($teacher['employee_id']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
        </div>
        
        <!-- Department field removed -->
        
        <div>
            <label for="qualification" class="block text-sm font-medium text-gray-700">Qualification</label>
            <input type="text" name="qualification" id="qualification" value="<?php echo htmlspecialchars($teacher['qualification'] ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div>
            <label for="joining_date" class="block text-sm font-medium text-gray-700">Joining Date</label>
            <input type="date" name="joining_date" id="joining_date" value="<?php echo !empty($teacher['joining_date']) ? date('Y-m-d', strtotime($teacher['joining_date'])) : ''; ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div>
            <label for="experience" class="block text-sm font-medium text-gray-700">Experience (years)</label>
            <input type="number" name="experience" id="experience" value="<?php echo htmlspecialchars($teacher['experience'] ?? ''); ?>" min="0" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" id="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                <option value="active" <?php echo ($teacher['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo ($teacher['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                <option value="pending" <?php echo ($teacher['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
            </select>
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

<!-- Department script removed -->
