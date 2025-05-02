<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_result'])) {
    // Validate inputs
    $student_id = $_POST['student_id'] ?? '';
    $exam_id = $_POST['exam_id'] ?? '';
    $subject_ids = $_POST['subject_id'] ?? [];
    $theory_marks = $_POST['theory_marks'] ?? [];
    $practical_marks = $_POST['practical_marks'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    
    // Basic validation
    if (empty($student_id) || empty($exam_id) || empty($subject_ids)) {
        $error_message = "Please fill all required fields.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Process each subject
            for ($i = 0; $i < count($subject_ids); $i++) {
                if (empty($subject_ids[$i])) continue;
                
                $subject_id = $subject_ids[$i];
                $theory = isset($theory_marks[$i]) && is_numeric($theory_marks[$i]) ? $theory_marks[$i] : 0;
                $practical = isset($practical_marks[$i]) && is_numeric($practical_marks[$i]) ? $practical_marks[$i] : 0;
                $remark = $remarks[$i] ?? '';
                
                // Calculate total and grade
                $total_marks = $theory + $practical;
                
                // Determine grade based on percentage
                $percentage = ($total_marks / 100) * 100; // Assuming total possible marks is 100
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
                $stmt->bind_param("sis", $student_id, $exam_id, $subject_id);
                $stmt->execute();
                $existing_result = $stmt->get_result();
                $stmt->close();
                
                if ($existing_result->num_rows > 0) {
                    // Update existing result
                    $result_row = $existing_result->fetch_assoc();
                    $stmt = $conn->prepare("UPDATE results SET theory_marks = ?, practical_marks = ?, grade = ?, remarks = ?, updated_at = NOW() WHERE result_id = ?");
                    $stmt->bind_param("ddssi", $theory, $practical, $grade, $remark, $result_row['result_id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Insert new result
                    $stmt = $conn->prepare("INSERT INTO results (student_id, exam_id, subject_id, theory_marks, practical_marks, grade, remarks, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    $stmt->bind_param("sisdds", $student_id, $exam_id, $subject_id, $theory, $practical, $grade, $remark);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Update student performance summary
            updateStudentPerformance($student_id, $exam_id, $conn);
            
            $success_message = "Results saved successfully!";
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = "Error saving results: " . $e->getMessage();
        }
    }
}

// Function to update student performance summary
function updateStudentPerformance($student_id, $exam_id, $conn) {
    // Get all results for this student and exam
    $stmt = $conn->prepare("
        SELECT r.*, s.subject_name
        FROM results r
        JOIN subjects s ON r.subject_id = s.subject_id
        WHERE r.student_id = ? AND r.exam_id = ?
    ");
    $stmt->bind_param("si", $student_id, $exam_id);
    $stmt->execute();
    $results = $stmt->get_result();
    $stmt->close();
    
    $total_marks = 0;
    $total_subjects = 0;
    $subjects_passed = 0;
    $total_gpa = 0;
    
    while ($row = $results->fetch_assoc()) {
        $total_marks += ($row['theory_marks'] + $row['practical_marks']);
        $total_gpa += $row['gpa'] ?? 0;
        $total_subjects++;
        
        if (($row['theory_marks'] + $row['practical_marks']) >= 33) {
            $subjects_passed++;
        }
    }
    
    // Calculate average marks and GPA
    $average_marks = $total_subjects > 0 ? $total_marks / $total_subjects : 0;
    $gpa = $total_subjects > 0 ? $total_gpa / $total_subjects : 0;
    
    // Check if performance record exists
    $stmt = $conn->prepare("SELECT performance_id FROM student_performance WHERE student_id = ? AND exam_id = ?");
    $stmt->bind_param("si", $student_id, $exam_id);
    $stmt->execute();
    $performance = $stmt->get_result();
    $stmt->close();
    
    if ($performance->num_rows > 0) {
        // Update existing performance record
        $performance_row = $performance->fetch_assoc();
        $stmt = $conn->prepare("
            UPDATE student_performance 
            SET average_marks = ?, gpa = ?, total_subjects = ?, subjects_passed = ?, updated_at = NOW() 
            WHERE performance_id = ?
        ");
        $stmt->bind_param("ddiii", $average_marks, $gpa, $total_subjects, $subjects_passed, $performance_row['performance_id']);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new performance record
        $stmt = $conn->prepare("
            INSERT INTO student_performance 
            (student_id, exam_id, average_marks, gpa, total_subjects, subjects_passed, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("siddii", $student_id, $exam_id, $average_marks, $gpa, $total_subjects, $subjects_passed);
        $stmt->execute();
        $stmt->close();
    }
    
    // Update ranks (optional, can be complex)
    // This would require ordering all students by average marks or GPA and assigning ranks
}

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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Result Entry | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
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
                            <h1 class="text-2xl font-semibold text-gray-900 my-auto">Manual Result Entry</h1>
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

                        <!-- Manual Entry Form -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Manual Result Entry</h3>
                                <p class="mt-1 text-sm text-gray-500">Enter student results manually for individual subjects.</p>
                            </div>
                            <div class="px-4 py-5 sm:p-6">
                                <form action="manual_entry.php" method="POST" id="resultForm">
                                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                        <!-- Student Selection -->
                                        <div class="sm:col-span-3">
                                            <label for="student_id" class="block text-sm font-medium text-gray-700">Student ID</label>
                                            <div class="mt-1 flex rounded-md shadow-sm">
                                                <input type="text" name="student_id" id="student_id" value="<?php echo isset($_GET['student_id']) ? $_GET['student_id'] : ''; ?>" class="flex-1 focus:ring-blue-500 focus:border-blue-500 block w-full min-w-0 rounded-md sm:text-sm border-gray-300" placeholder="Enter Student ID (e.g., S001)" required>
                                                <button type="button" id="searchStudent" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                    <i class="fas fa-search mr-2"></i> Search
                                                </button>
                                            </div>
                                            <div id="studentInfo" class="mt-2 text-sm text-gray-500 <?php echo $student_data ? '' : 'hidden'; ?>">
                                                <div class="p-3 bg-gray-50 rounded-md">
                                                    <p><span class="font-medium">Name:</span> <span id="studentName"><?php echo $student_data ? $student_data['full_name'] : '-'; ?></span></p>
                                                    <p><span class="font-medium">Class:</span> <span id="studentClass"><?php echo $student_data ? $student_data['class_name'] . ' ' . $student_data['section'] : '-'; ?></span></p>
                                                    <p><span class="font-medium">Roll Number:</span> <span id="studentRoll"><?php echo $student_data ? $student_data['roll_number'] : '-'; ?></span></p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Exam Selection -->
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
                                    </div>

                                    <!-- Subject Marks Section -->
                                    <div class="mt-6">
                                        <h4 class="text-md font-medium text-gray-700 mb-3">Subject Marks</h4>
                                        
                                        <div id="subjectsContainer">
                                            <div class="subject-row grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-12 mb-4 pb-4 border-b border-gray-200">
                                                <div class="sm:col-span-4">
                                                    <label class="block text-sm font-medium text-gray-700">Subject</label>
                                                    <select name="subject_id[]" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                        <option value="">-- Select Subject --</option>
                                                        <?php foreach ($subjects as $subject): ?>
                                                            <option value="<?php echo $subject['subject_id']; ?>">
                                                                <?php echo $subject['subject_name']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-sm font-medium text-gray-700">Theory Marks</label>
                                                    <input type="number" name="theory_marks[]" min="0" max="100" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-sm font-medium text-gray-700">Practical Marks</label>
                                                    <input type="number" name="practical_marks[]" min="0" max="100" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                </div>
                                                <div class="sm:col-span-3">
                                                    <label class="block text-sm font-medium text-gray-700">Remarks</label>
                                                    <input type="text" name="remarks[]" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                </div>
                                                <div class="sm:col-span-1 flex items-end">
                                                    <button type="button" class="remove-subject mt-1 text-red-600 hover:text-red-800">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <button type="button" id="addSubject" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-plus mr-2"></i> Add Subject
                                            </button>
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
                                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
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
                                            <i class="fas fa-eye mr-2"></i> Preview
                                        </button>
                                        <button type="submit" name="save_result" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-save mr-2"></i> Save Results
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
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
        
        // Add event listener to initial remove button
        document.querySelector('.remove-subject').addEventListener('click', function() {
            const container = document.getElementById('subjectsContainer');
            if (container.children.length > 1) {
                this.closest('.subject-row').remove();
            }
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
                    
                    // Calculate grade
                    const percentage = (total / 100) * 100; // Assuming total possible marks is 100
                    let grade = '';
                    
                    if (percentage >= 90) {
                        grade = 'A+';
                    } else if (percentage >= 80) {
                        grade = 'A';
                    } else if (percentage >= 70) {
                        grade = 'B+';
                    } else if (percentage >= 60) {
                        grade = 'B';
                    } else if (percentage >= 50) {
                        grade = 'C+';
                    } else if (percentage >= 40) {
                        grade = 'C';
                    } else if (percentage >= 33) {
                        grade = 'D';
                    } else {
                        grade = 'F';
                    }
                    
                    // Create table row
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${subjectName}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${theory}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${practical}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${total}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${percentage >= 33 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                ${grade}
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
                previewResult.textContent = percentage >= 33 ? 'PASS' : 'FAIL';
                previewResult.className = percentage >= 33 ? 'px-6 py-3 text-left text-sm font-medium text-green-600' : 'px-6 py-3 text-left text-sm font-medium text-red-600';
                
                // Show preview section
                previewSection.classList.remove('hidden');
            } else {
                alert('Please enter marks for at least one subject.');
            }
        });
        
        // Search Student
        document.getElementById('searchStudent').addEventListener('click', function() {
            const studentId = document.getElementById('student_id').value;
            
            if (studentId) {
                // Redirect to the same page with student_id parameter
                window.location.href = 'manual_entry.php?student_id=' + encodeURIComponent(studentId);
            } else {
                alert('Please enter a Student ID');
            }
        });
        
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            sidebar.classList.toggle('hidden');
        });
    </script>
</body>
</html>
