<?php
require_once "auth_check.php";
requireRole('admin');
require_once "connection.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['db'])) {
    die("<p style='color:red;'>Missing user ID or source.</p><a href='user_list.php'>Back</a>");
}

$user_id = (int) $_GET['id'];
$db = trim($_GET['db']); // "Postgres", "MySQL", "SQL Server"

try {

    /* ==========================
       POSTGRESQL DELETE
    =========================== */
    if ($db === "Postgres" && $pg_conn instanceof PDO) {

        $pg_conn->beginTransaction();

        // Optional: block deleting admin
        $roleStmt = $pg_conn->prepare('SELECT role FROM "user" WHERE user_id = :id');
        $roleStmt->execute([':id' => $user_id]);
        $role = $roleStmt->fetchColumn();

        if (!$role) throw new Exception("User not found in Postgres.");
        if ($role === 'admin') throw new Exception("Admin account cannot be deleted.");

        // ✅ delete child first (patient)
        $pg_conn->prepare('DELETE FROM patient WHERE user_id = :id')
                ->execute([':id' => $user_id]);

        // ✅ then delete user
        $pg_conn->prepare('DELETE FROM "user" WHERE user_id = :id')
                ->execute([':id' => $user_id]);

        $pg_conn->commit();
        header("Location: user_list.php?deleted=1");
        exit;
    }

    /* ==========================
   MYSQL DELETE
=========================== */
if ($db === "MySQL" && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {

    $mysql_conn2->begin_transaction();

    // Check if user exists + role
    $stmtRole = $mysql_conn2->prepare("SELECT ROLE FROM `USER` WHERE USER_ID = ?");
    $stmtRole->bind_param("i", $user_id);
    $stmtRole->execute();
    $stmtRole->bind_result($role);
    if (!$stmtRole->fetch()) {
        $stmtRole->close();
        throw new Exception("User not found in MySQL.");
    }
    $stmtRole->close();

    if (strtolower($role) === 'admin') {
        throw new Exception("Admin account cannot be deleted.");
    }

    // ✅ NEW: Check prescriptions referencing this pharmacist
    $stmtChk = $mysql_conn2->prepare("SELECT COUNT(*) FROM PRESCRIPTION WHERE PHARMACIST_ID = ?");
    $stmtChk->bind_param("i", $user_id);
    $stmtChk->execute();
    $stmtChk->bind_result($presCount);
    $stmtChk->fetch();
    $stmtChk->close();

    if ($presCount > 0) {
        throw new Exception("Cannot delete this user because they are referenced in PRESCRIPTION records ($presCount record(s)).");
    }

    // ✅ delete patient records first (if exist)
    $stmtP = $mysql_conn2->prepare("DELETE FROM PATIENT WHERE USER_ID = ?");
    $stmtP->bind_param("i", $user_id);
    $stmtP->execute();
    $stmtP->close();

    // ✅ now delete user
    $stmtU = $mysql_conn2->prepare("DELETE FROM `USER` WHERE USER_ID = ?");
    $stmtU->bind_param("i", $user_id);
    $stmtU->execute();
    $stmtU->close();

    $mysql_conn2->commit();
    header("Location: user_list.php?deleted=1");
    exit;
}


    /* ==========================
       SQL SERVER DELETE (PDO)
    =========================== */
    if ($db === "SQL Server" && isset($pdo) && $pdo instanceof PDO) {

        $pdo->beginTransaction();

        // Optional: block deleting admin
        $stmtRole = $pdo->prepare("SELECT role FROM [USER] WHERE user_id = ?");
        $stmtRole->execute([$user_id]);
        $role = $stmtRole->fetchColumn();

        if (!$role) throw new Exception("User not found in SQL Server.");
        if (strtolower($role) === 'admin') throw new Exception("Admin account cannot be deleted.");

        // ✅ delete child first (patient)
        $pdo->prepare("DELETE FROM patient WHERE user_id = ?")->execute([$user_id]);

        // ✅ then delete user
        $pdo->prepare("DELETE FROM [USER] WHERE user_id = ?")->execute([$user_id]);

        $pdo->commit();
        header("Location: user_list.php?deleted=1");
        exit;
    }

    throw new Exception("Invalid DB selected or connection not available.");

} catch (Exception $e) {

    // rollback if needed
    if ($db === "Postgres" && isset($pg_conn) && $pg_conn instanceof PDO && $pg_conn->inTransaction()) $pg_conn->rollBack();
    if ($db === "MySQL" && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) $mysql_conn2->rollback();
    if ($db === "SQL Server" && isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();

    echo "<p style='color:red;'>Error deleting user: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='user_list.php'>Back</a>";
}
