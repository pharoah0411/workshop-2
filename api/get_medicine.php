<?php
require_once __DIR__ . '/../connection.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid id']); exit; }

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT Id, Name, Category_Type AS category, Quantity_In_Stock AS quantity, Expiry_Date AS expiryDate, Supplier_Name AS supplier, Unit_Price AS unitPrice, Stock_Price AS stockPrice FROM Medicine WHERE Id = :id");
        $stmt->execute([':id' => $id]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($m && !empty($m['expiryDate'])) $m['expiryDate'] = date('Y-m-d', strtotime($m['expiryDate']));
        echo json_encode($m ?: []);
        exit;
    } elseif (isset($conn)) {
        $sql = "SELECT Id, Name, Category_Type AS category, Quantity_In_Stock AS quantity, Expiry_Date AS expiryDate, Supplier_Name AS supplier, Unit_Price AS unitPrice, Stock_Price AS stockPrice FROM Medicine WHERE Id = ?";
        $params = [$id];
        $res = sqlsrv_query($conn, $sql, $params);
        if ($res === false) throw new Exception('Query failed: ' . print_r(sqlsrv_errors(), true));
        $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        if (isset($row['expiryDate']) && $row['expiryDate'] instanceof DateTime) $row['expiryDate'] = $row['expiryDate']->format('Y-m-d');
        echo json_encode($row ?: []);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'No database connection available']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
