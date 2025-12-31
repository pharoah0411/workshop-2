<?php
include "connection.php"; 
include "header.php";

$all_users = [];

// 1. Fetch from PostgreSQL
if (isset($pg_conn)) {
    try {
        $query = $pg_conn->query("SELECT user_id, username, name, email, phone, role FROM \"user\" WHERE role IN ('admin', 'pharmacist')");
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $row['source'] = 'Postgres'; // Track which DB it came from
            $all_users[] = $row;
        }
    } catch (Exception $e) { /* Log error if needed */ }
}

// 2. Fetch from MySQL (using mysqli as defined in your connection.php)
if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
    try {
        // IMPORTANT: use the correct table name `USER` (same as insert)
        $sql = "SELECT USER_ID as user_id, USERNAME as username, NAME as name, EMAIL as email, PHONE as phone, ROLE as role
                FROM `USER`
                WHERE ROLE IN ('admin', 'pharmacist')";

        $result = $mysql_conn2->query($sql);

        if (!$result) {
            // show the real MySQL error (temporary debug)
            die("MySQL Query Error: " . $mysql_conn2->error);
        }

        while ($row = $result->fetch_assoc()) {
            $row['source'] = 'MySQL';
            $all_users[] = $row;
        }

    } catch (Exception $e) {
        die("MySQL Exception: " . $e->getMessage());
    }
}


// 3. Fetch from SQL Server (using $pdo as defined in your connection.php)
if (isset($pdo)) {
    try {
        $query = $pdo->query("SELECT user_id, username, name, email, phone, role FROM [USER] WHERE role IN ('admin', 'pharmacist')");
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $row['source'] = 'SQL Server';
            $all_users[] = $row;
        }
    } catch (Exception $e) { }
}
?>

<h1>User List (Multi-Database)</h1>

<a href="add_user.php" style="padding: 10px 15px; background: #28a745; color: white; border-radius: 5px; text-decoration: none;">
    + Add User
</a>

<br><br>

<table border="0" cellpadding="10" cellspacing="0" style="width: 100%; background:white; border-radius:10px;">
    <tr style="background:linear-gradient(135deg, #0066ff 0%, #0099ff 100%); color:white;">
        <th>Source</th>
        <th>ID</th>
        <th>Username</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Role</th>
        <th>Actions</th>
    </tr>

    <?php if (empty($all_users)): ?>
        <tr><td colspan="8" style="text-align:center;">No users found in any database.</td></tr>
    <?php else: ?>
        <?php foreach($all_users as $u): ?>
        <tr style="border-bottom:1px solid #ccc;">
            <td style="font-size: 0.8em; color: #666;"><strong><?= $u['source'] ?></strong></td>
            <td><?= $u['user_id'] ?></td>
            <td><?= htmlspecialchars($u['username'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['name'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['phone'] ?? '') ?></td>
            <td><?= ucfirst($u['role']) ?></td>

            <td>
                <a href="edit_user.php?id=<?= $u['user_id'] ?>&db=<?= $u['source'] ?>" 
                   style="color:blue; margin-right:10px;">Edit</a>

                <a href="delete_user.php?id=<?= $u['user_id'] ?>&db=<?= $u['source'] ?>" 
                   onclick="return confirm('Delete this user from <?= $u['source'] ?>?');"
                   style="color:red;">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

</div> </body>
</html>