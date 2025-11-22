<?php
require_once __DIR__ . '/../connection.php';
header('Content-Type: application/json; charset=utf-8');

// Expect JSON POST body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit; }

$name = isset($input['name']) ? trim($input['name']) : '';
$category = isset($input['category']) ? trim($input['category']) : null;
$quantity = isset($input['quantity']) ? intval($input['quantity']) : 0;
$expiry = isset($input['expiryDate']) && $input['expiryDate'] !== '' ? $input['expiryDate'] : null;
$supplier = isset($input['supplier']) ? trim($input['supplier']) : null;
$unitPrice = isset($input['unitPrice']) ? floatval($input['unitPrice']) : null;
$stockPrice = isset($input['stockPrice']) ? floatval($input['stockPrice']) : null;

if ($name === '') { http_response_code(400); echo json_encode(['error' => 'Name is required']); exit; }

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("INSERT INTO Medicine (Name, Category_Type, Quantity_In_Stock, Expiry_Date, Supplier_Name, Unit_Price, Stock_Price) VALUES (:name, :category, :qty, :expiry, :supplier, :unitPrice, :stockPrice)");
        $stmt->execute([
            ':name' => $name,
            ':category' => $category,
            ':qty' => $quantity,
            ':expiry' => $expiry,
            ':supplier' => $supplier,
            ':unitPrice' => $unitPrice,
            ':stockPrice' => $stockPrice
        ]);
        $id = $pdo->lastInsertId();
        echo json_encode(['id' => $id]);
        exit;
    } elseif (isset($conn)) {
        $sql = "INSERT INTO Medicine (Name, Category_Type, Quantity_In_Stock, Expiry_Date, Supplier_Name, Unit_Price, Stock_Price) VALUES (?, ?, ?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS id;";
        $params = [$name, $category, $quantity, $expiry, $supplier, $unitPrice, $stockPrice];
        $res = sqlsrv_query($conn, $sql, $params);
        if ($res === false) throw new Exception('Insert failed: ' . print_r(sqlsrv_errors(), true));
        // Retrieve identity
        sqlsrv_next_result($res);
        $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        $id = $row['id'] ?? null;
        echo json_encode(['id' => $id]);
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
