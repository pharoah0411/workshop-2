<?php
require_once "auth_check.php";
requireRole('admin');

include "connection.php";
include "header.php";

$all_patients = [];

/* =========================================================
   HELPER: check if patient is "in use" by prescriptions
   Returns count (0 means safe to delete)
========================================================= */
function patientUsageCount($db, $conn, $patient_id) {
    try {
        if ($db === "Postgres" && $conn instanceof PDO) {
            $stmt = $conn->prepare('SELECT COUNT(*) FROM prescription WHERE patient_id = ?');
            $stmt->execute([$patient_id]);
            return (int)$stmt->fetchColumn();
        }

        if ($db === "MySQL" && $conn instanceof mysqli) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM PRESCRIPTION WHERE PATIENT_ID = ?");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $stmt->bind_result($cnt);
            $stmt->fetch();
            $stmt->close();
            return (int)$cnt;
        }

        if ($db === "SQL Server" && $conn instanceof PDO) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM prescription WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            return (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
        return 0; // fail-safe
    }

    return 0;
}

/* =========================================================
   1) POSTGRES FETCH
========================================================= */
if (isset($pg_conn) && $pg_conn instanceof PDO) {
    try {
        $sql = "
            SELECT 
                p.patient_id,
                p.name AS full_name,
                p.gender,
                p.dob,
                p.ic_no,
                p.address
            FROM patient p
            ORDER BY p.patient_id ASC
        ";
        $stmt = $pg_conn->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['source'] = 'Postgres';
            $row['in_use'] = patientUsageCount("Postgres", $pg_conn, (int)$row['patient_id']);
            $all_patients[] = $row;
        }
    } catch (Exception $e) { }
}

/* =========================================================
   2) MYSQL FETCH
========================================================= */
if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
    try {
        $sql = "
            SELECT 
                PATIENT_ID as patient_id,
                NAME as full_name,
                GENDER as gender,
                DOB as dob,
                IC_NO as ic_no,
                ADDRESS as address
            FROM PATIENT
            ORDER BY PATIENT_ID ASC
        ";

        $result = $mysql_conn2->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['source'] = 'MySQL';
                $row['in_use'] = patientUsageCount("MySQL", $mysql_conn2, (int)$row['patient_id']);
                $all_patients[] = $row;
            }
        }
    } catch (Exception $e) { }
}

/* =========================================================
   3) SQL SERVER FETCH
========================================================= */
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $sql = "
            SELECT 
                patient_id,
                name AS full_name,
                gender,
                dob,
                ic_no,
                address
            FROM patient
            ORDER BY patient_id ASC
        ";
        $stmt = $pdo->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['source'] = 'SQL Server';
            $row['in_use'] = patientUsageCount("SQL Server", $pdo, (int)$row['patient_id']);
            $all_patients[] = $row;
        }
    } catch (Exception $e) { }
}
?>

<h1 style="font-size:48px; font-weight:800; margin-bottom:18px;">Patient List (Multi-Database)</h1>

<a href="add_patient.php"
   style="display:inline-block; padding:12px 18px; background:#28a745; color:white;
          border-radius:10px; text-decoration:none; font-weight:700;">
    + Add Patient
</a>

<br><br>

<table border="0" cellpadding="14" cellspacing="0"
       style="width:100%; background:white; border-radius:14px; overflow:hidden;">
    <tr style="background:linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color:white; font-size:18px;">
        <th style="text-align:left;">Source</th>
        <th style="text-align:left;">ID</th>
        <th style="text-align:left;">Full Name</th>
        <th style="text-align:left;">Gender</th>
        <th style="text-align:left;">DOB</th>
        <th style="text-align:left;">IC No</th>
        <th style="text-align:left;">Actions</th>
    </tr>

    <?php if (empty($all_patients)): ?>
        <tr>
            <td colspan="7" style="text-align:center; padding:22px;">
                No patients found in any database.
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($all_patients as $p): ?>
            <tr style="border-bottom:1px solid #eee; font-size:18px;">
                <td style="font-weight:800; color:#0b2f6d;">
                    <?= htmlspecialchars($p['source']) ?>
                </td>
                <td><?= (int)$p['patient_id'] ?></td>
                <td><?= htmlspecialchars($p['full_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($p['gender'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['dob'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['ic_no'] ?? '') ?></td>

                <td style="white-space:nowrap;">
                    <a href="edit_patient.php?id=<?= (int)$p['patient_id'] ?>&db=<?= urlencode($p['source']) ?>"
                       style="color:#0a58ff; font-weight:700; text-decoration:underline; margin-right:14px;">
                        Edit
                    </a>

                    <a href="patient_history.php?id=<?= (int)$p['patient_id'] ?>&db=<?= urlencode($p['source']) ?>"
                       style="color:#198754; font-weight:700; text-decoration:underline; margin-right:14px;">
                        History
                    </a>

                    <?php if ((int)$p['in_use'] > 0): ?>
                        <span style="display:inline-block; padding:6px 12px; background:#ffe6e6; color:#d40000;
                                     border-radius:999px; font-weight:800; font-size:15px;">
                            In Use (<?= (int)$p['in_use'] ?>)
                        </span>
                    <?php else: ?>
                        <a href="delete_patient.php?id=<?= (int)$p['patient_id'] ?>&db=<?= urlencode($p['source']) ?>"
                           onclick="return confirm('Delete this patient from <?= htmlspecialchars($p['source']) ?>?');"
                           style="color:#dc3545; font-weight:800; text-decoration:underline;">
                            Delete
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

</div>
</body>
</html>
