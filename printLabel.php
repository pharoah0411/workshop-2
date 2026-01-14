<?php
require_once 'session_check.php';
require_once 'connection.php'; 

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$source = $_GET['source'] ?? 'MySQL'; 

$items = [];
$patient_name = "";

if ($id > 0) {
    try {
        // Unified Query
        $sql = "SELECT m.NAME as MEDICINE_NAME, pd.DOSAGE, pd.QUANTITY, pd.INSTRUCTION, p.NAME as PATIENT_NAME 
                FROM PRESCRIPTION_DETAIL pd
                JOIN PRESCRIPTION pr ON pd.PRESCRIPTION_ID = pr.PRESCRIPTION_ID
                JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
                JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID
                WHERE pd.PRESCRIPTION_ID = ?";

        if ($source === 'MySQL' && isset($mysql_conn2)) {
            $stmt = $mysql_conn2->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $r = array_change_key_case($row, CASE_UPPER);
                $items[] = $r;
                $patient_name = $r['PATIENT_NAME'];
            }
        } elseif ($source === 'Postgres' && isset($pg_conn)) {
            $stmt = $pg_conn->prepare($sql);
            $stmt->execute([$id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $r = array_change_key_case($row, CASE_UPPER);
                $items[] = $r;
                $patient_name = $r['PATIENT_NAME'];
            }
        } elseif ($source === 'SQLServer' && isset($pdo_sqlsrv)) {
            $stmt = $pdo_sqlsrv->prepare($sql);
            $stmt->execute([$id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $r = array_change_key_case($row, CASE_UPPER);
                $items[] = $r;
                $patient_name = $r['PATIENT_NAME'];
            }
        }
    } catch (Exception $e) {
        $error = "Print Error: " . $e->getMessage();
    }
}

// Get user info for sidebar
$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Pharmacist';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Medication Labels | Pharmacy System</title>
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

        /* Print Control Bar */
        .print-control {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            border: 1px solid var(--border-color);
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

        .btn-success {
            background: var(--success-green);
            color: white;
        }

        .btn-success:hover {
            background: #4cae4c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(92, 184, 92, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: var(--dark-blue);
        }

        .btn-print {
            background: var(--warning-orange);
            color: white;
        }

        .btn-print:hover {
            background: #eea236;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(240, 173, 78, 0.2);
        }

        /* Labels Grid */
        .labels-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border-color);
        }

        /* Medication Label */
        .med-label {
            background: white;
            border: 2px solid var(--dark-blue);
            border-radius: 10px;
            padding: 25px;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 6px rgba(28, 73, 102, 0.1);
            transition: transform 0.3s ease;
            break-inside: avoid;
        }

        .med-label:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(28, 73, 102, 0.15);
        }

        .label-header {
            font-size: 0.9em;
            font-weight: 600;
            color: var(--dark-blue);
            border-bottom: 2px solid var(--blue-light);
            padding-bottom: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .medicine-name {
            font-size: 1.8em;
            font-weight: 800;
            color: var(--dark-blue);
            margin: 15px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }

        .medicine-details {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            color: var(--dark-grey);
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .instructions-box {
            background: #fff3cd;
            border: 2px dashed var(--warning-orange);
            padding: 15px;
            border-radius: 8px;
            font-weight: 700;
            color: #856404;
            text-align: center;
            margin: 15px 0;
            font-size: 1.1em;
        }

        .label-footer {
            display: flex;
            justify-content: space-between;
            font-size: 0.85em;
            color: var(--soft-grey);
            border-top: 1px solid var(--border-color);
            padding-top: 10px;
            margin-top: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
            grid-column: span 2;
        }

        .empty-state i {
            font-size: 3em;
            color: var(--blue-light);
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: var(--dark-grey);
            margin-bottom: 10px;
            font-size: 1.2em;
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
            
            .labels-grid {
                grid-template-columns: 1fr;
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
            
            .print-control {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .medicine-name {
                font-size: 1.5em;
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
            
            .med-label {
                padding: 20px;
            }
            
            .database-status {
                flex-direction: column;
                align-items: center;
            }
        }

        /* Print Styles */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .dashboard-container {
                max-width: 100% !important;
                height: auto !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            
            .sidebar,
            .main-header,
            .print-control {
                display: none !important;
            }
            
            .main-content {
                width: 100% !important;
                margin: 0 !important;
            }
            
            .content-wrapper {
                padding: 0 !important;
                background: white !important;
            }
            
            .labels-grid {
                display: block !important;
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }
            
            .med-label {
                border: 2px solid #000 !important;
                box-shadow: none !important;
                margin-bottom: 20px !important;
                page-break-inside: avoid !important;
                transform: none !important;
            }
            
            .med-label:hover {
                transform: none !important;
                box-shadow: none !important;
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
                    <h1>Print Medication Labels</h1>
                    <p>Prescription ID: #<?php echo $id; ?> | Patient: <?php echo htmlspecialchars($patient_name); ?></p>
                </div>
                <div class="header-actions">
                    <a href="javascript:history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Database Status -->
                <div class="database-status">
                    <span class="status-item <?php echo (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? 'status-online' : 'status-offline'; ?>">
                        <i class="fas fa-database"></i> MySQL: <?php echo (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? 'Connected' : 'Offline'; ?>
                    </span>
                    <span class="status-item <?php echo (isset($pg_conn) && $pg_conn instanceof PDO) ? 'status-online' : 'status-offline'; ?>">
                        <i class="fas fa-database"></i> PostgreSQL: <?php echo (isset($pg_conn) && $pg_conn instanceof PDO) ? 'Connected' : 'Offline'; ?>
                    </span>
                    <span class="status-item <?php echo (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? 'status-online' : 'status-offline'; ?>">
                        <i class="fas fa-database"></i> SQL Server: <?php echo (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? 'Connected' : 'Offline'; ?>
                    </span>
                </div>

                <!-- Print Control Bar -->
                <div class="print-control">
                    <button onclick="window.print()" class="btn btn-print">
                        <i class="fas fa-print"></i> Print Labels
                    </button>
                    <a href="prescriptionDashboard.php" class="btn btn-secondary">
                        <i class="fas fa-prescription"></i> Back to Prescriptions
                    </a>
                    <a href="createPrescription.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> New Prescription
                    </a>
                </div>

                <!-- Labels Grid -->
                <div class="labels-grid">
                    <?php if (empty($items)): ?>
                        <div class="empty-state">
                            <i class="fas fa-print"></i>
                            <h3>No Prescription Data Found</h3>
                            <p>Check your database connection or verify the prescription ID.</p>
                            <a href="prescriptionDashboard.php" class="btn btn-primary" style="margin-top: 15px; display: inline-block;">
                                <i class="fas fa-arrow-left"></i> Return to Prescriptions
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <div class="med-label">
                                <div class="label-header">
                                    <i class="fas fa-user-injured"></i>
                                    PATIENT: <?php echo htmlspecialchars($patient_name ?? ''); ?>
                                </div>
                                
                                <div class="medicine-name">
                                    <?php echo htmlspecialchars($item['MEDICINE_NAME'] ?? ''); ?>
                                </div>
                                
                                <div class="medicine-details">
                                    <span>Qty: <?php echo htmlspecialchars($item['QUANTITY'] ?? ''); ?></span>
                                    <span>Dose: <?php echo htmlspecialchars($item['DOSAGE'] ?? ''); ?></span>
                                </div>
                                
                                <div class="instructions-box">
                                    <i class="fas fa-info-circle"></i>
                                    <?php echo htmlspecialchars($item['INSTRUCTION'] ?? ''); ?>
                                </div>
                                
                                <div class="label-footer">
                                    <span>Date: <?php echo date('d/m/Y'); ?></span>
                                    <span>Source: <?php echo htmlspecialchars($source); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                if (link.getAttribute('href') === 'prescriptionDashboard.php') {
                    link.classList.add('active');
                }
            });
        });

        // Print optimization
        window.addEventListener('beforeprint', function() {
            document.querySelectorAll('.med-label').forEach(label => {
                label.style.border = '2px solid #000';
                label.style.boxShadow = 'none';
            });
        });
    </script>
</body>
</html>