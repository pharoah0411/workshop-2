<?php
require_once "session_check.php";   // ✅ auto logout + login check
require_once 'connection.php';

// Check if user is NOT logged in. If not, redirect to login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 🔐 Logged in but forced to reset password
if (!empty($_SESSION['force_reset'])) {
    header("Location: reset_password.php");
    exit;
}

$userRole = $_SESSION['role'] ?? 'Guest';
$username = $_SESSION['username'] ?? 'User';
$message = "";

// --- 1. HANDLE BILL FINALIZATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize_bill') {
    $presc_id = intval($_POST['prescription_id']);
    $total_amt = floatval($_POST['total_amount']);
    $target_source = $_POST['source'] ?? '';
    $current_date = date('Y-m-d'); 

    if ($presc_id > 0) {
        try {
            $success = false;
            $sql_update = "UPDATE PRESCRIPTION SET STATUS = 'Completed' WHERE PRESCRIPTION_ID = ?";
            $sql_payment = "INSERT INTO PAYMENT (PRESCRIPTION_ID, PAYMENT_DATE, TOTAL_AMOUNT) VALUES (?, ?, ?)";

            if ($target_source === 'MySQL' && isset($mysql_conn2)) {
                $mysql_conn2->begin_transaction();
                $stmt1 = $mysql_conn2->prepare($sql_update);
                $stmt1->bind_param("i", $presc_id);
                $stmt1->execute();
                $stmt2 = $mysql_conn2->prepare($sql_payment);
                $stmt2->bind_param("isd", $presc_id, $current_date, $total_amt);
                $stmt2->execute();
                $mysql_conn2->commit();
                $success = true;
            }
            elseif (($target_source === 'Postgres' && isset($pg_conn)) || ($target_source === 'SQLServer' && isset($pdo))) {
                $conn = ($target_source === 'Postgres') ? $pg_conn : $pdo;
                $conn->beginTransaction();
                $conn->prepare($sql_update)->execute([$presc_id]);
                $conn->prepare($sql_payment)->execute([$presc_id, $current_date, $total_amt]);
                $conn->commit();
                $success = true;
            }

            if ($success) {
                $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> ✅ Success: Payment recorded. <a href='Sales_Billing.php?bill_id=$presc_id&source=$target_source&print=1' class='btn btn-primary ms-3'><i class='fas fa-print'></i> Print Receipt Now</a></div>";
            }
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> ❌ Error: " . $e->getMessage() . "</div>";
        }
    }
}

// --- 2. FETCH INVOICE/RECEIPT DETAILS ---
$invoice_items = [];
$selected_presc_id = filter_input(INPUT_GET, 'bill_id', FILTER_VALIDATE_INT);
$selected_source = $_GET['source'] ?? '';
$is_print_mode = isset($_GET['print']);
$patient_name = "N/A";
$grand_total = 0;

if ($selected_presc_id && $selected_source) {
    try {
        $invoice_sql = "SELECT m.NAME as MED_NAME, pd.QUANTITY, m.UNIT_PRICE, p.NAME as PT_NAME 
                        FROM PRESCRIPTION_DETAIL pd 
                        JOIN MEDICINE m ON pd.MEDICINE_ID = m.MEDICINE_ID 
                        JOIN PRESCRIPTION pr ON pd.PRESCRIPTION_ID = pr.PRESCRIPTION_ID
                        JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
                        WHERE pd.PRESCRIPTION_ID = ?";

        if ($selected_source === 'MySQL' && isset($mysql_conn2)) {
            $stmt = $mysql_conn2->prepare($invoice_sql);
            $stmt->bind_param("i", $selected_presc_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()) $invoice_items[] = array_change_key_case($row, CASE_UPPER);
        } else {
            $conn_to_use = ($selected_source === 'Postgres') ? $pg_conn : $pdo;
            if ($conn_to_use) {
                $stmt = $conn_to_use->prepare($invoice_sql);
                $stmt->execute([$selected_presc_id]);
                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) $invoice_items[] = array_change_key_case($row, CASE_UPPER);
            }
        }

        if (!empty($invoice_items)) {
            $patient_name = $invoice_items[0]['PT_NAME'] ?? "Unknown Patient";
            foreach($invoice_items as $item) {
                $grand_total += (float)($item['QUANTITY'] ?? 0) * (float)($item['UNIT_PRICE'] ?? 0);
            }
        }
    } catch (Exception $e) { }
}

// --- 3. FETCH ALL BILLS (With Sorting & Search) ---
$search = $_GET['search'] ?? '';
$prescriptions = [];
$list_sql = "SELECT pr.PRESCRIPTION_ID, pr.STATUS, p.NAME AS PATIENT_NAME
             FROM PRESCRIPTION pr
             JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID";

try {
    if (isset($mysql_conn2)) {
        $res = $mysql_conn2->query($list_sql);
        if ($res) while ($row = $res->fetch_assoc()) { $r = array_change_key_case($row, CASE_UPPER); $r['SOURCE'] = 'MySQL'; $prescriptions[] = $r; }
    }
    if (isset($pg_conn)) {
        $stmt = $pg_conn->query($list_sql);
        if ($stmt) while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $r = array_change_key_case($row, CASE_UPPER); $r['SOURCE'] = 'Postgres'; $prescriptions[] = $r; }
    }
    if (isset($pdo)) {
        $stmt = $pdo->query($list_sql);
        if ($stmt) foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $r = array_change_key_case($row, CASE_UPPER); $r['SOURCE'] = 'SQLServer'; $prescriptions[] = $r; }
    }
} catch (Exception $e) { }

// Search Filter
if ($search) {
    $prescriptions = array_filter($prescriptions, function($p) use ($search) {
        $q = strtolower($search);
        return (strpos(strtolower($p['PATIENT_NAME'] ?? ''), $q) !== false || strpos(strval($p['PRESCRIPTION_ID'] ?? ''), $q) !== false);
    });
}

// Custom Sort: Pending First, then Completed
usort($prescriptions, function($a, $b) {
    if ($a['STATUS'] === $b['STATUS']) {
        return (int)($b['PRESCRIPTION_ID'] ?? 0) - (int)($a['PRESCRIPTION_ID'] ?? 0);
    }
    return ($a['STATUS'] === 'Pending') ? -1 : 1;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales & Billing | Pharmacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* Medical Professional Color Scheme with Dark Blue - EXACT SAME AS YOUR CODE */
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

        /* Sidebar - Dark Blue - EXACT SAME */
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

        /* Navigation Menu - EXACT SAME */
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

        /* Main Content Area - EXACT SAME */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header - EXACT SAME */
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

        /* Content Area - EXACT SAME */
        .content-wrapper {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: var(--main-bg);
        }

        /* Welcome Section - EXACT SAME */
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

        /* Additional styles for Sales & Billing */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success-green);
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--alert-red);
        }

        /* Invoice Card */
        .invoice-card {
            background: white;
            border: 2px solid var(--dark-blue);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        /* Table Styling */
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }

        thead {
            background: var(--dark-blue);
            color: white;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9em;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
        }

        tbody tr:hover {
            background-color: var(--blue-light);
            transition: background-color 0.2s ease;
        }

        /* Source Badges */
        .src-badge {
            font-size: 0.7em;
            padding: 3px 8px;
            border-radius: 15px;
            color: white;
            font-weight: 600;
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }

        .src-MySQL {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .src-Postgres {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .src-SQLServer {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        /* Status Badges */
        .status-badge {
            font-size: 0.8em;
            padding: 4px 10px;
            border-radius: 15px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Buttons */
        .btn {
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            font-weight: 600;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85em;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--dark-blue);
        }

        .btn-primary:hover {
            background: var(--blue-medium);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(28, 73, 102, 0.2);
            color: white;
        }

        .btn-success {
            background: var(--success-green);
        }

        .btn-success:hover {
            background: #4cae4c;
            transform: translateY(-2px);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
        }

        /* Search Bar */
        .search-bar {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9em;
            background: white;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .search-bar:focus {
            outline: none;
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1);
        }

        /* Patient Link */
        .patient-link {
            color: var(--dark-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
            font-size: 0.9em;
        }

        .patient-link:hover {
            color: var(--blue-medium);
            text-decoration: underline;
        }

        /* Print Styles */
        @media print {
            .no-print, .sidebar, .main-header, .search-box, .alert, .btn {
                display: none !important;
            }
            body {
                background: white;
                padding: 0;
            }
            .dashboard-container {
                box-shadow: none;
                border: none;
                max-width: 100%;
                height: auto;
            }
            .invoice-card {
                border: 2px solid #000;
                box-shadow: none;
            }
        }

        /* Responsive Design - EXACT SAME AS YOUR CODE */
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
            
            .welcome-section {
                padding: 20px;
            }
            
            .invoice-card {
                padding: 15px;
            }
            
            table {
                font-size: 0.8em;
            }
            
            th, td {
                padding: 8px;
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
        }
    </style>
</head>
<body <?= $is_print_mode ? 'onload="window.print()"' : '' ?>>
    <div class="dashboard-container">
        <!-- Sidebar - EXACT SAME STRUCTURE -->
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
                    <div class="nav-title">Medical Operations</div>
                    <ul class="nav-links">
                        <li><a href="dashboard.php"><i class="fas fa-home nav-icon"></i>Dashboard</a></li>
                        <li><a href="medDirectory.php"><i class="fas fa-pills nav-icon"></i>Medicine Inventory</a></li>
                        <li><a href="prescriptionDashboard.php"><i class="fas fa-prescription nav-icon"></i>Prescriptions</a></li>
                        <li><a href="Sales_Billing.php" class="active"><i class="fas fa-cash-register nav-icon"></i>Sales & Billing</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-title">Administration</div>
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
                    <h1>Sales & Billing</h1>
                    <p>Pharmacy Management - <?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" placeholder="Search prescriptions...">
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Welcome Section -->
                <section class="welcome-section">
                    <div class="welcome-text">
                        <h2><i class="fas fa-cash-register"></i> Sales & Billing Console</h2>
                        <p>Process payments, generate invoices, and manage billing records</p>
                    </div>
                </section>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="no-print"><?= $message ?></div>
                <?php endif; ?>

                <!-- Invoice Details Section -->
                <?php if ($selected_presc_id): ?>
                <div class="invoice-card" id="printableInvoice">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                        <div>
                            <h2 style="color:var(--dark-blue); font-size: 1.2em;">
                                <i class="fas fa-file-invoice-dollar"></i> 
                                <?= $is_print_mode ? 'OFFICIAL RECEIPT' : 'Invoice Details' ?>
                            </h2>
                            <p style="color:var(--text-secondary); font-size: 0.9em;">
                                Prescription #<?= htmlspecialchars($selected_presc_id ?? '') ?> 
                                <span class="src-badge src-<?= htmlspecialchars($selected_source ?? '') ?> ms-2">
                                    <?= htmlspecialchars($selected_source ?? '') ?>
                                </span>
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <p style="color:var(--dark-grey); font-size: 0.9em;"><strong>Date:</strong> <?= date('d-M-Y') ?></p>
                            <p style="color:var(--dark-grey); font-size: 0.9em;"><strong>Time:</strong> <?= date('h:i A') ?></p>
                        </div>
                    </div>
                    
                    <hr style="margin: 15px 0; border-color: var(--border-color);">
                    
                    <div style="margin-bottom: 15px;">
                        <p style="font-size: 1em;">
                            <strong style="color:var(--dark-blue);">Patient Name:</strong> 
                            <span style="color:var(--dark-grey);"><?= htmlspecialchars($patient_name ?? 'N/A') ?></span>
                        </p>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($invoice_items as $item): 
                                    $qty = $item['QUANTITY'] ?? 0;
                                    $u_price = $item['UNIT_PRICE'] ?? 0;
                                    $subtotal = (float)$qty * (float)$u_price;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['MED_NAME'] ?? 'Unknown') ?></td>
                                    <td><?= htmlspecialchars($qty) ?></td>
                                    <td>RM <?= number_format((float)$u_price, 2) ?></td>
                                    <td>RM <?= number_format($subtotal, 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background-color: var(--blue-light);">
                                    <td colspan="3" style="text-align:right; font-weight:bold; font-size: 1em; padding: 12px;">
                                        Total Amount:
                                    </td>
                                    <td style="font-size: 1.2em; color:var(--success-green); font-weight:bold; padding: 12px;">
                                        RM <?= number_format((float)$grand_total, 2) ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="no-print" style="margin-top:25px; display:flex; gap:10px;">
                        <?php 
                        // Check current status for action button logic
                        $current_status = '';
                        foreach($prescriptions as $p) {
                            if($p['PRESCRIPTION_ID'] == $selected_presc_id && $p['SOURCE'] == $selected_source) {
                                $current_status = $p['STATUS'];
                                break;
                            }
                        }
                        
                        if (!$is_print_mode && $current_status === 'Pending'): ?>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="finalize_bill">
                                <input type="hidden" name="prescription_id" value="<?= htmlspecialchars($selected_presc_id ?? '') ?>">
                                <input type="hidden" name="source" value="<?= htmlspecialchars($selected_source ?? '') ?>">
                                <input type="hidden" name="total_amount" value="<?= htmlspecialchars($grand_total ?? 0) ?>">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle"></i> Mark as Paid
                                </button>
                            </form>
                        <?php else: ?>
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="fas fa-print"></i> Print Receipt
                            </button>
                        <?php endif; ?>
                        <a href="Sales_Billing.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Main Billing List -->
                <div class="no-print">
                    <form method="GET" class="mb-4">
                        <input type="text" name="search" class="search-bar" 
                               placeholder="🔍 Search by Patient Name or Prescription ID..." 
                               value="<?= htmlspecialchars($search ?? '') ?>">
                    </form>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Prescription ID</th>
                                    <th>Patient Name</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($prescriptions)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-secondary);">
                                            <i class="fas fa-inbox" style="font-size: 2.5em; margin-bottom: 10px; display: block; color: var(--border-color);"></i>
                                            No prescriptions found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($prescriptions as $p): $src=$p['SOURCE']; ?>
                                    <tr>
                                        <td>
                                            <span class="src-badge src-<?= htmlspecialchars($src ?? '') ?>">
                                                <?= htmlspecialchars($src ?? '') ?>
                                            </span>
                                        </td>
                                        <td><strong>#<?= htmlspecialchars($p['PRESCRIPTION_ID'] ?? '') ?></strong></td>
                                        <td>
                                            <a href="Sales_Billing.php?bill_id=<?= htmlspecialchars($p['PRESCRIPTION_ID'] ?? '') ?>&source=<?= htmlspecialchars($src ?? '') ?>" 
                                               class="patient-link">
                                                <?= htmlspecialchars($p['PATIENT_NAME'] ?? 'Unknown') ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($p['STATUS'] ?? '') ?>">
                                                <?= htmlspecialchars($p['STATUS'] ?? '') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if(($p['STATUS'] ?? '') === 'Pending'): ?>
                                                <a href="Sales_Billing.php?bill_id=<?= htmlspecialchars($p['PRESCRIPTION_ID'] ?? '') ?>&source=<?= htmlspecialchars($src ?? '') ?>" 
                                                   class="btn btn-success" style="font-size:0.8em;">
                                                    <i class="fas fa-cash-register"></i> Bill Now
                                                </a>
                                            <?php else: ?>
                                                <a href="Sales_Billing.php?bill_id=<?= htmlspecialchars($p['PRESCRIPTION_ID'] ?? '') ?>&source=<?= htmlspecialchars($src ?? '') ?>&print=1" 
                                                   class="btn btn-secondary" style="font-size:0.8em;">
                                                    <i class="fas fa-print"></i> Reprint
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Sidebar navigation active state - EXACT SAME AS YOUR CODE
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function(e) {
                // Store active page in sessionStorage before navigation
                sessionStorage.setItem('activePage', this.getAttribute('href'));
                // The actual navigation will happen via the href attribute
            });
        });

        // Restore active page on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const activePage = sessionStorage.getItem('activePage') || 'Sales_Billing.php';
            
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
                    window.location.href = 'Sales_Billing.php?search=' + encodeURIComponent(searchTerm);
                }
            }
        });

        // Auto-submit search form
        document.querySelector('.search-bar').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // Clear search on escape
        document.querySelector('.search-box input').addEventListener('keyup', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
            }
        });

        // Update time display
        function updateTime() {
            const now = new Date();
            const timeElement = document.querySelector('.header-title p');
            if (timeElement) {
                timeElement.textContent = `Pharmacy Management - ${now.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                })}`;
            }
        }

        // Initialize
        updateTime();
    </script>
</body>
</html>