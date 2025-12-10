<?php
include "connection.php";
include "header.php";

// Fetch patients + join with user table
$sql = "
SELECT 
    p.patient_id,
    u.name,
    u.email,
    u.phone,
    p.gender,
    p.dob,
    p.ic_no,
    p.address,
    (
        SELECT COUNT(*) 
        FROM medical_history mh 
        WHERE mh.patient_id = p.patient_id
    ) AS history_count
FROM patient p
JOIN \"user\" u ON p.user_id = u.user_id
ORDER BY p.patient_id ASC
";

$stmt = $conn->query($sql);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Patient List</h1>

<a href="add_patient.php" 
    style="padding: 10px 15px; background: #28a745; color: white; 
           border-radius: 5px; text-decoration: none;">
    + Add Patient
</a>

<br><br>

<table border="0" cellpadding="10" cellspacing="0" 
       style="width: 100%; background:white; border-radius:10px;">
    
    <tr style="background:#0b2f6d; color:white;">
        <th>ID</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Gender</th>
        <th>DOB</th>
        <th>History</th>
        <th>Actions</th>
    </tr>

    <?php foreach($patients as $p): ?>
    <tr style="border-bottom:1px solid #ccc;">
        <td><?= $p['patient_id'] ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td><?= htmlspecialchars($p['email']) ?></td>
        <td><?= htmlspecialchars($p['phone']) ?></td>
        <td><?= $p['gender'] ?></td>
        <td><?= $p['dob'] ?></td>
        <td><?= $p['history_count'] ?> record(s)</td>

        <td>
            <a href="edit_patient.php?id=<?= $p['patient_id'] ?>" 
               style="color:blue; margin-right:10px;">Edit</a>

            <a href="patient_history.php?id=<?= $p['patient_id'] ?>" 
               style="color:green; margin-right:10px;">History</a>

            <a href="delete_patient.php?id=<?= $p['patient_id'] ?>" 
               onclick="return confirm('Delete this patient?');"
               style="color:red;">Delete</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

</div> <!-- end content -->
</body>
</html>
