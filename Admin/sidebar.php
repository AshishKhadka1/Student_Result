<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-60 bg-gray-900 text-white">
        <div class="flex items-center justify-center h-16 bg-gray-800 shadow">
            <span class="text-lg font-bold">Result Management</span>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto text-sm font-medium">
            <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'admin_dashboard.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="result.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'result.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-clipboard-list"></i> Results
            </a>
            <a href="bulk_upload.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'bulk_upload.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-upload"></i> Bulk Upload
            </a>
            <a href="users.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'users.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-users"></i> Users
            </a>

            <div class="mt-4 text-xs font-semibold text-gray-400 uppercase tracking-wide">Academic</div>

            <a href="subject.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'subject.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-book"></i> Subjects
            </a>
            <a href="grade_sheet.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'grade_sheet.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-file-alt"></i> Grade Sheet
            </a>
            <a href="result_sheet_template.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'result_sheet_template.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-file-invoice"></i> Result Sheet
            </a>
            <a href="students_result.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'students_result.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-user-graduate"></i> Student Result
            </a>
            
            <div class="mt-4 text-xs font-semibold text-gray-400 uppercase tracking-wide">Management</div>

            <a href="classes.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'classes.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-chalkboard"></i> Classes
            </a>
            <a href="exams.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'exams.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-calendar-alt"></i> Exams
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'reports.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= ($current_page == 'settings.php') ? 'bg-gray-700' : '' ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
        <div class="px-4 pb-4">
            <a href="../login.php" class="flex items-center gap-3 px-3 py-2 rounded-md text-red-300 hover:text-white hover:bg-red-600 transition">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</div>
