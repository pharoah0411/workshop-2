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

// --- Handle Status Update POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $presc_id = intval($_POST['prescription_id']);
    $new_status = $_POST['status'];
    
    if ($presc_id > 0 && !empty($new_status)) {
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->prepare("UPDATE PRESCRIPTION SET STATUS = :status WHERE PRESCRIPTION_ID = :id");
                $stmt->execute([':status' => $new_status, ':id' => $presc_id]);
            } elseif (isset($conn)) {
                $params = [$new_status, $presc_id];
                $sql = "UPDATE PRESCRIPTION SET STATUS = ? WHERE PRESCRIPTION_ID = ?";
                sqlsrv_query($conn, $sql, $params);
            }
        } catch (Exception $e) { /* Ignore for demo */ }
    }
    // Redirect to self to prevent resubmission
    header('Location: prescriptiondashboard.php');
    exit;
}

// Search & Filter Inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$prescriptions = [];
$error = '';

try {
    // UPDATED QUERY: Fetching PATIENT_NAME directly from the PATIENT table
    // Pharmacist name is still fetched from the USER table via PHARMACIST_ID
    $sql = "SELECT 
                pr.PRESCRIPTION_ID, 
                pr.DATE_ISSUED, 
                pr.STATUS,
                p.NAME AS PATIENT_NAME,
                pharm_user.NAME AS PHARMACIST_NAME
            FROM PRESCRIPTION pr
            JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
            LEFT JOIN [USER] pharm_user ON pr.PHARMACIST_ID = pharm_user.USER_ID
            WHERE 1=1";
    
    $params = [];
    
    // Apply Status Filter
    if ($status_filter !== '') {
        $sql .= " AND pr.STATUS = ?";
        $params[] = $status_filter;
    }
    
    // Apply Search Filter (Search by Patient Name or Prescription ID)
    if ($search !== '') {
        $sql .= " AND (p.NAME LIKE ? OR pr.PRESCRIPTION_ID LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY pr.DATE_ISSUED DESC";

    // Execute Query
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn)) {
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $prescriptions[] = $row;
            }
        } else {
             // Capture SQL error for debugging if query fails
             $error = "Query failed: " . print_r(sqlsrv_errors(), true);
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription Dashboard</title>
    <style>
        /* Reusing Core Design */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        
        /* Top Navigation Bar */
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 30px;
            background: #1565c0; /* Darker blue */
            color: white;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        .nav-links a { color: white; text-decoration: none; margin-left: 15px; font-weight: 500; transition: opacity 0.2s; }
        .nav-links a:hover { opacity: 0.8; }
        .user-info { font-size: 0.9em; }
        .btn-logout { padding: 6px 12px; border: 1px solid white; border-radius: 6px; background: transparent; color: white; cursor: pointer; text-decoration: none; font-size: 0.9em; }
        .btn-logout:hover { background: rgba(255, 255, 255, 0.1); }

        .header { background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }

        .controls { display: flex; gap: 15px; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; color: white; font-weight: 600; }
        .btn-primary { background: #28a745; } /* Green for add */
        .btn-blue { background: #0066ff; }
        input, select { padding: 10px; border: 1px solid #ddd; border-radius: 6px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f1f1f1; color: #333; }
        tr:hover { background: #f9f9f9; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; }
        .bg-Pending { background: #fff3cd; color: #856404; }
        .bg-Completed { background: #d4edda; color: #155724; }
        .bg-Cancelled { background: #f8d7da; color: #721c24; }

        .action-link { margin-right: 10px; color: #0066ff; text-decoration: none; }
        .action-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header class="top-nav">
        <div class="user-info">
            Welcome, <strong><?php echo htmlspecialchars($username); ?></strong> (<?php echo htmlspecialchars($userRole); ?>)
        </div>
        <div class="nav-links">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="medDirectory.php">📦 Medicines</a>
            <a href="prescriptiondashboard.php">📝 Prescriptions</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </header>

    <div class="container">
        <header class="header">
            <h1>📝 Prescription Dashboard</h1>
            <p>Manage patient prescriptions and tracking</p>
        </header>

        <div class="content">
            <div class="controls">
                <a href="createPrescription.php" class="btn btn-primary">+ New Prescription</a>
                <form method="GET" style="display:flex; gap:10px; flex:1;">
                    <input type="text" name="search" placeholder="Search Patient Name or ID..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;">
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
                <div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:5px; margin-bottom:15px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
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
                        <tr><td colspan="6" style="text-align:center;">No prescriptions found.</td></tr>
                    <?php else: ?>
                        <?php foreach($prescriptions as $p): 
                            $date = $p['DATE_ISSUED'];
                            if($date instanceof DateTime) $date = $date->format('Y-m-d H:i');
                        ?>
                        <tr>
                            <td>#<?php echo $p['PRESCRIPTION_ID']; ?></td>
                            <td><?php echo htmlspecialchars($p['PATIENT_NAME'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($p['PHARMACIST_NAME'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars(is_string($date)? $date : ''); ?></td>
                            <td><span class="badge bg-<?php echo $p['STATUS']; ?>"><?php echo $p['STATUS']; ?></span></td>
                            <td>
                                <a href="viewPrescription.php?id=<?php echo $p['PRESCRIPTION_ID']; ?>" class="action-link">View</a>
                                <?php if($p['STATUS'] === 'Pending'): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Mark as Completed?');">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="prescription_id" value="<?php echo $p['PRESCRIPTION_ID']; ?>">
                                    <input type="hidden" name="status" value="Completed">
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