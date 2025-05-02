<div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Admin Dashboard</h1>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
                        <!-- Search button -->
                        <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3" id="search-button">
                            <span class="sr-only">Search</span>
                            <i class="fas fa-search"></i>
                        </button>

                        <!-- Search modal -->
                        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden" id="search-modal">
                            <div class="flex items-center justify-center min-h-screen p-4">
                                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-medium">Search</h3>
                                        <button id="close-search" class="text-gray-400 hover:text-gray-500">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="relative">
                                        <input type="text" id="global-search" class="w-full border border-gray-300 rounded-md py-2 px-4 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Search for students, classes, exams...">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-search text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="mt-4 max-h-60 overflow-y-auto" id="search-results">
                                        <!-- Search results will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dark mode toggle for mobile -->
                        <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3 md:hidden" id="mobile-dark-mode-toggle">
                            <span class="sr-only">Toggle dark mode</span>
                            <i class="fas fa-moon"></i>
                        </button>

                        <!-- Notification button -->
                        <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 relative" id="notification-button">
                            <span class="sr-only">View notifications</span>
                            <i class="fas fa-bell"></i>
                            <?php if (!empty($notifications)): ?>
                                <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-400 ring-2 ring-white"></span>
                            <?php endif; ?>
                        </button>

                        <!-- Notification dropdown -->
                        <div class="hidden origin-top-right absolute right-0 mt-2 w-80 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" id="notification-dropdown" style="top: 3rem; right: 1rem;">
                            <div class="px-4 py-2 border-b border-gray-200">
                                <h3 class="text-sm font-medium text-gray-700">Notifications</h3>
                            </div>
                            <div class="max-h-60 overflow-y-auto">
                                <?php if (empty($notifications)): ?>
                                    <p class="px-4 py-2 text-sm text-gray-500">No new notifications.</p>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="px-4 py-2 hover:bg-gray-50 border-b border-gray-100">
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <p class="text-xs text-gray-400 mt-1"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="px-4 py-2 border-t border-gray-200">
                                <a href="notifications.php" class="text-xs text-blue-600 hover:text-blue-500">View all notifications</a>
                            </div>
                        </div>

                        <!-- Profile dropdown -->
                        <div class="ml-3 relative">
                            <div>
                                <button type="button"
                                    class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    id="user-menu-button"
                                    aria-expanded="false"
                                    aria-haspopup="true">
                                    <span class="sr-only">Open user menu</span>
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-600">
                                        <span class="text-sm font-medium leading-none text-white">
                                            <?php echo isset($_SESSION['full_name']) ? substr($_SESSION['full_name'], 0, 1) : 'A'; ?>
                                        </span>
                                    </span>
                                </button>
                            </div>

                            <!-- Profile dropdown menu -->
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none"
                                id="user-menu"
                                role="menu"
                                aria-orientation="vertical"
                                aria-labelledby="user-menu-button"
                                tabindex="-1">
                                <div class="px-4 py-2 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900"><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin User'; ?></p>
                                    <p class="text-xs text-gray-500"><?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'admin@example.com'; ?></p>
                                </div>
                                <a href="profile.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                    role="menuitem"
                                    tabindex="-1">
                                    <i class="fas fa-user mr-2"></i> Your Profile
                                </a>
                                <a href="settings.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                    role="menuitem"
                                    tabindex="-1">
                                    <i class="fas fa-cog mr-2"></i> Settings
                                </a>
                                <a href="logout.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                    role="menuitem"
                                    tabindex="-1">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>