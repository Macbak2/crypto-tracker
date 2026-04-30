<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once '../../config/database.php';

try {
    $db = (new Database())->getConnection();

    $market    = $_GET['market']    ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to   = $_GET['date_to']   ?? '';

    $where  = [];
    $params = [];

    if ($market) {
        $where[]  = 't.market = ?';
        $params[] = $market;
    }
    if ($date_from) {
        $where[]  = 'DATE(t.datetime) >= ?';
        $params[] = $date_from;
    }
    if ($date_to) {
        $where[]  = 'DATE(t.datetime) <= ?';
        $params[] = $date_to;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT
            t.type,
            COUNT(DISTINCT t.id)                                                           AS count,
            SUM(t.amount)                                                                  AS total_amount,
            SUM(t.value)                                                                   AS total_value,
            COALESCE(SUM(CASE WHEN o.currency = 'PLN'  THEN ABS(o.amount) ELSE 0 END), 0) AS fee_pln,
            COALESCE(SUM(CASE WHEN o.currency != 'PLN' THEN ABS(o.amount) ELSE 0 END), 0) AS fee_crypto,
            -- Kupno: dziel przez ilość netto (po odjęciu prowizji krypto) = efektywny koszt zakupu
            -- Sprzedaż: pomijaj transakcje z value=0 (transfery/straty) żeby nie psuć średniej
            CASE t.type
                WHEN 'buy' THEN
                    SUM(t.value) / NULLIF(
                        SUM(t.amount) - COALESCE(SUM(CASE WHEN o.currency != 'PLN' THEN ABS(o.amount) ELSE 0 END), 0),
                    0)
                WHEN 'sell' THEN
                    SUM(CASE WHEN t.value > 0 THEN t.value ELSE NULL END)
                    / NULLIF(SUM(CASE WHEN t.value > 0 THEN t.amount ELSE NULL END), 0)
            END AS avg_rate
        FROM transactions t
        LEFT JOIN operations o
            ON o.transaction_id = t.id
            AND o.operation_type LIKE '%prowizji%'
        $whereClause
        GROUP BY t.type
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = ['buy' => null, 'sell' => null];
    foreach ($rows as $row) {
        $totalAmount = (float) $row['total_amount'];
        $totalValue  = (float) $row['total_value'];
        $feePln      = (float) $row['fee_pln'];
        $feeCrypto   = (float) $row['fee_crypto'];

        $summary[$row['type']] = [
            'count'        => (int)   $row['count'],
            'total_amount' => $totalAmount,
            'total_value'  => $totalValue,
            'avg_rate'     => (float) $row['avg_rate'],
            'fee_pln'      => $feePln,
            'fee_crypto'   => $feeCrypto,
            // Netto: ile faktycznie otrzymano po prowizji
            'net_amount'   => $row['type'] === 'buy'
                ? $totalAmount - $feeCrypto      // krypto po prowizji
                : $totalValue  - $feePln,        // PLN po prowizji
        ];
    }

    // Bilans uwzględniający prowizje
    $buy_cost    = ($summary['buy']['total_value'] ?? 0) + ($summary['buy']['fee_pln'] ?? 0);
    $sell_income = ($summary['sell']['net_amount'] ?? 0); // PLN netto ze sprzedaży
    $balance     = $sell_income - $buy_cost;

    // Pozostałe krypto = kupione netto - sprzedane brutto
    $remaining_crypto = ($summary['buy']['net_amount'] ?? 0) - ($summary['sell']['total_amount'] ?? 0);

    // Próg rentowności
    $break_even = null;
    if ($balance < 0 && $remaining_crypto > 0.000000001) {
        $break_even = abs($balance) / $remaining_crypto;
    }

    echo json_encode([
        'success'          => true,
        'summary'          => $summary,
        'balance'          => round($balance, 2),
        'remaining_crypto' => round($remaining_crypto, 8),
        'break_even'       => $break_even ? round($break_even, 2) : null,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
