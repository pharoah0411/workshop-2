<?php
// db_connect.php
// --------------------------------------------------------
// Connection Details (Based on common XAMPP/WAMP settings)
// --------------------------------------------------------
$servername = "localhost";
$username = "root";
$password = "";          // IMPORTANT: Use the actual password if you set one!
$dbname = "pharmacy_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // Stop the script execution and display the connection error
    die("Connection failed: " . $conn->connect_error);
}

// NOTE: The connection object is now available as $conn
?>