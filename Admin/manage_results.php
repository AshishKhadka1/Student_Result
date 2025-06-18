<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all result uploads
$uploadsQuery = "SELECT ru.*, u.full_name as uploaded_by_name, e.exam_name, c.class_name, c.section 
                FROM result_uploads ru
                LEFT JOIN users u ON ru.uploaded_by = u.user_id
                LEFT JOIN exams e ON ru.exam_id = e.exam_id
                LEFT JOIN classes c ON ru.class_id = c.class_id
                ORDER BY ru.upload_date DESC";
$uploads = $conn->query($uploadsQuery);

// Add this debug code to check if there are any uploads in the database
if ($uploads->num_rows == 0) {
    // Check if there are any uploads at all
    $checkUploads = $conn->query("SELECT COUNT(*) as count FROM result_uploads");
    $uploadCount = $checkUploads->fetch_assoc()['count'];
    // You can uncomment this for debugging
    // echo "<!-- Debug: Total uploads in database: $uploadCount -->";
}

// Get subjects for manual entry
$subjectsQuery = "SELECT * FROM subjects ORDER BY subject_name";
$subjects = $conn->query($subjectsQuery);

// Get students for manual entry
$studentsQuery = "SELECT s.student_id, u.full_name, s.roll_number, c.class_name, c.section 
                FROM students s 
                JOIN users u ON s.user_id = u.user_id 
                LEFT JOIN classes c ON s.class_id = c.class_id
                ORDER BY u.full_name";
$students = $conn->query($studentsQuery);

// Get classes for dropdown
$classesQuery = "SELECT class_id, class_name, section FROM classes ORDER BY class_name, section";
$classes = $conn->query($classesQuery);

// Get exams for dropdown
$examsQuery = "SELECT exam_id, exam_name, exam_type, class_id FROM exams ORDER BY created_at DESC";
$exams = $conn->query($examsQuery);

// Get student data if ID is provided
$student_data = null;
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    $stmt = $conn->prepare("
       SELECT s.*, u.full_name, c.class_name, c.section 
       FROM students s
       JOIN users u ON s.user_id = u.user_id
       JOIN classes c ON s.class_id = c.class_id
       WHERE s.student_id = ?
   ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $student_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get active tab from URL parameter or default to 'manual'
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'manual';

// Handle search functionality
$searchResults = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $stmt = $conn->prepare("
        SELECT s.student_id, s.roll_number, u.full_name, c.class_name, c.section
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN classes c ON s.class_id = c.class_id
        WHERE s.student_id LIKE ? OR s.roll_number LIKE ? OR u.full_name LIKE ?
        LIMIT 10
    ");
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $searchResults[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Results | Result Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Tab styles */
        .tab-button {
            @apply px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300;
        }

        .tab-button.active {
            @apply border-b-2 border-blue-500 text-blue-600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Dark mode */
        .dark-mode {
            background-color: #1a202c;
            color: #e2e8f0;
        }

        .dark-mode .bg-white {
            background-color: #2d3748 !important;
            color: #e2e8f0;
        }

        .dark-mode .bg-gray-50 {
            background-color: #4a5568 !important;
            color: #e2e8f0;
        }

        .dark-mode .text-gray-900 {
            color: #e2e8f0 !important;
        }

        .dark-mode .text-gray-500 {
            color: #a0aec0 !important;
        }

        .dark-mode .border-gray-200 {
            border-color: #4a5568 !important;
        }
    </style>
</head>

<body class="bg-gray-100" id="body">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include 'topBar.php'; ?>

            <!-- Mobile sidebar -->
            <div class="fixed inset-0 flex z-40 md:hidden transform -translate-x-full transition-transform duration-300 ease-in-out" id="mobile-sidebar">
                <div class="fixed inset-0 bg-gray-600 bg-opacity-75" id="sidebar-backdrop"></div>
                <div class="relative flex-1 flex flex-col max-w-xs w-full bg-gray-800">
                    <div class="absolute top-0 right-0 -mr-12 pt-2">
                        <button class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" id="close-sidebar">
                            <span class="sr-only">Close sidebar</span>
                            <i class="fas fa-times text-white"></i>
                        </button>
                    </div>
                    <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
                        <div class="flex-shrink-0 flex items-center px-4">
                            <span class="text-white text-lg font-semibold">Result Management</span>
                        </div>
                        <nav class="mt-5 px-2 space-y-1">
                            <!-- Mobile menu items -->
                            <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-tachometer-alt mr-3"></i>
                                Dashboard
                            </a>
                            <a href="manage_results.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                                <i class="fas fa-clipboard-list mr-3"></i>
                                Manage Results
                            </a>
                            <!-- More mobile menu items -->
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <h1 class="text-2xl font-semibold text-gray-900">Manage Results</h1>

                        <!-- Notification Messages -->
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 mt-4 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700">
                                            <?php echo $_SESSION['success_message'];
                                            unset($_SESSION['success_message']); ?>
                                        </p>
                                    </div>
                                    <div class="ml-auto pl-3">
                                        <div class="-mx-1.5 -my-1.5">
                                            <button class="inline-flex rounded-md p-1.5 text-green-500 hover:bg-green-100 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                                                <span class="sr-only">Dismiss</span>
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 mt-4 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700">
                                            <?php echo $_SESSION['error_message'];
                                            unset($_SESSION['error_message']); ?>
                                        </p>
                                    </div>
                                    <div class="ml-auto pl-3">
                                        <div class="-mx-1.5 -my-1.5">
                                            <button class="inline-flex rounded-md p-1.5 text-red-500 hover:bg-red-100 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                                                <span class="sr-only">Dismiss</span>
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-6 mt-6">
                            <div class="border-b border-gray-300">
                                <nav class="flex space-x-4" role="tablist">
                                    <button onclick="showTab('manual')" id="tab-manual"
                                        class="tab-button px-4 py-2 text-sm font-medium transition-colors 
                <?php echo $activeTab == 'manual' ? 'border-b-2 border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-blue-500'; ?>">
                                        Manual Entry
                                    </button>
                                    <button onclick="showTab('batch')" id="tab-batch"
                                        class="tab-button px-4 py-2 text-sm font-medium transition-colors  
                <?php echo $activeTab == 'batch' ? 'border-b-2 border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-blue-500'; ?>">
                                        Batch Entry
                                    </button>
                                    <button onclick="showTab('manage')" id="tab-manage"
                                        class="tab-button px-4 py-2 text-sm font-medium transition-colors 
                <?php echo $activeTab == 'manage' ? 'border-b-2 border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-blue-500'; ?>">
                                        Manage Uploads
                                    </button>
                                </nav>
                            </div>
                        </div>

                        <!-- Manual Entry Tab Content -->
                        <div id="content-manual" class="tab-content <?php echo $activeTab == 'manual' ? 'active' : ''; ?>">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <!-- Student Search Section -->
                                <div class="lg:col-span-1">
                                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                        <div class="p-6">
                                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Search Student</h2>
                                            <form action="" method="GET" class="mb-4">
                                                <input type="hidden" name="tab" value="manual">
                                                <div class="flex">
                                                    <input type="text" name="search" placeholder="Enter Student ID, Roll Number or Name" class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-md">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                </div>
                                            </form>

                                            <?php if (!empty($searchResults)): ?>
                                                <div class="mt-4">
                                                    <h3 class="text-md font-medium text-gray-700 mb-2">Search Results</h3>
                                                    <div class="overflow-x-auto">
                                                        <table class="min-w-full divide-y divide-gray-200">
                                                            <thead class="bg-gray-50">
                                                                <tr>
                                                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="bg-white divide-y divide-gray-200">
                                                                <?php foreach ($searchResults as $student): ?>
                                                                    <tr>
                                                                        <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $student['student_id']; ?></td>
                                                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo $student['full_name']; ?></td>
                                                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                                                            <?php
                                                                            echo $student['class_name'] ?? 'N/A';
                                                                            if (!empty($student['section'])) {
                                                                                echo ' ' . $student['section'];
                                                                            }
                                                                            ?>
                                                                        </td>
                                                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                                                            <button type="button" class="select-student text-blue-600 hover:text-blue-900"
                                                                                data-id="<?php echo $student['student_id']; ?>"
                                                                                data-name="<?php echo $student['full_name']; ?>"
                                                                                data-roll="<?php echo $student['roll_number']; ?>"
                                                                                data-class="<?php echo ($student['class_name'] ?? '') . ' ' . ($student['section'] ?? ''); ?>">
                                                                                Select
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            <?php elseif (isset($_GET['search'])): ?>
                                                <div class="mt-4 text-center text-gray-500">No students found matching your search.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Manual Entry Form -->
                                <div class="lg:col-span-2">
                                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                        <div class="p-6">
                                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Manual Result Entry</h2>
                                            <form action="process_manual_entry.php" method="POST" id="resultForm">
                                                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                                    <!-- Student Information -->
                                                    <div class="sm:col-span-3">
                                                        <label for="student_id" class="block text-sm font-medium text-gray-700">Student ID</label>
                                                        <div class="mt-1">
                                                            <input type="text" name="student_id" id="student_id" value="<?php echo isset($_GET['student_id']) ? $_GET['student_id'] : ''; ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Enter Student ID" required>
                                                        </div>
                                                    </div>

                                                    <div class="sm:col-span-3">
                                                        <label for="student_name" class="block text-sm font-medium text-gray-700">Student Name</label>
                                                        <div class="mt-1">
                                                            <input type="text" id="student_name" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md bg-gray-50" readonly>
                                                        </div>
                                                    </div>

                                                    <div class="sm:col-span-3">
                                                        <label for="student_roll" class="block text-sm font-medium text-gray-700">Roll Number</label>
                                                        <div class="mt-1">
                                                            <input type="text" id="student_roll" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md bg-gray-50" readonly>
                                                        </div>
                                                    </div>

                                                    <div class="sm:col-span-3">
                                                        <label for="student_class" class="block text-sm font-medium text-gray-700">Class</label>
                                                        <div class="mt-1">
                                                            <input type="text" id="student_class" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md bg-gray-50" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Exam Selection -->
                                                    <div class="sm:col-span-6">
                                                        <label for="exam_id" class="block text-sm font-medium text-gray-700">Select Exam</label>
                                                        <select id="exam_id" name="exam_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                            <option value="">-- Select Exam --</option>
                                                            <?php
                                                            // Reset exams result pointer
                                                            if ($exams) $exams->data_seek(0);
                                                            if ($exams && $exams->num_rows > 0):
                                                            ?>
                                                                <?php while ($row = $exams->fetch_assoc()): ?>
                                                                    <option value="<?php echo $row['exam_id']; ?>" data-class="<?php echo $row['class_id']; ?>">
                                                                        <?php echo $row['exam_name'] . ' (' . ucfirst($row['exam_type']) . ')'; ?>
                                                                    </option>
                                                                <?php endwhile; ?>
                                                            <?php endif; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Subject Marks Section -->
                                                <div class="mt-6">
                                                    <h4 class="text-md font-medium text-gray-700 mb-3">Subject Marks</h4>

                                                    <div id="subjectsContainer">
                                                        <div class="subject-row grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-10 mb-4 pb-4 border-b border-gray-200">
                                                            <div class="sm:col-span-4">
                                                                <label class="block text-sm font-medium text-gray-700">Subject</label>
                                                                <select name="subject_id[]" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                                    <option value="">-- Select Subject --</option>
                                                                    <?php if ($subjects && $subjects->num_rows > 0): ?>
                                                                        <?php
                                                                        // Reset subjects result pointer
                                                                        if ($subjects) $subjects->data_seek(0);
                                                                        while ($row = $subjects->fetch_assoc()):
                                                                        ?>
                                                                            <option value="<?php echo $row['subject_id']; ?>">
                                                                                <?php echo $row['subject_name']; ?>
                                                                            </option>
                                                                        <?php endwhile; ?>
                                                                    <?php endif; ?>
                                                                </select>
                                                            </div>
                                                            <div class="sm:col-span-2">
                                                                <label class="block text-sm font-medium text-gray-700">
                                                                    Theory Marks 
                                                                    <span class="theory-max-marks text-xs text-gray-500">(Max: 100)</span>
                                                                </label>
                                                                <input type="number" name="theory_marks[]" min="0" max="100" step="0.01" 
                                                                       class="theory-marks mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                                       onchange="updateMarksDistribution(this)">
                                                            </div>
                                                            <div class="sm:col-span-2">
                                                                <label class="block text-sm font-medium text-gray-700">
                                                                    Practical Marks 
                                                                    <span class="practical-max-marks text-xs text-gray-500">(Max: 0)</span>
                                                                </label>
                                                                <input type="number" name="practical_marks[]" min="0" max="100" step="0.01" 
                                                                       class="practical-marks mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                                       onchange="updateMarksDistribution(this)">
                                                            </div>
                                                            <div class="sm:col-span-1">
                                                                <label class="block text-sm font-medium text-gray-700">Total</label>
                                                                <input type="text" class="total-marks mt-1 bg-gray-100 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" readonly>
                                                            </div>
                                                            <div class="sm:col-span-1 flex items-end">
                                                                <button type="button" class="remove-subject mt-1 text-red-600 hover:text-red-800">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mt-2">
                                                        <button type="button" id="addSubject" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                            </svg>
                                                            Add Subject
                                                        </button>
                                                    </div>

                                                    <!-- Marks Distribution Info -->
                                                    <div class="mt-4 bg-blue-50 border-l-4 border-blue-400 p-4">
                                                        <div class="flex">
                                                            <div class="flex-shrink-0">
                                                                <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                            <div class="ml-3">
                                                                <p class="text-sm text-blue-700">
                                                                    <strong>Marks Distribution:</strong><br>
                                                                    • If both Theory and Practical marks are provided: Theory (75 marks) + Practical (25 marks) = 100 marks<br>
                                                                    • If only Theory marks are provided: Theory (100 marks) = 100 marks<br>
                                                                    • Practical marks can be left blank if not applicable
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Preview Section -->
                                                <div class="mt-6 hidden" id="previewSection">
                                                    <h4 class="text-md font-medium text-gray-700 mb-3">Result Preview</h4>
                                                    <div class="bg-gray-50 p-4 rounded-md">
                                                        <div class="overflow-x-auto">
                                                            <table class="min-w-full divide-y divide-gray-200">
                                                                <thead class="bg-gray-100">
                                                                    <tr>
                                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Theory</th>
                                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Practical</th>
                                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade Point</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="previewBody" class="bg-white divide-y divide-gray-200">
                                                                    <!-- Preview data will be inserted here -->
                                                                </tbody>
                                                                <tfoot class="bg-gray-50">
                                                                    <tr>
                                                                        <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-500">Total:</td>
                                                                        <td id="previewTotal" class="px-6 py-3 text-left text-sm font-medium text-gray-900">0</td>
                                                                        <td class="px-6 py-3"></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-500">Percentage:</td>
                                                                        <td id="previewPercentage" class="px-6 py-3 text-left text-sm font-medium text-gray-900">0%</td>
                                                                        <td class="px-6 py-3"></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-500">Result:</td>
                                                                        <td id="previewResult" class="px-6 py-3 text-left text-sm font-medium text-gray-900">-</td>
                                                                        <td class="px-6 py-3"></td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mt-6 flex justify-end space-x-3">
                                                    <button type="button" id="previewButton" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        Preview
                                                    </button>
                                                    <button type="submit" name="save_result" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                                        </svg>
                                                        Save Results
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Batch Entry Tab Content -->
                        <div id="content-batch" class="tab-content <?php echo $activeTab == 'batch' ? 'active' : ''; ?>">
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                <div class="p-6">
                                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Batch Entry</h2>
                                    <p class="text-gray-600 mb-4">Use this form to enter results for multiple students for the same subject.</p>
                                    <form action="process_batch_entry.php" method="POST">
                                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                            <!-- Subject Selection -->
                                            <div class="sm:col-span-3">
                                                <label for="batch_subject_id" class="block text-sm font-medium text-gray-700">Subject</label>
                                                <select id="batch_subject_id" name="subject_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" required>
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

                                            <!-- Exam Selection -->
                                            <div class="sm:col-span-3">
                                                <label for="batch_exam_id" class="block text-sm font-medium text-gray-700">Exam</label>
                                                <select id="batch_exam_id" name="exam_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" required>
                                                    <option value="">-- Select Exam --</option>
                                                    <?php
                                                    // Reset exams result pointer
                                                    if ($exams) $exams->data_seek(0);
                                                    if ($exams && $exams->num_rows > 0):
                                                    ?>
                                                        <?php while ($row = $exams->fetch_assoc()): ?>
                                                            <option value="<?php echo $row['exam_id']; ?>"><?php echo htmlspecialchars($row['exam_name'] . ' (' . ucfirst($row['exam_type']) . ')'); ?></option>
                                                        <?php endwhile; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>

                                            <!-- Class Selection (Optional) -->
                                            <div class="sm:col-span-3">
                                                <label for="batch_class_id" class="block text-sm font-medium text-gray-700">Class (Optional)</label>
                                                <select id="batch_class_id" name="class_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                    <option value="">-- All Classes --</option>
                                                    <?php
                                                    // Reset classes result pointer
                                                    if ($classes) $classes->data_seek(0);
                                                    if ($classes && $classes->num_rows > 0):
                                                    ?>
                                                        <?php while ($row = $classes->fetch_assoc()): ?>
                                                            <option value="<?php echo $row['class_id']; ?>"><?php echo htmlspecialchars($row['class_name'] . ' ' . $row['section']); ?></option>
                                                        <?php endwhile; ?>
                                                    <?php endif; ?>
                                                </select>
                                                <p class="mt-1 text-xs text-gray-500">Filter students by class (optional)</p>
                                            </div>
                                        </div>

                                        <div class="mt-6">
                                            <h4 class="text-md font-medium text-gray-700 mb-3">Student Results</h4>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                Student
                                                            </th>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                Theory Marks
                                                                <span class="batch-theory-max text-xs text-gray-400 block">(Max: 100)</span>
                                                            </th>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                Practical Marks
                                                                <span class="batch-practical-max text-xs text-gray-400 block">(Max: 0)</span>
                                                            </th>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                Total
                                                            </th>
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                Action
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
                                                                <input type="number" name="students[0][theory_marks]" 
                                                                       class="batch-theory-marks w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                                       min="0" max="100" step="0.01" required
                                                                       onchange="updateBatchMarksDistribution(this)">
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <input type="number" name="students[0][practical_marks]" 
                                                                       class="batch-practical-marks w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                                       min="0" max="100" step="0.01"
                                                                       onchange="updateBatchMarksDistribution(this)">
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <input type="text" class="batch-total-marks w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md" readonly>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <button type="button" class="delete-student-row text-red-600 hover:text-red-800">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </button>
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

                                            <!-- Batch Marks Distribution Info -->
                                            <div class="mt-4 bg-green-50 border-l-4 border-green-400 p-4">
                                                <div class="flex">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm text-green-700">
                                                            <strong>Batch Entry Marks Distribution:</strong><br>
                                                            • Theory + Practical: Theory (75 marks) + Practical (25 marks) = 100 marks<br>
                                                            • Theory Only: Theory (100 marks) = 100 marks<br>
                                                            • Practical marks can be left blank if not applicable
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-6 flex justify-end">
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
                        <div id="content-manage" class="tab-content <?php echo $activeTab == 'manage' ? 'active' : ''; ?>">
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                <div class="p-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h2 class="text-lg font-semibold text-gray-800">Manage Result Uploads</h2>
                                        <a href="?tab=manage" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm">
                                            <i class="fas fa-sync-alt mr-2"></i>Refresh
                                        </a>
                                    </div>
                                    <?php if ($uploads && $uploads->num_rows == 0): ?>
                                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                                            <div class="flex">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm text-yellow-700">
                                                        No uploads found. If you've manually entered results, they should appear here.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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
                                                        Exam
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
                                                                <?php echo htmlspecialchars($row['exam_name'] ?? 'N/A'); ?>
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
                                                                <a href="delete_upload.php?id=<?php echo $row['id']; ?>" class="text-red-600 hover:text-red-900 mr-3" onclick="return confirm('Are you sure you want to delete this upload? This will also delete all associated results.')">Delete</a>
                                                                <a href="students_result.php?upload_id=<?php echo $row['id']; ?>" class="text-indigo-600 hover:text-indigo-900">Student Results</a>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" colspan="7">
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
                content.classList.remove('active');
            });

            tabButtons.forEach(button => {
                button.classList.remove('active');
            });

            document.getElementById('content-' + tabId).classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');

            // Update URL with tab parameter
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabId);
            window.history.replaceState({}, '', url);
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
                    <input type="number" name="students[${studentRowCount}][theory_marks]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" max="100" step="0.01" required>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="number" name="students[${studentRowCount}][practical_marks]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" max="100" step="0.01">
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <button type="button" class="delete-student-row text-red-600 hover:text-red-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </td>
            `;

            container.appendChild(newRow);
            studentRowCount++;
        });

        // Add Subject Row
        document.getElementById('addSubject').addEventListener('click', function() {
            const container = document.getElementById('subjectsContainer');
            const subjectRow = document.querySelector('.subject-row').cloneNode(true);

            // Clear input values
            subjectRow.querySelectorAll('input').forEach(input => {
                input.value = '';
            });

            // Reset select
            subjectRow.querySelector('select').selectedIndex = 0;

            // Add event listener to remove button
            subjectRow.querySelector('.remove-subject').addEventListener('click', function() {
                if (container.children.length > 1) {
                    this.closest('.subject-row').remove();
                }
            });

            container.appendChild(subjectRow);
        });

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listener to initial remove button
            document.querySelector('.remove-subject').addEventListener('click', function() {
                const container = document.getElementById('subjectsContainer');
                if (container.children.length > 1) {
                    this.closest('.subject-row').remove();
                }
            });

            // Student selection from search results
            document.querySelectorAll('.select-student').forEach(button => {
                button.addEventListener('click', function() {
                    const studentId = this.getAttribute('data-id');
                    const studentName = this.getAttribute('data-name');
                    const studentRoll = this.getAttribute('data-roll');
                    const studentClass = this.getAttribute('data-class');

                    document.getElementById('student_id').value = studentId;
                    document.getElementById('student_name').value = studentName;
                    document.getElementById('student_roll').value = studentRoll;
                    document.getElementById('student_class').value = studentClass;
                });
            });

            // Preview Results
            document.getElementById('previewButton').addEventListener('click', function() {
                const previewSection = document.getElementById('previewSection');
                const previewBody = document.getElementById('previewBody');
                const previewTotal = document.getElementById('previewTotal');
                const previewPercentage = document.getElementById('previewPercentage');
                const previewResult = document.getElementById('previewResult');

                // Clear previous preview
                previewBody.innerHTML = '';

                // Get all subject rows
                const subjectRows = document.querySelectorAll('.subject-row');

                let totalMarks = 0;
                let totalSubjects = 0;
                let validSubjects = 0;

                // Process each subject
                subjectRows.forEach(row => {
                    const subjectSelect = row.querySelector('select[name="subject_id[]"]');
                    const theoryInput = row.querySelector('input[name="theory_marks[]"]');
                    const practicalInput = row.querySelector('input[name="practical_marks[]"]');

                    if (subjectSelect.value && (theoryInput.value || practicalInput.value)) {
                        const subjectName = subjectSelect.options[subjectSelect.selectedIndex].text;
                        const theory = parseFloat(theoryInput.value) || 0;
                        const practical = parseFloat(practicalInput.value) || 0;
                        const total = theory + practical;

                        // Calculate grade point based on percentage
                        const percentage = (total / 100) * 100;
                        let gradePoint = 0;

                        if (percentage >= 91) {
                            gradePoint = 3.8;
                        } else if (percentage >= 81) {
                            gradePoint = 3.4;
                        } else if (percentage >= 71) {
                            gradePoint = 3.0;
                        } else if (percentage >= 61) {
                            gradePoint = 2.7;
                        } else if (percentage >= 51) {
                            gradePoint = 2.4;
                        } else if (percentage >= 41) {
                            gradePoint = 1.9;
                        } else if (percentage >= 35) {
                            gradePoint = 1.6;
                        } else {
                            gradePoint = 0.0;
                        }

                        // Create table row - show blank for practical if empty
                        const practicalDisplay = practicalInput.value === '' ? '' : practical;
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${subjectName}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${theory}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${practicalDisplay}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${total}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${percentage >= 35 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                    ${gradePoint.toFixed(1)}
                                </span>
                            </td>
                        `;

                        previewBody.appendChild(tr);

                        totalMarks += total;
                        validSubjects++;
                    }

                    totalSubjects++;
                });

                // Update summary
                if (validSubjects > 0) {
                    const percentage = (totalMarks / (validSubjects * 100)) * 100;
                    previewTotal.textContent = totalMarks;
                    previewPercentage.textContent = percentage.toFixed(2) + '%';
                    previewResult.textContent = percentage >= 35 ? 'PASS' : 'FAIL';
                    previewResult.className = percentage >= 35 ? 'px-6 py-3 text-left text-sm font-medium text-green-600' : 'px-6 py-3 text-left text-sm font-medium text-red-600';

                    // Show preview section
                    previewSection.classList.remove('hidden');
                } else {
                    alert('Please enter marks for at least one subject.');
                }
            });

            // Filter students by class in batch entry
            document.getElementById('batch_class_id').addEventListener('change', function() {
                const classId = this.value;
                if (!classId) return;

                // Fetch students via AJAX
                fetch(`get_students_by_class.php?class_id=${classId}`)
                    .then(response => response.json())
                    .then(data => {
                        const container = document.getElementById('batch-students-container');
                        container.innerHTML = '';

                        if (data.length === 0) {
                            container.innerHTML = `
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        No students found in this class.
                                    </td>
                                </tr>
                            `;
                            return;
                        }

                        data.forEach((student, index) => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <select name="students[${index}][student_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="${student.student_id}">${student.full_name} (${student.student_id})</option>
                                    </select>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" name="students[${index}][theory_marks]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" max="100" step="0.01" required>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" name="students[${index}][practical_marks]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" max="100" step="0.01">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="text" class="batch-total-marks w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md" readonly>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button type="button" class="delete-student-row text-red-600 hover:text-red-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            `;
                            container.appendChild(row);
                        });

                        // Update student row count
                        studentRowCount = data.length;
                    })
                    .catch(error => {
                        console.error('Error fetching students:', error);
                        alert('Failed to load students. Please try again.');
                    });
            });

            // Mobile sidebar toggle
            const sidebarToggle = document.getElementById('sidebar-toggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.getElementById('mobile-sidebar').classList.remove('-translate-x-full');
                });
            }

            const closeSidebar = document.getElementById('close-sidebar');
            if (closeSidebar) {
                closeSidebar.addEventListener('click', function() {
                    document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
                });
            }

            const sidebarBackdrop = document.getElementById('sidebar-backdrop');
            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', function() {
                    document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
                });
            }

            // Add event delegation for delete student row buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('.delete-student-row')) {
                    const row = e.target.closest('tr');
                    const container = document.getElementById('batch-students-container');
                    
                    // Only delete if there's more than one row
                    if (container.querySelectorAll('tr').length > 1) {
                        row.remove();
                    } else {
                        alert('You must have at least one student row.');
                    }
                }
            });
        });
    </script>
    
    <script>
        // Function to update marks distribution for manual entry
        function updateMarksDistribution(input) {
            const row = input.closest('.subject-row');
            const theoryInput = row.querySelector('.theory-marks');
            const practicalInput = row.querySelector('.practical-marks');
            const totalInput = row.querySelector('.total-marks');
            const theoryMaxSpan = row.querySelector('.theory-max-marks');
            const practicalMaxSpan = row.querySelector('.practical-max-marks');
            
            const theoryValue = parseFloat(theoryInput.value) || 0;
            const practicalValue = practicalInput.value === '' ? 0 : parseFloat(practicalInput.value) || 0;
            
            // Determine marks distribution
            if (theoryValue > 0 && practicalInput.value !== '' && practicalValue > 0) {
                // Both theory and practical provided - distribute as 75:25
                theoryInput.max = 75;
                practicalInput.max = 25;
                theoryMaxSpan.textContent = '(Max: 75)';
                practicalMaxSpan.textContent = '(Max: 25)';
                
                // Validate current values
                if (theoryValue > 75) {
                    theoryInput.value = 75;
                    alert('Theory marks cannot exceed 75 when practical marks are provided.');
                }
                if (practicalValue > 25) {
                    practicalInput.value = 25;
                    alert('Practical marks cannot exceed 25 when theory marks are provided.');
                }
            } else if (theoryValue > 0 && (practicalInput.value === '' || practicalValue === 0)) {
                // Only theory provided or practical is blank - allow up to 100 for theory
                theoryInput.max = 100;
                practicalInput.max = 0;
                theoryMaxSpan.textContent = '(Max: 100)';
                practicalMaxSpan.textContent = '(Max: 0)';
                // Don't clear practical marks if user left it blank intentionally
            } else if (theoryValue === 0 && practicalValue > 0) {
                // Only practical provided - not allowed, reset
                alert('Practical marks cannot be entered without theory marks.');
                practicalInput.value = '';
                return;
            } else {
                // Reset to default
                theoryInput.max = 100;
                practicalInput.max = 100;
                theoryMaxSpan.textContent = '(Max: 100)';
                practicalMaxSpan.textContent = '(Max: 0)';
            }
            
            // Calculate total - treat blank practical as 0 for calculation
            const finalTheory = parseFloat(theoryInput.value) || 0;
            const finalPractical = practicalInput.value === '' ? 0 : parseFloat(practicalInput.value) || 0;
            totalInput.value = finalTheory + finalPractical;
        }

        // Function to update marks distribution for batch entry
        function updateBatchMarksDistribution(input) {
            const row = input.closest('tr');
            const theoryInput = row.querySelector('.batch-theory-marks');
            const practicalInput = row.querySelector('.batch-practical-marks');
            const totalInput = row.querySelector('.batch-total-marks');
            
            const theoryValue = parseFloat(theoryInput.value) || 0;
            const practicalValue = practicalInput.value === '' ? 0 : parseFloat(practicalInput.value) || 0;
            
            // Update header max values
            const theoryMaxSpan = document.querySelector('.batch-theory-max');
            const practicalMaxSpan = document.querySelector('.batch-practical-max');
            
            // Determine marks distribution
            if (theoryValue > 0 && practicalInput.value !== '' && practicalValue > 0) {
                // Both theory and practical provided - distribute as 75:25
                theoryInput.max = 75;
                practicalInput.max = 25;
                theoryMaxSpan.textContent = '(Max: 75)';
                practicalMaxSpan.textContent = '(Max: 25)';
                
                // Validate current values
                if (theoryValue > 75) {
                    theoryInput.value = 75;
                    alert('Theory marks cannot exceed 75 when practical marks are provided.');
                }
                if (practicalValue > 25) {
                    practicalInput.value = 25;
                    alert('Practical marks cannot exceed 25 when theory marks are provided.');
                }
            } else if (theoryValue > 0 && (practicalInput.value === '' || practicalValue === 0)) {
                // Only theory provided or practical is blank - allow up to 100 for theory
                theoryInput.max = 100;
                practicalInput.max = 0;
                theoryMaxSpan.textContent = '(Max: 100)';
                practicalMaxSpan.textContent = '(Max: 0)';
                // Don't clear practical marks if user left it blank intentionally
            } else if (theoryValue === 0 && practicalValue > 0) {
                // Only practical provided - not allowed, reset
                alert('Practical marks cannot be entered without theory marks.');
                practicalInput.value = '';
                return;
            } else {
                // Reset to default
                theoryInput.max = 100;
                practicalInput.max = 100;
                theoryMaxSpan.textContent = '(Max: 100)';
                practicalMaxSpan.textContent = '(Max: 0)';
            }
            
            // Calculate total - treat blank practical as 0 for calculation
            const finalTheory = parseFloat(theoryInput.value) || 0;
            const finalPractical = practicalInput.value === '' ? 0 : parseFloat(practicalInput.value) || 0;
            totalInput.value = finalTheory + finalPractical;
            
            // Update all other rows in batch entry to maintain consistency
            updateAllBatchRows();
        }

        // Function to update all batch entry rows with consistent max values
        function updateAllBatchRows() {
            const container = document.getElementById('batch-students-container');
            const rows = container.querySelectorAll('tr');
            const theoryMaxSpan = document.querySelector('.batch-theory-max');
            const practicalMaxSpan = document.querySelector('.batch-practical-max');
            
            // Get max values from header
            const theoryMax = theoryMaxSpan.textContent.includes('75') ? 75 : 100;
            const practicalMax = practicalMaxSpan.textContent.includes('25') ? 25 : 0;
            
            rows.forEach(row => {
                const theoryInput = row.querySelector('.batch-theory-marks');
                const practicalInput = row.querySelector('.batch-practical-marks');
                
                if (theoryInput && practicalInput) {
                    theoryInput.max = theoryMax;
                    practicalInput.max = practicalMax;
                    
                    if (practicalMax === 0) {
                        // Don't clear the value, just disable if max is 0
                        practicalInput.disabled = true;
                    } else {
                        practicalInput.disabled = false;
                    }
                }
            });
        }
    </script>
    
    <script>
        // Add Subject Row (updated version)
        document.getElementById('addSubject').addEventListener('click', function() {
            const container = document.getElementById('subjectsContainer');
            const subjectRow = document.querySelector('.subject-row').cloneNode(true);

            // Clear input values
            subjectRow.querySelectorAll('input').forEach(input => {
                input.value = '';
            });

            // Reset select
            subjectRow.querySelector('select').selectedIndex = 0;
            
            // Reset max marks display
            subjectRow.querySelector('.theory-max-marks').textContent = '(Max: 100)';
            subjectRow.querySelector('.practical-max-marks').textContent = '(Max: 0)';
            subjectRow.querySelector('.theory-marks').max = 100;
            subjectRow.querySelector('.practical-marks').max = 100;

            // Add event listeners for marks distribution
            subjectRow.querySelector('.theory-marks').addEventListener('change', function() {
                updateMarksDistribution(this);
            });
            
            subjectRow.querySelector('.practical-marks').addEventListener('change', function() {
                updateMarksDistribution(this);
            });

            // Add event listener to remove button
            subjectRow.querySelector('.remove-subject').addEventListener('click', function() {
                if (container.children.length > 1) {
                    this.closest('.subject-row').remove();
                }
            });

            container.appendChild(subjectRow);
        });
    </script>
    
    <script>
        // Add student row in batch entry (updated version)
        // studentRowCount is already declared above
        document.getElementById('add-student-row').addEventListener('click', function() {
            const container = document.getElementById('batch-students-container');
            const newRow = document.createElement('tr');

            // Get the HTML content of the first student row
            const firstRow = container.querySelector('tr');
            const studentSelectHTML = firstRow.querySelector('td:first-child select').outerHTML;

            // Replace the name attribute to use the new index
            const updatedStudentSelectHTML = studentSelectHTML.replace(/students\[0\]/g, `students[${studentRowCount}]`);

            // Get current max values
            const theoryMaxSpan = document.querySelector('.batch-theory-max');
            const practicalMaxSpan = document.querySelector('.batch-practical-max');
            const theoryMax = theoryMaxSpan.textContent.includes('75') ? 75 : 100;
            const practicalMax = practicalMaxSpan.textContent.includes('25') ? 25 : 0;

            newRow.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap">
                    ${updatedStudentSelectHTML}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="number" name="students[${studentRowCount}][theory_marks]" 
                           class="batch-theory-marks w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           min="0" max="${theoryMax}" step="0.01" required
                           onchange="updateBatchMarksDistribution(this)">
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="number" name="students[${studentRowCount}][practical_marks]" 
                           class="batch-practical-marks w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           min="0" max="${practicalMax}" step="0.01" ${practicalMax === 0 ? 'disabled' : ''}
                           onchange="updateBatchMarksDistribution(this)">
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="text" class="batch-total-marks w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md" readonly>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <button type="button" class="delete-student-row text-red-600 hover:text-red-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </td>
            `;

            container.appendChild(newRow);
            studentRowCount++;
        });
    </script>
</body>

</html>
<?php
function studentHasResults($conn, $student_id, $exam_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM results WHERE student_id = ? AND exam_id = ?");
    $stmt->bind_param("si", $student_id, $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}
?>
