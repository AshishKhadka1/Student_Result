<?php

session_destroy();
echo "Session destroyed. Redirecting to index.php...";
// Use JavaScript for redirect as a fallback
echo "<script>window.location.href = 'index.php';</script>";
// Still try PHP redirect
header("Location: index.php");
exit();
?>