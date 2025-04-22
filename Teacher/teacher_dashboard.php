<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Include database connection and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$teacher = [];

try {
    // Get teacher details
    $stmt = $conn->prepare("SELECT t.*, u.full_name as name, u.email, u.profile_image 
                           FROM teachers t 
                           JOIN users u ON t.user_id = u.user_id 
                           WHERE t.user_id = ?");
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $teacher_id);
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    $stmt->close();
    
    if (!$teacher) {
        throw new Exception("Teacher information not found");
    }
    
    // Get assigned classes
    $assigned_classes = [];
    $stmt = $conn->prepare("SELECT DISTINCT c.*, 
                          (SELECT COUNT(*) FROM students s WHERE s.class_id = c.class_id) as student_count
                          FROM classes c 
                          JOIN teachersubjects ts ON c.class_id = ts.class_id 
                          JOIN teachers t ON ts.teacher_id = t.teacher_id 
                          WHERE t.user_id = ?
                          ORDER BY c.academic_year DESC, c.class_name ASC");
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assigned_classes[] = $row;
    }
    $stmt->close();
    
    // Get assigned subjects
    $assigned_subjects = [];
    $stmt = $conn->prepare("SELECT ts.*, s.subject_name, s.subject_code, c.class_name, c.section
                          FROM teachersubjects ts 
                          JOIN subjects s ON ts.subject_id = s.subject_id 
                          JOIN classes c ON ts.class_id = c.class_id 
                          JOIN teachers t ON ts.teacher_id = t.teacher_id 
                          WHERE t.user_id = ?
                          ORDER BY c.class_name ASC, s.subject_name ASC");
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assigned_subjects[] = $row;
    }
    $stmt->close();
    
    // Get recent activities (simplified for now)
    $recent_activities = [
        ['activity_type' => 'login', 'description' => 'Logged into the system', 'timestamp' => date('Y-m-d H:i:s')],
        ['activity_type' => 'view', 'description' => 'Viewed dashboard', 'timestamp' => date('Y-m-d H:i:s')]
    ];
    
    // Get pending tasks (simplified for now)
    $pending_tasks = [];
    foreach ($assigned_subjects as $index => $subject) {
        if ($index < 3) { // Limit to 3 tasks for demo
            $pending_tasks[] = [
                'description' => "Enter marks for {$subject['subject_name']} - {$subject['class_name']} {$subject['section']}",
                'due_date' => date('M d, Y', strtotime('+1 week')),
                'action_url' => "edit_marks.php?subject_id={$subject['subject_id']}&class_id={$subject['class_id']}"
            ];
        }
    }
    
} catch (Exception $e) {
    // Log the error with more details
    error_log("Teacher Dashboard Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    $error_message = "An error occurred while loading the dashboard. Please try again later.";
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* Basic styling */
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: rgba(0, 0, 0, 0.03);
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        }
        .table th {
            font-weight: 600;
            background-color: rgba(0, 0, 0, 0.03);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <img src="<?php echo !empty($teacher['profile_image']) ? htmlspecialchars($teacher['profile_image']) : '../assets/images/default-teacher.png'; ?>" 
                             alt="Teacher Profile" class="img-fluid rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                        <h6 class="text-white"><?php echo isset($teacher['name']) ? htmlspecialchars($teacher['name']) : 'Teacher'; ?></h6>
                        <span class="badge bg-success">Teacher</span>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="teacher_dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assigned_classes.php">
                                <i class="bi bi-mortarboard me-2"></i>
                                Assigned Classes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assigned_subjects.php">
                                <i class="bi bi-book me-2"></i>
                                Assigned Subjects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="edit_marks.php">
                                <i class="bi bi-pencil-square me-2"></i>
                                Edit Marks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="class_performance.php">
                                <i class="bi bi-bar-chart me-2"></i>
                                Class Performance
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-white-50">
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person-circle me-2"></i>
                                My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../includes/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="academicYearDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-calendar3"></i> Academic Year: 2023-2024
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="academicYearDropdown">
                                <li><a class="dropdown-item" href="#">2023-2024</a></li>
                                <li><a class="dropdown-item" href="#">2022-2023</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Welcome Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h4>Welcome, <?php echo isset($teacher['name']) ? htmlspecialchars($teacher['name']) : 'Teacher'; ?>!</h4>
                                <p class="text-muted">
                                    <?php if (isset($teacher['employee_id'])): ?>
                                        Teacher ID: <?php echo htmlspecialchars($teacher['employee_id']); ?> | 
                                        Department: <?php echo htmlspecialchars($teacher['department'] ?? 'N/A'); ?>
                                    <?php else: ?>
                                        Welcome to the Teacher Dashboard
                                    <?php endif; ?>
                                </p>
                                <p>You have <span class="badge bg-warning"><?php echo count($pending_tasks); ?></span> pending tasks and <span class="badge bg-info"><?php echo count($assigned_classes); ?></span> assigned classes.</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <img src="<?php echo !empty($teacher['profile_image']) ? htmlspecialchars($teacher['profile_image']) : '../assets/images/default-teacher.png'; ?>" alt="Teacher Profile" class="img-fluid rounded-circle" style="max-width: 100px;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-muted">Assigned Classes</h6>
                                        <h2 class="mb-0"><?php echo count($assigned_classes); ?></h2>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-mortarboard text-primary fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-muted">Assigned Subjects</h6>
                                        <h2 class="mb-0"><?php echo count($assigned_subjects); ?></h2>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-book text-success fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-muted">Total Students</h6>
                                        <h2 class="mb-0">
                                            <?php 
                                                $total_students = 0;
                                                foreach ($assigned_classes as $class) {
                                                    $total_students += $class['student_count'];
                                                }
                                                echo $total_students;
                                            ?>
                                        </h2>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-people text-info fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-muted">Pending Tasks</h6>
                                        <h2 class="mb-0"><?php echo count($pending_tasks); ?></h2>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-clipboard-check text-warning fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assigned Classes and Subjects -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Assigned Classes</h5>
                                    <a href="assigned_classes.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Class</th>
                                                <th>Section</th>
                                                <th>Students</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($assigned_classes)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No classes assigned yet.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach (array_slice($assigned_classes, 0, 5) as $class): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($class['section']); ?></td>
                                                        <td><?php echo htmlspecialchars($class['student_count']); ?></td>
                                                        <td>
                                                            <a href="class_performance.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-sm btn-outline-info">
                                                                <i class="bi bi-bar-chart"></i> Performance
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Assigned Subjects</h5>
                                    <a href="assigned_subjects.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Class</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($assigned_subjects)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No subjects assigned yet.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach (array_slice($assigned_subjects, 0, 5) as $subject): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($subject['class_name'] . ' ' . $subject['section']); ?></td>
                                                        <td>
                                                            <a href="edit_marks.php?subject_id=<?php echo $subject['subject_id']; ?>&class_id=<?php echo $subject['class_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-pencil-square"></i> Edit Marks
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities and Pending Tasks -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($recent_activities)): ?>
                                        <li class="list-group-item text-center">No recent activities.</li>
                                    <?php else: ?>
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <li class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="bi bi-activity me-2"></i>
                                                        <?php echo htmlspecialchars($activity['description']); ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?></small>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Pending Tasks</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($pending_tasks)): ?>
                                        <li class="list-group-item text-center">No pending tasks.</li>
                                    <?php else: ?>
                                        <?php foreach ($pending_tasks as $task): ?>
                                            <li class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="bi bi-exclamation-circle text-warning me-2"></i>
                                                        <?php echo htmlspecialchars($task['description']); ?>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-danger"><?php echo htmlspecialchars($task['due_date']); ?></span>
                                                        <a href="<?php echo $task['action_url']; ?>" class="btn btn-sm btn-outline-primary ms-2">
                                                            <i class="bi bi-arrow-right"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Debug Information (remove in production) -->
                <?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Debug Information</h5>
                    </div>
                    <div class="card-body">
                        <h6>Session Variables:</h6>
                        <pre><?php print_r($_SESSION); ?></pre>
                        
                        <h6>Teacher Data:</h6>
                        <pre><?php print_r($teacher); ?></pre>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple script to highlight the current page in the sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage) {
                    link.classList.add('active');
                    link.setAttribute('aria-current', 'page');
                }
            });
        });
    </script>
</body>
</html>
