<?php
// connection_local.php
// This is your temporary local connection

session_start();

// Define empty connection variables so the interface loads without errors
$mysql_conn = null;
$mysql_conn2 = null;
$pg_conn = null;
$pdo = null;
$conn = null;

// HARDCODE LOGIN BYPASS
// If we are on the login page, we automatically set the session to let you in.
// This is just for viewing the interface!
if (basename($_SERVER['PHP_SELF']) == 'login.php') {
    $_SESSION['user_id'] = 999;
    $_SESSION['username'] = 'TestUser';
    $_SESSION['role'] = 'Pharmacist';
    header('Location: dashboard.php'); // Auto-redirect to dashboard
    exit;
}
?>