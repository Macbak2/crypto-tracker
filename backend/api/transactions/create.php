<?php

/**
 * API Endpoint: Dodawanie nowej transakcji
 * POST /api/transactions/create.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

 $data = json_decode(file_get_contents("php://input"));

 if (!$data) {
  http_response_code(400);
  echo json_encode([
   'success' => false,
   'message' => 'Nieprawidłowe dane wejściowe'
  ]);
  exit;
 }

 try {
  $database = new Database();
  $db = $database->getConnection();

  // Walidacja
  if (
   empty($data->market) || empty($data->datetime) || empty($data->type) ||
   empty($data->order_type) || empty($data->rate) || empty($data->amount) || empty($data->value)
  ) {
   throw new Exception('Wszystkie pola są wymagane');
  }

  // Generuj UUID
  $uuid = sprintf(
   '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
   mt_rand(0, 0xffff),
   mt_rand(0, 0xffff),
   mt_rand(0, 0xffff),
   mt_rand(0, 0x0fff) | 0x4000,
   mt_rand(0, 0x3fff) | 0x8000,
   mt_rand(0, 0xffff),
   mt_rand(0, 0xffff),
   mt_rand(0, 0xffff)
  );

  $stmt = $db->prepare("
            INSERT INTO transactions (id, market, datetime, type, order_type, rate, amount, value, notes)
            VALUES (:id, :market, :datetime, :type, :order_type, :rate, :amount, :value, :notes)
        ");

  $stmt->execute([
   ':id' => $uuid,
   ':market' => $data->market,
   ':datetime' => $data->datetime,
   ':type' => $data->type,
   ':order_type' => $data->order_type,
   ':rate' => floatval($data->rate),
   ':amount' => floatval($data->amount),
   ':value' => floatval($data->value),
   ':notes' => $data->notes ?? null
  ]);

  echo json_encode([
   'success' => true,
   'message' => 'Transakcja została dodana',
   'id' => $uuid
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
