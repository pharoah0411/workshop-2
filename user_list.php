<?php
include "connectionSyameel.php"; 
include "header.php";

// Fetch only admin + pharmacist
$query = $conn->query("SELECT * FROM \"user\" WHERE role IN ('admin', 'pharmacist') ORDER BY user_id ASC");
$users = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>User List</h1>

<a href="add_user.php" style="padding: 10px 15px; background: #28a745; color: white; border-radius: 5px; text-decoration: none;">
    + Add User
</a>

<br><br>

<table border="0" cellpadding="10" cellspacing="0" style="width: 100%; background:white; border-radius:10px;">
    <tr style="background:#0b2f6d; color:white;">
        <th>ID</th>
        <th>Username</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Role</th>
        <th>Actions</th>
    </tr>

    <?php foreach($users as $u): ?>
    <tr style="border-bottom:1px solid #ccc;">
        <td><?= $u['user_id'] ?></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['name']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= htmlspecialchars($u['phone']) ?></td>
        <td><?= ucfirst($u['role']) ?></td>

        <td>
            <a href="edit_user.php?id=<?= $u['user_id'] ?>" 
               style="color:blue; margin-right:10px;">Edit</a>

            <a href="delete_user.php?id=<?= $u['user_id'] ?>" 
               onclick="return confirm('Delete this user?');"
               style="color:red;">Delete</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

</div> <!-- end content -->
</body>
</html>
