<?php
require_once "auth_check.php";   // MUST BE FIRST
requireRole('admin');            // admin only

require_once "connection.php";
include "header.php";

$message = "";

/* =========================
   1) VALIDATE INPUT
========================= */
if (!isset($_GET['id']) || !isset($_GET['db'])) {
    die("<p style='color:red;'>Missing user ID or source.</p>");
}

$user_id = trim($_GET['id']);
$db      = trim($_GET['db']);

// Normalize db name (accept SQL%20Server, sqlserver, etc.)
$dbKey = strtolower(str_replace(' ', '', $db));
if ($dbKey === 'postgres' || $dbKey === 'postgresql') $dbKey = 'postgres';
elseif ($dbKey === 'mysql') $dbKey = 'mysql';
elseif ($dbKey === 'sqlserver' || $dbKey === 'mssql') $dbKey = 'sqlserver';
else die("<p style='color:red;'>Invalid database source.</p>");

/* =========================
   2) FETCH USER BY DB
========================= */
$user = null;

try {
    if ($dbKey === 'postgres') {

        if (!($pg_conn instanceof PDO)) die("<p style='color:red;'>Postgres connection not available.</p>");

        $stmt = $pg_conn->prepare('SELECT user_id, username, name, email, phone, role FROM "user" WHERE user_id = :id');
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

    } elseif ($dbKey === 'mysql') {

        if (!($mysql_conn2 instanceof mysqli)) die("<p style='color:red;'>MySQL connection not available.</p>");

        $stmt = $mysql_conn2->prepare("SELECT USER_ID, USERNAME, NAME, EMAIL, PHONE, ROLE FROM `USER` WHERE USER_ID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        // Normalize keys to match Postgres style
        if ($user) {
            $user = [
                'user_id'   => $user['USER_ID'],
                'username'  => $user['USERNAME'],
                'name'      => $user['NAME'],
                'email'     => $user['EMAIL'],
                'phone'     => $user['PHONE'],
                'role'      => $user['ROLE'],
            ];
        }

    } elseif ($dbKey === 'sqlserver') {

        if (!($pdo instanceof PDO)) die("<p style='color:red;'>SQL Server connection not available.</p>");

        $stmt = $pdo->prepare("SELECT USER_ID, username, name, email, phone, role FROM [USER] WHERE USER_ID = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $user = [
                'user_id'   => $row['USER_ID'],
                'username'  => $row['username'],
                'name'      => $row['name'],
                'email'     => $row['email'],
                'phone'     => $row['phone'],
                'role'      => $row['role'],
            ];
        }
    }

} catch (Exception $e) {
    die("<p style='color:red;'>Error fetching user: " . htmlspecialchars($e->getMessage()) . "</p>");
}

if (!$user) {
    die("<p style='color:red;'>User not found in selected database.</p>");
}

/* =========================
   3) HANDLE UPDATE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $name     = trim($_POST["name"]);
    $email    = trim($_POST["email"]);
    $phone    = trim($_POST["phone"]);
    $role     = trim($_POST["role"]);

    try {
        if ($dbKey === 'postgres') {

            $stmt = $pg_conn->prepare('
                UPDATE "user"
                SET username = :u, name = :n, email = :e, phone = :ph, role = :r
                WHERE user_id = :id
            ');
            $stmt->execute([
                ':u'  => $username,
                ':n'  => $name,
                ':e'  => $email,
                ':ph' => $phone,
                ':r'  => $role,
                ':id' => $user_id
            ]);

        } elseif ($dbKey === 'mysql') {

            $stmt = $mysql_conn2->prepare("
                UPDATE `USER`
                SET USERNAME = ?, NAME = ?, EMAIL = ?, PHONE = ?, ROLE = ?
                WHERE USER_ID = ?
            ");
            $stmt->bind_param("sssssi", $username, $name, $email, $phone, $role, $user_id);
            $stmt->execute();
            $stmt->close();

        } elseif ($dbKey === 'sqlserver') {

            $stmt = $pdo->prepare("
                UPDATE [USER]
                SET username = ?, name = ?, email = ?, phone = ?, role = ?
                WHERE USER_ID = ?
            ");
            $stmt->execute([$username, $name, $email, $phone, $role, $user_id]);
        }

        $message = "<p style='color:green;'>✅ User updated successfully ($db).</p>";

        // Refresh displayed data
        $user['username'] = $username;
        $user['name']     = $name;
        $user['email']    = $email;
        $user['phone']    = $phone;
        $user['role']     = $role;

    } catch (Exception $e) {
        $message = "<p style='color:red;'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>

<h1>Edit User (<?= htmlspecialchars($db) ?>)</h1>
<?= $message ?>

<form method="POST"
      style="background:white; padding:20px; border-radius:10px; width:600px;">

    <label><strong>Username:</strong></label><br>
    <input type="text" name="username"
           value="<?= htmlspecialchars($user['username']) ?>"
           required class="input-box"><br><br>

    <label><strong>Full Name:</strong></label><br>
    <input type="text" name="name"
           value="<?= htmlspecialchars($user['name']) ?>"
           required class="input-box"><br><br>

    <label><strong>Email:</strong></label><br>
    <input type="email" name="email"
           value="<?= htmlspecialchars($user['email']) ?>"
           class="input-box"><br><br>

    <label><strong>Phone:</strong></label><br>
    <input type="text" name="phone"
           value="<?= htmlspecialchars($user['phone']) ?>"
           class="input-box"><br><br>

    <label><strong>Role:</strong></label><br>
    <select name="role" class="input-box" required>
        <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
        <option value="pharmacist" <?= ($user['role'] === 'pharmacist') ? 'selected' : '' ?>>Pharmacist</option>
    </select>
    <br><br>

    <button type="submit"
            style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px;">
        Save Changes
    </button>

    <a href="user_list.php"
       style="padding:10px 20px; background:#999; color:white; border-radius:5px;
       margin-left:10px; text-decoration:none;">
        Cancel
    </a>
</form>

<style>
.input-box {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border-radius: 5px;
    border: 1px solid #ccc;
}
</style>

</div> <!-- end content -->
</body>
</html>
