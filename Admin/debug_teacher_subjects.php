<?php
// This is a debugging script to help identify issues with teacher subjects functionality

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo "Error: Unauthorized access. You must be logged in as an admin.";
    exit();
}

echo "<h1>Teacher Subjects Debug Information</h1>";

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if teachersubjects table exists
$table_check = $conn->query("SHOW TABLES LIKE 'teachersubjects'");
if ($table_check->num_rows == 0) {
    echo "<p style='color:red'>Error: The teachersubjects table does not exist in the database.</p>";
    echo "<p>Please run the SQL script in Database/fix_teachersubjects_table.sql to create it.</p>";
} else {
    echo "<p style='color:green'>Success: The teachersubjects table exists in the database.</p>";
    
    // Check table structure
    $structure = $conn->query("DESCRIBE teachersubjects");
    echo "<h2>Table Structure:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $expected_columns = ['id', 'teacher_id', 'subject_id', 'class_id', 'is_active', 'created_at', 'updated_at'];
    $missing_columns = $expected_columns;
    
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
        
        // Remove from missing columns if found
        if (in_array($row['Field'], $missing_columns)) {
            $missing_columns = array_diff($missing_columns, [$row['Field']]);
        }
    }
    echo "</table>";
    
    if (!empty($missing_columns)) {
        echo "<p style='color:red'>Error: The following columns are missing from the teachersubjects table: " . implode(', ', $missing_columns) . "</p>";
    } else {
        echo "<p style='color:green'>Success: All required columns exist in the teachersubjects table.</p>";
    }
    
    // Check data in the table
    $data = $conn->query("SELECT COUNT(*) as count FROM teachersubjects");
    $count = $data->fetch_assoc()['count'];
    echo "<p>There are currently $count records in the teachersubjects table.</p>";
    
    if ($count > 0) {
        $sample_data = $conn->query("SELECT ts.*, t.teacher_id, u.full_name as teacher_name, s.subject_name, c.class_name, c.section 
                                    FROM teachersubjects ts
                                    JOIN teachers t ON ts.teacher_id = t.teacher_id
                                    JOIN users u ON t.user_id = u.user_id
                                    JOIN subjects s ON ts.subject_id = s.subject_id
                                    JOIN classes c ON ts.class_id = c.class_id
                                    LIMIT 5");
        
        if ($sample_data) {
            echo "<h2>Sample Data:</h2>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Teacher</th><th>Subject</th><th>Class</th><th>Status</th><th>Created At</th></tr>";
            
            while ($row = $sample_data->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['teacher_name'] . " (ID: " . $row['teacher_id'] . ")</td>";
                echo "<td>" . $row['subject_name'] . " (ID: " . $row['subject_id'] . ")</td>";
                echo "<td>" . $row['class_name'] . " " . $row['section'] . " (ID: " . $row['class_id'] . ")</td>";
                echo "<td>" . ($row['is_active'] ? 'Active' : 'Inactive') . "</td>";
                echo "<td>" . $row['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color:red'>Error executing sample data query: " . $conn->error . "</p>";
        }
    }
}

// Check teachers table
$teachers = $conn->query("SELECT COUNT(*) as count FROM teachers");
$teacher_count = $teachers->fetch_assoc()['count'];
echo "<p>There are currently $teacher_count teachers in the database.</p>";

// Check subjects table
$subjects = $conn->query("SELECT COUNT(*) as count FROM subjects");
$subject_count = $subjects->fetch_assoc()['count'];
echo "<p>There are currently $subject_count subjects in the database.</p>";

// Check classes table
$classes = $conn->query("SELECT COUNT(*) as count FROM classes");
$class_count = $classes->fetch_assoc()['count'];
echo "<p>There are currently $class_count classes in the database.</p>";

// Test save_teacher_subjects.php endpoint
echo "<h2>Testing save_teacher_subjects.php Endpoint</h2>";
echo "<p>This will test if the save_teacher_subjects.php endpoint is working correctly.</p>";

// Get a sample teacher, subject, and class for testing
$sample_teacher = $conn->query("SELECT teacher_id FROM teachers LIMIT 1");
$sample_subject = $conn->query("SELECT subject_id FROM subjects LIMIT 1");
$sample_class = $conn->query("SELECT class_id FROM classes LIMIT 1");

if ($sample_teacher && $sample_subject && $sample_class) {
    $teacher_id = $sample_teacher->fetch_assoc()['teacher_id'];
    $subject_id = $sample_subject->fetch_assoc()['subject_id'];
    $class_id = $sample_class->fetch_assoc()['class_id'];
    
    echo "<p>Using Teacher ID: $teacher_id, Subject ID: $subject_id, Class ID: $class_id for testing.</p>";
    
    echo "<form action='save_teacher_subjects.php' method='post'>";
    echo "<input type='hidden' name='teacher_id' value='$teacher_id'>";
    echo "<input type='hidden' name='subject_id' value='$subject_id'>";
    echo "<input type='hidden' name='class_id' value='$class_id'>";
    echo "<button type='submit'>Test Assign Subject</button>";
    echo "</form>";
    
    echo "<p>You can also test the JavaScript functionality:</p>";
    echo "<button onclick='testAssignSubject($teacher_id, $subject_id, $class_id)'>Test JavaScript Assign</button>";
    echo "<button onclick='testToggleStatus($teacher_id)'>Test JavaScript Toggle</button>";
    echo "<button onclick='testRemoveSubject($teacher_id)'>Test JavaScript Remove</button>";
    
    echo "<div id='result' style='margin-top: 20px; padding: 10px; border: 1px solid #ccc;'></div>";
    
    echo "<script>
    function testAssignSubject(teacherId, subjectId, classId) {
        const formData = new FormData();
        formData.append('teacher_id', teacherId);
        formData.append('subject_id', subjectId);
        formData.append('class_id', classId);
        
        document.getElementById('result').innerHTML = 'Testing assign subject...';
        
        fetch('save_teacher_subjects.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                document.getElementById('result').innerHTML = 'Response: ' + JSON.stringify(data, null, 2);
            } catch (e) {
                document.getElementById('result').innerHTML = 'Raw Response (not JSON): ' + text;
            }
        })
        .catch(error => {
            document.getElementById('result').innerHTML = 'Error: ' + error.message;
        });
    }
    
    function testToggleStatus(teacherId) {
        // First get an existing assignment
        fetch('get_sample_assignment.php?teacher_id=' + teacherId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.assignment) {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('assignment_id', data.assignment.id);
                formData.append('status', data.assignment.is_active ? 0 : 1);
                formData.append('teacher_id', teacherId);
                
                document.getElementById('result').innerHTML = 'Testing toggle status for assignment ID: ' + data.assignment.id;
                
                return fetch('save_teacher_subjects.php', {
                    method: 'POST',
                    body: formData
                });
            } else {
                document.getElementById('result').innerHTML = 'No assignments found for this teacher. Please assign a subject first.';
                throw new Error('No assignments found');
            }
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                document.getElementById('result').innerHTML = 'Response: ' + JSON.stringify(data, null, 2);
            } catch (e) {
                document.getElementById('result').innerHTML = 'Raw Response (not JSON): ' + text;
            }
        })
        .catch(error => {
            if (error.message !== 'No assignments found') {
                document.getElementById('result').innerHTML = 'Error: ' + error.message;
            }
        });
    }
    
    function testRemoveSubject(teacherId) {
        // First get an existing assignment
        fetch('get_sample_assignment.php?teacher_id=' + teacherId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.assignment) {
                const formData = new FormData();
                formData.append('action', 'remove');
                formData.append('assignment_id', data.assignment.id);
                formData.append('teacher_id', teacherId);
                
                document.getElementById('result').innerHTML = 'Testing remove for assignment ID: ' + data.assignment.id;
                
                return fetch('save_teacher_subjects.php', {
                    method: 'POST',
                    body: formData
                });
            } else {
                document.getElementById('result').innerHTML = 'No assignments found for this teacher. Please assign a subject first.';
                throw new Error('No assignments found');
            }
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                document.getElementById('result').innerHTML = 'Response: ' + JSON.stringify(data, null, 2);
            } catch (e) {
                document.getElementById('result').innerHTML = 'Raw Response (not JSON): ' + text;
            }
        })
        .catch(error => {
            if (error.message !== 'No assignments found') {
                document.getElementById('result').innerHTML = 'Error: ' + error.message;
            }
        });
    }
    </script>";
} else {
    echo "<p style='color:red'>Error: Could not find sample data for testing. Make sure you have teachers, subjects, and classes in your database.</p>";
}

$conn->close();
?>
