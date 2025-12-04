<?php
include "connectionSyameel.php";  // use your own file
include "header.php";             // brings in sidebar + css

// Count total users
$totalUsers = $conn->query("SELECT COUNT(*) FROM \"user\"")->fetchColumn();

// Count total admins
$totalAdmins = $conn->query("SELECT COUNT(*) FROM \"user\" WHERE role = 'admin'")->fetchColumn();

// Count total pharmacists
$totalPharmacists = $conn->query("SELECT COUNT(*) FROM \"user\" WHERE role = 'pharmacist'")->fetchColumn();

// Count total patients
$totalPatients = $conn->query("SELECT COUNT(*) FROM \"user\" WHERE role = 'patient'")->fetchColumn();
?>

<h1>Dashboard</h1>

<div class="card-container">

    <div class="card">
        <h3>Total Users</h3>
        <p><?= $totalUsers ?></p>
    </div>

    <div class="card">
        <h3>Admins</h3>
        <p><?= $totalAdmins ?></p>
    </div>

    <div class="card">
        <h3>Pharmacists</h3>
        <p><?= $totalPharmacists ?></p>
    </div>

    <div class="card">
        <h3>Patients</h3>
        <p><?= $totalPatients ?></p>
    </div>

</div>

</div> <!-- end content -->
</body>
</html>
