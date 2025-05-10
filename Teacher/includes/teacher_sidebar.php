<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get the current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <img src="../img/logo.png" alt="School Logo" class="img-fluid rounded-circle mb-3" style="width: 80px; height: 80px; object-fit: cover;">
            <h5>Result Management</h5>
            <p class="text-muted">Teacher Portal</p>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'teacher_dashboard.php') ? 'active' : ''; ?>" href="teacher_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_results.php') ? 'active' : ''; ?>" href="manage_results.php">
                    <i class="fas fa-graduation-cap me-2"></i>
                    Manage Results
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'edit_marks.php') ? 'active' : ''; ?>" href="edit_marks.php">
                    <i class="fas fa-edit me-2"></i>
                    Edit Marks
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'class_performance.php') ? 'active' : ''; ?>" href="class_performance.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Class Performance
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'student_details.php') ? 'active' : ''; ?>" href="student_details.php">
                    <i class="fas fa-user-graduate me-2"></i>
                    Student Details
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user-cog me-2"></i>
                    My Profile
                </a>
            </li>
        </ul>
        
        <hr class="my-3">
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../includes/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

