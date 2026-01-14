<?php
require_once 'session_check.php';
require_once 'connection.php';

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'Staff';

// --- Determine Connection Status --- FIXED: Use $pdo_sqlsrv instead of $pdo
$status_mysql2 = (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? "✅ Connected" : "❌ Failed";
$status_pg = (isset($pg_conn) && $pg_conn instanceof PDO) ? "✅ Connected" : "❌ Failed";
$status_sql = (isset($pdo_sqlsrv) && $pdo_sqlsrv instanceof PDO) ? "✅ Connected" : "❌ Failed"; // FIXED HERE

// --- Handle Status Update --- FIXED: Use $pdo_sqlsrv
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $presc_id = intval($_POST['prescription_id']);
    $new_status = $_POST['status'];
    $target_source = $_POST['source'] ?? '';

    if ($presc_id > 0 && !empty($new_status)) {
        try {
            if ($target_source === 'MySQL' && isset($mysql_conn2)) {
                $stmt = $mysql_conn2->prepare("UPDATE PRESCRIPTION SET STATUS = ? WHERE PRESCRIPTION_ID = ?");
                $stmt->bind_param("si", $new_status, $presc_id);
                $stmt->execute();
            }
            if ($target_source === 'Postgres' && isset($pg_conn)) {
                $stmt = $pg_conn->prepare("UPDATE PRESCRIPTION SET STATUS = :status WHERE PRESCRIPTION_ID = :id");
                $stmt->execute([':status' => $new_status, ':id' => $presc_id]);
            }
            if ($target_source === 'SQLServer' && isset($pdo_sqlsrv)) { // FIXED HERE
                $stmt = $pdo_sqlsrv->prepare("UPDATE PRESCRIPTION SET STATUS = :status WHERE PRESCRIPTION_ID = :id");
                $stmt->execute([':status' => $new_status, ':id' => $presc_id]);
            }
        } catch (Exception $e) {}
    }
    header('Location: prescriptionDashboard.php');
    exit;
}

// --- Handle DELETE Prescription (FIXED: Use $pdo_sqlsrv) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_prescription') {
    $presc_id = intval($_POST['prescription_id']);
    $target_source = $_POST['source'] ?? '';

    if ($presc_id > 0) {
        try {
            if ($target_source === 'MySQL' && isset($mysql_conn2)) {
                $mysql_conn2->query("DELETE FROM PRESCRIPTION_DETAIL WHERE PRESCRIPTION_ID = $presc_id");
                $stmt = $mysql_conn2->prepare("DELETE FROM PRESCRIPTION WHERE PRESCRIPTION_ID = ?");
                $stmt->bind_param("i", $presc_id);
                $stmt->execute();
            }
            if ($target_source === 'Postgres' && isset($pg_conn)) {
                // DELETE PAYMENTS FIRST to avoid Foreign Key constraint errors
                $pg_conn->prepare("DELETE FROM public.payment WHERE prescription_id = ?")->execute([$presc_id]);
                $pg_conn->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE PRESCRIPTION_ID = ?")->execute([$presc_id]);
                $stmt = $pg_conn->prepare("DELETE FROM PRESCRIPTION WHERE PRESCRIPTION_ID = :id");
                $stmt->execute([':id' => $presc_id]);
            }
            if ($target_source === 'SQLServer' && isset($pdo_sqlsrv)) { // FIXED HERE
                $pdo_sqlsrv->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE PRESCRIPTION_ID = ?")->execute([$presc_id]);
                $stmt = $pdo_sqlsrv->prepare("DELETE FROM PRESCRIPTION WHERE PRESCRIPTION_ID = :id");
                $stmt->execute([':id' => $presc_id]);
            }
        } catch (Exception $e) {
            error_log("Delete Error: " . $e->getMessage());
        }
    }
    header('Location: prescriptionDashboard.php');
    exit;
}

// Search & Filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$prescriptions = [];
$error = '';

$sql = "SELECT pr.PRESCRIPTION_ID, pr.DATE_ISSUED, pr.STATUS, p.NAME AS PATIENT_NAME, u.NAME AS PHARMACIST_NAME
        FROM PRESCRIPTION pr
        JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
        LEFT JOIN [USER] u ON pr.PHARMACIST_ID = u.USER_ID";

try {
    if (isset($mysql_conn2)) {
        $m_sql = str_replace('[USER]', '`USER`', $sql);
        $res = $mysql_conn2->query($m_sql);
        if ($res) while ($row = $res->fetch_assoc()) {
            $r = array_change_key_case($row, CASE_UPPER);
            $r['SOURCE'] = 'MySQL';
            $prescriptions[] = $r;
        }
    }
    if (isset($pg_conn)) {
        $p_sql = str_replace('[USER]', '"user"', $sql);
        $stmt = $pg_conn->query($p_sql);
        if ($stmt) while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $r = array_change_key_case($row, CASE_UPPER);
            $r['SOURCE'] = 'Postgres';
            $prescriptions[] = $r;
        }
    }
    if (isset($pdo_sqlsrv)) { // FIXED HERE
        $stmt = $pdo_sqlsrv->query($sql);
        if ($stmt) foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $r = array_change_key_case($row, CASE_UPPER);
            $r['SOURCE'] = 'SQLServer';
            $prescriptions[] = $r;
        }
    }
} catch (Exception $e) { $error = $e->getMessage(); }

$prescriptions = array_filter($prescriptions, function($p) use ($search, $status_filter) {
    if ($status_filter && strcasecmp($p['STATUS'], $status_filter) !== 0) return false;
    if ($search) {
        $q = strtolower($search);
        return (strpos(strtolower($p['PATIENT_NAME'] ?? ''), $q) !== false || strpos(strval($p['PRESCRIPTION_ID']), $q) !== false);
    }
    return true;
});

// --- UPDATED SORTING LOGIC: PENDING ON TOP, COMPLETED ON BOTTOM ---
usort($prescriptions, function($a, $b) {
    // 1. Prioritize 'Pending' status
    $statusA = strtolower($a['STATUS'] ?? '');
    $statusB = strtolower($b['STATUS'] ?? '');

    if ($statusA === 'pending' && $statusB !== 'pending') {
        return -1; // $a comes first
    }
    if ($statusB === 'pending' && $statusA !== 'pending') {
        return 1; // $b comes first
    }

    // 2. If statuses are the same (both Pending or both Completed), sort by ID descending
    return (int)($b['PRESCRIPTION_ID'] ?? 0) - (int)($a['PRESCRIPTION_ID'] ?? 0);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHARMACY SYSTEM - Prescription Dashboard</title>
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

        /* Sidebar - Match Dashboard Colors */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1c4966 0%, #143852 100%);
            color: white;
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
            display: flex;
            flex-direction: column;
            overflow: hidden;
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

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 15px 10px 40px;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            width: 280px;
            font-size: 0.9em;
            background: white;
            transition: all 0.3s ease;
            font-weight: 300;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--dark-blue);
            box-shadow: 0 0 0 2px rgba(28, 73, 102, 0.1);
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
            padding: 25px;
            overflow-y: auto;
            background: #f5f7fa;
        }

        /* Database Status Cards */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .status-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e9ecef;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease;
        }

        .status-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .status-number {
            font-size: 2em;
            font-weight: 700;
            color: var(--dark-blue);
            margin: 10px 0;
        }

        .status-label {
            color: #6c757d;
            font-size: 0.85em;
            font-weight: 400;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .btn-primary {
            background: var(--dark-blue);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.9em;
        }

        .btn-primary:hover {
            background: var(--blue-medium);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: var(--dark-blue);
            border: 1px solid #ddd;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: var(--dark-blue);
        }

        /* Search and Filter */
        .search-filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .search-form {
            flex: 1;
            display: flex;
            gap: 10px;
        }

        .search-form input[type="text"] {
            flex: 1;
            padding: 10px 15px 10px 40px;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            font-size: 0.9em;
            background: white;
        }

        .search-form select {
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            font-size: 0.9em;
            background: white;
            cursor: pointer;
        }

        /* Table Styling */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .prescription-table {
            width: 100%;
            border-collapse: collapse;
        }

        .prescription-table thead {
            background: linear-gradient(135deg, var(--dark-blue), var(--blue-medium));
            color: white;
        }

        .prescription-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .prescription-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .prescription-table tr:hover {
            background-color: #f8f9fa;
        }

        .prescription-table tr:last-child td {
            border-bottom: none;
        }

        /* Source Badge */
        .src-badge {
            font-size: 0.75em;
            padding: 4px 8px;
            border-radius: 4px;
            color: white;
            font-weight: 600;
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

        /* Status Badge */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 100px;
        }

        .status-Pending {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-Completed {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Action Buttons in Table */
        .table-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .table-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }

        .table-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-view {
            background: linear-gradient(135deg, #4a90e2, #357ae8);
        }

        .btn-print {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }

        .btn-edit {
            background: linear-gradient(135deg, #9c27b0, #7b1fa2);
        }

        .btn-done {
            background: linear-gradient(135deg, #5cb85c, #4cae4c);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85em;
            transition: all 0.3s ease;
        }

        .btn-done:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(92, 184, 92, 0.3);
        }

        .btn-delete {
            background: linear-gradient(135deg, #e53935, #d32f2f);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-delete:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(229, 57, 53, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--dark-blue);
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: var(--dark-grey);
        }

        .empty-state p {
            font-size: 1em;
            color: var(--soft-grey);
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e9ecef;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .stat-number {
            font-size: 2em;
            font-weight: 700;
            color: var(--dark-blue);
            margin: 10px 0;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.85em;
            font-weight: 400;
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
            
            .table-actions {
                flex-wrap: wrap;
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
            
            .status-grid,
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons,
            .search-filter-container {
                flex-direction: column;
            }
            
            .prescription-table {
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
            
            .status-grid,
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .table-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table-btn,
            .btn-done,
            .btn-delete {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="pharmacy-logo">
                <h1><i class="fas fa-prescription"></i> PHARMACY SYSTEM</h1>
                <p>Professional Prescription Management</p>
            </div>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($username, 0, 2)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($role); ?></div>
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
                    <h1>Prescription Management</h1>
                    <p>Unified database tracking across all pharmacy systems</p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" placeholder="Search Patient or ID..." id="searchInput" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Database Status Cards -->
                <div class="status-grid">
                    <div class="status-card">
                        <div class="status-number"><?php echo count($prescriptions); ?></div>
                        <div class="status-label">Total Prescriptions</div>
                        <div style="font-size: 0.8em; color: #28a745; margin-top: 5px;">
                            <i class="fas fa-check-circle"></i> <?php echo $status_mysql2; ?>
                        </div>
                    </div>
                    <div class="status-card">
                        <div class="status-number">
                            <?php 
                                $pendingCount = 0;
                                foreach ($prescriptions as $p) {
                                    if (strtolower($p['STATUS']) === 'pending') $pendingCount++;
                                }
                                echo $pendingCount;
                            ?>
                        </div>
                        <div class="status-label">Pending</div>
                        <div style="font-size: 0.8em; color: <?php echo $status_pg === '✅ Connected' ? '#28a745' : '#dc3545'; ?>; margin-top: 5px;">
                            <i class="<?php echo $status_pg === '✅ Connected' ? 'fas fa-check-circle' : 'fas fa-times-circle'; ?>"></i> <?php echo $status_pg; ?>
                        </div>
                    </div>
                    <div class="status-card">
                        <div class="status-number">
                            <?php 
                                $completedCount = 0;
                                foreach ($prescriptions as $p) {
                                    if (strtolower($p['STATUS']) === 'completed') $completedCount++;
                                }
                                echo $completedCount;
                            ?>
                        </div>
                        <div class="status-label">Completed</div>
                        <div style="font-size: 0.8em; color: <?php echo $status_sql === '✅ Connected' ? '#28a745' : '#dc3545'; ?>; margin-top: 5px;">
                            <i class="<?php echo $status_sql === '✅ Connected' ? 'fas fa-check-circle' : 'fas fa-times-circle'; ?>"></i> <?php echo $status_sql; ?>
                        </div>
                    </div>
                    <div class="status-card">
                        <div class="status-number">
                            <?php 
                                $today = date('Y-m-d');
                                $todayCount = 0;
                                foreach ($prescriptions as $p) {
                                    $date = $p['DATE_ISSUED'];
                                    if (is_string($date) && substr($date, 0, 10) === $today) {
                                        $todayCount++;
                                    }
                                }
                                echo $todayCount;
                            ?>
                        </div>
                        <div class="status-label">Today's</div>
                        <div style="font-size: 0.8em; color: #28a745; margin-top: 5px;">
                            <i class="fas fa-check-circle"></i> All Connected
                        </div>
                    </div>
                </div>

                <!-- Action Buttons and Search -->
                <div class="action-buttons">
    <?php 
    // Convert to lowercase to avoid case-sensitivity issues
    $currentRole = strtolower($role); 
    
    if ($currentRole === 'pharmacist'): ?>
        <a href="createPrescription.php" class="btn-primary">
            <i class="fas fa-plus"></i> New Prescription
        </a>
    <?php else: ?>
        <a href="javascript:void(0);" class="btn-primary" 
           style="opacity: 0.6; cursor: not-allowed; pointer-events: none; background: #6c757d; border-color: #6c757d;" 
           title="Only Pharmacists can create new prescriptions">
            <i class="fas fa-plus"></i> New Prescription (Pharmacist Only)
        </a>
    <?php endif; ?>
    
    <a href="export_prescription.php" class="btn-secondary">
        <i class="fas fa-file-export"></i> Export Prescriptions
    </a>
</div>

                <!-- Search and Filter -->
                <form method="GET" class="search-filter-container">
                    <div class="search-form">
                        <input type="text" name="search" placeholder="Search by Patient Name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter=='Pending'?'selected':''; ?>>Pending</option>
                            <option value="Completed" <?php echo $status_filter=='Completed'?'selected':''; ?>>Completed</option>
                        </select>
                    </div>
                </form>

                <!-- Prescriptions Table -->
                <div class="table-container">
                    <?php if (empty($prescriptions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-prescription-bottle-alt"></i>
                            <h3>No Prescriptions Found</h3>
                            <p>Try adjusting your search or filter criteria</p>
                            <a href="createPrescription.php" class="btn-primary" style="margin-top: 20px; display: inline-block;">
                                <i class="fas fa-plus"></i> Create New Prescription
                            </a>
                        </div>
                    <?php else: ?>
                        <table class="prescription-table">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Date</th>
                                    <th>Pharmacist</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($prescriptions as $p): 
                                    $src = $p['SOURCE'];
                                    $date = $p['DATE_ISSUED'];
                                    if($date instanceof DateTime) {
                                        $date = $date->format('Y-m-d H:i');
                                    } elseif (is_string($date)) {
                                        // Format date properly
                                        $date = date('Y-m-d', strtotime($date));
                                    } else {
                                        $date = 'N/A';
                                    }
                                ?>
                                <tr>
                                    <td><span class="src-badge src-<?php echo $src; ?>"><?php echo $src; ?></span></td>
                                    <td><strong>#<?php echo $p['PRESCRIPTION_ID']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['PATIENT_NAME'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($date); ?></td>
                                    <td><?php echo htmlspecialchars($p['PHARMACIST_NAME'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $p['STATUS']; ?>">
                                            <?php echo $p['STATUS']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                          <a href="viewPrescription.php?id=<?php echo $p['PRESCRIPTION_ID']; ?>&source=<?php echo $src; ?>" class="table-btn btn-view" title="View">
                                              <i class="fas fa-eye"></i>
                                         </a>

                                             <a href="printLabel.php?id=<?php echo $p['PRESCRIPTION_ID']; ?>&source=<?php echo $src; ?>" class="table-btn btn-print" title="Print">
                                                 <i class="fas fa-print"></i>
                                             </a>

    <?php if (strtolower($role) === 'admin'): ?>
        <a href="javascript:void(0);" class="table-btn btn-edit" title="Edit Disabled for Admin" 
           style="opacity: 0.5; cursor: not-allowed; pointer-events: none; filter: grayscale(1);">
            <i class="fas fa-edit"></i>
        </a>
    <?php else: ?>
        <a href="editPrescription.php?id=<?php echo $p['PRESCRIPTION_ID']; ?>&source=<?php echo $src; ?>" class="table-btn btn-edit" title="Edit">
            <i class="fas fa-edit"></i>
        </a>
    <?php endif; ?>

    <?php if(strtolower($p['STATUS'] ?? '') === 'pending'): ?>
    <form method="POST" style="display:inline;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="prescription_id" value="<?php echo $p['PRESCRIPTION_ID']; ?>">
        <input type="hidden" name="source" value="<?php echo $src; ?>">
        <input type="hidden" name="status" value="Completed">
        <button type="submit" class="btn-done" onclick="return confirm('Mark prescription #<?php echo $p['PRESCRIPTION_ID']; ?> as Completed?')">
            Done
        </button>
    </form>
    <?php endif; ?>

    <form method="POST" style="display:inline;">
        <input type="hidden" name="action" value="delete_prescription">
        <input type="hidden" name="prescription_id" value="<?php echo $p['PRESCRIPTION_ID']; ?>">
        <input type="hidden" name="source" value="<?php echo $src; ?>">
        <button type="submit" class="btn-delete" onclick="return confirm('Delete prescription #<?php echo $p['PRESCRIPTION_ID']; ?>? This cannot be undone!')">
            <i class="fas fa-trash-alt"></i>
        </button>
    </form>
</div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
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
            const activePage = sessionStorage.getItem('activePage') || 'prescriptionDashboard.php';
            
            document.querySelectorAll('.nav-links a').forEach(link => {
                const linkPage = link.getAttribute('href');
                link.classList.remove('active');
                
                if (linkPage === currentPage || linkPage === activePage) {
                    link.classList.add('active');
                }
            });
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const searchTerm = this.value.trim();
                        if (searchTerm) {
                            window.location.href = 'prescriptionDashboard.php?search=' + encodeURIComponent(searchTerm);
                        }
                    }
                });
            }
            
            // Add hover effects to table rows
            document.querySelectorAll('.prescription-table tbody tr').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                    this.style.transition = 'transform 0.3s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
        });
    </script>
</body>
</html>