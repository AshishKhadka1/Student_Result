<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all classes
$classes = [];
$result = $conn->query("SELECT class_id, class_name, section, academic_year FROM classes ORDER BY academic_year DESC, class_name, section");
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

// Get all exams
$exams = [];
$result = $conn->query("SELECT exam_id, exam_name, exam_type, class_id, academic_year FROM exams ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}

// Get all subjects
$subjects = [];
$result = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'upload_csv') {
        $exam_id = $_POST['exam_id'];
        $subject_id = $_POST['subject_id'];
        
        // Validate exam and subject
        if (empty($exam_id) || empty($subject_id)) {
            $_SESSION['error'] = "Please select both exam and subject.";
        } else {
            // Check if file was uploaded without errors
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
                $file_name = $_FILES['csv_file']['name'];
                $file_tmp = $_FILES['csv_file']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Check file extension
                if ($file_ext == 'csv') {
                    // Read CSV file
                    $handle = fopen($file_tmp, "r");
                    if ($handle) {
                        // Begin transaction
                        $conn->begin_transaction();
                        
                        try {
                            $row_count = 0;
                            $success_count = 0;
                            $error_count = 0;
                            $errors = [];
                            
                            // Skip header row
                            fgetcsv($handle);
                            
                            while (($data = fgetcsv($handle)) !== FALSE) {
                                $row_count++;
                                
                                // Check if we have enough columns
                                if (count($data) < 4) {
                                    $errors[] = "Row $row_count: Not enough columns.";
                                    $error_count++;
                                    continue;
                                }
                                
                                $student_id = trim($data[0]);
                                $theory_marks = is_numeric($data[1]) ? (int)$data[1] : null;
                                $practical_marks = is_numeric($data[2]) ? (int)$data[2] : null;
                                $grade = trim($data[3]);
                                $gpa = isset($data[4]) && is_numeric($data[4]) ? (float)$data[4] : null;
                                
                                // Validate data
                                if (empty($student_id)) {
                                    $errors[] = "Row $row_count: Student ID is required.";
                                    $error_count++;
                                    continue;
                                }
                                
                                if ($theory_marks === null && $practical_marks === null) {
                                    $errors[] = "Row $row_count: At least one of theory or practical marks is required.";
                                    $error_count++;
                                    continue;
                                }
                                
                                if ($theory_marks !== null && ($theory_marks < 0 || $theory_marks > 100)) {
                                    $errors[] = "Row $row_count: Theory marks must be between 0 and 100.";
                                    $error_count++;
                                    continue;
                                }
                                
                                if ($practical_marks !== null && ($practical_marks < 0 || $practical_marks > 100)) {
                                    $errors[] = "Row $row_count: Practical marks must be between 0 and 100.";
                                    $error_count++;
                                    continue;
                                }
                                
                                // Check if student exists
                                $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
                                $stmt->bind_param("s", $student_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($result->num_rows == 0) {
                                    $errors[] = "Row $row_count: Student ID '$student_id' does not exist.";
                                    $error_count++;
                                    continue;
                                }
                                
                                // Check if result already exists
                                $stmt = $conn->prepare("SELECT result_id FROM results WHERE student_id = ? AND exam_id = ? AND subject_id = ?");
                                $stmt->bind_param("sis", $student_id, $exam_id, $subject_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($result->num_rows > 0) {
                                    // Update existing result
                                    $result_id = $result->fetch_assoc()['result_id'];
                                    $stmt = $conn->prepare("UPDATE results SET theory_marks = ?, practical_marks = ?, grade = ?, gpa = ?, updated_at = NOW() WHERE result_id = ?");
                                    $stmt->bind_param("iisdi", $theory_marks, $practical_marks, $grade, $gpa, $result_id);
                                } else {
                                    // Insert new result
                                    $stmt = $conn->prepare("INSERT INTO results (student_id, exam_id, subject_id, theory_marks, practical_marks, grade, gpa, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                                    $stmt->bind_param("sisiisi", $student_id, $exam_id, $subject_id, $theory_marks, $practical_marks, $grade, $gpa);
                                }
                                
                                if ($stmt->execute()) {
                                    $success_count++;
                                } else {
                                    $errors[] = "Row $row_count: " . $stmt->error;
                                    $error_count++;
                                }
                            }
                            
                            fclose($handle);
                            
                            // Commit transaction
                            $conn->commit();
                            
                            // Update student_performance table
                            updateStudentPerformance($conn, $exam_id);
                            
                            // Set session messages
                            $_SESSION['success'] = "CSV file processed successfully. $success_count records imported.";
                            
                            if ($error_count > 0) {
                                $_SESSION['error'] = "$error_count errors occurred during import. See details below.";
                                $_SESSION['import_errors'] = $errors;
                            }
                        } catch (Exception $e) {
                            // Rollback transaction on error
                            $conn->rollback();
                            $_SESSION['error'] = "Error processing CSV file: " . $e->getMessage();
                        }
                    } else {
                        $_SESSION['error'] = "Could not open the CSV file.";
                    }
                } else {
                    $_SESSION['error'] = "Only CSV files are allowed.";
                }
            } else {
                $_SESSION['error'] = "Please select a CSV file to upload.";
            }
        }
        
        // Redirect to prevent form resubmission
        header("Location: bulk_upload.php");
        exit();
    }
}

// Function to update student performance
function updateStudentPerformance($conn, $exam_id) {
    // Get all students who have results for this exam
    $stmt = $conn->prepare("
        SELECT DISTINCT r.student_id
        FROM results r
        WHERE r.exam_id = ?
    ");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $student_id = $row['student_id'];
        
        // Calculate average marks and GPA
        $stmt = $conn->prepare("
            SELECT 
                AVG(r.theory_marks + r.practical_marks) as average_marks,
                AVG(r.gpa) as avg_gpa,
                COUNT(r.subject_id) as total_subjects,
                SUM(CASE WHEN (r.theory_marks + r.practical_marks) >= 33 THEN 1 ELSE 0 END) as subjects_passed
            FROM 
                results r
            WHERE 
                r.student_id = ? AND r.exam_id = ?
        ");
        $stmt->bind_param("si", $student_id, $exam_id);
        $stmt->execute();
        $perf_result = $stmt->get_result();
        $performance = $perf_result->fetch_assoc();
        
        $average_marks = $performance['average_marks'];
        $gpa = $performance['avg_gpa'];
        $total_subjects = $performance['total_subjects'];
        $subjects_passed = $performance['subjects_passed'];
        
        // Calculate rank
        $stmt = $conn->prepare("
            SELECT 
                s.class_id
            FROM 
                students s
            WHERE 
                s.student_id = ?
        ");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $class_result = $stmt->get_result();
        $class_id = $class_result->fetch_assoc()['class_id'];
        
        // Check if student performance record exists
        $stmt = $conn->prepare("
            SELECT 
                performance_id
            FROM 
                student_performance
            WHERE 
                student_id = ? AND exam_id = ?
        ");
        $stmt->bind_param("si", $student_id, $exam_id);
        $stmt->execute();
        $perf_exists = $stmt->get_result();
        
        if ($perf_exists->num_rows > 0) {
            // Update existing record
            $performance_id = $perf_exists->fetch_assoc()['performance_id'];
            $stmt = $conn->prepare("
                UPDATE 
                    student_performance
                SET 
                    average_marks = ?,
                    gpa = ?,
                    total_subjects = ?,
                    subjects_passed = ?,
                    updated_at = NOW()
                WHERE 
                    performance_id = ?
            ");
            $stmt->bind_param("ddiii", $average_marks, $gpa, $total_subjects, $subjects_passed, $performance_id);
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO 
                    student_performance
                    (student_id, exam_id, average_marks, gpa, total_subjects, subjects_passed, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("siddii", $student_id, $exam_id, $average_marks, $gpa, $total_subjects, $subjects_passed);
        }
        
        $stmt->execute();
    }
    
    // Update ranks within each class for this exam
    $stmt = $conn->prepare("
        SELECT DISTINCT s.class_id
        FROM students s
        JOIN results r ON s.student_id = r.student_id
        WHERE r.exam_id = ?
    ");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $class_id = $row['class_id'];
        
        // Get students in this class sorted by GPA
        $stmt = $conn->prepare("
            SELECT 
                sp.performance_id,
                sp.student_id,
                sp.gpa
            FROM 
                student_performance sp
            JOIN 
                students s ON sp.student_id = s.student_id
            WHERE 
                s.class_id = ? AND sp.exam_id = ?
            ORDER BY 
                sp.gpa DESC
        ");
        $stmt->bind_param("ii", $class_id, $exam_id);
        $stmt->execute();
        $students_result = $stmt->get_result();
        
        $rank = 1;
        while ($student = $students_result->fetch_assoc()) {
            $performance_id = $student['performance_id'];
            
            // Update rank
            $stmt = $conn->prepare("
                UPDATE 
                    student_performance
                SET 
                    rank = ?,
                    updated_at = NOW()
                WHERE 
                    performance_id = ?
            ");
            $stmt->bind_param("ii", $rank, $performance_id);
            $stmt->execute();
            
            $rank++;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php
        // Include the file that processes form data
        include 'sidebar.php';
        ?>

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
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            Results
                        </a>
                        <a href="bulk_upload.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-upload mr-3"></i>
                            Bulk Upload
                        </a>
                        <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-users mr-3"></i>
                            Users
                        </a>
                        <a href="classes.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard mr-3"></i>
                            Classes
                        </a>
                        <a href="subject.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-book mr-3"></i>
                            Subjects
                        </a>
                        <a href="teachers.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard-teacher mr-3"></i>
                            Teachers
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-cog mr-3"></i>
                            Settings
                        </a>
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Bulk Upload Results</h1>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
                        <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <span class="sr-only">View notifications</span>
                            <i class="fas fa-bell"></i>
                        </button>

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

                            <!-- Profile dropdown menu -->
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" id="user-menu" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Notification Messages -->
                        <?php if(isset($_SESSION['success'])): ?>
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">
                                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">
                                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                    </p>
                                    
                                    <?php if(isset($_SESSION['import_errors'])): ?>
                                        <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                            <?php foreach ($_SESSION['import_errors'] as $error): ?>
                                                <li><?php echo $error; ?></li>
                                            <?php endforeach; ?>
                                            <?php unset($_SESSION['import_errors']); ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Upload Form -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Upload Results CSV</h2>
                            <form action="bulk_upload.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                                <input type="hidden" name="action" value="upload_csv">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-1">Exam</label>
                                        <select id="exam_id" name="exam_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <option value="">Select Exam</option>
                                            <?php foreach ($exams as $exam): ?>
                                                <option value="<?php echo $exam['exam_id']; ?>" data-class="<?php echo $exam['class_id']; ?>">
                                                    <?php echo $exam['exam_name'] . ' (' . $exam['academic_year'] . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                        <select id="subject_id" name="subject_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <option value="">Select Subject</option>
                                            <?php foreach ($subjects as $subject): ?>
                                                <option value="<?php echo $subject['subject_id']; ?>">
                                                    <?php echo $subject['subject_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-1">CSV File</label>
                                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                    <p class="mt-1 text-sm text-gray-500">
                                        Upload a CSV file with the following columns: Student ID, Theory Marks, Practical Marks, Grade, GPA (optional)
                                    </p>
                                </div>
                                
                                <div class="flex justify-end space-x-3 pt-4">
                                    <a href="admin_dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </a>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-upload mr-2"></i> Upload CSV
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- CSV Template -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">CSV Template</h2>
                            <p class="text-sm text-gray-500 mb-4">
                                Download the CSV template below and fill it with your data. The first row is the header and should not be modified.
                            </p>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Theory Marks</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Practical Marks</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GPA (Optional)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">S001</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">85</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">90</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">A+</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">4.0</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">S002</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">75</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">80</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">A</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">3.7</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4">
                                <a href="templates/results_template.csv" download class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <i class="fas fa-download mr-2"></i> Download Template
                                </a>
                            </div>
                        </div>

                        <!-- Instructions -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Instructions</h2>
                            <div class="text-sm text-gray-500 space-y-2">
                                <p>
                                    <strong>1. Prepare your CSV file:</strong> Use the template above or create your own CSV file with the required columns.
                                </p>
                                <p>
                                    <strong>2. Fill in the data:</strong> Each row should contain data for one student and one subject.
                                </p>
                                <ul class="list-disc list-inside ml-4 space-y-1">
                                    <li><strong>Student ID:</strong> The unique identifier for the student (e.g., S001).</li>
                                    <li><strong>Theory Marks:</strong> Marks obtained in theory (0-100).</li>
                                    <li><strong>Practical Marks:</strong> Marks obtained in practical (0-100).</li>
                                    <li><strong>Grade:</strong> The grade assigned (e.g., A+, A, B+, etc.).</li>
                                    <li><strong>GPA:</strong> The GPA for this subject (optional).</li>
                                </ul>
                                <p>
                                    <strong>3. Select Exam and Subject:</strong> Choose the exam and subject for which you are uploading results.
                                </p>
                                <p>
                                    <strong>4. Upload the CSV file:</strong> Click the "Upload CSV" button to process the file.
                                </p>
                                <p>
                                    <strong>5. Review Results:</strong> After uploading, you will see a summary of the import process.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.remove('-translate-x-full');
        });
        
        document.getElementById('close-sidebar').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });
        
        document.getElementById('sidebar-backdrop').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        });
        
        // User menu toggle
        document.getElementById('user-menu-button').addEventListener('click', function() {
            document.getElementById('user-menu').classList.toggle('hidden');
        });
        
        // Close user menu when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('user-menu');
            const userMenuButton = document.getElementById('user-menu-button');
            
            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });
        
        // Filter subjects based on exam class
        document.getElementById('exam_id').addEventListener('change', function() {
            const examOption = this.options[this.selectedIndex];
            const classId = examOption.getAttribute('data-class');
            
            // You can add logic here to filter subjects based on class if needed
        });
    </script>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

</body>
</html>