<?php include "header.php"; ?>

<style>
    .module-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: transform 0.2s ease;
    }
    .module-card:hover {
        transform: translateY(-5px);
    }

    .module-header h2 {
        color: #0b2f6d;
        font-weight: bold;
        margin-bottom: 10px;
    }

    .module-content ul {
        padding-left: 20px;
        font-size: 18px;
    }

    .module-content ul li {
        margin-bottom: 10px;
    }

    .module-content a {
        text-decoration: none;
        color: #0b2f6d;
        font-weight: 600;
    }

    .module-content a:hover {
        text-decoration: underline;
    }

    h1 {
        color: #0b2f6d;
        margin-bottom: 25px;
        font-size: 32px;
        font-weight: bold;
    }
</style>

<div style="padding: 20px;">

    <h1>👤 User Management Dashboard</h1>

    <!-- USER ACCOUNTS MODULE -->
    <div class="module-card">
        <div class="module-header">
            <h2>🧑‍💼 User Accounts</h2>
        </div>
        <div class="module-content">
            <ul>
                <li><a href="user_list.php">View All Users</a></li>
                <li><a href="add_user.php">Add New User</a></li>
            </ul>
        </div>
    </div>

    <!-- PATIENT MANAGEMENT MODULE -->
    <div class="module-card">
        <div class="module-header">
            <h2>🩺 Patient Management</h2>
        </div>
        <div class="module-content">
            <ul>
                <li><a href="patient_list.php">View Patient List</a></li>
                <li><a href="add_patient.php">Add New Patient</a></li>
            </ul>
        </div>
    </div>

    <!-- ROLE PERMISSION MODULE -->
    <div class="module-card">
        <div class="module-header">
            <h2>🔐 Role & Permission Control</h2>
        </div>
        <div class="module-content">
            <ul>
                <li><a href="permissions.php">Manage Role Permissions</a></li>
            </ul>
        </div>
    </div>


</div>

</body>
</html>
