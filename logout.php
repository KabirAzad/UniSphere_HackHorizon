<?php
// UniSphere Logout Session Cleanup
session_start();
session_unset();
session_destroy();

header("Location: login.php");
exit();
?>
