<?php
session_start();
require_once 'connection.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Force password reset check
if (!empty($_SESSION['force_reset'])) {
    header("Location: reset_password.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Pharmacist';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$source = $_GET['source'] ?? 'MySQL'; 

$prescription = null;
$details = [];

if ($id > 0) {
    try {
        $conn = null;
        $db_type = ''; 
        
        // Use explicit aliases to ensure columns don't collide and are easily identified
        $sql = "SELECT pr.*, 
                       p.NAME AS PATIENT_NAME, p.IC_NO AS PATIENT_IC, p.ADDRESS AS PATIENT_ADDR,
                       u.NAME AS PHARMACIST_NAME
                FROM PRESCRIPTION pr
                JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
                LEFT JOIN {USER_TABLE} u ON pr.PHARMACIST_ID = u.USER_ID
                WHERE pr.PRESCRIPTION_ID = ?";

        if ($source === 'MySQL' && isset($mysql_conn2)) {
            $conn = $mysql_conn2;
            $db_type = 'mysqli';
            $sql = str_replace('{USER_TABLE}', '`USER`', $sql);
        } elseif ($source === 'Postgres' && isset($pg_conn)) {
            $conn = $pg_conn;
            $db_type = 'pdo';
            $sql = str_replace('{USER_TABLE}', '"user"', $sql);
        } elseif ($source === 'SQLServer' && isset($pdo_sqlsrv)) {
            $conn = $pdo_sqlsrv;
            $db_type = 'pdo';
            $sql = str_replace('{USER_TABLE}', '[USER]', $sql);
        }

        if ($conn) {
            // 1. Fetch Header
            if ($db_type === 'mysqli') {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $prescription = $stmt->get_result()->fetch_assoc();
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($prescription) {
                // Force all keys to UPPERCASE for consistency across all DB types
                $prescription = array_change_key_case($prescription, CASE_UPPER);

                // 2. Fetch Details
                $d_sql = "SELECT pd.*, m.NAME AS MED_NAME 
                          FROM PRESCRIPTION_DETAIL pd 
                          JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID 
                          WHERE pd.PRESCRIPTION_ID = ?";
                
                if ($db_type === 'mysqli') {
                    $stmtD = $conn->prepare($d_sql);
                    $stmtD->bind_param("i", $id);
                    $stmtD->execute();
                    $resD = $stmtD->get_result();
                    while ($row = $resD->fetch_assoc()) {
                        $details[] = array_change_key_case($row, CASE_UPPER);
                    }
                } else {
                    $stmtD = $conn->prepare($d_sql);
                    $stmtD->execute([$id]);
                    while ($row = $stmtD->fetch(PDO::FETCH_ASSOC)) {
                        $details[] = array_change_key_case($row, CASE_UPPER);
                    }
                }
            }
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Prescription | PHARMACY SYSTEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* Medical Professional Color Scheme */
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
            font-weight: 400;
            line-height: 1.5;
        }

        /* Sidebar - Match Dashboard Colors */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1c4966 0%, #143852 100%);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 25px 0;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
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
            background: linear-gradient(135deg, white, #e3f2fd);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1c4966;
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
            margin-left: 260px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #f5f7fa;
        }

        /* Header */
        .main-header {
            padding: 20px 30px;
            background: white;
            border-bottom: 1px solid #e4e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .header-title h1 {
            font-size: 1.4em;
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .header-title p {
            color: #6c757d;
            font-size: 0.85em;
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
        }

        /* Prescription Card */
        .prescription-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.5em;
            font-weight: 600;
            margin: 0;
        }

        .source-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
            margin-left: 15px;
        }

        .status-PENDING {
            background: #fff3e0;
            color: #e65100;
        }

        .status-COMPLETED {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .card-body {
            padding: 30px;
        }

        /* Patient Info */
        .patient-info {
            background: var(--blue-light);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #e3f2fd;
        }

        .patient-info h3 {
            color: var(--dark-blue);
            margin-bottom: 15px;
            font-size: 1.2em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .detail-item {
            margin-bottom: 10px;
        }

        .detail-label {
            font-size: 0.85em;
            color: var(--soft-grey);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 1.1em;
            color: var(--dark-grey);
            font-weight: 600;
        }

        /* Prescription Details */
        .prescription-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        /* Medicine Table */
        .medicine-section {
            margin-top: 30px;
        }

        .section-title {
            font-size: 1.2em;
            color: var(--dark-blue);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--blue-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .medicine-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .medicine-table th {
            background: #f8f9fa;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-grey);
            border-bottom: 1px solid #e9ecef;
        }

        .medicine-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f3f4;
            color: var(--text-primary);
        }

        .medicine-table tr:last-child td {
            border-bottom: none;
        }

        .medicine-table tr:hover {
            background: #f8f9fa;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid #e9ecef;
        }

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

        .btn-print {
            background: #4a90e2;
            color: white;
        }

        .btn-print:hover {
            background: #357abd;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.2);
        }

        /* Not Found State */
        .not-found {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .not-found i {
            font-size: 4em;
            color: var(--warning-orange);
            margin-bottom: 20px;
        }

        .not-found h2 {
            color: var(--dark-grey);
            margin-bottom: 15px;
        }

        .not-found p {
            color: var(--soft-grey);
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .sidebar {
                width: 220px;
            }
            
            .main-content {
                margin-left: 220px;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content-wrapper {
                padding: 20px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main-header {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .patient-details {
                grid-template-columns: 1fr;
            }
            
            .prescription-meta {
                grid-template-columns: 1fr;
            }
            
            .medicine-table {
                font-size: 0.9em;
            }
            
            .medicine-table th,
            .medicine-table td {
                padding: 12px 15px;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .main-header,
            .action-buttons,
            .no-print {
                display: none !important;
            }
            
            body {
                background: white;
                margin: 0;
                padding: 0;
            }
            
            .main-content {
                margin: 0;
            }
            
            .content-wrapper {
                padding: 20px;
            }
            
            .prescription-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .btn {
                display: none;
            }
        }
    </style>
</head>
<body>
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
                    <li><a href="prescriptionDashboard.php" class="active"><i class="fas fa-prescription nav-icon"></i>Prescriptions</a></li>
                    <li><a href="Sales_Billing.php"><i class="fas fa-cash-register nav-icon"></i>Sales & Billing</a></li>
                </ul>
            </div>

            <div class="nav-section">
                <div class="nav-title">ADMINISTRATION</div>
                <ul class="nav-links">
                    <li><a href="user_management.php"><i class="fas fa-users nav-icon"></i>User Management</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar nav-icon"></i>Reports</a></li>
                    <li><a href="backup.php"><i class="fas fa-database nav-icon"></i>Backup & Restore</a></li>
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
                <h1>Prescription Details</h1>
                <p>View complete prescription information and medication details</p>
            </div>
            <div class="header-actions no-print">
                <a href="prescriptionDashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </header>

        <div class="content-wrapper">
            <?php if ($prescription): ?>
                <div class="prescription-card">
                    <div class="card-header">
                        <div>
                            <h2>Prescription Record</h2>
                            <div style="display: flex; align-items: center; margin-top: 10px; gap: 10px;">
                                <span class="source-badge">
                                    <i class="fas fa-database"></i> <?php echo $source; ?> Database
                                </span>
                                <?php $status = strtoupper($prescription['STATUS'] ?? 'PENDING'); ?>
                                <span class="status-badge status-<?php echo $status; ?>">
                                    <i class="fas fa-<?php echo $status === 'COMPLETED' ? 'check-circle' : 'clock'; ?>"></i>
                                    <?php echo $status; ?>
                                </span>
                            </div>
                        </div>
                        <div class="no-print">
                            <button onclick="window.print()" class="btn btn-print">
                                <i class="fas fa-print"></i> Print Record
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Patient Information -->
                        <div class="patient-info">
                            <h3><i class="fas fa-user-injured"></i> Patient Information</h3>
                            <div class="patient-details">
                                <div>
                                    <div class="detail-item">
                                        <div class="detail-label">Full Name</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($prescription['PATIENT_NAME'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Identification Number</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($prescription['PATIENT_IC'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-item">
                                        <div class="detail-label">Address</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($prescription['PATIENT_ADDR'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Prescription Metadata -->
                        <div class="prescription-meta">
                            <div class="detail-item">
                                <div class="detail-label">Prescription ID</div>
                                <div class="detail-value">#<?php echo $id; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Date Issued</div>
                                <div class="detail-value">
                                    <?php 
                                    $date = $prescription['DATE_ISSUED'] ?? '';
                                    if (is_object($date)) {
                                        echo $date->format('d M Y, h:i A');
                                    } elseif (!empty($date)) {
                                        echo date('d M Y, h:i A', strtotime($date));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Pharmacist</div>
                                <div class="detail-value"><?php echo htmlspecialchars($prescription['PHARMACIST_NAME'] ?? 'System User'); ?></div>
                            </div>
                        </div>

                        <!-- Medication Details -->
                        <div class="medicine-section">
                            <h3 class="section-title">
                                <i class="fas fa-prescription-bottle-alt"></i> Prescribed Medication
                            </h3>
                            
                            <?php if (empty($details)): ?>
                                <div class="not-found" style="padding: 30px;">
                                    <i class="fas fa-pills"></i>
                                    <h3>No Medication Items</h3>
                                    <p>This prescription does not contain any medication items.</p>
                                </div>
                            <?php else: ?>
                                <table class="medicine-table">
                                    <thead>
                                        <tr>
                                            <th>Medicine Name</th>
                                            <th>Dosage</th>
                                            <th>Instructions</th>
                                            <th>Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($details as $d): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($d['MED_NAME'] ?? 'Unknown'); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($d['DOSAGE'] ?? 'As directed'); ?></td>
                                            <td><?php echo htmlspecialchars($d['INSTRUCTION'] ?? '-'); ?></td>
                                            <td>
                                                <span style="background: var(--blue-light); color: var(--dark-blue); padding: 5px 12px; border-radius: 20px; font-weight: 600;">
                                                    <?php echo $d['QUANTITY'] ?? '0'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons no-print">
                            <a href="prescriptionDashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                            <button onclick="window.print()" class="btn btn-print">
                                <i class="fas fa-print"></i> Print Record
                            </button>
                            <a href="editprescription.php?id=<?php echo $id; ?>&source=<?php echo $source; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Prescription
                            </a>
                        </div>

                        <!-- Footer Note -->
                        <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e9ecef; color: var(--soft-grey); font-size: 0.85em;">
                            <p><i class="fas fa-info-circle"></i> This document was generated by the Unified Pharmacy Management System</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="not-found">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Prescription Not Found</h2>
                    <p>The prescription record #<?php echo $id; ?> could not be located in the <?php echo $source; ?> database.</p>
                    <a href="prescriptionDashboard.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-arrow-left"></i> Return to Prescription List
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Sidebar active state
        document.addEventListener('DOMContentLoaded', function() {
            // Set active navigation
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });

            // Print functionality
            const printBtn = document.querySelector('.btn-print');
            if (printBtn) {
                printBtn.addEventListener('click', function() {
                    window.print();
                });
            }
        });
    </script>
</body>
</html>