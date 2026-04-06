<?php

/**
 * API Endpoint: Pobieranie transakcji z prowizjami
 * GET /api/transactions.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

 try {
  $database = new Database();
  $db = $database->getConnection();

  // Parametry filtrowania
  $market = $_GET['market'] ?? null;
  $type = $_GET['type'] ?? null;
  $dateFrom = $_GET['date_from'] ?? null;
  $dateTo = $_GET['date_to'] ?? null;
  $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
  $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

  // Buduj zapytanie
  $sql = "SELECT * FROM transactions WHERE 1=1";
  $params = [];

  if ($market) {
   $sql .= " AND market = :market";
   $params[':market'] = $market;
  }

  if ($type) {
   $sql .= " AND type = :type";
   $params[':type'] = $type;
  }

  if ($dateFrom) {
   $sql .= " AND datetime >= :date_from";
   $params[':date_from'] = $dateFrom . ' 00:00:00';
  }

  if ($dateTo) {
   $sql .= " AND datetime <= :date_to";
   $params[':date_to'] = $dateTo . ' 23:59:59';
  }

  $sql .= " ORDER BY datetime DESC LIMIT :limit OFFSET :offset";

  $stmt = $db->prepare($sql);

  // Bind parametrów
  foreach ($params as $key => $value) {
   $stmt->bindValue($key, $value);
  }
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

  $stmt->execute();
  $transactions = $stmt->fetchAll();

  // Pobierz prowizje dla tych transakcji
  if (!empty($transactions)) {
   $transactionIds = array_column($transactions, 'id');
   $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));

   $feesSql = "
                SELECT
                    transaction_id,
                    amount,
                    currency
                FROM operations
                WHERE transaction_id IN ($placeholders)
                AND operation_type LIKE '%prowizji%'
            ";

   $feesStmt = $db->prepare($feesSql);
   $feesStmt->execute($transactionIds);
   $fees = $feesStmt->fetchAll();

   // Mapuj prowizje do transakcji
   $feesMap = [];
   foreach ($fees as $fee) {
    $txId = $fee['transaction_id'];
    if (!isset($feesMap[$txId])) {
     $feesMap[$txId] = [];
    }
    $feesMap[$txId][] = [
     'amount' => abs(floatval($fee['amount'])),
     'currency' => $fee['currency']
    ];
   }

   // Dodaj prowizje do transakcji
   foreach ($transactions as &$tx) {
    $tx['fees'] = $feesMap[$tx['id']] ?? [];

    // Oblicz łączną prowizję w PLN i crypto osobno
    $tx['fee_pln'] = 0;
    $tx['fee_crypto'] = 0;
    $tx['fee_crypto_currency'] = null;

    foreach ($tx['fees'] as $fee) {
     if ($fee['currency'] === 'PLN') {
      $tx['fee_pln'] += $fee['amount'];
     } else {
      $tx['fee_crypto'] += $fee['amount'];
      $tx['fee_crypto_currency'] = $fee['currency'];
     }
    }
   }
   unset($tx); // Usuń referencję
  }

  // Policz wszystkie (bez limitu)
  $countSql = "SELECT COUNT(*) as total FROM transactions WHERE 1=1";
  if ($market) $countSql .= " AND market = :market";
  if ($type) $countSql .= " AND type = :type";
  if ($dateFrom) $countSql .= " AND datetime >= :date_from";
  if ($dateTo) $countSql .= " AND datetime <= :date_to";

  $countStmt = $db->prepare($countSql);
  foreach ($params as $key => $value) {
   $countStmt->bindValue($key, $value);
  }
  $countStmt->execute();
  $total = $countStmt->fetch()['total'];

  echo json_encode([
   'success' => true,
   'transactions' => $transactions,
   'pagination' => [
    'total' => intval($total),
    'limit' => $limit,
    'offset' => $offset,
    'pages' => ceil($total / $limit)
   ]
  ]);
 } catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
   'success' => false,
   'message' => 'Błąd podczas pobierania danych: ' . $e->getMessage()
  ]);
 }
} else {
 http_response_code(405);
 echo json_encode([
  'success' => false,
  'message' => 'Metoda nie dozwolona'
 ]);
}
