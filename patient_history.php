<?php
// Use ONLY auth_check.php like before
require_once "auth_check.php";
requireRole('admin');

require_once "connection.php";
// REMOVE: include "header.php"; // Don't use this old header

$message = "";

// ---------------------
// 1) Validate Inputs
// ---------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['db'])) {
    die("<p style='color:red;'>Missing patient ID or database source.</p><a href='patient_list.php'>Back</a>");
}

$patient_id = (int)$_GET['id'];
$db = trim($_GET['db']); // "Postgres", "MySQL", "SQL Server"

// We'll store patient & history here
$patient = null;
$historyList = [];

// ---------------------
// 2) FETCH PATIENT (by DB)
// ---------------------
try {

    /* =========================
       POSTGRESQL
    ========================= */
    if ($db === "Postgres" && isset($pg_conn) && $pg_conn instanceof PDO) {

        // patient name is inside patient table in your latest design
        $stmt = $pg_conn->prepare('SELECT patient_id, name FROM patient WHERE patient_id = ?');
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) die("<p style='color:red;'>Patient not found in Postgres.</p><a href='patient_list.php'>Back</a>");

        // Add new history
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $description = trim($_POST["description"] ?? '');
            if ($description !== "") {
                $ins = $pg_conn->prepare('INSERT INTO medical_history (patient_id, description) VALUES (?, ?)');
                $ins->execute([$patient_id, $description]);
                $message = "<div class='success-message'><i class='fas fa-check-circle'></i> New history record added!</div>";
            }
        }

        // Fetch history list
        $stmtH = $pg_conn->prepare('SELECT history_id, description FROM medical_history WHERE patient_id = ? ORDER BY history_id DESC');
        $stmtH->execute([$patient_id]);
        $historyList = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================
       MYSQL
    ========================= */
    elseif ($db === "MySQL" && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {

        // patient name is inside PATIENT table
        $stmt = $mysql_conn2->prepare("SELECT PATIENT_ID, NAME FROM PATIENT WHERE PATIENT_ID = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $patient = $res->fetch_assoc();
        $stmt->close();

        if (!$patient) die("<p style='color:red;'>Patient not found in MySQL.</p><a href='patient_list.php'>Back</a>");

        // Add new history
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $description = trim($_POST["description"] ?? '');
            if ($description !== "") {
                $ins = $mysql_conn2->prepare("INSERT INTO MEDICAL_HISTORY (PATIENT_ID, DESCRIPTION) VALUES (?, ?)");
                $ins->bind_param("is", $patient_id, $description);
                $ins->execute();
                $ins->close();
                $message = "<div class='success-message'><i class='fas fa-check-circle'></i> New history record added!</div>";
            }
        }

        // Fetch history list
        $stmtH = $mysql_conn2->prepare("SELECT HISTORY_ID, DESCRIPTION FROM MEDICAL_HISTORY WHERE PATIENT_ID = ? ORDER BY HISTORY_ID DESC");
        $stmtH->bind_param("i", $patient_id);
        $stmtH->execute();
        $historyList = $stmtH->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtH->close();
    }

    /* =========================
       SQL SERVER
    ========================= */
    elseif ($db === "SQL Server" && isset($pdo) && $pdo instanceof PDO) {

        $stmt = $pdo->prepare("SELECT patient_id, name FROM patient WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) die("<p style='color:red;'>Patient not found in SQL Server.</p><a href='patient_list.php'>Back</a>");

        // Add new history
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $description = trim($_POST["description"] ?? '');
            if ($description !== "") {
                $ins = $pdo->prepare("INSERT INTO medical_history (patient_id, description) VALUES (?, ?)");
                $ins->execute([$patient_id, $description]);
                $message = "<div class='success-message'><i class='fas fa-check-circle'></i> New history record added!</div>";
            }
        }

        // Fetch history list
        $stmtH = $pdo->prepare("SELECT history_id, description FROM medical_history WHERE patient_id = ? ORDER BY history_id DESC");
        $stmtH->execute([$patient_id]);
        $historyList = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    }

    else {
        die("<p style='color:red;'>Invalid database selected or connection not available.</p><a href='patient_list.php'>Back</a>");
    }

} catch (Exception $e) {
    die("<p style='color:red;'>System error: " . htmlspecialchars($e->getMessage()) . "</p><a href='patient_list.php'>Back</a>");
}

// Normalize name key (MySQL uses NAME)
$patientName = $patient['name'] ?? $patient['NAME'] ?? 'Patient';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - PHARMACY SYSTEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* EXACT SAME CSS STYLING FROM BEFORE - KEEP EVERYTHING BELOW THIS LINE */
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

        /* Patient Info */
        .patient-info {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 25px 30px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .patient-details h2 {
            font-size: 1.4em;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .patient-details p {
            opacity: 0.9;
            font-size: 0.95em;
        }

        .db-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Form Card */
        .form-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
        }

        .form-title {
            font-size: 1.1em;
            color: var(--dark-blue);
            margin-bottom: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-box {
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.95em;
            transition: all 0.3s ease;
            resize: vertical;
            min-height: 120px;
            margin-bottom: 15px;
        }

        .input-box:focus {
            outline: none;
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1);
        }

        .form-buttons {
            display: flex;
            gap: 12px;
        }

        .btn-primary {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--blue-medium) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(28, 73, 102, 0.2);
        }

        .btn-secondary {
            padding: 12px 24px;
            background: var(--soft-grey);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-secondary:hover {
            background: var(--dark-grey);
            transform: translateY(-2px);
        }

        /* Message Styling */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* History Table */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--blue-medium) 100%);
            color: white;
        }

        .data-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9em;
        }

        .data-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s ease;
        }

        .data-table tbody tr:hover {
            background-color: var(--blue-light);
        }

        .data-table td {
            padding: 16px 20px;
            vertical-align: top;
        }

        .history-id {
            font-weight: 600;
            color: var(--dark-blue);
            font-size: 0.9em;
            width: 80px;
        }

        .history-description {
            font-size: 0.95em;
            line-height: 1.6;
            color: var(--text-primary);
        }

        .no-data {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 2.5em;
            color: var(--border-color);
            margin-bottom: 15px;
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
            
            .patient-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .form-buttons {
                flex-direction: column;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
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
            
            .patient-details h2 {
                font-size: 1.2em;
            }
            
            .data-table th,
            .data-table td {
                padding: 12px 15px;
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
                    <?php 
                    // Get session variables directly from auth_check.php
                    require_once "auth_check.php";
                    $username = $_SESSION['username'] ?? 'User';
                    $userRole = $_SESSION['role'] ?? 'Guest';
                    echo strtoupper(substr($username, 0, 2)); 
                    ?>
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
                        <li><a href="dashboard.php"><i class="fas fa-home nav-icon"></i>Dashboard</a></li>
                        <li><a href="medDirectory.php"><i class="fas fa-pills nav-icon"></i>Medicine Inventory</a></li>
                        <li><a href="prescriptionDashboard.php"><i class="fas fa-prescription nav-icon"></i>Prescriptions</a></li>
                        <li><a href="Sales_Billing.php"><i class="fas fa-cash-register nav-icon"></i>Sales & Billing</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-title">ADMINISTRATION</div>
                    <ul class="nav-links">
                        <li><a href="patient_list.php"><i class="fas fa-user-injured nav-icon"></i>Patient List</a></li>
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
                    <h1>Medical History</h1>
                    <p>Manage patient medical records - <?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" placeholder="Search medical records...">
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Success Message -->
                <?php echo $message; ?>

                <!-- Patient Information -->
                <div class="patient-info">
                    <div class="patient-details">
                        <h2><?php echo htmlspecialchars($patientName); ?></h2>
                        <p>Patient ID: #<?php echo htmlspecialchars($patient_id); ?></p>
                    </div>
                    <div class="db-badge">
                        <i class="fas fa-database"></i> <?php echo htmlspecialchars($db); ?> Database
                    </div>
                </div>

                <!-- Add History Form -->
                <form method="POST" class="form-card">
                    <h3 class="form-title"><i class="fas fa-plus-circle"></i> Add New History Record</h3>
                    
                    <textarea name="description" 
                              class="input-box" 
                              required
                              placeholder="Enter medical note, symptoms, diagnosis, treatment, or follow-up information..."></textarea>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Add Record
                        </button>
                        <a href="patient_list.php" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Patient List
                        </a>
                    </div>
                </form>

                <!-- History Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Medical Record</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($historyList) === 0): ?>
                                <tr>
                                    <td colspan="2" class="no-data">
                                        <i class="fas fa-clipboard"></i>
                                        <h3>No Medical History Recorded</h3>
                                        <p>Start by adding the patient's first medical record above.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historyList as $h): ?>
                                    <tr>
                                        <td class="history-id">
                                            #<?php echo htmlspecialchars($h['history_id'] ?? $h['HISTORY_ID']) ?>
                                        </td>
                                        <td class="history-description">
                                            <?php echo nl2br(htmlspecialchars($h['description'] ?? $h['DESCRIPTION'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
                
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });
            
            // Highlight Patients in navigation
            const patientLinks = document.querySelectorAll('.nav-links a[href*="patient"]');
            patientLinks.forEach(link => {
                if (currentPage.includes('patient_history') || currentPage.includes('patient_list')) {
                    link.classList.add('active');
                }
            });
        });

        // Search functionality
        document.querySelector('.search-box input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    // Filter table rows
                    const rows = document.querySelectorAll('.data-table tbody tr');
                    let found = false;
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        if (text.includes(searchTerm.toLowerCase())) {
                            row.style.display = '';
                            found = true;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    if (!found) {
                        alert('No matching records found.');
                    }
                }
            }
        });

        // Clear search on escape
        document.querySelector('.search-box input').addEventListener('keyup', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                // Show all rows
                document.querySelectorAll('.data-table tbody tr').forEach(row => {
                    row.style.display = '';
                });
            }
        });

        // Auto-focus on textarea when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('textarea[name="description"]');
            if (textarea) {
                textarea.focus();
            }
        });

        // Form validation
        const form = document.querySelector('.form-card');
        if (form) {
            form.addEventListener('submit', function(e) {
                const textarea = this.querySelector('textarea[name="description"]');
                if (textarea.value.trim().length < 3) {
                    e.preventDefault();
                    alert('Please enter a meaningful medical note (at least 3 characters).');
                    textarea.focus();
                }
            });
        }
    </script>
</body>
</html>