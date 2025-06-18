<div class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-64">
        <div class="flex flex-col h-0 flex-1 bg-gray-800">
            <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                <div class="flex items-center flex-shrink-0 px-4">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <nav class="mt-5 flex-1 px-2 space-y-1">
                    <a href="student_dashboard.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'student_dashboard.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-tachometer-alt mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Dashboard
                    </a>
                    <a href="grade_sheet.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo basename($_SERVER['PHP_SELF']) == 'grade_sheet.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <i class="fas fa-file-alt mr-3 text-gray-400 group-hover:text-gray-300"></i>
                        Grade Sheet
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
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
