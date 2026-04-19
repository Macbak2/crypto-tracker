<?php
/**
 * Jednorazowy setup: dodaje kolumnę verification_status do transactions
 * GET /api/setup-verification.php
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

$db = (new Database())->getConnection();
$results = [];

// Dodaj kolumnę verification_status
try {
    $db->exec("ALTER TABLE transactions ADD COLUMN verification_status ENUM('unverified','ok','needs_review') NOT NULL DEFAULT 'unverified'");
    $results[] = "✅ Dodano kolumnę verification_status";
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        $results[] = "ℹ️ Kolumna verification_status już istnieje";
    } else {
        $results[] = "❌ " . $e->getMessage();
    }
}

// Dodaj indeks
try {
    $db->exec("ALTER TABLE transactions ADD INDEX idx_verification_status (verification_status)");
    $results[] = "✅ Dodano indeks";
} catch (Exception $e) {
    $results[] = "ℹ️ Indeks już istnieje lub błąd: " . $e->getMessage();
}

echo json_encode(['success' => true, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
