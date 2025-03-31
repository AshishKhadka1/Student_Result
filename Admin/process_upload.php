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

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['message'] = "Error uploading file. Please try again.";
    $_SESSION['message_type'] = "red";
    header("Location: manage_results.php");
    exit();
}

// Check file type
$fileType = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
if ($fileType != 'csv') {
    $_SESSION['message'] = "Only CSV files are allowed.";
    $_SESSION['message_type'] = "red";
    header("Location: manage_results.php");
    exit();
}

// Get other form data
$description = isset($_POST['description']) ? $_POST['description'] : '';
$publish = isset($_POST['publish']) ? true : false;
$overwrite = isset($_POST['overwrite']) ? true : false;

// Create upload record
$fileName = $_FILES['file']['name'];
$status = $publish ? 'Published' : 'Draft';

$stmt = $conn->prepare("INSERT INTO ResultUploads (file_name, description, status, uploaded_by, upload_date) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("sssi", $fileName, $description, $status, $_SESSION['user_id']);
$stmt->execute();
$uploadId = $conn->insert_id;

// Process CSV file
$file = fopen($_FILES['file']['tmp_name'], 'r');
$headers = fgetcsv($file); // Get headers
$studentCount = 0;
$errorCount = 0;
$errorMessages = [];

// Validate headers
$requiredHeaders = ['student_id', 'subject_id', 'theory_marks', 'practical_marks', 'credit_hours'];
$missingHeaders = array_diff($requiredHeaders, array_map('strtolower', $headers));

if (!empty($missingHeaders)) {
    $_SESSION['message'] = "CSV file is missing required headers: " . implode(', ', $missingHeaders);
    $_SESSION['message_type'] = "red";
    header("Location: manage_results.php");
    exit();
}

// Map header indices
$headerMap = [];
foreach ($requiredHeaders as $header) {
    $index = array_search(strtolower($header), array_map('strtolower', $headers));
    if ($index !== false) {
        $headerMap[$header] = $index;
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    // Process each row
    while (($row = fgetcsv($file)) !== false) {
        $studentId = $row[$headerMap['student_id']];
        $subjectId = $row[$headerMap['subject_id']];
        $theoryMarks = $row[$headerMap['theory_marks']];
        $practicalMarks = $row[$headerMap['practical_marks']] ?? null;
        $creditHours = $row[$headerMap['credit_hours']];
        
        // Validate data
        if (empty($studentId) || empty($subjectId) || !is_numeric($theoryMarks) || !is_numeric($creditHours)) {
            $errorCount++;
            $errorMessages[] = "Row " . ($studentCount + 1) . ": Invalid data format.";
            continue;
        }
        
        // Check if student exists
        $stmt = $conn->prepare("SELECT student_id FROM Students WHERE student_id = ?");
        $stmt->bind_param("s", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $errorCount++;
            $errorMessages[] = "Row " . ($studentCount + 1) . ": Student ID $studentId not found.";
            continue;
        }
        
        // Check if subject exists
        $stmt = $conn->prepare("SELECT subject_id FROM Subjects WHERE subject_id = ?");
        $stmt->bind_param("s", $subjectId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $errorCount++;
            $errorMessages[] = "Row " . ($studentCount + 1) . ": Subject ID $subjectId not found.";
            continue;
        }
        
        // Calculate grade and GPA
        $grade = calculateGrade($theoryMarks, $practicalMarks);
        $gpa = calculateGPA($grade);
        
        // Check if result already exists
        $stmt = $conn->prepare("SELECT result_id FROM Results WHERE student_id = ? AND subject_id = ?");
        $stmt->bind_param("ss", $studentId, $subjectId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            if ($overwrite) {
                // Update existing result
                $stmt = $conn->prepare("UPDATE Results SET theory_marks = ?, practical_marks = ?, credit_hours = ?, grade = ?, gpa = ?, upload_id = ?, updated_at = NOW() WHERE student_id = ? AND subject_id = ?");
                $stmt->bind_param("dddsdiis", $theoryMarks, $practicalMarks, $creditHours, $grade, $gpa, $uploadId, $studentId, $subjectId);
                $stmt->execute();
            } else {
                $errorCount++;
                $errorMessages[] = "Row " . ($studentCount + 1) . ": Result already exists for student $studentId and subject $subjectId.";
                continue;
            }
        } else {
            // Insert new result
            $stmt = $conn->prepare("INSERT INTO Results (student_id, subject_id, theory_marks, practical_marks, credit_hours, grade, gpa, upload_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssdddsdi", $studentId, $subjectId, $theoryMarks, $practicalMarks, $creditHours, $grade, $gpa, $uploadId);
            $stmt->execute();
        }
        
        $studentCount++;
    }
    
    // Update upload record with student count
    $stmt = $conn->prepare("UPDATE ResultUploads SET student_count = ? WHERE id = ?");
    $stmt->bind_param("ii", $studentCount, $uploadId);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    if ($errorCount > 0) {
        $_SESSION['message'] = "Upload completed with $errorCount errors. $studentCount results processed successfully.";
        $_SESSION['message_type'] = "yellow";
        $_SESSION['error_messages'] = $errorMessages;
    } else {
        $_SESSION['message'] = "Upload completed successfully. $studentCount results processed.";
        $_SESSION['message_type'] = "green";
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $_SESSION['message'] = "Error processing file: " . $e->getMessage();
    $_SESSION['message_type'] = "red";
}

fclose($file);
header("Location: manage_results.php");
exit();

// Helper functions
function calculateGrade($theoryMarks, $practicalMarks = null) {
    // Calculate average marks
    $totalMarks = $theoryMarks;
    $divisor = 1;
    
    if (!empty($practicalMarks)) {
        $totalMarks += $practicalMarks;
        $divisor = 2;
    }
    
    $averageMarks = $totalMarks / $divisor;
    
    // Determine grade based on average marks
    if ($averageMarks >= 90) return 'A+';
    if ($averageMarks >= 80) return 'A';
    if ($averageMarks >= 70) return 'B+';
    if ($averageMarks >= 60) return 'B';
    if ($averageMarks >= 50) return 'C+';
    if ($averageMarks >= 40) return 'C';
    if ($averageMarks >= 30) return 'D';
    return 'F';
}

function calculateGPA($grade) {
    switch ($grade) {
        case 'A+': return 4.0;
        case 'A': return 3.6;
        case 'B+': return 3.2;
        case 'B': return 2.8;
        case 'C+': return 2.4;
        case 'C': return 2.0;
        case 'D': return 1.6;
        default: return 0.0;
    }
}
?>

