<?php
/**
 * XlsxParser — чист PHP парсър за .xlsx файлове
 * Използва ZipArchive (вградено в PHP 5.2+)
 * Без никакви зависимости.
 */
class XlsxParser {

    /**
     * Парсва .xlsx файл.
     * Връща ['products' => [...], 'columns' => [...], 'errors' => [...]]
     */
    public static function parse(string $filePath): array {
        if (!file_exists($filePath)) {
            return ['products' => [], 'columns' => [], 'errors' => ['Файлът не съществува']];
        }

        $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return static::parseCsv($filePath);
        }

        if (!class_exists('ZipArchive')) {
            return ['products' => [], 'columns' => [], 'errors' => ['ZipArchive extension не е наличен']];
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return ['products' => [], 'columns' => [], 'errors' => ['Невалиден .xlsx файл']];
        }

        // 1. Shared strings
        $strings = [];
        $ssIdx = $zip->locateName('xl/sharedStrings.xml');
        if ($ssIdx !== false) {
            $xml = @simplexml_load_string($zip->getFromIndex($ssIdx));
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $strings[] = (string)$si->t;
                    } else {
                        $t = '';
                        foreach ($si->r as $r) $t .= (string)($r->t ?? '');
                        $strings[] = $t;
                    }
                }
            }
        }

        // 2. Sheet1
        $sheetIdx = $zip->locateName('xl/worksheets/sheet1.xml');
        if ($sheetIdx === false) {
            $zip->close();
            return ['products' => [], 'columns' => [], 'errors' => ['Няма sheet1 в файла']];
        }

        $sheetXml = @simplexml_load_string($zip->getFromIndex($sheetIdx));
        $zip->close();

        if (!$sheetXml) {
            return ['products' => [], 'columns' => [], 'errors' => ['Грешка при четене на sheet1']];
        }

        // 3. Parse rows
        $rows   = [];
        $errors = [];

        foreach ($sheetXml->sheetData->row as $row) {
            $rowArr = [];
            $maxCol = 0;

            foreach ($row->c as $cell) {
                $ref    = (string)($cell['r'] ?? 'A1');
                $col    = preg_replace('/[0-9]/', '', $ref);
                $colIdx = static::colToIndex($col);
                $maxCol = max($maxCol, $colIdx);

                $type  = (string)($cell['t'] ?? '');
                $value = (string)($cell->v ?? '');

                if ($type === 's') {
                    $value = $strings[(int)$value] ?? '';
                } elseif ($type === 'b') {
                    $value = $value === '1' ? 'TRUE' : 'FALSE';
                } elseif ($type === 'str' || $type === 'inlineStr') {
                    $value = isset($cell->is->t) ? (string)$cell->is->t : $value;
                }
                // Numbers/dates stay as-is

                $rowArr[$colIdx] = $value;
            }

            // Fill gaps
            for ($i = 0; $i <= $maxCol; $i++) {
                if (!array_key_exists($i, $rowArr)) $rowArr[$i] = '';
            }
            ksort($rowArr);
            $rows[] = array_values($rowArr);
        }

        if (empty($rows)) {
            return ['products' => [], 'columns' => [], 'errors' => ['Файлът е празен']];
        }

        // 4. Headers = first row
        $headers  = array_map('trim', $rows[0]);
        $products = [];

        foreach (array_slice($rows, 1) as $rowIdx => $row) {
            $p = [];
            foreach ($headers as $i => $h) {
                if ($h === '') continue;
                $p[$h] = isset($row[$i]) ? trim((string)$row[$i]) : '';
            }

            // Skip completely empty rows
            $ean = trim((string)($p['EAN Amazon'] ?? ''));
            // Excel reads EAN as float: 4015400259275.0 → strip .0
            if (is_numeric($ean) && str_contains($ean, '.')) {
                $ean = rtrim(rtrim($ean, '0'), '.');
                $p['EAN Amazon'] = $ean;
            }
            // Scientific notation fix: 4.01540025E+12 → 4015400250000
            if (preg_match('/^[\d.]+[eE][+\-]?\d+$/', $ean)) {
                $ean = rtrim(rtrim(number_format((float)$ean, 0, '.', ''), '0'), '.');
                $p['EAN Amazon'] = $ean;
            }
            if ($ean === '') continue;

            // Ensure internal fields
            if (!isset($p['_upload_status'])) $p['_upload_status'] = 'NOT_UPLOADED';

            $products[] = $p;
        }

        return [
            'products' => $products,
            'columns'  => $headers,
            'errors'   => $errors,
            'count'    => count($products),
        ];
    }


    private static function parseCsv(string $filePath): array {
        $fh = @fopen($filePath, 'r');
        if (!$fh) return ['products' => [], 'columns' => [], 'errors' => ['Грешка при четене на CSV файла']];
        $rows = [];
        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            if (count($row) === 1) {
                $try = str_getcsv($row[0], ',');
                if (count($try) > 1) $row = $try;
            }
            if (!$rows && isset($row[0])) $row[0] = preg_replace('/^ï»¿/u', '', (string)$row[0]);
            $rows[] = array_map(fn($v) => trim((string)$v), $row);
        }
        fclose($fh);
        if (!$rows) return ['products' => [], 'columns' => [], 'errors' => ['CSV файлът е празен']];
        $headers = array_map('trim', $rows[0]);
        $products = [];
        foreach (array_slice($rows, 1) as $row) {
            $p = [];
            foreach ($headers as $i => $h) {
                if ($h === '') continue;
                $p[$h] = isset($row[$i]) ? trim((string)$row[$i]) : '';
            }
            $ean = trim((string)($p['EAN Amazon'] ?? ''));
            if ($ean === '') continue;
            if (is_numeric($ean) && str_contains($ean, '.')) {
                $ean = rtrim(rtrim($ean, '0'), '.');
                $p['EAN Amazon'] = $ean;
            }
            if (!isset($p['_upload_status'])) $p['_upload_status'] = 'NOT_UPLOADED';
            $products[] = $p;
        }
        return ['products' => $products, 'columns' => $headers, 'errors' => [], 'count' => count($products)];
    }

    private static function colToIndex(string $col): int {
        $idx = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $idx = $idx * 26 + (ord(strtoupper($col[$i])) - ord('A') + 1);
        }
        return $idx - 1;
    }
}
