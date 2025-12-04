<?php
// connection.php

// --- ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize connection variables
$mysql_conn = null;
$mysql_conn2 = null; // <--- NEW VARIABLE
$pg_conn = null;
$pdo = null; 
$conn = null; 

// ==================================================================================
// 2. MySQL Connection #2 (Secondary)
// ==================================================================================
// !!! PLEASE UPDATE THESE CREDENTIALS FOR YOUR SECOND DATABASE !!!
$mysql2_servername = "10.245.156.39"; // Enter Second IP Here
$mysql2_username = "farah";           // Enter Username
$mysql2_password = "Syimazmi201.";            // Enter Password
$mysql2_dbname = "medicine prescription";       // Enter Database Name
$mysql2_port = 3306; 

$temp_mysql2 = @new mysqli($mysql2_servername, $mysql2_username, $mysql2_password, $mysql2_dbname, $mysql2_port);

if ($temp_mysql2->connect_error) {
    $mysql2_error = "MySQL #2 Failed: " . $temp_mysql2->connect_error;
} else {
    $mysql_conn2 = $temp_mysql2;
}

// ==================================================================================
// 3. PostgreSQL Connection - 10.245.156.44
// ==================================================================================
$pg_host = "10.245.156.44";
$pg_port = "5432"; 
$pg_dbname = "Workshop"; // Using 'Workshop' as per your snippet
$pg_user = "farah"; 
$pg_password = "12345"; 

try {
    $dsn = "pgsql:host=$pg_host;port=$pg_port;dbname=$pg_dbname";
    $pg_conn = new PDO($dsn, $pg_user, $pg_password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    $pg_error = "PostgreSQL Failed: " . $e->getMessage();
}

// ==================================================================================
// 4. SQL Server Connection - IP Address
// ==================================================================================
// Replace with your specific IP and Port (Default SQL Server port is 1433)
$serverIp = "10.245.156.110"; // Example IP
$serverPort = "1433";        // Example Port
$serverName = "$serverIp, $serverPort"; // Format: "IP, PORT"

$database = "Workshop";
$uid = "myuser";       // Your Database Username
$pass = "StrongPass123!"; // Your Database Password

try {
    // Try PDO first
    // Note: 'tcp:' forces a TCP/IP connection
    $dsn = "sqlsrv:Server={$serverName};Database={$database}";
    $pdo = new PDO($dsn, $uid, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
} catch (PDOException $e) {
    // If PDO fails, try legacy sqlsrv
    if (function_exists('sqlsrv_connect')) {
        $connectionInfo = ["Database" => $database, "UID" => $uid, "PWD" => $pass];
        $conn = @sqlsrv_connect($serverName, $connectionInfo);
        
        if ($conn === false) {
             // Optional: Uncomment to debug connection errors
             // die(print_r(sqlsrv_errors(), true));
        }
    }
}
?>