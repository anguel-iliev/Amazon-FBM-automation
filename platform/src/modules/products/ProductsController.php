<?php
class ProductsController {

    private static array $editableFields = [
        'Продажна Цена в Амазон  - Brutto',
        'Цена Доставчик -Netto',
        'Транспорт до кр. лиент  Netto',
        'Намерена 2ра обява', 'DM цена',
        'Нова цена след намаление', 'За следваща поръчка',
        'Електоника', 'Корекция  на цена', 'Коментар',
    ];

    // ── Index — server-side paginated ────────────────────────
    public function index(): void {
        try {
            $stats     = ProductDB::stats();
            $suppliers = $this->loadSupplierNames();
            $brands    = ProductDB::distinct('Бранд');
        } catch (\Throwable $e) {
            Logger::error("Products::index: " . $e->getMessage());
            $stats = ['total'=>0,'withAsin'=>0,'notUploaded'=>0,'suppliers'=>0];
            $suppliers = []; $brands = [];
        }

        $filters = $this->parseFilters();
        $perPage = $this->parsePerPage();
        $page    = max(1, (int)($_GET['page'] ?? 1));

        View::renderWithLayout('products/index', [
            'pageTitle'  => 'Продукти',
            'activePage' => 'products',
            'stats'      => $stats,
            'suppliers'  => $suppliers,
            'brands'     => $brands,
            'filters'    => $filters,
            'perPage'    => $perPage,
            'page'       => $page,
        ]);
    }

    // ── AJAX: server-side paginated data ─────────────────────
    public function data(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $filters = $this->parseFilters();
            $perPage = $this->parsePerPage();
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $sortCol = $_GET['sort'] ?? '';
            $sortDir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            // SERVER-SIDE: filter+sort+paginate in PHP from local cache
            $result = ProductDB::query($filters, $sortCol, $sortDir, $page, $perPage);

            echo json_encode(array_merge($result, ['ok' => true]), JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            Logger::error("Products::data: " . $e->getMessage());
            echo json_encode([
                'ok'    => false,
                'error' => 'PHP грешка: ' . $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
                'diag'  => [
                    'cache_status'   => ProductDB::status(),
                    'firebase_ready' => Firebase::isReady(),
                    'curl'           => function_exists('curl_init'),
                ],
            ]);
        }
    }

    // ── Brands for supplier (AJAX) ────────────────────────────
    public function brandsForSupplier(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $supplier = trim($_GET['supplier'] ?? '');
            $brands   = ProductDB::distinct('Бранд', $supplier);
            echo json_encode(['ok' => true, 'brands' => $brands]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'brands' => [], 'error' => $e->getMessage()]);
        }
    }

    // ── Update single cell ────────────────────────────────────
    public function update(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $ean   = trim($_POST['ean']   ?? '');
            $field = trim($_POST['field'] ?? '');
            $value = trim($_POST['value'] ?? '');

            if (!$ean || !$field) { echo json_encode(['success'=>false,'error'=>'Missing params']); return; }
            if (!in_array($field, static::$editableFields)) { echo json_encode(['success'=>false,'error'=>'Not editable']); return; }

            // Write to Firebase
            $ok = Firebase::updateProduct($ean, $field, $value);
            if (!$ok) { echo json_encode(['success'=>false,'error'=>'Firebase write failed']); return; }

            // Update local cache
            ProductDB::updateField($ean, $field, $value);

            Logger::info("Update: EAN={$ean} {$field}={$value}");
            echo json_encode(['success' => true]);

        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    // ── Add product ───────────────────────────────────────────
    public function addPage(): void {
        View::renderWithLayout('products/add', ['pageTitle'=>'Добави продукт','activePage'=>'products']);
    }

    public function addAction(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $p = [
                'EAN Amazon'            => trim($_POST['ean']      ?? ''),
                'Доставчик'             => trim($_POST['supplier'] ?? ''),
                'Бранд'                 => trim($_POST['brand']    ?? ''),
                'Модел'                 => trim($_POST['model']    ?? ''),
                'ASIN'                  => trim($_POST['asin']     ?? ''),
                'Amazon Link'           => trim($_POST['link']     ?? ''),
                'Цена Доставчик -Netto' => trim($_POST['price']    ?? ''),
                'Коментар'              => trim($_POST['notes']    ?? ''),
            ];
            if (empty($p['EAN Amazon'])) { echo json_encode(['success'=>false,'error'=>'EAN е задължителен']); return; }

            $ok = Firebase::addProduct($p);
            if ($ok) {
                // Insert into SQLite
                ProductDB::insertOne($p);
                Logger::info("Add: EAN={$p['EAN Amazon']}");
            }
            echo json_encode(['success'=>$ok,'error'=>$ok?null:'Firebase грешка']);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    // ── Import page ───────────────────────────────────────────
    public function importPage(): void {
        try { $archives = Firebase::listArchives(); } catch (\Throwable $e) { $archives = []; }
        $cacheStatus = ProductDB::status();
        View::renderWithLayout('products/import', [
            'pageTitle'   => 'Import продукти',
            'activePage'  => 'products',
            'archives'    => $archives,
            'cacheStatus' => $cacheStatus,
        ]);
    }

    // ── Import action — writes to Firebase + rebuilds cache ──
    public function importAction(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            if (empty($_FILES['file']['tmp_name'])) { echo json_encode(['success'=>false,'error'=>'Не е избран файл']); return; }
            if (strtolower(pathinfo($_FILES['file']['name']??'',PATHINFO_EXTENSION)) !== 'xlsx') {
                echo json_encode(['success'=>false,'error'=>'Само .xlsx файлове']); return;
            }

            $tmpPath = DATA_DIR . '/upload_' . time() . '.xlsx';
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $tmpPath)) {
                echo json_encode(['success'=>false,'error'=>'Грешка при качване']); return;
            }

            $parsed = XlsxParser::parse($tmpPath);
            @unlink($tmpPath);

            if (empty($parsed['products'])) {
                echo json_encode(['success'=>false,'error'=>implode('; ',$parsed['errors']??['Файлът е празен'])]); return;
            }

            $mode  = $_POST['mode']  ?? 'first';
            $label = trim($_POST['label'] ?? '');

            if ($mode === 'first') {
                // First import — no archive, just write
                $result = Firebase::firstImport($parsed['products']);
                if (!$result['ok']) { echo json_encode(['success'=>false,'error'=>$result['error'],'written'=>$result['written']]); return; }
                // Write to SQLite
                ProductDB::replaceAll($parsed['products']);
                Firebase::appendLog(['type'=>'import_first','count'=>$result['written']]);
                echo json_encode(['success'=>true,'mode'=>'first','count'=>$result['written'],
                    'message'=>"Импортирани {$result['written']} продукта."]);

            } elseif ($mode === 'replace') {
                $archiveKey = Firebase::archiveCurrent($label ?: 'Преди импорт '.date('d.m.Y H:i'));
                $result     = Firebase::putProducts($parsed['products']);
                if (!$result['ok']) { echo json_encode(['success'=>false,'error'=>$result['error'],'written'=>$result['written']]); return; }
                // Write to SQLite
                ProductDB::replaceAll($parsed['products']);
                Firebase::appendLog(['type'=>'import_replace','count'=>$result['written']]);
                echo json_encode(['success'=>true,'mode'=>'replace','count'=>$result['written'],
                    'archive_key'=>$archiveKey,'message'=>"Заменени с {$result['written']} продукта."]);

            } else {
                // merge
                $result = Firebase::mergeProducts($parsed['products']);
                if (!empty($result['error'])) { echo json_encode(['success'=>false,'error'=>$result['error']]); return; }
                // Rebuild cache from Firebase to include merged data
                ProductDB::rebuildFromFirebase();
                Firebase::appendLog(['type'=>'import_merge','added'=>$result['added'],'skipped'=>$result['skipped']]);
                echo json_encode(['success'=>true,'mode'=>'merge','added'=>$result['added'],
                    'skipped'=>$result['skipped'],'total'=>$result['total'],
                    'message'=>"Добавени {$result['added']} нови. Пропуснати {$result['skipped']} съществуващи."]);
            }

        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>'PHP: '.$e->getMessage()]);
        }
    }

    // ── Restore archive ───────────────────────────────────────
    public function restoreArchive(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $key = trim($_POST['key'] ?? '');
            if (!$key) { echo json_encode(['success'=>false,'error'=>'Невалиден архив']); return; }
            Firebase::archiveCurrent('Преди възстановяване '.date('d.m.Y H:i'));
            $ok = Firebase::restoreArchive($key);
            if ($ok) {
                ProductDB::rebuildFromFirebase();
            }
            echo json_encode(['success'=>$ok,'message'=>$ok?'Архивът е зареден!':'Firebase грешка']);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    // ── Rebuild cache manually ────────────────────────────────
    public function rebuildCache(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $ok     = ProductDB::rebuildFromFirebase();
            $status = ProductDB::status();
            echo json_encode(['success'=>$ok,'status'=>$status,'message'=>$ok?"Кешът е обновен ({$status['count']} продукта)":'Firebase грешка']);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    // ── Export CSV ────────────────────────────────────────────
    public function export(): void {
        try {
            $filters  = $this->parseFilters();
            // For export we need ALL matching — no pagination
            $result   = ProductDB::query($filters, '', 'asc', 1, 999999);
            $products = $result['products'];

            $headers = ['EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар',
                'Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел','ASIN',
                'Цена Конкурент  - Brutto','Цена Amazon  - Brutto',
                'Продажна Цена в Амазон  - Brutto','Цена без ДДС',
                'ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto',
                'ДДС  от Цена Доставчик','Транспорт от Доставчик до нас',
                'Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент',
                'Резултат','Намерена 2ра обява','Цена за ES FR IT',
                'DM цена','Нова цена след намаление','Доставени',
                'За следваща поръчка','Електоника','Статус'];

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="products_'.date('Ymd_His').'.csv"');
            $out = fopen('php://output','w'); fputs($out,"\xEF\xBB\xBF");
            fputcsv($out,$headers,';');
            foreach ($products as $p) {
                $row=[];
                foreach (array_slice($headers,0,29) as $h) $row[]=$p[$h]??'';
                $row[]=$p['_upload_status']??'NOT_UPLOADED';
                fputcsv($out,$row,';');
            }
            fclose($out); exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/plain'); echo 'Грешка: '.$e->getMessage(); exit;
        }
    }

    public function template(): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_template.csv"');
        $out=fopen('php://output','w'); fputs($out,"\xEF\xBB\xBF");
        fputcsv($out,['EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар',
            'Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел','Amazon Link','ASIN',
            'Цена Конкурент  - Brutto','Цена Amazon  - Brutto',
            'Продажна Цена в Амазон  - Brutto','Цена без ДДС',
            'ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto',
            'ДДС  от Цена Доставчик','Транспорт от Доставчик до нас',
            'Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент',
            'Резултат','Намерена 2ра обява','Цена за ES FR IT',
            'DM цена','Нова цена след намаление','Доставени','За следваща поръчка','Електоника'],';');
        fclose($out); exit;
    }

    // ── Diagnostics ───────────────────────────────────────────
    public function diagnose(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $conn        = Firebase::testConnection();
            $cacheStatus = ProductDB::status();
            echo json_encode([
                'firebase'     => $conn,
                'cache'        => $cacheStatus,
                'php_version'  => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'curl'         => function_exists('curl_init'),
                'zip'          => class_exists('ZipArchive'),
                'env'          => [
                    'FIREBASE_DATABASE_URL'   => env('FIREBASE_DATABASE_URL',''),
                    'FIREBASE_SECRET_LEN'     => strlen(env('FIREBASE_SECRET','')),
                    'FIREBASE_SECRET_PREVIEW' => substr(env('FIREBASE_SECRET',''),0,6).'...',
                ],
            ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['fatal'=>$e->getMessage()]);
        }
    }

    // ── Debug import ──────────────────────────────────────────
    public function debugImport(): void {
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_FILES['file']['tmp_name'])) { echo json_encode(['error'=>'No file']); return; }
        $parsed = XlsxParser::parse($_FILES['file']['tmp_name']);
        if (empty($parsed['products'])) { echo json_encode(['error'=>'Parse failed','errors'=>$parsed['errors']]); return; }
        $first3 = array_slice($parsed['products'], 0, 3);
        $keys   = array_map(fn($p) => ['raw'=>$p['EAN Amazon']??'','sanitized'=>Firebase::sanitizeKey($p['EAN Amazon']??'')], $first3);
        echo json_encode(['count'=>count($parsed['products']),'first3_keys'=>$keys,'json_ok'=>json_encode($first3)!==false,'json_error'=>json_last_error_msg(),'columns'=>$parsed['columns']??[]]);
    }

    // ── Helpers ───────────────────────────────────────────────
    private function parseFilters(): array {
        $f = [];
        foreach (['dostavchik','brand','upload_status','elektronika','search','sort','dir'] as $k) {
            if (!empty($_GET[$k])) $f[$k] = $_GET[$k];
        }
        return $f;
    }

    private function parsePerPage(): int {
        $pp = (int)($_GET['perpage'] ?? 50);
        return in_array($pp, [25,50,100,250]) ? $pp : 50;
    }

    private function loadSupplierNames(): array {
        $file = DATA_DIR . '/suppliers.json';
        if (!file_exists($file)) return [];
        $list = json_decode(file_get_contents($file), true) ?? [];
        $names = array_map(fn($s) => $s['name'], array_filter($list, fn($s) => $s['active'] ?? true));
        sort($names);
        return array_values($names);
    }
}

    // ── Export archive as XLSX ────────────────────────────────
    public function exportArchive(): void {
        $key = trim($_GET['key'] ?? '');
        if (!$key) {
            http_response_code(400); echo 'Невалиден архивен ключ'; exit;
        }

        // Load archive from Firebase
        $res = Firebase::get("/archive/{$key}");
        if (!$res['ok'] || empty($res['data']['products'])) {
            http_response_code(404); echo 'Архивът не е намерен'; exit;
        }

        $products = array_values($res['data']['products']);
        $label    = $res['data']['label'] ?? $key;
        $date     = $res['data']['date']  ?? '';
        $filename = 'archive_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.xlsx';

        // Generate XLSX using simple SpreadsheetML (XML-based, no library needed)
        $headers = [
            'EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар',
            'Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел','Amazon Link','ASIN',
            'Цена Конкурент  - Brutto','Цена Amazon  - Brutto',
            'Продажна Цена в Амазон  - Brutto','Цена без ДДС',
            'ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto',
            'ДДС  от Цена Доставчик','Транспорт от Доставчик до нас',
            'Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент',
            'Резултат','Намерена 2ра обява','Цена за ES FR IT',
            'DM цена','Нова цена след намаление','Доставени','За следваща поръчка','Електоника',
        ];

        $xlsx = $this->buildXlsx($headers, $products, $label);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($xlsx));
        header('Cache-Control: max-age=0');
        echo $xlsx;
        exit;
    }

    /**
     * Build a valid .xlsx file using ZipArchive + SpreadsheetML
     * No external libraries needed.
     */
    private function buildXlsx(array $headers, array $rows, string $sheetName = 'Products'): string {
        // Collect all strings for shared strings
        $strings  = [];
        $strIndex = [];

        $addString = function(string $s) use (&$strings, &$strIndex): int {
            if (!isset($strIndex[$s])) {
                $strIndex[$s] = count($strings);
                $strings[]    = $s;
            }
            return $strIndex[$s];
        };

        // Pre-process: build row data
        $sheetRows = [];

        // Header row
        $hRow = [];
        foreach ($headers as $h) { $hRow[] = ['t' => 's', 'v' => $addString($h)]; }
        $sheetRows[] = $hRow;

        // Data rows
        foreach ($rows as $p) {
            $row = [];
            foreach ($headers as $h) {
                $val = (string)($p[$h] ?? '');
                if ($val !== '' && is_numeric(str_replace(',', '.', $val))) {
                    $row[] = ['t' => 'n', 'v' => $val];
                } else {
                    $row[] = ['t' => 's', 'v' => $addString($val)];
                }
            }
            $sheetRows[] = $row;
        }

        // Build sheet XML
        $colLetters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $sheetXml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheetXml  .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $sheetXml  .= '<sheetData>';

        foreach ($sheetRows as $ri => $row) {
            $rowNum = $ri + 1;
            $sheetXml .= "<row r=\"{$rowNum}\">";
            foreach ($row as $ci => $cell) {
                $colLetter = $ci < 26 ? $colLetters[$ci] : $colLetters[intdiv($ci,26)-1] . $colLetters[$ci%26];
                $ref = $colLetter . $rowNum;
                if ($cell['t'] === 'n') {
                    $sheetXml .= "<c r=\"{$ref}\"><v>" . htmlspecialchars($cell['v'], ENT_XML1) . "</v></c>";
                } else {
                    $sheetXml .= "<c r=\"{$ref}\" t=\"s\"><v>" . $cell['v'] . "</v></c>";
                }
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '</sheetData></worksheet>';

        // Build shared strings XML
        $ssXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $s) {
            $ssXml .= '<si><t>' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
        }
        $ssXml .= '</sst>';

        // Build XLSX zip in memory
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip     = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . htmlspecialchars($sheetName, ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>');

        $zip->addFromString('xl/sharedStrings.xml', $ssXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);
        return $content;
    }
