<?php
require_once "session_check.php";
require_once 'connection.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!empty($_SESSION['force_reset'])) {
    header("Location: reset_password.php");
    exit;
}

$userRole = $_SESSION['role'] ?? 'Guest';
$username = $_SESSION['username'] ?? 'User';

// ========== DYNAMIC DATA FETCHING ==========
$stats = [
    'medicine_count' => 0,
    'low_stock_count' => 0,
    'pending_prescriptions' => 0,
    'total_patients' => 0,
    'total_users' => 0,
    'low_stock_medicines' => [],
    'pending_prescription_list' => [],
    'recent_prescriptions' => [],
    'recent_patients' => []
];

// Database connection status
$mysql_connected = ($mysql_conn2 && $mysql_conn2 instanceof mysqli);
$sqlserver_connected = (isset($pdo) && $pdo instanceof PDO);
$postgresql_connected = (isset($pg_conn) && $pg_conn instanceof PDO);

// Only fetch if MySQL is connected
if ($mysql_connected) {
    try {
        // 1. Medicine Statistics
        $result = $mysql_conn2->query("SELECT COUNT(*) as count FROM MEDICINE");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['medicine_count'] = $row['count'] ?? 0;
        }
        
        // 2. Low Stock Medicines (less than or equal to 50 quantity) - FIXED!
        $low_stock_threshold = 50;
        $result = $mysql_conn2->query("SELECT COUNT(*) as count FROM MEDICINE WHERE quantity <= $low_stock_threshold");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['low_stock_count'] = $row['count'] ?? 0;
        }
        
        // 3. Low Stock Medicine Names - FIXED!
        $result = $mysql_conn2->query("SELECT medicine_name, quantity FROM MEDICINE WHERE quantity <= $low_stock_threshold ORDER BY quantity ASC LIMIT 5");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $stats['low_stock_medicines'][] = $row;
            }
        }
        
        // 4. Pending Prescriptions
        $result = $mysql_conn2->query("SELECT COUNT(*) as count FROM PRESCRIPTION WHERE status = 'pending' OR status IS NULL");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['pending_prescriptions'] = $row['count'] ?? 0;
        }
        
        // 5. Recent Prescriptions
        $result = $mysql_conn2->query("SELECT p.prescription_id, p.date_issued, pat.first_name, pat.last_name 
                                       FROM PRESCRIPTION p 
                                       LEFT JOIN PATIENT pat ON p.patient_id = pat.patient_id 
                                       ORDER BY p.date_issued DESC LIMIT 5");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $stats['recent_prescriptions'][] = $row;
            }
        }
        
        // 6. Pending Prescription List
        $result = $mysql_conn2->query("SELECT p.prescription_id, p.date_issued, pat.first_name, pat.last_name 
                                       FROM PRESCRIPTION p 
                                       LEFT JOIN PATIENT pat ON p.patient_id = pat.patient_id 
                                       WHERE p.status = 'pending' OR p.status IS NULL 
                                       ORDER BY p.date_issued DESC LIMIT 3");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $stats['pending_prescription_list'][] = $row;
            }
        }
        
        // 7. Total Patients
        $result = $mysql_conn2->query("SELECT COUNT(*) as count FROM PATIENT");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_patients'] = $row['count'] ?? 0;
        }
        
        // 8. Recent Patients
        $result = $mysql_conn2->query("SELECT first_name, last_name, created_at FROM PATIENT ORDER BY created_at DESC LIMIT 3");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $stats['recent_patients'][] = $row;
            }
        }
        
        // 9. Total Users
        $result = $mysql_conn2->query("SELECT COUNT(*) as count FROM USER");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_users'] = $row['count'] ?? 0;
        }
        
    } catch (Exception $e) {
        // Keep default stats if query fails
        error_log("Dashboard stats error: " . $e->getMessage());
    }
}

// Calculate connected databases count
$connected_dbs = 0;
if ($mysql_connected) $connected_dbs++;
if ($sqlserver_connected) $connected_dbs++;
if ($postgresql_connected) $connected_dbs++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHARMACY SYSTEM DASHBOARD</title>
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
            --main-bg: var(--cream-white);
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

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 15px 10px 40px;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            width: 280px;
            font-size: 0.9em;
            background: var(--blue-light);
            transition: all 0.3s ease;
            font-weight: 300;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1);
            background: white;
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-blue);
            font-size: 0.9em;
        }

        /* Content Area */
        .content-wrapper {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: var(--main-bg);
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .welcome-text h2 {
            font-size: 1.5em;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .welcome-text p {
            font-size: 0.95em;
            opacity: 0.9;
            font-weight: 300;
        }

        /* Important Notices */
        .notices-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.2em;
            color: var(--dark-blue);
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }

        .notice-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--dark-blue);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease;
        }

        .notice-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .notice-card.urgent {
            border-left-color: var(--alert-red);
        }

        .notice-card.warning {
            border-left-color: var(--warning-orange);
        }

        .notice-title {
            font-size: 1em;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notice-content {
            color: var(--text-secondary);
            margin-bottom: 12px;
            line-height: 1.5;
            font-size: 0.9em;
        }

        .notice-date {
            font-size: 0.8em;
            color: var(--dark-blue);
            font-weight: 500;
        }

        /* System Status Grid */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .status-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
        }

        .status-icon {
            font-size: 2em;
            margin-bottom: 12px;
        }

        .status-title {
            font-size: 1em;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .status-description {
            color: var(--text-secondary);
            font-size: 0.85em;
            margin-bottom: 10px;
        }

        .status-time {
            font-size: 0.8em;
            color: var(--dark-blue);
            font-weight: 500;
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid var(--border-color);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
        }

        .stat-number {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--dark-blue);
            margin: 8px 0;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.85em;
            font-weight: 300;
        }

        /* Recent Activity */
        .activity-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1em;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            color: var(--text-primary);
            margin-bottom: 3px;
            font-size: 0.9em;
        }

        .activity-time {
            color: var(--text-secondary);
            font-size: 0.8em;
            font-weight: 300;
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
            
            .search-box input {
                width: 100%;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .notices-grid,
            .status-grid,
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .welcome-section {
                padding: 20px;
            }
            
            .welcome-text h2 {
                font-size: 1.3em;
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
            
            .section-title {
                font-size: 1.1em;
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
                        <!-- Using your friend's preferred pills icon -->
                        <li><a href="dashboard.php" class="active"><i class="fas fa-pills nav-icon"></i>Dashboard</a></li>
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
            </nav>

            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </button>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-title">
                    <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                    <p>Pharmacy Management Dashboard - <?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" placeholder="Search medicines, prescriptions...">
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Welcome Section -->
                <section class="welcome-section">
                    <div class="welcome-text">
                        <h2>Pharmacy Management System</h2>
                        <p>Efficient healthcare management for better patient care</p>
                    </div>
                </section>

                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['medicine_count']; ?></div>
                        <div class="stat-label">Medicines in Stock</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['low_stock_count']; ?></div>
                        <div class="stat-label">Low Stock Items</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['pending_prescriptions']; ?></div>
                        <div class="stat-label">Pending Prescriptions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $connected_dbs; ?>/3</div>
                        <div class="stat-label">DBs Connected</div>
                    </div>
                </div>

                <!-- Important Notices -->
                <section class="notices-section">
                    <h2 class="section-title"><i class="fas fa-bell"></i> Important Notices</h2>
                    <div class="notices-grid">
                        <!-- Low Stock Notice -->
                        <div class="notice-card <?php echo ($stats['low_stock_count'] > 0) ? 'urgent' : ''; ?>">
                            <div class="notice-title">
                                <i class="fas <?php echo ($stats['low_stock_count'] > 0) ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i> 
                                <?php echo ($stats['low_stock_count'] > 0) ? 'Low Stock Alert' : 'Stock Levels Normal'; ?>
                            </div>
                            <div class="notice-content">
                                <?php if ($stats['low_stock_count'] > 0): ?>
                                    <strong><?php echo $stats['low_stock_count']; ?> medicine(s) running low (≤50 items):</strong><br>
                                    <?php foreach ($stats['low_stock_medicines'] as $medicine): ?>
                                        • <?php echo htmlspecialchars($medicine['medicine_name']); ?> 
                                        <span style="color: var(--alert-red); font-weight: bold;">
                                            (<?php echo $medicine['quantity']; ?> left)
                                        </span><br>
                                    <?php endforeach; ?>
                                    <?php if (count($stats['low_stock_medicines']) < $stats['low_stock_count']): ?>
                                        <br>... and <?php echo ($stats['low_stock_count'] - count($stats['low_stock_medicines'])); ?> more medicine(s) with low stock
                                    <?php endif; ?>
                                <?php else: ?>
                                    All medicines are sufficiently stocked. No medicines with 50 or fewer items.
                                <?php endif; ?>
                            </div>
                            <div class="notice-date">Updated <?php echo date('h:i A'); ?></div>
                        </div>
                        
                        <!-- System Status Notice -->
                        <div class="notice-card">
                            <div class="notice-title">
                                <i class="fas fa-database"></i> 
                                Database Status
                            </div>
                            <div class="notice-content">
                                <strong>Connected Databases:</strong><br>
                                • MySQL: <?php echo $mysql_connected ? '✅ Connected' : '❌ Disconnected'; ?><br>
                                • SQL Server: <?php echo $sqlserver_connected ? '✅ Connected' : '❌ Disconnected'; ?><br>
                                • PostgreSQL: <?php echo $postgresql_connected ? '✅ Connected' : '❌ Disconnected'; ?>
                                <br><br>
                                <strong>Data Summary:</strong><br>
                                • <?php echo $stats['medicine_count']; ?> medicines in database<br>
                                • <?php echo $stats['total_patients']; ?> patients registered<br>
                                • <?php echo $stats['pending_prescriptions']; ?> prescriptions pending
                            </div>
                            <div class="notice-date">Checked just now</div>
                        </div>
                    </div>
                </section>

                <!-- System Status -->
                <section class="notices-section">
                    <h2 class="section-title"><i class="fas fa-heartbeat"></i> System Status</h2>
                    <div class="status-grid">
                        <div class="status-card">
                            <div class="status-icon" style="color: <?php echo $mysql_connected ? '#5cb85c' : '#d9534f'; ?>">
                                <i class="fas <?php echo $mysql_connected ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            </div>
                            <h3 class="status-title">MySQL Database</h3>
                            <p class="status-description"><?php echo $mysql_connected ? 'Connected' : 'Disconnected'; ?></p>
                            <div class="status-time">
                                <?php echo $mysql_connected ? 'Live Data' : 'Offline'; ?>
                            </div>
                        </div>
                        <div class="status-card">
                            <div class="status-icon" style="color: <?php echo $sqlserver_connected ? '#5cb85c' : '#8a8a8a'; ?>">
                                <i class="fas <?php echo $sqlserver_connected ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            </div>
                            <h3 class="status-title">SQL Server</h3>
                            <p class="status-description"><?php echo $sqlserver_connected ? 'Connected' : 'Not configured'; ?></p>
                            <div class="status-time">
                                <?php echo $sqlserver_connected ? 'Team Database' : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="status-card">
                            <div class="status-icon" style="color: <?php echo $postgresql_connected ? '#5cb85c' : '#8a8a8a'; ?>">
                                <i class="fas <?php echo $postgresql_connected ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            </div>
                            <h3 class="status-title">PostgreSQL</h3>
                            <p class="status-description"><?php echo $postgresql_connected ? 'Connected' : 'Not configured'; ?></p>
                            <div class="status-time">
                                <?php echo $postgresql_connected ? 'Team Database' : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="status-card">
                            <div class="status-icon" style="color: var(--dark-blue)">
                                <i class="fas fa-capsules"></i>
                            </div>
                            <h3 class="status-title">Pharmacy Data</h3>
                            <p class="status-description">
                                <?php echo $stats['medicine_count']; ?> medicines<br>
                                <?php echo $stats['total_patients']; ?> patients
                            </p>
                            <div class="status-time">Live from database</div>
                        </div>
                    </div>
                </section>

                <!-- Recent Activity -->
                <section class="activity-section">
                    <h2 class="section-title"><i class="fas fa-history"></i> Recent Activity</h2>
                    <ul class="activity-list">
                        <li class="activity-item">
                            <div class="activity-icon" style="background: #e3f2fd; color: #1c4966;">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">You logged in to the system as <?php echo htmlspecialchars($userRole); ?></div>
                                <div class="activity-time"><?php echo date('h:i A'); ?></div>
                            </div>
                        </li>
                        
                        <?php if ($stats['medicine_count'] > 0): ?>
                        <li class="activity-item">
                            <div class="activity-icon" style="background: #e3f2fd; color: #1c4966;">
                                <i class="fas fa-pills"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text"><?php echo $stats['medicine_count']; ?> medicines in inventory</div>
                                <div class="activity-time">Live count</div>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($stats['total_patients'] > 0): ?>
                        <li class="activity-item">
                            <div class="activity-icon" style="background: #e3f2fd; color: #1c4966;">
                                <i class="fas fa-user-injured"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text"><?php echo $stats['total_patients']; ?> patients in database</div>
                                <div class="activity-time">Active records</div>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($stats['low_stock_count'] > 0): ?>
                        <li class="activity-item">
                            <div class="activity-icon" style="background: #f8d7da; color: #721c24;">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text"><?php echo $stats['low_stock_count']; ?> medicine(s) running low on stock</div>
                                <div class="activity-time">Requires attention</div>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($stats['pending_prescriptions'] > 0): ?>
                        <li class="activity-item">
                            <div class="activity-icon" style="background: #fff3cd; color: #856404;">
                                <i class="fas fa-prescription"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text"><?php echo $stats['pending_prescriptions']; ?> prescriptions pending processing</div>
                                <div class="activity-time">Needs review</div>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (count($stats['recent_prescriptions']) > 0): ?>
                        <li class="activity-item">
                            <div class="activity-icon" style="background: #e3f2fd; color: #1c4966;">
                                <i class="fas fa-file-medical"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    Latest prescription: #<?php echo $stats['recent_prescriptions'][0]['prescription_id']; ?> 
                                    for <?php echo htmlspecialchars($stats['recent_prescriptions'][0]['first_name'] . ' ' . $stats['recent_prescriptions'][0]['last_name']); ?>
                                </div>
                                <div class="activity-time">
                                    <?php echo date('M d, h:i A', strtotime($stats['recent_prescriptions'][0]['date_issued'])); ?>
                                </div>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </section>
            </div>
        </main>
    </div>

    <script>
        // Sidebar navigation active state
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function(e) {
                sessionStorage.setItem('activePage', this.getAttribute('href'));
            });
        });

        // Restore active page on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const activePage = sessionStorage.getItem('activePage') || 'dashboard.php';
            
            document.querySelectorAll('.nav-links a').forEach(link => {
                const linkPage = link.getAttribute('href');
                link.classList.remove('active');
                
                if (linkPage === currentPage || linkPage === activePage) {
                    link.classList.add('active');
                }
            });
        });

        // Search functionality
        document.querySelector('.search-box input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    alert(`Search function would search for: "${searchTerm}"\n\nThis feature connects to your database search.`);
                }
            }
        });

        // Clear search on escape
        document.querySelector('.search-box input').addEventListener('keyup', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
            }
        });

        // Auto-refresh dashboard every 60 seconds
        setTimeout(function() {
            window.location.reload();
        }, 60000);
    </script>
</body>
</html>