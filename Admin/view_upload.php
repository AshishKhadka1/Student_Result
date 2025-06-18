<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Upload ID is required.";
    header("Location: manage_results.php?tab=manage");
    exit();
}

$upload_id = $_GET['id'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get upload details
$stmt = $conn->prepare("
    SELECT ru.*, u.full_name as uploaded_by_name, e.exam_name, c.class_name, c.section 
    FROM result_uploads ru
    LEFT JOIN users u ON ru.uploaded_by = u.user_id
    LEFT JOIN exams e ON ru.exam_id = e.exam_id
    LEFT JOIN classes c ON ru.class_id = c.class_id
    WHERE ru.id = ?
");

$stmt->bind_param("i", $upload_id);
$stmt->execute();
$upload = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$upload) {
    $_SESSION['error_message'] = "Upload not found.";
    header("Location: manage_results.php?tab=manage");
    exit();
}

// Get results for this upload
$stmt = $conn->prepare("
    SELECT r.*, s.subject_name, st.roll_number, u.full_name as student_name
    FROM results r
    JOIN subjects s ON r.subject_id = s.subject_id
    JOIN students st ON r.student_id = st.student_id
    JOIN users u ON st.user_id = u.user_id
    WHERE r.upload_id = ?
    ORDER BY u.full_name, s.subject_name
");

$stmt->bind_param("i", $upload_id);
$stmt->execute();
$results = $stmt->get_result();
$stmt->close();

// Count total students
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT student_id) as total_students
    FROM results
    WHERE upload_id = ?
");

$stmt->bind_param("i", $upload_id);
$stmt->execute();
$total_students = $stmt->get_result()->fetch_assoc()['total_students'];
$stmt->close();

// Count total subjects
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT subject_id) as total_subjects
    FROM results
    WHERE upload_id = ?
");

$stmt->bind_param("i", $upload_id);
$stmt->execute();
$total_subjects = $stmt->get_result()->fetch_assoc()['total_subjects'];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Upload | Result Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
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
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <?php include 'topBar.php'; ?>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <div class="flex items-center justify-between mb-6">
                            <h1 class="text-2xl font-semibold text-gray-900">View Upload Details</h1>
                            <a href="manage_results.php?tab=manage" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-gray-700 bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Manage Uploads
                            </a>
                        </div>

                        <!-- Upload Details -->
                        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                            <div class="px-4 py-5 sm:px-6 bg-gray-50">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Upload Information</h3>
                                <p class="mt-1 max-w-2xl text-sm text-gray-500">Details about the result upload.</p>
                            </div>
                            <div class="border-t border-gray-200">
                                <dl>
                                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">File Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo htmlspecialchars($upload['file_name']); ?></dd>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">Description</dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo htmlspecialchars($upload['description'] ?? 'N/A'); ?></dd>
                                    </div>
                                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">Exam</dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo htmlspecialchars($upload['exam_name'] ?? 'N/A'); ?></dd>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">Class</dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                            <?php 
                                            if (!empty($upload['class_name'])) {
                                                echo htmlspecialchars($upload['class_name']);
                                                if (!empty($upload['section'])) {
                                                    echo ' ' . htmlspecialchars($upload['section']);
                                                }
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </dd>
                                    </div>
                                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">Upload Date</dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo date('F d, Y h:i A', strtotime($upload['upload_date'])); ?></dd>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">Uploaded By</dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo htmlspecialchars($upload['uploaded_by_name'] ?? 'N/A'); ?></dd>
                                    </div>
                                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd class="mt-1 sm:mt-0 sm:col-span-2">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $upload['status'] == 'Published' ? 'green' : 'yellow'; ?>-100 text-<?php echo $upload['status'] == 'Published' ? 'green' : 'yellow'; ?>-800">
                                                <?php echo htmlspecialchars($upload['status']); ?>
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">Total Students</dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo number_format($total_students); ?></dd>
                                    </div>
                                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">Total Subjects</dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo number_format($total_subjects); ?></dd>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt class="text-sm font-medium text-gray-500">Actions</dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                            <div class="flex space-x-3">
                                                <?php if ($upload['status'] != 'Published'): ?>
                                                    <a href="publish_results.php?id=<?php echo $upload_id; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                        <i class="fas fa-check-circle mr-1"></i> Publish
                                                    </a>
                                                <?php else: ?>
                                                    <a href="unpublish_results.php?id=<?php echo $upload_id; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                                        <i class="fas fa-eye-slash mr-1"></i> Unpublish
                                                    </a>
                                                <?php endif; ?>
                                                <a href="delete_upload.php?id=<?php echo $upload_id; ?>" onclick="return confirm('Are you sure you want to delete this upload? This will also delete all associated results.')" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                                </a>
                                            </div>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Results Table -->
                        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                            <div class="px-4 py-5 sm:px-6 bg-gray-50 flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Results</h3>
                                    <p class="mt-1 max-w-2xl text-sm text-gray-500">List of results in this upload.</p>
                                </div>
                                <div>
                                    <input type="text" id="searchInput" placeholder="Search by name or subject..." class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll Number</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Theory</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Practical</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200" id="resultsTableBody">
                                        <?php if ($results && $results->num_rows > 0): ?>
                                            <?php while ($row = $results->fetch_assoc()): ?>
                                                <?php 
                                                $total = $row['theory_marks'] + $row['practical_marks'];
                                                $percentage = ($total / 100) * 100; // Assuming max marks is 100
                                                
                                                // Calculate grade
                                                if ($percentage >= 90) {
                                                    $grade = 'A+';
                                                    $gradeClass = 'bg-green-100 text-green-800';
                                                } elseif ($percentage >= 80) {
                                                    $grade = 'A';
                                                    $gradeClass = 'bg-green-100 text-green-800';
                                                } elseif ($percentage >= 70) {
                                                    $grade = 'B+';
                                                    $gradeClass = 'bg-green-100 text-green-800';
                                                } elseif ($percentage >= 60) {
                                                    $grade = 'B';
                                                    $gradeClass = 'bg-green-100 text-green-800';
                                                } elseif ($percentage >= 50) {
                                                    $grade = 'C+';
                                                    $gradeClass = 'bg-green-100 text-green-800';
                                                } elseif ($percentage >= 40) {
                                                    $grade = 'C';
                                                    $gradeClass = 'bg-green-100 text-green-800';
                                                } elseif ($percentage >= 33) {
                                                    $grade = 'D';
                                                    $gradeClass = 'bg-yellow-100 text-yellow-800';
                                                } else {
                                                    $grade = 'F';
                                                    $gradeClass = 'bg-red-100 text-red-800';
                                                }
                                                ?>
                                                <tr class="result-row">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['student_name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($row['roll_number']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($row['theory_marks'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($row['practical_marks'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($total, 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $gradeClass; ?>">
                                                            <?php echo $grade; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No results found for this upload.</td>
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
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#resultsTableBody .result-row');
            
            rows.forEach(row => {
                const studentName = row.cells[0].textContent.toLowerCase();
                const rollNumber = row.cells[1].textContent.toLowerCase();
                const subject = row.cells[2].textContent.toLowerCase();
                
                if (studentName.includes(searchValue) || rollNumber.includes(searchValue) || subject.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
