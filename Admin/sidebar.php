<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Font Awesome (make sure it's loaded in your layout) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

<div class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-64 bg-gray-900 text-white min-h-screen shadow-lg">
        
        <!-- Header -->
        <div class="flex items-center justify-center h-16 bg-gray-800 border-b border-gray-700">
            <span class="text-xl font-semibold tracking-wide">📊 Result Management</span>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-2 text-sm font-medium">

            <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-md transition hover:bg-gray-700 <?= ($current_page == 'admin_dashboard.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
              <a href="users.php" class="flex items-center gap-3 px-3 py-2 rounded-md transition hover:bg-gray-700 <?= ($current_page == 'users.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-users"></i> Users
            </a>
                        <div class="mt-6 text-xs font-semibold text-gray-400 uppercase tracking-wide">Result</div>

            <a href="result.php" class="flex items-center gap-3 px-3 py-2 rounded-md transition hover:bg-gray-700 <?= ($current_page == 'result.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-clipboard-list"></i> Results
            </a>
            <a href="manage_results.php" class="flex items-center gap-3 px-3 py-2 rounded-md transition hover:bg-gray-700 <?= ($current_page == 'manage_results.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-upload"></i> Manage Results
            </a>
        
            <!-- Academic Section -->
            <div class="mt-6 text-xs font-semibold text-gray-400 uppercase tracking-wide">Academic</div>
            
            <a href="subject.php" class="flex items-center gap-3 px-3 py-2 rounded-md transition hover:bg-gray-700 <?= ($current_page == 'subject.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-book"></i> Subjects
            </a>
            <a href="grade_sheet.php" class="flex items-center gap-3 px-3 py-2 rounded-md transition hover:bg-gray-700 <?= ($current_page == 'grade_sheet.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-file-alt"></i> Grade Sheet
            </a>
            <a href="students_result.php" class="flex items-center gap-3 px-3 py-2 rounded-md transition hover:bg-gray-700 <?= ($current_page == 'students_result.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-user-graduate"></i> Student Result
            </a>

            <!-- Management Section -->
            <div class="mt-6 text-xs font-semibold text-gray-400 uppercase tracking-wide">Management</div>
            
            <a href="classes.php" class="flex items-center gap-3 px-3 py-2 rounded-md transition hover:bg-gray-700 <?= ($current_page == 'classes.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-chalkboard-teacher"></i> Classes
            </a>
            <a href="exams.php" class="flex items-center gap-3 px-3 py-2 rounded-md transition hover:bg-gray-700 <?= ($current_page == 'exams.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-calendar-alt"></i> Exams
            </a>
            <!-- <a href="settings.php" class="flex items-center gap-3 px-3 py-2 rounded-md transition hover:bg-gray-700 <?= ($current_page == 'settings.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-cog"></i> Settings
            </a> -->
        </nav>

        <!-- Logout -->
        <div class="px-4 pb-4">
            <a href="../login.php" class="flex items-center gap-3 px-3 py-2 rounded-md text-red-400 hover:text-white hover:bg-red-600 transition">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</div>
