<?php
require_once "auth_check.php";
requireRole('admin');

include "connection.php";
include "header.php";

$all_users = [];

/* =========================
   1) FETCH USERS FROM POSTGRES
========================= */
if (isset($pg_conn) && $pg_conn instanceof PDO) {
    try {
        $query = $pg_conn->query('
            SELECT user_id, username, name, email, phone, role
            FROM "user"
            WHERE role IN (\'admin\', \'pharmacist\')
            ORDER BY user_id ASC
        ');
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $row['source'] = 'Postgres';
            $all_users[] = $row;
        }
    } catch (Exception $e) {}
}

/* =========================
   2) FETCH USERS FROM MYSQL + CHECK PRESCRIPTION REFERENCES
========================= */
if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
    try {
        $sql = "SELECT USER_ID AS user_id, USERNAME AS username, NAME AS name, EMAIL AS email, PHONE AS phone, ROLE AS role
                FROM `USER`
                WHERE ROLE IN ('admin','pharmacist')
                ORDER BY USER_ID ASC";
        $result = $mysql_conn2->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {

                // ✅ Check if this user is referenced in PRESCRIPTION
                $row['in_use'] = 0;

                $stmtChk = $mysql_conn2->prepare("SELECT COUNT(*) FROM PRESCRIPTION WHERE PHARMACIST_ID = ?");
                if ($stmtChk) {
                    $uid = (int)$row['user_id'];
                    $stmtChk->bind_param("i", $uid);
                    $stmtChk->execute();
                    $stmtChk->bind_result($cnt);
                    $stmtChk->fetch();
                    $stmtChk->close();

                    $row['in_use'] = (int)$cnt;
                }

                $row['source'] = 'MySQL';
                $all_users[] = $row;
            }
        }
    } catch (Exception $e) {}
}

/* =========================
   3) FETCH USERS FROM SQL SERVER
========================= */
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $query = $pdo->query("
            SELECT user_id, username, name, email, phone, role
            FROM [USER]
            WHERE role IN ('admin','pharmacist')
            ORDER BY user_id ASC
        ");
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $row['source'] = 'SQL Server';
            $all_users[] = $row;
        }
    } catch (Exception $e) {}
}

/* Optional: sort combined list by source then id */
usort($all_users, function($a, $b) {
    $s = strcmp($a['source'], $b['source']);
    if ($s !== 0) return $s;
    return (int)$a['user_id'] <=> (int)$b['user_id'];
});
?>

<h1 style="margin-bottom:15px;">User List (Multi-Database)</h1>

<a href="add_user.php" class="btn-add">+ Add User</a>

<br><br>

<table class="table-users">
    <tr class="thead">
        <th>Source</th>
        <th>ID</th>
        <th>Username</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Role</th>
        <th style="width:180px;">Actions</th>
    </tr>

    <?php if (empty($all_users)): ?>
        <tr>
            <td colspan="8" style="text-align:center; padding:20px;">
                No users found in any database.
            </td>
        </tr>
    <?php else: ?>

        <?php foreach($all_users as $u): ?>
            <?php
                $source = $u['source'];
                $id     = (int)($u['user_id'] ?? 0);

                // MySQL only: disable delete if referenced
                $mysqlInUse = ($source === 'MySQL' && !empty($u['in_use']) && (int)$u['in_use'] > 0);
                $inUseCount = (int)($u['in_use'] ?? 0);
            ?>

            <tr>
                <td class="source"><?= htmlspecialchars($source) ?></td>
                <td><?= $id ?></td>
                <td><?= htmlspecialchars($u['username'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['phone'] ?? '') ?></td>
                <td><?= ucfirst($u['role'] ?? '') ?></td>

                <td>
                    <a class="link-edit"
                       href="edit_user.php?id=<?= $id ?>&db=<?= urlencode($source) ?>">
                        Edit
                    </a>

                    <?php if ($mysqlInUse): ?>
                        <span class="badge-inuse">In Use (<?= $inUseCount ?>)</span>
                    <?php else: ?>
                        <a class="link-delete"
                           href="delete_user.php?id=<?= $id ?>&db=<?= urlencode($source) ?>"
                           onclick="return confirm('Delete this user from <?= htmlspecialchars($source) ?>?');">
                            Delete
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

    <?php endif; ?>
</table>

<style>
/* Button */
.btn-add{
    display:inline-block;
    padding:10px 16px;
    background:#28a745;
    color:#fff;
    border-radius:8px;
    text-decoration:none;
    font-weight:600;
}

/* Table */
.table-users{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 4px 18px rgba(0,0,0,0.08);
}

.table-users th, .table-users td{
    padding:12px 12px;
    border-bottom:1px solid #eee;
    text-align:left;
    font-size:14px;
}

.table-users .thead{
    background: linear-gradient(135deg, #0066ff 0%, #0099ff 100%);
    color:#fff;
}

.source{
    font-weight:700;
    color:#0b2f6d;
}

/* Action links */
.link-edit{
    color:#0056d2;
    font-weight:600;
    text-decoration:none;
    margin-right:10px;
}
.link-edit:hover{ text-decoration:underline; }

.link-delete{
    color:#d90000;
    font-weight:700;
    text-decoration:none;
}
.link-delete:hover{ text-decoration:underline; }

/* Badge */
.badge-inuse{
    display:inline-block;
    padding:6px 10px;
    border-radius:999px;
    background:#ffe6e6;
    color:#c20000;
    font-weight:700;
    font-size:12px;
}
</style>

</div>
</body>
</html>