<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Database connection (example using PDO)
require_once 'config/db.php';

// Get admin data from database
$admin_id = $_SESSION['admin_id'];
try {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        throw new Exception("Admin not found");
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Handle profile update
$update_success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate name
    if (empty($name)) {
        $errors['name'] = "Name is required";
    }
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }
    
    // Password change validation
    $password_changed = false;
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (!password_verify($current_password, $admin['password'])) {
            $errors['current_password'] = "Current password is incorrect";
        } elseif (strlen($new_password) < 8) {
            $errors['new_password'] = "Password must be at least 8 characters";
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords don't match";
        } else {
            $password_changed = true;
        }
    }
    
    // Update profile if no errors
    if (empty($errors)) {
        try {
            if ($password_changed) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $email, $hashed_password, $admin_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $email, $admin_id]);
            }
            
            $update_success = true;
            // Refresh admin data
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $errors['database'] = "Error updating profile: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --topbar-height: 60px;
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, var(--primary-color) 0%, #224abe 100%);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar-brand {
            height: var(--topbar-height);
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 800;
            padding: 1.5rem 1rem;
            text-align: center;
            letter-spacing: 0.05rem;
            z-index: 1001;
            color: #fff !important;
        }
        
        .sidebar-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            margin: 0 1rem 1rem;
        }
        
        .nav-item .nav-link {
            position: relative;
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            font-weight: 600;
        }
        
        .nav-item .nav-link:hover {
            color: #fff;
        }
        
        .nav-item .nav-link i {
            font-size: 0.85rem;
            margin-right: 0.25rem;
        }
        
        .nav-item.active .nav-link {
            color: #fff;
            font-weight: 700;
        }
        
        #content-wrapper {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        .topbar {
            height: var(--topbar-height);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            background-color: #fff;
        }
        
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            #content-wrapper {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar.toggled {
                margin-left: 0;
            }
            
            #content-wrapper.toggled {
                margin-left: var(--sidebar-width);
                width: calc(100% - var(--sidebar-width));
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboard.php">
            <div class="sidebar-brand-icon">
                <i class="bi bi-shield-lock"></i>
            </div>
            <div class="sidebar-brand-text mx-2">Admin Panel</div>
        </a>
        
        <hr class="sidebar-divider">
        
        <div class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link active" href="profile.php">
                    <i class="bi bi-person"></i>
                    <span>Profile</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="bi bi-people"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </div>
    </div>
    
    <!-- Content Wrapper -->
    <div id="content-wrapper">
        <!-- Topbar -->
        <nav class="navbar navbar-expand topbar mb-4 static-top shadow">
            <button id="sidebarToggle" class="btn btn-link d-md-none rounded-circle mr-3">
                <i class="bi bi-list"></i>
            </button>
            
            <!-- Topbar Navbar -->
            <ul class="navbar-nav ml-auto">
                <!-- Nav Item - Alerts -->
                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <span class="badge badge-danger badge-counter">3+</span>
                    </a>
                </li>
                
                <!-- Nav Item - Messages -->
                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-envelope"></i>
                        <span class="badge badge-danger badge-counter">7</span>
                    </a>
                </li>
                
                <div class="topbar-divider d-none d-sm-block"></div>
                
                <!-- Nav Item - User Information -->
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($admin['name']) ?></span>
                        <img class="img-profile rounded-circle" src="https://source.unsplash.com/QAB-WJcbgJk/60x60" width="32" height="32">
                    </a>
                    <!-- Dropdown - User Information -->
                    <div class="dropdown-menu dropdown-menu-right shadow">
                        <a class="dropdown-item" href="profile.php">
                            <i class="bi bi-person mr-2 text-gray-400"></i>
                            Profile
                        </a>
                        <a class="dropdown-item" href="#">
                            <i class="bi bi-gear mr-2 text-gray-400"></i>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="logout.php">
                            <i class="bi bi-box-arrow-right mr-2 text-gray-400"></i>
                            Logout
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
        
        <!-- Begin Page Content -->
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Admin Profile</h1>
            </div>
            
            <!-- Content Row -->
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <!-- Profile Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Profile Picture</h6>
                        </div>
                        <div class="card-body text-center">
                            <img class="profile-img rounded-circle mb-3" src="https://source.unsplash.com/QAB-WJcbgJk/150x150" alt="Profile Image">
                            <h5 class="font-weight-bold"><?= htmlspecialchars($admin['name']) ?></h5>
                            <p class="text-muted mb-1">Administrator</p>
                            <p class="text-muted mb-4"><?= htmlspecialchars($admin['email']) ?></p>
                            
                            <div class="d-flex justify-content-center mb-2">
                                <button type="button" class="btn btn-primary me-2">Change Photo</button>
                                <button type="button" class="btn btn-outline-secondary">Remove</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- About Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">About</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6 class="font-weight-bold">Full Name</h6>
                                <p><?= htmlspecialchars($admin['name']) ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="font-weight-bold">Email</h6>
                                <p><?= htmlspecialchars($admin['email']) ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="font-weight-bold">Join Date</h6>
                                <p><?= date('F j, Y', strtotime($admin['created_at'])) ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="font-weight-bold">Last Updated</h6>
                                <p><?= date('F j, Y', strtotime($admin['updated_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8 mb-4">
                    <!-- Edit Profile Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Edit Profile</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($update_success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    Profile updated successfully!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="profile.php">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                                               id="name" name="name" value="<?= htmlspecialchars($admin['name']) ?>" required>
                                        <?php if (isset($errors['name'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                               id="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <hr class="my-4">
                                
                                <h5 class="mb-3">Change Password</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>" 
                                               id="current_password" name="current_password">
                                        <?php if (isset($errors['current_password'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['current_password']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>" 
                                               id="new_password" name="new_password">
                                        <?php if (isset($errors['new_password'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['new_password']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" 
                                               id="confirm_password" name="confirm_password">
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['confirm_password']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">Leave password fields blank if you don't want to change it</small>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Activity Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow">
                                    <a class="dropdown-item" href="#">View All</a>
                                    <a class="dropdown-item" href="#">Clear All</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <a href="#" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Updated user profile</h6>
                                        <small>10 mins ago</small>
                                    </div>
                                    <p class="mb-1">Changed email for user john.doe@example.com</p>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Deleted post</h6>
                                        <small>1 hour ago</small>
                                    </div>
                                    <p class="mb-1">Removed inappropriate content</p>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">System maintenance</h6>
                                        <small>3 hours ago</small>
                                    </div>
                                    <p class="mb-1">Performed scheduled database backup</p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>Copyright &copy; Your Website <?= date('Y') ?></span>
                </div>
            </div>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('toggled');
            document.getElementById('content-wrapper').classList.toggle('toggled');
        });
        
        // Close alerts
        document.querySelectorAll('.btn-close').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.alert').remove();
            });
        });
    </script>
</body>
</html>