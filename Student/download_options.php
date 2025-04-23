<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get student information
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
