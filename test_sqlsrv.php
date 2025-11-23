<?php
// Diagnostic helper: open in browser at http://localhost/workshop/test_sqlsrv.php
header('Content-Type: text/plain; charset=utf-8');

echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP Binary: " . (defined('PHP_BINARY') ? PHP_BINARY : 'N/A') . "\n";
echo "Loaded php.ini: " . (php_ini_loaded_file() ?: 'none') . "\n";
echo "Thread Safety: " . (defined('ZEND_THREAD_SAFE') && ZEND_THREAD_SAFE ? 'TS' : 'NTS') . "\n";

echo "Function sqlsrv_connect exists?: " . (function_exists('sqlsrv_connect') ? 'YES' : 'NO') . "\n";

echo "pdo_sqlsrv available?: ";
if (function_exists('pdo_drivers')) {
    $drivers = pdo_drivers();
    echo (in_array('sqlsrv', $drivers) ? 'YES' : 'NO') . "\n";
    echo "Available PDO drivers: " . implode(', ', $drivers) . "\n";
} else {
    echo "PDO not available or pdo_drivers() missing\n";
}

// Show small phpinfo output header for manual lookup
echo "\n---- phpinfo top lines (for web view) ----\n";
phpinfo(INFO_GENERAL);

?>