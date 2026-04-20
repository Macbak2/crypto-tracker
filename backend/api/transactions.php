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

  $allowedSortColumns = ['datetime', 'rate', 'amount', 'value', 'market', 'type'];
  $sortBy  = in_array($_GET['sort_by']  ?? '', $allowedSortColumns) ? $_GET['sort_by']  : 'datetime';
  $sortDir = strtoupper($_GET['sort_dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

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

  $sql .= " ORDER BY $sortBy $sortDir LIMIT :limit OFFSET :offset";

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
                    currency,
                    balance_total
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
     'amount'        => abs(floatval($fee['amount'])),
     'currency'      => $fee['currency'],
     'balance_total' => floatval($fee['balance_total']),
    ];
   }

   // Dodaj prowizje do transakcji
   foreach ($transactions as &$tx) {
    $tx['fees'] = $feesMap[$tx['id']] ?? [];

    // Oblicz łączną prowizję w PLN i crypto osobno
    $tx['fee_pln'] = 0;
    $tx['fee_crypto'] = 0;
    $tx['fee_crypto_currency'] = null;
    $tx['balance_after'] = null;
    $tx['balance_after_currency'] = null;

    foreach ($tx['fees'] as $fee) {
     if ($fee['currency'] === 'PLN') {
      $tx['fee_pln'] += $fee['amount'];
     } else {
      $tx['fee_crypto'] += $fee['amount'];
      $tx['fee_crypto_currency'] = $fee['currency'];
     }
     // Saldo po operacji z pliku szczegółowego (niezerowe = dane z giełdy)
     if ($fee['balance_total'] != 0) {
      $tx['balance_after']          = $fee['balance_total'];
      $tx['balance_after_currency'] = $fee['currency'];
      $tx['balance_after_source']   = 'exchange';
     }
    }

    $tx['has_real_fee'] = $tx['fee_pln'] > 0 || $tx['fee_crypto'] > 0;

    // Netto — ile faktycznie trafiło do portfela po prowizji
    if ($tx['type'] === 'buy') {
     $tx['net_received']          = floatval($tx['amount']) - $tx['fee_crypto'];
     $tx['net_received_currency'] = $tx['fee_crypto_currency']
      ?? explode('-', $tx['market'])[0];
    } else {
     $tx['net_received']          = floatval($tx['value']) - $tx['fee_pln'];
     $tx['net_received_currency'] = explode('-', $tx['market'])[1] ?? 'PLN';
    }
   }
   unset($tx);

   // Saldo krypto dla zakupów bez danych giełdowych:
   // znajdź ostatnią operację danej waluty z niezerowym balance_total.
   $cryptoBalStmt = $db->prepare("
    SELECT balance_total
    FROM operations
    WHERE currency = ?
      AND balance_total != 0
      AND datetime <= ?
    ORDER BY datetime DESC, id DESC
    LIMIT 1
   ");

   foreach ($transactions as &$tx) {
    if (isset($tx['balance_after_source'])) continue;
    if ($tx['type'] !== 'buy') continue;
    $currency = explode('-', $tx['market'])[0];
    $cryptoBalStmt->execute([$currency, $tx['datetime']]);
    $row = $cryptoBalStmt->fetch();
    if ($row !== false) {
     $tx['balance_after']          = floatval($row['balance_total']);
     $tx['balance_after_currency'] = $currency;
     $tx['balance_after_source']   = 'exchange';
    }
   }
   unset($tx);

   // Saldo PLN dla sprzedaży bez danych giełdowych:
   // znajdź ostatnią operację PLN z niezerowym balance_total w okolicach tej transakcji.
   $plnBalStmt = $db->prepare("
    SELECT balance_total
    FROM operations
    WHERE currency = 'PLN'
      AND balance_total != 0
      AND datetime <= ?
    ORDER BY datetime DESC, id DESC
    LIMIT 1
   ");

   foreach ($transactions as &$tx) {
    if (isset($tx['balance_after_source'])) continue;
    if ($tx['type'] !== 'sell') continue;
    $plnBalStmt->execute([$tx['datetime']]);
    $row = $plnBalStmt->fetch();
    if ($row !== false) {
     $tx['balance_after']          = floatval($row['balance_total']);
     $tx['balance_after_currency'] = 'PLN';
     $tx['balance_after_source']   = 'exchange';
    }
   }
   unset($tx);
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
