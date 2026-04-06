<?php

/**
 * API Endpoint: Usuwanie transakcji
 * DELETE /api/transactions/delete.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

 $data = json_decode(file_get_contents("php://input"));

 if (!$data || empty($data->id)) {
  http_response_code(400);
  echo json_encode([
   'success' => false,
   'message' => 'Brak ID transakcji'
  ]);
  exit;
 }

 try {
  $database = new Database();
  $db = $database->getConnection();

  $stmt = $db->prepare("DELETE FROM transactions WHERE id = :id");
  $stmt->execute([':id' => $data->id]);

  if ($stmt->rowCount() === 0) {
   throw new Exception('Nie znaleziono transakcji');
  }

  echo json_encode([
   'success' => true,
   'message' => 'Transakcja została usunięta'
  ]);
 } catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
   'success' => false,
   'message' => 'Błąd: ' . $e->getMessage()
  ]);
 }
} else {
 http_response_code(405);
 echo json_encode([
  'success' => false,
  'message' => 'Metoda nie dozwolona'
 ]);
}
