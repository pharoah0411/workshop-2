<?php
require_once "auth_check.php";
requireRole('admin');
require_once "connection.php";

if (!isset($_GET['id']) || !isset($_GET['db'])) {
    die("<p style='color:red;'>Missing patient ID or source.</p>");
}

$patient_id = (int) $_GET['id'];
$db = $_GET['db'];

try {

    /* =========================
       POSTGRES
    ========================= */
    if ($db === "Postgres") {

        $pg_conn->beginTransaction();

        // 1) get user_id
        $stmt = $pg_conn->prepare("SELECT user_id FROM patient WHERE patient_id = :id");
        $stmt->execute([':id' => $patient_id]);
        $user_id = $stmt->fetchColumn();

        // 2) delete medical history (if table exists)
        // (If you don't have medical_history in Postgres, comment this block)
        $pg_conn->prepare("DELETE FROM medical_history WHERE patient_id = :id")
                ->execute([':id' => $patient_id]);

        // 3) delete patient
        $pg_conn->prepare("DELETE FROM patient WHERE patient_id = :id")
                ->execute([':id' => $patient_id]);

        // 4) delete linked user (only if exists)
        if (!empty($user_id)) {
            $pg_conn->prepare('DELETE FROM "user" WHERE user_id = :uid')
                    ->execute([':uid' => $user_id]);
        }

        $pg_conn->commit();
    }

    /* =========================
       MYSQL  (FIX 1: PATIENT uppercase)
    ========================= */
    if ($db === "MySQL") {

        $mysql_conn2->begin_transaction();

        // 1) get user_id
        $stmt = $mysql_conn2->prepare("SELECT user_id FROM PATIENT WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $user_id = $row['user_id'] ?? null;
        $stmt->close();

        // 2) delete medical history if exists (MEDICAL_HISTORY)
        // If your MySQL table name is different, change it here.
        $stmt = $mysql_conn2->prepare("DELETE FROM MEDICAL_HISTORY WHERE patient_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $stmt->close();
        }

        // 3) delete patient
        $stmt = $mysql_conn2->prepare("DELETE FROM PATIENT WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $stmt->close();

        // 4) delete linked user (only if exists)
        if (!empty($user_id)) {
            $stmt = $mysql_conn2->prepare("DELETE FROM `USER` WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }

        $mysql_conn2->commit();
    }

    /* =========================
       SQL SERVER
    ========================= */
    if ($db === "SQL Server") {

        $pdo->beginTransaction();

        // 1) get user_id
        $stmt = $pdo->prepare("SELECT user_id FROM patient WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $user_id = $stmt->fetchColumn();

        // 2) delete medical history (if exists)
        $pdo->prepare("DELETE FROM medical_history WHERE patient_id = ?")->execute([$patient_id]);

        // 3) delete patient
        $pdo->prepare("DELETE FROM patient WHERE patient_id = ?")->execute([$patient_id]);

        // 4) delete linked user (only if exists)
        if (!empty($user_id)) {
            $pdo->prepare("DELETE FROM [USER] WHERE user_id = ?")->execute([$user_id]);
        }

        $pdo->commit();
    }

    header("Location: patient_list.php?deleted=1");
    exit;

} catch (Exception $e) {

    // Rollbacks
    if ($db === "Postgres" && isset($pg_conn) && $pg_conn->inTransaction()) $pg_conn->rollBack();
    if ($db === "MySQL" && isset($mysql_conn2)) $mysql_conn2->rollback();
    if ($db === "SQL Server" && isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();

    echo "<p style='color:red;'>❌ Delete failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='patient_list.php'>Back</a>";
}
