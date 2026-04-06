<?php

/**
 * API Endpoint: Edycja transakcji
 * PUT /api/transactions/update.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

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

  $stmt = $db->prepare("
            UPDATE transactions 
            SET market = :market,
                datetime = :datetime,
                type = :type,
                order_type = :order_type,
                rate = :rate,
                amount = :amount,
                value = :value,
                notes = :notes
            WHERE id = :id
        ");

  $stmt->execute([
   ':id' => $data->id,
   ':market' => $data->market,
   ':datetime' => $data->datetime,
   ':type' => $data->type,
   ':order_type' => $data->order_type,
   ':rate' => floatval($data->rate),
   ':amount' => floatval($data->amount),
   ':value' => floatval($data->value),
   ':notes' => $data->notes ?? null
  ]);

  if ($stmt->rowCount() === 0) {
   throw new Exception('Nie znaleziono transakcji lub dane są identyczne');
  }

  echo json_encode([
   'success' => true,
   'message' => 'Transakcja została zaktualizowana'
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
