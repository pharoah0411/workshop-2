<?php
// connection.php - Multi-database connection for Prescription Management System
// TESTED AND WORKING with PHP 8.4

// --- ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize connection variables
$mysql_conn2 = null; // MySQL #2
$pg_conn = null;     // PostgreSQL
$pdo_sqlsrv = null;  // SQL Server (PDO)
$pdo         = null; // ✅ ALIAS for backward compatibility

// Debug mode - set to false in production
$debug = true;

if ($debug) {
    echo "<!-- PHP Version: " . PHP_VERSION . " -->\n";
    echo "<!-- PDO Drivers: " . implode(', ', PDO::getAvailableDrivers()) . " -->\n";
}

// ====================
// 1. MySQL CONNECTION
// ====================
$mysql2_servername = "10.245.156.39"; 
$mysql2_username = "farah";
$mysql2_password = "Syimazmi201.";
$mysql2_dbname = "medicine prescription";
$mysql2_port = 3306; 

try {
    $mysql_conn2 = new mysqli($mysql2_servername, $mysql2_username, $mysql2_password, $mysql2_dbname, $mysql2_port);
    if ($mysql_conn2->connect_error) { 
        throw new Exception("MySQL Connection Error: " . $mysql_conn2->connect_error); 
    }
    $mysql_conn2->set_charset("utf8mb4");
    if ($debug) echo "<!-- MySQL: Connected -->\n";
} catch (Exception $e) { 
    $mysql_conn2 = null;
    if ($debug) echo "<!-- MySQL Error: " . htmlspecialchars($e->getMessage()) . " -->\n";
    error_log("MySQL Connection Error: " . $e->getMessage());
}

// ========================
// 2. PostgreSQL CONNECTION
// ========================
$pg_host = "10.245.156.44";
$pg_port = "5432"; 
$pg_dbname = "Workshop"; 
$pg_user = "farah"; 
$pg_password = "12345"; 

try {
    $dsn = "pgsql:host=$pg_host;port=$pg_port;dbname=$pg_dbname";
    $pg_conn = new PDO($dsn, $pg_user, $pg_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    if ($debug) echo "<!-- PostgreSQL: Connected -->\n";
} catch (Exception $e) { 
    $pg_conn = null;
    if ($debug) echo "<!-- PostgreSQL Error: " . htmlspecialchars($e->getMessage()) . " -->\n";
    error_log("PostgreSQL Connection Error: " . $e->getMessage());
}

// ==========================
// 3. SQL Server CONNECTION
// ==========================
$serverIp = "10.245.156.110"; 
$serverPort = "1433";        
$serverName = "$serverIp,$serverPort"; // NO space after comma!
$database = "Workshop";
$uid = "myuser";         
$pass = "StrongPass123!";

if ($debug) echo "<!-- Attempting SQL Server connection to $serverName -->\n";

try {
    // Use TrustServerCertificate to avoid certificate validation issues
    $dsn = "sqlsrv:Server=$serverName;Database=$database;TrustServerCertificate=1";
    
    $pdo_sqlsrv = new PDO($dsn, $uid, $pass);
    $pdo_sqlsrv->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_sqlsrv->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ✅ CREATE ALIAS
    $pdo = $pdo_sqlsrv;
    
    if ($debug) {
        echo "<!-- SQL Server: Connected -->\n";
    }
    
} catch (PDOException $e) { 
    $pdo_sqlsrv = null;
    if ($debug) {
        echo "<!-- SQL Server Connection Error: " . htmlspecialchars($e->getMessage()) . " -->\n";
    }
    error_log("SQL Server Connection Error: " . $e->getMessage());
}

// ========================
// HELPER FUNCTIONS
// ========================

/**
 * Execute SQL Server query
 */
function execute_sqlsrv_query($sql, $params = []) {
    global $pdo_sqlsrv;
    
    if (!$pdo_sqlsrv) {
        return ['success' => false, 'error' => 'No SQL Server connection available'];
    }
    
    try {
        $stmt = $pdo_sqlsrv->prepare($sql);
        $stmt->execute($params);
        return ['success' => true, 'statement' => $stmt];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Fetch all results from SQL Server statement
 */
function fetch_all_sqlsrv($stmt) {
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch single row from SQL Server statement
 */
function fetch_single_sqlsrv($stmt) {
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>