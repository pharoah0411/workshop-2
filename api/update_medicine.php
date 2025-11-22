<?php
require_once __DIR__ . '/../connection.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['id'])) { http_response_code(400); echo json_encode(['error' => 'Invalid input']); exit; }

$id = intval($input['id']);
$name = isset($input['name']) ? trim($input['name']) : null;
$category = isset($input['category']) ? trim($input['category']) : null;
$quantity = isset($input['quantity']) ? intval($input['quantity']) : null;
$expiry = isset($input['expiryDate']) && $input['expiryDate'] !== '' ? $input['expiryDate'] : null;
$supplier = isset($input['supplier']) ? trim($input['supplier']) : null;
$unitPrice = isset($input['unitPrice']) ? floatval($input['unitPrice']) : null;
$stockPrice = isset($input['stockPrice']) ? floatval($input['stockPrice']) : null;

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $fields = [];
        $params = [':id' => $id];
        if ($name !== null) { $fields[] = 'Name = :name'; $params[':name'] = $name; }
        if ($category !== null) { $fields[] = 'Category_Type = :category'; $params[':category'] = $category; }
        if ($quantity !== null) { $fields[] = 'Quantity_In_Stock = :qty'; $params[':qty'] = $quantity; }
        if ($expiry !== null) { $fields[] = 'Expiry_Date = :expiry'; $params[':expiry'] = $expiry; }
        if ($supplier !== null) { $fields[] = 'Supplier_Name = :supplier'; $params[':supplier'] = $supplier; }
        if ($unitPrice !== null) { $fields[] = 'Unit_Price = :unitPrice'; $params[':unitPrice'] = $unitPrice; }
        if ($stockPrice !== null) { $fields[] = 'Stock_Price = :stockPrice'; $params[':stockPrice'] = $stockPrice; }

        if (empty($fields)) { echo json_encode(['updated' => 0]); exit; }

        $sql = 'UPDATE Medicine SET ' . implode(', ', $fields) . ' WHERE Id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['updated' => $stmt->rowCount()]);
        exit;
    } elseif (isset($conn)) {
        // Build query for sqlsrv
        $sets = [];
        $params = [];
        if ($name !== null) { $sets[] = 'Name = ?'; $params[] = $name; }
        if ($category !== null) { $sets[] = 'Category_Type = ?'; $params[] = $category; }
        if ($quantity !== null) { $sets[] = 'Quantity_In_Stock = ?'; $params[] = $quantity; }
        if ($expiry !== null) { $sets[] = 'Expiry_Date = ?'; $params[] = $expiry; }
        if ($supplier !== null) { $sets[] = 'Supplier_Name = ?'; $params[] = $supplier; }
        if ($unitPrice !== null) { $sets[] = 'Unit_Price = ?'; $params[] = $unitPrice; }
        if ($stockPrice !== null) { $sets[] = 'Stock_Price = ?'; $params[] = $stockPrice; }

        if (empty($sets)) { echo json_encode(['updated' => 0]); exit; }

        $sql = 'UPDATE Medicine SET ' . implode(', ', $sets) . ' WHERE Id = ?';
        $params[] = $id;
        $res = sqlsrv_query($conn, $sql, $params);
        if ($res === false) throw new Exception('Update failed: ' . print_r(sqlsrv_errors(), true));
        echo json_encode(['updated' => 1]);
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
