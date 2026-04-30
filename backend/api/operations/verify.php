<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../../config/database.php';

$body   = json_decode(file_get_contents('php://input'), true);
$id     = $body['id']     ?? null;
$status = $body['status'] ?? null;

$allowed = ['unverified', 'ok', 'needs_review'];
if (!$id || !in_array($status, $allowed)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nieprawidłowe parametry']);
    exit;
}

$db = (new Database())->getConnection();
$stmt = $db->prepare("UPDATE operations SET verification_status = ? WHERE id = ?");
$stmt->execute([$status, $id]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Operacja nie istnieje']);
    exit;
}

echo json_encode(['success' => true, 'id' => $id, 'status' => $status]);
