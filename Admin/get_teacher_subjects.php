<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Check if teachersubjects table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'teachersubjects'");
if ($table_check->num_rows == 0) {
    // Create the teachersubjects table
    $create_table_sql = "CREATE TABLE `teachersubjects` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `teacher_id` int(11) NOT NULL,
        `subject_id` int(11) NOT NULL,
        `class_id` int(11) NOT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `teacher_id` (`teacher_id`),
        KEY `subject_id` (`subject_id`),
        KEY `class_id` (`class_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    if (!$conn->query($create_table_sql)) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                <p class="font-bold">Database Error</p>
                <p>Failed to create teachersubjects table: ' . $conn->error . '</p>
              </div>';
        exit();
    }
}

// Get teacher details
$query = "SELECT t.*, u.full_name 
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

// Get teacher's current subject assignments
$current_subjects = [];
$current_subjects_query = "SELECT ts.*, s.subject_name, s.subject_code, c.class_name, c.section 
                          FROM teachersubjects ts 
                          JOIN subjects s ON ts.subject_id = s.subject_id 
                          JOIN classes c ON ts.class_id = c.class_id 
                          WHERE ts.teacher_id = ?";
$current_subjects_stmt = $conn->prepare($current_subjects_query);
if ($current_subjects_stmt) {
    $current_subjects_stmt->bind_param("i", $teacher_id);
    $current_subjects_stmt->execute();
    $current_subjects_result = $current_subjects_stmt->get_result();
    
    while ($subject = $current_subjects_result->fetch_assoc()) {
        $current_subjects[] = $subject;
    }
    $current_subjects_stmt->close();
}

// Get all available subjects
$subjects = [];
$subjects_query = "SELECT * FROM subjects ORDER BY subject_name";
$subjects_result = $conn->query($subjects_query);
if ($subjects_result) {
    while ($subject = $subjects_result->fetch_assoc()) {
        $subjects[] = $subject;
    }
}

// Get all available classes
$classes = [];
$classes_query = "SELECT * FROM classes ORDER BY class_name, section";
$classes_result = $conn->query($classes_query);
if ($classes_result) {
    while ($class = $classes_result->fetch_assoc()) {
        $classes[] = $class;
    }
}

$conn->close();
?>

<div class="space-y-6">
    <div class="bg-white p-4 rounded-lg border border-gray-200">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Teacher: <?php echo htmlspecialchars($teacher['full_name']); ?></h3>
        <p class="text-sm text-gray-600 mb-4">Department: <?php echo htmlspecialchars($teacher['department']); ?></p>
        
        <!-- Current Subject Assignments -->
        <div class="mb-6">
            <h4 class="text-md font-medium text-gray-900 mb-2">Current Subject Assignments</h4>
            
            <?php if (empty($current_subjects)): ?>
                <p class="text-sm text-gray-500">No subjects currently assigned to this teacher.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($current_subjects as $subject): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($subject['subject_code']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($subject['class_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($subject['section']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($subject['is_active'] == 1): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <button type="button" onclick="toggleSubjectStatus(<?php echo $subject['id']; ?>, <?php echo $subject['is_active'] == 1 ? 0 : 1; ?>)" class="text-indigo-600 hover:text-indigo-900">
                                            <?php echo $subject['is_active'] == 1 ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                        <button type="button" onclick="removeSubject(<?php echo $subject['id']; ?>)" class="ml-3 text-red-600 hover:text-red-900">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Add New Subject Assignment -->
        <div>
            <h4 class="text-md font-medium text-gray-900 mb-2">Assign New Subject</h4>
            
            <form id="assignSubjectForm" onsubmit="return assignNewSubject()">
                <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="subject_id" class="block text-sm font-medium text-gray-700">Subject</label>
                        <select name="subject_id" id="subject_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['subject_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="class_id" class="block text-sm font-medium text-gray-700">Class</label>
                        <select name="class_id" id="class_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name'] . ' - Section ' . $class['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-plus mr-2"></i> Assign Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Assign new subject
    function assignNewSubject() {
        const form = document.getElementById('assignSubjectForm');
        const formData = new FormData(form);
        
        // Show loading indicator
        Swal.fire({
            title: 'Processing...',
            text: 'Assigning subject to teacher',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Log the form data for debugging
        console.log('Form data:', {
            teacher_id: formData.get('teacher_id'),
            subject_id: formData.get('subject_id'),
            class_id: formData.get('class_id')
        });
        
        fetch('save_teacher_subjects.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            console.log('Parsed response:', data);
            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#3085d6'
                }).then(() => {
                    // Refresh the subjects modal
                    showSubjectsModal('<?php echo $teacher_id; ?>');
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message || 'An unknown error occurred',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred: ' + error.message,
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
        });
        
        return false; // Prevent form submission
    }
    
    // Toggle subject status (activate/deactivate)
    function toggleSubjectStatus(assignmentId, newStatus) {
        // Show confirmation dialog
        Swal.fire({
            title: newStatus ? 'Activate Subject?' : 'Deactivate Subject?',
            text: newStatus ? "This will make the subject active for this teacher." : "This will make the subject inactive for this teacher.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus ? '#3085d6' : '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: newStatus ? 'Yes, activate it!' : 'Yes, deactivate it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading indicator
                Swal.fire({
                    title: 'Processing...',
                    text: newStatus ? 'Activating subject' : 'Deactivating subject',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('id', assignmentId);
                formData.append('status', newStatus);
                formData.append('teacher_id', '<?php echo $teacher_id; ?>');
                
                // Log the form data for debugging
                console.log('Toggle status data:', {
                    action: 'toggle_status',
                    id: assignmentId,
                    status: newStatus,
                    teacher_id: '<?php echo $teacher_id; ?>'
                });
                
                fetch('save_teacher_subjects.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            throw new Error('Invalid JSON response: ' + text);
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed response:', data);
                    if (data.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonColor: '#3085d6'
                        }).then(() => {
                            // Refresh the subjects modal
                            showSubjectsModal('<?php echo $teacher_id; ?>');
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: data.message || 'An unknown error occurred',
                            icon: 'error',
                            confirmButtonColor: '#3085d6'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred: ' + error.message,
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                });
            }
        });
    }
    
    // Remove subject assignment
    function removeSubject(assignmentId) {
        Swal.fire({
            title: 'Remove Subject Assignment?',
            text: "Are you sure you want to remove this subject assignment? This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading indicator
                Swal.fire({
                    title: 'Processing...',
                    text: 'Removing subject assignment',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                const formData = new FormData();
                formData.append('action', 'remove');
                formData.append('id', assignmentId);
                formData.append('teacher_id', '<?php echo $teacher_id; ?>');
                
                // Log the form data for debugging
                console.log('Remove subject data:', {
                    action: 'remove',
                    id: assignmentId,
                    teacher_id: '<?php echo $teacher_id; ?>'
                });
                
                fetch('save_teacher_subjects.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            throw new Error('Invalid JSON response: ' + text);
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed response:', data);
                    if (data.success) {
                        Swal.fire({
                            title: 'Removed!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonColor: '#3085d6'
                        }).then(() => {
                            // Refresh the subjects modal
                            showSubjectsModal('<?php echo $teacher_id; ?>');
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: data.message || 'An unknown error occurred',
                            icon: 'error',
                            confirmButtonColor: '#3085d6'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred: ' + error.message,
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                });
            }
        });
    }
</script>
