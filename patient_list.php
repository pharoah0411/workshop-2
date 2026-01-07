<?php
include "connection.php";
include "header.php";

$all_patients = [];

// 1. Fetch from PostgreSQL
if (isset($pg_conn)) {
    try {
        // We use COALESCE just in case some old records still have names in the user table
        $sql = "SELECT p.patient_id, COALESCE(p.name, u.name) AS full_name, p.gender, p.dob, p.ic_no, p.address 
                FROM patient p 
                LEFT JOIN \"user\" u ON p.user_id = u.user_id 
                ORDER BY p.patient_id ASC";
        $stmt = $pg_conn->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['source'] = 'Postgres';
            $all_patients[] = $row;
        }
    } catch (Exception $e) { /* Connection failed or table missing */ }
}

// 2. Fetch from MySQL
if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
    try {
        $sql = "SELECT 
                    p.PATIENT_ID AS patient_id,
                    p.NAME AS full_name,
                    p.GENDER AS gender,
                    p.DOB AS dob,
                    p.IC_NO AS ic_no,
                    p.ADDRESS AS address
                FROM PATIENT p
                ORDER BY p.PATIENT_ID ASC";

        $result = $mysql_conn2->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['source'] = 'MySQL';
                $all_patients[] = $row;
            }
        } else {
            // optional debug:
            // echo "MySQL Error: " . $mysql_conn2->error;
        }
    } catch (Exception $e) {
        // optional debug:
        // echo "MySQL Exception: " . $e->getMessage();
    }
}


// 3. Fetch from SQL Server
if (isset($pdo)) {
    try {
        $sql = "SELECT p.patient_id, p.name AS full_name, p.gender, p.dob, p.ic_no, p.address 
                FROM patient p 
                ORDER BY p.patient_id ASC";
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['source'] = 'SQL Server';
            $all_patients[] = $row;
        }
    } catch (Exception $e) { }
}
?>

<h1>Patient List (Multi-Database)</h1>

<a href="add_patient.php" style="padding: 10px 15px; background: #28a745; color: white; border-radius: 5px; text-decoration: none;">
    + Add Patient
</a>

<br><br>

<table border="0" cellpadding="10" cellspacing="0" style="width: 100%; background:white; border-radius:10px;">
    <tr style="background:linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color:white;">
        <th>Source</th>
        <th>ID</th>
        <th>Full Name</th>
        <th>Gender</th>
        <th>DOB</th>
        <th>IC No</th>
        <th>Actions</th>
    </tr>

    <?php if (empty($all_patients)): ?>
        <tr><td colspan="7" style="text-align:center;">No patients found in any database.</td></tr>
    <?php else: ?>
        <?php foreach($all_patients as $p): ?>
        <tr style="border-bottom:1px solid #ccc;">
            <td style="font-size: 0.8em; color: #666;"><strong><?= $p['source'] ?></strong></td>
            <td><?= $p['patient_id'] ?></td>
            <td><?= htmlspecialchars($p['full_name'] ?? 'N/A') ?></td>
            <td><?= $p['gender'] ?></td>
            <td><?= $p['dob'] ?></td>
            <td><?= htmlspecialchars($p['ic_no'] ?? '') ?></td>

            <td>
                <a href="edit_patient.php?id=<?= $p['patient_id'] ?>&db=<?= $p['source'] ?>" 
                   style="color:blue; margin-right:10px;">Edit</a>

                <a href="patient_history.php?id=<?= $p['patient_id'] ?>&db=<?= $p['source'] ?>" 
                   style="color:green; margin-right:10px;">History</a>

                <a href="delete_patient.php?id=<?= $p['patient_id'] ?>&db=<?= $p['source'] ?>" 
                   onclick="return confirm('Delete this patient from <?= $p['source'] ?>?');"
                   style="color:red;">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

</div> </body>
</html>