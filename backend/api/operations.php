<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
    exit;
}

try {
    $db = (new Database())->getConnection();

    $currency     = $_GET['currency']       ?? null;
    $opType       = $_GET['operation_type'] ?? null;
    $dateFrom     = $_GET['date_from']      ?? null;
    $dateTo       = $_GET['date_to']        ?? null;
    $limit        = isset($_GET['limit'])  ? intval($_GET['limit'])  : 100;
    $offset       = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $allowedSort = ['datetime', 'operation_type', 'currency', 'amount', 'balance_total'];
    $sortBy  = in_array($_GET['sort_by']  ?? '', $allowedSort) ? $_GET['sort_by']  : 'datetime';
    $sortDir = strtoupper($_GET['sort_dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

    $sql    = "SELECT * FROM operations WHERE 1=1";
    $params = [];

    if ($currency) {
        $sql .= " AND currency = :currency";
        $params[':currency'] = $currency;
    }
    if ($opType) {
        $sql .= " AND operation_type = :operation_type";
        $params[':operation_type'] = $opType;
    }
    if ($dateFrom) {
        $sql .= " AND datetime >= :date_from";
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo) {
        $sql .= " AND datetime <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
    $countStmt = $db->prepare($countSql);
    foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
    $countStmt->execute();
    $total = intval($countStmt->fetch()['total']);

    $sql .= " ORDER BY $sortBy $sortDir LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $operations = $stmt->fetchAll();

    // Distinct typy operacji do filtra
    $typesStmt = $db->query("SELECT DISTINCT operation_type FROM operations ORDER BY operation_type");
    $types = array_column($typesStmt->fetchAll(), 'operation_type');

    // Distinct waluty do filtra
    $currStmt = $db->query("SELECT DISTINCT currency FROM operations ORDER BY currency");
    $currencies = array_column($currStmt->fetchAll(), 'currency');

    echo json_encode([
        'success'    => true,
        'operations' => $operations,
        'types'      => $types,
        'currencies' => $currencies,
        'pagination' => [
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
            'pages'  => $limit > 0 ? ceil($total / $limit) : 1,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
