<?php

/**
 * API Endpoint: Analiza zysków/strat metodą FIFO + Break-even
 * GET /api/profit-analysis.php
 *
 * Parametry:
 *   - year (opcjonalny) - rok do analizy, domyślnie wszystkie lata
 *   - crypto (opcjonalny) - konkretna kryptowaluta, domyślnie wszystkie
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
 http_response_code(200);
 exit();
}

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
 http_response_code(405);
 echo json_encode(['success' => false, 'error' => 'Method not allowed']);
 exit();
}

$database = new Database();
$db = $database->getConnection();

$year = isset($_GET['year']) ? (int)$_GET['year'] : null;
$cryptoFilter = isset($_GET['crypto']) ? $_GET['crypto'] : null;

try {
 // Pobierz unikalne kryptowaluty
 $cryptoSql = "SELECT DISTINCT SUBSTRING_INDEX(market, '-', 1) as crypto FROM transactions";
 if ($cryptoFilter) {
  $cryptoSql .= " WHERE market LIKE :crypto";
 }
 $cryptoSql .= " ORDER BY crypto";

 $stmt = $db->prepare($cryptoSql);
 if ($cryptoFilter) {
  $stmt->bindValue(':crypto', $cryptoFilter . '-%');
 }
 $stmt->execute();
 $cryptos = $stmt->fetchAll(PDO::FETCH_COLUMN);

 $results = [];
 $totalRealizedProfit = 0;
 $totalUnrealizedCost = 0;
 $totalFeesPLN = 0;

 foreach ($cryptos as $crypto) {
  // Pobierz wszystkie transakcje dla tej kryptowaluty
  $sql = "SELECT id, market, datetime, type, rate, amount, value
                FROM transactions
                WHERE market LIKE :market";
  $params = [':market' => $crypto . '-%'];

  if ($year) {
   $sql .= " AND YEAR(datetime) <= :year";
   $params[':year'] = $year;
  }

  $sql .= " ORDER BY datetime ASC, id ASC";

  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $transactions = $stmt->fetchAll();

  if (empty($transactions)) {
   continue;
  }

  // Pobierz prowizje dla tej kryptowaluty (w jednostkach crypto)
  $feesSql = "SELECT SUM(ABS(amount)) as total
                    FROM operations
                    WHERE operation_type LIKE '%prowizji%'
                    AND currency = :crypto";
  $feesParams = [':crypto' => $crypto];

  if ($year) {
   $feesSql .= " AND YEAR(datetime) <= :year";
   $feesParams[':year'] = $year;
  }

  $stmt = $db->prepare($feesSql);
  $stmt->execute($feesParams);
  $feesResult = $stmt->fetch();
  $feesCrypto = $feesResult ? floatval($feesResult['total']) : 0;

  // Pobierz prowizje w PLN powiązane z tą kryptowalutą (przybliżenie)
  $feesPLNSql = "SELECT SUM(ABS(o.amount)) as total
                       FROM operations o
                       WHERE o.operation_type LIKE '%prowizji%'
                       AND o.currency = 'PLN'
                       AND EXISTS (
                           SELECT 1 FROM transactions t
                           WHERE t.market LIKE :market
                           AND DATE(t.datetime) = DATE(o.datetime)
                       )";
  $feesPLNParams = [':market' => $crypto . '-%'];

  if ($year) {
   $feesPLNSql .= " AND YEAR(o.datetime) <= :year";
   $feesPLNParams[':year'] = $year;
  }

  $stmt = $db->prepare($feesPLNSql);
  $stmt->execute($feesPLNParams);
  $feesPLNResult = $stmt->fetch();
  $feesPLN = $feesPLNResult ? floatval($feesPLNResult['total']) : 0;

  // ========================================
  // ALGORYTM FIFO
  // ========================================

  $buyQueue = []; // Kolejka zakupów FIFO
  $realizedProfit = 0; // Zrealizowany zysk/strata
  $totalBought = 0;
  $totalSold = 0;
  $totalSpent = 0;
  $totalEarned = 0;

  foreach ($transactions as $tx) {
   $type = strtolower($tx['type']);
   $amount = floatval($tx['amount']);
   $rate = floatval($tx['rate']);
   $value = floatval($tx['value']);

   if ($type === 'buy' || $type === 'kupno') {
    $buyQueue[] = [
     'amount' => $amount,
     'rate' => $rate,
     'value' => $value,
     'datetime' => $tx['datetime']
    ];
    $totalBought += $amount;
    $totalSpent += $value;
   } elseif ($type === 'sell' || $type === 'sprzedaż' || $type === 'sprzedaz') {
    $remainingToSell = $amount;
    $costBasis = 0;

    while ($remainingToSell > 0.00000001 && !empty($buyQueue)) {
     if ($buyQueue[0]['amount'] <= $remainingToSell) {
      $costBasis += $buyQueue[0]['value'];
      $remainingToSell -= $buyQueue[0]['amount'];
      array_shift($buyQueue);
     } else {
      $fraction = $remainingToSell / $buyQueue[0]['amount'];
      $costBasis += $buyQueue[0]['value'] * $fraction;
      $buyQueue[0]['amount'] -= $remainingToSell;
      $buyQueue[0]['value'] *= (1 - $fraction);
      $remainingToSell = 0;
     }
    }

    $realizedProfit += ($value - $costBasis);
    $totalSold += $amount;
    $totalEarned += $value;
   }
  }

  // Oblicz pozostałe holdings
  $holdingsAmount = 0;
  $holdingsCost = 0;
  $avgBuyRate = 0;

  foreach ($buyQueue as $buy) {
   $holdingsAmount += $buy['amount'];
   $holdingsCost += $buy['value'];
  }

  if ($holdingsAmount > 0.00000001) {
   $avgBuyRate = $holdingsCost / $holdingsAmount;
  }

  // Uwzględnij prowizje w kosztach
  $feesValuePLN = ($feesCrypto * $avgBuyRate) + $feesPLN;

  // Break-even
  $breakEvenRate = 0;
  $totalLoss = 0;

  if ($holdingsAmount > 0.00000001) {
   if ($realizedProfit < 0) {
    $totalLoss = abs($realizedProfit);
   }
   $breakEvenRate = ($holdingsCost + $totalLoss + $feesValuePLN) / $holdingsAmount;
  }

  // Status: zysk, strata, lub brak pozycji
  $netProfit = $realizedProfit - $feesValuePLN;
  $status = 'neutral';
  if ($netProfit > 0) $status = 'profit';
  elseif ($netProfit < 0) $status = 'loss';

  $results[] = [
   'crypto' => $crypto,
   'totalBought' => round($totalBought, 8),
   'totalSold' => round($totalSold, 8),
   'totalSpent' => round($totalSpent, 2),
   'totalEarned' => round($totalEarned, 2),
   'holdingsAmount' => round($holdingsAmount, 8),
   'holdingsCost' => round($holdingsCost, 2),
   'avgBuyRate' => round($avgBuyRate, 2),
   'realizedProfit' => round($realizedProfit, 2),
   'feesCrypto' => round($feesCrypto, 8),
   'feesPLN' => round($feesPLN, 2),
   'feesValuePLN' => round($feesValuePLN, 2),
   'netProfit' => round($netProfit, 2),
   'breakEvenRate' => round($breakEvenRate, 2),
   'status' => $status
  ];

  $totalRealizedProfit += $realizedProfit;
  $totalUnrealizedCost += $holdingsCost;
  $totalFeesPLN += $feesValuePLN;
 }

 // Sortuj po netProfit (od najgorszych do najlepszych)
 usort($results, function ($a, $b) {
  return $a['netProfit'] <=> $b['netProfit'];
 });

 echo json_encode([
  'success' => true,
  'year' => $year,
  'cryptoFilter' => $cryptoFilter,
  'summary' => [
   'totalRealizedProfit' => round($totalRealizedProfit, 2),
   'totalUnrealizedCost' => round($totalUnrealizedCost, 2),
   'totalFees' => round($totalFeesPLN, 2),
   'netProfit' => round($totalRealizedProfit - $totalFeesPLN, 2),
   'cryptoCount' => count($results)
  ],
  'analysis' => $results
 ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
 http_response_code(500);
 echo json_encode([
  'success' => false,
  'error' => 'Error: ' . $e->getMessage()
 ]);
}
