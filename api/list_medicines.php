<?php
require_once __DIR__ . '/../connection.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $meds = [];

    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT Id, Name, Category_Type AS category, Quantity_In_Stock AS quantity, Expiry_Date AS expiryDate, Supplier_Name AS supplier, Unit_Price AS unitPrice, Stock_Price AS stockPrice FROM Medicine");
        $stmt->execute();
        $meds = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize date format
        foreach ($meds as &$m) {
            if (!empty($m['expiryDate'])) $m['expiryDate'] = date('Y-m-d', strtotime($m['expiryDate']));
        }
    } elseif (isset($conn)) {
        $sql = "SELECT Id, Name, Category_Type AS category, Quantity_In_Stock AS quantity, Expiry_Date AS expiryDate, Supplier_Name AS supplier, Unit_Price AS unitPrice, Stock_Price AS stockPrice FROM Medicine";
        $res = sqlsrv_query($conn, $sql);
        if ($res === false) throw new Exception('Query failed: ' . print_r(sqlsrv_errors(), true));
        while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
            if (isset($row['expiryDate']) && $row['expiryDate'] instanceof DateTime) {
                $row['expiryDate'] = $row['expiryDate']->format('Y-m-d');
            }
            $meds[] = $row;
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'No database connection available']);
        exit;
    }

    echo json_encode($meds);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
