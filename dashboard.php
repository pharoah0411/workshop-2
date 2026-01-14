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

// ========== DYNAMIC DATA FETCHING (AGGREGATED & DEDUPLICATED) ==========
$stats = [
    'medicine_count' => 0,
    'low_stock_count' => 0,
    'pending_prescriptions' => 0,
    'total_patients' => 0,
    'total_users' => 0
];

$low_stock_threshold = 50;

// Database connection status
$mysql_connected = ($mysql_conn2 && $mysql_conn2 instanceof mysqli);
$sqlserver_connected = (isset($pdo) && $pdo instanceof PDO);
$postgresql_connected = (isset($pg_conn) && $pg_conn instanceof PDO);

// Arrays to collect identifiers for cross-database deduplication
$all_usernames = [];
$all_patient_ics = [];

/**
 * Helper function to fetch single count value from different DB drivers
 */
function getDbCountValue($conn, $query, $is_mysqli = false) {
    try {
        if ($is_mysqli) {
            $res = $conn->query($query);
            if ($res) {
                $row = $res->fetch_row();
                return isset($row[0]) ? intval($row[0]) : 0;
            }
        } else {
            $res = $conn->query($query);
            if ($res) return intval($res->fetchColumn());
        }
    } catch (Exception $e) {
        error_log("DB Count Error: " . $e->getMessage());
    }
    return 0;
}

// 1. MySQL Data Collection
if ($mysql_connected) {
    $stats['medicine_count'] += getDbCountValue($mysql_conn2, "SELECT COUNT(*) FROM MEDICINE", true);
    $stats['low_stock_count'] += getDbCountValue($mysql_conn2, "SELECT COUNT(*) FROM MEDICINE WHERE quantity <= $low_stock_threshold", true);
    $stats['pending_prescriptions'] += getDbCountValue($mysql_conn2, "SELECT COUNT(*) FROM PRESCRIPTION WHERE LOWER(status) = 'pending' OR status IS NULL", true);
    
    // Collect IDs for deduplication
    $res = $mysql_conn2->query("SELECT USERNAME FROM `USER` ");
    if($res) while($row = $res->fetch_row()) if($row[0]) $all_usernames[] = strtolower(trim($row[0]));
    
    $res = $mysql_conn2->query("SELECT IC_NO FROM PATIENT");
    if($res) while($row = $res->fetch_row()) if($row[0]) $all_patient_ics[] = strtolower(trim($row[0]));
}

// 2. PostgreSQL Data Collection
if ($postgresql_connected) {
    $stats['medicine_count'] += getDbCountValue($pg_conn, "SELECT COUNT(*) FROM MEDICINE");
    $stats['low_stock_count'] += getDbCountValue($pg_conn, "SELECT COUNT(*) FROM MEDICINE WHERE quantity <= $low_stock_threshold");
    $stats['pending_prescriptions'] += getDbCountValue($pg_conn, "SELECT COUNT(*) FROM PRESCRIPTION WHERE LOWER(status) = 'pending' OR status IS NULL");
    
    // Collect IDs for deduplication
    $res = $pg_conn->query("SELECT username FROM \"user\"");
    if($res) while($row = $res->fetch(PDO::FETCH_NUM)) if($row[0]) $all_usernames[] = strtolower(trim($row[0]));
    
    $res = $pg_conn->query("SELECT ic_no FROM patient");
    if($res) while($row = $res->fetch(PDO::FETCH_NUM)) if($row[0]) $all_patient_ics[] = strtolower(trim($row[0]));
}

// 3. SQL Server Data Collection
if ($sqlserver_connected) {
    $stats['medicine_count'] += getDbCountValue($pdo, "SELECT COUNT(*) FROM MEDICINE");
    $stats['low_stock_count'] += getDbCountValue($pdo, "SELECT COUNT(*) FROM MEDICINE WHERE quantity <= $low_stock_threshold");
    $stats['pending_prescriptions'] += getDbCountValue($pdo, "SELECT COUNT(*) FROM PRESCRIPTION WHERE LOWER(status) = 'pending' OR status IS NULL");
    
    // Collect IDs for deduplication
    $res = $pdo->query("SELECT USERNAME FROM [USER]");
    if($res) while($row = $res->fetch(PDO::FETCH_NUM)) if($row[0]) $all_usernames[] = strtolower(trim($row[0]));
    
    $res = $pdo->query("SELECT IC_NO FROM PATIENT");
    if($res) while($row = $res->fetch(PDO::FETCH_NUM)) if($row[0]) $all_patient_ics[] = strtolower(trim($row[0]));
}

// Calculate Deduplicated Aggregated Totals
$stats['total_users'] = count(array_unique($all_usernames));
$stats['total_patients'] = count(array_unique($all_patient_ics));

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

        /* System Status Section */
        .system-status-section {
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

        /* Responsive Design */
        @media (max-width: 1200px) {
            .dashboard-container { height: auto; flex-direction: column; }
            .sidebar { width: 100%; height: auto; }
            .nav-menu { display: flex; flex-wrap: wrap; gap: 10px; padding: 15px; }
            .nav-section { flex: 1; min-width: 200px; margin-bottom: 15px; }
            .main-content { width: 100%; }
        }

        @media (max-width: 768px) {
            .content-wrapper { padding: 20px; }
            .main-header { padding: 15px 20px; flex-direction: column; align-items: flex-start; gap: 15px; }
            .status-grid, .quick-stats { grid-template-columns: 1fr; }
            .welcome-section { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
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

        <main class="main-content">
            <header class="main-header">
                <div class="header-title">
                    <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                    <p>Pharmacy Management Dashboard - <?php echo date('l, F j, Y'); ?></p>
                </div>
            </header>

            <div class="content-wrapper">
                <section class="welcome-section">
                    <div class="welcome-text">
                        <h2>Pharmacy Management System</h2>
                        <p>Aggregated management across all connected database systems</p>
                    </div>
                </section>

                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['medicine_count']; ?></div>
                        <div class="stat-label">Total Medicines</div>
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

                <section class="system-status-section">
                    <h2 class="section-title"><i class="fas fa-heartbeat"></i> System Status</h2>
                    <div class="status-grid">
                        <div class="status-card">
                            <div class="status-icon" style="color: <?php echo $mysql_connected ? '#5cb85c' : '#d9534f'; ?>">
                                <i class="fas <?php echo $mysql_connected ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            </div>
                            <h3 class="status-title">MySQL Database</h3>
                            <p class="status-description"><?php echo $mysql_connected ? 'Connected' : 'Disconnected'; ?></p>
                            <div class="status-time"><?php echo $mysql_connected ? 'Live' : 'Offline'; ?></div>
                        </div>
                        <div class="status-card">
                            <div class="status-icon" style="color: <?php echo $sqlserver_connected ? '#5cb85c' : '#8a8a8a'; ?>">
                                <i class="fas <?php echo $sqlserver_connected ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            </div>
                            <h3 class="status-title">SQL Server</h3>
                            <p class="status-description"><?php echo $sqlserver_connected ? 'Connected' : 'Disconnected'; ?></p>
                            <div class="status-time"><?php echo $sqlserver_connected ? 'Live' : 'Offline'; ?></div>
                        </div>
                        <div class="status-card">
                            <div class="status-icon" style="color: <?php echo $postgresql_connected ? '#5cb85c' : '#8a8a8a'; ?>">
                                <i class="fas <?php echo $postgresql_connected ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            </div>
                            <h3 class="status-title">PostgreSQL</h3>
                            <p class="status-description"><?php echo $postgresql_connected ? 'Connected' : 'Disconnected'; ?></p>
                            <div class="status-time"><?php echo $postgresql_connected ? 'Live' : 'Offline'; ?></div>
                        </div>
                        <div class="status-card">
                            <div class="status-icon" style="color: var(--dark-blue)">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="status-title">Aggregated Data</h3>
                            <p class="status-description">
                                <?php echo $stats['total_patients']; ?> patients<br>
                                <?php echo $stats['total_users']; ?> users
                            </p>
                            <div class="status-time">Combined Global Count</div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh dashboard every 60 seconds
        setTimeout(function() {
            window.location.reload();
        }, 60000);
    </script>
</body>
</html>