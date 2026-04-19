<?php
/**
 * Porównanie danych Excel (CSV) z bazą danych
 * GET /api/compare-excel.php?crypto=LTC&year=2017
 */
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

$crypto = strtoupper($_GET['crypto'] ?? '');
$year   = (int)($_GET['year'] ?? 2017);

if (!$crypto) {
    echo json_encode(['success' => false, 'message' => 'Brak parametru crypto']);
    exit;
}

$csvPath = __DIR__ . '/../../_workspace/verification/' . $crypto . '.csv';
if (!file_exists($csvPath)) {
    echo json_encode(['success' => false, 'message' => "Brak pliku: $crypto.csv"]);
    exit;
}

// ── 1. Parsuj CSV ──────────────────────────────────────────────────────────
function parsePolishNumber($s) {
    $s = trim($s, '"');
    $s = str_replace([' ', "\xc2\xa0", 'zł', ' '], '', $s);
    $s = str_replace('.', '', $s);   // separator tysięcy
    $s = str_replace(',', '.', $s);  // separator dziesiętny
    return floatval(preg_replace('/[^0-9.\-]/', '', $s));
}

function parsePolishDate($s) {
    $s = trim($s, '"');
    // Format: "4.09.2017, 19:55:53" lub "2017-09-04 19:55:53"
    if (preg_match('/(\d{1,2})\.(\d{2})\.(\d{4}),\s*(\d{2}:\d{2}:\d{2})/', $s, $m)) {
        return sprintf('%04d-%02d-%02d %s', $m[3], $m[2], $m[1], $m[4]);
    }
    return $s;
}

$buysExcel  = [];
$sellsExcel = [];
$inYear     = false;
$mode       = null; // 'buy' or 'sell'

if (($fh = fopen($csvPath, 'r')) !== false) {
    while (($row = fgetcsv($fh, 0, ',')) !== false) {
        // BOM
        $row[0] = str_replace("\xEF\xBB\xBF", '', $row[0]);

        $col0 = trim($row[0] ?? '', '"');

        // Rok
        if (preg_match('/Rozliczenie za (\d{4}) rok/', $col0, $m)) {
            $inYear = ((int)$m[1] === $year);
            $mode   = null;
            continue;
        }
        if (!$inYear) continue;

        // Nagłówki sekcji
        if (stripos($col0, 'Zakup') !== false || stripos($col0, 'Wpłata') !== false) {
            $mode = null; continue;
        }
        if (str_contains($col0 ?? '', 'Data')) {
            // rozróżniamy nagłówki kolumn — sprawdzamy czy wiersz ma "Kurs" na pozycji 4 (buy) czy 9 (sell)
            $mode = 'header'; continue;
        }

        // Dane transakcji — rozpoznaj po dacie w kolumnie 0 (buy) lub 7 (sell)
        $dateCol0 = trim($row[0] ?? '', '"');
        $dateCol7 = trim($row[7] ?? '', '"');

        $isBuyRow  = preg_match('/\d{1,2}\.\d{2}\.\d{4}/', $dateCol0);
        $isSellRow = preg_match('/\d{1,2}\.\d{2}\.\d{4}/', $dateCol7);

        if ($isBuyRow) {
            $buysExcel[] = [
                'datetime'  => parsePolishDate($row[0] ?? ''),
                'amount'    => parsePolishNumber($row[1] ?? '0'),   // Ilość
                'fee'       => parsePolishNumber($row[2] ?? '0'),   // Prowizja
                'net'       => parsePolishNumber($row[3] ?? '0'),   // Suma (po prowizji)
                'rate'      => parsePolishNumber($row[4] ?? '0'),   // Kurs
                'total_cost'=> parsePolishNumber($row[5] ?? '0'),   // Łączny koszt
            ];
        }
        if ($isSellRow) {
            $sellsExcel[] = [
                'datetime'  => parsePolishDate($row[7]  ?? ''),
                'amount'    => parsePolishNumber($row[8]  ?? '0'),  // Suma sprzedanej ilości
                'rate'      => parsePolishNumber($row[9]  ?? '0'),  // Kurs
                'revenue'   => parsePolishNumber($row[10] ?? '0'),  // Przychód (przed prowizją)
                'fee'       => parsePolishNumber($row[11] ?? '0'),  // Prowizja
                'net'       => parsePolishNumber($row[12] ?? '0'),  // Łączny przychód
            ];
        }
    }
    fclose($fh);
}

// ── 2. Pobierz transakcje z bazy ──────────────────────────────────────────
$db = (new Database())->getConnection();
$stmt = $db->prepare("
    SELECT t.id, t.datetime, t.type, t.amount, t.rate, t.value,
           COALESCE(SUM(CASE WHEN o.currency='PLN' THEN ABS(o.amount) ELSE 0 END), 0) as fee_pln,
           COALESCE(SUM(CASE WHEN o.currency!='PLN' THEN ABS(o.amount) ELSE 0 END), 0) as fee_crypto
    FROM transactions t
    LEFT JOIN operations o ON o.transaction_id = t.id AND o.operation_type LIKE '%prowizji%'
    WHERE t.market = :market AND YEAR(t.datetime) = :year
    GROUP BY t.id
    ORDER BY t.datetime ASC
");
$stmt->execute([':market' => $crypto . '-PLN', ':year' => $year]);
$dbTxs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dbBuys  = array_values(array_filter($dbTxs, fn($t) => $t['type'] === 'buy'));
$dbSells = array_values(array_filter($dbTxs, fn($t) => $t['type'] === 'sell'));

// ── 3. Porównaj ────────────────────────────────────────────────────────────
const TOLERANCE_AMOUNT = 0.000001;
const TOLERANCE_VALUE  = 0.02;
const TIME_WINDOW_SEC  = 120;

function timeDiff($a, $b) {
    return abs(strtotime($a) - strtotime($b));
}

function compareRows($excel, $db, $type) {
    $results = [];
    $usedDb  = [];

    foreach ($excel as $ei => $ex) {
        $bestMatch = null;
        $bestScore = PHP_INT_MAX;

        foreach ($db as $di => $db_row) {
            if (in_array($di, $usedDb)) continue;
            $tdiff = timeDiff($ex['datetime'], $db_row['datetime']);
            $adiff = abs($ex['amount'] - floatval($db_row['amount']));
            if ($tdiff <= TIME_WINDOW_SEC && $adiff <= TOLERANCE_AMOUNT) {
                if ($tdiff < $bestScore) { $bestScore = $tdiff; $bestMatch = $di; }
            }
        }

        if ($bestMatch === null) {
            $results[] = [
                'status'       => 'no_match',
                'excel'        => $ex,
                'db'           => null,
                'issues'       => ['Brak pasującej transakcji w bazie'],
            ];
            continue;
        }

        $usedDb[] = $bestMatch;
        $dbr = $db[$bestMatch];
        $issues = [];

        // Czas
        $td = timeDiff($ex['datetime'], $dbr['datetime']);
        if ($td > 5)  $issues[] = "Czas: Excel={$ex['datetime']} DB={$dbr['datetime']} (różnica {$td}s)";

        // Ilość
        $ad = abs($ex['amount'] - floatval($dbr['amount']));
        if ($ad > TOLERANCE_AMOUNT) $issues[] = sprintf("Ilość: Excel=%.8f DB=%.8f", $ex['amount'], $dbr['amount']);

        // Kurs
        $rd = abs($ex['rate'] - floatval($dbr['rate']));
        if ($rd > 0.01) $issues[] = sprintf("Kurs: Excel=%.2f DB=%.2f", $ex['rate'], $dbr['rate']);

        // Wartość
        if ($type === 'buy') {
            $vd = abs($ex['total_cost'] - floatval($dbr['value']));
            if ($vd > TOLERANCE_VALUE) $issues[] = sprintf("Koszt: Excel=%.2f DB=%.2f", $ex['total_cost'], $dbr['value']);
        } else {
            $vd = abs($ex['revenue'] - floatval($dbr['value']));
            if ($vd > TOLERANCE_VALUE) $issues[] = sprintf("Przychód: Excel=%.2f DB=%.2f", $ex['revenue'], $dbr['value']);
        }

        $status = empty($issues) ? 'ok' : (count($issues) === 1 && str_starts_with($issues[0], 'Czas') ? 'ok' : 'mismatch');

        $results[] = [
            'status' => $status,
            'tx_id'  => $dbr['id'],
            'excel'  => $ex,
            'db'     => $dbr,
            'issues' => $issues,
        ];
    }

    // DB transakcje bez odpowiednika w Excel
    foreach ($db as $di => $dbr) {
        if (!in_array($di, $usedDb)) {
            $results[] = [
                'status' => 'only_in_db',
                'tx_id'  => $dbr['id'],
                'excel'  => null,
                'db'     => $dbr,
                'issues' => ['Transakcja w bazie, brak w Excelu'],
            ];
        }
    }

    return $results;
}

$buyResults  = compareRows($buysExcel,  $dbBuys,  'buy');
$sellResults = compareRows($sellsExcel, $dbSells, 'sell');

// ── 4. Statystyki ─────────────────────────────────────────────────────────
function stats($results) {
    $ok=0; $mismatch=0; $no_match=0; $only_db=0;
    foreach ($results as $r) {
        match($r['status']) {
            'ok'       => $ok++,
            'mismatch' => $mismatch++,
            'no_match' => $no_match++,
            'only_in_db' => $only_db++,
            default    => null,
        };
    }
    return compact('ok','mismatch','no_match','only_db');
}

echo json_encode([
    'success' => true,
    'crypto'  => $crypto,
    'year'    => $year,
    'buys'    => ['stats' => stats($buyResults),  'rows' => $buyResults],
    'sells'   => ['stats' => stats($sellResults), 'rows' => $sellResults],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
