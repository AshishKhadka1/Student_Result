<?php
// Start session for potential authentication check
session_start();

// Check if viewing a specific student result or using sample data
if (isset($_GET['student_id']) && isset($_GET['exam_id'])) {
    // Connect to database
    $conn = new mysqli('localhost', 'root', '', 'result_management');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Get student information
    $student_id = $_GET['student_id'];
    $exam_id = $_GET['exam_id'];
    
    // Get student details
    $stmt = $conn->prepare("
        SELECT s.student_id, s.roll_number, s.registration_number, u.full_name, 
               c.class_name, c.section, e.exam_name, e.exam_type, e.academic_year,
               e.start_date, e.end_date
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        JOIN classes c ON s.class_id = c.class_id
        JOIN exams e ON e.class_id = c.class_id
        WHERE s.student_id = ? AND e.exam_id = ?
    ");
    $stmt->bind_param("si", $student_id, $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        die("Student or exam not found");
    }
    
    $student = $result->fetch_assoc();
    $stmt->close();
    
    // Get results for this student and exam
    $stmt = $conn->prepare("
        SELECT r.*, s.subject_name, s.subject_id as subject_code
        FROM results r
        JOIN subjects s ON r.subject_id = s.subject_id
        WHERE r.student_id = ? AND r.exam_id = ?
    ");
    $stmt->bind_param("si", $student_id, $exam_id);
    $stmt->execute();
    $results_data = $stmt->get_result();
    
    $subjects = [];
    $total_marks = 0;
    $total_subjects = 0;
    
    while ($row = $results_data->fetch_assoc()) {
        $subjects[] = [
            'code' => $row['subject_code'],
            'name' => $row['subject_name'],
            'credit_hour' => 5, // Default value, adjust as needed
            'theory_marks' => $row['theory_marks'],
            'practical_marks' => $row['practical_marks'],
            'total_marks' => $row['theory_marks'] + $row['practical_marks'],
            'grade' => $row['grade'],
            'remarks' => $row['remarks']
        ];
        
        $total_marks += ($row['theory_marks'] + $row['practical_marks']);
        $total_subjects++;
    }
    
    $stmt->close();
    $conn->close();
    
    // Calculate percentage
    $percentage = $total_subjects > 0 ? ($total_marks / ($total_subjects * 100)) * 100 : 0;
    
    // Calculate GPA (simplified)
    $gpa = 0;
    if ($percentage >= 90) {
        $gpa = 4.0;
    } elseif ($percentage >= 80) {
        $gpa = 3.7;
    } elseif ($percentage >= 70) {
        $gpa = 3.3;
    } elseif ($percentage >= 60) {
        $gpa = 3.0;
    } elseif ($percentage >= 50) {
        $gpa = 2.7;
    } elseif ($percentage >= 40) {
        $gpa = 2.3;
    } elseif ($percentage >= 33) {
        $gpa = 2.0;
    }
    
    // Set prepared by and issue date
    $prepared_by = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'System Administrator';
    $issue_date = date('Y-m-d');
    
} else {
    // Use sample data if no specific student is requested
    $student = [
        'full_name' => 'RAM BAHADUR SHRESTHA',
        'roll_number' => '123456',
        'registration_number' => '789012',
        'class_name' => 'Grade 10',
        'section' => 'A',
        'exam_name' => 'Final Examination',
        'academic_year' => '2080',
        'start_date' => '2080-01-15',
        'end_date' => '2080-01-25'
    ];
    
    // Sample subjects with their data
    $subjects = [
        ['code' => '002', 'name' => 'COMPULSORY NEPALI', 'credit_hour' => 5, 'theory_marks' => 75, 'practical_marks' => 20, 'total_marks' => 95, 'grade' => 'A', 'remarks' => ''],
        ['code' => '004', 'name' => 'COMPULSORY ENGLISH', 'credit_hour' => 5, 'theory_marks' => 70, 'practical_marks' => 22, 'total_marks' => 92, 'grade' => 'A', 'remarks' => ''],
        ['code' => '006', 'name' => 'MATHEMATICS', 'credit_hour' => 5, 'theory_marks' => 85, 'practical_marks' => 10, 'total_marks' => 95, 'grade' => 'A+', 'remarks' => ''],
        ['code' => '008', 'name' => 'SCIENCE AND TECHNOLOGY', 'credit_hour' => 5, 'theory_marks' => 72, 'practical_marks' => 23, 'total_marks' => 95, 'grade' => 'A', 'remarks' => ''],
        ['code' => '010', 'name' => 'SOCIAL STUDIES', 'credit_hour' => 4, 'theory_marks' => 65, 'practical_marks' => 20, 'total_marks' => 85, 'grade' => 'B+', 'remarks' => ''],
        ['code' => '102', 'name' => 'OPTIONAL I. MATHEMATICS', 'credit_hour' => 4, 'theory_marks' => 80, 'practical_marks' => 15, 'total_marks' => 95, 'grade' => 'A', 'remarks' => ''],
        ['code' => '202', 'name' => 'OPTIONAL II. SCIENCE', 'credit_hour' => 4, 'theory_marks' => 75, 'practical_marks' => 20, 'total_marks' => 95, 'grade' => 'A', 'remarks' => '']
    ];
    
    // Calculate GPA
    $gpa = 3.85;
    
    // Set prepared by and issue date
    $prepared_by = 'JOHN DOE';
    $issue_date = date('Y-m-d');
}

// Calculate total, percentage and division
$total_marks = 0;
$max_marks = 0;

foreach ($subjects as $subject) {
    $total_marks += $subject['total_marks'];
    $max_marks += 100; // Assuming each subject is out of 100
}

$percentage = ($total_marks / $max_marks) * 100;

// Determine division
$division = '';
if ($percentage >= 80) {
    $division = 'Distinction';
} elseif ($percentage >= 60) {
    $division = 'First Division';
} elseif ($percentage >= 45) {
    $division = 'Second Division';
} elseif ($percentage >= 33) {
    $division = 'Third Division';
} else {
    $division = 'Fail';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examination Grade Sheet</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
        }
        .container {
            width: 21cm;
            min-height: 29.7cm;
            padding: 1cm;
            margin: 0 auto;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            box-sizing: border-box;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(0, 0, 0, 0.03);
            z-index: 0;
            pointer-events: none;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            border-bottom: 2px solid #1a5276;
            padding-bottom: 10px;
        }
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 10px;
            background-color: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #555;
        }
        .title {
            font-weight: bold;
            font-size: 22px;
            margin-bottom: 5px;
            color: #1a5276;
        }
        .subtitle {
            font-size: 18px;
            margin-bottom: 5px;
            color: #2874a6;
        }
        .exam-title {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
            color: #1a5276;
            border: 2px solid #1a5276;
            display: inline-block;
            padding: 5px 15px;
            border-radius: 5px;
        }
        .student-info {
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .info-item {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #2874a6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        table, th, td {
            border: 1px solid #bdc3c7;
        }
        th, td {
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #1a5276;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .summary {
            margin: 20px 0;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        .summary-item {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
        }
        .summary-label {
            font-weight: bold;
            color: #2874a6;
            margin-bottom: 5px;
        }
        .summary-value {
            font-size: 18px;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .signature {
            text-align: center;
            margin-top: 50px;
        }
        .signature-line {
            width: 80%;
            margin: 50px auto 10px;
            border-top: 1px solid #333;
        }
        .signature-title {
            font-weight: bold;
        }
        .grade-scale {
            margin-top: 20px;
            font-size: 12px;
            border: 1px solid #bdc3c7;
            padding: 10px;
            background-color: #f9f9f9;
        }
        .grade-title {
            font-weight: bold;
            margin-bottom: 5px;
            text-align: center;
        }
        .grade-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .grade-table th, .grade-table td {
            padding: 3px;
            text-align: center;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #1a5276;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            z-index: 100;
        }
        .print-button:hover {
            background-color: #154360;
        }
        .qr-code {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 80px;
            height: 80px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #555;
        }
        @media print {
            body {
                background-color: white;
            }
            .container {
                width: 100%;
                min-height: auto;
                padding: 0.5cm;
                margin: 0;
                box-shadow: none;
            }
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Print Result</button>
    
    <div class="container">
        <div class="watermark">OFFICIAL</div>
        
        <div class="header">
            <div class="logo">LOGO</div>
            <div class="title">GOVERNMENT OF NEPAL</div>
            <div class="title">NATIONAL EXAMINATION BOARD</div>
            <div class="subtitle">SECONDARY EDUCATION EXAMINATION</div>
            <div class="exam-title">GRADE SHEET</div>
        </div>

        <div class="student-info">
            <div class="info-item">
                <span class="info-label">Student Name:</span> 
                <span><?php echo $student['full_name']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Roll No:</span> 
                <span><?php echo $student['roll_number']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Registration No:</span> 
                <span><?php echo $student['registration_number']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Class:</span> 
                <span><?php echo $student['class_name'] . ' ' . $student['section']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Examination:</span> 
                <span><?php echo $student['exam_name']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Academic Year:</span> 
                <span><?php echo $student['academic_year']; ?></span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>SUBJECT CODE</th>
                    <th>SUBJECTS</th>
                    <th>CREDIT HOUR</th>
                    <th>THEORY MARKS</th>
                    <th>PRACTICAL MARKS</th>
                    <th>TOTAL MARKS</th>
                    <th>GRADE</th>
                    <th>REMARKS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $subject): ?>
                <tr>
                    <td><?php echo $subject['code']; ?></td>
                    <td><?php echo $subject['name']; ?></td>
                    <td><?php echo $subject['credit_hour']; ?></td>
                    <td><?php echo $subject['theory_marks']; ?></td>
                    <td><?php echo $subject['practical_marks']; ?></td>
                    <td><?php echo $subject['total_marks']; ?></td>
                    <td><?php echo $subject['grade']; ?></td>
                    <td><?php echo $subject['remarks']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary">
            <div class="summary-item">
                <div class="summary-label">TOTAL MARKS</div>
                <div class="summary-value"><?php echo $total_marks; ?> / <?php echo $max_marks; ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">PERCENTAGE</div>
                <div class="summary-value"><?php echo number_format($percentage, 2); ?>%</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">GPA</div>
                <div class="summary-value"><?php echo number_format($gpa, 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">DIVISION</div>
                <div class="summary-value"><?php echo $division; ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">RESULT</div>
                <div class="summary-value"><?php echo $percentage >= 33 ? 'PASS' : 'FAIL'; ?></div>
            </div>
        </div>

        <div class="grade-scale">
            <div class="grade-title">GRADING SCALE</div>
            <table class="grade-table">
                <tr>
                    <th>Grade</th>
                    <th>A+</th>
                    <th>A</th>
                    <th>B+</th>
                    <th>B</th>
                    <th>C+</th>
                    <th>C</th>
                    <th>D+</th>
                    <th>D</th>
                    <th>E</th>
                </tr>
                <tr>
                    <th>Percentage</th>
                    <td>90-100</td>
                    <td>80-89</td>
                    <td>70-79</td>
                    <td>60-69</td>
                    <td>50-59</td>
                    <td>40-49</td>
                    <td>30-39</td>
                    <td>20-29</td>
                    <td>0-19</td>
                </tr>
                <tr>
                    <th>Grade Point</th>
                    <td>4.0</td>
                    <td>3.6</td>
                    <td>3.2</td>
                    <td>2.8</td>
                    <td>2.4</td>
                    <td>2.0</td>
                    <td>1.6</td>
                    <td>1.2</td>
                    <td>0.8</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <div class="signature">
                <div class="signature-line"></div>
                <div class="signature-title">PREPARED BY</div>
                <div><?php echo $prepared_by; ?></div>
            </div>
            <div class="signature">
                <div class="signature-line"></div>
                <div class="signature-title">PRINCIPAL</div>
                <div>SCHOOL PRINCIPAL</div>
            </div>
        </div>
        
        <div class="qr-code">QR CODE</div>
        
        <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #777;">
            <p>This is a computer-generated document. No signature is required.</p>
            <p>Issue Date: <?php echo date('d-m-Y', strtotime($issue_date)); ?></p>
        </div>
    </div>
</body>
</html>