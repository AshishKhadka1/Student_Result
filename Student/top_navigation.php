<div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
  <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden">
    <i class="fas fa-bars"></i>
  </button>
  <div class="flex-1 px-4 flex justify-between">
    <div class="flex-1 flex">
      <div class="w-full flex md:ml-0">
        <h1 class="text-2xl font-semibold text-gray-900 my-auto">Student Dashboard</h1>
      </div>
    </div>
    <div class="ml-4 flex items-center md:ml-6">
      <!-- Profile dropdown -->
      <div class="ml-3 relative">
        <div>
          <button type="button" class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="user-menu-button">
            <span class="sr-only">Open user menu</span>
            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-600">
              <span class="text-sm font-medium leading-none text-white"><?php echo substr($_SESSION['full_name'], 0, 1); ?></span>
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
