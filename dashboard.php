<?php
include "includes/config.php"; // Start session

if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome, <?php echo $_SESSION['user']; ?>!</h1>
    <a href="#">Logout</a>
</body>
</html>
