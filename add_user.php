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
    
    // Get the selected target database
    $target_source = $_POST['source'] ?? 'Postgres'; 

    if ($username === '' || $password === '' || $name === '' || $email === '' || $phone === '' || $role === '') {
        $message = "<div class='alert alert-danger'>ERROR: All fields are required.</div>";
    } else {
        $success_count = 0;
        $attempt_count = 0;
        $errors = []; // Array to store specific errors

        // Base SQL
        $sql = 'INSERT INTO "user" (username, password, role, name, email, phone) VALUES (:u, :p, :r, :n, :e, :ph)';
        
        // --- 1. MySQL #2 INSERT ---
        if ($target_source === 'All' || $target_source === 'MySQL') {
            if (isset($mysql_conn2) && $mysql_conn2 instanceof mysqli) {
                $attempt_count++;
                try {
                    $m_sql = str_replace('"', '`', $sql);
                    $m_sql = str_replace(':u', '?', $m_sql);
                    // Replace other params with ? manually or use a helper logic. 
                    // Simpler regex for quick replacement of all :param to ?
                    $m_sql = preg_replace('/:[a-z]+/', '?', $m_sql);

                    $stmt = $mysql_conn2->prepare($m_sql);
                    if (!$stmt) {
                        throw new Exception("MySQL Prepare failed: " . $mysql_conn2->error);
                    }
                    $stmt->bind_param("ssssss", $username, $password, $role, $name, $email, $phone);
                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        throw new Exception($stmt->error);
                    }
                    $stmt->close();
                } catch (Exception $e) { 
                    $errors[] = "MySQL Error: " . $e->getMessage(); 
                }
            } else {
                $errors[] = "MySQL: Connection not available.";
            }
        }

        // --- 2. PostgreSQL INSERT ---
        if ($target_source === 'All' || $target_source === 'Postgres') {
            if (isset($pg_conn) && $pg_conn instanceof PDO) {
                $attempt_count++;
                try {
                    $stmt = $pg_conn->prepare($sql);
                    if ($stmt->execute([':u'=>$username, ':p'=>$password, ':r'=>$role, ':n'=>$name, ':e'=>$email, ':ph'=>$phone])) {
                        $success_count++;
                    }
                } catch (PDOException $e) { 
                    // Check for duplicate key
                    if ($e->getCode() == 23505) {
                        $errors[] = "Postgres Error: Username '$username' already exists.";
                    } else {
                        $errors[] = "Postgres Error: " . $e->getMessage(); 
                    }
                }
            } else {
                $errors[] = "Postgres: Connection not available (Check VPN/Network).";
            }
        }
        
        // --- 3. SQL Server INSERT ---
        if ($target_source === 'All' || $target_source === 'SQLServer') {
            if ((isset($pdo) && $pdo instanceof PDO) || (isset($conn) && $conn !== false)) {
                $attempt_count++;
                try {
                    // SQL Server uses brackets for reserved word [USER]
                    $s_sql = str_replace('"user"', '[USER]', $sql); 
                    
                    if (isset($pdo) && $pdo instanceof PDO) {
                        $stmt = $pdo->prepare($s_sql);
                        if ($stmt->execute([':u'=>$username, ':p'=>$password, ':r'=>$role, ':n'=>$name, ':e'=>$email, ':ph'=>$phone])) {
                            $success_count++;
                        }
                    } elseif (isset($conn) && $conn !== false) {
                        $sql_legacy = "INSERT INTO [USER] (username, password, role, name, email, phone) VALUES (?, ?, ?, ?, ?, ?)";
                        $params = [$username, $password, $role, $name, $email, $phone];
                        if (sqlsrv_query($conn, $sql_legacy, $params)) {
                            $success_count++;
                        } else {
                            $errors[] = "SQL Server Error: " . print_r(sqlsrv_errors(), true);
                        }
                    }
                } catch (PDOException $e) { 
                    $errors[] = "SQL Server Error: " . $e->getMessage(); 
                }
            } else {
                $errors[] = "SQL Server: Connection not available.";
            }
        }
        
        // --- FINAL STATUS MESSAGE ---
        if ($success_count > 0) {
            $message = "<div class='alert alert-success' style='color:green; font-weight:bold; padding:10px; border:1px solid green; background:#d4edda;'>✅ User added successfully to $success_count out of $attempt_count database(s)!</div>";
        } else {
            // Display ALL collected errors
            $error_html = implode("<br>", $errors);
            $message = "<div class='alert alert-danger' style='color:red; padding:10px; border:1px solid red; background:#f8d7da;'>❌ FAILED to add user.<br><strong>Debug Info:</strong><br>$error_html</div>";
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
            style="padding:10px 20px; background:#0b2f6d; color:white; border:0; border-radius:5px; cursor:pointer;">
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