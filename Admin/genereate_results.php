<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get report type from query string
$reportType = isset($_GET['type']) ? $_GET['type'] : 'class_toppers';

// Get available classes/subjects for filters
$classesQuery = "SELECT DISTINCT class FROM Students ORDER BY class";
$classes = $conn->query($classesQuery);

$subjectsQuery = "SELECT * FROM Subjects ORDER BY subject_name";
$subjects = $conn->query($subjectsQuery);

// Generate report based on type
$reportData = [];
$chartData = [];

switch ($reportType) {
    case 'class_toppers':
        $class = isset($_GET['class']) ? $_GET['class'] : '';
        
        $query = "
            SELECT 
                u.full_name, 
                s.student_id, 
                s.class,
                AVG(r.gpa) as avg_gpa
            FROM 
                Results r
                JOIN Students s ON r.student_id = s.student_id
                JOIN Users u ON s.user_id = u.user_id
        ";
        
        if (!empty($class)) {
            $query .= " WHERE s.class = '$class'";
        }
        
        $query .= "
            GROUP BY 
                r.student_id
            ORDER BY 
                avg_gpa DESC
            LIMIT 10
        ";
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
        }
        
        // Prepare chart data
        $labels = [];
        $data = [];
        foreach ($reportData as $row) {
            $labels[] = $row['full_name'];
            $data[] = $row['avg_gpa'];
        }
        $chartData = [
            'labels' => json_encode($labels),
            'data' => json_encode($data)
        ];
        break;
        
    case 'subject_performance':
        $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
        
        $query = "
            SELECT 
                sub.subject_name,
                sub.subject_id,
                COUNT(r.result_id) as total_students,
                AVG(r.theory_marks) as avg_theory,
                AVG(r.practical_marks) as avg_practical,
                AVG(r.gpa) as avg_gpa,
                SUM(CASE WHEN r.grade = 'A+' THEN 1 ELSE 0 END) as a_plus,
                SUM(CASE WHEN r.grade = 'A' THEN 1 ELSE 0 END) as a,
                SUM(CASE WHEN r.grade = 'B+' THEN 1 ELSE 0 END) as b_plus,
                SUM(CASE WHEN r.grade = 'B' THEN 1 ELSE 0 END) as b,
                SUM(CASE WHEN r.grade = 'C+' THEN 1 ELSE 0 END) as c_plus,
                SUM(CASE WHEN r.grade = 'C' THEN 1 ELSE 0 END) as c,
                SUM(CASE WHEN r.grade = 'D' THEN 1 ELSE 0 END) as d,
                SUM(CASE WHEN r.grade = 'F' THEN 1 ELSE 0 END) as f
            FROM 
                Subjects sub
                LEFT JOIN Results r ON sub.subject_id = r.subject_id
        ";
        
        if (!empty($subject)) {
            $query .= " WHERE sub.subject_id = '$subject'";
        }
        
        $query .= "
            GROUP BY 
                sub.subject_id
            ORDER BY 
                avg_gpa DESC
        ";
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
        }
        
        // Prepare chart data for first subject
        if (!empty($reportData)) {
            $firstSubject = $reportData[0];
            $labels = ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F'];
            $data = [
                $firstSubject['a_plus'],
                $firstSubject['a'],
                $firstSubject['b_plus'],
                $firstSubject['b'],
                $firstSubject['c_plus'],
                $firstSubject['c'],
                $firstSubject['d'],
                $firstSubject['f']
            ];
            $chartData = [
                'labels' => json_encode($labels),
                'data' => json_encode($data),
                'subject' => $firstSubject['subject_name']
            ];
        }
        break;
        
    case 'overall_progress':
        $query = "
            SELECT 
                YEAR(r.created_at) as year,
                MONTH(r.created_at) as month,
                DATE_FORMAT(r.created_at, '%b %Y') as period,
                COUNT(DISTINCT r.student_id) as total_students,
                AVG(r.gpa) as avg_gpa
            FROM 
                Results r
            GROUP BY 
                YEAR(r.created_at), MONTH(r.created_at)
            ORDER BY 
                YEAR(r.created_at), MONTH(r.created_at)
        ";
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
        }
        
        // Prepare chart data
        $labels = [];
        $data = [];
        foreach ($reportData as $row) {
            $labels[] = $row['period'];
            $data[] = $row['avg_gpa'];
        }
        $chartData = [
            'labels' => json_encode($labels),
            'data' => json_encode($data)
        ];
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports - Admin Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jsPDF for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
</head>
<body class="bg-gray-100 font-light">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <?php
        // Include the file that processes form data
        include 'sidebar.php';
        ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php
        // Include the file that processes form data
        include 'topBar.php';
        ?>

        <div class="flex-1 overflow-x-hidden overflow-y-auto">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm">
                <div class="flex items-center justify-between px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Generate Reports</h2>
                    <div class="flex items-center space-x-4">
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
                <!-- Report Type Selection -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Select Report Type</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <a href="?type=class_toppers" class="block p-4 border rounded-lg <?php echo $reportType == 'class_toppers' ? 'bg-blue-50 border-blue-500' : 'hover:bg-gray-50'; ?>">
                                <div class="flex items-center">
                                    <div class="rounded-full bg-blue-100 p-3 mr-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">Class-wise Toppers</h3>
                                        <p class="text-sm text-gray-500">View top performing students by class</p>
                                    </div>
                                </div>
                            </a>
                            <a href="?type=subject_performance" class="block p-4 border rounded-lg <?php echo $reportType == 'subject_performance' ? 'bg-blue-50 border-blue-500' : 'hover:bg-gray-50'; ?>">
                                <div class="flex items-center">
                                    <div class="rounded-full bg-green-100 p-3 mr-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">Subject-wise Performance</h3>
                                        <p class="text-sm text-gray-500">Analyze performance by subject</p>
                                    </div>
                                </div>
                            </a>
                            <a href="?type=overall_progress" class="block p-4 border rounded-lg <?php echo $reportType == 'overall_progress' ? 'bg-blue-50 border-blue-500' : 'hover:bg-gray-50'; ?>">
                                <div class="flex items-center">
                                    <div class="rounded-full bg-purple-100 p-3 mr-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">Overall Academic Progress</h3>
                                        <p class="text-sm text-gray-500">Track progress over time</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Report Filters -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Report Filters</h2>
                        <form action="" method="GET" class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-4">
                            <input type="hidden" name="type" value="<?php echo $reportType; ?>">
                            
                            <?php if ($reportType == 'class_toppers'): ?>
                                <div class="flex-1">
                                    <label for="class" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                    <select name="class" id="class" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Classes</option>
                                        <?php if ($classes && $classes->num_rows > 0): ?>
                                            <?php while ($row = $classes->fetch_assoc()): ?>
                                                <option value="<?php echo $row['class']; ?>" <?php echo isset($_GET['class']) && $_GET['class'] == $row['class'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($row['class']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            <?php elseif ($reportType == 'subject_performance'): ?>
                                <div class="flex-1">
                                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                    <select name="subject" id="subject" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Subjects</option>
                                        <?php if ($subjects && $subjects->num_rows > 0): ?>
                                            <?php while ($row = $subjects->fetch_assoc()): ?>
                                                <option value="<?php echo $row['subject_id']; ?>" <?php echo isset($_GET['subject']) && $_GET['subject'] == $row['subject_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($row['subject_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <div class="md:self-end">
                                <button type="submit" class="w-full md:w-auto bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-lg transition duration-200">
                                    Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Content -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="p-6">
                        <?php if ($reportType == 'class_toppers'): ?>
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-semibold text-gray-800">Class-wise Toppers</h2>
                                <div class="flex space-x-2">
                                    <button onclick="exportToPDF()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-md text-sm flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        Export PDF
                                    </button>
                                    <button onclick="exportToCSV()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-md text-sm flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12" />
                                        </svg>
                                        Export CSV
                                    </button>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="overflow-x-auto">
                                        <table id="reportTable" class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Rank
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Student Name
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Class
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Average GPA
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php if (!empty($reportData)): ?>
                                                    <?php $rank = 1; ?>
                                                    <?php foreach ($reportData as $row): ?>
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo $rank++; ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="flex items-center">
                                                                    <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-800 font-semibold">
                                                                        <?php echo substr($row['full_name'], 0, 1); ?>
                                                                    </div>
                                                                    <div class="ml-3">
                                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['student_id']); ?></div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($row['class']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                    <?php echo number_format($row['avg_gpa'], 2); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" colspan="4">
                                                            No data available.
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div>
                                    <div class="h-80">
                                        <canvas id="reportChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($reportType == 'subject_performance'): ?>
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-semibold text-gray-800">Subject-wise Performance</h2>
                                <div class="flex space-x-2">
                                    <button onclick="exportToPDF()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-md text-sm flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        Export PDF
                                    </button>
                                    <button onclick="exportToCSV()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-md text-sm flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12" />
                                        </svg>
                                        Export CSV
                                    </button>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="overflow-x-auto">
                                        <table id="reportTable" class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Subject
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Students
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Avg. Theory
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Avg. Practical
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Avg. GPA
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php if (!empty($reportData)): ?>
                                                    <?php foreach ($reportData as $row): ?>
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo number_format($row['total_students']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo number_format($row['avg_theory'], 1); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo number_format($row['avg_practical'], 1); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                    <?php echo number_format($row['avg_gpa'], 2); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" colspan="5">
                                                            No data available.
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div>
                                    <div class="h-80">
                                        <canvas id="reportChart"></canvas>
                                    </div>
                                    <?php if (!empty($chartData)): ?>
                                        <p class="text-center text-sm text-gray-500 mt-2">Grade distribution for <?php echo htmlspecialchars($chartData['subject']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($reportType == 'overall_progress'): ?>
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-semibold text-gray-800">Overall Academic Progress</h2>
                                <div class="flex space-x-2">
                                    <button onclick="exportToPDF()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-md text-sm flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        Export PDF
                                    </button>
                                    <button onclick="exportToCSV()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-md text-sm flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12" />
                                        </svg>
                                        Export CSV
                                    </button>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="overflow-x-auto">
                                        <table id="reportTable" class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Period
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Students
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Average GPA
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php if (!empty($reportData)): ?>
                                                    <?php foreach ($reportData as $row): ?>
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                <?php echo htmlspecialchars($row['period']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo number_format($row['total_students']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                    <?php echo number_format($row['avg_gpa'], 2); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" colspan="3">
                                                            No data available.
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div>
                                    <div class="h-80">
                                        <canvas id="reportChart"></canvas>
                                    </div>
                                    <p class="text-center text-sm text-gray-500 mt-2">GPA trend over time</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('reportChart').getContext('2d');
            
            <?php if ($reportType == 'class_toppers'): ?>
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo $chartData['labels']; ?>,
                        datasets: [{
                            label: 'Average GPA',
                            data: <?php echo $chartData['data']; ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 4,
                                title: {
                                    display: true,
                                    text: 'GPA (0-4)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Students'
                                }
                            }
                        }
                    }
                });
            <?php elseif ($reportType == 'subject_performance'): ?>
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo $chartData['labels']; ?>,
                        datasets: [{
                            data: <?php echo $chartData['data']; ?>,
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
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
            <?php elseif ($reportType == 'overall_progress'): ?>
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo $chartData['labels']; ?>,
                        datasets: [{
                            label: 'Average GPA',
                            data: <?php echo $chartData['data']; ?>,
                            backgroundColor: 'rgba(124, 58, 237, 0.2)',
                            borderColor: 'rgba(124, 58, 237, 1)',
                            borderWidth: 2,
                            tension: 0.1,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false,
                                min: 0,
                                max: 4,
                                title: {
                                    display: true,
                                    text: 'GPA (0-4)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Time Period'
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
        
        // Export to PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Add title
            doc.setFontSize(16);
            doc.text('<?php echo ucfirst(str_replace('_', ' ', $reportType)); ?> Report', 14, 15);
            
            // Add date
            doc.setFontSize(10);
            doc.text('Generated on: <?php echo date('F d, Y'); ?>', 14, 22);
            
            // Add table
            doc.autoTable({
                html: '#reportTable',
                startY: 30,
                theme: 'grid',
                headStyles: { fillColor: [44, 74, 124], textColor: [255, 255, 255] }
            });
            
            // Save the PDF
            doc.save('<?php echo $reportType; ?>_report.pdf');
        }
        
        // Export to CSV
        function exportToCSV() {
            const table = document.getElementById('reportTable');
            const rows = table.querySelectorAll('tr');
            
            let csv = [];
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Get the text content and clean it
                    let data = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, '').trim();
                    // Escape double quotes
                    data = data.replace(/"/g, '""');
                    // Add quotes around the data
                    row.push('"' + data + '"');
                }
                csv.push(row.join(','));
            }
            
            const csvString = csv.join('\n');
            const filename = '<?php echo $reportType; ?>_report.csv';
            
            // Create a download link and trigger it
            const link = document.createElement('a');
            link.style.display = 'none';
            link.setAttribute('target', '_blank');
            link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

