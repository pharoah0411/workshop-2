<?php
// Set headers to return JSON and allow cross-origin requests
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// Include your existing connection logic
require_once 'connection.php'; 

$search = isset($_GET['search']) ? $_GET['search'] : '';
$response = [];

try {
    // 1. Prepare SQL (Using your MEDICINE table)
    $query = "SELECT MEDICINE_ID, NAME, QUANTITY_IN_STOCK, EXPIRY_DATE, UNIT_PRICE FROM MEDICINE";
    if (!empty($search)) {
        $query .= " WHERE NAME LIKE :search";
    }

    $stmt = $pdo->prepare($query);
    
    if (!empty($search)) {
        $stmt->execute(['search' => "%$search%"]);
    } else {
        $stmt->execute();
    }

    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Build the JSON Response
    $response = [
        "status" => "success",
        "count" => count($medicines),
        "data" => $medicines
    ];

} catch (PDOException $e) {
    http_response_code(500);
    $response = [
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>