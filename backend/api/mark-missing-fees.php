<?php
/**
 * Oznacza jako needs_review transakcje bez powiązanej prowizji w operations
 * GET /api/mark-missing-fees.php?dry_run=1  — podgląd
 * GET /api/mark-missing-fees.php            — zapisuje
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

$dryRun = isset($_GET['dry_run']);
$db = (new Database())->getConnection();

// Znajdź transakcje bez powiązanej operacji prowizji
$stmt = $db->query("
    SELECT t.id, t.market, t.datetime, t.type, t.verification_status
    FROM transactions t
    LEFT JOIN operations o
        ON o.transaction_id = t.id
        AND o.operation_type LIKE '%prowizji%'
    WHERE o.id IS NULL
      AND t.verification_status = 'unverified'
    ORDER BY t.datetime
");
$toMark = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$dryRun && !empty($toMark)) {
    $ids = array_column($toMark, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $update = $db->prepare("
        UPDATE transactions
        SET verification_status = 'needs_review'
        WHERE id IN ($placeholders)
    ");
    $update->execute($ids);
}

echo json_encode([
    'success'  => true,
    'dry_run'  => $dryRun,
    'count'    => count($toMark),
    'marked'   => $toMark,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
