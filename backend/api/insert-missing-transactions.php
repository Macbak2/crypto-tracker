<?php
/**
 * Jednorazowy skrypt: wstawia brakujące transakcje z Excela z verification_status='needs_review'
 * GET /api/insert-missing-transactions.php
 * GET /api/insert-missing-transactions.php?dry_run=1  — podgląd bez zapisu
 */
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

$dryRun = isset($_GET['dry_run']);
$db = (new Database())->getConnection();

// Transakcje do wstawienia — rate=0 i/lub amount=0 oznacza brak danych (needs_review)
$missing = [
    // BTC marzec 2017 — brak w bazie (import zaczynał się od 2017-06-25)
    [
        'market'      => 'BTC-PLN',
        'datetime'    => '2017-03-02 17:51:00',
        'type'        => 'buy',
        'order_type'  => 'limit',
        'rate'        => 5176.26,
        'amount'      => 0.04151428,
        'value'       => 218.07,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Rate i koszt z Excela.',
    ],
    [
        'market'      => 'BTC-PLN',
        'datetime'    => '2017-03-02 18:24:00',
        'type'        => 'sell',
        'order_type'  => 'limit',
        'rate'        => 0,
        'amount'      => 0.03998000,
        'value'       => 0,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Kurs i przychód nieznane.',
    ],
    [
        'market'      => 'BTC-PLN',
        'datetime'    => '2017-03-18 03:33:00',
        'type'        => 'buy',
        'order_type'  => 'limit',
        'rate'        => 4297.50,
        'amount'      => 0.05184654,
        'value'       => 226.11,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Rate i koszt z Excela.',
    ],
    [
        'market'      => 'BTC-PLN',
        'datetime'    => '2017-03-18 03:53:00',
        'type'        => 'sell',
        'order_type'  => 'limit',
        'rate'        => 0,
        'amount'      => 0.05241000,
        'value'       => 0,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Kurs i przychód nieznane.',
    ],
    // BTC lipiec 2017 — sprzedaż bez kursu/przychodu w Excelu
    [
        'market'      => 'BTC-PLN',
        'datetime'    => '2017-07-29 05:54:00',
        'type'        => 'sell',
        'order_type'  => 'limit',
        'rate'        => 0,
        'amount'      => 0.00033940,
        'value'       => 0,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Kurs i przychód nieznane.',
    ],
    // BCC sierpień 2017 — zakup/transfer z kursem=0 (prawdopodobnie reward/fork)
    [
        'market'      => 'BCC-PLN',
        'datetime'    => '2017-08-01 14:22:21',
        'type'        => 'buy',
        'order_type'  => 'limit',
        'rate'        => 0,
        'amount'      => 0.00063142,
        'value'       => 0,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Prawdopodobnie fork/reward BCC. Koszt = 0.',
    ],
    // ETH grudzień 2017 — sprzedaże powiązane z zakupem IOTA (crypto→crypto)
    [
        'market'      => 'ETH-PLN',
        'datetime'    => '2017-12-04 22:22:25',
        'type'        => 'sell',
        'order_type'  => 'limit',
        'rate'        => 0,
        'amount'      => 0.00126000,
        'value'       => 0,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Prawdopodobnie sprzedaż ETH pod zakup IOTA (crypto→crypto). Kurs nieznany.',
    ],
    [
        'market'      => 'ETH-PLN',
        'datetime'    => '2017-12-05 00:38:27',
        'type'        => 'sell',
        'order_type'  => 'limit',
        'rate'        => 0,
        'amount'      => 0.00126000,
        'value'       => 0,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Prawdopodobnie sprzedaż ETH pod zakup IOTA (crypto→crypto). Kurs nieznany.',
    ],
    // IOTA grudzień 2017 — zakupy bez ilości i kursu (rynek wycofany)
    [
        'market'      => 'IOTA-PLN',
        'datetime'    => '2017-12-04 22:22:25',
        'type'        => 'buy',
        'order_type'  => 'limit',
        'rate'        => 0,
        'amount'      => 0,
        'value'       => 0,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Zakup IOTA za ETH. Ilość i kurs nieznane (rynek wycofany).',
    ],
    [
        'market'      => 'IOTA-PLN',
        'datetime'    => '2017-12-05 00:38:27',
        'type'        => 'buy',
        'order_type'  => 'limit',
        'rate'        => 0,
        'amount'      => 0,
        'value'       => 0,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Zakup IOTA za ETH. Ilość i kurs nieznane (rynek wycofany).',
    ],
    // GCC kwiecień 2017 — zakupy bez ilości i kursu (rynek wycofany)
    [
        'market'      => 'GCC-PLN',
        'datetime'    => '2017-04-06 12:00:00',
        'type'        => 'buy',
        'order_type'  => 'limit',
        'rate'        => 0,
        'amount'      => 0,
        'value'       => 166.50,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Koszt 166.50 PLN. Ilość i kurs nieznane (rynek wycofany GCC).',
    ],
    [
        'market'      => 'GCC-PLN',
        'datetime'    => '2017-04-17 12:00:00',
        'type'        => 'buy',
        'order_type'  => 'limit',
        'rate'        => 0,
        'amount'      => 0,
        'value'       => 333.00,
        'notes'       => 'Brak w imporcie — dane z Excela 2017. Koszt 333.00 PLN. Ilość i kurs nieznane (rynek wycofany GCC).',
    ],
];

$stmt = $db->prepare("
    INSERT INTO transactions (id, market, datetime, type, order_type, rate, amount, value, notes, verification_status)
    VALUES (UUID(), :market, :datetime, :type, :order_type, :rate, :amount, :value, :notes, 'needs_review')
");

$inserted = [];
$skipped  = [];
$errors   = [];

foreach ($missing as $tx) {
    // Sprawdź czy już istnieje (na podstawie datetime + market + type + amount)
    $check = $db->prepare("
        SELECT id FROM transactions
        WHERE market = ? AND datetime = ? AND type = ? AND ABS(amount - ?) < 0.000001
        LIMIT 1
    ");
    $check->execute([$tx['market'], $tx['datetime'], $tx['type'], $tx['amount']]);
    $existing = $check->fetch();

    if ($existing) {
        $skipped[] = [
            'market'   => $tx['market'],
            'datetime' => $tx['datetime'],
            'type'     => $tx['type'],
            'reason'   => 'już istnieje (id=' . $existing['id'] . ')',
        ];
        continue;
    }

    if ($dryRun) {
        $inserted[] = ['dry_run' => true] + $tx;
        continue;
    }

    try {
        $stmt->execute([
            ':market'     => $tx['market'],
            ':datetime'   => $tx['datetime'],
            ':type'       => $tx['type'],
            ':order_type' => $tx['order_type'],
            ':rate'       => $tx['rate'],
            ':amount'     => $tx['amount'],
            ':value'      => $tx['value'],
            ':notes'      => $tx['notes'],
        ]);
        $inserted[] = $tx;
    } catch (Exception $e) {
        $errors[] = $tx['market'] . ' ' . $tx['datetime'] . ': ' . $e->getMessage();
    }
}

echo json_encode([
    'success'   => true,
    'dry_run'   => $dryRun,
    'inserted'  => count($inserted),
    'skipped'   => count($skipped),
    'errors'    => count($errors),
    'details'   => [
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'errors'   => $errors,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
