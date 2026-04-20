<?php

/**
 * API Endpoint: Tworzenie nowej transakcji z obsługą prowizji
 * POST /api/transactions/create.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
 http_response_code(200);
 exit();
}

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $data = json_decode(file_get_contents("php://input"));

 if (!$data) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Nieprawidłowe dane']);
  exit;
 }

 try {
  $database = new Database();
  $db = $database->getConnection();

  // Walidacja wymaganych pól
  if (
   empty($data->market) || empty($data->datetime) || empty($data->type) ||
   empty($data->order_type) || empty($data->rate) || empty($data->amount) || empty($data->value)
  ) {
   throw new Exception('Wszystkie pola są wymagane');
  }

  // Generuj UUID dla transakcji
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

  // Rozpocznij transakcję bazodanową
  $db->beginTransaction();

  // 1. Zapisz transakcję
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

  // 2. Jeśli jest prowizja, zapisz do operations
  $feeAmountSet = isset($data->fee_amount) && $data->fee_amount !== null;
  $feeAmount = $feeAmountSet ? floatval($data->fee_amount) : null;
  $feeCurrency = isset($data->fee_currency) ? $data->fee_currency : null;

  if ($feeCurrency && $feeAmountSet) {
   $stmt = $db->prepare("
                INSERT INTO operations (datetime, operation_type, amount, currency, transaction_id, notes)
                VALUES (:datetime, :operation_type, :amount, :currency, :transaction_id, :notes)
            ");

   $stmt->execute([
    ':datetime' => $data->datetime,
    ':operation_type' => 'Pobranie prowizji za transakcję',
    ':amount' => -abs($feeAmount), // Prowizja jako wartość ujemna
    ':currency' => $feeCurrency,
    ':transaction_id' => $uuid,
    ':notes' => 'Prowizja dodana ręcznie'
   ]);
  }

  // Zatwierdź transakcję
  $db->commit();

  echo json_encode([
   'success' => true,
   'message' => 'Transakcja została dodana' . ($feeCurrency && $feeAmountSet ? ' wraz z prowizją' : ''),
   'id' => $uuid,
   'fee_saved' => $feeCurrency !== null && $feeAmountSet
  ]);
 } catch (Exception $e) {
  // Wycofaj transakcję w razie błędu
  if (isset($db) && $db->inTransaction()) {
   $db->rollBack();
  }

  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Błąd: ' . $e->getMessage()]);
 }
} else {
 http_response_code(405);
 echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
}
