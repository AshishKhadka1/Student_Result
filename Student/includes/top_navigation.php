<div class="sticky top-0 z-10 flex-shrink-0 flex h-16 bg-white shadow">
    <button type="button" class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 md:hidden" id="sidebar-toggle">
        <span class="sr-only">Open sidebar</span>
        <i class="fas fa-bars"></i>
    </button>
    <div class="flex-1 px-4 flex justify-between">
        <div class="flex-1 flex items-center">
            <div class="max-w-7xl">
                <h1 class="text-xl font-semibold text-gray-900">Student Portal</h1>
            </div>
        </div>
        <div class="ml-4 flex items-center md:ml-6">
            <!-- Notification dropdown -->
            <div class="ml-3 relative">
                <button type="button" class="bg-white p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="notification-button">
                    <span class="sr-only">View notifications</span>
                    <i class="fas fa-bell"></i>
                </button>
                <div class="origin-top-right absolute right-0 mt-2 w-80 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none hidden" id="notification-dropdown">
                    <div class="px-4 py-2 border-b border-gray-200">
                        <h3 class="text-sm font-medium text-gray-900">Notifications</h3>
                    </div>
                    <div class="max-h-60 overflow-y-auto">
                        <!-- Notification items would go here -->
                        <div class="px-4 py-2 text-sm text-gray-500">No new notifications</div>
                    </div>
                    <div class="px-4 py-2 border-t border-gray-200 text-sm text-blue-600 hover:text-blue-500">
                        <a href="#">View all notifications</a>
                    </div>
                </div>
            </div>

            <!-- Dark mode toggle -->
            <div class="ml-3 relative">
                <button type="button" class="bg-white p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="dark-mode-toggle">
                    <span class="sr-only">Toggle dark mode</span>
                    <div class="w-10 h-5 bg-gray-200 rounded-full flex items-center p-0.5">
                        <div class="w-4 h-4 bg-white rounded-full transform translate-x-0.5 transition-transform duration-300" id="dark-mode-toggle-dot"></div>
                    </div>
                </button>
            </div>

            <!-- Profile dropdown -->
            <div class="ml-3 relative">
                <div>
                    <button type="button" class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                        <span class="sr-only">Open user menu</span>
                        <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white">
                            <?php echo substr($_SESSION['full_name'] ?? 'U', 0, 1); ?>
                        </div>
                    </button>
                </div>
                <div class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none hidden" id="user-menu">
                    <a href="student_profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Profile</a>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                    <a href="../includes/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign out</a>
                </div>
            </div>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
