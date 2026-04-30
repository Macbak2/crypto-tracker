<?php
/**
 * GET /api/simple-profit.php
 * Metoda uproszczona: przychody - koszty - prowizje per rok i per kryptowaluta
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';
$db = (new Database())->getConnection();

try {
    // Per rok + per krypto: koszty, przychody i prowizje PLN (z transaction_id)
    $stmt = $db->query("
        SELECT
            YEAR(t.datetime)                        AS year,
            SUBSTRING_INDEX(t.market, '-', 1)       AS crypto,
            SUM(CASE WHEN t.type='buy'  THEN t.value ELSE 0 END) AS buy_cost,
            SUM(CASE WHEN t.type='sell' THEN t.value ELSE 0 END) AS sell_revenue,
            COALESCE(SUM(CASE WHEN o.currency = 'PLN' THEN ABS(o.amount) ELSE 0 END), 0) AS fee_pln
        FROM transactions t
        LEFT JOIN operations o
            ON o.transaction_id = t.id
            AND o.operation_type LIKE '%prowizji%'
        GROUP BY YEAR(t.datetime), SUBSTRING_INDEX(t.market, '-', 1)
        ORDER BY year ASC, crypto ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grupowanie po roku
    $byYear = [];
    foreach ($rows as $row) {
        $year   = (int)$row['year'];
        $crypto = $row['crypto'];

        $buyCost     = (float)$row['buy_cost'];
        $sellRevenue = (float)$row['sell_revenue'];
        $totalFees   = (float)$row['fee_pln'];
        $profit      = $sellRevenue - $buyCost - $totalFees;

        if (!isset($byYear[$year])) {
            $byYear[$year] = [
                'year'        => $year,
                'buy_cost'    => 0,
                'sell_revenue'=> 0,
                'fees'        => 0,
                'profit'      => 0,
                'cryptos'     => [],
            ];
        }

        $byYear[$year]['buy_cost']     += $buyCost;
        $byYear[$year]['sell_revenue'] += $sellRevenue;
        $byYear[$year]['fees']         += $totalFees;
        $byYear[$year]['profit']       += $profit;

        $byYear[$year]['cryptos'][] = [
            'crypto'       => $crypto,
            'buy_cost'     => round($buyCost, 2),
            'sell_revenue' => round($sellRevenue, 2),
            'fees'         => round($totalFees, 2),
            'profit'       => round($profit, 2),
        ];
    }

    // Zaokrąglenie sum rocznych i cumulative
    $cumulative = 0;
    $result = [];
    foreach ($byYear as $year => $data) {
        $cumulative += $data['profit'];
        $result[] = [
            'year'        => $year,
            'buy_cost'    => round($data['buy_cost'], 2),
            'sell_revenue'=> round($data['sell_revenue'], 2),
            'fees'        => round($data['fees'], 2),
            'profit'      => round($data['profit'], 2),
            'cumulative'  => round($cumulative, 2),
            'cryptos'     => $data['cryptos'],
        ];
    }

    echo json_encode(['success' => true, 'years' => $result], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
