<?php

/**
 * API Endpoint: Aktualizacja transakcji z obsługą prowizji
 * PUT /api/transactions/update.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
 http_response_code(200);
 exit();
}

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
 $data = json_decode(file_get_contents("php://input"));

 if (!$data || empty($data->id)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Brak ID transakcji']);
  exit;
 }

 try {
  $database = new Database();
  $db = $database->getConnection();

  // Rozpocznij transakcję bazodanową
  $db->beginTransaction();

  // 1. Zaktualizuj transakcję
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

  // 2. Obsłuż prowizję
  $feeAmountSet = isset($data->fee_amount) && $data->fee_amount !== null;
  $feeAmount = $feeAmountSet ? floatval($data->fee_amount) : null;
  $feeCurrency = isset($data->fee_currency) ? $data->fee_currency : null;

  // Usuń starą prowizję dla tej transakcji (jeśli była dodana ręcznie)
  $stmt = $db->prepare("
            DELETE FROM operations
            WHERE transaction_id = :transaction_id
            AND operation_type = 'Pobranie prowizji za transakcję'
            AND notes = 'Prowizja dodana ręcznie'
        ");
  $stmt->execute([':transaction_id' => $data->id]);

  // Dodaj nową prowizję jeśli waluta i kwota są jawnie podane (kwota może być 0)
  if ($feeCurrency && $feeAmountSet) {
   $stmt = $db->prepare("
                INSERT INTO operations (datetime, operation_type, amount, currency, transaction_id, notes)
                VALUES (:datetime, :operation_type, :amount, :currency, :transaction_id, :notes)
            ");

   $stmt->execute([
    ':datetime' => $data->datetime,
    ':operation_type' => 'Pobranie prowizji za transakcję',
    ':amount' => -abs($feeAmount),
    ':currency' => $feeCurrency,
    ':transaction_id' => $data->id,
    ':notes' => 'Prowizja dodana ręcznie'
   ]);
  }

  // Zatwierdź transakcję
  $db->commit();

  echo json_encode([
   'success' => true,
   'message' => 'Transakcja została zaktualizowana',
   'fee_saved' => $feeCurrency !== null && $feeAmountSet
  ]);
 } catch (Exception $e) {
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
