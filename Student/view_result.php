<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

// Fetch student details
$user_id = $_SESSION['user_id'];
$sql = "SELECT s.*, u.full_name 
        FROM Students s 
        JOIN Users u ON s.user_id = u.user_id 
        WHERE s.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// If student data isn't found, use placeholder
if (!$student) {
    die("Student record not found. Please contact administrator.");
}

// Fetch results with subject names
$sql = "SELECT r.*, s.subject_name 
        FROM Results r 
        JOIN Subjects s ON r.subject_id = s.subject_id 
        WHERE r.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student['student_id']);
$stmt->execute();
$results = $stmt->get_result();

// Calculate GPA
$total_grade_points = 0;
$total_credit_hours = 0;

// Clone results to calculate GPA
$sql = "SELECT * FROM Results WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student['student_id']);
$stmt->execute();
$results_clone = $stmt->get_result();

while ($row = $results_clone->fetch_assoc()) {
    $total_grade_points += $row['gpa'] * $row['credit_hours'];
    $total_credit_hours += $row['credit_hours'];
}

$gpa = $total_credit_hours > 0 ? round($total_grade_points / $total_credit_hours, 2) : 0;

$conn->close();

// Helper function to convert marks to grade
function convertToGrade($marks) {
    if ($marks >= 90) return 'A+';
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B+';
    if ($marks >= 60) return 'B';
    if ($marks >= 50) return 'C+';
    if ($marks >= 40) return 'C';
    if ($marks >= 30) return 'D';
    return 'F';
}

// Helper function to convert marks to GPA
function convertToGPA($marks) {
    if ($marks >= 90) return 4.0;
    if ($marks >= 80) return 3.6;
    if ($marks >= 70) return 3.2;
    if ($marks >= 60) return 2.8;
    if ($marks >= 50) return 2.4;
    if ($marks >= 40) return 2.0;
    if ($marks >= 30) return 1.6;
    return 0.0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Result - Result Management System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jsPDF for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
        }
        .header-blue {
            background-color: #2c4a7c;
            color: white;
        }
        .data-row:nth-child(even) {
            background-color: #f2f2f2;
        }
        .data-row:hover {
            background-color: #e6e6e6;
        }
        .result-table th, 
        .result-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
        }
        .result-table th {
            text-align: left;
        }
        .red-text {
            color: #e53e3e;
        }
        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tab-button {
            padding: 10px 15px;
            background-color: #e2e8f0;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .tab-button.active {
            background-color: #2c4a7c;
            color: white;
        }
        .grade-badge {
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .grade-a-plus {
            background-color: #dcfce7;
            color: #166534;
        }
        .grade-a {
            background-color: #dcfce7;
            color: #166534;
        }
        .grade-b-plus {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .grade-b {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .grade-c-plus {
            background-color: #fef9c3;
            color: #854d0e;
        }
        .grade-c {
            background-color: #fef9c3;
            color: #854d0e;
        }
        .grade-d {
            background-color: #ffedd5;
            color: #9a3412;
        }
        .grade-f {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                padding: 20px;
            }
        }
        .print-only {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="no-print bg-blue-900 text-white p-4 flex justify-between items-center">
        <div class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span class="font-semibold text-xl">Result Management System</span>
        </div>
        <div class="flex items-center">
            <button onclick="window.print()" class="mr-2 bg-white text-blue-900 px-3 py-1 rounded flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print
            </button>
            <button onclick="generatePDF()" class="mr-2 bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Download PDF
            </button>
            <a href="logout.php" class="bg-red-700 hover:bg-red-800 px-3 py-1 rounded flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Logout
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="no-print max-w-4xl mx-auto mt-8 flex">
        <button class="tab-button active" onclick="openTab('result')">View Results</button>
        <button class="tab-button" onclick="openTab('progress')">Track Progress</button>
        <button class="tab-button" onclick="openTab('download')">Download Options</button>
    </div>

    <!-- Result Tab Content -->
    <div id="result" class="tab-content active">
        <?php
echo '<div id="resultSheet" class="max-w-4xl mx-auto my-4 p-6 bg-white shadow-md">
    <!-- Student Details Section -->
    <div class="header-blue p-3 font-bold text-lg">
        Student Details
    </div>
    
    <div class="grid grid-cols-2 border border-gray-300">
        <div class="p-3 border-r border-b border-gray-300">
            <span class="red-text font-bold">Symbol No:</span>
        </div>
        <div class="p-3 border-b border-gray-300">
            <span class="red-text font-bold">'.$student['student_id'].'</span>
        </div>
        <div class="p-3 border-r border-b border-gray-300">
            <span class="red-text font-bold">Student Name:</span>
        </div>
        <div class="p-3 border-b border-gray-300">
            <span class="red-text font-bold">'.$student['full_name'].'</span>
        </div>
        <div class="p-3 border-r border-gray-300">
            <span class="red-text font-bold">Date of Birth:</span>
        </div>
        <div class="p-3">
            <span class="red-text font-bold">'.(isset($student['dob']) ? $student['dob'] : date('Y/m/d')).'</span>
        </div>
    </div>

    <!-- Grade Details Section -->
    <div class="mt-6">
        <div class="header-blue p-3 font-bold text-lg">
            Grade Details
        </div>
        
        <table class="result-table w-full border-collapse">
            <thead>
                <tr class="header-blue">
                    <th>S. No.</th>
                    <th>Subjects</th>
                    <th>Credit Hour<sup>1</sup></th>
                    <th colspan="2">Obtained Grade<sup>2</sup></th>
                    <th>Final Grade</th>
                    <th>Grade Point</th>
                </tr>
                <tr class="header-blue">
                    <th></th>
                    <th></th>
                    <th></th>
                    <th>TH</th>
                    <th>PR</th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>';
                $sn = 1;
                while ($row = $results->fetch_assoc()) { 
                    // Convert marks to grades if needed
                    $theory_grade = isset($row['theory_grade']) ? $row['theory_grade'] : convertToGrade($row['theory_marks']);
                    $practical_grade = isset($row['practical_grade']) ? $row['practical_grade'] : 
                                    ($row['practical_marks'] > 0 ? convertToGrade($row['practical_marks']) : '');
                echo '<tr class="data-row">
                    <td>'.$sn++.'</td>
                    <td>'.(isset($row['subject_name']) ? $row['subject_name'] : $row['subject_id']).'</td>
                    <td>'.(isset($row['credit_hours']) ? $row['credit_hours'] : 4).'</td>
                    <td>'.$theory_grade.'</td>
                    <td>'.$practical_grade.'</td>
                    <td>'.$row['grade'].'</td>
                    <td>'.$row['gpa'].'</td>
                </tr>';
                 } 
            echo '</tbody>
            <tfoot>
                <tr class="bg-gray-200">
                    <td colspan="6" class="text-right font-bold">GRADE POINT AVERAGE (GPA):</td>
                    <td class="font-bold">'.$gpa.'</td>
                </tr>
            </tfoot>
        </table>
        
        <div class="mt-4 text-xs">
            <p><sup>1</sup> Credit Hour represents the weight of the subject.</p>
            <p><sup>2</sup> TH = Theory, PR = Practical</p>
        </div>
        
        <!-- GPA Scale Reference -->
        <div class="mt-6 bg-gray-100 p-4 text-xs">
            <p class="font-bold mb-2">GPA Scale Reference:</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <div>A+ (4.0): 90-100%</div>
                <div>A (3.6): 80-89%</div>
                <div>B+ (3.2): 70-79%</div>
                <div>B (2.8): 60-69%</div>
                <div>C+ (2.4): 50-59%</div>
                <div>C (2.0): 40-49%</div>
                <div>D (1.6): 30-39%</div>
                <div>F (0.0): Below 30%</div>
            </div>
        </div>
    </div>
</div>';
?>
    </div>

    <!-- Progress Tracking Tab -->
    <div id="progress" class="tab-content">
        <div class="max-w-4xl mx-auto my-4 p-6 bg-white shadow-md">
            <h2 class="text-xl font-bold text-blue-900 mb-4">Academic Progress Tracking</h2>
            
            <!-- GPA Progress Chart -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-blue-800 mb-2">GPA Progress</h3>
                <div class="h-64 bg-white p-4 rounded-lg shadow-sm">
                    <canvas id="gpaChart"></canvas>
                </div>
                <p class="text-sm text-gray-600 mt-2">This chart shows your GPA progression over time.</p>
            </div>
            
            <!-- Subject-wise Performance Chart -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-blue-800 mb-2">Subject Performance</h3>
                <div class="h-64 bg-white p-4 rounded-lg shadow-sm">
                    <canvas id="subjectChart"></canvas>
                </div>
                <p class="text-sm text-gray-600 mt-2">Compare your performance across different subjects.</p>
            </div>
            
            <!-- Theory vs Practical Performance -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-blue-800 mb-2">Theory vs Practical Performance</h3>
                <div class="h-64 bg-white p-4 rounded-lg shadow-sm">
                    <canvas id="theoryPracticalChart"></canvas>
                </div>
                <p class="text-sm text-gray-600 mt-2">Compare your theory and practical marks over time.</p>
            </div>
            
            <!-- Performance Summary -->
            <div class="bg-blue-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-blue-800 mb-2">Performance Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white p-4 rounded-lg shadow-sm">
                        <h4 class="font-medium text-blue-700 mb-1">Current GPA</h4>
                        <div class="flex items-baseline">
                            <span class="text-3xl font-bold text-blue-900"><?php echo $gpa; ?></span>
                            <span class="text-sm text-gray-500 ml-1">/ 4.0</span>
                        </div>
                        <div class="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-600 rounded-full" style="width: <?php echo ($gpa / 4) * 100; ?>%;"></div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-4 rounded-lg shadow-sm">
                        <h4 class="font-medium text-blue-700 mb-1">Improvement</h4>
                        <?php 
                        $improvement = count($gpa_trend) >= 2 ? $gpa_trend[count($gpa_trend) - 1] - $gpa_trend[0] : 0;
                        $improvement_percent = count($gpa_trend) >= 2 ? ($improvement / max(0.1, $gpa_trend[0])) * 100 : 0;
                        ?>
                        <div class="flex items-baseline">
                            <span class="text-3xl font-bold <?php echo $improvement >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $improvement >= 0 ? '+' : ''; ?><?php echo number_format($improvement, 2); ?>
                            </span>
                            <span class="text-sm text-gray-500 ml-1">points</span>
                        </div>
                        <p class="text-sm <?php echo $improvement >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $improvement >= 0 ? '+' : ''; ?><?php echo number_format($improvement_percent, 1); ?>% since first term
                        </p>
                    </div>
                    
                    <div class="bg-white p-4 rounded-lg shadow-sm">
                        <h4 class="font-medium text-blue-700 mb-1">Best Subject</h4>
                        <?php
                        // Find best subject
                        $results->data_seek(0);
                        $best_subject = '';
                        $best_gpa = 0;
                        while ($row = $results->fetch_assoc()) {
                            if ($row['gpa'] > $best_gpa) {
                                $best_gpa = $row['gpa'];
                                $best_subject = isset($row['subject_name']) ? $row['subject_name'] : $row['subject_id'];
                            }
                        }
                        ?>
                        <div class="text-lg font-semibold text-blue-900"><?php echo $best_subject; ?></div>
                        <div class="flex items-baseline">
                            <span class="text-2xl font-bold text-green-600"><?php echo $best_gpa; ?></span>
                            <span class="text-sm text-gray-500 ml-1">GPA</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Download Options Tab -->
    <div id="download" class="tab-content">
        <div class="max-w-4xl mx-auto my-4 p-6 bg-white shadow-md">
            <h2 class="text-xl font-bold text-blue-900 mb-4">Download Result Sheets</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-blue-50 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-blue-800 mb-3">Current Result Sheet</h3>
                    <p class="text-gray-600 mb-4">Download your latest result sheet in PDF format.</p>
                    <button onclick="generatePDF('current')" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Download Result PDF
                    </button>
                </div>
                
                <div class="bg-green-50 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-green-800 mb-3">Progress Report</h3>
                    <p class="text-gray-600 mb-4">Download a comprehensive progress report with charts and analysis.</p>
                    <button onclick="generatePDF('progress')" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Download Progress PDF
                    </button>
                </div>
            </div>
            
            <div class="mt-6 bg-gray-50 p-6 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Official NEB Format</h3>
                <p class="text-gray-600 mb-4">Download your result in the official National Examination Board format.</p>
                <button onclick="generatePDF('neb')" class="w-full bg-gray-800 hover:bg-gray-900 text-white py-2 px-4 rounded flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download NEB Format PDF
                </button>
            </div>
            
            <div class="mt-6 p-4 bg-yellow-50 rounded-lg">
                <div class="flex items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="text-sm text-yellow-700">
                            <span class="font-medium">Note:</span> These PDF documents are for personal use only. For official purposes, please request a certified copy from the administration office.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="no-print max-w-4xl mx-auto mb-8 text-center text-sm text-gray-500">
        © <?php echo date('Y'); ?> Result Management System. All rights reserved.
    </div>

    <script>
        // Tab functionality
        function openTab(tabName) {
            const tabContents = document.getElementsByClassName('tab-content');
            const tabButtons = document.getElementsByClassName('tab-button');
            
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
                tabButtons[i].classList.remove('active');
            }
            
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        // PDF Generation
        function generatePDF(type = 'current') {
            // Use jsPDF
            const { jsPDF } = window.jspdf;
            
            if (type === 'current') {
                // Generate current result sheet PDF
                const resultSheet = document.getElementById('resultSheet');
                
                html2canvas(resultSheet).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const imgWidth = 210; // A4 width in mm
                    const pageHeight = 295; // A4 height in mm
                    const imgHeight = canvas.height * imgWidth / canvas.width;
                    let heightLeft = imgHeight;
                    let position = 0;
                    
                    // Add image to PDF
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                    
                    // Add new pages if content overflows
                    while (heightLeft >= 0) {
                        position = heightLeft - imgHeight;
                        pdf.addPage();
                        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                        heightLeft -= pageHeight;
                    }
                    
                    // Save the PDF
                    pdf.save('Result_Sheet_<?php echo $student['student_id']; ?>.pdf');
                });
            } else if (type === 'progress') {
                // Generate progress report PDF
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                // Add title
                pdf.setFontSize(18);
                pdf.setTextColor(44, 74, 124);
                pdf.text('Academic Progress Report', 105, 15, { align: 'center' });
                
                // Add student info
                pdf.setFontSize(12);
                pdf.setTextColor(0, 0, 0);
                pdf.text(`Student: <?php echo $student['full_name']; ?>`, 20, 30);
                pdf.text(`Symbol No: <?php echo $student['student_id']; ?>`, 20, 37);
                pdf.text(`Date: ${new Date().toLocaleDateString()}`, 20, 44);
                
                // Add current GPA
                pdf.setFontSize(14);
                pdf.setTextColor(44, 74, 124);
                pdf.text('Current Performance', 20, 60);
                
                pdf.setFontSize(12);
                pdf.setTextColor(0, 0, 0);
                pdf.text(`Current GPA: <?php echo $gpa; ?> / 4.0`, 20, 70);
                
                // Add improvement info
                <?php 
                $improvement = count($gpa_trend) >= 2 ? $gpa_trend[count($gpa_trend) - 1] - $gpa_trend[0] : 0;
                $improvement_percent = count($gpa_trend) >= 2 ? ($improvement / max(0.1, $gpa_trend[0])) * 100 : 0;
                ?>
                pdf.text(`GPA Improvement: <?php echo $improvement >= 0 ? '+' : ''; ?><?php echo number_format($improvement, 2); ?> points (<?php echo $improvement >= 0 ? '+' : ''; ?><?php echo number_format($improvement_percent, 1); ?>%)`, 20, 77);
                
                // Add best subject
                <?php
                // Reset results pointer
                $results->data_seek(0);
                $best_subject = '';
                $best_gpa = 0;
                while ($row = $results->fetch_assoc()) {
                    if ($row['gpa'] > $best_gpa) {
                        $best_gpa = $row['gpa'];
                        $best_subject = isset($row['subject_name']) ? $row['subject_name'] : $row['subject_id'];
                    }
                }
                ?>
                pdf.text(`Best Subject: <?php echo $best_subject; ?> (GPA: <?php echo $best_gpa; ?>)`, 20, 84);
                
                // Add table of results
                pdf.setFontSize(14);
                pdf.setTextColor(44, 74, 124);
                pdf.text('Subject Results', 20, 100);
                
                // Create table
                pdf.autoTable({
                    startY: 105,
                    head: [['Subject', 'Theory', 'Practical', 'Grade', 'GPA']],
                    body: [
                        <?php 
                        $results->data_seek(0);
                        while ($row = $results->fetch_assoc()) {
                            $subject_name = isset($row['subject_name']) ? $row['subject_name'] : $row['subject_id'];
                            $theory = $row['theory_marks'];
                            $practical = $row['practical_marks'] ? $row['practical_marks'] : '-';
                            $grade = $row['grade'];
                            $subject_gpa = $row['gpa'];
                            echo "['$subject_name', '$theory', '$practical', '$grade', '$subject_gpa'],";
                        }
                        ?>
                    ],
                    theme: 'grid',
                    headStyles: { fillColor: [44, 74, 124], textColor: [255, 255, 255] }
                });
                
                // Add note at the bottom
                const finalY = pdf.lastAutoTable.finalY || 150;
                pdf.setFontSize(10);
                pdf.setTextColor(100, 100, 100);
                pdf.text('This report is generated automatically by the Result Management System.', 105, finalY + 15, { align: 'center' });
                pdf.text('For official purposes, please request a certified copy from the administration.', 105, finalY + 20, { align: 'center' });
                
                // Save the PDF
                pdf.save('Progress_Report_<?php echo $student['student_id']; ?>.pdf');
            } else if (type === 'neb') {
                // Generate NEB format PDF
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                // Add NEB header
                pdf.setFontSize(16);
                pdf.setTextColor(0, 0, 0);
                pdf.text('NATIONAL EXAMINATION BOARD', 105, 15, { align: 'center' });
                pdf.setFontSize(14);
                pdf.text('GRADE SHEET', 105, 22, { align: 'center' });
                
                // Add student info in NEB format
                pdf.setFontSize(12);
                pdf.text('SYMBOL NO:', 20, 35);
                pdf.text('<?php echo $student['student_id']; ?>', 70, 35);
                
                pdf.text('STUDENT NAME:', 20, 42);
                pdf.text('<?php echo strtoupper($student['full_name']); ?>', 70, 42);
                
                pdf.text('DATE OF BIRTH:', 20, 49);
                pdf.text('<?php echo isset($student['dob']) ? $student['dob'] : date('Y/m/d'); ?>', 70, 49);
                
                // Add grade table in NEB format
                pdf.autoTable({
                    startY: 60,
                    head: [
                        ['S.N.', 'SUBJECT', 'CREDIT HOUR', 'THEORY', 'PRACTICAL', 'FINAL GRADE', 'GRADE POINT']
                    ],
                    body: [
                        <?php 
                        $results->data_seek(0);
                        $sn = 1;
                        while ($row = $results->fetch_assoc()) {
                            $subject_name = isset($row['subject_name']) ? strtoupper($row['subject_name']) : "SUBJECT " . $row['subject_id'];
                            $credit_hours = isset($row['credit_hours']) ? $row['credit_hours'] : 4;
                            $theory_grade = isset($row['theory_grade']) ? $row['theory_grade'] : convertToGrade($row['theory_marks']);
                            $practical_grade = isset($row['practical_grade']) ? $row['practical_grade'] : 
                                            ($row['practical_marks'] > 0 ? convertToGrade($row['practical_marks']) : '-');
                            $grade = $row['grade'];
                            $gpa = $row['gpa'];
                            
                            echo "['$sn', '$subject_name', '$credit_hours', '$theory_grade', '$practical_grade', '$grade', '$gpa'],";
                            $sn++;
                        }
                        ?>
                    ],
                    foot: [
                        ['', '', '', '', 'GPA', '', '<?php echo $gpa; ?>']
                    ],
                    theme: 'grid',
                    headStyles: { fillColor: [50, 50, 50], textColor: [255, 255, 255] },
                    footStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold' }
                });
                
                // Add GPA scale
                const finalY = pdf.lastAutoTable.finalY || 150;
                pdf.setFontSize(10);
                pdf.text('GPA SCALE:', 20, finalY + 15);
                pdf.text('A+ (4.0): 90-100%', 20, finalY + 22);
                pdf.text('A (3.6): 80-89%', 60, finalY + 22);
                pdf.text('B+ (3.2): 70-79%', 100, finalY + 22);
                pdf.text('B (2.8): 60-69%', 140, finalY + 22);
                pdf.text('C+ (2.4): 50-59%', 20, finalY + 29);
                pdf.text('C (2.0): 40-49%', 60, finalY + 29);
                pdf.text('D (1.6): 30-39%', 100, finalY + 29);
                pdf.text('F (0.0): Below 30%', 140, finalY + 29);
                
                // Add official signatures
                pdf.setFontSize(11);
                pdf.text('_________________', 40, finalY + 50);
                pdf.text('_________________', 105, finalY + 50);
                pdf.text('_________________', 170, finalY + 50);
                
                pdf.setFontSize(10);
                pdf.text('Prepared By', 40, finalY + 55);
                pdf.text('Checked By', 105, finalY + 55);
                pdf.text('Controller of Examinations', 170, finalY + 55);
                
                // Add date and official stamp text
                pdf.setFontSize(10);
                pdf.text('Date: _______________', 20, finalY + 65);
                pdf.text('Official Stamp', 170, finalY + 65);
                
                // Save the PDF
                pdf.save('NEB_Result_<?php echo $student['student_id']; ?>.pdf');
            }
        }
        
        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // GPA Progress Chart
                const gpaCtx = document.getElementById('gpaChart');
                if (gpaCtx) {
                    const gpaChart = new Chart(gpaCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($time_periods); ?>,
                            datasets: [{
                                label: 'GPA',
                                data: <?php echo json_encode($gpa_trend); ?>,
                                backgroundColor: 'rgba(66, 135, 245, 0.2)',
                                borderColor: 'rgba(66, 135, 245, 1)',
                                borderWidth: 2,
                                tension: 0.1,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    min: 0,
                                    max: 4,
                                    title: {
                                        display: true,
                                        text: 'GPA (0-4)'
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Time Period'
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return `GPA: ${context.raw.toFixed(2)}`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Subject Performance Chart
                const subjectCtx = document.getElementById('subjectChart');
                if (subjectCtx) {
                    // Prepare data for subject chart
                    const subjectData = {};
                    <?php foreach ($subject_names as $subject): ?>
                        subjectData['<?php echo $subject; ?>'] = [];
                    <?php endforeach; ?>
                    
                    <?php foreach ($chart_data as $data): ?>
                        if (subjectData['<?php echo $data['subject']; ?>']) {
                            subjectData['<?php echo $data['subject']; ?>'].push({
                                x: '<?php echo $data['period']; ?>',
                                y: <?php echo $data['gpa']; ?>
                            });
                        }
                    <?php endforeach; ?>
                    
                    const subjectChart = new Chart(subjectCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            datasets: [
                                <?php 
                                $colors = ['rgba(255, 99, 132, 1)', 'rgba(54, 162, 235, 1)', 'rgba(255, 206, 86, 1)', 
                                        'rgba(75, 192, 192, 1)', 'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)'];
                                $i = 0;
                                foreach ($subject_names as $subject): 
                                    $color = $colors[$i % count($colors)];
                                    $i++;
                                ?>
                                ,{
                                    label: '<?php echo $subject; ?>',
                                    data: subjectData['<?php echo $subject; ?>'],
                                    borderColor: '<?php echo $color; ?>',
                                    backgroundColor: '<?php echo str_replace('1)', '0.2)', $color); ?>',
                                    borderWidth: 2,
                                    tension: 0.1
                                },
                                <?php endforeach; ?>
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    min: 0,
                                    max: 4,
                                    title: {
                                        display: true,
                                        text: 'GPA (0-4)'
                                    }
                                },
                                x: {
                                    type: 'category',
                                    title: {
                                        display: true,
                                        text: 'Time Period'
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Theory vs Practical Chart
                const theoryPracticalCtx = document.getElementById('theoryPracticalChart');
                if (theoryPracticalCtx) {
                    // Prepare data for theory vs practical chart
                    const theoryData = [];
                    const practicalData = [];
                    
                    <?php
                    // Group by period and calculate averages
                    $theory_by_period = [];
                    $practical_by_period = [];
                    
                    foreach ($chart_data as $data) {
                        $period = $data['period'];
                        
                        if (!isset($theory_by_period[$period])) {
                            $theory_by_period[$period] = ['sum' => 0, 'count' => 0];
                            $practical_by_period[$period] = ['sum' => 0, 'count' => 0];
                        }
                        
                        $theory_by_period[$period]['sum'] += $data['theory_marks'];
                        $theory_by_period[$period]['count']++;
                        
                        if ($data['practical_marks'] > 0) {
                            $practical_by_period[$period]['sum'] += $data['practical_marks'];
                            $practical_by_period[$period]['count']++;
                        }
                    }
                    
                    foreach ($time_periods as $period) {
                        $theory_avg = isset($theory_by_period[$period]) && $theory_by_period[$period]['count'] > 0 
                            ? $theory_by_period[$period]['sum'] / $theory_by_period[$period]['count'] 
                            : 0;
                        
                        $practical_avg = isset($practical_by_period[$period]) && $practical_by_period[$period]['count'] > 0 
                            ? $practical_by_period[$period]['sum'] / $practical_by_period[$period]['count'] 
                            : 0;
                        
                        echo "theoryData.push(" . round($theory_avg, 1) . ");\n";
                        echo "practicalData.push(" . round($practical_avg, 1) . ");\n";
                    }
                    ?>
                    
                    const theoryPracticalChart = new Chart(theoryPracticalCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($time_periods); ?>,
                            datasets: [
                                {
                                    label: 'Theory',
                                    data: theoryData,
                                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Practical',
                                    data: practicalData,
                                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                                    borderColor: 'rgba(255, 99, 132, 1)',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100,
                                    title: {
                                        display: true,
                                        text: 'Average Marks'
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Time Period'
                                    }
                                }
                            }
                        }
                    });
                }
            } catch (error) {
                console.error("Error initializing charts:", error);
            }
        });
    </script>
</body>
</html>
