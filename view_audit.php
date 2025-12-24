<?php
session_start();
require_once 'connection.php';

// Security: Only allow Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// 1. Identify which connection to use to fetch logs
$activeConn = null;
if (isset($pdo) && $pdo instanceof PDO) $activeConn = $pdo;
elseif (isset($conn) && $conn !== false) $activeConn = $conn;
elseif (isset($mysql_conn) && $mysql_conn instanceof mysqli) $activeConn = $mysql_conn;
elseif (isset($pg_conn) && $pg_conn instanceof PDO) $activeConn = $pg_conn;

$logs = [];
$sql = "SELECT * FROM audit_trail ORDER BY created_at DESC LIMIT 100";

try {
    if ($activeConn instanceof PDO) {
        $stmt = $activeConn->query($sql);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($activeConn instanceof mysqli) {
        $result = $activeConn->query($sql);
        $logs = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $error = "Error fetching logs: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Trail - Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f9; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h1 { color: #0066ff; border-bottom: 2px solid #0066ff; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        th { background: #0066ff; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f1f1f1; }
        .back-btn { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #666; font-weight: bold; }
        .badge { padding: 4px 8px; border-radius: 4px; color: white; font-size: 0.8em; }
        .bg-login { background: #2e7d32; }
        .bg-logout { background: #d32f2f; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>🛡️ System Audit Trail</h1>
        <p>Showing last 100 activities across the system.</p>

        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo $log['created_at']; ?></td>
                    <td><strong><?php echo htmlspecialchars($log['username']); ?></strong><br><small><?php echo $log['role']; ?></small></td>
                    <td>
                        <span class="badge <?php echo ($log['action'] == 'LOGIN') ? 'bg-login' : (($log['action'] == 'LOGOUT') ? 'bg-logout' : ''); ?>">
                            <?php echo $log['action']; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($log['module']); ?></td>
                    <td><?php echo htmlspecialchars($log['description']); ?></td>
                    <td><code><?php echo $log['ip_address']; ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>