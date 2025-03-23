<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: index.php");
    exit();
}
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 
// Fetch student details
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM Students WHERE user_id='$user_id'";
$result = $conn->query($sql);
$student = $result->fetch_assoc();
// Fetch results
$sql = "SELECT * FROM Results WHERE student_id='{$student['student_id']}'";
$results = $conn->query($sql);

// Calculate totals and average
$total_theory = 0;
$total_practical = 0;
$total_subjects = 0;
$average_gpa = 0;

// Clone results to calculate totals
$results_clone = $conn->query("SELECT * FROM Results WHERE student_id='{$student['student_id']}'");
while ($row = $results_clone->fetch_assoc()) {
    $total_theory += $row['theory_marks'];
    $total_practical += $row['practical_marks'];
    $average_gpa += $row['gpa'];
    $total_subjects++;
}

$average_gpa = $total_subjects > 0 ? round($average_gpa / $total_subjects, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Result Management System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            font-weight: 300;
        }

        .dashboard-card {
            box-shadow: 0 10px 25px rgba(0, 0, 40, 0.1);
        }

        .result-table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .result-table th,
        .result-table td {
            border: 1px solid #e2e8f0;
        }

        .result-table th {
            border-bottom: 2px solid #2d3748;
        }

        .result-table tr:hover {
            background-color: #f8fafc;
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">
    <nav class="bg-blue-900 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-2" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span class="font-semibold text-xl">Result Management System</span>
                </div>
                <div class="flex items-center">
                    <div class="mr-4 text-sm">
                        <span class="text-slate-300">Student ID: </span>
                        <span class="font-medium"><?php echo $student['student_id']; ?></span>
                    </div>
                    <a href="/logout.php"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium bg-red-700 hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Student Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-blue-900">Welcome, <?php echo $student['name']; ?></h1>
                    <p class="text-slate-500">View your latest results and academic performance</p>
                </div>
                <!-- Summary Card -->
                <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 mt-4 md:mt-0 flex space-x-6">
                    <div class="text-center">
                        <p class="text-sm text-slate-500">Average GPA</p>
                        <p class="text-2xl font-bold text-blue-900"><?php echo $average_gpa; ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-slate-500">Subjects</p>
                        <p class="text-2xl font-bold text-blue-900"><?php echo $total_subjects; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-blue-900">Your Results</h2>
                <button onclick="window.print()"
                    class="flex items-center text-sm text-blue-900 hover:text-blue-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print Results
                </button>
            </div>

            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="result-table w-full rounded-lg overflow-hidden">
                        <thead class="bg-slate-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">
                                    Subject</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">
                                    Theory Marks</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">
                                    Practical Marks</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">
                                    Grade</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">
                                    GPA</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php while ($row = $results->fetch_assoc()) { ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-blue-900">
                                        <?php echo $row['subject_id']; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap"><?php echo $row['theory_marks']; ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap"><?php echo $row['practical_marks']; ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php
                                            switch ($row['grade']) {
                                                case 'A':
                                                case 'A+':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'B':
                                                case 'B+':
                                                    echo 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'C':
                                                case 'C+':
                                                    echo 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                default:
                                                    echo 'bg-red-100 text-red-800';
                                                    break;
                                            }
                                            ?>">
                                            <?php echo $row['grade']; ?>
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 whitespace-nowrap font-medium
                                        <?php echo $row['gpa'] >= 3.5 ? 'text-green-600' : ($row['gpa'] >= 2.5 ? 'text-blue-600' : 'text-red-600'); ?>">
                                        <?php echo $row['gpa']; ?>
                                    </td>
                                </tr>
                            <?php } ?>

                            <!-- Summary Row -->
                            <tr class="bg-slate-50 font-medium">
                                <td class="px-4 py-3 whitespace-nowrap">Overall Summary</td>
                                <td class="px-4 py-3 whitespace-nowrap"><?php echo $total_theory; ?></td>
                                <td class="px-4 py-3 whitespace-nowrap"><?php echo $total_practical; ?></td>
                                <td class="px-4 py-3 whitespace-nowrap">-</td>
                                <td class="px-4 py-3 whitespace-nowrap"><?php echo $average_gpa; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- GPA Scale Reference -->
                <div class="mt-6 bg-slate-50 rounded-lg p-4 text-xs text-slate-600">
                    <p class="font-medium mb-1">GPA Scale Reference:</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <div>A+ (4.0): 90-100%</div>
                        <div>A (3.7): 85-89%</div>
                        <div>B+ (3.3): 80-84%</div>
                        <div>B (3.0): 75-79%</div>
                        <div>C+ (2.7): 70-74%</div>
                        <div>C (2.3): 65-69%</div>
                        <div>D (2.0): 60-64%</div>
                        <div>F (0.0): Below 60%</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-white mt-12 border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <p class="text-center text-sm text-slate-500">© 2025 Result Management System. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>