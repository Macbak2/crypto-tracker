<?php
/**
 * Skrypt dopasowujący prowizje (operations) do transakcji (transactions)
 * GET /api/match-fees.php          — uruchamia dopasowanie
 * GET /api/match-fees.php?reset=1  — resetuje dopasowania i robi od nowa
 * GET /api/match-fees.php?dry_run=1 — podgląd bez zapisu
 */

header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $reset   = isset($_GET['reset'])   && $_GET['reset']   == '1';
    $dryRun  = isset($_GET['dry_run']) && $_GET['dry_run'] == '1';
    $timeWindow = isset($_GET['window']) ? max(1, min(30, (int)$_GET['window'])) : 5;

    // Reset: wyczyść poprzednie dopasowania
    if ($reset && !$dryRun) {
        $db->exec("UPDATE operations SET transaction_id = NULL WHERE operation_type LIKE '%prowizji%'");
    }

    // Pobierz wszystkie prowizje bez dopasowania
    $fees = $db->query("
        SELECT id, datetime, amount, currency
        FROM operations
        WHERE operation_type LIKE '%prowizji%'
        AND transaction_id IS NULL
        ORDER BY datetime
    ")->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'total_fees'     => count($fees),
        'matched'        => 0,
        'ambiguous'      => 0,
        'no_match'       => 0,
    ];
    $ambiguousList = [];
    $noMatchList   = [];

    $updateStmt = $db->prepare("UPDATE operations SET transaction_id = ? WHERE id = ?");

    foreach ($fees as $fee) {
        $feeCurrency = $fee['currency'];
        $feeAmount   = abs((float)$fee['amount']);
        $feeDatetime = $fee['datetime'];

        // Znajdź kandydatów: czas ±window sekund, waluta pasuje do typu transakcji
        $candidates = $db->prepare("
            SELECT
                id, market, type, amount, value, rate,
                ABS(TIMESTAMPDIFF(SECOND, datetime, ?)) AS time_diff
            FROM transactions
            WHERE ABS(TIMESTAMPDIFF(SECOND, datetime, ?)) <= ?
            AND (
                (type = 'buy'  AND SUBSTRING_INDEX(market, '-', 1)  = ?)
                OR
                (type = 'sell' AND SUBSTRING_INDEX(market, '-', -1) = ?)
            )
            ORDER BY time_diff ASC
        ");
        $candidates->execute([$feeDatetime, $feeDatetime, $timeWindow, $feeCurrency, $feeCurrency]);
        $matches = $candidates->fetchAll(PDO::FETCH_ASSOC);

        if (count($matches) === 0) {
            $stats['no_match']++;
            $noMatchList[] = [
                'fee_id'       => $fee['id'],
                'datetime'     => $feeDatetime,
                'amount'       => $feeAmount,
                'currency'     => $feeCurrency,
            ];
            continue;
        }

        if (count($matches) === 1) {
            // Jednoznaczne dopasowanie
            if (!$dryRun) {
                $updateStmt->execute([$matches[0]['id'], $fee['id']]);
            }
            $stats['matched']++;
            continue;
        }

        // Wiele kandydatów — dopasuj po proporcji prowizji do wartości transakcji
        // Prowizja Zonda: ~0.10%–0.42% wartości transakcji
        $best      = null;
        $bestScore = PHP_FLOAT_MAX;

        foreach ($matches as $tx) {
            $base = ($tx['type'] === 'buy')
                ? (float)$tx['amount']   // prowizja w crypto = % od kupionej ilości
                : (float)$tx['value'];   // prowizja w PLN/crypto = % od wartości

            if ($base <= 0) continue;

            $ratio = $feeAmount / $base;
            // Oczekiwany zakres prowizji: 0.05% – 0.60%
            $distanceFromCenter = abs($ratio - 0.003); // środek przedziału ~0.3%
            if ($distanceFromCenter < $bestScore) {
                $bestScore = $distanceFromCenter;
                $best      = $tx;
            }
        }

        // Uznaj za niejednoznaczne jeśli ratio poza sensownym zakresem
        $base = $best
            ? (($best['type'] === 'buy') ? (float)$best['amount'] : (float)$best['value'])
            : 0;
        $ratio = ($base > 0) ? $feeAmount / $base : 0;

        if ($best && $ratio >= 0.0005 && $ratio <= 0.006) {
            if (!$dryRun) {
                $updateStmt->execute([$best['id'], $fee['id']]);
            }
            $stats['matched']++;
        } else {
            $stats['ambiguous']++;
            $ambiguousList[] = [
                'fee_id'      => $fee['id'],
                'datetime'    => $feeDatetime,
                'amount'      => $feeAmount,
                'currency'    => $feeCurrency,
                'candidates'  => array_map(fn($m) => [
                    'tx_id'   => $m['id'],
                    'market'  => $m['market'],
                    'type'    => $m['type'],
                    'value'   => $m['value'],
                ], $matches),
            ];
        }
    }

    echo json_encode([
        'success'     => true,
        'dry_run'     => $dryRun,
        'reset'       => $reset,
        'time_window' => $timeWindow,
        'stats'       => $stats,
        'ambiguous'   => $ambiguousList,
        'no_match'    => $noMatchList,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
