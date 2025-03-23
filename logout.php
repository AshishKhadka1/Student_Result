<?php

session_destroy();
echo "Session destroyed. Redirecting to login.php...";

header("Location: login.php");
exit();
?>