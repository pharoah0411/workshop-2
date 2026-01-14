<?php
require_once "auth_check.php";
requireRole('admin');

require_once 'connection.php';

// Get user info for sidebar
$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Administrator';

// Default permissions structure
$defaultPermissions = [
    "admin" => [
        "dashboard" => true,
        "user_management" => true,
        "add_user" => true,
        "edit_user" => true,
        "delete_user" => true,
        "patient_management" => true,
        "add_patient" => true,
        "edit_patient" => true,
        "delete_patient" => true,
        "med_directory" => true,
        "add_medicine" => true,
        "edit_medicine" => true,
        "delete_medicine" => true,
        "prescription_dashboard" => true,
        "add_prescription" => true,
        "edit_prescription" => true,
        "sales_billing" => true,
        "reports" => true,
        "backup_restore" => true,
        "role_management" => true
    ],
    "pharmacist" => [
        "dashboard" => true,
        "user_management" => false,
        "add_user" => false,
        "edit_user" => false,
        "delete_user" => false,
        "patient_management" => true,
        "add_patient" => true,
        "edit_patient" => true,
        "delete_patient" => true,
        "med_directory" => true,
        "add_medicine" => true,
        "edit_medicine" => true,
        "delete_medicine" => true,
        "prescription_dashboard" => true,
        "add_prescription" => true,
        "edit_prescription" => true,
        "sales_billing" => true,
        "reports" => true,
        "backup_restore" => false,
        "role_management" => false
    ],
    "patient" => [
        "dashboard" => false,
        "patient_portal" => true,
        "view_history" => true
    ]
];

// Initialize permissions
$permissions = $defaultPermissions;
$message = "";

// Create permissions table if not exists (PostgreSQL)
if (isset($pg_conn) && $pg_conn instanceof PDO) {
    try {
        $pg_conn->exec('
            CREATE TABLE IF NOT EXISTS "role_permissions" (
                id SERIAL PRIMARY KEY,
                role_name VARCHAR(50) NOT NULL,
                permission_key VARCHAR(100) NOT NULL,
                is_allowed BOOLEAN DEFAULT false,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(role_name, permission_key)
            )
        ');
        
        // Check if we have initial data
        $check = $pg_conn->query("SELECT COUNT(*) FROM role_permissions");
        if ($check->fetchColumn() == 0) {
            // Insert default permissions
            $stmt = $pg_conn->prepare(
                'INSERT INTO "role_permissions" (role_name, permission_key, is_allowed) 
                 VALUES (:role, :key, :allowed)'
            );
            
            foreach ($defaultPermissions as $role => $perms) {
                foreach ($perms as $key => $allowed) {
                    $stmt->execute([
                        ':role' => $role,
                        ':key' => $key,
                        ':allowed' => $allowed
                    ]);
                }
            }
        }
    } catch (Exception $e) {}
}

// Create permissions table if not exists (MySQL)
if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
    try {
        $mysql_conn2->query('
            CREATE TABLE IF NOT EXISTS ROLE_PERMISSIONS (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_name VARCHAR(50) NOT NULL,
                permission_key VARCHAR(100) NOT NULL,
                is_allowed TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_role_permission (role_name, permission_key)
            )
        ');
        
        // Check if we have initial data
        $result = $mysql_conn2->query("SELECT COUNT(*) as count FROM ROLE_PERMISSIONS");
        $row = $result->fetch_assoc();
        if ($row['count'] == 0) {
            // Insert default permissions
            $stmt = $mysql_conn2->prepare(
                "INSERT INTO ROLE_PERMISSIONS (role_name, permission_key, is_allowed) 
                 VALUES (?, ?, ?)"
            );
            
            foreach ($defaultPermissions as $role => $perms) {
                foreach ($perms as $key => $allowed) {
                    $stmt->bind_param("ssi", $role, $key, $allowed);
                    $stmt->execute();
                }
            }
        }
    } catch (Exception $e) {}
}

// Load permissions from database (PostgreSQL)
if (isset($pg_conn) && $pg_conn instanceof PDO) {
    try {
        $stmt = $pg_conn->query('SELECT role_name, permission_key, is_allowed FROM role_permissions');
        $dbPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $permissions = [];
        foreach ($dbPermissions as $perm) {
            $permissions[$perm['role_name']][$perm['permission_key']] = (bool)$perm['is_allowed'];
        }
    } catch (Exception $e) {}
}

// Load permissions from database (MySQL)
elseif (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
    try {
        $result = $mysql_conn2->query("SELECT role_name, permission_key, is_allowed FROM ROLE_PERMISSIONS");
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[$row['role_name']][$row['permission_key']] = (bool)$row['is_allowed'];
        }
    } catch (Exception $e) {}
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Collect all permissions from form
    $formPermissions = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, '_') !== false) {
            list($role, $permKey) = explode('_', $key, 2);
            $formPermissions[$role][$permKey] = true;
        }
    }
    
    // Update database (PostgreSQL)
    if (isset($pg_conn) && $pg_conn instanceof PDO) {
        try {
            $pg_conn->beginTransaction();
            
            // Clear existing permissions
            $pg_conn->exec('DELETE FROM role_permissions');
            
            // Insert new permissions
            $stmt = $pg_conn->prepare(
                'INSERT INTO "role_permissions" (role_name, permission_key, is_allowed) 
                 VALUES (:role, :key, :allowed)'
            );
            
            foreach ($formPermissions as $role => $perms) {
                foreach ($perms as $key => $allowed) {
                    $stmt->execute([
                        ':role' => $role,
                        ':key' => $key,
                        ':allowed' => $allowed
                    ]);
                }
            }
            
            $pg_conn->commit();
            
            // Update local permissions
            $permissions = $formPermissions;
            $message = "success";
            
        } catch (Exception $e) {
            $pg_conn->rollBack();
            $message = "error";
        }
    }
    
    // Update database (MySQL)
    elseif (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        try {
            $mysql_conn2->begin_transaction();
            
            // Clear existing permissions
            $mysql_conn2->query("DELETE FROM ROLE_PERMISSIONS");
            
            // Insert new permissions
            $stmt = $mysql_conn2->prepare(
                "INSERT INTO ROLE_PERMISSIONS (role_name, permission_key, is_allowed) 
                 VALUES (?, ?, ?)"
            );
            
            foreach ($formPermissions as $role => $perms) {
                foreach ($perms as $key => $allowed) {
                    $stmt->bind_param("ssi", $role, $key, $allowed);
                    $stmt->execute();
                }
            }
            
            $mysql_conn2->commit();
            
            // Update local permissions
            $permissions = $formPermissions;
            $message = "success";
            
        } catch (Exception $e) {
            $mysql_conn2->rollback();
            $message = "error";
        }
    }
}

// Permission descriptions
$permDescriptions = [
    "dashboard" => ["Dashboard", "fas fa-tachometer-alt", "Access main dashboard"],
    "user_management" => ["User Management", "fas fa-users", "View user list and details"],
    "add_user" => ["Add User", "fas fa-user-plus", "Create new user accounts"],
    "edit_user" => ["Edit User", "fas fa-user-edit", "Modify existing user information"],
    "delete_user" => ["Delete User", "fas fa-user-times", "Remove user accounts"],
    "patient_management" => ["Patient Management", "fas fa-user-injured", "View patient list and details"],
    "add_patient" => ["Add Patient", "fas fa-user-plus", "Register new patients"],
    "edit_patient" => ["Edit Patient", "fas fa-user-edit", "Modify patient records"],
    "delete_patient" => ["Delete Patient", "fas fa-user-times", "Remove patient records"],
    "med_directory" => ["Medicine Inventory", "fas fa-pills", "View medicine stock and details"],
    "add_medicine" => ["Add Medicine", "fas fa-plus-circle", "Add new medicines to inventory"],
    "edit_medicine" => ["Edit Medicine", "fas fa-edit", "Modify medicine information"],
    "delete_medicine" => ["Delete Medicine", "fas fa-trash", "Remove medicines from inventory"],
    "prescription_dashboard" => ["Prescriptions", "fas fa-prescription", "View and manage prescriptions"],
    "add_prescription" => ["Add Prescription", "fas fa-file-medical", "Create new prescriptions"],
    "edit_prescription" => ["Edit Prescription", "fas fa-file-edit", "Modify existing prescriptions"],
    "sales_billing" => ["Sales & Billing", "fas fa-cash-register", "Process sales and generate bills"],
    "reports" => ["Reports", "fas fa-chart-bar", "View system reports and analytics"],
    "backup_restore" => ["Backup & Restore", "fas fa-database", "Perform database backup and restore"],
    "role_management" => ["Role Management", "fas fa-user-shield", "Manage roles and permissions"],
    "patient_portal" => ["Patient Portal", "fas fa-laptop-medical", "Access patient self-service portal"],
    "view_history" => ["View History", "fas fa-history", "View personal medical history"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management | PHARMACY SYSTEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* Medical Professional Color Scheme with Dark Blue */
        :root {
            --dark-blue: #1c4966;
            --blue-medium: #2a5d7a;
            --blue-light: #e3f2fd;
            --blue-accent: #4a90e2;
            --cream-white: #f8fafc;
            --soft-grey: #8a8a8a;
            --dark-grey: #2c3e50;
            --alert-red: #d9534f;
            --warning-orange: #f0ad4e;
            --success-green: #5cb85c;
            
            --sidebar-bg: var(--dark-blue);
            --sidebar-text: white;
            --main-bg: #f5f7fa;
            --card-bg: white;
            --border-color: #e1e8ed;
            --text-primary: var(--dark-grey);
            --text-secondary: var(--soft-grey);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Be Vietnam Pro", sans-serif;
            background: var(--main-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
            font-weight: 400;
            line-height: 1.5;
        }

        .dashboard-container {
            width: 100%;
            max-width: 1400px;
            height: 92vh;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(28, 73, 102, 0.1);
            overflow: hidden;
            display: flex;
        }

        /* Sidebar - Dark Blue */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--dark-blue) 0%, #143852 100%);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            padding: 25px 0;
        }

        .pharmacy-logo {
            text-align: center;
            padding: 0 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .pharmacy-logo h1 {
            font-size: 1.3em;
            font-weight: 600;
            color: white;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .pharmacy-logo p {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 300;
        }

        .user-profile {
            padding: 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, white, var(--blue-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 1.2em;
            border: 2px solid white;
        }

        .user-info {
            margin-left: 12px;
        }

        .user-name {
            font-weight: 500;
            font-size: 0.95em;
            margin-bottom: 3px;
        }

        .user-role {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.15);
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
        }

        /* Navigation Menu */
        .nav-menu {
            flex: 1;
            padding: 25px 0;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 25px;
            padding: 0 20px;
        }

        .nav-title {
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 12px;
            font-weight: 500;
        }

        .nav-links {
            list-style: none;
        }

        .nav-links li {
            margin-bottom: 6px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            border-left: 2px solid transparent;
            font-size: 0.9em;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--blue-accent);
        }

        .nav-links a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: white;
            font-weight: 500;
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            font-size: 1em;
        }

        .logout-btn {
            margin: 15px 20px 0;
            padding: 12px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: var(--alert-red);
            border-color: var(--alert-red);
            transform: translateY(-1px);
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header */
        .main-header {
            padding: 20px 35px;
            background: white;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            font-size: 1.4em;
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .header-title p {
            color: var(--text-secondary);
            font-size: 0.9em;
            font-weight: 300;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Content Area */
        .content-wrapper {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: var(--main-bg);
        }

        /* Management Card */
        .management-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .card-header {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 20px;
        }

        .card-header h2 {
            font-size: 1.3em;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Database Status */
        .database-status {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .status-item {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-online {
            background: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }

        .status-offline {
            background: rgba(211, 47, 47, 0.1);
            color: #d32f2f;
            border: 1px solid rgba(211, 47, 47, 0.2);
        }

        /* Messages */
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Buttons */
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95em;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--dark-blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--blue-medium);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(28, 73, 102, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--dark-blue);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: var(--dark-blue);
        }

        /* Permissions Table */
        .permissions-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .permissions-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-grey);
            border-bottom: 2px solid #e9ecef;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .permissions-table td {
            padding: 15px;
            color: var(--text-primary);
            border-bottom: 1px solid #f1f3f4;
        }

        .permissions-table tr:hover {
            background: #f8f9fa;
        }

        .permissions-table tr:last-child td {
            border-bottom: none;
        }

        .table-header {
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
            color: white;
        }

        /* Permission row styling */
        .permission-row td:first-child {
            width: 35%;
            vertical-align: top;
        }

        .permission-row:hover {
            background: #f8f9fa;
        }

        .permission-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .permission-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--blue-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-blue);
            font-size: 1.1em;
        }

        .permission-text h4 {
            margin: 0;
            font-size: 1em;
            color: var(--dark-grey);
            font-weight: 600;
        }

        .permission-text p {
            margin: 4px 0 0;
            font-size: 0.85em;
            color: var(--text-secondary);
        }

        /* Checkbox styling */
        .checkbox-cell {
            text-align: center;
            width: 15%;
        }

        .checkbox-container {
            display: inline-block;
            position: relative;
            width: 50px;
            height: 26px;
        }

        .checkbox-container input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .checkbox-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .checkbox-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .checkbox-slider {
            background-color: var(--success-green);
        }

        input:checked + .checkbox-slider:before {
            transform: translateX(24px);
        }

        /* Role headers */
        .role-header {
            text-align: center;
            padding-bottom: 10px;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
        }

        .role-admin {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
        }

        .role-pharmacist {
            background: linear-gradient(135deg, #4ecdc4, #44a08d);
            color: white;
        }

        .role-patient {
            background: linear-gradient(135deg, #a8e6cf, #8dd7a5);
            color: white;
        }

        /* Form actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .dashboard-container {
                height: auto;
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .nav-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                padding: 15px;
            }
            
            .nav-section {
                flex: 1;
                min-width: 200px;
                margin-bottom: 15px;
            }
            
            .main-content {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 20px;
            }
            
            .main-header {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .permissions-table {
                display: block;
                overflow-x: auto;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .permission-info {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                padding: 15px 0;
            }
            
            .pharmacy-logo h1 {
                font-size: 1.1em;
            }
            
            .user-profile {
                padding: 15px;
            }
            
            .content-wrapper {
                padding: 15px;
            }
            
            .database-status {
                flex-direction: column;
                align-items: center;
            }
            
            .checkbox-cell {
                width: 20%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="pharmacy-logo">
                <h1><i class="fas fa-pills"></i> PHARMACY SYSTEM</h1>
                <p>Professional Healthcare Management</p>
            </div>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($username, 0, 2)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div>
                </div>
            </div>

            <nav class="nav-menu">
                <div class="nav-section">
                    <div class="nav-title">NAVIGATION</div>
                    <ul class="nav-links">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt nav-icon"></i>Dashboard</a></li>
                        <li><a href="medDirectory.php"><i class="fas fa-pills nav-icon"></i>Medicine Inventory</a></li>
                        <li><a href="prescriptionDashboard.php"><i class="fas fa-prescription nav-icon"></i>Prescriptions</a></li>
                        <li><a href="Sales_Billing.php"><i class="fas fa-cash-register nav-icon"></i>Sales & Billing</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-title">ADMINISTRATION</div>
                    <ul class="nav-links">
                        <li><a href="user_management.php"><i class="fas fa-users nav-icon"></i>User Management</a></li>
                        <li><a href="patient_management.php"><i class="fas fa-user-injured nav-icon"></i>Patient Management</a></li>
                        <li><a href="reports.php"><i class="fas fa-chart-bar nav-icon"></i>Reports</a></li>
                        <li><a href="backup.php"><i class="fas fa-database nav-icon"></i>Backup & Restore</a></li>
                        <li><a href="role_management.php" class="active"><i class="fas fa-user-shield nav-icon"></i>Role Management</a></li>
                    </ul>
                </div>
            </nav>

            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </button>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-title">
                    <h1>Role & Permission Management</h1>
                    <p>Configure access permissions for different user roles</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Database Status -->
                <div class="database-status">
                    <span class="status-item <?php echo (isset($pg_conn) && $pg_conn instanceof PDO) ? 'status-online' : 'status-offline'; ?>">
                        <i class="fas fa-database"></i> PostgreSQL: <?php echo (isset($pg_conn) && $pg_conn instanceof PDO) ? 'Connected' : 'Offline'; ?>
                    </span>
                    <span class="status-item <?php echo (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? 'status-online' : 'status-offline'; ?>">
                        <i class="fas fa-database"></i> MySQL: <?php echo (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? 'Connected' : 'Offline'; ?>
                    </span>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($message === "success"): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>Success!</strong> Permissions have been updated successfully.
                    </div>
                <?php elseif ($message === "error"): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Error!</strong> Failed to update permissions. Please try again.
                    </div>
                <?php endif; ?>

                <div class="management-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-shield"></i> Permission Configuration</h2>
                    </div>

                    <div style="padding: 20px;">
                        <form method="POST">
                            <table class="permissions-table">
                                <thead>
                                    <tr class="table-header">
                                        <th>Permission</th>
                                        <th class="role-header">
                                            <span class="role-badge role-admin">
                                                <i class="fas fa-crown"></i> Administrator
                                            </span>
                                        </th>
                                        <th class="role-header">
                                            <span class="role-badge role-pharmacist">
                                                <i class="fas fa-user-md"></i> Pharmacist
                                            </span>
                                        </th>
                                        <th class="role-header">
                                            <span class="role-badge role-patient">
                                                <i class="fas fa-user-injured"></i> Patient
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($permDescriptions as $key => $desc): 
                                        list($title, $icon, $helpText) = $desc;
                                        
                                        // Check if this permission exists for each role
                                        $adminChecked = isset($permissions['admin'][$key]) ? $permissions['admin'][$key] : false;
                                        $pharmacistChecked = isset($permissions['pharmacist'][$key]) ? $permissions['pharmacist'][$key] : false;
                                        $patientChecked = isset($permissions['patient'][$key]) ? $permissions['patient'][$key] : false;
                                    ?>
                                    <tr class="permission-row">
                                        <td>
                                            <div class="permission-info">
                                                <div class="permission-icon">
                                                    <i class="<?php echo $icon; ?>"></i>
                                                </div>
                                                <div class="permission-text">
                                                    <h4><?php echo $title; ?></h4>
                                                    <p><?php echo $helpText; ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="checkbox-cell">
                                            <label class="checkbox-container">
                                                <input type="checkbox" 
                                                       name="admin_<?php echo $key; ?>" 
                                                       <?php echo $adminChecked ? 'checked' : ''; ?>>
                                                <span class="checkbox-slider"></span>
                                            </label>
                                        </td>
                                        <td class="checkbox-cell">
                                            <label class="checkbox-container">
                                                <input type="checkbox" 
                                                       name="pharmacist_<?php echo $key; ?>" 
                                                       <?php echo $pharmacistChecked ? 'checked' : ''; ?>>
                                                <span class="checkbox-slider"></span>
                                            </label>
                                        </td>
                                        <td class="checkbox-cell">
                                            <label class="checkbox-container">
                                                <input type="checkbox" 
                                                       name="patient_<?php echo $key; ?>" 
                                                       <?php echo $patientChecked ? 'checked' : ''; ?>>
                                                <span class="checkbox-slider"></span>
                                            </label>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Permissions
                                </button>
                                <button type="button" onclick="resetToDefaults()" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset to Defaults
                                </button>
                                <a href="role_management.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Information Card -->
                <div class="management-card" style="margin-top: 30px;">
                    <div class="card-header">
                        <h2><i class="fas fa-info-circle"></i> Permission Information</h2>
                    </div>
                    <div style="padding: 20px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                            <div>
                                <h3 style="color: var(--dark-blue); margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                                    <span class="role-badge role-admin" style="font-size: 0.8em;">
                                        <i class="fas fa-crown"></i> Administrator
                                    </span>
                                </h3>
                                <p style="color: var(--text-secondary); font-size: 0.9em;">
                                    Full system access including user management, role configuration, and database administration.
                                </p>
                            </div>
                            <div>
                                <h3 style="color: var(--dark-blue); margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                                    <span class="role-badge role-pharmacist" style="font-size: 0.8em;">
                                        <i class="fas fa-user-md"></i> Pharmacist
                                    </span>
                                </h3>
                                <p style="color: var(--text-secondary); font-size: 0.9em;">
                                    Operational access including patient management, prescriptions, inventory, and billing.
                                </p>
                            </div>
                            <div>
                                <h3 style="color: var(--dark-blue); margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                                    <span class="role-badge role-patient" style="font-size: 0.8em;">
                                        <i class="fas fa-user-injured"></i> Patient
                                    </span>
                                </h3>
                                <p style="color: var(--text-secondary); font-size: 0.9em;">
                                    Limited access to personal information, prescription history, and patient portal.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Set active navigation
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });

        // Reset to default permissions
        function resetToDefaults() {
            if (confirm('Are you sure you want to reset all permissions to default values?')) {
                // Default permissions mapping
                const defaultPermissions = {
                    // Admin permissions
                    'admin_dashboard': true,
                    'admin_user_management': true,
                    'admin_add_user': true,
                    'admin_edit_user': true,
                    'admin_delete_user': true,
                    'admin_patient_management': true,
                    'admin_add_patient': true,
                    'admin_edit_patient': true,
                    'admin_delete_patient': true,
                    'admin_med_directory': true,
                    'admin_add_medicine': true,
                    'admin_edit_medicine': true,
                    'admin_delete_medicine': true,
                    'admin_prescription_dashboard': true,
                    'admin_add_prescription': true,
                    'admin_edit_prescription': true,
                    'admin_sales_billing': true,
                    'admin_reports': true,
                    'admin_backup_restore': true,
                    'admin_role_management': true,
                    
                    // Pharmacist permissions
                    'pharmacist_dashboard': true,
                    'pharmacist_user_management': false,
                    'pharmacist_add_user': false,
                    'pharmacist_edit_user': false,
                    'pharmacist_delete_user': false,
                    'pharmacist_patient_management': true,
                    'pharmacist_add_patient': true,
                    'pharmacist_edit_patient': true,
                    'pharmacist_delete_patient': true,
                    'pharmacist_med_directory': true,
                    'pharmacist_add_medicine': true,
                    'pharmacist_edit_medicine': true,
                    'pharmacist_delete_medicine': true,
                    'pharmacist_prescription_dashboard': true,
                    'pharmacist_add_prescription': true,
                    'pharmacist_edit_prescription': true,
                    'pharmacist_sales_billing': true,
                    'pharmacist_reports': true,
                    'pharmacist_backup_restore': false,
                    'pharmacist_role_management': false,
                    
                    // Patient permissions
                    'patient_dashboard': false,
                    'patient_patient_portal': true,
                    'patient_view_history': true
                };
                
                // Reset all checkboxes
                Object.keys(defaultPermissions).forEach(name => {
                    const checkbox = document.querySelector(`input[name="${name}"]`);
                    if (checkbox) {
                        checkbox.checked = defaultPermissions[name];
                    }
                });
                
                alert('Permissions reset to default values. Click "Save Permissions" to apply.');
            }
        }
        
        // Add hover effect to permission rows
        document.querySelectorAll('.permission-row').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
                this.style.transition = 'all 0.2s ease';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
    </script>
</body>
</html>