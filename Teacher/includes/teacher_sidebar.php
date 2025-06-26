<!-- Desktop Sidebar -->
<div class="hidden md:flex md:w-64 md:flex-col bg-gray-800 min-h-screen">
    <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
        <div class="flex items-center justify-center px-4 mb-4">
            <span class="text-white text-lg font-semibold">Result Management</span>
        </div>
        <nav class="flex-1 px-2 space-y-1">
            <?php
            function active($file) {
                return basename($_SERVER['PHP_SELF']) == $file ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white';
            }
            ?>
            <a href="teacher_dashboard.php" class="group flex items-center w-full px-4 py-2 text-sm font-medium rounded-md <?php echo active('teacher_dashboard.php'); ?>">
                <i class="fas fa-tachometer-alt mr-3 text-gray-400 group-hover:text-gray-300"></i>
                Dashboard
            </a>
            <a href="edit_results.php" class="group flex items-center w-full px-4 py-2 text-sm font-medium rounded-md <?php echo active('edit_results.php'); ?>">
                <i class="fas fa-edit mr-3 text-gray-400 group-hover:text-gray-300"></i>
                Edit Results
            </a>
            <a href="grade_sheet.php" class="group flex items-center w-full px-4 py-2 text-sm font-medium rounded-md <?php echo active('grade_sheet.php'); ?>">
                <i class="fas fa-graduation-cap mr-3 text-gray-400 group-hover:text-gray-300"></i>
                Grade Sheet
            </a>
            <a href="view_students.php" class="group flex items-center w-full px-4 py-2 text-sm font-medium rounded-md <?php echo active('view_students.php'); ?>">
                <i class="fas fa-users mr-3 text-gray-400 group-hover:text-gray-300"></i>
                View Students
            </a>
            <a href="../includes/logout.php" class="group flex items-center w-full px-4 py-2 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-700 hover:text-white mt-10">
                <i class="fas fa-sign-out-alt mr-3 text-gray-400 group-hover:text-gray-300"></i>
                Logout
            </a>
        </nav>
    </div>
</div>

<!-- Mobile Sidebar -->
<div class="md:hidden">
    <div class="fixed inset-0 z-40 flex">
        <div id="mobile-sidebar" class="fixed inset-y-0 left-0 w-64 bg-gray-800 transform -translate-x-full transition duration-300 ease-in-out">
            <div class="pt-5 pb-4">
                <div class="flex items-center justify-center px-4">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <nav class="mt-5 px-2 space-y-1">
                    <a href="teacher_dashboard.php" class="group flex items-center w-full px-4 py-2 text-sm font-medium rounded-md <?php echo active('teacher_dashboard.php'); ?>">
                        <i class="fas fa-tachometer-alt mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Dashboard
                    </a>
                    <a href="edit_results.php" class="group flex items-center w-full px-4 py-2 text-sm font-medium rounded-md <?php echo active('edit_results.php'); ?>">
                        <i class="fas fa-edit mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Edit Results
                    </a>
                    <a href="grade_sheet.php" class="group flex items-center w-full px-4 py-2 text-sm font-medium rounded-md <?php echo active('grade_sheet.php'); ?>">
                        <i class="fas fa-graduation-cap mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Grade Sheet
                    </a>
                    <a href="view_students.php" class="group flex items-center w-full px-4 py-2 text-sm font-medium rounded-md <?php echo active('view_students.php'); ?>">
                        <i class="fas fa-users mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        View Students
                    </a>
                    <a href="../includes/logout.php" class="group flex items-center w-full px-4 py-2 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-700 hover:text-white mt-10">
                        <i class="fas fa-sign-out-alt mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Logout
                    </a>
                </nav>
            </div>
        </div>
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 opacity-0 pointer-events-none transition-opacity"></div>
    </div>
    <button id="sidebar-toggle" class="fixed bottom-4 right-4 z-50 p-2 rounded-full bg-blue-600 text-white shadow-lg hover:bg-blue-700 focus:outline-none">
        <i class="fas fa-bars"></i>
    </button>
</div>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">