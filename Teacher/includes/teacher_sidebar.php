<!-- Sidebar -->
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <img src="<?php echo !empty($_SESSION['profile_image']) ? htmlspecialchars($_SESSION['profile_image']) : 'assets/images/default-teacher.png'; ?>" 
                 alt="Teacher Profile" class="img-fluid rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
            <h6 class="text-white"><?php echo htmlspecialchars($_SESSION['full_name']); ?></h6>
            <span class="badge bg-success">Teacher</span>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'teacher_dashboard.php' ? 'active bg-primary' : ''; ?>" href="teacher_dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'assigned_classes.php' ? 'active bg-primary' : ''; ?>" href="assigned_classes.php">
                    <i class="bi bi-mortarboard me-2"></i>
                    Assigned Classes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'assigned_subjects.php' ? 'active bg-primary' : ''; ?>" href="assigned_subjects.php">
                    <i class="bi bi-book me-2"></i>
                    Assigned Subjects
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'edit_marks.php' ? 'active bg-primary' : ''; ?>" href="edit_marks.php">
                    <i class="bi bi-pencil-square me-2"></i>
                    Edit Marks
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'class_performance.php' ? 'active bg-primary' : ''; ?>" href="class_performance.php">
                    <i class="bi bi-bar-chart me-2"></i>
                    Class Performance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active bg-primary' : ''; ?>" href="reports.php">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Reports
                </a>
            </li>
        </ul>
        
        <hr class="text-white-50">
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active bg-primary' : ''; ?>" href="profile.php">
                    <i class="bi bi-person-circle me-2"></i>
                    My Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active bg-primary' : ''; ?>" href="settings.php">
                    <i class="bi bi-gear me-2"></i>
                    Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="../includes/logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>
