<?php
/**
 * API Endpoint: Import danych z CSV do bazy
 * POST /api/import.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';
require_once '../utils/CSVParser.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Brak pliku w żądaniu'
        ]);
        exit;
    }
    
    $file = $_FILES['file'];
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Parsuj CSV
        $parsed = CSVParser::parseCSV($file['tmp_name'], $file['name']);
        
        $imported = 0;
        $errors = [];
        
        // Import w zależności od typu pliku
        switch ($parsed['type']) {
            case 'transactions':
                $result = importTransactions($db, $parsed['data']);
                $imported = $result['imported'];
                $errors = $result['errors'];
                break;
                
            case 'detailed_report':
            case 'operations':
            case 'transfers':
                $result = importOperations($db, $parsed['data']);
                $imported = $result['imported'];
                $errors = $result['errors'];
                break;
                
            default:
                throw new Exception('Nieobsługiwany typ pliku');
        }
        
        // Zapisz historię importu
        $dateRange = extractDateRange($parsed['data']);
        saveImportHistory($db, [
            'file_name' => $file['name'],
            'file_type' => $parsed['type'],
            'records_count' => $imported,
            'date_from' => $dateRange['from'],
            'date_to' => $dateRange['to'],
            'status' => count($errors) > 0 ? 'partial' : 'success',
            'notes' => count($errors) > 0 ? implode('; ', $errors) : null
        ]);
        
        echo json_encode([
            'success' => true,
            'imported' => $imported,
            'total' => $parsed['count'],
            'errors' => $errors,
            'file_type' => $parsed['type']
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Błąd podczas importu: ' . $e->getMessage()
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metoda nie dozwolona'
    ]);
}

/**
 * Importuje transakcje do bazy
 */
function importTransactions($db, $data) {
    $imported = 0;
    $errors = [];
    
    $stmt = $db->prepare("
        INSERT INTO transactions (id, market, datetime, type, order_type, rate, amount, value)
        VALUES (:id, :market, :datetime, :type, :order_type, :rate, :amount, :value)
        ON DUPLICATE KEY UPDATE 
            market = VALUES(market),
            datetime = VALUES(datetime),
            type = VALUES(type),
            order_type = VALUES(order_type),
            rate = VALUES(rate),
            amount = VALUES(amount),
            value = VALUES(value)
    ");
    
    foreach ($data as $index => $row) {
        try {
            $stmt->execute([
                ':id' => $row['ID'],
                ':market' => $row['Rynek'],
                ':datetime' => CSVParser::parseDateTime($row['Data operacji']),
                ':type' => CSVParser::mapTransactionType($row['Rodzaj']),
                ':order_type' => CSVParser::mapOrderType($row['Typ']),
                ':rate' => CSVParser::parseNumber($row['Kurs']),
                ':amount' => CSVParser::parseNumber($row['Ilość']),
                ':value' => CSVParser::parseNumber($row['Wartość'])
            ]);
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Linia " . ($index + 2) . ": " . $e->getMessage();
        }
    }
    
    return ['imported' => $imported, 'errors' => $errors];
}

/**
 * Importuje operacje do bazy
 */
function importOperations($db, $data) {
    $imported = 0;
    $errors = [];
    
    // Sprawdź czy są kolumny z saldami dostępnymi
    $hasDetailedBalances = isset($data[0]['Saldo dostępne po operacji']);
    
    if ($hasDetailedBalances) {
        $stmt = $db->prepare("
            INSERT INTO operations (datetime, operation_type, amount, currency, balance_available, balance_locked, balance_total)
            VALUES (:datetime, :operation_type, :amount, :currency, :balance_available, :balance_locked, :balance_total)
        ");
    } else {
        $stmt = $db->prepare("
            INSERT INTO operations (datetime, operation_type, amount, currency, balance_total)
            VALUES (:datetime, :operation_type, :amount, :currency, :balance_total)
        ");
    }
    
    foreach ($data as $index => $row) {
        try {
            $params = [
                ':datetime' => CSVParser::parseDateTime($row['Data operacji']),
                ':operation_type' => $row['Rodzaj'],
                ':amount' => CSVParser::parseNumber($row['Wartość']),
                ':currency' => $row['Waluta'],
                ':balance_total' => CSVParser::parseNumber($row['Saldo całkowite po operacji'])
            ];
            
            if ($hasDetailedBalances) {
                $params[':balance_available'] = CSVParser::parseNumber($row['Saldo dostępne po operacji']);
                $params[':balance_locked'] = CSVParser::parseNumber($row['Saldo zablokowane po operacji']);
            }
            
            $stmt->execute($params);
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Linia " . ($index + 2) . ": " . $e->getMessage();
        }
    }
    
    return ['imported' => $imported, 'errors' => $errors];
}

/**
 * Zapisuje historię importu
 */
function saveImportHistory($db, $data) {
    $stmt = $db->prepare("
        INSERT INTO import_history (file_name, file_type, records_count, date_from, date_to, status, notes)
        VALUES (:file_name, :file_type, :records_count, :date_from, :date_to, :status, :notes)
    ");
    
    $stmt->execute($data);
}

/**
 * Wyciąga zakres dat
 */
function extractDateRange($data) {
    if (empty($data)) {
        return ['from' => null, 'to' => null];
    }
    
    $dates = [];
    foreach ($data as $row) {
        if (isset($row['Data operacji']) && !empty($row['Data operacji'])) {
            $dates[] = substr($row['Data operacji'], 0, 10);
        }
    }
    
    if (empty($dates)) {
        return ['from' => null, 'to' => null];
    }
    
    sort($dates);
    return ['from' => $dates[0], 'to' => end($dates)];
}
