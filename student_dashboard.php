<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch student details
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM Students WHERE user_id='$user_id'";
$result = $conn->query($sql);
$student = $result->fetch_assoc();

// Debug check - if student data isn't found, use a placeholder name
if (!isset($student['name'])) {
    // Check if there's a 'student_name' column instead
    if (isset($student['student_name'])) {
        $student['name'] = $student['student_name'];
    } else {
        // Fallback to a generic name
        $student['name'] = "Student";
    }
}

// Fetch results
$sql = "SELECT r.*, s.subject_name 
        FROM Results r 
        JOIN Subjects s ON r.subject_id = s.subject_id 
        WHERE r.student_id='{$student['student_id']}'";
$results = $conn->query($sql);

// Calculate GPA
$total_grade_points = 0;
$total_credit_hours = 0;

// Clone results to calculate GPA
$results_clone = $conn->query("SELECT * FROM Results WHERE student_id='{$student['student_id']}'");
while ($row = $results_clone->fetch_assoc()) {
    $total_grade_points += $row['gpa'] * $row['credit_hours'];
    $total_credit_hours += $row['credit_hours'];
}

$gpa = $total_credit_hours > 0 ? round($total_grade_points / $total_credit_hours, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Result - Result Management System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- jsPDF for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 20px;
            }
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

    <div id="resultSheet" class="max-w-4xl mx-auto my-4 p-6 bg-white shadow-md">
        <!-- Student Details Section -->
        <div class="header-blue p-3 font-bold text-lg">
            Student Details
        </div>
        
        <div class="grid grid-cols-2 border border-gray-300">
            <div class="p-3 border-r border-b border-gray-300">
                <span class="red-text font-bold">Symbol No:</span>
            </div>
            <div class="p-3 border-b border-gray-300">
                <span class="red-text font-bold"><?php echo $student['student_id']; ?></span>
            </div>
            <div class="p-3 border-r border-gray-300">
                <span class="red-text font-bold">Student Name:</span>
            </div>
            <div class="p-3">
                <span class="red-text font-bold"><?php echo htmlspecialchars($student['name']); ?></span>
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
                <tbody>
                    <?php 
                    $sn = 1;
                    while ($row = $results->fetch_assoc()) { 
                        // Convert marks to grades if needed
                        $theory_grade = isset($row['theory_grade']) ? $row['theory_grade'] : convertToGrade($row['theory_marks']);
                        $practical_grade = isset($row['practical_grade']) ? $row['practical_grade'] : 
                                        ($row['practical_marks'] > 0 ? convertToGrade($row['practical_marks']) : '');
                    ?>
                    <tr class="data-row">
                        <td><?php echo $sn++; ?></td>
                        <td><?php echo isset($row['subject_name']) ? $row['subject_name'] : $row['subject_id']; ?></td>
                        <td><?php echo isset($row['credit_hours']) ? $row['credit_hours'] : 4; ?></td>
                        <td><?php echo $theory_grade; ?></td>
                        <td><?php echo $practical_grade; ?></td>
                        <td><?php echo $row['grade']; ?></td>
                        <td><?php echo $row['gpa']; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-200">
                        <td colspan="6" class="text-right font-bold">GRADE POINT AVERAGE (GPA):</td>
                        <td class="font-bold"><?php echo $gpa; ?></td>
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
    </div>

    <div class="no-print max-w-4xl mx-auto mb-8 text-center text-sm text-gray-500">
        © <?php echo date('Y'); ?> Result Management System. All rights reserved.
    </div>

    <script>
        // PDF Generation
        function generatePDF() {
            // Use jsPDF and html2canvas
            const { jsPDF } = window.jspdf;
            
            const resultSheet = document.getElementById('resultSheet');
            
            // Create a new PDF document
            const doc = new jsPDF('p', 'mm', 'a4');
            
            // Use html2canvas to capture the result sheet
            html2canvas(resultSheet).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const imgWidth = 210; // A4 width in mm
                const pageHeight = 295; // A4 height in mm
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;
                
                // Add image to PDF
                doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                // Add new pages if content overflows
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    doc.addPage();
                    doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                // Save the PDF
                doc.save('Result_Sheet_<?php echo $student['student_id']; ?>.pdf');
            });
        }
    </script>
</body>
</html>

<?php
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