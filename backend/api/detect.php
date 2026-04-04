<?php
/**
 * API Endpoint: Wykrywanie typu pliku CSV
 * POST /api/detect.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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
    
    // Sprawdź czy to CSV
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileExtension !== 'csv') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Plik musi być w formacie CSV'
        ]);
        exit;
    }
    
    try {
        // Parsuj CSV
        $parsed = CSVParser::parseCSV($file['tmp_name']);
        
        // Wykryj zakres dat (jeśli są kolumny z datami)
        $dateRange = extractDateRange($parsed['data'], $parsed['headers']);
        
        echo json_encode([
            'success' => true,
            'file_info' => [
                'name' => $file['name'],
                'type' => $parsed['type'],
                'type_label' => getTypeLabel($parsed['type']),
                'records_count' => $parsed['count'],
                'date_from' => $dateRange['from'],
                'date_to' => $dateRange['to'],
                'headers' => $parsed['headers']
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Błąd podczas analizy pliku: ' . $e->getMessage()
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
 * Wyciąga zakres dat z danych
 */
function extractDateRange($data, $headers) {
    $dateColumn = null;
    
    // Znajdź kolumnę z datą
    foreach ($headers as $header) {
        if (strpos($header, 'Data') !== false || strpos($header, 'data') !== false) {
            $dateColumn = $header;
            break;
        }
    }
    
    if (!$dateColumn || empty($data)) {
        return ['from' => null, 'to' => null];
    }
    
    $dates = [];
    foreach ($data as $row) {
        if (isset($row[$dateColumn]) && !empty($row[$dateColumn])) {
            $dates[] = substr($row[$dateColumn], 0, 10); // Tylko data bez czasu
        }
    }
    
    if (empty($dates)) {
        return ['from' => null, 'to' => null];
    }
    
    sort($dates);
    
    return [
        'from' => $dates[0],
        'to' => end($dates)
    ];
}

/**
 * Zwraca czytelną nazwę typu pliku
 */
function getTypeLabel($type) {
    $labels = [
        'transactions' => 'Transakcje',
        'detailed_report' => 'Szczegółowy raport',
        'operations' => 'Operacje',
        'transfers' => 'Transfery'
    ];
    
    return $labels[$type] ?? 'Nieznany';
}
