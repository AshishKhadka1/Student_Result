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
    // Add new template
    if (isset($_POST['add_template'])) {
        $template_name = mysqli_real_escape_string($conn, $_POST['template_name']);
        $header_text = mysqli_real_escape_string($conn, $_POST['header_text']);
        $footer_text = mysqli_real_escape_string($conn, $_POST['footer_text']);
        $show_grade = isset($_POST['show_grade']) ? 1 : 0;
        $show_percentage = isset($_POST['show_percentage']) ? 1 : 0;
        $show_rank = isset($_POST['show_rank']) ? 1 : 0;
        $show_attendance = isset($_POST['show_attendance']) ? 1 : 0;
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // Check if template name already exists
        $check_query = "SELECT * FROM result_templates WHERE template_name = '$template_name'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error_msg = "Template name '$template_name' already exists!";
        } else {
            // If this is set as default, unset all other defaults
            if ($is_default) {
                $update_query = "UPDATE result_templates SET is_default = 0";
                mysqli_query($conn, $update_query);
            }
            
            // Handle logo upload if provided
            $logo = NULL;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $upload_dir = '../uploads/templates/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = time() . '_' . $_FILES['logo']['name'];
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                    $logo = $file_name;
                }
            }
            
            // Handle signature upload if provided
            $signature = NULL;
            if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
                $upload_dir = '../uploads/templates/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = time() . '_' . $_FILES['signature']['name'];
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['signature']['tmp_name'], $upload_path)) {
                    $signature = $file_name;
                }
            }
            
            $insert_query = "INSERT INTO result_templates (template_name, header_text, footer_text, logo, signature, show_grade, show_percentage, show_rank, show_attendance, is_default) 
                            VALUES ('$template_name', '$header_text', '$footer_text', '$logo', '$signature', '$show_grade', '$show_percentage', '$show_rank', '$show_attendance', '$is_default')";
            
            if (mysqli_query($conn, $insert_query)) {
                $success_msg = "Template added successfully!";
            } else {
                $error_msg = "Error adding template: " . mysqli_error($conn);
            }
        }
    }
    
    // Update template
    if (isset($_POST['update_template'])) {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $template_name = mysqli_real_escape_string($conn, $_POST['template_name']);
        $header_text = mysqli_real_escape_string($conn, $_POST['header_text']);
        $footer_text = mysqli_real_escape_string($conn, $_POST['footer_text']);
        $show_grade = isset($_POST['show_grade']) ? 1 : 0;
        $show_percentage = isset($_POST['show_percentage']) ? 1 : 0;
        $show_rank = isset($_POST['show_rank']) ? 1 : 0;
        $show_attendance = isset($_POST['show_attendance']) ? 1 : 0;
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // Check if template name already exists (excluding current record)
        $check_query = "SELECT * FROM result_templates WHERE template_name = '$template_name' AND id != '$id'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error_msg = "Template name '$template_name' already exists!";
        } else {
            // If this is set as default, unset all other defaults
            if ($is_default) {
                $update_query = "UPDATE result_templates SET is_default = 0";
                mysqli_query($conn, $update_query);
            }
            
            // Get current template data
            $template_query = "SELECT * FROM result_templates WHERE id = '$id'";
            $template_result = mysqli_query($conn, $template_query);
            $template = mysqli_fetch_assoc($template_result);
            
            // Handle logo upload if provided
            $logo = $template['logo'];
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $upload_dir = '../uploads/templates/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = time() . '_' . $_FILES['logo']['name'];
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                    // Delete old logo if exists
                    if ($logo && file_exists($upload_dir . $logo)) {
                        unlink($upload_dir . $logo);
                    }
                    $logo = $file_name;
                }
            }
            
            // Handle signature upload if provided
            $signature = $template['signature'];
            if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
                $upload_dir = '../uploads/templates/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = time() . '_' . $_FILES['signature']['name'];
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['signature']['tmp_name'], $upload_path)) {
                    // Delete old signature if exists
                    if ($signature && file_exists($upload_dir . $signature)) {
                        unlink($upload_dir . $signature);
                    }
                    $signature = $file_name;
                }
            }
            
            $update_query = "UPDATE result_templates SET 
                            template_name = '$template_name', 
                            header_text = '$header_text', 
                            footer_text = '$footer_text', 
                            logo = '$logo', 
                            signature = '$signature', 
                            show_grade = '$show_grade', 
                            show_percentage = '$show_percentage', 
                            show_rank = '$show_rank', 
                            show_attendance = '$show_attendance', 
                            is_default = '$is_default' 
                            WHERE id = '$id'";
            
            if (mysqli_query($conn, $update_query)) {
                $success_msg = "Template updated successfully!";
            } else {
                $error_msg = "Error updating template: " . mysqli_error($conn);
            }
        }
    }
}

// Delete template
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Check if it's the default template
    $check_query = "SELECT * FROM result_templates WHERE id = '$id'";
    $check_result = mysqli_query($conn, $check_query);
    $template = mysqli_fetch_assoc($check_result);
    
    if ($template['is_default']) {
        $error_msg = "Cannot delete the default template!";
    } else {
        // Delete associated files
        $upload_dir = '../uploads/templates/';
        if ($template['logo'] && file_exists($upload_dir . $template['logo'])) {
            unlink($upload_dir . $template['logo']);
        }
        if ($template['signature'] && file_exists($upload_dir . $template['signature'])) {
            unlink($upload_dir . $template['signature']);
        }
        
        $delete_query = "DELETE FROM result_templates WHERE id = '$id'";
        if (mysqli_query($conn, $delete_query)) {
            $success_msg = "Template deleted successfully!";
        } else {
            $error_msg = "Error deleting template: " . mysqli_error($conn);
        }
    }
}

// Get all templates
$templates_query = "SELECT * FROM result_templates ORDER BY is_default DESC, template_name ASC";
$templates_result = mysqli_query($conn, $templates_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Result Templates - Result Management System</title>
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
                <h1 class="text-3xl font-semibold text-gray-800 mb-6">Manage Result Templates</h1>
                
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
                    <!-- Add Template Card -->
                    <div class="md:col-span-1">
                        <div class="bg-white rounded-lg shadow-md p-6 transition-all duration-300 hover:shadow-lg">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Template</h2>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <label for="template_name" class="block text-gray-700 text-sm font-bold mb-2">Template Name*</label>
                                    <input type="text" name="template_name" id="template_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="header_text" class="block text-gray-700 text-sm font-bold mb-2">Header Text</label>
                                    <textarea name="header_text" id="header_text" rows="2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="footer_text" class="block text-gray-700 text-sm font-bold mb-2">Footer Text</label>
                                    <textarea name="footer_text" id="footer_text" rows="2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="logo" class="block text-gray-700 text-sm font-bold mb-2">Logo</label>
                                    <input type="file" name="logo" id="logo" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" accept="image/*">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="signature" class="block text-gray-700 text-sm font-bold mb-2">Signature</label>
                                    <input type="file" name="signature" id="signature" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" accept="image/*">
                                </div>
                                
                                <div class="mb-4">
                                    <h3 class="block text-gray-700 text-sm font-bold mb-2">Display Options</h3>
                                    <div class="space-y-2">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="show_grade" id="show_grade" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" checked>
                                            <label for="show_grade" class="ml-2 block text-sm text-gray-700">Show Grade</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="checkbox" name="show_percentage" id="show_percentage" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" checked>
                                            <label for="show_percentage" class="ml-2 block text-sm text-gray-700">Show Percentage</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="checkbox" name="show_rank" id="show_rank" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" checked>
                                            <label for="show_rank" class="ml-2 block text-sm text-gray-700">Show Rank</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="checkbox" name="show_attendance" id="show_attendance" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label for="show_attendance" class="ml-2 block text-sm text-gray-700">Show Attendance</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="checkbox" name="is_default" id="is_default" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label for="is_default" class="ml-2 block text-sm text-gray-700">Set as Default Template</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-end">
                                    <button type="submit" name="add_template" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                                        Add Template
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Templates List -->
                    <div class="md:col-span-2">
                        <div class="bg-white rounded-lg shadow-md p-6 transition-all duration-300 hover:shadow-lg">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Existing Templates</h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white">
                                    <thead>
                                        <tr>
                                            <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Template Name</th>
                                            <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Header</th>
                                            <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Display Options</th>
                                            <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                            <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-700">
                                        <?php 
                                        if (mysqli_num_rows($templates_result) > 0) {
                                            while($template = mysqli_fetch_assoc($templates_result)): 
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b border-gray-200 font-medium">
                                                <?php echo $template['template_name']; ?>
                                                <?php if ($template['is_default']): ?>
                                                    <span class="ml-2 bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded">Default</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 border-b border-gray-200">
                                                <div class="truncate max-w-xs"><?php echo $template['header_text']; ?></div>
                                            </td>
                                            <td class="py-3 px-4 border-b border-gray-200">
                                                <div class="flex flex-wrap gap-1">
                                                    <?php if ($template['show_grade']): ?>
                                                        <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded">Grade</span>
                                                    <?php endif; ?>
                                                    <?php if ($template['show_percentage']): ?>
                                                        <span class="bg-purple-100 text-purple-800 text-xs font-semibold px-2 py-0.5 rounded">Percentage</span>
                                                    <?php endif; ?>
                                                    <?php if ($template['show_rank']): ?>
                                                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded">Rank</span>
                                                    <?php endif; ?>
                                                    <?php if ($template['show_attendance']): ?>
                                                        <span class="bg-indigo-100 text-indigo-800 text-xs font-semibold px-2 py-0.5 rounded">Attendance</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 border-b border-gray-200">
                                                <?php if ($template['is_default']): ?>
                                                    <span class="text-green-600">Active</span>
                                                <?php else: ?>
                                                    <span class="text-gray-500">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 border-b border-gray-200">
                                                <div class="flex space-x-2">
                                                    <button onclick="editTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)" class="text-blue-600 hover:text-blue-800">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="?delete=<?php echo $template['id']; ?>" onclick="return confirm('Are you sure you want to delete this template?')" class="text-red-600 hover:text-red-800 <?php echo $template['is_default'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $template['is_default'] ? 'onclick="return false;"' : ''; ?>>
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <a href="preview_template.php?id=<?php echo $template['id']; ?>" target="_blank" class="text-green-600 hover:text-green-800">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php 
                                            endwhile;
                                        } else {
                                        ?>
                                        <tr>
                                            <td colspan="5" class="py-4 px-4 text-center text-gray-500">No templates found. Add your first template using the form.</td>
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
    
    <!-- Edit Template Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Template</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="mb-4">
                    <label for="edit_template_name" class="block text-gray-700 text-sm font-bold mb-2">Template Name*</label>
                    <input type="text" name="template_name" id="edit_template_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                
                <div class="mb-4">
                    <label for="edit_header_text" class="block text-gray-700 text-sm font-bold mb-2">Header Text</label>
                    <textarea name="header_text" id="edit_header_text" rows="2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="edit_footer_text" class="block text-gray-700 text-sm font-bold mb-2">Footer Text</label>
                    <textarea name="footer_text" id="edit_footer_text" rows="2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="edit_logo" class="block text-gray-700 text-sm font-bold mb-2">Logo</label>
                    <input type="file" name="logo" id="edit_logo" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" accept="image/*">
                    <div id="current_logo_container" class="mt-2 hidden">
                        <p class="text-xs text-gray-500">Current Logo:</p>
                        <img id="current_logo" src="/placeholder.svg" alt="Current Logo" class="h-10 mt-1">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="edit_signature" class="block text-gray-700 text-sm font-bold mb-2">Signature</label>
                    <input type="file" name="signature" id="edit_signature" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" accept="image/*">
                    <div id="current_signature_container" class="mt-2 hidden">
                        <p class="text-xs text-gray-500">Current Signature:</p>
                        <img id="current_signature" src="/placeholder.svg" alt="Current Signature" class="h-10 mt-1">
                    </div>
                </div>
                
                <div class="mb-4">
                    <h3 class="block text-gray-700 text-sm font-bold mb-2">Display Options</h3>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input type="checkbox" name="show_grade" id="edit_show_grade" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="edit_show_grade" class="ml-2 block text-sm text-gray-700">Show Grade</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="show_percentage" id="edit_show_percentage" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="edit_show_percentage" class="ml-2 block text-sm text-gray-700">Show Percentage</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="show_rank" id="edit_show_rank" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="edit_show_rank" class="ml-2 block text-sm text-gray-700">Show Rank</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="show_attendance" id="edit_show_attendance" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="edit_show_attendance" class="ml-2 block text-sm text-gray-700">Show Attendance</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_default" id="edit_is_default" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="edit_is_default" class="ml-2 block text-sm text-gray-700">Set as Default Template</label>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </button>
                    <button type="submit" name="update_template" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update Template
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Edit template modal functions
        function editTemplate(template) {
            document.getElementById('edit_id').value = template.id;
            document.getElementById('edit_template_name').value = template.template_name;
            document.getElementById('edit_header_text').value = template.header_text;
            document.getElementById('edit_footer_text').value = template.footer_text;
            
            document.getElementById('edit_show_grade').checked = template.show_grade == 1;
            document.getElementById('edit_show_percentage').checked = template.show_percentage == 1;
            document.getElementById('edit_show_rank').checked = template.show_rank == 1;
            document.getElementById('edit_show_attendance').checked = template.show_attendance == 1;
            document.getElementById('edit_is_default').checked = template.is_default == 1;
            
            // Show current logo if exists
            if (template.logo) {
                document.getElementById('current_logo_container').classList.remove('hidden');
                document.getElementById('current_logo').src = '../uploads/templates/' + template.logo;
            } else {
                document.getElementById('current_logo_container').classList.add('hidden');
            }
            
            // Show current signature if exists
            if (template.signature) {
                document.getElementById('current_signature_container').classList.remove('hidden');
                document.getElementById('current_signature').src = '../uploads/templates/' + template.signature;
            } else {
                document.getElementById('current_signature_container').classList.add('hidden');
            }
            
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
