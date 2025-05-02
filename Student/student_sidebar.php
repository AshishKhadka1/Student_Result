<div class="flex flex-col w-64 bg-gray-800">
   <div class="flex items-center justify-center h-16 bg-gray-900">
     <span class="text-white text-lg font-semibold">Result Management</span>
   </div>
   <div class="flex flex-col flex-grow px-4 mt-5">
     <nav class="flex-1 space-y-1">
       <a href="student_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-md">
         <i class="fas fa-tachometer-alt mr-3"></i>
         Dashboard
       </a>
       <a href="view_result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
         <i class="fas fa-clipboard-list mr-3"></i>
         My Results
       </a>
       <!-- Add links for Subjects and Exam Schedule, if needed -->
       <a href="#" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
         <i class="fas fa-book mr-3"></i>
         Subjects
       </a>
       <a href="#" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
         <i class="fas fa-calendar-alt mr-3"></i>
         Exam Schedule
       </a>
       <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
         <i class="fas fa-cog mr-3"></i>
         Settings
       </a>
     </nav>
     <div class="flex-shrink-0 block w-full">
       <a href="../includes/logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
         <i class="fas fa-sign-out-alt mr-3"></i>
         Logout
       </a>
     </div>
   </div>
 </div>
