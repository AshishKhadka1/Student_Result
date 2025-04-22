<?php
// Start session
session_start();

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Clear remember-me cookies if they exist
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

if (isset($_COOKIE['remember_role'])) {
    setcookie('remember_role', '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../login.php");
exit();
?>
