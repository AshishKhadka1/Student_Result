<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'teacher' && $_SESSION['role'] != 'admin')) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get teacher information if user is a teacher
$teacher_id = null;
if ($role == 'teacher') {
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $teacher_id = $result->fetch_assoc()['teacher_id'];
    }
    $stmt->close();
}

// Get all classes
$classes = [];
$sql = "SELECT class_id, class_name, section, academic_year FROM classes ORDER BY class_name, section";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

// Handle form submission for adding/updating subjects
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_subject') {
        $subject_name = $_POST['subject_name'];
        $subject_code = $_POST['subject_id']; // Using subject_id as the code
        $description = $_POST['description'] ?? '';
        
        // Check if subject already exists
        $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_id = ?");
        $stmt->bind_param("s", $subject_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error'] = "Subject with this code already exists!";
        } else {
            // Insert new subject
            $stmt = $conn->prepare("INSERT INTO subjects (subject_id, subject_name, description, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sss", $subject_code, $subject_name, $description);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Subject added successfully!";
                
                // If teacher is adding the subject, assign it to them
                if ($role == 'teacher' && $teacher_id) {
                    $class_id = $_POST['class_id'];
                    $academic_year = date('Y') . '-' . (date('Y') + 1);
                    
                    $assign_stmt = $conn->prepare("INSERT INTO teachersubjects (teacher_id, subject_id, academic_year) VALUES (?, ?, ?)");
                    $assign_stmt->bind_param("iss", $teacher_id, $subject_code, $academic_year);
                    $assign_stmt->execute();
                    $assign_stmt->close();
                }
            } else {
                $_SESSION['error'] = "Failed to add subject: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] == 'update_subject') {
        $subject_id = $_POST['subject_id'];
        $subject_name = $_POST['subject_name'];
        $description = $_POST['description'] ?? '';
        
        // Update subject
        $stmt = $conn->prepare("UPDATE subjects SET subject_name = ?, description = ?, updated_at = NOW() WHERE subject_id = ?");
        $stmt->bind_param("sss", $subject_name, $description, $subject_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Subject updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update subject: " . $stmt->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] == 'delete_subject') {
        $subject_id = $_POST['subject_id'];
        
        // Check if there are any results for this subject
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM results WHERE subject_id = ?");
        $stmt->bind_param("s", $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        $stmt->close();
        
        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete subject. There are results associated with this subject.";
        } else {
            // Delete subject
            $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
            $stmt->bind_param("s", $subject_id);
            
            if ($stmt->execute()) {
                // Also delete from teachersubjects
                $stmt = $conn->prepare("DELETE FROM teachersubjects WHERE subject_id = ?");
                $stmt->bind_param("s", $subject_id);
                $stmt->execute();
                
                $_SESSION['success'] = "Subject deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete subject: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] == 'assign_teacher') {
        $subject_id = $_POST['subject_id'];
        $teacher_id = $_POST['teacher_id'];
        $academic_year = $_POST['academic_year'];
        
        // Check if assignment already exists
        $stmt = $conn->prepare("SELECT id FROM teachersubjects WHERE teacher_id = ? AND subject_id = ? AND academic_year = ?");
        $stmt->bind_param("iss", $teacher_id, $subject_id, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing assignment
            $_SESSION['error'] = "This teacher is already assigned to this subject for the selected academic year.";
        } else {
            // Insert new assignment
            $stmt = $conn->prepare("INSERT INTO teachersubjects (teacher_id, subject_id, academic_year) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $teacher_id, $subject_id, $academic_year);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Teacher assigned to subject successfully!";
            } else {
                $_SESSION['error'] = "Failed to assign teacher: " . $stmt->error;
            }
        }
        $stmt->close();
    }
    
    // Redirect to prevent form resubmission
    header("Location: subject.php");
    exit();
}

// Get subjects based on role
$subjects = [];
if ($role == 'teacher' && $teacher_id) {
    // Get subjects taught by this teacher
    $sql = "SELECT s.*, ts.academic_year 
            FROM subjects s 
            JOIN teachersubjects ts ON s.subject_id = ts.subject_id 
            WHERE ts.teacher_id = ? 
            ORDER BY s.subject_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
} else {
    // Admin can see all subjects
    $sql = "SELECT s.* FROM subjects s ORDER BY s.subject_name";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Get all teachers for admin
$teachers = [];
if ($role == 'admin') {
    $sql = "SELECT t.teacher_id, t.employee_id, u.full_name, t.department 
            FROM teachers t 
            JOIN users u ON t.user_id = u.user_id 
            ORDER BY u.full_name";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php
        // Include the file that processes form data
        include 'sidebar.php';
        ?>

        <!-- Mobile sidebar -->
        <div class="fixed inset-0 flex z-40 md:hidden transform -translate-x-full transition-transform duration-300 ease-in-out" id="mobile-sidebar">
            <div class="fixed inset-0 bg-gray-600 bg-opacity-75" id="sidebar-backdrop"></div>
            <div class="relative flex-1 flex flex-col max-w-xs w-full bg-gray-800">
                <div class="absolute top-0 right-0 -mr-12 pt-2">
                    <button class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" id="close-sidebar">
                        <span class="sr-only">Close sidebar</span>
                        <i class="fas fa-times text-white"></i>
                    </button>
                </div>
                <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
                    <div class="flex-shrink-0 flex items-center px-4">
                        <span class="text-white text-lg font-semibold">Result Management</span>
                    </div>
                    <nav class="mt-5 px-2 space-y-1">
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            Results
                        </a>
                        <a href="subject.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-book mr-3"></i>
                            Subjects
                        </a>
                        <?php if ($role == 'admin'): ?>
                        <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-users mr-3"></i>
                            Users
                        </a>
                        <a href="students.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-user-graduate mr-3"></i>
                            Students
                        </a>
                        <a href="teachers.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard-teacher mr-3"></i>
                            Teachers
                        </a>
                        <?php endif; ?>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-cog mr-3"></i>
                            Settings
                        </a>
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Manage Subjects</h1>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Notification Messages -->
                        <?php if(isset($_SESSION['success'])): ?>
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">
                                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">
                                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Add Subject Section -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Add New Subject</h2>
                            <form action="subject.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <input type="hidden" name="action" value="add_subject">
                                
                                <div>
                                    <label for="subject_name" class="block text-sm font-medium text-gray-700 mb-1">Subject Name</label>
                                    <input type="text" id="subject_name" name="subject_name" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                </div>
                                
                                <div>
                                    <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Subject ID</label>
                                    <input type="text" id="subject_id" name="subject_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                    <p class="text-xs text-gray-500 mt-1">Example: 101, 102, etc.</p>
                                </div>
                                
                                <?php if ($role == 'admin' || $role == 'teacher'): ?>
                                <div>
                                    <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                    <select id="class_id" name="class_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>">
                                            <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <div class="<?php echo ($role == 'admin' || $role == 'teacher') ? '' : 'md:col-span-2'; ?>">
                                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea id="description" name="description" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-plus mr-2"></i> Add Subject
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Subjects Table -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                                <h2 class="text-lg font-medium text-gray-900">Subject List</h2>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject ID</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                                <?php if ($role == 'admin'): ?>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($subjects)): ?>
                                            <tr>
                                                <td colspan="<?php echo $role == 'admin' ? '4' : '3'; ?>" class="px-6 py-4 text-center text-sm text-gray-500">No subjects found.</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($subjects as $subject): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $subject['subject_id']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $subject['subject_name']; ?></td>
                                                    <td class="px-6 py-4 text-sm text-gray-900">
                                                        <?php echo !empty($subject['description']) ? $subject['description'] : 'N/A'; ?>
                                                    </td>
                                                    <?php if ($role == 'admin'): ?>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button type="button" onclick="editSubject('<?php echo $subject['subject_id']; ?>', '<?php echo $subject['subject_name']; ?>', '<?php echo $subject['description']; ?>')" class="text-blue-600 hover:text-blue-900 mr-3">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" onclick="assignTeacher('<?php echo $subject['subject_id']; ?>')" class="text-green-600 hover:text-green-900 mr-3">
                                                            <i class="fas fa-user-plus"></i>
                                                        </button>
                                                        <form action="subject.php" method="POST" class="inline-block">
                                                            <input type="hidden" name="action" value="delete_subject">
                                                            <input type="hidden" name="subject_id" value="<?php echo $subject['subject_id']; ?>">
                                                            <button type="submit" onclick="return confirm('Are you sure you want to delete this subject?')" class="text-red-600 hover:text-red-900">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Subject Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Subject</h3>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="subject.php" method="POST">
                <input type="hidden" name="action" value="update_subject">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                
                <div class="space-y-4">
                    <div>
                        <label for="edit_subject_name" class="block text-sm font-medium text-gray-700 mb-1">Subject Name</label>
                        <input type="text" id="edit_subject_name" name="subject_name" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="edit_description" name="description" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeModal('editModal')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Update Subject
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Teacher Modal -->
    <?php if ($role == 'admin'): ?>
    <div id="assignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Assign Teacher to Subject</h3>
                <button onclick="closeModal('assignModal')" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="subject.php" method="POST">
                <input type="hidden" name="action" value="assign_teacher">
                <input type="hidden" name="subject_id" id="assign_subject_id">
                
                <div class="space-y-4">
                    <div>
                        <label for="teacher_id" class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                        <select id="teacher_id" name="teacher_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['teacher_id']; ?>">
                                <?php echo $teacher['full_name'] . ' (' . $teacher['employee_id'] . ' - ' . $teacher['department'] . ')'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                        <select id="academic_year" name="academic_year" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <?php 
                            $current_year = date('Y');
                            for ($i = 0; $i < 3; $i++) {
                                $year = $current_year + $i;
                                $next_year = $year + 1;
                                $academic_year = $year . '-' . $next_year;
                                echo "<option value=\"$academic_year\">$academic_year</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeModal('assignModal')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Assign Teacher
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Function to edit subject
        function editSubject(subjectId, subjectName, description) {
            document.getElementById('edit_subject_id').value = subjectId;
            document.getElementById('edit_subject_name').value = subjectName;
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        // Function to assign teacher
        function assignTeacher(subjectId) {
            document.getElementById('assign_subject_id').value = subjectId;
            document.getElementById('assignModal').classList.remove('hidden');
        }
        
        // Function to close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }
        
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.remove('-translate-x-full');
        });
        
        document.getElementById('close-sidebar').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });
        
        document.getElementById('sidebar-backdrop').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });
    </script>

    
</body>
</html>