<?php
function logAudit($conn, $action, $module, $description) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $userId   = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $role     = $_SESSION['role'];
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    // Use '?' placeholders to support MySQL, SQL Server, and Postgres
    $sql = "INSERT INTO audit_trail (user_id, username, role, action, module, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    if ($conn instanceof PDO) {
        // SQL Server / PostgreSQL
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId, $username, $role, $action, $module, $description, $ip]);
    } elseif ($conn instanceof mysqli) {
        // MySQL
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssss", $userId, $username, $role, $action, $module, $description, $ip);
        $stmt->execute();
    }
}
?>
