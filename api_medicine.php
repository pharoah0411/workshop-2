<?php
// api_medicine.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'connection.php'; // Uses your $pdo connection

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Select the columns exactly as they appear in your SQL Server MEDICINE table
    $query = "SELECT MEDICINE_ID, NAME, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE FROM MEDICINE";
    
    if (!empty($search)) {
        $query .= " WHERE NAME LIKE :search OR MEDICINE_ID LIKE :search";
    }

    $stmt = $pdo->prepare($query);
    
    if (!empty($search)) {
        $stmt->execute(['search' => "%$search%"]);
    } else {
        $stmt->execute();
    }

    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $medicines
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>