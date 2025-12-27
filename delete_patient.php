<?php
require_once "auth_check.php";   // ⬅ MUST BE FIRST
requireRole('admin');            // ⬅ BLOCK non-admins
require_once "connection.php";

// 1️⃣ Validate patient ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid patient ID.");
}

$patient_id = (int) $_GET['id'];

try {
    if (!$pg_conn instanceof PDO) {
        throw new Exception("PostgreSQL connection not available.");
    }

    // 2️⃣ Start transaction
    $pg_conn->beginTransaction();

    // 3️⃣ Get related user_id (important)
    $stmt = $pg_conn->prepare(
        "SELECT user_id FROM patient WHERE patient_id = :pid"
    );
    $stmt->execute([':pid' => $patient_id]);
    $user_id = $stmt->fetchColumn();

    if (!$user_id) {
        throw new Exception("Patient not found.");
    }

    // 4️⃣ Delete medical history first (FK dependency)
    $stmt = $pg_conn->prepare(
        "DELETE FROM medical_history WHERE patient_id = :pid"
    );
    $stmt->execute([':pid' => $patient_id]);

    // 5️⃣ Delete patient
    $stmt = $pg_conn->prepare(
        "DELETE FROM patient WHERE patient_id = :pid"
    );
    $stmt->execute([':pid' => $patient_id]);

    // 6️⃣ OPTIONAL: delete linked user (recommended)
    $stmt = $pg_conn->prepare(
        'DELETE FROM "user" WHERE user_id = :uid'
    );
    $stmt->execute([':uid' => $user_id]);

    // 7️⃣ Commit transaction
    $pg_conn->commit();

    // 8️⃣ Redirect back
    header("Location: patient_list.php?deleted=1");
    exit;

} catch (Exception $e) {
    if ($pg_conn && $pg_conn->inTransaction()) {
        $pg_conn->rollBack();
    }

    echo "<p style='color:red;'>Error deleting patient: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='patient_list.php'>Back to Patient List</a>";
}
