<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'result_management');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get parameters
$action = $_GET['action'] ?? '';
$exam_id = $_GET['exam_id'] ?? '';
$class_id = $_GET['class_id'] ?? null;
$student_id = $_GET['student_id'] ?? null;

if ($action !== 'check_existing' || empty($exam_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

$response = ['exists' => false];

try {
    if ($student_id) {
        // Check for specific student and exam combination (Manual Entry)
        $query = "SELECT r.*, s.subject_name, u.full_name as student_name, e.exam_name
                  FROM results r
                  JOIN subjects s ON r.subject_id = s.subject_id
                  JOIN students st ON r.student_id = st.student_id
                  JOIN users u ON st.user_id = u.user_id
                  JOIN exams e ON r.exam_id = e.exam_id
                  WHERE r.student_id = ? AND r.exam_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $student_id, $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response['exists'] = true;
            $subjects = [];
            $student_name = '';
            $exam_name = '';
            
            while ($row = $result->fetch_assoc()) {
                $subjects[] = $row['subject_name'];
                $student_name = $row['student_name'];
                $exam_name = $row['exam_name'];
            }
            
            $response['student_name'] = $student_name;
            $response['exam_name'] = $exam_name;
            $response['subjects'] = array_unique($subjects);
        }
        
    } else {
        // Check for exam and class combination (Batch Entry)
        $query = "SELECT r.*, s.subject_name, e.exam_name, c.class_name, c.section,
                         COUNT(DISTINCT r.student_id) as student_count
                  FROM results r
                  JOIN subjects s ON r.subject_id = s.subject_id
                  JOIN exams e ON r.exam_id = e.exam_id
                  LEFT JOIN students st ON r.student_id = st.student_id
                  LEFT JOIN classes c ON st.class_id = c.class_id
                  WHERE r.exam_id = ?";
        
        $params = [$exam_id];
        $types = "i";
        
        if ($class_id) {
            $query .= " AND st.class_id = ?";
            $params[] = $class_id;
            $types .= "i";
        }
        
        $query .= " GROUP BY r.exam_id, s.subject_id";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response['exists'] = true;
            $row = $result->fetch_assoc();
            
            $response['exam_name'] = $row['exam_name'];
            $response['subject_name'] = $row['subject_name'];
            $response['student_count'] = $row['student_count'];
            
            if ($class_id && $row['class_name']) {
                $response['class_name'] = $row['class_name'] . ' ' . ($row['section'] ?? '');
            }
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
}

header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>
