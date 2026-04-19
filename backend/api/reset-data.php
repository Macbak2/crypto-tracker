<?php
/**
 * Jednorazowy skrypt do czyszczenia danych przed reimportem
 * GET /api/reset-data.php?what=operations          — czyści tylko operacje + fee matching
 * GET /api/reset-data.php?what=all                 — czyści operacje + transakcje + historię importu
 * GET /api/reset-data.php?what=operations&dry_run=1 — tylko podgląd
 *
 * UWAGA: Nie usuwa transakcji dodanych ręcznie (needs_review)
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

$what   = $_GET['what']   ?? '';
$dryRun = isset($_GET['dry_run']);

$allowed = ['operations', 'all'];
if (!in_array($what, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Parametr what musi być: operations | all']);
    exit;
}

$db = (new Database())->getConnection();
$results = [];

// Zlicz przed usunięciem
$counts = [];
foreach (['operations', 'transactions', 'import_history'] as $table) {
    $counts[$table] = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
}

if ($dryRun) {
    echo json_encode([
        'success'  => true,
        'dry_run'  => true,
        'what'     => $what,
        'would_delete' => $what === 'all'
            ? $counts
            : ['operations' => $counts['operations']],
        'note' => $what === 'all'
            ? 'Transakcje z verification_status=needs_review TEŻ zostaną usunięte przy what=all'
            : 'Transakcje i historia importu pozostają niezmienione',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Wyczyść transaction_id w operacjach (fee matching)
$db->exec("UPDATE operations SET transaction_id = NULL");
$results[] = "✅ Wyczyszczono fee matching (transaction_id = NULL)";

// Usuń operacje
$deleted = $db->exec("DELETE FROM operations");
$results[] = "✅ Usunięto $deleted operacji";

if ($what === 'all') {
    $deleted = $db->exec("DELETE FROM transactions");
    $results[] = "✅ Usunięto $deleted transakcji";

    $deleted = $db->exec("DELETE FROM import_history");
    $results[] = "✅ Usunięto $deleted wpisów historii importu";
} else {
    $results[] = "ℹ️ Transakcje ({$counts['transactions']}) i historia importu ({$counts['import_history']}) bez zmian";
}

echo json_encode([
    'success' => true,
    'what'    => $what,
    'before'  => $counts,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
