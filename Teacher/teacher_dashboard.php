<?php
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
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db_functions.php';

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$error_message = null;
$teacher = null;
$assigned_classes = [];
$assigned_subjects = [];
$recent_activities = [];
$pending_tasks = [];

try {
    // Get teacher details
    $teacher = getTeacherDetails($conn, $teacher_id);
    
    if (!$teacher) {
        // If teacher not found in teachers table, try to create a record
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'teacher'");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            // Create a new teacher record
            $stmt = $conn->prepare("INSERT INTO teachers (user_id, department, designation, employee_id) 
                                   VALUES (?, 'General', 'Teacher', ?)");
            $employee_id = 'T' . str_pad($teacher_id, 3, '0', STR_PAD_LEFT);
            $stmt->bind_param("is", $teacher_id, $employee_id);
            $stmt->execute();
            $stmt->close();
            
            // Get teacher details again
            $teacher = getTeacherDetails($conn, $teacher_id);
        } else {
            throw new Exception("Teacher user not found");
        }
    }
    
    // Get assigned classes
    $assigned_classes = getTeacherAssignedClasses($conn, $teacher_id);
    
    // Get assigned subjects
    $assigned_subjects = getTeacherAssignedSubjects($conn, $teacher_id);
    
    // Get recent activities
    $recent_activities = getTeacherRecentActivities($conn, $teacher_id, 5);
    
    // If no activities found, create default ones
    if (empty($recent_activities)) {
        $recent_activities = [
            ['activity_id' => 0, 'activity_type' => 'login', 'description' => 'Logged into the system', 'timestamp' => date('Y-m-d H:i:s')],
            ['activity_id' => 0, 'activity_type' => 'view', 'description' => 'Viewed dashboard', 'timestamp' => date('Y-m-d H:i:s')]
        ];
        
        // Log these activities
        logTeacherActivity($conn, $teacher_id, 'login', 'Logged into the system');
        logTeacherActivity($conn, $teacher_id, 'view', 'Viewed dashboard');
    }
    
    // Get pending tasks
    $pending_tasks = getTeacherPendingTasks($conn, $teacher_id);
    
    // If no pending tasks found, create some based on assigned subjects
    if (empty($pending_tasks) && !empty($assigned_subjects)) {
        foreach (array_slice($assigned_subjects, 0, 3) as $subject) {
            $pending_tasks[] = [
                'description' => "Enter marks for {$subject['subject_name']} - {$subject['class_name']} {$subject['section']}",
                'due_date' => date('M d, Y', strtotime('+1 week')),
                'action_url' => "edit_marks.php?subject_id={$subject['subject_id']}&class_id={$subject['class_id']}"
            ];
        }
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Teacher Dashboard Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $error_message = "An error occurred while loading the dashboard. Please try again later. Error: " . $e->getMessage();
}
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
            border-radius: 5px;
            padding: 8px 16px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
            border-radius: 10px;
            overflow: hidden;
        }
        .card-header {
            background-color: rgba(0, 0, 0, 0.03);
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            font-weight: 600;
        }
        .table th {
            font-weight: 600;
            background-color: rgba(0, 0, 0, 0.03);
        }
        .stat-card {
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.05);
            margin-right: 15px;
        }
        .profile-header {
            background-color: #4e73df;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
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
                            <a class="nav-link" href="class_performance.php">
                                <i class="bi bi-bar-chart me-2"></i>
                                Class Performance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="edit_marks.php">
                                <i class="bi bi-pencil-square me-2"></i>
                                Edit Marks
                            </a>
                        </li>
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
                    <h1 class="h2">Teacher Dashboard</h1>
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

                <!-- Profile Header -->
                <div class="profile-header mb-4">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <img src="<?php echo !empty($teacher['profile_image']) ? htmlspecialchars($teacher['profile_image']) : '../assets/images/default-teacher.png'; ?>" 
                                 alt="Teacher Profile" class="img-fluid rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                        </div>
                        <div class="col">
                            <h4>Welcome, <?php echo isset($teacher['name']) ? htmlspecialchars($teacher['name']) : 'Teacher'; ?>!</h4>
                            <p class="mb-0">
                                <?php if (isset($teacher['employee_id'])): ?>
                                    Teacher ID: <?php echo htmlspecialchars($teacher['employee_id']); ?> | 
                                    Department: <?php echo htmlspecialchars($teacher['department'] ?? 'General'); ?>
                                <?php else: ?>
                                    Welcome to your dashboard
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-auto">
                            <div class="d-flex">
                                <div class="me-3 text-center">
                                    <h3 class="mb-0"><?php echo count($assigned_classes); ?></h3>
                                    <small>Classes</small>
                                </div>
                                <div class="me-3 text-center">
                                    <h3 class="mb-0"><?php echo count($assigned_subjects); ?></h3>
                                    <small>Subjects</small>
                                </div>
                                <div class="text-center">
                                    <h3 class="mb-0"><?php echo count($pending_tasks); ?></h3>
                                    <small>Tasks</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-muted">Assigned Classes</h6>
                                        <h2 class="mb-0"><?php echo count($assigned_classes); ?></h2>
                                    </div>
                                    <div class="stat-icon bg-primary bg-opacity-10">
                                        <i class="bi bi-mortarboard text-primary fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-muted">Assigned Subjects</h6>
                                        <h2 class="mb-0"><?php echo count($assigned_subjects); ?></h2>
                                    </div>
                                    <div class="stat-icon bg-success bg-opacity-10">
                                        <i class="bi bi-book text-success fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 stat-card">
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
                                    <div class="stat-icon bg-info bg-opacity-10">
                                        <i class="bi bi-people text-info fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-muted">Pending Tasks</h6>
                                        <h2 class="mb-0"><?php echo count($pending_tasks); ?></h2>
                                    </div>
                                    <div class="stat-icon bg-warning bg-opacity-10">
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
                                    <a href="class_performance.php" class="btn btn-sm btn-outline-primary">View All</a>
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
                                    <a href="edit_marks.php" class="btn btn-sm btn-outline-primary">View All</a>
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
                                                <div class="d-flex align-items-center">
                                                    <div class="activity-icon">
                                                        <i class="<?php echo getActivityIcon($activity['activity_type']); ?>"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between">
                                                            <strong><?php echo htmlspecialchars($activity['description']); ?></strong>
                                                            <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?></small>
                                                        </div>
                                                        <small class="text-muted"><?php echo getTimeAgo($activity['timestamp']); ?></small>
                                                    </div>
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
                
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="edit_marks.php" class="card h-100 text-decoration-none text-dark">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-pencil-square text-primary" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="card-title">Edit Marks</h5>
                                        <p class="card-text small text-muted">Update student marks and grades</p>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="class_performance.php" class="card h-100 text-decoration-none text-dark">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-bar-chart text-success" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="card-title">Class Performance</h5>
                                        <p class="card-text small text-muted">View class performance analytics</p>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="profile.php" class="card h-100 text-decoration-none text-dark">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-person-circle text-info" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="card-title">My Profile</h5>
                                        <p class="card-text small text-muted">View and update your profile</p>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="../includes/logout.php" class="card h-100 text-decoration-none text-dark">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-box-arrow-right text-danger" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="card-title">Logout</h5>
                                        <p class="card-text small text-muted">Sign out from your account</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
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
