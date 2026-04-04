<?php
// ================================================
// API: Statystyki i podsumowania
// Endpoint: GET /api/statistics.php
// ================================================

require_once '../config/database.php';

enableCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

try {
    // Podsumowanie transakcji per kryptowaluta
    $stmt = $db->prepare("
        SELECT 
            SUBSTRING_INDEX(market, '-', 1) as crypto,
            SUM(CASE WHEN type = 'buy' THEN amount ELSE 0 END) as total_bought,
            SUM(CASE WHEN type = 'sell' THEN amount ELSE 0 END) as total_sold,
            SUM(CASE WHEN type = 'buy' THEN value ELSE 0 END) as total_spent,
            SUM(CASE WHEN type = 'sell' THEN value ELSE 0 END) as total_earned,
            COUNT(CASE WHEN type = 'buy' THEN 1 END) as buy_count,
            COUNT(CASE WHEN type = 'sell' THEN 1 END) as sell_count
        FROM transactions
        WHERE YEAR(datetime) = ?
        GROUP BY crypto
        ORDER BY total_spent DESC
    ");
    $stmt->execute([$year]);
    $perCrypto = $stmt->fetchAll();
    
    // Prowizje per waluta
    $stmt = $db->prepare("
        SELECT 
            currency,
            SUM(ABS(amount)) as total_fees
        FROM operations
        WHERE operation_type LIKE '%prowizji%'
        AND YEAR(datetime) = ?
        GROUP BY currency
    ");
    $stmt->execute([$year]);
    $fees = $stmt->fetchAll();
    
    // Podsumowanie miesięczne
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(datetime, '%Y-%m') as month,
            SUM(CASE WHEN type = 'buy' THEN value ELSE 0 END) as spent,
            SUM(CASE WHEN type = 'sell' THEN value ELSE 0 END) as earned,
            COUNT(*) as transaction_count
        FROM transactions
        WHERE YEAR(datetime) = ?
        GROUP BY month
        ORDER BY month
    ");
    $stmt->execute([$year]);
    $monthly = $stmt->fetchAll();
    
    // Podsumowanie roczne
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN type = 'buy' THEN value ELSE 0 END) as total_spent,
            SUM(CASE WHEN type = 'sell' THEN value ELSE 0 END) as total_earned,
            COUNT(*) as total_transactions
        FROM transactions
        WHERE YEAR(datetime) = ?
    ");
    $stmt->execute([$year]);
    $yearly = $stmt->fetch();
    
    // Profit/Loss (uproszczony - do rozliczenia FIFO potrzeba bardziej zaawansowany algorytm)
    $profitLoss = $yearly['total_earned'] - $yearly['total_spent'];
    
    // Historia importów
    $stmt = $db->prepare("
        SELECT 
            filename,
            file_type,
            records_count,
            date_from,
            date_to,
            imported_at
        FROM import_history
        ORDER BY imported_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $importHistory = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'year' => $year,
        'summary' => [
            'totalSpent' => round($yearly['total_spent'], 2),
            'totalEarned' => round($yearly['total_earned'], 2),
            'profitLoss' => round($profitLoss, 2),
            'totalTransactions' => (int)$yearly['total_transactions']
        ],
        'perCrypto' => $perCrypto,
        'fees' => $fees,
        'monthly' => $monthly,
        'importHistory' => $importHistory
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching statistics: ' . $e->getMessage()
    ]);
}
