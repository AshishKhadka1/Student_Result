<?php
session_start();
include('../includes/config.php');
include('../includes/db_connetc.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new grade
    if (isset($_POST['add_grade'])) {
        $grade = mysqli_real_escape_string($conn, $_POST['grade']);
        $min_marks = mysqli_real_escape_string($conn, $_POST['min_marks']);
        $max_marks = mysqli_real_escape_string($conn, $_POST['max_marks']);
        $gpa = mysqli_real_escape_string($conn, $_POST['gpa']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        // Check if grade already exists
        $check_query = "SELECT * FROM grade_scale WHERE grade = '$grade'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error_msg = "Grade '$grade' already exists!";
        } else {
            $insert_query = "INSERT INTO grade_scale (grade, min_marks, max_marks, gpa, description) 
                            VALUES ('$grade', '$min_marks', '$max_marks', '$gpa', '$description')";
            
            if (mysqli_query($conn, $insert_query)) {
                $success_msg = "Grade added successfully!";
            } else {
                $error_msg = "Error adding grade: " . mysqli_error($conn);
            }
        }
    }
    
    // Update grade
    if (isset($_POST['update_grade'])) {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $grade = mysqli_real_escape_string($conn, $_POST['grade']);
        $min_marks = mysqli_real_escape_string($conn, $_POST['min_marks']);
        $max_marks = mysqli_real_escape_string($conn, $_POST['max_marks']);
        $gpa = mysqli_real_escape_string($conn, $_POST['gpa']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        // Check if grade already exists (excluding current record)
        $check_query = "SELECT * FROM grade_scale WHERE grade = '$grade' AND id != '$id'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error_msg = "Grade '$grade' already exists!";
        } else {
            $update_query = "UPDATE grade_scale SET 
                            grade = '$grade', 
                            min_marks = '$min_marks', 
                            max_marks = '$max_marks', 
                            gpa = '$gpa', 
                            description = '$description' 
                            WHERE id = '$id'";
            
            if (mysqli_query($conn, $update_query)) {
                $success_msg = "Grade updated successfully!";
            } else {
                $error_msg = "Error updating grade: " . mysqli_error($conn);
            }
        }
    }
}

// Delete grade
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Check if grade is being used in results
    $check_query = "SELECT COUNT(*) as count FROM results WHERE grade = (SELECT grade FROM grade_scale WHERE id = '$id')";
    $check_result = mysqli_query($conn, $check_query);
    $row = mysqli_fetch_assoc($check_result);
    
    if ($row['count'] > 0) {
        $error_msg = "Cannot delete grade. It is being used in student results!";
    } else {
        $delete_query = "DELETE FROM grade_scale WHERE id = '$id'";
        if (mysqli_query($conn, $delete_query)) {
            $success_msg = "Grade deleted successfully!";
        } else {
            $error_msg = "Error deleting grade: " . mysqli_error($conn);
        }
    }
}

// Get all grades
$grades_query = "SELECT * FROM grade_scale ORDER BY min_marks DESC";
$grades_result = mysqli_query($conn, $grades_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades - Result Management System</title>
    <link rel="stylesheet" href="../css/tailwind.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="flex h-screen bg-gray-100">
        <!-- Sidebar -->
        <?php include('sidebar.php'); ?>
        
        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <div class="p-6">
                <h1 class="text-3xl font-semibold text-gray-800 mb-6">Manage Grading System</h1>
                
                <!-- Notification Messages -->
                <?php if(isset($success_msg)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                        <p><?php echo $success_msg; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error_msg)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo $error_msg; ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Add Grade Card -->
                    <div class="md:col-span-1">
                        <div class="bg-white rounded-lg shadow-md p-6 transition-all duration-300 hover:shadow-lg">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Grade</h2>
                            <form action="" method="POST">
                                <div class="mb-4">
                                    <label for="grade" class="block text-gray-700 text-sm font-bold mb-2">Grade*</label>
                                    <input type="text" name="grade" id="grade" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                    <p class="text-xs text-gray-500 mt-1">Example: A+, B, C+, etc.</p>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="min_marks" class="block text-gray-700 text-sm font-bold mb-2">Minimum Marks*</label>
                                    <input type="number" name="min_marks" id="min_marks" step="0.01" min="0" max="100" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="max_marks" class="block text-gray-700 text-sm font-bold mb-2">Maximum Marks*</label>
                                    <input type="number" name="max_marks" id="max_marks" step="0.01" min="0" max="100" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="gpa" class="block text-gray-700 text-sm font-bold mb-2">GPA*</label>
                                    <input type="number" name="gpa" id="gpa" step="0.01" min="0" max="4" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                                    <input type="text" name="description" id="description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <p class="text-xs text-gray-500 mt-1">Example: Excellent, Good, Average, etc.</p>
                                </div>
                                
                                <div class="flex items-center justify-end">
                                    <button type="submit" name="add_grade" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                                        Add Grade
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Grades List -->
                    <div class="md:col-span-2">
                        <div class="bg-white rounded-lg shadow-md p-6 transition-all duration-300 hover:shadow-lg">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Existing Grades</h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white">
                                    <thead>
                                        <tr>
                                            <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Grade</th>
                                            <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Range</th>
                                            <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">GPA</th>
                                            <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Description</th>
                                            <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-700">
                                        <?php 
                                        if (mysqli_num_rows($grades_result) > 0) {
                                            while($grade = mysqli_fetch_assoc($grades_result)): 
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b border-gray-200 font-medium"><?php echo $grade['grade']; ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200"><?php echo $grade['min_marks'] . ' - ' . $grade['max_marks']; ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200"><?php echo $grade['gpa']; ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200"><?php echo $grade['description']; ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200">
                                                <div class="flex space-x-2">
                                                    <button onclick="editGrade(<?php echo htmlspecialchars(json_encode($grade)); ?>)" class="text-blue-600 hover:text-blue-800">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="?delete=<?php echo $grade['id']; ?>" onclick="return confirm('Are you sure you want to delete this grade?')" class="text-red-600 hover:text-red-800">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php 
                                            endwhile;
                                        } else {
                                        ?>
                                        <tr>
                                            <td colspan="5" class="py-4 px-4 text-center text-gray-500">No grades found. Add your first grade using the form.</td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Grade Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Grade</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="mb-4">
                    <label for="edit_grade" class="block text-gray-700 text-sm font-bold mb-2">Grade*</label>
                    <input type="text" name="grade" id="edit_grade" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                
                <div class="mb-4">
                    <label for="edit_min_marks" class="block text-gray-700 text-sm font-bold mb-2">Minimum Marks*</label>
                    <input type="number" name="min_marks" id="edit_min_marks" step="0.01" min="0" max="100" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                
                <div class="mb-4">
                    <label for="edit_max_marks" class="block text-gray-700 text-sm font-bold mb-2">Maximum Marks*</label>
                    <input type="number" name="max_marks" id="edit_max_marks" step="0.01" min="0" max="100" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                
                <div class="mb-4">
                    <label for="edit_gpa" class="block text-gray-700 text-sm font-bold mb-2">GPA*</label>
                    <input type="number" name="gpa" id="edit_gpa" step="0.01" min="0" max="4" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                
                <div class="mb-4">
                    <label for="edit_description" class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                    <input type="text" name="description" id="edit_description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </button>
                    <button type="submit" name="update_grade" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update Grade
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Edit grade modal functions
        function editGrade(grade) {
            document.getElementById('edit_id').value = grade.id;
            document.getElementById('edit_grade').value = grade.grade;
            document.getElementById('edit_min_marks').value = grade.min_marks;
            document.getElementById('edit_max_marks').value = grade.max_marks;
            document.getElementById('edit_gpa').value = grade.gpa;
            document.getElementById('edit_description').value = grade.description;
            
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
