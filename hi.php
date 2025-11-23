hi highlight_file

<?php
// hi.php

// 1. Include the connection file
require_once 'db_connect.php'; 

// 2. Test a simple query (e.g., fetch one row from the USER table)
$sql = "SELECT USERNAME FROM `USER` LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<h1>Database Connection Successful!</h1>";
    echo "<p>Test User found: **" . htmlspecialchars($row['USERNAME']) . "**</p>";
} else {
    echo "<h1>Connection OK, but no data found or query failed.</h1>";
}

// 3. Close the connection when the script finishes
$conn->close();
?>
