<?php
// api_prescriptions.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'connection.php'; // Uses your $pdo SQL Server connection

try {
    if (!$pdo) {
        throw new Exception("Database connection failed.");
    }

    // Correct Query: Join tables to get Patient and Pharmacist names
    // Note: Use [USER] because 'USER' is a reserved word in SQL Server
    $query = "SELECT 
                pr.PRESCRIPTION_ID, 
                p.NAME AS PATIENT_NAME, 
                u.NAME AS PHARMACIST_NAME, 
                CONVERT(VARCHAR, pr.DATE_ISSUED, 120) AS DATE_ISSUED, 
                pr.STATUS 
              FROM PRESCRIPTION pr
              JOIN PATIENT p ON pr.PATIENT_ID = p.PATIENT_ID
              LEFT JOIN [USER] u ON pr.PHARMACIST_ID = u.USER_ID";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $prescriptions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>