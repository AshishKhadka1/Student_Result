<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
   session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
   header("Location: ../login.php");
   exit();
}

// Get the current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-64">
        <div class="flex flex-col h-0 flex-1 bg-gray-800">
            <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                <div class="flex items-center flex-shrink-0 px-4">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <nav class="mt-5 flex-1 px-2 space-y-1">
                    <a href="teacher_dashboard.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'teacher_dashboard.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-tachometer-alt mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Dashboard
                    </a>
                    <a href="result_sheet.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'manage_results.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-graduation-cap mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Result Sheet
                    </a>
                    <a href="edit_results.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'edit_marks.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-edit mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Edit Results
                    </a>

                     <a href="grade_sheet.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'edit_marks.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-edit mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Grade Sheet
                    </a>
               
                    <a href="view_students.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'view_students.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-users mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        View Students
                    </a>
                  
                   
                    <a href="../includes/logout.php" class="group flex items-center px-2 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white mt-10">
                        <i class="fas fa-sign-out-alt mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Logout
                    </a>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Mobile menu -->
<div class="md:hidden">
    <div class="fixed inset-0 flex z-40 pointer-events-none">
        <!-- Sidebar -->
        <div id="mobile-sidebar" class="fixed inset-y-0 left-0 flex flex-col w-64 bg-gray-800 transform -translate-x-full transition ease-in-out duration-300 pointer-events-auto">
            <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                <div class="flex items-center flex-shrink-0 px-4">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <nav class="mt-5 flex-1 px-2 space-y-1">
                    <a href="teacher_dashboard.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'teacher_dashboard.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-tachometer-alt mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Dashboard
                    </a>
                    <a href="manage_results.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'manage_results.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-graduation-cap mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Manage Results
                    </a>
                    <a href="edit_result.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'edit_marks.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-edit mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Edit Result
                    </a>
                    <a href="class_performance.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'class_performance.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-chart-bar mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Class Performance
                    </a>
                    <a href="view_students.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'view_students.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-users mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        View Students
                    </a>
                    <a href="student_results.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'student_details.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-user-graduate mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Student Results
                    </a>
                 
                    <a href="../includes/logout.php" class="group flex items-center px-2 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white mt-10">
                        <i class="fas fa-sign-out-alt mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Logout
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Overlay -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-gray-600 bg-opacity-75 opacity-0 pointer-events-none transition-opacity ease-in-out duration-300"></div>
    </div>
    
    <!-- Toggle button -->
    <button id="sidebar-toggle" type="button" class="fixed bottom-4 right-4 z-50 p-2 rounded-full bg-blue-600 text-white shadow-lg hover:bg-blue-700 focus:outline-none">
        <i class="fas fa-bars"></i>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    
    if (sidebarToggle && mobileSidebar && sidebarOverlay) {
        sidebarToggle.addEventListener('click', function() {
            // Toggle sidebar
            if (mobileSidebar.classList.contains('-translate-x-full')) {
                mobileSidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
                sidebarOverlay.classList.add('opacity-100', 'pointer-events-auto');
            } else {
                mobileSidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
                sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
            }
        });
        
        // Close sidebar when clicking overlay
        sidebarOverlay.addEventListener('click', function() {
            mobileSidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
            sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
        });
    }
});
</script>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
