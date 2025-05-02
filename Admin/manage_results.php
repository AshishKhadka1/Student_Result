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

// Get all result uploads
$uploadsQuery = "SELECT * FROM ResultUploads ORDER BY upload_date DESC";
$uploads = $conn->query($uploadsQuery);

// Get subjects for manual entry
$subjectsQuery = "SELECT * FROM Subjects ORDER BY subject_name";
$subjects = $conn->query($subjectsQuery);

// Get students for manual entry
$studentsQuery = "SELECT s.student_id, u.full_name 
                 FROM Students s 
                 JOIN Users u ON s.user_id = u.user_id 
                 ORDER BY u.full_name";
$students = $conn->query($studentsQuery);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Results - Admin Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <h2 class="text-xl font-semibold text-gray-800">Manage Results</h2>
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
                <!-- Alert Component -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="bg-<?php echo $_SESSION['message_type'] ?? 'blue'; ?>-100 border-l-4 border-<?php echo $_SESSION['message_type'] ?? 'blue'; ?>-800 text-<?php echo $_SESSION['message_type'] ?? 'blue'; ?>-800 p-4 mb-6 rounded">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm"><?php echo $_SESSION['message']; ?></p>
                            </div>
                        </div>
                    </div>
                <?php
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                endif;
                ?>

                <!-- Tabs -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex">
                            <button onclick="showTab('upload')" id="tab-upload" class="tab-button active">
                                Bulk Upload
                            </button>
                            <button onclick="showTab('manual')" id="tab-manual" class="tab-button">
                                Manual Entry
                            </button>
                            <button onclick="showTab('manage')" id="tab-manage" class="tab-button">
                                Manage Uploads
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Upload Tab Content -->
                <div id="content-upload" class="tab-content active">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Upload Results (CSV)</h2>
                            <form action="process_upload.php" method="POST" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <label for="file" class="block text-gray-700 mb-2">Select CSV File:</label>
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-1">
                                            <div class="relative border-2 border-dashed border-gray-300 rounded-lg p-6 transition-all duration-200 hover:border-blue-500">
                                                <input type="file" id="file" name="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" required accept=".csv">
                                                <div class="text-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                    </svg>
                                                    <p class="mt-2 text-sm text-gray-600">Drag and drop your file here, or <span class="text-blue-600">browse</span></p>
                                                    <p class="mt-1 text-xs text-gray-500">Supports CSV files only</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="description" class="block text-gray-700 mb-2">Description (Optional):</label>
                                    <input type="text" id="description" name="description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Midterm Results 2023">
                                </div>

                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Options:</label>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="publish" name="publish" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="publish" class="ml-2 block text-sm text-gray-900">
                                            Publish results immediately
                                        </label>
                                    </div>
                                    <div class="flex items-center mt-2">
                                        <input type="checkbox" id="overwrite" name="overwrite" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="overwrite" class="ml-2 block text-sm text-gray-900">
                                            Overwrite existing results
                                        </label>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-lg transition duration-200 flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12" />
                                        </svg>
                                        Upload Results
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>


                </div>

                <!-- Manual Entry Tab Content -->
                <div id="content-manual" class="tab-content hidden">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Manual Result Entry</h2>
                            <form action="process_manual_entry.php" method="POST">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="student_id" class="block text-gray-700 mb-2">Student:</label>
                                        <select id="student_id" name="student_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                            <option value="">-- Select Student --</option>
                                            <?php if ($students && $students->num_rows > 0): ?>
                                                <?php while ($row = $students->fetch_assoc()): ?>
                                                    <option value="<?php echo $row['student_id']; ?>"><?php echo htmlspecialchars($row['full_name']) . ' (' . $row['student_id'] . ')'; ?></option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="subject_id" class="block text-gray-700 mb-2">Subject:</label>
                                        <select id="subject_id" name="subject_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                            <option value="">-- Select Subject --</option>
                                            <?php if ($subjects && $subjects->num_rows > 0): ?>
                                                <?php while ($row = $subjects->fetch_assoc()): ?>
                                                    <option value="<?php echo $row['subject_id']; ?>"><?php echo htmlspecialchars($row['subject_name']); ?></option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label for="theory_marks" class="block text-gray-700 mb-2">Theory Marks:</label>
                                        <input type="number" id="theory_marks" name="theory_marks" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" max="100" required>
                                    </div>
                                    <div>
                                        <label for="practical_marks" class="block text-gray-700 mb-2">Practical Marks (Optional):</label>
                                        <input type="number" id="practical_marks" name="practical_marks" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" max="100">
                                    </div>
                                    <div>
                                        <label for="credit_hours" class="block text-gray-700 mb-2">Credit Hours:</label>
                                        <input type="number" id="credit_hours" name="credit_hours" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="1" max="6" value="4" required>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-lg transition duration-200 flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Save Result
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="mt-6 bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Batch Entry</h3>
                            <p class="text-gray-600 mb-4">Use this form to enter results for multiple students for the same subject.</p>
                            <form action="process_batch_entry.php" method="POST">
                                <div class="mb-4">
                                    <label for="batch_subject_id" class="block text-gray-700 mb-2">Subject:</label>
                                    <select id="batch_subject_id" name="subject_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="">-- Select Subject --</option>
                                        <?php
                                        // Reset subjects result pointer
                                        if ($subjects) $subjects->data_seek(0);
                                        if ($subjects && $subjects->num_rows > 0):
                                        ?>
                                            <?php while ($row = $subjects->fetch_assoc()): ?>
                                                <option value="<?php echo $row['subject_id']; ?>"><?php echo htmlspecialchars($row['subject_name']); ?></option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="credit_hours_batch" class="block text-gray-700 mb-2">Credit Hours:</label>
                                    <input type="number" id="credit_hours_batch" name="credit_hours" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="1" max="6" value="4" required>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Student Results:</label>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Student
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Theory Marks
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Practical Marks
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200" id="batch-students-container">
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <select name="students[0][student_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                                            <option value="">-- Select Student --</option>
                                                            <?php
                                                            // Reset students result pointer
                                                            if ($students) $students->data_seek(0);
                                                            if ($students && $students->num_rows > 0):
                                                            ?>
                                                                <?php while ($row = $students->fetch_assoc()): ?>
                                                                    <option value="<?php echo $row['student_id']; ?>"><?php echo htmlspecialchars($row['full_name']) . ' (' . $row['student_id'] . ')'; ?></option>
                                                                <?php endwhile; ?>
                                                            <?php endif; ?>
                                                        </select>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <input type="number" name="students[0][theory_marks]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" max="100" required>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <input type="number" name="students[0][practical_marks]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" max="100">
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" id="add-student-row" class="text-blue-600 hover:text-blue-800 flex items-center text-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Another Student
                                        </button>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-lg transition duration-200 flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Save Batch Results
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Manage Uploads Tab Content -->
                <div id="content-manage" class="tab-content hidden">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Manage Result Uploads</h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                File Name
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Description
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
                                        <?php if ($uploads && $uploads->num_rows > 0): ?>
                                            <?php while ($row = $uploads->fetch_assoc()): ?>
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
                                                        <?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?>
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
                                                        <a href="view_upload.php?id=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                                        <?php if ($row['status'] != 'Published'): ?>
                                                            <a href="publish_results.php?id=<?php echo $row['id']; ?>" class="text-green-600 hover:text-green-900 mr-3">Publish</a>
                                                        <?php else: ?>
                                                            <a href="unpublish_results.php?id=<?php echo $row['id']; ?>" class="text-yellow-600 hover:text-yellow-900 mr-3">Unpublish</a>
                                                        <?php endif; ?>
                                                        <a href="delete_upload.php?id=<?php echo $row['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this upload? This will also delete all associated results.')">Delete</a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" colspan="6">
                                                    No result uploads found.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabId) {
            const tabContents = document.querySelectorAll('.tab-content');
            const tabButtons = document.querySelectorAll('.tab-button');

            tabContents.forEach(content => {
                content.classList.add('hidden');
            });

            tabButtons.forEach(button => {
                button.classList.remove('active');
            });

            document.getElementById('content-' + tabId).classList.remove('hidden');
            document.getElementById('tab-' + tabId).classList.add('active');
        }

        // Add student row in batch entry
        let studentRowCount = 1;
        document.getElementById('add-student-row').addEventListener('click', function() {
            const container = document.getElementById('batch-students-container');
            const newRow = document.createElement('tr');

            // Get the HTML content of the first student row
            const firstRow = container.querySelector('tr');
            const studentSelectHTML = firstRow.querySelector('td:first-child select').outerHTML;

            // Replace the name attribute to use the new index
            const updatedStudentSelectHTML = studentSelectHTML.replace(/students\[0\]/g, `students[${studentRowCount}]`);

            newRow.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap">
                    ${updatedStudentSelectHTML}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="number" name="students[${studentRowCount}][theory_marks]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" max="100" required>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="number" name="students[${studentRowCount}][practical_marks]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" max="100">
                </td>
            `;

            container.appendChild(newRow);
            studentRowCount++;
        });

        // Style active tab
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');

            tabButtons.forEach(button => {
                button.classList.add('px-4', 'py-2', 'text-sm', 'font-medium', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');

                if (button.classList.contains('active')) {
                    button.classList.add('border-b-2', 'border-blue-500', 'text-blue-600');
                }
            });
        });
    </script>
</body>

</html>