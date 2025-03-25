<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to access this page.";
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Get dashboard statistics
$studentsQuery = "SELECT COUNT(*) as total FROM Users WHERE role = 'student'";
$teachersQuery = "SELECT COUNT(*) as total FROM Users WHERE role = 'teacher'";
$resultsQuery = "SELECT COUNT(*) as total FROM Results";

$studentsResult = $conn->query($studentsQuery);
$teachersResult = $conn->query($teachersQuery);
$resultsResult = $conn->query($resultsQuery);

$totalStudents = 0;
$totalTeachers = 0;
$totalResults = 0;

if ($studentsResult && $studentsResult->num_rows > 0) {
    $totalStudents = $studentsResult->fetch_assoc()['total'];
}

if ($teachersResult && $teachersResult->num_rows > 0) {
    $totalTeachers = $teachersResult->fetch_assoc()['total'];
}

if ($resultsResult && $resultsResult->num_rows > 0) {
    $totalResults = $resultsResult->fetch_assoc()['total'];
}

// Get recent results
$recentResultsQuery = "SELECT * FROM ResultUploads ORDER BY upload_date DESC LIMIT 3";
$recentResults = $conn->query($recentResultsQuery);

// Get top performing students
$topStudentsQuery = "
    SELECT u.full_name, AVG(r.gpa) as avg_gpa 
    FROM Results r
    JOIN Students s ON r.student_id = s.student_id
    JOIN Users u ON s.user_id = u.user_id
    GROUP BY r.student_id
    ORDER BY avg_gpa DESC
    LIMIT 5
";
$topStudents = $conn->query($topStudentsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-light">
    <div class="min-h-screen flex">
        <div class="w-64 bg-blue-900 text-white">
            <div class="p-6">
                <div class="flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <h1 class="text-xl font-semibold">Result Manager</h1>
                </div>
            </div>
            <nav class="mt-6">
                <div class="px-6 py-3 bg-blue-800">
                    <div class="flex items-center space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <a href="admin_dashboard.php" class="block">Dashboard</a>
                    </div>
                </div>
                <div class="px-6 py-3 hover:bg-blue-800 transition duration-200">
                    <div class="flex items-center space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <a href="manage_results.php" class="block">Results</a>
                    </div>
                </div>
                <div class="px-6 py-3 hover:bg-blue-800 transition duration-200">
                    <div class="flex items-center space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <a href="manage_users.php" class="block">Users</a>
                    </div>
                </div>
                <div class="px-6 py-3 hover:bg-blue-800 transition duration-200">
                    <div class="flex items-center space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <a href="generate_reports.php" class="block">Reports</a>
                    </div>
                </div>
                <div class="px-6 py-3 hover:bg-blue-800 transition duration-200">
                    <div class="flex items-center space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <a href="settings.php" class="block">Settings</a>
                    </div>
                </div>
                <div class="px-6 py-3 hover:bg-blue-800 transition duration-200">
                    <div class="flex items-center space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <a href="logout.php" class="block">Logout</a>
                    </div>
                </div>
            </nav>
        </div>

        <div class="flex-1 overflow-x-hidden overflow-y-auto">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm">
                <div class="flex items-center justify-between px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Admin Dashboard</h2>
                    <div class="flex items-center space-x-4">
                        <button class="text-gray-500 hover:text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </button>
                        <div class="flex items-center space-x-2">
                            <div class="h-8 w-8 rounded-full bg-blue-700 flex items-center justify-center text-white">
                                <?php echo substr($_SESSION['username'] ?? 'A', 0, 1); ?>
                            </div>
                            <span class="text-gray-700"><?php echo $_SESSION['username'] ?? 'Admin'; ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6">
                <!-- Welcome Message -->
                <div class="mb-8">
                    <h1 class="text-2xl font-semibold text-gray-800 mb-2">Welcome, <?php echo $_SESSION['username'] ?? 'Admin'; ?></h1>
                </div>             

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="rounded-full bg-blue-100 p-3 mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Total Students</p>
                                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($totalStudents); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="rounded-full bg-green-100 p-3 mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M12 14l9-5-9-5-9 5 9 5z" />
                                    <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Total Teachers</p>
                                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($totalTeachers); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="rounded-full bg-purple-100 p-3 mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Results Published</p>
                                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($totalResults); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="rounded-full bg-yellow-100 p-3 mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Last Update</p>
                                <p class="text-2xl font-semibold text-gray-800"><?php echo date('M d, Y'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Performance Overview Chart -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Performance Overview</h3>
                            <div class="h-64">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Top Performing Students -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Performing Students</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Student Name
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Average GPA
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if ($topStudents && $topStudents->num_rows > 0): ?>
                                            <?php while ($row = $topStudents->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-800 font-semibold">
                                                                <?php echo substr($row['full_name'], 0, 1); ?>
                                                            </div>
                                                            <span class="ml-3 text-sm text-gray-900"><?php echo htmlspecialchars($row['full_name']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            <?php echo number_format($row['avg_gpa'], 2); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" colspan="2">
                                                    No data available.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Results Section -->
                <div class="mt-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent Results</h2>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            File Name
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date Uploaded
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Students
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($recentResults && $recentResults->num_rows > 0): ?>
                                        <?php while ($row = $recentResults->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        <span class="text-sm text-gray-900"><?php echo htmlspecialchars($row['file_name']); ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo date('F d, Y', strtotime($row['upload_date'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo number_format($row['student_count']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $row['status'] == 'Published' ? 'green' : 'yellow'; ?>-100 text-<?php echo $row['status'] == 'Published' ? 'green' : 'yellow'; ?>-800">
                                                        <?php echo htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <a href="view_result.php?id=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                                    <a href="delete_result.php?id=<?php echo $row['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this result?')">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" colspan="5">
                                                No results found. Upload your first result file.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Format Guide Modal -->
    <div id="formatGuideModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">CSV Format Guide</h3>
                    <button onclick="hideFormatGuide()" class="text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="prose max-w-none">
                    <p>Your CSV file should follow this format:</p>
                    <pre class="bg-gray-100 p-4 rounded-md overflow-x-auto">student_id,subject_id,theory_marks,practical_marks,credit_hours
20012663,101,85,90,4
20012664,101,75,80,4
20012665,102,65,70,4</pre>
                    <p class="mt-4">Column descriptions:</p>
                    <ul class="list-disc pl-5">
                        <li><strong>student_id</strong>: The unique identifier for the student</li>
                        <li><strong>subject_id</strong>: The unique identifier for the subject</li>
                        <li><strong>theory_marks</strong>: Marks obtained in theory (0-100)</li>
                        <li><strong>practical_marks</strong>: Marks obtained in practical (0-100, can be empty)</li>
                        <li><strong>credit_hours</strong>: Credit hours for the subject (usually 4)</li>
                    </ul>
                    <p class="mt-4">Notes:</p>
                    <ul class="list-disc pl-5">
                        <li>The first row should contain the column headers as shown above</li>
                        <li>Make sure student_id and subject_id exist in the database</li>
                        <li>The system will automatically calculate grades and GPA based on the marks</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Format Guide Modal
        function showFormatGuide() {
            document.getElementById('formatGuideModal').classList.remove('hidden');
        }
        
        function hideFormatGuide() {
            document.getElementById('formatGuideModal').classList.add('hidden');
        }
        
        // Initialize Performance Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            const performanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F'],
                    datasets: [{
                        label: 'Grade Distribution',
                        data: [15, 22, 30, 25, 18, 12, 8, 5],
                        backgroundColor: [
                            'rgba(52, 211, 153, 0.7)',
                            'rgba(52, 211, 153, 0.6)',
                            'rgba(59, 130, 246, 0.7)',
                            'rgba(59, 130, 246, 0.6)',
                            'rgba(251, 191, 36, 0.7)',
                            'rgba(251, 191, 36, 0.6)',
                            'rgba(239, 68, 68, 0.6)',
                            'rgba(239, 68, 68, 0.7)'
                        ],
                        borderColor: [
                            'rgba(52, 211, 153, 1)',
                            'rgba(52, 211, 153, 1)',
                            'rgba(59, 130, 246, 1)',
                            'rgba(59, 130, 246, 1)',
                            'rgba(251, 191, 36, 1)',
                            'rgba(251, 191, 36, 1)',
                            'rgba(239, 68, 68, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Students'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Grades'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>

