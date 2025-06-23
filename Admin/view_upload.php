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
$selected_student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get upload details with proper joins to match the manage_results.php data
$stmt = $conn->prepare("
    SELECT ru.*, u.full_name as uploaded_by_name, e.exam_name, e.exam_type, c.class_name, c.section 
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

// Get the student for this specific upload (should match the one from manage_results.php)
$stmt = $conn->prepare("
    SELECT DISTINCT r.student_id, u.full_name as student_name, s.roll_number
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE r.upload_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $upload_id);
$stmt->execute();
$student_result = $stmt->get_result();

if ($student_result->num_rows === 0) {
    $_SESSION['error_message'] = "No student found for this upload.";
    header("Location: manage_results.php?tab=manage");
    exit();
}

$student_info = $student_result->fetch_assoc();
$selected_student_id = $student_info['student_id'];
$stmt->close();

// Get detailed student information
$stmt = $conn->prepare("
    SELECT st.*, u.full_name as student_name, u.email, c.class_name, c.section
    FROM students st
    JOIN users u ON st.user_id = u.user_id
    LEFT JOIN classes c ON st.class_id = c.class_id
    WHERE st.student_id = ?
");

$stmt->bind_param("s", $selected_student_id);
$stmt->execute();
$selected_student_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get results for this single student from this specific upload
$stmt = $conn->prepare("
    SELECT r.*, s.subject_name, s.subject_code
    FROM results r
    JOIN subjects s ON r.subject_id = s.subject_id
    WHERE r.upload_id = ? AND r.student_id = ?
    ORDER BY s.subject_name
");

$stmt->bind_param("is", $upload_id, $selected_student_id);
$stmt->execute();
$results = $stmt->get_result();
$stmt->close();

// Count subjects for this entry
$subject_count = $results->num_rows;

// Function to get grade and grade point from percentage
function getGradeInfo($percentage)
{
    if ($percentage >= 90) return ['grade' => 'A+', 'point' => 4.0, 'class' => 'bg-green-100 text-green-800'];
    elseif ($percentage >= 80) return ['grade' => 'A', 'point' => 3.6, 'class' => 'bg-green-100 text-green-800'];
    elseif ($percentage >= 70) return ['grade' => 'B+', 'point' => 3.2, 'class' => 'bg-green-100 text-green-800'];
    elseif ($percentage >= 60) return ['grade' => 'B', 'point' => 2.8, 'class' => 'bg-green-100 text-green-800'];
    elseif ($percentage >= 50) return ['grade' => 'C+', 'point' => 2.4, 'class' => 'bg-yellow-100 text-yellow-800'];
    elseif ($percentage >= 40) return ['grade' => 'C', 'point' => 2.0, 'class' => 'bg-yellow-100 text-yellow-800'];
    elseif ($percentage >= 35) return ['grade' => 'D', 'point' => 1.6, 'class' => 'bg-orange-100 text-orange-800'];
    else return ['grade' => 'NG', 'point' => 0.0, 'class' => 'bg-red-100 text-red-800'];
}

// Function to format file name for display (matching manage_results.php)
function getDisplayFileName($fileName)
{
    if (strpos($fileName, 'Manual Entry') !== false) {
        return 'Manual Entry';
    }
    return basename($fileName);
}
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
        .info-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3b82f6;
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
                                <i class="fas fa-arrow-left mr-2"></i> Back to Manage
                            </a>
                        </div>

                        <!-- Student Profile Card -->
                        <div class="bg-white shadow-sm rounded-lg mb-6 p-6">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
                                    <?php echo strtoupper(substr($selected_student_info['student_name'], 0, 2)); ?>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($selected_student_info['student_name']); ?></h2>
                                    <div class="flex flex-wrap gap-4 mt-1 text-sm text-gray-600">
                                        <span><i class="fas fa-id-card mr-1"></i>ID: <?php echo htmlspecialchars($selected_student_info['student_id']); ?></span>
                                        <span><i class="fas fa-hashtag mr-1"></i>Roll: <?php echo htmlspecialchars($selected_student_info['roll_number']); ?></span>
                                        <span><i class="fas fa-users mr-1"></i>Class:
                                            <?php
                                            if (!empty($selected_student_info['class_name'])) {
                                                echo htmlspecialchars($selected_student_info['class_name']);
                                                if (!empty($selected_student_info['section'])) {
                                                    echo ' ' . htmlspecialchars($selected_student_info['section']);
                                                }
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </span>
                                        <?php if (!empty($selected_student_info['email'])): ?>
                                            <span><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($selected_student_info['email']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Enhanced Upload Information Section -->
                        <div class="bg-white border rounded-lg p-6 max-w-5xl mx-auto mb-6">
                            <!-- Header -->
                            <div class="border-b pb-4 mb-6">
                                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                    Upload Information
                                </h2>
                            </div>

                            <!-- Content Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm text-gray-700">

                                <!-- Exam -->
                                <div class="space-y-1">
                                    <p class="text-xs text-gray-500 uppercase">Exam</p>
                                    <p class="text-base font-medium">
                                        <?php
                                        echo !empty($upload['exam_name']) ? htmlspecialchars($upload['exam_name']) : 'N/A';
                                        ?>
                                    </p>
                                    <?php if (!empty($upload['exam_type'])): ?>
                                        <p class="text-xs text-gray-500"><?php echo ucfirst($upload['exam_type']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <!-- Upload Date -->
                                <div class="space-y-1">
                                    <p class="text-xs text-gray-500 uppercase">Upload Date</p>
                                    <p class="text-base font-medium"><?php echo date('M d, Y', strtotime($upload['upload_date'])); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo date('h:i A', strtotime($upload['upload_date'])); ?></p>
                                </div>

                                <!-- Subjects -->
                                <div>
                                    <p class="text-lg font-bold text-gray-800"><?php echo $subject_count; ?></p>
                                    <p class="text-xs text-gray-500 uppercase">Subjects</p>
                                </div>



                                <!-- Action Buttons -->
                                <div class="flex flex-wrap gap-3   ">
                                    <?php if ($upload['status'] != 'Published'): ?>
                                        <a href="publish_results.php?id=<?php echo $upload_id; ?>" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded">
                                            <i class="fas fa-check-circle mr-1"></i> Publish
                                        </a>
                                    <?php else: ?>
                                        <a href="unpublish_results.php?id=<?php echo $upload_id; ?>" class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm rounded">
                                            <i class="fas fa-eye-slash mr-1"></i> Unpublish
                                        </a>
                                    <?php endif; ?>
                                    <a href="delete_upload.php?id=<?php echo $upload_id; ?>" onclick="return confirm('Are you sure you want to delete this upload?')" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded">
                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Results Table for Single Student -->
                    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900 flex items-center">
                                <i class="fas fa-chart-line text-green-600 mr-2"></i>
                                Subject Results for <?php echo htmlspecialchars($selected_student_info['student_name']); ?>
                            </h3>
                        </div>

                        <?php if ($results && $results->num_rows > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <div class="flex flex-col">
                                                    <span>Theory</span>
                                                    <span class="text-xs font-normal text-gray-400">Marks/% /Grade</span>
                                                </div>
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <div class="flex flex-col">
                                                    <span>Practical</span>
                                                    <span class="text-xs font-normal text-gray-400">Marks/% /Grade</span>
                                                </div>
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <div class="flex flex-col">
                                                    <span>Total</span>
                                                    <span class="text-xs font-normal text-gray-400">Marks/%</span>
                                                </div>
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <div class="flex flex-col">
                                                    <span>Final Grade</span>
                                                    <span class="text-xs font-normal text-gray-400">& GPA</span>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php
                                        $total_gpa_points = 0;
                                        $subject_count = 0;
                                        $overall_total_marks = 0;
                                        $overall_full_marks = 0;
                                        $failed_subjects = 0;

                                        while ($row = $results->fetch_assoc()):
                                            // Determine full marks based on whether practical marks exist
                                            $has_practical = $row['practical_marks'] > 0;
                                            $theory_full_marks = $has_practical ? 75 : 100;
                                            $practical_full_marks = $has_practical ? 25 : 0;
                                            $total_full_marks = 100;

                                            // Calculate percentages
                                            $theory_percentage = ($row['theory_marks'] / $theory_full_marks) * 100;
                                            $practical_percentage = $has_practical ? ($row['practical_marks'] / $practical_full_marks) * 100 : 0;

                                            // Get grade info for theory and practical
                                            $theory_grade_info = getGradeInfo($theory_percentage);
                                            $practical_grade_info = $has_practical ? getGradeInfo($practical_percentage) : ['grade' => 'N/A', 'point' => 0, 'class' => 'bg-gray-100 text-gray-800'];

                                            // Check for failure condition (either theory or practical below 35%)
                                            $is_failed = ($theory_percentage < 35) || ($has_practical && $practical_percentage < 35);

                                            // Calculate final GPA
                                            if ($is_failed) {
                                                $final_gpa = 0.0;
                                                $final_grade = 'NG';
                                                $final_grade_class = 'bg-red-100 text-red-800';
                                                $failed_subjects++;
                                            } else {
                                                if ($has_practical) {
                                                    $final_gpa = (($theory_grade_info['point'] * $theory_full_marks) + ($practical_grade_info['point'] * $practical_full_marks)) / $total_full_marks;
                                                } else {
                                                    $final_gpa = $theory_grade_info['point'];
                                                }

                                                // Determine final grade based on GPA
                                                if ($final_gpa >= 3.8) $final_grade = 'A+';
                                                elseif ($final_gpa >= 3.4) $final_grade = 'A';
                                                elseif ($final_gpa >= 3.0) $final_grade = 'B+';
                                                elseif ($final_gpa >= 2.6) $final_grade = 'B';
                                                elseif ($final_gpa >= 2.2) $final_grade = 'C+';
                                                elseif ($final_gpa >= 1.8) $final_grade = 'C';
                                                elseif ($final_gpa >= 1.4) $final_grade = 'D';
                                                else $final_grade = 'NG';

                                                $final_grade_class = $final_grade == 'NG' ? 'bg-red-100 text-red-800' : ($final_gpa >= 3.0 ? 'bg-green-100 text-green-800' : ($final_gpa >= 2.0 ? 'bg-yellow-100 text-yellow-800' : 'bg-orange-100 text-orange-800'));
                                            }

                                            $subject_total_marks = $row['theory_marks'] + $row['practical_marks'];
                                            $overall_percentage = ($subject_total_marks / $total_full_marks) * 100;

                                            $total_gpa_points += $final_gpa;
                                            $subject_count++;
                                            $overall_total_marks += $subject_total_marks;
                                            $overall_full_marks += $total_full_marks;
                                        ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                                    <?php if (!empty($row['subject_code'])): ?>
                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['subject_code']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="flex flex-col">
                                                        <span><?php echo number_format($row['theory_marks'], 1); ?>/<?php echo $theory_full_marks; ?></span>
                                                        <span class="text-xs text-gray-400"><?php echo number_format($theory_percentage, 1); ?>%</span>
                                                        <span class="px-1 inline-flex text-xs leading-4 font-semibold rounded <?php echo $theory_grade_info['class']; ?>">
                                                            <?php echo $theory_grade_info['grade']; ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php if ($has_practical): ?>
                                                        <div class="flex flex-col">
                                                            <span><?php echo number_format($row['practical_marks'], 1); ?>/<?php echo $practical_full_marks; ?></span>
                                                            <span class="text-xs text-gray-400"><?php echo number_format($practical_percentage, 1); ?>%</span>
                                                            <span class="px-1 inline-flex text-xs leading-4 font-semibold rounded <?php echo $practical_grade_info['class']; ?>">
                                                                <?php echo $practical_grade_info['grade']; ?>
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="flex flex-col">
                                                        <span class="font-medium"><?php echo number_format($subject_total_marks, 1); ?>/100</span>
                                                        <span class="text-xs text-gray-400"><?php echo number_format($overall_percentage, 1); ?>%</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="flex flex-col">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $final_grade_class; ?>">
                                                            <?php echo $final_grade; ?>
                                                        </span>
                                                        <span class="text-xs text-gray-400 mt-1">GPA: <?php echo number_format($final_gpa, 2); ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>


                            <div class="bg-white border rounded-lg p-6 max-w-5xl mx-auto text-gray-800 mb-6">
                                <!-- Summary Header -->
                                <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                                    <i class="fas fa-chart-bar text-blue-600 mr-2"></i>
                                    Overall Result Summary
                                </h2>

                                <!-- Statistics Grid -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6 text-center text-sm">


                                    <!-- Total Marks -->
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">Total Marks</p>
                                        <p class="text-xl font-bold text-green-600"><?php echo number_format($overall_total_marks, 1); ?></p>
                                        <p class="text-xs text-gray-400">Out of <?php echo $overall_full_marks; ?></p>
                                    </div>

                                    <!-- Overall Percentage -->
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">Overall %</p>
                                        <p class="text-xl font-bold text-purple-600">
                                            <?php echo number_format(($overall_total_marks / $overall_full_marks) * 100, 1); ?>%
                                        </p>
                                    </div>

                                    <!-- Average GPA -->
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">Average GPA</p>
                                        <p class="text-xl font-bold <?php echo $failed_subjects > 0 ? 'text-red-600' : 'text-emerald-600'; ?>">
                                            <?php echo $subject_count > 0 ? number_format($total_gpa_points / $subject_count, 2) : '0.00'; ?>
                                        </p>
                                    </div>

                                    <div class="inline-block px-2 py-1 rounded text-sm font-semibold 
                                        <?php echo $failed_subjects > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo $failed_subjects > 0 ? 'FAIL' : 'PASS'; ?>
                                    </div>
                                </div>
                            </div>


                        <?php else: ?>
                            <div class="p-8 text-center">
                                <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No Results Found</h3>
                                <p class="text-gray-500">No subject results found for this student entry.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
        </main>
    </div>
    </div>
</body>

</html>