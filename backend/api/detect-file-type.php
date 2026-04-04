<?php
// ================================================
// API: Wykrywanie typu pliku CSV
// Endpoint: POST /api/detect-file-type.php
// ================================================

require_once '../config/database.php';
require_once '../utils/CSVParser.php';

enableCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Sprawdź czy plik został przesłany
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit();
}

$file = $_FILES['file'];

// Walidacja pliku
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File upload error']);
    exit();
}

// Sprawdź rozszerzenie
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($fileExtension !== 'csv') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Only CSV files are allowed']);
    exit();
}

try {
    // Parsuj plik CSV
    $result = CSVParser::parseCSV($file['tmp_name']);
    
    $response = [
        'success' => true,
        'filename' => $file['name'],
        'type' => $result['type'],
        'recordCount' => $result['count'],
        'headers' => $result['headers'],
        'dateRange' => CSVParser::getDateRange($result['data'])
    ];
    
    // Dodaj podgląd pierwszych 3 wierszy
    $response['preview'] = array_slice($result['data'], 0, 3);
    
    // Walidacja w zależności od typu
    if ($result['type'] === 'transactions') {
        $validation = CSVParser::validateTransactions($result['headers']);
        $response['valid'] = $validation['valid'];
        if (!$validation['valid']) {
            $response['error'] = 'Missing column: ' . $validation['missing'];
        }
    } elseif ($result['type'] === 'detailed_report') {
        $validation = CSVParser::validateDetailedReport($result['headers']);
        $response['valid'] = $validation['valid'];
        if (!$validation['valid']) {
            $response['error'] = 'Missing column: ' . $validation['missing'];
        }
    } else {
        $response['valid'] = true; // Operations/Transfers są opcjonalne
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error parsing file: ' . $e->getMessage()
    ]);
}
