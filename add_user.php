<?php
include "connection.php"; 
include "header.php";

$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // NEW: Get the selected target database
    $target_source = $_POST['source'] ?? 'Postgres'; // Default to Postgres if not set

    if ($username === '' || $password === '' || $name === '' || $email === '' || $phone === '' || $role === '') {
        $message = "<p style='color:red;'>ERROR: All fields are required.</p>";
    } else {
        $success_count = 0;
        $attempt_count = 0;

        // Base SQL (adjusting syntax for each DB type)
        $sql = 'INSERT INTO "user" (username, password, role, name, email, phone) VALUES (:u, :p, :r, :n, :e, :ph)';
        
        // --- 1. MySQL #2 INSERT ---
        if (($target_source === 'All' || $target_source === 'MySQL') && isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
            $attempt_count++;
            try {
                // MySQL uses backticks and no double quotes
                $m_sql = str_replace('"', '`', $sql);
                $m_sql = str_replace(':u', '?', $m_sql); // Replace named params with ? for mysqli
                $stmt = $mysql_conn2->prepare($m_sql);
                $stmt->bind_param("ssssss", $username, $password, $role, $name, $email, $phone);
                if ($stmt->execute()) {
                    $success_count++;
                }
                $stmt->close();
            } catch (Exception $e) { /* Ignore individual DB failures */ }
        }

        // --- 2. PostgreSQL INSERT ---
        if (($target_source === 'All' || $target_source === 'Postgres') && isset($pg_conn) && $pg_conn instanceof PDO) {
            $attempt_count++;
            try {
                // PostgreSQL uses double quotes for reserved word "user"
                $stmt = $pg_conn->prepare($sql);
                if ($stmt->execute([':u'=>$username, ':p'=>$password, ':r'=>$role, ':n'=>$name, ':e'=>$email, ':ph'=>$phone])) {
                    $success_count++;
                }
            } catch (Exception $e) { /* Ignore individual DB failures */ }
        }
        
        // --- 3. SQL Server INSERT ---
        if ($target_source === 'All' || $target_source === 'SQLServer') {
            $attempt_count++;
            try {
                // SQL Server uses brackets for reserved word [USER]
                $s_sql = str_replace('"user"', '[USER]', $sql); 
                
                if (isset($pdo) && $pdo instanceof PDO) {
                    // SQL Server PDO
                    $stmt = $pdo->prepare($s_sql);
                    if ($stmt->execute([':u'=>$username, ':p'=>$password, ':r'=>$role, ':n'=>$name, ':e'=>$email, ':ph'=>$phone])) {
                        $success_count++;
                    }
                } elseif (isset($conn) && $conn !== false) {
                    // SQL Server Legacy (assuming the same parameters array structure for simplicity, though sqlsrv_query uses positional ? markers)
                    $sql_legacy = "INSERT INTO [USER] (username, password, role, name, email, phone) VALUES (?, ?, ?, ?, ?, ?)";
                    $params = [$username, $password, $role, $name, $email, $phone];
                    if (sqlsrv_query($conn, $sql_legacy, $params)) {
                        $success_count++;
                    }
                }
            } catch (Exception $e) { /* Ignore individual DB failures */ }
        }
        
        if ($success_count > 0) {
            $message = "<p style='color:green;'>User has been added successfully to $success_count out of $attempt_count database(s)!</p>";
        } else {
             $message = "<p style='color:red;'>ERROR: Failed to add user to any selected database. Please check connection logs.</p>";
        }
    }
}
?>

<h1>Add New User</h1>

<?= $message ?>

<form method="POST" 
      style="background:white; padding:20px; border-radius:10px; width:600px;">

    <div class="form-group">
        <label><strong>Save to Database:</strong></label><br>
        <select name="source" class="input-box" required>
            <option value="Postgres" selected>Postgres Only</option>
            <option value="MySQL">MySQL Only</option>
            <option value="SQLServer">SQL Server Only</option>
            <option value="All">All Databases</option>
        </select>
        <br><br>
    </div>
    <label><strong>Username:</strong></label><br>
    <input type="text" name="username" required class="input-box"><br><br>

    <label><strong>Password:</strong></label><br>
    <input type="password" name="password" required class="input-box"><br><br>

    <label><strong>Role:</strong></label><br>
    <select name="role" class="input-box" required>
        <option value="">Select role...</option>
        <option value="admin">Admin</option>
        <option value="pharmacist">Pharmacist</option>
    </select>
    <br><br>

    <label><strong>Full Name:</strong></label><br>
    <input type="text" name="name" required class="input-box"><br><br>

    <label><strong>Email:</strong></label><br>
    <input type="email" name="email" required class="input-box"><br><br>

    <label><strong>Phone:</strong></label><br>
    <input type="text" name="phone" required class="input-box"><br><br>

    <button type="submit" 
            style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px;">
        Add User
    </button>

    <a href="user_list.php" 
       style="padding:10px 20px; background:#999; color:white; border-radius:5px; margin-left:10px; text-decoration:none;">
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

</div> </body>
</html>