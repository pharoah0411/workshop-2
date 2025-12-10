<?php
session_start();
require_once 'connection.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'Staff';
$username = $_SESSION['username'] ?? 'User';

// --- Determine Connection Status ---
$status_mysql2 = (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? "✅" : "❌";
$status_pg = (isset($pg_conn) && $pg_conn instanceof PDO) ? "✅" : "❌";
$status_sql = ((isset($pdo) && $pdo instanceof PDO) || (isset($conn) && $conn !== false)) ? "✅" : "❌";

// --- Handle Status Update POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $presc_id = intval($_POST['prescription_id']);
    $new_status = $_POST['status'];
    $target_source = $_POST['source'] ?? ''; // NEW: Target specific DB

    if ($presc_id > 0 && !empty($new_status)) {
        try {
            // 1. Update MySQL #2
            if ($target_source === 'MySQL' && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
                $stmt = $mysql_conn2->prepare("UPDATE PRESCRIPTION SET STATUS = ? WHERE PRESCRIPTION_ID = ?");
                $stmt->bind_param("si", $new_status, $presc_id);
                $stmt->execute();
            }
            // 2. Update PostgreSQL
            if ($target_source === 'Postgres' && isset($pg_conn) && $pg_conn instanceof PDO) {
                $stmt = $pg_conn->prepare("UPDATE PRESCRIPTION SET STATUS = :status WHERE PRESCRIPTION_ID = :id");
                $stmt->execute([':status' => $new_status, ':id' => $presc_id]);
            }
            // 3. Update SQL Server
            if ($target_source === 'SQLServer') {
                if (isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->prepare("UPDATE PRESCRIPTION SET STATUS = :status WHERE PRESCRIPTION_ID = :id");
                    $stmt->execute([':status' => $new_status, ':id' => $presc_id]);
                } elseif (isset($conn) && $conn !== false) {
                    $sql = "UPDATE PRESCRIPTION SET STATUS = ? WHERE PRESCRIPTION_ID = ?";
                    sqlsrv_query($conn, $sql, [$new_status, $presc_id]);
                }
            }
        } catch (Exception $e) { /* Ignore */ }
    }
    // Redirect to self
    header('Location: prescriptiondashboard.php');
    exit;
}

// Search & Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$prescriptions = [];
$error = '';

// Query Logic
$sql = "SELECT 
            pr.PRESCRIPTION_ID, 
            pr.DATE_ISSUED, 
            pr.STATUS,
            p.NAME AS PATIENT_NAME,
            u.NAME AS PHARMACIST_NAME
        FROM PRESCRIPTION pr
        JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
        LEFT JOIN [USER] u ON pr.PHARMACIST_ID = u.USER_ID
        WHERE 1=1";

// Note: For MySQL/Postgres, we might need to adjust table quoting slightly if strict
// but typically standard SQL works if names are uppercase.
// Postgres: "USER" is reserved, so we handle that in specific blocks.

try {
    // 1. MySQL #2
    if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
        // MySQL uses backticks for reserved words
        $m_sql = str_replace(['[USER]', '1=1'], ['`USER`', '1=1'], $sql); 
        $result = $mysql_conn2->query($m_sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['SOURCE'] = 'MySQL';
                $prescriptions[] = $r;
            }
        }
    }

    // 2. PostgreSQL
    if (isset($pg_conn) && $pg_conn instanceof PDO) {
        // Postgres uses double quotes for reserved words
        $p_sql = str_replace(['[USER]', '1=1'], ['"user"', '1=1'], $sql);
        $stmt = $pg_conn->query($p_sql);
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['SOURCE'] = 'Postgres';
                $prescriptions[] = $r;
            }
        }
    }

    // 3. SQL Server
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query($sql); // [USER] is correct for T-SQL
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $r = array_change_key_case($r, CASE_UPPER);
                $r['SOURCE'] = 'SQLServer';
                $prescriptions[] = $r;
            }
        }
    } elseif (isset($conn) && $conn !== false) {
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $r = array_change_key_case($row, CASE_UPPER);
                $r['SOURCE'] = 'SQLServer';
                $prescriptions[] = $r;
            }
        }
    }
} catch (Exception $e) { $error = $e->getMessage(); }

// Filter in PHP (Simpler than merging SQL WHERE clauses across different DB syntaxes)
$prescriptions = array_filter($prescriptions, function($p) use ($search, $status_filter) {
    // Status Filter
    if ($status_filter && strcasecmp($p['STATUS'], $status_filter) !== 0) return false;
    
    // Search Filter
    if ($search) {
        $q = strtolower($search);
        $found = false;
        if (strpos(strtolower($p['PATIENT_NAME'] ?? ''), $q) !== false) $found = true;
        if (strpos(strval($p['PRESCRIPTION_ID']), $q) !== false) $found = true;
        if (!$found) return false;
    }
    return true;
});

// Sort by ID DESC
usort($prescriptions, function($a, $b) {
    return $b['PRESCRIPTION_ID'] - $a['PRESCRIPTION_ID'];
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription Dashboard</title>
    <style>
        /* Shared CSS */
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); min-height:100vh; padding:20px; }
        .container { max-width:1200px; margin:0 auto; background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.2); }
        .top-nav { display:flex; justify-content:space-between; align-items:center; padding:10px 30px; background:#1565c0; color:white; margin-bottom:20px; border-radius:8px; }
        .nav-links a { color:white; text-decoration:none; margin-left:15px; font-weight:500; }
        .btn-logout { padding:6px 12px; border:1px solid white; border-radius:6px; background:transparent; color:white; cursor:pointer; }
        .header { background:linear-gradient(135deg,#0066ff 0%,#0099ff 100%); color:white; padding:30px; text-align:center; }
        .content { padding:30px; }
        .controls { display:flex; gap:15px; margin-bottom:20px; background:#f8f9fa; padding:15px; border-radius:8px; align-items:center; }
        .btn { padding:10px 20px; border:none; border-radius:6px; cursor:pointer; text-decoration:none; color:white; font-weight:600; display:inline-block; }
        .btn-primary { background:#28a745; }
        .btn-blue { background:#0066ff; }
        input, select { padding:10px; border:1px solid #ddd; border-radius:6px; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th, td { padding:12px 15px; text-align:left; border-bottom:1px solid #eee; }
        th { background:#f1f1f1; color:#333; }
        tr:hover { background:#f9f9f9; }
        .badge { padding:4px 8px; border-radius:4px; font-size:0.85em; font-weight:bold; }
        .bg-Pending { background:#fff3cd; color:#856404; }
        .bg-Completed { background:#d4edda; color:#155724; }
        .bg-Cancelled { background:#f8d7da; color:#721c24; }
        .src-badge { font-size:0.75em; padding:2px 6px; border-radius:4px; color:white; margin-right:5px; }
        .src-MySQL { background:#f39c12; }
        .src-Postgres { background:#3498db; }
        .src-SQLServer { background:#e74c3c; }
        .status-bar { display:flex; gap:15px; color:#fff; font-size:0.9em; margin-bottom:10px; }
    </style>
</head>
<body>
    <header class="top-nav">
        <div>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></div>
        <div class="nav-links">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="medDirectory.php">📦 Medicines</a>
            <a href="prescriptiondashboard.php">📝 Prescriptions</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </header>

    <div class="status-bar">
        <span>MySQL: <?php echo $status_mysql2; ?></span>
        <span>Postgres: <?php echo $status_pg; ?></span>
        <span>SQL Server: <?php echo $status_sql; ?></span>
    </div>

    <div class="container">
        <header class="header">
            <h1>📝 Prescription Dashboard</h1>
            <p>Manage patient prescriptions from all databases</p>
        </header>

        <div class="content">
            <div class="controls">
                <a href="createPrescription.php" class="btn btn-primary">+ New Prescription</a>
                <form method="GET" style="display:flex; gap:10px; flex:1;">
                    <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;">
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php if($status_filter=='Pending') echo 'selected'; ?>>Pending</option>
                        <option value="Completed" <?php if($status_filter=='Completed') echo 'selected'; ?>>Completed</option>
                        <option value="Cancelled" <?php if($status_filter=='Cancelled') echo 'selected'; ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="btn btn-blue">Filter</button>
                </form>
            </div>

            <?php if($error): ?>
                <div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:5px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>ID</th>
                        <th>Patient Name</th>
                        <th>Pharmacist</th>
                        <th>Date Issued</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                        <tr><td colspan="7" style="text-align:center;">No prescriptions found.</td></tr>
                    <?php else: ?>
                        <?php foreach($prescriptions as $p): 
                            $date = $p['DATE_ISSUED'];
                            if($date instanceof DateTime) $date = $date->format('Y-m-d H:i');
                            $src = $p['SOURCE'];
                        ?>
                        <tr>
                            <td><span class="src-badge src-<?php echo $src; ?>"><?php echo $src; ?></span></td>
                            <td>#<?php echo $p['PRESCRIPTION_ID']; ?></td>
                            <td><?php echo htmlspecialchars($p['PATIENT_NAME'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($p['PHARMACIST_NAME'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars(is_string($date)? $date : ''); ?></td>
                            <td><span class="badge bg-<?php echo $p['STATUS']; ?>"><?php echo $p['STATUS']; ?></span></td>
                            <td>
                                <a href="viewPrescription.php?id=<?php echo $p['PRESCRIPTION_ID']; ?>" style="color:#0066ff; margin-right:10px;">View</a>
                                <?php if($p['STATUS'] === 'Pending'): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Complete this prescription in <?php echo $src; ?>?');">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="prescription_id" value="<?php echo $p['PRESCRIPTION_ID']; ?>">
                                    <input type="hidden" name="source" value="<?php echo $src; ?>"> <input type="hidden" name="status" value="Completed">
                                    <button type="submit" style="border:none;background:none;color:green;cursor:pointer;text-decoration:underline;">Complete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>