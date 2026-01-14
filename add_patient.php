<?php
require_once "connection.php";
require_once "session_check.php"; // For authentication check

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user info for sidebar
$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Administrator';

$message = "";

// Function to auto-generate username
function generateUsername() {
    return "patient" . time();
}

// Function to auto-generate password
function generatePassword() {
    return "PT" . rand(10000, 99999);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $target_source = $_POST['source'] ?? 'Postgres';

    // Still generated (required for USER table), but NOT displayed
    $username_generated = generateUsername();
    $password_generated = generatePassword();

    // Patient details
    $name     = trim($_POST['name']);
    $gender   = trim($_POST['gender']);
    $dob      = trim($_POST['dob']);
    $ic_no    = trim($_POST['ic_no']);
    $address  = trim($_POST['address']);

    $success_count = 0;
    $attempt_count = 0;
    $errors = [];

    /* =========================
       1) MYSQL
    ========================= */
    if (($target_source === "MySQL" || $target_source === "All") && $mysql_conn2 instanceof mysqli) {
        $attempt_count++;
        try {
            $mysql_conn2->begin_transaction();

            // USER
            $stmtUser = $mysql_conn2->prepare(
                "INSERT INTO `USER` (USERNAME, PASSWORD, ROLE) VALUES (?, ?, 'patient')"
            );
            $stmtUser->bind_param("ss", $username_generated, $password_generated);
            $stmtUser->execute();
            $userId = $mysql_conn2->insert_id;
            $stmtUser->close();

            // PATIENT
            $stmtPatient = $mysql_conn2->prepare(
                "INSERT INTO PATIENT (USER_ID, NAME, GENDER, DOB, IC_NO, ADDRESS)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmtPatient->bind_param("isssss", $userId, $name, $gender, $dob, $ic_no, $address);
            $stmtPatient->execute();
            $stmtPatient->close();

            $mysql_conn2->commit();
            $success_count++;

        } catch (Exception $e) {
            $mysql_conn2->rollback();
            $errors[] = "MySQL: " . $e->getMessage();
        }
    }

    /* =========================
       2) POSTGRESQL
    ========================= */
    if (($target_source === "Postgres" || $target_source === "All") && $pg_conn instanceof PDO) {
        $attempt_count++;
        try {
            $pg_conn->beginTransaction();

            // USER
            $stmtUser = $pg_conn->prepare(
                'INSERT INTO "user" (username, password, role)
                 VALUES (:u, :p, \'patient\')
                 RETURNING user_id'
            );
            $stmtUser->execute([':u' => $username_generated, ':p' => $password_generated]);
            $newUserId = $stmtUser->fetchColumn();

            // PATIENT
            $stmtPatient = $pg_conn->prepare(
                'INSERT INTO patient (user_id, gender, dob, address, ic_no, name)
                 VALUES (:uid, :g, :dob, :addr, :ic, :n)'
            );
            $stmtPatient->execute([
                ':uid'  => $newUserId,
                ':g'    => $gender,
                ':dob'  => $dob,
                ':addr' => $address,
                ':ic'   => $ic_no,
                ':n'    => $name
            ]);

            $pg_conn->commit();
            $success_count++;

        } catch (Exception $e) {
            if ($pg_conn->inTransaction()) $pg_conn->rollBack();
            $errors[] = "Postgres: " . $e->getMessage();
        }
    }

    /* =========================
       3) SQL SERVER (PDO) - FIXED: Use $pdo_sqlsrv
    ========================= */
    if (($target_source === "SQLServer" || $target_source === "All") && isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) {
        $attempt_count++;
        try {
            $pdo_sqlsrv->beginTransaction();

            // USER
            $stmtUser = $pdo_sqlsrv->prepare("INSERT INTO [USER] (username, password, role) VALUES (?, ?, 'patient')");
            $stmtUser->execute([$username_generated, $password_generated]);

            $newUserId = $pdo_sqlsrv->query("SELECT SCOPE_IDENTITY()")->fetchColumn();

            // PATIENT
            $stmtPatient = $pdo_sqlsrv->prepare(
                "INSERT INTO patient (user_id, gender, dob, address, ic_no, name)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmtPatient->execute([$newUserId, $gender, $dob, $address, $ic_no, $name]);

            $pdo_sqlsrv->commit();
            $success_count++;

        } catch (Exception $e) {
            if ($pdo_sqlsrv->inTransaction()) $pdo_sqlsrv->rollBack();
            $errors[] = "SQL Server: " . $e->getMessage();
        }
    }

    /* =========================
       FINAL MESSAGE (NO USER/PASS)
    ========================= */
    if ($success_count > 0) {
        $message = "
        <div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-bottom: 20px;'>
            <i class='fas fa-check-circle'></i> <strong>Success!</strong> Patient added successfully to <b>$success_count</b> out of <b>$attempt_count</b> database(s).
        </div>";
    } else {
        $message = "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545; margin-bottom: 20px;'>
            <i class='fas fa-exclamation-triangle'></i> <strong>Error:</strong> Failed to add patient.<br>" . implode("<br>", array_map("htmlspecialchars", $errors)) . "
        </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Patient | PHARMACY SYSTEM</title>
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

        /* Form Container */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .form-header {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
            padding: 20px;
        }

        .form-header h2 {
            font-size: 1.3em;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-body {
            padding: 30px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: var(--blue-accent);
            font-size: 0.9em;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: white;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .form-control:focus {
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 3px rgba(28, 73, 102, 0.15);
            outline: none;
        }

        .form-control:hover {
            border-color: var(--blue-medium);
        }

        select.form-control {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%231c4966' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
            appearance: none;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        /* Database Options */
        .database-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .db-option {
            position: relative;
        }

        .db-option input[type="radio"] {
            display: none;
        }

        .db-option label {
            display: block;
            padding: 15px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .db-option input[type="radio"]:checked + label {
            background: var(--blue-light);
            border-color: var(--dark-blue);
            color: var(--dark-blue);
            font-weight: 600;
        }

        .db-option label:hover {
            border-color: var(--blue-medium);
        }

        /* Buttons */
        .form-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
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

        /* Information Box */
        .info-box {
            background: var(--blue-light);
            border-left: 4px solid var(--dark-blue);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
        }

        .info-box p {
            margin: 0;
            color: var(--dark-grey);
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
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
            
            .database-options {
                grid-template-columns: 1fr;
            }
            
            .form-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
            
            .form-body {
                padding: 20px;
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
                    <h1>Add New Patient</h1>
                    <p>Register new patient records in the system</p>
                </div>
                <div class="header-actions">
                    <a href="patient_management.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Patients
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

                <!-- Message Display -->
                <?php if (!empty($message)): ?>
                    <?php echo $message; ?>
                <?php endif; ?>

                <div class="form-container">
                    <!-- Information Box -->
                    <div class="info-box">
                        <p><i class="fas fa-info-circle"></i> Patient account credentials are automatically generated and linked internally. Only basic information is required.</p>
                    </div>

                    <!-- Form Card -->
                    <div class="form-card">
                        <div class="form-header">
                            <h2><i class="fas fa-user-plus"></i> Patient Registration Form</h2>
                        </div>

                        <form method="POST" class="form-body">
                            <!-- Database Selection -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-database"></i> Target Database(s)
                                </label>
                                <div class="database-options">
                                    <div class="db-option">
                                        <input type="radio" id="postgres" name="source" value="Postgres" <?php echo (($_POST['source'] ?? 'Postgres') == 'Postgres') ? 'checked' : ''; ?>>
                                        <label for="postgres">
                                            <i class="fas fa-database"></i><br>PostgreSQL Only
                                        </label>
                                    </div>
                                    <div class="db-option">
                                        <input type="radio" id="mysql" name="source" value="MySQL" <?php echo (($_POST['source'] ?? '') == 'MySQL') ? 'checked' : ''; ?>>
                                        <label for="mysql">
                                            <i class="fas fa-database"></i><br>MySQL Only
                                        </label>
                                    </div>
                                    <div class="db-option">
                                        <input type="radio" id="sqlserver" name="source" value="SQLServer" <?php echo (($_POST['source'] ?? '') == 'SQLServer') ? 'checked' : ''; ?>>
                                        <label for="sqlserver">
                                            <i class="fas fa-database"></i><br>SQL Server Only
                                        </label>
                                    </div>
                                    <div class="db-option">
                                        <input type="radio" id="all" name="source" value="All" <?php echo (($_POST['source'] ?? '') == 'All') ? 'checked' : ''; ?>>
                                        <label for="all">
                                            <i class="fas fa-globe"></i><br>All Databases
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Personal Information Section -->
                            <h3 style="color: var(--dark-blue); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--blue-light);">
                                <i class="fas fa-user-circle"></i> Personal Information
                            </h3>

                            <div class="form-group">
                                <label class="form-label" for="name">
                                    <i class="fas fa-user"></i> Full Name
                                </label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                       required placeholder="Enter patient's full name">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="gender">
                                    <i class="fas fa-venus-mars"></i> Gender
                                </label>
                                <select id="gender" name="gender" class="form-control" required>
                                    <option value="">Select gender...</option>
                                    <option value="Male" <?php echo (($_POST['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (($_POST['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="dob">
                                    <i class="fas fa-calendar-alt"></i> Date of Birth
                                </label>
                                <input type="date" id="dob" name="dob" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>" 
                                       required max="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="ic_no">
                                    <i class="fas fa-id-card"></i> IC Number
                                </label>
                                <input type="text" id="ic_no" name="ic_no" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['ic_no'] ?? ''); ?>" 
                                       required placeholder="e.g. 990101-01-1234" 
                                       pattern="[0-9]{6}-[0-9]{2}-[0-9]{4}" 
                                       title="Format: 990101-01-1234">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="address">
                                    <i class="fas fa-home"></i> Address
                                </label>
                                <textarea id="address" name="address" class="form-control" 
                                          placeholder="Enter patient's complete address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>

                            <!-- Form Buttons -->
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Add Patient
                                </button>
                                <a href="patient_management.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
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
                if (link.getAttribute('href') === 'patient_management.php') {
                    link.classList.add('active');
                }
            });

            // IC Number formatting
            const icInput = document.getElementById('ic_no');
            if (icInput) {
                icInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/[^\d-]/g, '');
                    if (value.length > 6 && value.length <= 12) {
                        value = value.replace(/(\d{6})(\d{2})(\d{4})/, '$1-$2-$3');
                    }
                    e.target.value = value;
                });
            }

            // Today's date for max DOB
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('dob').setAttribute('max', today);
        });
    </script>
</body>
</html>