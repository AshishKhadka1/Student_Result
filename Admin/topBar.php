<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<!-- Top Navigation Bar -->
<div class="relative z-10 flex-shrink-0 flex h-16 bg-white dark:bg-gray-900 shadow px-4 md:px-6">
    <!-- Sidebar Toggle (Mobile) -->
    <button class="text-gray-500 dark:text-gray-300 md:hidden" id="sidebar-toggle">
        <i class="fas fa-bars fa-lg"></i>
    </button>

    <!-- Page Title -->
    <div class="flex-1 flex items-center justify-between ml-4">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Admin Dashboard</h1>

        <!-- Right Side -->
        <div class="flex items-center space-x-4">
            <!-- Dark Mode Toggle -->


            <!-- Profile Dropdown -->
            <div class="relative" id="profile-menu">
                <button class="flex items-center justify-center h-9 w-9 rounded-full bg-blue-600 text-white focus:outline-none" id="profile-button">
                    <?= isset($_SESSION['full_name']) ? substr($_SESSION['full_name'], 0, 1) : 'A'; ?>
                </button>

                <!-- Dropdown -->
                <div class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5" id="dropdown">
                    <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin User'); ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($_SESSION['email'] ?? 'admin@example.com'); ?></p>
                    </div>
                    <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-user mr-2"></i> Your Profile
                    </a>
                    <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-cog mr-2"></i> Settings
                    </a>
                    <a href="logout.php" class="block px-4 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900 dark:text-red-400">
                        <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script for Dropdown & Dark Mode -->
<script>
    // Profile dropdown
    document.getElementById('profile-button').addEventListener('click', function () {
        const dropdown = document.getElementById('dropdown');
        dropdown.classList.toggle('hidden');
    });

    // Dark mode toggle
    const toggle = document.getElementById('dark-mode-toggle');
    toggle.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark');
    });

    // Optional: Click outside to close dropdown
    window.addEventListener('click', function(e) {
        const menu = document.getElementById('profile-menu');
        const dropdown = document.getElementById('dropdown');
        if (!menu.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
</script>
