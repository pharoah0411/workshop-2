<?php
function logAudit($conn, $action, $module, $description)
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $userId   = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $role     = $_SESSION['role'];
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $sql = "
        INSERT INTO audit_trail
        (
            user_id,
            username,
            role,
            action,
            module,
            description,
            ip_address
        )
        VALUES
        (
            :user_id,
            :username,
            :role,
            :action,
            :module,
            :description,
            :ip
        )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':user_id'     => $userId,
        ':username'    => $username,
        ':role'        => $role,
        ':action'      => $action,
        ':module'      => $module,
        ':description' => $description,
        ':ip'          => $ip
    ]);
}
