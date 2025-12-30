<?php
// api_medicine.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'connection.php'; // Uses your $pdo connection

try {
    // Check if the connection exists
    if (!$pdo) {
        throw new Exception("Database connection failed.");
    }

    // SQL Server specific query with date conversion
    $query = "SELECT MEDICINE_ID, NAME, QUANTITY_IN_STOCK, 
              CONVERT(VARCHAR, EXPIRY_DATE, 23) AS EXPIRY_DATE, 
              UNIT_PRICE FROM MEDICINE";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $medicines
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>