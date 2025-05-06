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
    // Update settings
    if (isset($_POST['update_settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $key = mysqli_real_escape_string($conn, $key);
            $value = mysqli_real_escape_string($conn, $value);
            
            $update_query = "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'";
            mysqli_query($conn, $update_query);
        }
        
        $success_msg = "Settings updated successfully!";
    }
    
    // Add new setting
    if (isset($_POST['add_setting'])) {
        $key = mysqli_real_escape_string($conn, $_POST['setting_key']);
        $value = mysqli_real_escape_string($conn, $_POST['setting_value']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        // Check if setting already exists
        $check_query = "SELECT * FROM settings WHERE setting_key = '$key'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error_msg = "Setting key '$key' already exists!";
        } else {
            $insert_query = "INSERT INTO settings (setting_key, setting_value, description) 
                            VALUES ('$key', '$value', '$description')";
            
            if (mysqli_query($conn, $insert_query)) {
                $success_msg = "Setting added successfully!";
            } else {
                $error_msg = "Error adding setting: " . mysqli_error($conn);
            }
        }
    }
}

// Delete setting
if (isset($_GET['delete'])) {
    $key = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Check if it's a system setting that shouldn't be deleted
    $system_settings = ['school_name', 'school_address', 'school_phone', 'school_email', 'school_website', 'current_academic_year'];
    
    if (in_array($key, $system_settings)) {
        $error_msg = "Cannot delete system setting '$key'!";
    } else {
        $delete_query = "DELETE FROM settings WHERE setting_key = '$key'";
        if (mysqli_query($conn, $delete_query)) {
            $success_msg = "Setting deleted successfully!";
        } else {
            $error_msg = "Error deleting setting: " . mysqli_error($conn);
        }
    }
}

// Get all settings
$settings_query = "SELECT * FROM settings ORDER BY id ASC";
$settings_result = mysqli_query($conn, $settings_query);

// Group settings by category
$general_settings = [];
$result_settings = [];
$notification_settings = [];
$other_settings = [];

while ($setting = mysqli_fetch_assoc($settings_result)) {
    if (strpos($setting['setting_key'], 'school_') === 0) {
        $general_settings[] = $setting;
    } elseif (strpos($setting['setting_key'], 'result_') === 0) {
        $result_settings[] = $setting;
    } elseif (strpos($setting['setting_key'], 'notification_') === 0) {
        $notification_settings[] = $setting;
    } else {
        $other_settings[] = $setting;
    }
}

// Reset pointer for later use
mysqli_data_seek($settings_result, 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Result Management System</title>
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
                <h1 class="text-3xl font-semibold text-gray-800 mb-6">System Settings</h1>
                
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
                    <!-- Add Setting Card -->
                    <div class="md:col-span-1">
                        <div class="bg-white rounded-lg shadow-md p-6 transition-all duration-300 hover:shadow-lg">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Setting</h2>
                            <form action="" method="POST">
                                <div class="mb-4">
                                    <label for="setting_key" class="block text-gray-700 text-sm font-bold mb-2">Setting Key*</label>
                                    <input type="text" name="setting_key" id="setting_key" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                    <p class="text-xs text-gray-500 mt-1">Example: attendance_threshold, pass_percentage, etc.</p>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="setting_value" class="block text-gray-700 text-sm font-bold mb-2">Setting Value*</label>
                                    <input type="text" name="setting_value" id="setting_value" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                                    <textarea name="description" id="description" rows="2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                                </div>
                                
                                <div class="flex items-center justify-end">
                                    <button type="submit" name="add_setting" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                                        Add Setting
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Settings List -->
                    <div class="md:col-span-2">
                        <div class="bg-white rounded-lg shadow-md p-6 transition-all duration-300 hover:shadow-lg">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">System Settings</h2>
                            
                            <form action="" method="POST">
                                <!-- General Settings -->
                                <div class="mb-6">
                                    <h3 class="text-lg font-medium text-gray-700 mb-3 border-b pb-2">General Settings</h3>
                                    <div class="space-y-4">
                                        <?php foreach ($general_settings as $setting): ?>
                                            <div class="flex flex-col">
                                                <label for="<?php echo $setting['setting_key']; ?>" class="block text-gray-700 text-sm font-bold mb-1">
                                                    <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                                </label>
                                                <?php if ($setting['setting_key'] == 'school_logo'): ?>
                                                    <div class="flex items-center space-x-2">
                                                        <input type="text" name="settings[<?php echo $setting['setting_key']; ?>]" id="<?php echo $setting['setting_key']; ?>" value="<?php echo $setting['setting_value']; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                        <?php if (!empty($setting['setting_value']) && file_exists('../uploads/logo/' . $setting['setting_value'])): ?>
                                                            <img src="../uploads/logo/<?php echo $setting['setting_value']; ?>" alt="School Logo" class="h-10">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <input type="text" name="settings[<?php echo $setting['setting_key']; ?>]" id="<?php echo $setting['setting_key']; ?>" value="<?php echo $setting['setting_value']; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                <?php endif; ?>
                                                <?php if (!empty($setting['description'])): ?>
                                                    <p class="text-xs text-gray-500 mt-1"><?php echo $setting['description']; ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Result Settings -->
                                <?php if (!empty($result_settings)): ?>
                                <div class="mb-6">
                                    <h3 class="text-lg font-medium text-gray-700 mb-3 border-b pb-2">Result Settings</h3>
                                    <div class="space-y-4">
                                        <?php foreach ($result_settings as $setting): ?>
                                            <div class="flex flex-col">
                                                <label for="<?php echo $setting['setting_key']; ?>" class="block text-gray-700 text-sm font-bold mb-1">
                                                    <?php echo ucwords(str_replace(['_', 'result'], [' ', 'Result'], $setting['setting_key'])); ?>
                                                </label>
                                                <?php if (in_array($setting['setting_value'], ['0', '1'])): ?>
                                                    <select name="settings[<?php echo $setting['setting_key']; ?>]" id="<?php echo $setting['setting_key']; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                        <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Yes</option>
                                                        <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>No</option>
                                                    </select>
                                                <?php else: ?>
                                                    <input type="text" name="settings[<?php echo $setting['setting_key']; ?>]" id="<?php echo $setting['setting_key']; ?>" value="<?php echo $setting['setting_value']; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                <?php endif; ?>
                                                <?php if (!empty($setting['description'])): ?>
                                                    <p class="text-xs text-gray-500 mt-1"><?php echo $setting['description']; ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Notification Settings -->
                                <?php if (!empty($notification_settings)): ?>
                                <div class="mb-6">
                                    <h3 class="text-lg font-medium text-gray-700 mb-3 border-b pb-2">Notification Settings</h3>
                                    <div class="space-y-4">
                                        <?php foreach ($notification_settings as $setting): ?>
                                            <div class="flex flex-col">
                                                <label for="<?php echo $setting['setting_key']; ?>" class="block text-gray-700 text-sm font-bold mb-1">
                                                    <?php echo ucwords(str_replace(['_', 'notification'], [' ', 'Notification'], $setting['setting_key'])); ?>
                                                </label>
                                                <?php if (in_array($setting['setting_value'], ['0', '1'])): ?>
                                                    <select name="settings[<?php echo $setting['setting_key']; ?>]" id="<?php echo $setting['setting_key']; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                        <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Yes</option>
                                                        <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>No</option>
                                                    </select>
                                                <?php else: ?>
                                                    <input type="text" name="settings[<?php echo $setting['setting_key']; ?>]" id="<?php echo $setting['setting_key']; ?>" value="<?php echo $setting['setting_value']; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                <?php endif; ?>
                                                <?php if (!empty($setting['description'])): ?>
                                                    <p class="text-xs text-gray-500 mt-1"><?php echo $setting['description']; ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Other Settings -->
                                <?php if (!empty($other_settings)): ?>
                                <div class="mb-6">
                                    <h3 class="text-lg font-medium text-gray-700 mb-3 border-b pb-2">Other Settings</h3>
                                    <div class="space-y-4">
                                        <?php foreach ($other_settings as $setting): ?>
                                            <div class="flex flex-col">
                                                <label for="<?php echo $setting['setting_key']; ?>" class="block text-gray-700 text-sm font-bold mb-1">
                                                    <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                                </label>
                                                <?php if (in_array($setting['setting_value'], ['0', '1'])): ?>
                                                    <select name="settings[<?php echo $setting['setting_key']; ?>]" id="<?php echo $setting['setting_key']; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                        <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Yes</option>
                                                        <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>No</option>
                                                    </select>
                                                <?php else: ?>
                                                    <input type="text" name="settings[<?php echo $setting['setting_key']; ?>]" id="<?php echo $setting['setting_key']; ?>" value="<?php echo $setting['setting_value']; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                <?php endif; ?>
                                                <?php if (!empty($setting['description'])): ?>
                                                    <p class="text-xs text-gray-500 mt-1"><?php echo $setting['description']; ?></p>
                                                <?php endif; ?>
                                                <div class="flex justify-end mt-1">
                                                    <a href="?delete=<?php echo $setting['setting_key']; ?>" onclick="return confirm('Are you sure you want to delete this setting?')" class="text-red-600 hover:text-red-800 text-sm">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="flex justify-end">
                                    <button type="submit" name="update_settings" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                                        Save All Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
