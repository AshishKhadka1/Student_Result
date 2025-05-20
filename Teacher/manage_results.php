<?php
session_start();
include '../includes/config.php';
include '../includes/db_connetc.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get teacher information
$stmt = $conn->prepare("SELECT t.teacher_id FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.user_id = ?");
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $teacher = $result->fetch_assoc();
    $teacher_id = $teacher['teacher_id'];
} else {
    die("Teacher not found");
}
$stmt->close();

// Get parameters from URL
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Check if teacher is assigned to this subject and class
if ($subject_id && $class_id) {
    $permission_check = $conn->prepare("
        SELECT COUNT(*) as is_assigned 
        FROM teachersubjects 
        WHERE teacher_id = ? AND subject_id = ? AND class_id = ? AND is_active = 1
    ");
    
    if (!$permission_check) {
        die("Error preparing permission check: " . $conn->error);
    }
    
    $permission_check->bind_param("iii", $teacher_id, $subject_id, $class_id);
    $permission_check->execute();
    $permission_result = $permission_check->get_result();
    $is_assigned = $permission_result->fetch_assoc()['is_assigned'];
    $permission_check->close();
    
    if (!$is_assigned) {
        $_SESSION['error'] = "You are not assigned to the selected class or subject.";
        header("Location: manage_results.php");
        exit();
    }
}

// Process batch update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    $student_ids = $_POST['student_id'];
    $marks = $_POST['marks'];
    $remarks = $_POST['remarks'];
    $exam_id = $_POST['exam_id'];
    $subject_id = $_POST['subject_id'];
    
    $success_count = 0;
    $error_count = 0;
    
    // Get max marks for this exam
    $stmt = $conn->prepare("SELECT total_marks FROM exams WHERE exam_id = ?");
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $max_marks = 100; // Default
    if ($result->num_rows > 0) {
        $exam_data = $result->fetch_assoc();
        $max_marks = $exam_data['total_marks'];
    }
    $stmt->close();
    
    foreach ($student_ids as $index => $student_id) {
        $mark = floatval($marks[$index]);
        $remark = $remarks[$index];
        
        // Validate marks
        if ($mark < 0 || $mark > $max_marks) {
            $error_count++;
            continue;
        }
        
        // Calculate grade based on percentage
        $percentage = ($mark / $max_marks) * 100;
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
        $stmt = $conn->prepare("
            SELECT result_id FROM results 
            WHERE student_id = ? AND subject_id = ? AND exam_id = ?
        ");
        if (!$stmt) {
            die("Error preparing statement: " . $conn->error);
        }
        $stmt->bind_param("iii", $student_id, $subject_id, $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing result
            $result_id = $result->fetch_assoc()['result_id'];
            $stmt = $conn->prepare("
                UPDATE results 
                SET marks = ?, grade = ?, remarks = ?, updated_by = ?, updated_at = NOW()
                WHERE result_id = ?
            ");
            if (!$stmt) {
                die("Error preparing statement: " . $conn->error);
            }
            $stmt->bind_param("dssii", $mark, $grade, $remark, $teacher_id, $result_id);
        } else {
            // Insert new result
            $stmt = $conn->prepare("
                INSERT INTO results (student_id, subject_id, exam_id, marks, grade, remarks, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                die("Error preparing statement: " . $conn->error);
            }
            $stmt->bind_param("iiidssi", $student_id, $subject_id, $exam_id, $mark, $grade, $remark, $teacher_id);
        }
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }
        $stmt->close();
    }
    
    if ($success_count > 0) {
        $_SESSION['success'] = "$success_count results saved successfully.";
    }
    
    if ($error_count > 0) {
        $_SESSION['error'] = "$error_count results failed to save.";
    }
    
    // Redirect to refresh the page
    header("Location: manage_results.php?subject_id=$subject_id&class_id=$class_id&exam_id=$exam_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Results - Teacher Dashboard</title>
    <link rel="stylesheet" href="../css/tailwind.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'includes/teacher_topbar.php'; ?>
    
    <div class="flex">
        <?php include 'includes/teacher_sidebar.php'; ?>
        
        <div class="w-full p-4 md:ml-64">
            <?php
            ?>
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Manage Results</h1>
                <p class="text-gray-600 bg-blue-50 p-3 rounded-lg border-l-4 border-blue-500 shadow-sm">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                    Manage and record results for subjects you are teaching
                </p>
            </div>
            
            <!-- Display Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$subject_id): ?>
                <!-- Subject Selection -->
                <div class="bg-white rounded-lg shadow-md mb-6">
                    <div class="border-b px-6 py-4">
                        <h2 class="text-xl font-semibold text-gray-800">Your Teaching Subjects</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php
                            // Check if teachersubjects table exists
                            $table_check = $conn->query("SHOW TABLES LIKE 'teachersubjects'");
                            if ($table_check->num_rows > 0) {
                                // Get subjects assigned to this teacher
                                $query = "
                                    SELECT ts.*, s.subject_name, s.subject_code, c.class_name, c.section 
                                    FROM teachersubjects ts 
                                    JOIN subjects s ON ts.subject_id = s.subject_id 
                                    JOIN classes c ON ts.class_id = c.class_id 
                                    WHERE ts.teacher_id = ? AND ts.is_active = 1
                                    ORDER BY c.class_name, c.section, s.subject_name
                                ";
                                
                                $stmt = $conn->prepare($query);
                                if (!$stmt) {
                                    echo '<div class="col-span-full text-center py-4 text-gray-500">Error preparing statement: ' . $conn->error . '</div>';
                                } else {
                                    $stmt->bind_param("i", $teacher_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    
                                    if ($result->num_rows > 0) {
                                        while ($subject = $result->fetch_assoc()) {
                                            echo '<div class="bg-white border rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300 transform hover:-translate-y-1 hover:scale-102 transition-transform">';
                                            echo '<div class="p-5 border-b bg-gradient-to-r from-blue-600 to-indigo-700 rounded-t-lg">';
                                            echo '<h3 class="text-lg font-semibold text-white">' . htmlspecialchars($subject['subject_name']) . '</h3>';
                                            echo '<p class="text-blue-100 text-sm">Code: ' . htmlspecialchars($subject['subject_code']) . '</p>';
                                            echo '</div>';
                                            echo '<div class="p-5">';
                                            echo '<p class="text-gray-700 flex items-center"><i class="fas fa-chalkboard-teacher mr-2 text-indigo-500"></i> Class: ' . htmlspecialchars($subject['class_name']) . ' - Section ' . htmlspecialchars($subject['section']) . '</p>';
                                            echo '<div class="mt-4 flex justify-end">';
                                            echo '<a href="manage_results.php?subject_id=' . $subject['subject_id'] . '&class_id=' . $subject['class_id'] . '" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:border-indigo-800 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">';
                                            echo '<i class="fas fa-edit mr-2"></i> Manage Results</a>';
                                            echo '</div>';
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<div class="col-span-full text-center py-4 text-gray-500">No subjects assigned to you yet. Please contact the administrator.</div>';
                                    }
                                    $stmt->close();
                                }
                            } else {
                                // If teachersubjects table doesn't exist, try to get subjects from teachers table
                                echo '<div class="col-span-full text-center py-4 text-gray-500">Teacher subjects table not found. Please contact the administrator.</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php elseif (!$exam_id): ?>
                <!-- Get subject and class details -->
                <?php
                $subject_name = '';
                $class_name = '';
                $section = '';
                
                $stmt = $conn->prepare("SELECT subject_name FROM subjects WHERE subject_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $subject_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $subject_name = $result->fetch_assoc()['subject_name'];
                    }
                    $stmt->close();
                }
                
                $stmt = $conn->prepare("SELECT class_name, section FROM classes WHERE class_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $class_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $class_data = $result->fetch_assoc();
                        $class_name = $class_data['class_name'];
                        $section = $class_data['section'];
                    }
                    $stmt->close();
                }
                ?>
                
                <!-- Exam Selection -->
                <div class="bg-white rounded-lg shadow-md mb-6">
                    <div class="border-b px-6 py-4 flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800">Select Exam for <?php echo htmlspecialchars($subject_name); ?></h2>
                        <span class="text-sm text-gray-600">Class: <?php echo htmlspecialchars($class_name . ' - Section ' . $section); ?></span>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead>
                                    <tr>
                                        <th class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam Name</th>
                                        <th class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Marks</th>
                                        <th class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get exams for this class
                                    $stmt = $conn->prepare("
                                        SELECT exam_id, exam_name, total_marks, exam_date 
                                        FROM exams 
                                        WHERE class_id = ? 
                                        ORDER BY exam_date DESC
                                    ");
                                    
                                    if (!$stmt) {
                                        echo '<tr><td colspan="4" class="py-4 px-4 border-b border-gray-200 text-center text-red-500">Error preparing statement: ' . $conn->error . '</td></tr>';
                                    } else {
                                        $stmt->bind_param("i", $class_id);
                                        $stmt->execute();
                                        $exams_result = $stmt->get_result();
                                        
                                        if ($exams_result->num_rows > 0) {
                                            while ($exam = $exams_result->fetch_assoc()) {
                                                echo '<tr class="hover:bg-indigo-50 transition-colors duration-150">';
                                                echo '<td class="py-4 px-4 border-b border-gray-200 font-medium">' . htmlspecialchars($exam['exam_name']) . '</td>';
                                                echo '<td class="py-4 px-4 border-b border-gray-200">' . date('d M Y', strtotime($exam['exam_date'])) . '</td>';
                                                echo '<td class="py-4 px-4 border-b border-gray-200">' . htmlspecialchars($exam['total_marks']) . '</td>';
                                                echo '<td class="py-4 px-4 border-b border-gray-200">';
                                                echo '<a href="manage_results.php?subject_id=' . $subject_id . '&class_id=' . $class_id . '&exam_id=' . $exam['exam_id'] . '" class="text-indigo-600 hover:text-indigo-800 mr-3 inline-flex items-center">';
                                                echo '<i class="fas fa-edit mr-1"></i> Manage Results</a>';
                                                
                                                // Check if results exist for this exam and subject
                                                $check_stmt = $conn->prepare("
                                                    SELECT COUNT(*) as count FROM results 
                                                    WHERE subject_id = ? AND exam_id = ?
                                                ");
                                                if ($check_stmt) {
                                                    $check_stmt->bind_param("ii", $subject_id, $exam['exam_id']);
                                                    $check_stmt->execute();
                                                    $check_result = $check_stmt->get_result();
                                                    $count = $check_result->fetch_assoc()['count'];
                                                    
                                                    if ($count > 0) {
                                                        echo '<span class="text-green-500 text-sm"><i class="fas fa-check-circle mr-1"></i> Results Added</span>';
                                                    } else {
                                                        echo '<span class="text-yellow-500 text-sm"><i class="fas fa-exclamation-circle mr-1"></i> No Results</span>';
                                                    }
                                                    $check_stmt->close();
                                                }
                                                
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="4" class="py-4 px-4 border-b border-gray-200 text-center">No exams found for this class.</td></tr>';
                                        }
                                        $stmt->close();
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Back Button -->
                <div class="mb-6">
                    <a href="manage_results.php" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-800 focus:outline-none focus:border-gray-800 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Subjects
                    </a>
                </div>
            <?php else: ?>
                <!-- Get exam details -->
                <?php
                $subject_name = '';
                $class_name = '';
                $section = '';
                $exam_name = '';
                $max_marks = 100;
                
                $stmt = $conn->prepare("SELECT subject_name FROM subjects WHERE subject_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $subject_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $subject_name = $result->fetch_assoc()['subject_name'];
                    }
                    $stmt->close();
                }
                
                $stmt = $conn->prepare("SELECT class_name, section FROM classes WHERE class_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $class_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $class_data = $result->fetch_assoc();
                        $class_name = $class_data['class_name'];
                        $section = $class_data['section'];
                    }
                    $stmt->close();
                }
                
                $stmt = $conn->prepare("SELECT exam_name, total_marks FROM exams WHERE exam_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $exam_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $exam_data = $result->fetch_assoc();
                        $exam_name = $exam_data['exam_name'];
                        $max_marks = $exam_data['total_marks'];
                    }
                    $stmt->close();
                }
                ?>
                
                <!-- Results Entry Form -->
                <div class="bg-white rounded-lg shadow-md mb-6">
                    <div class="border-b px-6 py-4 flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800">Enter Results for <?php echo htmlspecialchars($exam_name); ?></h2>
                        <div>
                            <span class="text-gray-600">Subject: <?php echo htmlspecialchars($subject_name); ?> | Class: <?php echo htmlspecialchars($class_name . ' - Section ' . $section); ?> | Max Marks: <?php echo $max_marks; ?></span>
                        </div>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="">
                            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                            <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white">
                                    <thead>
                                        <tr>
                                            <th class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No</th>
                                            <th class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks (Max: <?php echo $max_marks; ?>)</th>
                                            <th class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get students in this class
                                        $stmt = $conn->prepare("
                                            SELECT s.student_id, s.roll_number, u.full_name
                                            FROM students s
                                            JOIN users u ON s.user_id = u.user_id
                                            WHERE s.class_id = ?
                                            ORDER BY s.roll_number
                                        ");
                                        
                                        if (!$stmt) {
                                            echo '<tr><td colspan="4" class="py-4 px-4 border-b border-gray-200 text-center text-red-500">Error preparing statement: ' . $conn->error . '</td></tr>';
                                        } else {
                                            $stmt->bind_param("i", $class_id);
                                            $stmt->execute();
                                            $students_result = $stmt->get_result();
                                            
                                            if ($students_result->num_rows > 0) {
                                                while ($student = $students_result->fetch_assoc()) {
                                                    // Check if result already exists
                                                    $marks = '';
                                                    $remarks = '';
                                                    
                                                    $result_stmt = $conn->prepare("
                                                        SELECT marks, remarks 
                                                        FROM results 
                                                        WHERE student_id = ? AND subject_id = ? AND exam_id = ?
                                                    ");
                                                    
                                                    if ($result_stmt) {
                                                        $result_stmt->bind_param("iii", $student['student_id'], $subject_id, $exam_id);
                                                        $result_stmt->execute();
                                                        $result = $result_stmt->get_result();
                                                        
                                                        if ($result->num_rows > 0) {
                                                            $result_data = $result->fetch_assoc();
                                                            $marks = $result_data['marks'];
                                                            $remarks = $result_data['remarks'];
                                                        }
                                                        $result_stmt->close();
                                                    }
                                                    
                                                    echo '<tr class="hover:bg-blue-50 transition-colors duration-150">';
                                                    echo '<td class="py-4 px-4 border-b border-gray-200 font-medium">' . htmlspecialchars($student['roll_number']) . '</td>';
                                                    echo '<td class="py-4 px-4 border-b border-gray-200">' . htmlspecialchars($student['full_name']) . '</td>';
                                                    echo '<td class="py-4 px-4 border-b border-gray-200">';
                                                    echo '<input type="hidden" name="student_id[]" value="' . $student['student_id'] . '">';
                                                    echo '<input type="number" name="marks[]" value="' . $marks . '" min="0" max="' . $max_marks . '" step="0.01" class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200">';
                                                    echo '</td>';
                                                    echo '<td class="py-4 px-4 border-b border-gray-200">';
                                                    echo '<input type="text" name="remarks[]" value="' . htmlspecialchars($remarks) . '" class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200">';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="4" class="py-4 px-4 border-b border-gray-200 text-center">No students found in this class.</td></tr>';
                                            }
                                            $stmt->close();
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (isset($students_result) && $students_result->num_rows > 0): ?>
                                <div class="mt-6 flex justify-between">
                                    <button type="submit" name="save_results" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-700 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:from-blue-700 hover:to-indigo-800 active:bg-indigo-800 focus:outline-none focus:border-indigo-800 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-md">
                                        <i class="fas fa-save mr-2"></i>Save All Results
                                    </button>
                                    <a href="manage_results.php?subject_id=<?php echo $subject_id; ?>&class_id=<?php echo $class_id; ?>" class="inline-flex items-center px-6 py-3 bg-gray-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-800 focus:outline-none focus:border-gray-800 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-md">
                                        <i class="fas fa-arrow-left mr-2"></i>Back to Exams
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add any JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const markInputs = document.querySelectorAll('input[name="marks[]"]');
                    let hasError = false;
                    
                    markInputs.forEach(input => {
                        const value = parseFloat(input.value);
                        if (isNaN(value)) {
                            input.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                            hasError = true;
                        } else if (value < 0 || value > <?php echo $max_marks ?? 100; ?>) {
                            input.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                            hasError = true;
                        } else {
                            input.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
                        }
                    });
                    
                    if (hasError) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Validation Error',
                            text: 'Please check the marks. They must be between 0 and <?php echo $max_marks ?? 100; ?>.',
                            icon: 'error',
                            confirmButtonColor: '#4f46e5',
                            confirmButtonText: 'Fix Errors',
                            background: '#ffffff',
                            iconColor: '#ef4444',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp'
                            }
                        });
                    } else {
                        // Show loading indicator
                        Swal.fire({
                            title: 'Saving Results',
                            html: '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-500"></div></div><p class="mt-4">Please wait while we save the results...</p>',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            background: '#ffffff',
                            customClass: {
                                title: 'text-indigo-700'
                            }
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>
