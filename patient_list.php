<?php
require_once "auth_check.php";
requireRole('admin');

require_once 'connection.php';

// Get user info for sidebar
$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Administrator';

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
   3) SQL SERVER FETCH - FIXED: Use $pdo_sqlsrv
========================================================= */
if (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) {
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
        $stmt = $pdo_sqlsrv->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['source'] = 'SQL Server';
            $row['in_use'] = patientUsageCount("SQL Server", $pdo_sqlsrv, (int)$row['patient_id']);
            $all_patients[] = $row;
        }
    } catch (Exception $e) { }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management | PHARMACY SYSTEM</title>
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.9em;
        }

        .btn-primary {
            background: var(--dark-blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--blue-medium);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(28, 73, 102, 0.2);
        }

        .btn-success {
            background: var(--success-green);
            color: white;
        }

        .btn-success:hover {
            background: #4cae4c;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(92, 184, 92, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--dark-blue);
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: var(--dark-blue);
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

        /* Patient Table */
        .patient-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .patient-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-grey);
            border-bottom: 2px solid #e9ecef;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .patient-table td {
            padding: 15px;
            color: var(--text-primary);
            border-bottom: 1px solid #f1f3f4;
        }

        .patient-table tr:hover {
            background: #f8f9fa;
        }

        .patient-table tr:last-child td {
            border-bottom: none;
        }

        .table-header {
            background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
            color: white;
        }

        /* Source Column */
        .source {
            font-weight: 700;
            color: #0b2f6d;
            font-size: 0.9em;
        }

        /* Action links */
        .action-link {
            font-weight: 600;
            text-decoration: none;
            margin-right: 10px;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .link-edit {
            color: #0056d2;
        }
        .link-edit:hover { text-decoration: underline; }

        .link-history {
            color: #198754;
        }
        .link-history:hover { text-decoration: underline; }

        .link-delete {
            color: #dc3545;
            font-weight: 700;
        }
        .link-delete:hover { text-decoration: underline; }

        /* Badge */
        .badge-inuse {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            background: #ffe6e6;
            color: #d40000;
            font-weight: 700;
            font-size: 0.8em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Add Patient Button */
        .btn-add {
            display: inline-block;
            padding: 10px 16px;
            background: #28a745;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-add:hover {
            background: #218838;
            transform: translateY(-1px);
        }

        /* Gender Badge */
        .gender-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .gender-male {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }

        .gender-female {
            background: #fce4ec;
            color: #c2185b;
            border: 1px solid #f8bbd9;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }

        .stat-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .stat-card h3 {
            font-size: 2em;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .stat-card p {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9em;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4em;
            color: var(--blue-light);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: var(--dark-grey);
            margin-bottom: 10px;
            font-size: 1.3em;
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
            
            .patient-table {
                display: block;
                overflow-x: auto;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-link {
                margin-right: 5px;
                margin-bottom: 5px;
                display: block;
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
            
            .source, .action-link {
                font-size: 0.8em;
            }
            
            .database-status {
                flex-direction: column;
                align-items: center;
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
                        <li><a href="patient_management.php" class="active"><i class="fas fa-user-injured nav-icon"></i>Patient Management</a></li>
                        <li><a href="reports.php"><i class="fas fa-chart-bar nav-icon"></i>Reports</a></li>
                        <li><a href="backup.php"><i class="fas fa-database nav-icon"></i>Backup & Restore</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-title">ACCOUNT</div>
                    <ul class="nav-links">
                        <li><a href="profile.php"><i class="fas fa-user-cog nav-icon"></i>Profile Settings</a></li>
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
                    <h1>Patient Management</h1>
                    <p>Manage patient records across all databases</p>
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
                    <span class="status-item <?php echo (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? 'status-online' : 'status-offline'; ?>">
                        <i class="fas fa-database"></i> SQL Server: <?php echo (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? 'Connected' : 'Offline'; ?>
                    </span>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="add_patient.php" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> Add Patient
                    </a>
                </div>

                <!-- Patient Table -->
                <table class="patient-table">
                    <tr class="table-header">
                        <th>Database</th>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>IC Number</th>
                        <th style="width:200px;">Actions</th>
                    </tr>

                    <?php if (empty($all_patients)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px;">
                                <div class="empty-state">
                                    <i class="fas fa-user-injured"></i>
                                    <h3>No Patients Found</h3>
                                    <p>No patient records were found in any of the connected databases.</p>
                                    <a href="add_patient.php" class="btn btn-primary" style="margin-top: 15px; display: inline-block;">
                                        <i class="fas fa-user-plus"></i> Add Your First Patient
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($all_patients as $p): ?>
                            <tr>
                                <td class="source">
                                    <i class="fas fa-database"></i> <?php echo htmlspecialchars($p['source']); ?>
                                </td>
                                <td><strong>#<?php echo (int)$p['patient_id']; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($p['full_name'] ?? 'N/A'); ?></strong><br>
                                    <small style="color: var(--text-secondary); font-size: 0.85em;">
                                        <?php echo htmlspecialchars($p['address'] ?? ''); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if (!empty($p['gender'])): ?>
                                        <?php $genderClass = strtolower($p['gender']) === 'female' ? 'gender-female' : 'gender-male'; ?>
                                        <span class="gender-badge <?php echo $genderClass; ?>">
                                            <i class="fas fa-<?php echo strtolower($p['gender']) === 'female' ? 'venus' : 'mars'; ?>"></i>
                                            <?php echo htmlspecialchars($p['gender']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['dob'])): ?>
                                        <?php 
                                            $dob = date('d M Y', strtotime($p['dob']));
                                            $age = date_diff(date_create($p['dob']), date_create('today'))->y;
                                        ?>
                                        <?php echo htmlspecialchars($dob); ?><br>
                                        <small style="color: var(--text-secondary); font-size: 0.85em;">
                                            Age: <?php echo $age; ?> years
                                        </small>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['ic_no'])): ?>
                                        <code style="background: var(--blue-light); padding: 4px 8px; border-radius: 4px; font-family: monospace;">
                                            <?php echo htmlspecialchars($p['ic_no']); ?>
                                        </code>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit_patient.php?id=<?php echo (int)$p['patient_id']; ?>&db=<?php echo urlencode($p['source']); ?>"
                                       class="action-link link-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>

                                    <a href="patient_history.php?id=<?php echo (int)$p['patient_id']; ?>&db=<?php echo urlencode($p['source']); ?>"
                                       class="action-link link-history">
                                        <i class="fas fa-history"></i> History
                                    </a>

                                    <?php if ((int)$p['in_use'] > 0): ?>
                                        <span class="badge-inuse">
                                            <i class="fas fa-lock"></i> In Use (<?php echo (int)$p['in_use']; ?>)
                                        </span>
                                    <?php else: ?>
                                        <a href="delete_patient.php?id=<?php echo (int)$p['patient_id']; ?>&db=<?php echo urlencode($p['source']); ?>"
                                           onclick="return confirm('Delete this patient from <?php echo htmlspecialchars($p['source']); ?> database? This action cannot be undone.');"
                                           class="action-link link-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>

                <!-- Statistics Section -->
                <?php if (!empty($all_patients)): ?>
                <div class="stats-grid">
                    <div class="stat-card" style="background: var(--blue-light); border-color: #e3f2fd;">
                        <h3 style="color: var(--dark-blue);"><?php echo count($all_patients); ?></h3>
                        <p>Total Patients</p>
                    </div>
                    <div class="stat-card" style="background: #e8f5e9; border-color: #c8e6c9;">
                        <h3 style="color: #2e7d32;">
                            <?php echo count(array_filter($all_patients, fn($p) => strtolower($p['gender'] ?? '') === 'male')); ?>
                        </h3>
                        <p>Male Patients</p>
                    </div>
                    <div class="stat-card" style="background: #fce4ec; border-color: #f8bbd9;">
                        <h3 style="color: #c2185b;">
                            <?php echo count(array_filter($all_patients, fn($p) => strtolower($p['gender'] ?? '') === 'female')); ?>
                        </h3>
                        <p>Female Patients</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Add hover effects to table rows
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.patient-table tbody tr');
            tableRows.forEach(row => {
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

            // Set active navigation
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>