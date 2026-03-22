<?php
session_start();

// 1. Clear all session variables
$_SESSION = array();

// 2. Destroy the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// 3. Destroy the session on the server
session_destroy();

// 4. Redirect to the admin login page
header("Location: login.php");
exit();
?>