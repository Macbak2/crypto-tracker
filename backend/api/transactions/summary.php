<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../config/database.php';

try {
    $db = (new Database())->getConnection();

    $market    = $_GET['market']    ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to   = $_GET['date_to']   ?? '';

    $where  = [];
    $params = [];

    if ($market) {
        $where[]  = 'market = ?';
        $params[] = $market;
    }
    if ($date_from) {
        $where[]  = 'DATE(datetime) >= ?';
        $params[] = $date_from;
    }
    if ($date_to) {
        $where[]  = 'DATE(datetime) <= ?';
        $params[] = $date_to;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT
            type,
            COUNT(*)                              AS count,
            SUM(amount)                           AS total_amount,
            SUM(value)                            AS total_value,
            SUM(value) / NULLIF(SUM(amount), 0)   AS avg_rate
        FROM transactions
        $whereClause
        GROUP BY type
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = ['buy' => null, 'sell' => null];
    foreach ($rows as $row) {
        $summary[$row['type']] = [
            'count'        => (int)   $row['count'],
            'total_amount' => (float) $row['total_amount'],
            'total_value'  => (float) $row['total_value'],
            'avg_rate'     => (float) $row['avg_rate'],
        ];
    }

    $bought_value = $summary['buy']['total_value']  ?? 0;
    $sold_value   = $summary['sell']['total_value'] ?? 0;
    $balance      = $sold_value - $bought_value;

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'balance' => round($balance, 2),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
