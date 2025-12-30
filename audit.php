<?php
/**
 * audit.php
 * Fixed to work with MySQL, PostgreSQL, and SQL Server.
 */

function logAudit($conn, $action, $module, $description) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $userId    = $_SESSION['user_id'];
    $username  = $_SESSION['username'];
    $role      = $_SESSION['role'];
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    // Cross-database compatible timestamp
    $createdAt = date('Y-m-d H:i:s');

    $sql = "INSERT INTO audit_trail (user_id, username, role, action, module, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    try {
        if ($conn instanceof PDO) {
            // PostgreSQL and SQL Server (PDO)
            $stmt = $conn->prepare($sql);
            $stmt->execute([$userId, $username, $role, $action, $module, $description, $ip, $createdAt]);
        } elseif ($conn instanceof mysqli) {
            // MySQL
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssss", $userId, $username, $role, $action, $module, $description, $ip, $createdAt);
            $stmt->execute();
        }
    } catch (Exception $e) {
        error_log("Audit Log Error: " . $e->getMessage());
    }
}
?>