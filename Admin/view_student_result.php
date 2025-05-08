<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if result_id is provided
if (!isset($_GET['result_id']) || empty($_GET['result_id'])) {
    $_SESSION['error'] = "Result ID is required.";
    header("Location: result.php");
    exit();
}

$result_id = intval($_GET['result_id']);

// Process actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'publish') {
        $stmt = $conn->prepare("UPDATE Results SET is_published = 1 WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Result published successfully.";
        header("Location: view_student_result.php?result_id=" . $result_id);
        exit();
    } 
    elseif ($_POST['action'] == 'unpublish') {
        $stmt = $conn->prepare("UPDATE Results SET is_published = 0 WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Result unpublished successfully.";
        header("Location: view_student_result.php?result_id=" . $result_id);
        exit();
    }
    elseif ($_POST['action'] == 'delete') {
        // First delete related records from ResultDetails
        $stmt = $conn->prepare("DELETE FROM ResultDetails WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        
        // Then delete the result
        $stmt = $conn->prepare("DELETE FROM Results WHERE result_id = ?");
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "Result deleted successfully.";
        header("Location: result.php");
        exit();
    }
    elseif ($_POST['action'] == 'update_subject_marks' && isset($_POST['detail_id'])) {
        $detail_id = intval($_POST['detail_id']);
        $marks_obtained = floatval($_POST['marks_obtained']);
        $total_marks = floatval($_POST['total_marks']);
        
        // Calculate percentage
        $percentage = ($marks_obtained / $total_marks) * 100;
        
        // Determine grade
        $grade = '';
        $is_pass = 0;
        
        if ($percentage >= 90) {
            $grade = 'A+';
            $is_pass = 1;
        } elseif ($percentage >= 80) {
            $grade = 'A';
            $is_pass = 1;
        } elseif ($percentage >= 70) {
            $grade = 'B+';
            $is_pass = 1;
        } elseif ($percentage >= 60) {
            $grade = 'B';
            $is_pass = 1;
        } elseif ($percentage >= 50) {
            $grade = 'C+';
            $is_pass = 1;
        } elseif ($percentage >= 40) {
            $grade = 'C';
            $is_pass = 1;
        } elseif ($percentage >= 33) {
            $grade = 'D';
            $is_pass = 1;
        } else {
            $grade = 'F';
            $is_pass = 0;
        }
        
        // Update the subject result
        $stmt = $conn->prepare("UPDATE ResultDetails SET marks_obtained = ?, total_marks = ?, percentage = ?, grade = ?, is_pass = ? WHERE detail_id = ?");
        $stmt->bind_param("dddsii", $marks_obtained, $total_marks, $percentage, $grade, $is_pass, $detail_id);
        $stmt->execute();
        $stmt->close();
        
        // Now update the overall result
        $query = "SELECT SUM(marks_obtained) as total_obtained, SUM(total_marks) as total_marks, 
                         MIN(is_pass) as all_pass
                  FROM ResultDetails 
                  WHERE result_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $total_obtained = $row['total_obtained'];
        $total_marks = $row['total_marks'];
        $all_pass = $row['all_pass'];
        
        $overall_percentage = ($total_obtained / $total_marks) * 100;
        
        // Determine overall grade
        $overall_grade = '';
        if ($overall_percentage >= 90) {
            $overall_grade = 'A+';
        } elseif ($overall_percentage >= 80) {
            $overall_grade = 'A';
        } elseif ($overall_percentage >= 70) {
            $overall_grade = 'B+';
        } elseif ($overall_percentage >= 60) {
            $overall_grade = 'B';
        } elseif ($overall_percentage >= 50) {
            $overall_grade = 'C+';
        } elseif ($overall_percentage >= 40) {
            $overall_grade = 'C';
        } elseif ($overall_percentage >= 33) {
            $overall_grade = 'D';
        } else {
            $overall_grade = 'F';
        }
        
        // Update the main result
        $query = "UPDATE Results 
                  SET total_marks = ?, marks_obtained = ?, percentage = ?, grade = ?, is_pass = ?
                  WHERE result_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("dddsii", $total_marks, $total_obtained, $overall_percentage, $overall_grade, $all_pass, $result_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "Subject marks updated successfully.";
        header("Location: view_student_result.php?result_id=" . $result_id);
        exit();
    }
    elseif ($_POST['action'] == 'delete_subject' && isset($_POST['detail_id'])) {
        $detail_id = intval($_POST['detail_id']);
        
        // Get the subject info before deleting (for the success message)
        $stmt = $conn->prepare("SELECT s.subject_name FROM ResultDetails rd 
                               JOIN Subjects s ON rd.subject_id = s.subject_id 
                               WHERE rd.detail_id = ?");
        $stmt->bind_param("i", $detail_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $subject_info = $result->fetch_assoc();
        $stmt->close();
        
        // Delete the subject result
        $stmt = $conn->prepare("DELETE FROM ResultDetails WHERE detail_id = ?");
        $stmt->bind_param("i", $detail_id);
        $stmt->execute();
        $stmt->close();
        
        // Now update the overall result
        $query = "SELECT SUM(marks_obtained) as total_obtained, SUM(total_marks) as total_marks, 
                         MIN(is_pass) as all_pass
                  FROM ResultDetails 
                  WHERE result_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['total_obtained'] !== null) {
            $total_obtained = $row['total_obtained'];
            $total_marks = $row['total_marks'];
            $all_pass = $row['all_pass'];
            
            $overall_percentage = ($total_obtained / $total_marks) * 100;
            
            // Determine overall grade
            $overall_grade = '';
            if ($overall_percentage >= 90) {
                $overall_grade = 'A+';
            } elseif ($overall_percentage >= 80) {
                $overall_grade = 'A';
            } elseif ($overall_percentage >= 70) {
                $overall_grade = 'B+';
            } elseif ($overall_percentage >= 60) {
                $overall_grade = 'B';
            } elseif ($overall_percentage >= 50) {
                $overall_grade = 'C+';
            } elseif ($overall_percentage >= 40) {
                $overall_grade = 'C';
            } elseif ($overall_percentage >= 33) {
                $overall_grade = 'D';
            } else {
                $overall_grade = 'F';
            }
            
            // Update the main result
            $query = "UPDATE Results 
                      SET total_marks = ?, marks_obtained = ?, percentage = ?, grade = ?, is_pass = ?
                      WHERE result_id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("dddsii", $total_marks, $total_obtained, $overall_percentage, $overall_grade, $all_pass, $result_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $_SESSION['success'] = "Subject '" . $subject_info['subject_name'] . "' has been deleted successfully.";
        header("Location: view_student_result.php?result_id=" . $result_id);
        exit();
    }
}

// Get result information
$result_data = null;
$student_data = null;
$exam_data = null;
$subject_results = [];

try {
    // Get result with student and exam details
    $query = "SELECT r.*, 
                s.roll_number,
                u.full_name, u.email, u.phone, u.address,
                c.class_name, c.section, c.academic_year,
                e.exam_name, e.exam_type, e.exam_date, e.description as exam_description
          FROM Results r
          JOIN Students s ON r.student_id = s.student_id
          JOIN Users u ON s.user_id = u.user_id
          JOIN Classes c ON s.class_id = c.class_id
          JOIN Exams e ON r.exam_id = e.exam_id
          WHERE r.result_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $result_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Result not found.";
        header("Location: result.php");
        exit();
    }
    
    $result_data = $result->fetch_assoc();
    $stmt->close();
    
    // Get subject-wise results
    $query = "SELECT rd.*, s.subject_name, s.subject_code
              FROM ResultDetails rd
              JOIN Subjects s ON rd.subject_id = s.subject_id
              WHERE rd.result_id = ?
              ORDER BY s.subject_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $result_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $subject_results[] = $row;
    }
    
    $stmt->close();
    
    // If no subject results found, check if we need to generate sample data
    if (empty($subject_results)) {
        // Get subjects for this class
        $query = "SELECT s.subject_id, s.subject_name, s.subject_code
                  FROM Subjects s
                  JOIN ClassSubjects cs ON s.subject_id = cs.subject_id
                  JOIN Students st ON cs.class_id = st.class_id
                  WHERE st.student_id = ?
                  LIMIT 5";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $result_data['student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $subjects = [];
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        
        $stmt->close();
        
        // If we have subjects, generate sample result details
        if (!empty($subjects)) {
            foreach ($subjects as $subject) {
                $marks_obtained = rand(60, 95);
                $total_marks = 100;
                $percentage = ($marks_obtained / $total_marks) * 100;
                
                // Determine grade
                $grade = '';
                $is_pass = 0;
                
                if ($percentage >= 90) {
                    $grade = 'A+';
                    $is_pass = 1;
                } elseif ($percentage >= 80) {
                    $grade = 'A';
                    $is_pass = 1;
                } elseif ($percentage >= 70) {
                    $grade = 'B+';
                    $is_pass = 1;
                } elseif ($percentage >= 60) {
                    $grade = 'B';
                    $is_pass = 1;
                } elseif ($percentage >= 50) {
                    $grade = 'C+';
                    $is_pass = 1;
                } elseif ($percentage >= 40) {
                    $grade = 'C';
                    $is_pass = 1;
                } elseif ($percentage >= 33) {
                    $grade = 'D';
                    $is_pass = 1;
                } else {
                    $grade = 'F';
                    $is_pass = 0;
                }
                
                // Insert result detail
                $query = "INSERT INTO ResultDetails (result_id, subject_id, marks_obtained, total_marks, percentage, grade, is_pass)
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iidddsi", $result_id, $subject['subject_id'], $marks_obtained, $total_marks, $percentage, $grade, $is_pass);
                $stmt->execute();
                $stmt->close();
                
                // Add to our array for display
                $subject_results[] = [
                    'subject_id' => $subject['subject_id'],
                    'subject_name' => $subject['subject_name'],
                    'subject_code' => $subject['subject_code'],
                    'marks_obtained' => $marks_obtained,
                    'total_marks' => $total_marks,
                    'percentage' => $percentage,
                    'grade' => $grade,
                    'is_pass' => $is_pass
                ];
            }
            
            // Update the main result with calculated totals
            $total_obtained = 0;
            $total_marks = 0;
            $all_pass = true;
            
            foreach ($subject_results as $sr) {
                $total_obtained += $sr['marks_obtained'];
                $total_marks += $sr['total_marks'];
                if ($sr['is_pass'] == 0) {
                    $all_pass = false;
                }
            }
            
            $overall_percentage = ($total_obtained / $total_marks) * 100;
            
            // Determine overall grade
            $overall_grade = '';
            if ($overall_percentage >= 90) {
                $overall_grade = 'A+';
            } elseif ($overall_percentage >= 80) {
                $overall_grade = 'A';
            } elseif ($overall_percentage >= 70) {
                $overall_grade = 'B+';
            } elseif ($overall_percentage >= 60) {
                $overall_grade = 'B';
            } elseif ($overall_percentage >= 50) {
                $overall_grade = 'C+';
            } elseif ($overall_percentage >= 40) {
                $overall_grade = 'C';
            } elseif ($overall_percentage >= 33) {
                $overall_grade = 'D';
            } else {
                $overall_grade = 'F';
            }
            
            // Update the main result
            $query = "UPDATE Results 
                      SET total_marks = ?, marks_obtained = ?, percentage = ?, grade = ?, is_pass = ?
                      WHERE result_id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("dddsii", $total_marks, $total_obtained, $overall_percentage, $overall_grade, $all_pass, $result_id);
            $stmt->execute();
            $stmt->close();
            
            // Update our result data
            $result_data['total_marks'] = $total_marks;
            $result_data['marks_obtained'] = $total_obtained;
            $result_data['percentage'] = $overall_percentage;
            $result_data['grade'] = $overall_grade;
            $result_data['is_pass'] = $all_pass ? 1 : 0;
        }
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading result: " . $e->getMessage();
    header("Location: result.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student Result | Result Management System</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        /* Hover effects */
        .hover-scale {
            transition: all 0.3s ease;
        }
        
        .hover-scale:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
        
        /* Dark mode for modal */
        .dark-mode .modal-content {
            background-color: #2d3748;
            color: #e2e8f0;
            border-color: #4a5568;
        }
        
        .dark-mode .close {
            color: #e2e8f0;
        }
        
        .dark-mode .close:hover,
        .dark-mode .close:focus {
            color: #fff;
        }

        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background-color: white;
                color: black;
            }
            
            .print-container {
                padding: 20px;
                max-width: 100%;
            }
            
            .print-header {
                text-align: center;
                margin-bottom: 20px;
            }
            
            .print-header h1 {
                font-size: 24px;
                font-weight: bold;
            }
            
            .print-header p {
                font-size: 16px;
            }
            
            .print-section {
                margin-bottom: 20px;
            }
            
            .print-section h2 {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            table, th, td {
                border: 1px solid #ddd;
            }
            
            th, td {
                padding: 8px;
                text-align: left;
            }
            
            th {
                background-color: #f2f2f2;
            }
        }
    </style>
</head>
<body class="bg-gray-100" id="body">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="no-print">
            <?php include 'sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 w-0 overflow-hidden">
            <!-- Top Navigation -->
            <div class="no-print">
                <?php include 'topBar.php'; ?>
            </div>

            <!-- Main Content -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Notification Messages -->
                        <?php if(isset($_SESSION['success'])): ?>
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded no-print">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">
                                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
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

                        <?php if(isset($_SESSION['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded no-print">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">
                                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
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

                        <!-- Action Buttons -->
                        <div class="flex justify-between items-center mb-6 no-print">
                            <a href="result.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Results
                            </a>
                            
                            <div class="flex space-x-2">
                                <?php if ($result_data['is_published']): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="unpublish">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                        <i class="fas fa-eye-slash mr-2"></i> Unpublish
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="publish">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <i class="fas fa-check-circle mr-2"></i> Publish
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this result? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <i class="fas fa-trash mr-2"></i> Delete
                                    </button>
                                </form>
                                
                                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-print mr-2"></i> Print
                                </button>
                            </div>
                        </div>

                        <!-- Result Card -->
                        <div class="bg-white shadow rounded-lg overflow-hidden mb-6 print-container">
                            <!-- School Header for Print -->
                            <div class="print-header hidden print:block">
                                <h1 class="text-2xl font-bold">School Result Management System</h1>
                                <p>Student Result Card</p>
                            </div>
                            
                            <!-- Result Status Banner -->
                            <?php if ($result_data['is_published']): ?>
                            <div class="bg-green-100 text-green-800 px-4 py-2 text-center">
                                <span class="font-semibold">Published Result</span>
                            </div>
                            <?php else: ?>
                            <div class="bg-yellow-100 text-yellow-800 px-4 py-2 text-center">
                                <span class="font-semibold">Unpublished Result</span>
                            </div>
                            <?php endif; ?>



    <!-- Student Information Card -->
    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
        <h2 class="text-2xl font-bold text-indigo-600 mb-4 border-b pb-2">🎓 Student Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-gray-500">Full Name</p>
                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['full_name']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Roll Number</p>
                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['roll_number']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Class</p>
                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['class_name'] . ' ' . $result_data['section']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Academic Year</p>
                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['academic_year']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Email</p>
                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['email'] ?? 'N/A'); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Phone</p>
                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['phone'] ?? 'N/A'); ?></p>
            </div>
        </div>
    </div>

    <!-- Exam Information Card -->
    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
        <h2 class="text-2xl font-bold text-indigo-600 mb-4 border-b pb-2">📝 Exam Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-gray-500">Exam Name</p>
                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['exam_name']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Exam Type</p>
                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['exam_type']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Exam Date</p>
                <p class="text-lg font-semibold text-gray-900"><?php echo date('d M Y', strtotime($result_data['exam_date'])); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Result Date</p>
                <p class="text-lg font-semibold text-gray-900"><?php echo date('d M Y', strtotime($result_data['created_at'])); ?></p>
            </div>
        </div>
    </div>

</div>

                            
                            <!-- Subject-wise Results -->
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">Subject-wise Results</h2>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider no-print">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($subject_results)): ?>
                                            <tr>
                                                <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No subject results found</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($subject_results as $subject): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($subject['marks_obtained'] . '/' . $subject['total_marks']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars(number_format($subject['percentage'], 2)); ?>%</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $grade_class = '';
                                                        switch ($subject['grade']) {
                                                            case 'A+': $grade_class = 'bg-green-100 text-green-800'; break;
                                                            case 'A': $grade_class = 'bg-green-100 text-green-800'; break;
                                                            case 'B+': $grade_class = 'bg-blue-100 text-blue-800'; break;
                                                            case 'B': $grade_class = 'bg-blue-100 text-blue-800'; break;
                                                            case 'C+': $grade_class = 'bg-yellow-100 text-yellow-800'; break;
                                                            case 'C': $grade_class = 'bg-yellow-100 text-yellow-800'; break;
                                                            case 'D': $grade_class = 'bg-orange-100 text-orange-800'; break;
                                                            case 'F': $grade_class = 'bg-red-100 text-red-800'; break;
                                                            default: $grade_class = 'bg-gray-100 text-gray-800';
                                                        }
                                                        ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $grade_class; ?>">
                                                            <?php echo htmlspecialchars($subject['grade']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($subject['is_pass']): ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            Pass
                                                        </span>
                                                        <?php else: ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                            Fail
                                                        </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium no-print">
                                                        <div class="flex space-x-3">
                                                            <button type="button" onclick="openEditSubjectMarksModal(<?php echo $subject['detail_id']; ?>, '<?php echo $subject['subject_name']; ?>', <?php echo $subject['marks_obtained']; ?>, <?php echo $subject['total_marks']; ?>)" class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            
                                                            <form method="POST" class="inline" onsubmit="return confirmDeleteSubject('<?php echo htmlspecialchars($subject['subject_name']); ?>');">
                                                                <input type="hidden" name="action" value="delete_subject">
                                                                <input type="hidden" name="detail_id" value="<?php echo $subject['detail_id']; ?>">
                                                                <button type="submit" class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Overall Result -->
                            <div class="p-6">
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">Overall Result</h2>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-sm text-gray-500">Total Marks</p>
                                        <p class="text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars($result_data['marks_obtained'] . '/' . $result_data['total_marks']); ?></p>
                                    </div>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-sm text-gray-500">Percentage</p>
                                        <p class="text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars(number_format($result_data['percentage'], 2)); ?>%</p>
                                    </div>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-sm text-gray-500">Grade</p>
                                        <?php
                                        $grade_class = '';
                                        switch ($result_data['grade']) {
                                            case 'A+': $grade_class = 'text-green-600'; break;
                                            case 'A': $grade_class = 'text-green-600'; break;
                                            case 'B+': $grade_class = 'text-blue-600'; break;
                                            case 'B': $grade_class = 'text-blue-600'; break;
                                            case 'C+': $grade_class = 'text-yellow-600'; break;
                                            case 'C': $grade_class = 'text-yellow-600'; break;
                                            case 'D': $grade_class = 'text-orange-600'; break;
                                            case 'F': $grade_class = 'text-red-600'; break;
                                            default: $grade_class = 'text-gray-600';
                                        }
                                        ?>
                                        <p class="text-2xl font-semibold <?php echo $grade_class; ?>"><?php echo htmlspecialchars($result_data['grade']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-sm text-gray-500">Result Status</p>
                                        <?php if ($result_data['is_pass']): ?>
                                        <p class="text-xl font-semibold text-green-600">PASS</p>
                                        <?php else: ?>
                                        <p class="text-xl font-semibold text-red-600">FAIL</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($result_data['remarks'])): ?>
                                <div class="mt-6">
                                    <p class="text-sm text-gray-500">Remarks</p>
                                    <p class="text-lg text-gray-900"><?php echo htmlspecialchars($result_data['remarks']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Signature Section (visible only in print) -->
                                <div class="hidden print:block mt-12">
                                    <div class="grid grid-cols-3 gap-4">
                                        <div class="text-center">
                                            <div class="border-t border-gray-300 pt-2">
                                                <p>Class Teacher</p>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="border-t border-gray-300 pt-2">
                                                <p>Examination Controller</p>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="border-t border-gray-300 pt-2">
                                                <p>Principal</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Subject Marks Modal -->
    <div id="editSubjectMarksModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditSubjectMarksModal()">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Update Subject Marks</h2>
            <form id="editSubjectMarksForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_subject_marks">
                <input type="hidden" id="edit_detail_id" name="detail_id" value="">
                
                <div>
                    <label for="subject_name" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" id="subject_name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" readonly>
                </div>
                
                <div>
                    <label for="subject_marks_obtained" class="block text-sm font-medium text-gray-700 mb-1">Marks Obtained</label>
                    <input type="number" id="subject_marks_obtained" name="marks_obtained" step="0.01" min="0" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                </div>
                
                <div>
                    <label for="subject_total_marks" class="block text-sm font-medium text-gray-700 mb-1">Total Marks</label>
                    <input type="number" id="subject_total_marks" name="total_marks" step="0.01" min="0.01" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="closeEditSubjectMarksModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openEditSubjectMarksModal(detailId, subjectName, marksObtained, totalMarks) {
            document.getElementById('edit_detail_id').value = detailId;
            document.getElementById('subject_name').value = subjectName;
            document.getElementById('subject_marks_obtained').value = marksObtained;
            document.getElementById('subject_total_marks').value = totalMarks;
            document.getElementById('editSubjectMarksModal').style.display = 'block';
        }
        
        function closeEditSubjectMarksModal() {
            document.getElementById('editSubjectMarksModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('editSubjectMarksModal');
            if (event.target == modal) {
                closeEditSubjectMarksModal();
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Dark mode toggle
            const darkModeToggle = document.getElementById('dark-mode-toggle');
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function() {
                    document.getElementById('body').classList.toggle('dark-mode');
                });
            }
        });

        function confirmDeleteSubject(subjectName) {
            return Swal.fire({
                title: 'Delete Subject Result',
                html: `Are you sure you want to delete the result for <strong>${subjectName}</strong>?<br><br>This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                return result.isConfirmed;
            });
        }
    </script>
</body>
</html>
