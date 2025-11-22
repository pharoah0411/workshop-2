<?php
require_once __DIR__ . '/../connection.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid id']); exit; }

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare('DELETE FROM Medicine WHERE Id = :id');
        $stmt->execute([':id' => $id]);
        echo json_encode(['deleted' => $stmt->rowCount()]);
        exit;
    } elseif (isset($conn)) {
        $sql = 'DELETE FROM Medicine WHERE Id = ?';
        $res = sqlsrv_query($conn, $sql, [$id]);
        if ($res === false) throw new Exception('Delete failed: ' . print_r(sqlsrv_errors(), true));
        echo json_encode(['deleted' => 1]);
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
