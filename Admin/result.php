<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'teacher' && $_SESSION['role'] != 'admin')) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get teacher information if user is a teacher
$teacher_id = null;
if ($role == 'teacher') {
    $stmt = $conn->prepare("SELECT teacher_id FROM Teachers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $teacher_id = $result->fetch_assoc()['teacher_id'];
    }
    $stmt->close();
}

// Get classes based on role
$classes = [];
if ($role == 'teacher' && $teacher_id) {
    // Get classes taught by this teacher
    $sql = "SELECT DISTINCT c.class_id, c.class_name, c.section, c.academic_year 
            FROM Classes c 
            JOIN teacher_subjects ts ON c.class_id = ts.class_id 
            WHERE ts.teacher_id = ? 
            ORDER BY c.class_name, c.section";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
} else {
    // Admin can see all classes
    $sql = "SELECT class_id, class_name, section, academic_year 
            FROM Classes 
            ORDER BY class_name, section";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();

// Get exams
$exams = [];
$sql = "SELECT exam_id, exam_name, exam_type, academic_year FROM Exams ORDER BY exam_date DESC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}

// Handle form submission for adding/updating results
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_result') {
        $student_id = $_POST['student_id'];
        $subject_id = $_POST['subject_id'];
        $exam_id = $_POST['exam_id'];
        $marks_obtained = $_POST['marks_obtained'];
        $total_marks = $_POST['total_marks'];
        $grade = calculateGrade($marks_obtained, $total_marks);
        $is_pass = ($grade != 'F') ? 1 : 0;
        $remarks = $_POST['remarks'] ?? '';
        
        // Check if result already exists
        $stmt = $conn->prepare("SELECT result_id FROM Results WHERE student_id = ? AND subject_id = ? AND exam_id = ?");
        $stmt->bind_param("isi", $student_id, $subject_id, $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing result
            $result_id = $result->fetch_assoc()['result_id'];
            $stmt = $conn->prepare("UPDATE Results SET marks_obtained = ?, total_marks = ?, grade = ?, is_pass = ?, remarks = ?, updated_at = NOW() WHERE result_id = ?");
            $stmt->bind_param("ddssii", $marks_obtained, $total_marks, $grade, $is_pass, $remarks, $result_id);
            $stmt->execute();
            $_SESSION['success'] = "Result updated successfully!";
        } else {
            // Insert new result
            $stmt = $conn->prepare("INSERT INTO Results (student_id, subject_id, exam_id, marks_obtained, total_marks, grade, is_pass, remarks, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isiddsis", $student_id, $subject_id, $exam_id, $marks_obtained, $total_marks, $grade, $is_pass, $remarks);
            $stmt->execute();
            $_SESSION['success'] = "Result added successfully!";
        }
        $stmt->close();
    } elseif ($_POST['action'] == 'import_csv') {
        // Handle CSV import
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $class_id = $_POST['class_id'];
            $subject_id = $_POST['subject_id'];
            $exam_id = $_POST['exam_id'];
            
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, "r");
            
            // Skip header row
            fgetcsv($handle, 1000, ",");
            
            $success_count = 0;
            $error_count = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Expected CSV format: roll_number, marks_obtained, total_marks, remarks
                $roll_number = $data[0];
                $marks_obtained = $data[1];
                $total_marks = $data[2];
                $remarks = $data[3] ?? '';
                
                // Get student_id from roll_number
                $stmt = $conn->prepare("SELECT student_id FROM Students WHERE roll_number = ? AND class_id = ?");
                $stmt->bind_param("si", $roll_number, $class_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $student_id = $result->fetch_assoc()['student_id'];
                    $grade = calculateGrade($marks_obtained, $total_marks);
                    $is_pass = ($grade != 'F') ? 1 : 0;
                    
                    // Check if result already exists
                    $check_stmt = $conn->prepare("SELECT result_id FROM Results WHERE student_id = ? AND subject_id = ? AND exam_id = ?");
                    $check_stmt->bind_param("isi", $student_id, $subject_id, $exam_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        // Update existing result
                        $result_id = $check_result->fetch_assoc()['result_id'];
                        $update_stmt = $conn->prepare("UPDATE Results SET marks_obtained = ?, total_marks = ?, grade = ?, is_pass = ?, remarks = ?, updated_at = NOW() WHERE result_id = ?");
                        $update_stmt->bind_param("ddssii", $marks_obtained, $total_marks, $grade, $is_pass, $remarks, $result_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    } else {
                        // Insert new result
                        $insert_stmt = $conn->prepare("INSERT INTO Results (student_id, subject_id, exam_id, marks_obtained, total_marks, grade, is_pass, remarks, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $insert_stmt->bind_param("isiddsis", $student_id, $subject_id, $exam_id, $marks_obtained, $total_marks, $grade, $is_pass, $remarks);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                    $check_stmt->close();
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            }
            
            fclose($handle);
            
            if ($success_count > 0) {
                $_SESSION['success'] = "Successfully imported $success_count results. $error_count errors encountered.";
            } else {
                $_SESSION['error'] = "No results were imported. Please check your CSV file format and data.";
            }
        } else {
            $_SESSION['error'] = "Error uploading file. Please try again.";
        }
    } elseif ($_POST['action'] == 'delete_result') {
        $result_id = $_POST['result_id'];
        
        $stmt = $conn->prepare("DELETE FROM Results WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = "Result deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete result.";
        }
        $stmt->close();
    }
    
    // Redirect to prevent form resubmission
    header("Location: result.php");
    exit();
}

// Function to calculate grade based on marks
function calculateGrade($marks_obtained, $total_marks) {
    $percentage = ($marks_obtained / $total_marks) * 100;
    
    if ($percentage >= 90) return 'A+';
    elseif ($percentage >= 80) return 'A';
    elseif ($percentage >= 70) return 'B+';
    elseif ($percentage >= 60) return 'B';
    elseif ($percentage >= 50) return 'C+';
    elseif ($percentage >= 40) return 'C';
    elseif ($percentage >= 33) return 'D';
    else return 'F';
}

// Get students and results based on selected class, subject, and exam
$students = [];
$results = [];
$selected_class_id = $_GET['class_id'] ?? null;
$selected_subject_id = $_GET['subject_id'] ?? null;
$selected_exam_id = $_GET['exam_id'] ?? null;

if ($selected_class_id && $selected_subject_id && $selected_exam_id) {
    // Get students in the selected class
    $stmt = $conn->prepare("SELECT s.student_id, s.roll_number, u.full_name 
                           FROM Students s 
                           JOIN Users u ON s.user_id = u.user_id 
                           WHERE s.class_id = ? 
                           ORDER BY s.roll_number");
    $stmt->bind_param("i", $selected_class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    
    // Get existing results for the selected class, subject, and exam
    $stmt = $conn->prepare("SELECT r.*, s.roll_number, u.full_name as student_name 
                           FROM Results r 
                           JOIN Students s ON r.student_id = s.student_id 
                           JOIN Users u ON s.user_id = u.user_id 
                           WHERE s.class_id = ? AND r.subject_id = ? AND r.exam_id = ? 
                           ORDER BY s.roll_number");
    $stmt->bind_param("isi", $selected_class_id, $selected_subject_id, $selected_exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results[$row['student_id']] = $row;
    }
    $stmt->close();
}

// Get subjects based on selected class
$subjects = [];
if ($selected_class_id) {
    if ($role == 'teacher' && $teacher_id) {
        // Get subjects taught by this teacher in the selected class
        $stmt = $conn->prepare("SELECT s.subject_id, s.subject_name, s.subject_code 
                               FROM Subjects s 
                               JOIN teacher_subjects ts ON s.subject_id = ts.subject_id 
                               WHERE ts.teacher_id = ? AND ts.class_id = ? 
                               ORDER BY s.subject_name");
        $stmt->bind_param("ii", $teacher_id, $selected_class_id);
    } else {
        // Admin can see all subjects in the selected class
        $stmt = $conn->prepare("SELECT subject_id, subject_name, subject_code 
                               FROM Subjects 
                               WHERE class_id = ? 
                               ORDER BY subject_name");
        $stmt->bind_param("i", $selected_class_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Results | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 bg-gray-800">
                <div class="flex items-center justify-center h-16 bg-gray-900">
                    <span class="text-white text-lg font-semibold">Result Management</span>
                </div>
                <div class="flex flex-col flex-grow px-4 mt-5">
                    <nav class="flex-1 space-y-1">
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-md">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="result.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-clipboard-list mr-3"></i>
                            Results
                        </a>
                        <a href="bulk_upload.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-upload mr-3"></i>
                            Bulk Upload
                        </a>
                        <a href="users.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-users mr-3"></i>
                            Users
                        </a>
                        <a href="students.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-user-graduate mr-3"></i>
                            Students
                        </a>
                        <a href="teachers.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard-teacher mr-3"></i>
                            Teachers
                        </a>
                        <a href="classes.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chalkboard mr-3"></i>
                            Classes
                        </a>
                        <a href="exams.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            Exams
                        </a>
                        <a href="reports.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-chart-bar mr-3"></i>
                            Reports
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 mt-1 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-cog mr-3"></i>
                            Settings
                        </a>
                    </nav>
                    <div class="flex-shrink-0 block w-full">
                        <a href="logout.php" class="flex items-center px-4 py-2 mt-5 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Manage Results</h1>
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
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Filter Section -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Select Class, Subject and Exam</h2>
                            <form action="result.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                    <select id="class_id" name="class_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" onchange="this.form.submit()">
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" <?php echo ($selected_class_id == $class['class_id']) ? 'selected' : ''; ?>>
                                            <?php echo $class['class_name'] . ' ' . $class['section'] . ' (' . $class['academic_year'] . ')'; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                    <select id="subject_id" name="subject_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" onchange="this.form.submit()" <?php echo empty($selected_class_id) ? 'disabled' : ''; ?>>
                                        <option value="">Select Subject</option>
                                        <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['subject_id']; ?>" <?php echo ($selected_subject_id == $subject['subject_id']) ? 'selected' : ''; ?>>
                                            <?php echo $subject['subject_name'] . ' (' . $subject['subject_code'] . ')'; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="exam_id" class="block text-sm font-medium text-gray-700 mb-1">Exam</label>
                                    <select id="exam_id" name="exam_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" onchange="this.form.submit()">
                                        <option value="">Select Exam</option>
                                        <?php foreach ($exams as $exam): ?>
                                        <option value="<?php echo $exam['exam_id']; ?>" <?php echo ($selected_exam_id == $exam['exam_id']) ? 'selected' : ''; ?>>
                                            <?php echo $exam['exam_name'] . ' (' . $exam['exam_type'] . ' - ' . $exam['academic_year'] . ')'; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>

                        <?php if ($selected_class_id && $selected_subject_id && $selected_exam_id): ?>
                        <!-- Import Results Section -->
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Import Results from CSV</h2>
                            <form action="result.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="action" value="import_csv">
                                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                                <input type="hidden" name="subject_id" value="<?php echo $selected_subject_id; ?>">
                                <input type="hidden" name="exam_id" value="<?php echo $selected_exam_id; ?>">
                                
                                <div>
                                    <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-1">CSV File</label>
                                    <input type="file" id="csv_file" name="csv_file" accept=".csv" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                    <p class="mt-1 text-sm text-gray-500">
                                        CSV format: roll_number, marks_obtained, total_marks, remarks
                                    </p>
                                </div>
                                
                                <div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-file-import mr-2"></i> Import Results
                                    </button>
                                    <a href="templates/result_import_template.csv" download class="ml-4 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-download mr-2"></i> Download Template
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Results Table -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 border-b border-gray-200 sm:px-6 flex justify-between items-center">
                                <h2 class="text-lg font-medium text-gray-900">Student Results</h2>
                                <div class="flex space-x-2">
                                    <button onclick="printResults()" class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-print mr-2"></i> Print
                                    </button>
                                    <button onclick="exportToExcel()" class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-file-excel mr-2"></i> Export
                                    </button>
                                </div>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="overflow-x-auto">
                                    <table id="resultsTable" class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks Obtained</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Marks</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['roll_number']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $student['full_name']; ?></td>
                                                
                                                <?php if (isset($results[$student['student_id']])): ?>
                                                <!-- Result exists - show result data -->
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $results[$student['student_id']]['marks_obtained']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $results[$student['student_id']]['total_marks']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php 
                                                        $grade = $results[$student['student_id']]['grade'];
                                                        if ($grade == 'A+' || $grade == 'A') echo 'bg-green-100 text-green-800';
                                                        elseif ($grade == 'B+' || $grade == 'B') echo 'bg-blue-100 text-blue-800';
                                                        elseif ($grade == 'C+' || $grade == 'C') echo 'bg-yellow-100 text-yellow-800';
                                                        elseif ($grade == 'D') echo 'bg-orange-100 text-orange-800';
                                                        else echo 'bg-red-100 text-red-800';
                                                        ?>">
                                                        <?php echo $grade; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $results[$student['student_id']]['remarks']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button type="button" onclick="editResult(<?php echo $student['student_id']; ?>, '<?php echo $results[$student['student_id']]['marks_obtained']; ?>', '<?php echo $results[$student['student_id']]['total_marks']; ?>', '<?php echo $results[$student['student_id']]['remarks']; ?>')" class="text-blue-600 hover:text-blue-900 mr-3">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form action="result.php" method="POST" class="inline-block">
                                                        <input type="hidden" name="action" value="delete_result">
                                                        <input type="hidden" name="result_id" value="<?php echo $results[$student['student_id']]['result_id']; ?>">
                                                        <button type="submit" onclick="return confirm('Are you sure you want to delete this result?')" class="text-red-600 hover:text-red-900">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                                <?php else: ?>
                                                <!-- No result - show form to add result -->
                                                <form action="result.php" method="POST" class="contents">
                                                    <input type="hidden" name="action" value="add_result">
                                                    <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                                    <input type="hidden" name="subject_id" value="<?php echo $selected_subject_id; ?>">
                                                    <input type="hidden" name="exam_id" value="<?php echo $selected_exam_id; ?>">
                                                    
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <input type="number" name="marks_obtained" step="0.01" min="0" required class="w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <input type="number" name="total_marks" step="0.01" min="0" value="100" required class="w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                            Pending
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <input type="text" name="remarks" class="w-32 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button type="submit" class="text-green-600 hover:text-green-900">
                                                            <i class="fas fa-save mr-1"></i> Save
                                                        </button>
                                                    </td>
                                                </form>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Result Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Result</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="result.php" method="POST">
                <input type="hidden" name="action" value="add_result">
                <input type="hidden" name="student_id" id="edit_student_id">
                <input type="hidden" name="subject_id" value="<?php echo $selected_subject_id; ?>">
                <input type="hidden" name="exam_id" value="<?php echo $selected_exam_id; ?>">
                
                <div class="space-y-4">
                    <div>
                        <label for="edit_marks_obtained" class="block text-sm font-medium text-gray-700 mb-1">Marks Obtained</label>
                        <input type="number" id="edit_marks_obtained" name="marks_obtained" step="0.01" min="0" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <div>
                        <label for="edit_total_marks" class="block text-sm font-medium text-gray-700 mb-1">Total Marks</label>
                        <input type="number" id="edit_total_marks" name="total_marks" step="0.01" min="0" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <div>
                        <label for="edit_remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <input type="text" id="edit_remarks" name="remarks" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Update Result
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Function to edit result
        function editResult(studentId, marksObtained, totalMarks, remarks) {
            document.getElementById('edit_student_id').value = studentId;
            document.getElementById('edit_marks_obtained').value = marksObtained;
            document.getElementById('edit_total_marks').value = totalMarks;
            document.getElementById('edit_remarks').value = remarks;
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        // Function to close modal
        function closeModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        // Function to print results
        function printResults() {
            const printContents = document.getElementById('resultsTable').outerHTML;
            const originalContents = document.body.innerHTML;
            
            document.body.innerHTML = `
                <html>
                <head>
                    <title>Print Results</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                    </style>
                </head>
                <body>
                    <h1>Results Report</h1>
                    <p>Class: ${document.getElementById('class_id').options[document.getElementById('class_id').selectedIndex].text}</p>
                    <p>Subject: ${document.getElementById('subject_id').options[document.getElementById('subject_id').selectedIndex].text}</p>
                    <p>Exam: ${document.getElementById('exam_id').options[document.getElementById('exam_id').selectedIndex].text}</p>
                    ${printContents}
                </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContents;
        }
        
        // Function to export to Excel
        function exportToExcel() {
            const table = document.getElementById('resultsTable');
            const rows = table.querySelectorAll('tr');
            
            let csv = [];
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length - 1; j++) { // Skip the Actions column
                    // Get the text content of the cell
                    let data = cols[j].textContent.trim();
                    // Wrap with quotes if the data contains comma
                    if (data.includes(',')) {
                        data = `"${data}"`;
                    }
                    row.push(data);
                }
                csv.push(row.join(','));
            }
            
            const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'results_export.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

</body>
</html>