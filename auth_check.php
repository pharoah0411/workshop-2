<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/**
 * Role-based access control
 * Usage: requireRole('admin');
 */
function requireRole($requiredRole) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        http_response_code(403);
        die("<h3 style='color:red;'>❌ Access Denied: Admin only</h3>");
    }
}
