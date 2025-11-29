<?php
// --- MANDATORY ERROR REPORTING FOR DEBUGGING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==================================================================================
// 1. MySQL Connection
// ==================================================================================
$mysql_servername = "10.245.156.96";
$mysql_username = "FARAH";
$mysql_password = "1234"; 
$mysql_dbname = "pharmacy_db";
$mysql_port = 3306; // Change to 3306 if using default MySQL port

// We use $mysql_conn to distinguish it from other connections
$mysql_conn = new mysqli($mysql_servername, $mysql_username, $mysql_password, $mysql_dbname, $mysql_port);

if ($mysql_conn->connect_error) {
    $mysql_error = "MySQL Connection failed: " . $mysql_conn->connect_error;
} else {
    // echo "MySQL Connected Successfully"; 
}

// ==================================================================================
// 2. PostgreSQL Connection (NEW)
// ==================================================================================
$pg_host = "10.245.156.44";
$pg_port = "5432"; // Default PostgreSQL port
$pg_dbname = "Workshop";
$pg_user = "farah"; // Default superuser is usually 'postgres'
$pg_password = "12345"; // UPDATE THIS

$pg_conn = null; // Variable for the Postgres connection

try {
    $dsn = "pgsql:host=$pg_host;port=$pg_port;dbname=$pg_dbname";
    // Using PDO for PostgreSQL
    $pg_conn = new PDO($dsn, $pg_user, $pg_password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // echo "PostgreSQL Connected Successfully";
} catch (PDOException $e) {
    $pg_error = "PostgreSQL Connection failed: " . $e->getMessage();
}

// ==================================================================================
// 3. SQL Server Connection (Original)
// ==================================================================================

// Central DB connection file for the workshop app.
// !!! YOU MUST CONFIGURE THESE 3 VARIABLES FOR YOUR SQL SERVER !!!
$serverName = "PHAROAHS"; // REPLACE WITH YOUR ACTUAL SQL SERVER NAME/INSTANCE
$database = "Workshop";
$uid = ""; // REPLACE WITH YOUR SQL LOGIN USERNAME (or leave empty for Windows Auth)
$pass = ""; // REPLACE WITH YOUR SQL LOGIN PASSWORD (or leave empty for Windows Auth)

// Variables for connection objects
$dbType = null;
$conn = null; // Legacy resource for sqlsrv_connect
$pdo = null;  // PDO object for SQL Server

// --- DIAGNOSTIC MODE: If this file is accessed directly, it performs a test ---
if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $info = []; $info['php_version'] = phpversion(); $info['server'] = $serverName; $info['database'] = $database; $results = [];

    // Diagnostic: MySQL
    if (isset($mysql_conn) && !$mysql_conn->connect_error) {
        echo "SUCCESS: MySQL connected (Port: $mysql_port)\n";
    } elseif (isset($mysql_error)) {
        echo "ERROR: MySQL failed - $mysql_error\n";
    }

    // Diagnostic: PostgreSQL
    if (isset($pg_conn) && $pg_conn) {
        echo "SUCCESS: PostgreSQL connected (Port: $pg_port)\n";
    } elseif (isset($pg_error)) {
        echo "ERROR: PostgreSQL failed - $pg_error\n";
    }

    // Diagnostic: SQL Server
    // Test sqlsrv extension
    if (function_exists('sqlsrv_connect')) {
        $connectionInfo = ["Database" => $database, "UID" => $uid, "PWD" => $pass];
        $tmp = @sqlsrv_connect($serverName, $connectionInfo);
        if ($tmp !== false) { echo "SUCCESS: connected (sqlsrv)"; exit; }
        $results['sqlsrv'] = 'available but connection failed';
    } else { $results['sqlsrv'] = 'extension not installed'; }

    // Test PDO + sqlsrv driver
    if (class_exists('PDO')) {
        try {
            if (in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
                $dsn = "sqlsrv:Server={$serverName};Database={$database}";
                $tmp = new PDO($dsn, $uid, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                echo "SUCCESS: connected (pdo_sqlsrv)"; exit;
            } else { $results['pdo_sqlsrv'] = 'pdo sqlsrv driver not available'; }
        } catch (PDOException $e) { $results['pdo_sqlsrv_error'] = $e->getMessage(); }
    } else { $results['pdo'] = 'PDO not available'; }

    // Try PDO ODBC fallback
    try {
        $odbcDriver = '{ODBC Driver 18 for SQL Server}';
        $odbcDsn = "odbc:Driver={$odbcDriver};Server={$serverName};Database={$database};";
        if ($uid !== '') { $odbcDsn .= "Uid={$uid};Pwd={$pass};"; }
        else { $odbcDsn .= "Trusted_Connection=Yes;"; }
        $tmp = new PDO($odbcDsn);
        echo "SUCCESS: connected (pdo_odbc)"; exit;
    } catch (Exception $e) { $results['pdo_odbc'] = $e->getMessage(); }

    // If we reach here, no driver connected.
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: SQL Server not connected\n\n";
    echo "Server/DB: " . $info['server'] . " / " . $info['database'] . "\n\n";
    echo "Driver test results: \n";
    foreach ($results as $k => $v) { echo " - $k: " . (is_array($v) ? implode(',', $v) : $v) . "\n"; }
    echo "\nACTION: Check \$serverName, \$uid, \$pass, and ensure SQL drivers are installed for PHP.\n";
    exit;
}
// --- END DIAGNOSTIC MODE ---


// Try sqlsrv extension first
if (function_exists('sqlsrv_connect')) {
    $connectionInfo = ["Database" => $database, "UID" => $uid, "PWD" => $pass];
    $tmp = @sqlsrv_connect($serverName, $connectionInfo);
    if ($tmp !== false) { $conn = $tmp; $dbType = 'sqlsrv'; return; }
}

// Try PDO with sqlsrv driver
if (class_exists('PDO')) {
    try {
        $dsn = "sqlsrv:Server={$serverName};Database={$database}";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        $tmp = new PDO($dsn, $uid, $pass, $options);
        $pdo = $tmp; $dbType = 'pdo_sqlsrv'; return;
    } catch (PDOException $e) { /* continue to ODBC fallback quietly */ }
}

// Fallback: PDO ODBC
try {
    $odbcDriver = '{ODBC Driver 18 for SQL Server}';
    $odbcDsn = "odbc:Driver={$odbcDriver};Server={$serverName};Database={$database};";
    if ($uid !== '') { $odbcDsn .= "Uid={$uid};Pwd={$pass};"; }
    else { $odbcDsn .= "Trusted_Connection=Yes;"; }
    $tmp = new PDO($odbcDsn);
    $pdo = $tmp; $dbType = 'pdo_odbc'; return;
} catch (Exception $e) {
    // All connection attempts failed. $conn and $pdo remain null.
    return;
}
?>