hi highlight_file

<?php
// hi.php

// 1. Include the connection file you just created
require_once 'db_connect.php'; 

// 2. Define a simple SQL query to retrieve a pharmacist's name (assuming Pharmacist ID is 2)
$sql = "SELECT NAME FROM `USER` WHERE ROLE = 'Pharmacist' AND USER_ID = 2 LIMIT 1";

// 3. Execute the query
$result = $conn->query($sql);

// 4. Check if the query was successful and returned rows
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Display a success message
    echo "<h1>✅ Database Connection Successful!</h1>";
    echo "<p>Your application is connected to **pharmacy_db**.</p>";
    echo "<p>Test Data Found: Pharmacist Name is **" . htmlspecialchars($row['NAME']) . "**</p>";
} else {
    // This runs if the connection was successful, but the query found no data or failed.
    echo "<h1>⚠️ Connection OK, but no data was returned by the test query.</h1>";
    echo "<p>Ensure you ran the INSERT statements for the `USER` table.</p>";
}

// 5. Always close the connection when done
$conn->close();
?>