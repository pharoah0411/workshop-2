<?php
session_start();
require_once 'connection.php';
require_once 'audit.php'; // Ensure this matches your actual filename

// 1. Identify which connection is available to perform the logging
$auditConn = null;
if (isset($pdo) && $pdo instanceof PDO) $auditConn = $pdo;
elseif (isset($conn) && $conn !== false) $auditConn = $conn;
elseif (isset($mysql_conn) && $mysql_conn instanceof mysqli) $auditConn = $mysql_conn;
elseif (isset($pg_conn) && $pg_conn instanceof PDO) $auditConn = $pg_conn;

// 2. 🔐 AUDIT LOG — LOGOUT (Only log if we have a user session and a connection)
if (isset($_SESSION['user_id']) && $auditConn) {
    logAudit(
        $auditConn,
        'LOGOUT',
        'Authentication',
        'User logged out of the system'
    );
}

// 3. Destroy session AFTER logging
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: login.php");
exit;