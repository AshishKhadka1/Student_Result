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

// Get classes for dropdown
$classes = [];
$result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name, section");
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

// Get exams for dropdown
$exams = [];
$result = $conn->query("SELECT exam_id, exam_name, exam_type, class_id FROM exams ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}

// Get subjects for dropdown
$subjects = [];
$result = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

// Process form submission
$success_message = '';
$error_message = '';
$validation_errors = [];
$import_summary = [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
    'updated' => 0
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_results'])) {
    $exam_id = $_POST['exam_id'] ?? '';
    $class_id = $_POST['class_id'] ?? '';
    
    // Validate required fields
    if (empty($exam_id)) {
        $error_message = "Please select an exam.";
    } elseif (empty($class_id)) {
        $error_message = "Please select a class.";
    } elseif (!isset($_FILES['result_file']) || $_FILES['result_file']['error'] != 0) {
        $error_message = "Please upload a valid CSV file.";
    } else {
        // Process the uploaded file
        $file = $_FILES['result_file']['tmp_name'];
        $file_type = pathinfo($_FILES['result_file']['name'], PATHINFO_EXTENSION);
        
        if ($file_type != 'csv') {
            $error_message = "Only CSV files are allowed.";
        } else {
            // Open the file
            if (($handle = fopen($file, "r")) !== FALSE) {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Skip header row
                    $header = fgetcsv($handle, 1000, ",");
                    
                    // Validate header structure
                    $expected_headers = ['student_id', 'subject_id', 'theory_marks', 'practical_marks', 'remarks'];
                    $header_valid = true;
                    
                    foreach ($expected_headers as $expected) {
                        if (!in_array($expected, $header)) {
                            $header_valid = false;
                            break;
                        }
                    }
                    
                    if (!$header_valid) {
                        throw new Exception("CSV file format is invalid. Please use the correct template.");
                    }
                    
                    // Process each row
                    $row_number = 1;
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $row_number++;
                        $import_summary['total']++;
                        
                        // Map CSV columns to variables
                        $student_id = $data[array_search('student_id', $header)];
                        $subject_id = $data[array_search('subject_id', $header)];
                        $theory_marks = $data[array_search('theory_marks', $header)];
                        $practical_marks = $data[array_search('practical_marks', $header)];
                        $remarks = $data[array_search('remarks', $header)];
                        
                        // Validate data
                        $row_valid = true;
                        $row_errors = [];
                        
                        // Check if student exists and belongs to the selected class
                        $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ? AND class_id = ?");
                        $stmt->bind_param("si", $student_id, $class_id);
                        $stmt->execute();
                        $student_result = $stmt->get_result();
                        $stmt->close();
                        
                        if ($student_result->num_rows == 0) {
                            $row_valid = false;
                            $row_errors[] = "Student ID $student_id does not exist or does not belong to the selected class.";
                        }
                        
                        // Check if subject exists
                        $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_id = ?");
                        $stmt->bind_param("i", $subject_id);
                        $stmt->execute();
                        $subject_result = $stmt->get_result();
                        $stmt->close();
                        
                        if ($subject_result->num_rows == 0) {
                            $row_valid = false;
                            $row_errors[] = "Subject ID $subject_id does not exist.";
                        }
                        
                        // Validate marks
                        if (!is_numeric($theory_marks) || $theory_marks < 0 || $theory_marks > 100) {
                            $row_valid = false;
                            $row_errors[] = "Theory marks must be between 0 and 100.";
                        }
                        
                        if (!is_numeric($practical_marks) || $practical_marks < 0 || $practical_marks > 100) {
                            $row_valid = false;
                            $row_errors[] = "Practical marks must be between 0 and 100.";
                        }
                        
                        if (!$row_valid) {
                            $validation_errors[] = [
                                'row' => $row_number,
                                'errors' => $row_errors
                            ];
                            $import_summary['failed']++;
                            continue;
                        }
                        
                        // Calculate total and grade
                        $total_marks = $theory_marks + $practical_marks;
                        $percentage = ($total_marks / 100) * 100; // Assuming total possible marks is 100
                        
                        // Determine grade based on percentage
                        $grade = '';
                        if ($percentage >= 90) {
                            $grade = 'A+';
                        } elseif ($percentage >= 80) {
                            $grade = 'A';
                        } elseif ($percentage >= 70) {
                            $grade = 'B+';
                        } elseif ($percentage >= 60) {
                            $grade = 'B';
                        } elseif ($percentage >= 50) {
                            $grade = 'C+';
                        } elseif ($percentage >= 40) {
                            $grade = 'C';
                        } elseif ($percentage >= 33) {
                            $grade = 'D';
                        } else {
                            $grade = 'F';
                        }
                        
                        // Check if result already exists
                        $stmt = $conn->prepare("SELECT result_id FROM results WHERE student_id = ? AND exam_id = ? AND subject_id = ?");
                        $stmt->bind_param("sii", $student_id, $exam_id, $subject_id);
                        $stmt->execute();
                        $existing_result = $stmt->get_result();
                        $stmt->close();
                        
                        if ($existing_result->num_rows > 0) {
                            // Update existing result
                            $result_row = $existing_result->fetch_assoc();
                            $stmt = $conn->prepare("UPDATE results SET theory_marks = ?, practical_marks = ?, grade = ?, remarks = ?, updated_at = NOW() WHERE result_id = ?");
                            $stmt->bind_param("ddssi", $theory_marks, $practical_marks, $grade, $remarks, $result_row['result_id']);
                            $stmt->execute();
                            $stmt->close();
                            $import_summary['updated']++;
                        } else {
                            // Insert new result
                            $stmt = $conn->prepare("INSERT INTO results (student_id, exam_id, subject_id, theory_marks, practical_marks, grade, remarks, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                            $stmt->bind_param("siiddss", $student_id, $exam_id, $subject_id, $theory_marks, $practical_marks, $grade, $remarks);
                            $stmt->execute();
                            $stmt->close();
                            $import_summary['success']++;
                        }
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Update student_performance table if it exists
                    $table_exists = $conn->query("SHOW TABLES LIKE 'student_performance'");
                    if ($table_exists->num_rows > 0) {
                        // Calculate and update performance metrics for each student
                        $stmt = $conn->prepare("
                            SELECT 
                                r.student_id,
                                AVG(r.theory_marks + r.practical_marks) as average_marks,
                                COUNT(r.subject_id) as total_subjects,
                                SUM(CASE WHEN r.grade != 'F' THEN 1 ELSE 0 END) as subjects_passed
                            FROM 
                                results r
                            WHERE 
                                r.exam_id = ?
                            GROUP BY 
                                r.student_id
                        ");
                        $stmt->bind_param("i", $exam_id);
                        $stmt->execute();
                        $performance_results = $stmt->get_result();
                        $stmt->close();
                        
                        while ($perf = $performance_results->fetch_assoc()) {
                            $student_id = $perf['student_id'];
                            $avg_marks = $perf['average_marks'];
                            $total_subjects = $perf['total_subjects'];
                            $subjects_passed = $perf['subjects_passed'];
                            
                            // Calculate GPA based on average marks
                            $gpa = 0;
                            if ($avg_marks >= 90) {
                                $gpa = 4.0;
                            } elseif ($avg_marks >= 80) {
                                $gpa = 3.7;
                            } elseif ($avg_marks >= 70) {
                                $gpa = 3.3;
                            } elseif ($avg_marks >= 60) {
                                $gpa = 3.0;
                            } elseif ($avg_marks >= 50) {
                                $gpa = 2.7;
                            } elseif ($avg_marks >= 40) {
                                $gpa = 2.3;
                            } elseif ($avg_marks >= 33) {
                                $gpa = 2.0;
                            }
                            
                            // Calculate rank (simplified - just count students with higher average)
                            $stmt = $conn->prepare("
                                SELECT COUNT(*) + 1 as rank
                                FROM (
                                    SELECT 
                                        student_id, 
                                        AVG(theory_marks + practical_marks) as avg_marks
                                    FROM 
                                        results
                                    WHERE 
                                        exam_id = ?
                                    GROUP BY 
                                        student_id
                                    HAVING 
                                        AVG(theory_marks + practical_marks) > ?
                                ) as higher_ranks
                            ");
                            $stmt->bind_param("id", $exam_id, $avg_marks);
                            $stmt->execute();
                            $rank_result = $stmt->get_result();
                            $rank = $rank_result->fetch_assoc()['rank'];
                            $stmt->close();
                            
                            // Generate remarks
                            $remarks = '';
                            if ($gpa >= 3.5) {
                                $remarks = 'Excellent performance';
                            } elseif ($gpa >= 3.0) {
                                $remarks = 'Very good performance';
                            } elseif ($gpa >= 2.5) {
                                $remarks = 'Good performance';
                            } elseif ($gpa >= 2.0) {
                                $remarks = 'Satisfactory performance';
                            } else {
                                $remarks = 'Needs improvement';
                            }
                            
                            // Check if performance record exists
                            $stmt = $conn->prepare("SELECT performance_id FROM student_performance WHERE student_id = ? AND exam_id = ?");
                            $stmt->bind_param("si", $student_id, $exam_id);
                            $stmt->execute();
                            $existing_performance = $stmt->get_result();
                            $stmt->close();
                            
                            if ($existing_performance->num_rows > 0) {
                                // Update existing performance record
                                $perf_row = $existing_performance->fetch_assoc();
                                $stmt = $conn->prepare("
                                    UPDATE student_performance 
                                    SET average_marks = ?, gpa = ?, total_subjects = ?, subjects_passed = ?, 
                                        rank = ?, remarks = ?, updated_at = NOW() 
                                    WHERE performance_id = ?
                                ");
                                $stmt->bind_param("ddiiisi", $avg_marks, $gpa, $total_subjects, $subjects_passed, $rank, $remarks, $perf_row['performance_id']);
                                $stmt->execute();
                                $stmt->close();
                            } else {
                                // Insert new performance record
                                $stmt = $conn->prepare("
                                    INSERT INTO student_performance 
                                    (student_id, exam_id, average_marks, gpa, total_subjects, subjects_passed, 
                                    rank, remarks, created_at, updated_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                                ");
                                $stmt->bind_param("siddiiis", $student_id, $exam_id, $avg_marks, $gpa, $total_subjects, $subjects_passed, $rank, $remarks);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                    }
                    
                    $success_message = "Results imported successfully!";
                    
                } catch (Exception $e) {
                    // Rollback on error
                    $conn->rollback();
                    $error_message = "Error importing results: " . $e->getMessage();
                }
                
                fclose($handle);
            } else {
                $error_message = "Could not open the file.";
            }
        }
    }
}

// Generate sample CSV template
$sample_csv = "student_id,subject_id,theory_marks,practical_marks,remarks\n";
$sample_csv .= "S001,1,75,20,Good performance\n";
$sample_csv .= "S001,2,80,15,Excellent work\n";
$sample_csv .= "S002,1,65,18,Needs improvement in theory\n";
$sample_csv .= "S002,2,70,20,Good practical skills\n";

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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

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
                <button class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex">
                        <div class="w-full flex md:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Bulk Upload</h1>
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

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Success/Error Messages -->
                        <?php if (!empty($success_message)): ?>
                            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700"><?php echo $success_message; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700"><?php echo $error_message; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Import Summary -->
                        <?php if ($import_summary['total'] > 0): ?>
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700 font-medium">Import Summary</p>
                                        <ul class="mt-2 text-sm text-blue-700">
                                            <li>Total records: <?php echo $import_summary['total']; ?></li>
                                            <li>Successfully imported: <?php echo $import_summary['success']; ?></li>
                                            <li>Updated: <?php echo $import_summary['updated']; ?></li>
                                            <li>Failed: <?php echo $import_summary['failed']; ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Validation Errors -->
                        <?php if (!empty($validation_errors)): ?>
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700 font-medium">Validation Errors</p>
                                        <div class="mt-2 max-h-60 overflow-y-auto">
                                            <?php foreach ($validation_errors as $error): ?>
                                                <div class="mb-2 p-2 bg-yellow-100 rounded">
                                                    <p class="text-sm font-medium text-yellow-800">Row <?php echo $error['row']; ?>:</p>
                                                    <ul class="mt-1 text-xs text-yellow-800 list-disc list-inside">
                                                        <?php foreach ($error['errors'] as $err): ?>
                                                            <li><?php echo $err; ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Upload Form -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Bulk Upload Results</h3>
                                <p class="mt-1 text-sm text-gray-500">Upload a CSV file with student results.</p>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <form action="bulk_upload.php" method="POST" enctype="multipart/form-data">
                                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                        <div class="sm:col-span-3">
                                            <label for="class_id" class="block text-sm font-medium text-gray-700">Select Class</label>
                                            <select id="class_id" name="class_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                <option value="">-- Select Class --</option>
                                                <?php foreach ($classes as $class): ?>
                                                    <option value="<?php echo $class['class_id']; ?>">
                                                        <?php echo $class['class_name'] . ' ' . $class['section']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="sm:col-span-3">
                                            <label for="exam_id" class="block text-sm font-medium text-gray-700">Select Exam</label>
                                            <select id="exam_id" name="exam_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                <option value="">-- Select Exam --</option>
                                                <?php foreach ($exams as $exam): ?>
                                                    <option value="<?php echo $exam['exam_id']; ?>" data-class="<?php echo $exam['class_id']; ?>">
                                                        <?php echo $exam['exam_name'] . ' (' . ucfirst($exam['exam_type']) . ')'; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="sm:col-span-6">
                                            <label for="result_file" class="block text-sm font-medium text-gray-700">Upload CSV File</label>
                                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                                <div class="space-y-1 text-center">
                                                    <i class="fas fa-file-csv text-gray-400 text-3xl mb-2"></i>
                                                    <div class="flex text-sm text-gray-600">
                                                        <label for="result_file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                            <span>Upload a file</span>
                                                            <input id="result_file" name="result_file" type="file" class="sr-only" accept=".csv">
                                                        </label>
                                                        <p class="pl-1">or drag and drop</p>
                                                    </div>
                                                    <p class="text-xs text-gray-500">CSV file up to 10MB</p>
                                                </div>
                                            </div>
                                            <div id="file-name" class="mt-2 text-sm text-gray-500"></div>
                                        </div>
                                    </div>

                                    <div class="mt-6 flex justify-end space-x-3">
                                        <a href="#" id="download-template" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-download mr-2"></i> Download Template
                                        </a>
                                        <button type="submit" name="upload_results" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-upload mr-2"></i> Upload Results
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Instructions -->
                        <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Instructions</h3>
                                <p class="mt-1 text-sm text-gray-500">How to prepare and upload your CSV file.</p>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="text-sm text-gray-700">
                                    <h4 class="font-medium mb-2">CSV File Format</h4>
                                    <p class="mb-2">Your CSV file should have the following columns:</p>
                                    <ul class="list-disc list-inside mb-4">
                                        <li><strong>student_id</strong> - The unique ID of the student (e.g., S001)</li>
                                        <li><strong>subject_id</strong> - The ID of the subject</li>
                                        <li><strong>theory_marks</strong> - Theory marks (0-100)</li>
                                        <li><strong>practical_marks</strong> - Practical marks (0-100)</li>
                                        <li><strong>remarks</strong> - Any remarks or comments (optional)</li>
                                    </ul>
                                    
                                    <h4 class="font-medium mb-2">Example</h4>
                                    <div class="bg-gray-100 p-3 rounded-md overflow-x-auto mb-4">
                                        <pre class="text-xs">student_id,subject_id,theory_marks,practical_marks,remarks
S001,1,75,20,Good performance
S001,2,80,15,Excellent work
S002,1,65,18,Needs improvement in theory
S002,2,70,20,Good practical skills</pre>
                                    </div>
                                    
                                    <h4 class="font-medium mb-2">Important Notes</h4>
                                    <ul class="list-disc list-inside mb-4">
                                        <li>The first row must contain the column headers as shown above.</li>
                                        <li>Make sure the student_id exists and belongs to the selected class.</li>
                                        <li>Make sure the subject_id exists in the system.</li>
                                        <li>Theory and practical marks must be between 0 and 100.</li>
                                        <li>The system will automatically calculate the grade based on the total marks.</li>
                                        <li>If a result already exists for a student, subject, and exam combination, it will be updated.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Show selected file name
        document.getElementById('result_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file selected';
            document.getElementById('file-name').textContent = 'Selected file: ' + fileName;
        });
        
        // Download CSV template
        document.getElementById('download-template').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Create CSV content
            const csvContent = `student_id,subject_id,theory_marks,practical_marks,remarks
S001,1,75,20,Good performance
S001,2,80,15,Excellent work
S002,1,65,18,Needs improvement in theory
S002,2,70,20,Good practical skills`;
            
            // Create a blob and download link
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('href', url);
            a.setAttribute('download', 'result_template.csv');
            a.click();
            window.URL.revokeObjectURL(url);
        });
        
        // Filter exams by class
        document.getElementById('class_id').addEventListener('change', function() {
            const classId = this.value;
            const examSelect = document.getElementById('exam_id');
            const examOptions = examSelect.querySelectorAll('option');
            
            // Reset exam selection
            examSelect.selectedIndex = 0;
            
            // Show/hide exam options based on class
            examOptions.forEach(option => {
                if (option.value === '') return; // Skip the placeholder option
                
                const examClassId = option.getAttribute('data-class');
                if (classId === '' || examClassId === classId) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        });
        
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            sidebar.classList.toggle('hidden');
        });
    </script>
</body>
</html>