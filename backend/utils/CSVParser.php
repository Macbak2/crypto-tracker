<?php

/**
 * Parser plików CSV z giełdy Zonda/BitBay
 * Obsługuje polskie formatowanie liczb (przecinek jako separator dziesiętny)
 */

class CSVParser
{

 /**
  * Wykrywa typ pliku CSV na podstawie nagłówków
  * 
  * @param array $headers Nagłówki kolumn
  * @return string Typ pliku: transactions|detailed_report|operations|transfers
  */
 public static function detectFileType($headers)
 {
  // Usuń BOM jeśli istnieje
  $headers = array_map(function ($h) {
   return str_replace("\xEF\xBB\xBF", '', $h);
  }, $headers);

  if (in_array('ID', $headers)) {
   return 'transactions';
  }

  if (in_array('Saldo dostępne po operacji', $headers)) {
   return 'detailed_report';
  }

  // Dla rozróżnienia operations vs transfers potrzebujemy więcej danych
  // Na razie zwróć 'operations' - później sprawdzimy ilość rekordów
  return 'operations';
 }

 /**
  * Parsuje plik CSV i zwraca dane jako tablicę
  * 
  * @param string $filePath Ścieżka do pliku CSV
  * @return array Dane z pliku
  */
 public static function parseCSV($filePath)
 {
  $data = [];
  $headers = [];

  if (($handle = fopen($filePath, "r")) !== FALSE) {
   $rowIndex = 0;

   while (($row = fgetcsv($handle, 0, ";")) !== FALSE) {
    if ($rowIndex === 0) {
     // Pierwsza linia to nagłówki - usuń BOM i oczyść
     $headers = array_map(function ($h) {
      // Usuń BOM
      $h = str_replace("\xEF\xBB\xBF", '', $h);
      // Usuń cudzysłowy
      $h = trim($h, '"');
      // Usuń białe znaki
      $h = trim($h);
      return $h;
     }, $row);
    } else {
     // Pozostałe linie to dane
     $rowData = [];
     foreach ($row as $index => $value) {
      if (isset($headers[$index])) {
       $columnName = $headers[$index];
       $rowData[$columnName] = trim($value, '"');
      }
     }
     $data[] = $rowData;
    }
    $rowIndex++;
   }

   fclose($handle);
  }

  return [
   'headers' => $headers,
   'data' => $data,
   'type' => self::detectFileType($headers),
   'count' => count($data)
  ];
 }

 /**
  * Konwertuje polską liczbę do formatu używanego w bazach danych
  * Przykład: "10500,50" -> 10500.50
  * 
  * @param string $value Wartość do konwersji
  * @return float Wartość liczbowa
  */
 public static function parseNumber($value)
 {
  // Usuń spacje
  $value = str_replace(' ', '', $value);
  // Zamień przecinek na kropkę
  $value = str_replace(',', '.', $value);
  // Usuń wszystko oprócz cyfr, kropek i minusów
  $value = preg_replace('/[^0-9.\-]/', '', $value);

  return floatval($value);
 }

 /**
  * Konwertuje polską datę do formatu MySQL DATETIME
  * Przykład: "2017-12-24 02:49:34" -> "2017-12-24 02:49:34"
  * 
  * @param string $dateString Data w formacie CSV
  * @return string Data w formacie MySQL
  */
 public static function parseDateTime($dateString)
 {
  // Jeśli puste, zwróć null
  if (empty($dateString) || $dateString === '') {
   return null;
  }

  // Format już jest poprawny, tylko upewnij się że jest valid
  $date = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);

  if ($date === false) {
   return null;
  }

  return $date->format('Y-m-d H:i:s');
 }

 /**
  * Mapuje polskie nazwy na wartości do bazy
  */
 public static function mapTransactionType($polish)
 {
  $map = [
   'Kupno' => 'buy',
   'Sprzedaż' => 'sell'
  ];
  return $map[$polish] ?? null;
 }

 /**
  * Mapuje typ zlecenia
  */
 public static function mapOrderType($polish)
 {
  $map = [
   'Maker' => 'maker',
   'Taker' => 'taker'
  ];
  return $map[$polish] ?? null;
 }
}
