<?php
require_once 'session_check.php';
require_once 'connection.php';

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'Staff';

// --- Determine Connection Status ---
$status_mysql2 = (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) ? "✅" : "❌";
$status_pg = (isset($pg_conn) && $pg_conn instanceof PDO) ? "✅" : "❌";
$status_sql = ((isset($pdo) && $pdo instanceof PDO) || (isset($conn) && $conn !== false)) ? "✅" : "❌";

// --- Handle Status Update ---
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
            if ($target_source === 'SQLServer' && isset($pdo)) {
                $stmt = $pdo->prepare("UPDATE PRESCRIPTION SET STATUS = :status WHERE PRESCRIPTION_ID = :id");
                $stmt->execute([':status' => $new_status, ':id' => $presc_id]);
            }
        } catch (Exception $e) {}
    }
    header('Location: prescriptionDashboard.php');
    exit;
}

// --- Handle DELETE Prescription (FIXED: Handles Foreign Keys) ---
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
            if ($target_source === 'SQLServer' && isset($pdo)) {
                $pdo->prepare("DELETE FROM PRESCRIPTION_DETAIL WHERE PRESCRIPTION_ID = ?")->execute([$presc_id]);
                $stmt = $pdo->prepare("DELETE FROM PRESCRIPTION WHERE PRESCRIPTION_ID = :id");
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
    if (isset($pdo)) {
        $stmt = $pdo->query($sql);
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
<title>Prescription Dashboard | Pharmacy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', sans-serif; }
    body { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    .top-nav { display:flex; justify-content:space-between; align-items:center; padding:15px 30px; background:#1565c0; color:white; margin-bottom:15px; border-radius:10px; }
    .nav-links a { color:white; text-decoration:none; margin-left:15px; font-weight:500; }
    .header { background: #f8f9fa; padding: 30px; text-align: center; border-bottom: 1px solid #eee; }
    .header h1 { color: #1565c0; margin-bottom: 10px; }
    .content { padding: 30px; }
    .status-bar { display:flex; gap:15px; color:#fff; font-size:0.9em; margin-bottom:10px; padding: 0 10px; }
    .controls { display:flex; gap:15px; margin-bottom:20px; background:#f8f9fa; padding:15px; border-radius:8px; align-items:center; }
    .btn { padding:10px 15px; border-radius:6px; cursor:pointer; text-decoration:none; color:white; font-weight:600; border:none; display:inline-flex; align-items:center; gap:5px; }
    .btn-primary { background:#28a745; }
    .btn-blue { background:#0066ff; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:12px 15px; text-align:left; border-bottom:1px solid #eee; }
    th { background:#1565c0; color:white; }
    .badge { padding:4px 8px; border-radius:4px; font-size:0.85em; font-weight:bold; }
    .bg-Pending { background:#fff3cd; color:#856404; }
    .bg-Completed { background:#d4edda; color:#155724; }
    .src-badge { font-size:0.75em; padding:2px 6px; border-radius:4px; color:white; }
    .src-MySQL { background:#f39c12; }
    .src-Postgres { background:#3498db; }
    .src-SQLServer { background:#e74c3c; }
</style>
</head>
<body>

<header class="top-nav">
    <div>Welcome, <strong><?= htmlspecialchars($username) ?></strong></div>
    <div class="nav-links">
        <a href="dashboard.php">🏠 Home</a>
        <a href="medDirectory.php">📦 Medicines</a>
        <a href="prescriptionDashboard.php">📝 Prescriptions</a>
        <a href="logout.php">Logout</a>
    </div>
</header>

<div class="status-bar">
    <span>MySQL: <?= $status_mysql2 ?></span>
    <span>Postgres: <?= $status_pg ?></span>
    <span>SQL Server: <?= $status_sql ?></span>
</div>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-prescription"></i> Prescription Management</h1>
        <p>Unified database tracking across all pharmacy systems</p>
    </div>

    <div class="content">
        <div class="controls">
            <a href="createPrescription.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Prescription</a>
            <form method="GET" style="display:flex; gap:10px; flex:1;">
                <input type="text" name="search" placeholder="Search Patient or ID..." value="<?= htmlspecialchars($search) ?>" style="flex:1; padding:10px; border-radius:6px; border:1px solid #ddd;">
                <select name="status" style="padding:10px; border-radius:6px; border:1px solid #ddd;">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?= $status_filter=='Pending'?'selected':'' ?>>Pending</option>
                    <option value="Completed" <?= $status_filter=='Completed'?'selected':'' ?>>Completed</option>
                </select>
                <button type="submit" class="btn btn-blue">Filter</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Source</th>
                    <th>ID</th>
                    <th>Patient</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prescriptions)): ?>
                <tr><td colspan="6" style="text-align:center;">No records found.</td></tr>
                <?php else: ?>
                <?php foreach($prescriptions as $p):
                    $src=$p['SOURCE'];
                    $date=$p['DATE_ISSUED'];
                    if($date instanceof DateTime) $date=$date->format('d/m/Y H:i');
                ?>
                <tr>
                    <td><span class="src-badge src-<?= $src ?>"><?= $src ?></span></td>
                    <td>#<?= $p['PRESCRIPTION_ID'] ?></td>
                    <td><strong><?= htmlspecialchars($p['PATIENT_NAME'] ?? 'Unknown') ?></strong></td>
                    <td><?= htmlspecialchars(is_string($date)?$date:'N/A') ?></td>
                    <td><span class="badge bg-<?= $p['STATUS'] ?>"><?= $p['STATUS'] ?></span></td>
                    <td>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <a href="viewPrescription.php?id=<?= $p['PRESCRIPTION_ID'] ?>&source=<?= $src ?>" style="color:#0066ff;" title="View"><i class="fas fa-eye"></i></a>
                            <a href="printLabel.php?id=<?= $p['PRESCRIPTION_ID'] ?>&source=<?= $src ?>" style="color:#ff9800;" title="Print"><i class="fas fa-print"></i></a>
                            <a href="editPrescription.php?id=<?= $p['PRESCRIPTION_ID'] ?>&source=<?= $src ?>" style="color:#9c27b0;" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if($p['STATUS']==='Pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="prescription_id" value="<?= $p['PRESCRIPTION_ID'] ?>">
                                <input type="hidden" name="source" value="<?= $src ?>">
                                <input type="hidden" name="status" value="Completed">
                                <button type="submit" style="background:none; border:none; color:#28a745; cursor:pointer; font-weight:bold; text-decoration:underline;" onclick="return confirm('Mark as Completed?')">Done</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete_prescription">
                                <input type="hidden" name="prescription_id" value="<?= $p['PRESCRIPTION_ID'] ?>">
                                <input type="hidden" name="source" value="<?= $src ?>">
                                <button type="submit" style="background:none; border:none; color:#e53935; cursor:pointer;" onclick="return confirm('Delete this prescription? This cannot be undone!')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
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