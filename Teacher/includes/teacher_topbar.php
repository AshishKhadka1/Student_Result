<div class="sticky top-0 z-10 flex-shrink-0 flex h-16 bg-white shadow">
    <button type="button" class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 md:hidden" id="sidebar-toggle">
        <span class="sr-only">Open sidebar</span>
        <i class="fas fa-bars"></i>
    </button>
    <div class="flex-1 px-4 flex justify-between">
        <div class="flex-1 flex items-center">
            <div class="max-w-7xl">
                <h1 class="text-xl font-semibold text-gray-900">Teacher Dashboard</h1>
            </div>
        </div>
        <div class="ml-4 flex items-center md:ml-6">
            <!-- Dark Mode Toggle -->
            <button id="dark-mode-toggle" class="bg-gray-200 relative inline-flex h-6 w-11 items-center rounded-full mx-3">
                <span class="sr-only">Toggle dark mode</span>
                <span id="dark-mode-toggle-dot" class="inline-block h-4 w-4 transform rounded-full bg-white transition translate-x-0.5"></span>
            </button>
            
            <!-- Notifications Dropdown -->
            <div class="ml-3 relative">
                <div>
                    <button type="button" class="bg-white p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <span class="sr-only">View notifications</span>
                        <i class="fas fa-bell"></i>
                    </button>
                </div>
            </div>
            
            <!-- Profile dropdown -->
            <div class="ml-3 relative">
                <div>
                    <button type="button" class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                        <span class="sr-only">Open user menu</span>
                        <?php if (isset($teacher['profile_image']) && !empty($teacher['profile_image'])): ?>
                            <img class="h-8 w-8 rounded-full" src="<?php echo htmlspecialchars($teacher['profile_image']); ?>" alt="Profile image">
                        <?php else: ?>
                            <div class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center text-white">
                                <?php 
                                $initials = 'T';
                                if (isset($teacher['full_name']) && !empty($teacher['full_name'])) {
                                    $name_parts = explode(' ', $teacher['full_name']);
                                    $initials = strtoupper(substr($name_parts[0], 0, 1));
                                    if (count($name_parts) > 1) {
                                        $initials .= strtoupper(substr(end($name_parts), 0, 1));
                                    }
                                }
                                echo $initials;
                                ?>
                            </div>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
