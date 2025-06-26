<!-- Include Tailwind CSS and Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Top Navbar -->
<nav class="bg-white shadow-md px-4 py-3 flex items-center justify-between">
    <!-- Left: Logo and toggler -->
    <div class="flex items-center space-x-4">
        <!-- Mobile sidebar toggler -->
        <button id="sidebar-toggle" class="text-gray-600 hover:text-blue-600 focus:outline-none md:hidden">
            <i class="fas fa-bars text-lg"></i>
        </button>

        <!-- Logo -->
    <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Teacher Dashboard</h1>
    </div>

    <!-- Right: Profile dropdown -->
    <div class="relative">
        <button id="profileDropdownBtn" class="focus:outline-none">
            <img src="#" alt="User" class="h-10 w-10 rounded-full border">
        </button>

        <!-- Dropdown Menu -->
        <div id="profileDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg hidden z-50">
            <ul class="py-2 text-sm text-gray-700">
                <li>
                    <a href="teacher_profile.php" class="flex items-center px-4 py-2 hover:bg-gray-100">
                        <i class="fas fa-user mr-2 text-gray-500"></i> My Profile
                    </a>
                </li>
                <li>
                    <a href="logout.php" class="flex items-center px-4 py-2 hover:bg-gray-100">
                        <i class="fa fa-power-off mr-2 text-gray-500"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Profile Dropdown Script -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('profileDropdownBtn');
        const dropdown = document.getElementById('profileDropdown');

        btn.addEventListener('click', () => {
            dropdown.classList.toggle('hidden');
        });

        window.addEventListener('click', function (e) {
            if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    });
</script>
