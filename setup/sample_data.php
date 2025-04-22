<?php
// Sample data script to populate the database with test data
// Run this script to add sample data for testing

// Connect to database
$conn = new mysqli('localhost', 'root', '', 'result_management');

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Add sample classes
$classes = [
    ['class_name' => 'Grade 10', 'section' => 'A', 'academic_year' => '2023-2024'],
    ['class_name' => 'Grade 10', 'section' => 'B', 'academic_year' => '2023-2024'],
    ['class_name' => 'Grade 11', 'section' => 'Science', 'academic_year' => '2023-2024'],
    ['class_name' => 'Grade 11', 'section' => 'Management', 'academic_year' => '2023-2024'],
    ['class_name' => 'Grade 12', 'section' => 'Science', 'academic_year' => '2023-2024'],
    ['class_name' => 'Grade 12', 'section' => 'Management', 'academic_year' => '2023-2024'],
];

foreach ($classes as $class) {
    $stmt = $conn->prepare("INSERT INTO classes (class_name, section, academic_year) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $class['class_name'], $class['section'], $class['academic_year']);
    $stmt->execute();
    $stmt->close();
}

echo "Sample classes added.<br>";

// Add sample subjects
$subjects = [
    ['subject_name' => 'Mathematics', 'subject_code' => 'MATH101', 'full_marks_theory' => 75, 'full_marks_practical' => 25, 'credit_hours' => 4],
    ['subject_name' => 'Science', 'subject_code' => 'SCI101', 'full_marks_theory' => 60, 'full_marks_practical' => 40, 'credit_hours' => 4],
    ['subject_name' => 'English', 'subject_code' => 'ENG101', 'full_marks_theory' => 80, 'full_marks_practical' => 20, 'credit_hours' => 3],
    ['subject_name' => 'Social Studies', 'subject_code' => 'SOC101', 'full_marks_theory' => 80, 'full_marks_practical' => 20, 'credit_hours' => 3],
    ['subject_name' => 'Computer Science', 'subject_code' => 'CS101', 'full_marks_theory' => 50, 'full_marks_practical' => 50, 'credit_hours' => 4],
    ['subject_name' => 'Physics', 'subject_code' => 'PHY101', 'full_marks_theory' => 70, 'full_marks_practical' => 30, 'credit_hours' => 4],
    ['subject_name' => 'Chemistry', 'subject_code' => 'CHEM101', 'full_marks_theory' => 70, 'full_marks_practical' => 30, 'credit_hours' => 4],
    ['subject_name' => 'Biology', 'subject_code' => 'BIO101', 'full_marks_theory' => 70, 'full_marks_practical' => 30, 'credit_hours' => 4],
    ['subject_name' => 'Accounting', 'subject_code' => 'ACC101', 'full_marks_theory' => 80, 'full_marks_practical' => 20, 'credit_hours' => 3],
    ['subject_name' => 'Business Studies', 'subject_code' => 'BUS101', 'full_marks_theory' => 80, 'full_marks_practical' => 20, 'credit_hours' => 3],
];

foreach ($subjects as $subject) {
    $stmt = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, full_marks_theory, full_marks_practical, credit_hours) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddi", $subject['subject_name'], $subject['subject_code'], 
                      $subject['full_marks_theory'], $subject['full_marks_practical'], $subject['credit_hours']);
    $stmt->execute();
    $stmt->close();
}

echo "Sample subjects added.<br>";

// Add sample exams
$exams = [
    ['exam_name' => 'First Terminal', 'exam_type' => 'terminal', 'class_id' => 1, 'start_date' => '2023-09-15', 'end_date' => '2023-09-25', 'total_marks' => 100, 'status' => 'completed'],
    ['exam_name' => 'Mid-Term', 'exam_type' => 'midterm', 'class_id' => 1, 'start_date' => '2023-12-10', 'end_date' => '2023-12-20', 'total_marks' => 100, 'status' => 'completed'],
    ['exam_name' => 'Final Exam', 'exam_type' => 'final', 'class_id' => 1, 'start_date' => '2024-03-15', 'end_date' => '2024-03-25', 'total_marks' => 100, 'status' => 'upcoming'],
    ['exam_name' => 'First Terminal', 'exam_type' => 'terminal', 'class_id' => 3, 'start_date' => '2023-09-15', 'end_date' => '2023-09-25', 'total_marks' => 100, 'status' => 'completed'],
    ['exam_name' => 'Mid-Term', 'exam_type' => 'midterm', 'class_id' => 3, 'start_date' => '2023-12-10', 'end_date' => '2023-12-20', 'total_marks' => 100, 'status' => 'completed'],
    ['exam_name' => 'Final Exam', 'exam_type' => 'final', 'class_id' => 3, 'start_date' => '2024-03-15', 'end_date' => '2024-03-25', 'total_marks' => 100, 'status' => 'upcoming'],
];

foreach ($exams as $exam) {
    $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_type, class_id, start_date, end_date, total_marks, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissds", $exam['exam_name'], $exam['exam_type'], $exam['class_id'], 
                      $exam['start_date'], $exam['end_date'], $exam['total_marks'], $exam['status']);
    $stmt->execute();
    $stmt->close();
}

echo "Sample exams added.<br>";

// Assign subjects to teachers
// First, get all teachers
$stmt = $conn->prepare("SELECT teacher_id FROM teachers LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
$teachers = [];
while ($row = $result->fetch_assoc()) {
    $teachers[] = $row['teacher_id'];
}
$stmt->close();

if (!empty($teachers)) {
    // Assign subjects to teachers
    $assignments = [
        ['teacher_id' => $teachers[0], 'subject_id' => 1, 'class_id' => 1, 'academic_year' => '2023-2024'],
        ['teacher_id' => $teachers[0], 'subject_id' => 1, 'class_id' => 3, 'academic_year' => '2023-2024'],
        ['teacher_id' => $teachers[0], 'subject_id' => 1, 'class_id' => 5, 'academic_year' => '2023-2024'],
        ['teacher_id' => $teachers[1], 'subject_id' => 2, 'class_id' => 1, 'academic_year' => '2023-2024'],
        ['teacher_id' => $teachers[1], 'subject_id' => 6, 'class_id' => 3, 'academic_year' => '2023-2024'],
        ['teacher_id' => $teachers[1], 'subject_id' => 6, 'class_id' => 5, 'academic_year' => '2023-2024'],
        ['teacher_id' => $teachers[2], 'subject_id' => 3, 'class_id' => 1, 'academic_year' => '2023-2024'],
        ['teacher_id' => $teachers[2], 'subject_id' => 3, 'class_id' => 3, 'academic_year' => '2023-2024'],
        ['teacher_id' => $teachers[2], 'subject_id' => 3, 'class_id' => 5, 'academic_year' => '2023-2024'],
    ];

    foreach ($assignments as $assignment) {
        $stmt = $conn->prepare("INSERT INTO teachersubjects (teacher_id, subject_id, class_id, academic_year) 
                               VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $assignment['teacher_id'], $assignment['subject_id'], 
                          $assignment['class_id'], $assignment['academic_year']);
        $stmt->execute();
        $stmt->close();
    }

    echo "Sample teacher-subject assignments added.<br>";
} else {
    echo "No teachers found. Please add teachers first.<br>";
}

echo "<br>Sample data setup completed!";

$conn->close();
?>

