<?php
require_once "auth_check.php";   // ⬅ MUST BE FIRST
requireRole('admin');            // ⬅ BLOCK non-admins
require_once "connection.php";

// 1. Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID.");
}

$user_id = (int) $_GET['id'];

try {
    // 2. Start transaction (PostgreSQL)
    if ($pg_conn instanceof PDO) {
        $pg_conn->beginTransaction();

        // 3. OPTIONAL SAFETY CHECK: prevent deleting patient users
        $check = $pg_conn->prepare(
            'SELECT role FROM "user" WHERE user_id = :id'
        );
        $check->execute([':id' => $user_id]);
        $role = $check->fetchColumn();

        if (!$role) {
            throw new Exception("User not found.");
        }

        if ($role === 'patient') {
            throw new Exception("Patient users cannot be deleted here.");
        }

        // 4. Delete user
        $stmt = $pg_conn->prepare(
            'DELETE FROM "user" WHERE user_id = :id'
        );
        $stmt->execute([':id' => $user_id]);

        $pg_conn->commit();
    }

    // 5. Redirect back
    header("Location: user_list.php?deleted=1");
    exit;

} catch (Exception $e) {
    if ($pg_conn && $pg_conn->inTransaction()) {
        $pg_conn->rollBack();
    }

    echo "<p style='color:red;'>Error deleting user: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='user_list.php'>Back</a>";
}
