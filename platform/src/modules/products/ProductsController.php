<?php
class ProductsController {

    private static array $editableFields = [
        'Продажна Цена в Амазон  - Brutto',
        'Цена Конкурент  - Brutto',
        'Цена Доставчик -Netto',
        'Транспорт от Доставчик до нас',
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
            $stats['suppliers'] = count($suppliers);
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
            'columnsMeta'=> ProductDB::getAllColumnsMeta(),
            'marketplaces'=> ProductDB::getMarketplaces(),
            'currentMarketplace'=> ProductDB::getMarketplaceCodeFromRequest(),
            'activeCourier'=> ProductDB::getActiveCourier(),
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
            if (!in_array($field, $this->editableFields(), true)) { echo json_encode(['success'=>false,'error'=>'Not editable']); return; }

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
        $suppliers = $this->loadSupplierNames();
        $brands = ProductDB::distinct('Бранд');
        View::renderWithLayout('products/add', [
            'pageTitle'=>'Добави продукт',
            'activePage'=>'products',
            'suppliers'=>$suppliers,
            'brands'=>$brands,
            'columnsMeta'=> ProductDB::getAllColumnsMeta(),
            'marketplaces'=> ProductDB::getMarketplaces(),
            'currentMarketplace'=> ProductDB::getMarketplaceCodeFromRequest(),
            'activeCourier'=> ProductDB::getActiveCourier(),
        ]);
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


    public function deleteAction(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $scope = trim((string)($_POST['scope'] ?? 'selected'));
            if ($scope === 'all') {
                $existing = Firebase::getProducts();
                $existingCount = is_array($existing) ? count($existing) : 0;
                $archiveKey = '';
                if ($existingCount > 0) {
                    $archiveKey = ProductDB::saveArchiveSnapshot($existing, 'Авто архив преди изтриване ' . date('d.m.Y H:i'));
                }
                $fb = Firebase::deleteAllProducts();
                if (!$fb['ok']) { echo json_encode(['success'=>false,'error'=>$fb['error'] ?? 'Firebase грешка']); return; }
                $count = ProductDB::deleteAll();
                Logger::audit('products.deleted_all', ['count'=>$count, 'by'=>Auth::user()['email'] ?? '', 'archive_key'=>$archiveKey]);
                echo json_encode(['success'=>true,'deleted'=>$count,'archive_key'=>$archiveKey]);
                return;
            }
            $eans = $_POST['eans'] ?? [];
            if (!is_array($eans)) $eans = [$eans];
            $eans = array_values(array_filter(array_map('trim', $eans)));
            if (!$eans) { echo json_encode(['success'=>false,'error'=>'Не са избрани продукти']); return; }
            $fb = Firebase::deleteProductsByEans($eans);
            if (!$fb['ok']) { echo json_encode(['success'=>false,'error'=>$fb['error'] ?? 'Firebase грешка']); return; }
            $count = ProductDB::deleteByEans($eans);
            Logger::audit('products.deleted', ['count'=>$count, 'by'=>Auth::user()['email'] ?? '', 'eans'=>$eans]);
            echo json_encode(['success'=>true,'deleted'=>$count]);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    // ── Import page ───────────────────────────────────────────
    public function importPage(): void {
        try { $archives = ProductDB::listProductArchives(); } catch (\Throwable $e) { $archives = []; }
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
            $upload = $_FILES['file'] ?? ($_FILES[array_key_first($_FILES ?? [])] ?? null);
            if (!$upload || !is_array($upload)) { echo json_encode(['success'=>false,'error'=>'Не е избран файл']); return; }
            $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_OK);
            if ($uploadError !== UPLOAD_ERR_OK) {
                $map = [UPLOAD_ERR_INI_SIZE=>'Файлът е твърде голям за сървъра',UPLOAD_ERR_FORM_SIZE=>'Файлът е твърде голям',UPLOAD_ERR_PARTIAL=>'Файлът е качен частично',UPLOAD_ERR_NO_FILE=>'Не е избран файл',UPLOAD_ERR_NO_TMP_DIR=>'Липсва временна папка на сървъра',UPLOAD_ERR_CANT_WRITE=>'Сървърът не може да запише файла',UPLOAD_ERR_EXTENSION=>'Качването е спряно от PHP extension'];
                echo json_encode(['success'=>false,'error'=>$map[$uploadError] ?? ('Upload error #' . $uploadError)]); return;
            }
            if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) { echo json_encode(['success'=>false,'error'=>'Не е избран файл']); return; }
            $ext = strtolower((string)pathinfo($upload['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx','csv'], true)) {
                echo json_encode(['success'=>false,'error'=>'Само .xlsx и .csv файлове']); return;
            }

            $tmpPath = DATA_DIR . '/upload_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (!move_uploaded_file($upload['tmp_name'], $tmpPath) && !@copy($upload['tmp_name'], $tmpPath)) {
                echo json_encode(['success'=>false,'error'=>'Грешка при качване']); return;
            }

            $parsed = XlsxParser::parse($tmpPath);
            @unlink($tmpPath);

            $mode  = $_POST['mode']  ?? 'first';
            $label = trim($_POST['label'] ?? '');

            if (!empty($parsed['products'])) {
                $parsed['products'] = $this->stripFormulaColumnsFromImport($parsed['products']);
                $parsed['products'] = $this->applySupplierTransportToProducts($parsed['products']);
                $guard = $this->protectCriticalStaticColumns($parsed['products'], $mode, $parsed['columns'] ?? []);
                if (!empty($guard['error'])) {
                    echo json_encode(['success'=>false,'error'=>$guard['error']]); return;
                }
                $parsed['products'] = $guard['products'];
                $supplierValidation = $this->validateImportSuppliers($parsed['products']);
                if (empty($supplierValidation['ok'])) {
                    echo json_encode(['success'=>false,'error'=>$this->buildUnknownSupplierError($supplierValidation['errors'])]); return;
                }
            }

            if (empty($parsed['products'])) {
                echo json_encode(['success'=>false,'error'=>implode('; ',$parsed['errors']??['Файлът е празен'])]); return;
            }

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
                $archiveKey = ProductDB::saveArchiveSnapshot(Firebase::getProducts(), $label ?: 'Преди импорт '.date('d.m.Y H:i'));
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


    public function validateImportAction(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $upload = $_FILES['file'] ?? ($_FILES[array_key_first($_FILES ?? [])] ?? null);
            if (!$upload || !is_array($upload)) { echo json_encode(['success'=>false,'error'=>'Не е избран файл']); return; }
            $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_OK);
            if ($uploadError !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'error'=>'Невалиден файл']); return; }
            if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) { echo json_encode(['success'=>false,'error'=>'Не е избран файл']); return; }
            $ext = strtolower((string)pathinfo($upload['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx','csv'], true)) { echo json_encode(['success'=>false,'error'=>'Само .xlsx и .csv файлове']); return; }

            $tmpPath = DATA_DIR . '/validate_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (!move_uploaded_file($upload['tmp_name'], $tmpPath) && !@copy($upload['tmp_name'], $tmpPath)) {
                echo json_encode(['success'=>false,'error'=>'Грешка при качване']); return;
            }
            $parsed = XlsxParser::parse($tmpPath);
            @unlink($tmpPath);

            $rows = $parsed['products'] ?? [];
            if (!$rows) { echo json_encode(['success'=>false,'error'=>'Файлът е празен']); return; }

            $blank = 0
            ;$seen = [];
            $dups = 0;
            foreach ($rows as $r) {
                $ean = trim((string)($r['EAN Amazon'] ?? ''));
                if ($ean === '') { $blank++; continue; }
                if (isset($seen[$ean])) $dups++;
                else $seen[$ean] = 1;
            }
            echo json_encode([
                'success'=>true,
                'rows'=>count($rows),
                'unique_ean'=>count($seen),
                'blank_ean'=>$blank,
                'duplicate_ean'=>$dups,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>'PHP: '.$e->getMessage()]);
        }
    }

    // ── Restore archive ───────────────────────────────────────
    public function restoreArchive(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $key = trim($_POST['key'] ?? $_GET['key'] ?? '');
            if (!$key) { echo json_encode(['success'=>false,'error'=>'Невалиден архив']); return; }
            ProductDB::saveArchiveSnapshot(Firebase::getProducts(), 'Преди възстановяване '.date('d.m.Y H:i'));
            $restored = ProductDB::restoreProductArchive($key);
            if (empty($restored['ok'])) { echo json_encode(['success'=>false,'error'=>$restored['error'] ?? 'Невалиден архив']); return; }
            $put = Firebase::putProducts($restored['products']);
            $ok = !empty($put['ok']);
            if ($ok) ProductDB::replaceAll($restored['products']);
            echo json_encode(['success'=>$ok,'message'=>$ok?'Архивът е зареден!':($put['error'] ?? 'Firebase грешка')]);
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

    private function getSupplierTransportMap(): array {
        $map = [];
        foreach (ProductDB::getSuppliers(true) as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') continue;
            $val = trim((string)($row['transport_to_us'] ?? '0.39'));
            if ($val === '' || !is_numeric(str_replace(',', '.', $val))) $val = '0.39';
            $map[$name] = number_format((float)str_replace(',', '.', $val), 2, '.', '');
        }
        return $map;
    }

    private function applySupplierTransportToProducts(array $products): array {
        $map = $this->getSupplierTransportMap();
        if (!$map) return $products;
        foreach ($products as &$p) {
            $supplier = trim((string)($p['Доставчик'] ?? ''));
            if ($supplier !== '' && isset($map[$supplier])) {
                $p['Транспорт от Доставчик до нас'] = $map[$supplier];
            }
        }
        unset($p);
        return $products;
    }

    private function selectedExportEans(): array {
        $raw = $_GET['eans'] ?? [];
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        } elseif (!is_array($raw)) {
            $raw = [];
        }
        return array_values(array_unique(array_filter(array_map('trim', $raw))));
    }

    private function exportProducts(): array {
        $result = ProductDB::query([], '', 'asc', 1, 999999);
        $products = $result['products'];
        $eans = $this->selectedExportEans();
        if (!$eans) return $products;
        $indexed = [];
        foreach ($products as $prod) { $indexed[(string)($prod['EAN Amazon'] ?? '')] = $prod; }
        $out = [];
        foreach ($eans as $ean) if (isset($indexed[$ean])) $out[] = $indexed[$ean];
        return $out;
    }

    // ── Export CSV ────────────────────────────────────────────
    public function export(): void {
        try {
            $products = $this->exportProducts();
            $headers = $this->exportHeaders(false, true);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="products_'.date('Ymd_His').'.csv"');
            $out = fopen('php://output','w'); fputs($out,"\xEF\xBB\xBF");
            fputcsv($out,$headers,';');
            foreach ($products as $p) {
                $row=[];
                foreach ($headers as $h) {
                    $row[] = $h === 'Статус' ? ($p['_upload_status'] ?? 'NOT_UPLOADED') : ($p[$h] ?? '');
                }
                fputcsv($out,$row,';');
            }
            fclose($out); exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/plain'); echo 'Грешка: '.$e->getMessage(); exit;
        }
    }


    public function exportXlsx(): void {
        try {
            $products = $this->exportProducts();
            $headers = $this->exportHeaders(true, true);

            $rows = [];
            foreach ($products as $p) {
                $row = $p;
                $row['Статус'] = $p['_upload_status'] ?? 'NOT_UPLOADED';
                $rows[] = $row;
            }

            $xlsx = $this->buildXlsx($headers, $rows, 'Products');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="products_' . date('Ymd_His') . '.xlsx"');
            header('Content-Length: ' . strlen($xlsx));
            header('Cache-Control: max-age=0');
            echo $xlsx;
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Грешка: ' . $e->getMessage();
            exit;
        }
    }

    public function template(): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_template.csv"');
        $out=fopen('php://output','w'); fputs($out,"\xEF\xBB\xBF");
        fputcsv($out,$this->exportHeaders(true,false),';');
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
    private function stripFormulaColumnsFromImport(array $products): array {
        $formulaColumns = array_keys(ProductDB::getFormulaMap());
        if (!$formulaColumns) return $products;
        foreach ($products as &$p) {
            foreach ($formulaColumns as $col) {
                unset($p[$col]);
            }
        }
        unset($p);
        return $products;
    }


    private function criticalImportColumns(): array {
        return [
            'Цена Доставчик -Netto',
            'Транспорт от Доставчик до нас',
            'Транспорт до кр. лиент  Netto',
        ];
    }

    private function currentProductsIndex(): array {
        $res = ProductDB::query([], '', 'asc', 1, 999999);
        $idx = [];
        foreach (($res['products'] ?? []) as $p) {
            $ean = trim((string)($p['EAN Amazon'] ?? ''));
            if ($ean !== '') $idx[$ean] = $p;
        }
        return $idx;
    }

    private function protectCriticalStaticColumns(array $products, string $mode, array $headers = []): array {
        if (!$products || !in_array($mode, ['replace','first','merge'], true)) {
            return ['products' => $products, 'warnings' => []];
        }

        $existing = $this->currentProductsIndex();
        $headersMap = array_fill_keys(array_map('trim', $headers), true);
        $critical = $this->criticalImportColumns();
        $stats = [];
        foreach ($critical as $col) {
            $stats[$col] = [
                'header_present' => !$headers || isset($headersMap[$col]),
                'import_non_empty' => 0,
                'import_blank' => 0,
                'matching_non_empty_existing' => 0,
                'blank_in_import' => 0,
                'preserved' => 0,
            ];
        }

        foreach ($products as $p) {
            foreach ($critical as $col) {
                $newVal = trim((string)($p[$col] ?? ''));
                if ($newVal === '') $stats[$col]['import_blank']++;
                else $stats[$col]['import_non_empty']++;
            }
        }

        if ($existing) {
            foreach ($products as &$p) {
                $ean = trim((string)($p['EAN Amazon'] ?? ''));
                if ($ean === '' || !isset($existing[$ean])) continue;
                $old = $existing[$ean];
                foreach ($critical as $col) {
                    $oldVal = trim((string)($old[$col] ?? ''));
                    if ($oldVal === '') continue;
                    $stats[$col]['matching_non_empty_existing']++;
                    $newVal = trim((string)($p[$col] ?? ''));
                    if ($newVal === '') {
                        $stats[$col]['blank_in_import']++;
                        $p[$col] = $oldVal;
                        $stats[$col]['preserved']++;
                    }
                }
            }
            unset($p);
        }

        $blocked = [];
        foreach ($stats as $col => $s) {
            if (!$s['header_present']) {
                $blocked[] = $col . ' (липсва колона във файла)';
                continue;
            }

            if (($s['import_non_empty'] ?? 0) <= 0) {
                $blocked[] = $col . ' (всички стойности във файла са празни)';
                continue;
            }

            if ($mode === 'replace' && ($s['matching_non_empty_existing'] ?? 0) > 0 && ($s['blank_in_import'] ?? 0) >= ($s['matching_non_empty_existing'] ?? 0)) {
                $blocked[] = $col . ' (всички налични стойности биха станали празни)';
            }
        }

        if ($blocked) {
            $modeLabel = match ($mode) {
                'first' => '„Първоначален импорт“',
                'merge' => '„Добави само нови продукти“',
                default => '„Замени изцяло“',
            };
            return [
                'products' => $products,
                'warnings' => $stats,
                'error' => 'Импортът е спрян за защита на данните. Критични входни колони са празни или липсват във файла: ' . implode('; ', $blocked) . '. Попълни липсващите стойности преди ' . $modeLabel . '.',
            ];
        }

        return ['products' => $products, 'warnings' => $stats];
    }

    private function validateImportSuppliers(array $products): array {
        $knownSuppliers = $this->loadSupplierNames();
        if (!$knownSuppliers) {
            return ['ok' => true, 'errors' => []];
        }

        $knownMap = [];
        foreach ($knownSuppliers as $name) {
            $knownMap[trim((string)$name)] = true;
        }

        $errors = [];
        foreach (array_values($products) as $idx => $p) {
            $supplier = trim((string)($p['Доставчик'] ?? ''));
            if ($supplier === '') continue;
            if (!isset($knownMap[$supplier])) {
                $rowNo = $idx + 2; // header is row 1
                $errors[] = [
                    'row' => $rowNo,
                    'supplier' => $supplier,
                ];
            }
        }

        return ['ok' => empty($errors), 'errors' => $errors];
    }

    private function buildUnknownSupplierError(array $errors): string {
        if (!$errors) return '';
        $lines = [];
        foreach (array_slice($errors, 0, 20) as $err) {
            $lines[] = 'ред ' . $err['row'] . ': „' . $err['supplier'] . '“';
        }
        $suffix = count($errors) > 20 ? ' Показани са първите 20 несъответствия.' : '';
        return 'Импортът е спрян. Открити са доставчици, които не съществуват в системата или не съвпадат 100% с въведените доставчици: ' . implode('; ', $lines) . '.' . $suffix;
    }

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


    private function editableFields(): array {
        $editable = static::$editableFields;
        foreach (ProductDB::getAllColumnsMeta() as $col) {
            if (!empty($col['is_custom']) && empty($col['is_formula'])) $editable[] = $col['name'];
        }
        return array_values(array_unique($editable));
    }

    private function exportHeaders(bool $includeLink = true, bool $includeStatus = true): array {
        $headers = ['EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар','Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел'];
        if ($includeLink) $headers[] = 'Amazon Link';
        $headers = array_merge($headers, ['ASIN','Цена Конкурент  - Brutto','Цена Amazon  - Brutto','Продажна Цена в Амазон  - Brutto','Цена без ДДС','ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto','ДДС  от Цена Доставчик','Транспорт от Доставчик до нас','Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент','Резултат','Намерена 2ра обява','Цена за ES FR IT','DM цена','Нова цена след намаление','Доставени','За следваща поръчка','Електоника']);
        foreach (ProductDB::getAllColumnsMeta() as $col) {
            if (!empty($col['is_custom'])) $headers[] = $col['name'];
        }
        if ($includeStatus) $headers[] = 'Статус';
        return $headers;
    }

    private function loadSupplierNames(): array {
        $names = array_map(fn($s) => $s['name'], ProductDB::getSuppliers(true));
        $names = array_values(array_unique(array_filter(array_map('trim', $names))));
        natcasesort($names);
        return array_values($names);
    }
    // ── Export archive as XLSX (POST — avoids URL encoding issues) ──
    public function exportArchive(): void {
        // Accept key from POST body (preferred) or GET parameter
        $key = trim($_POST['key'] ?? $_GET['key'] ?? '');
        if (!$key) {
            http_response_code(400); echo 'Невалиден архивен ключ'; exit;
        }

        $archive = ProductDB::getProductArchive($key);
        if (!$archive || empty($archive['products'])) {
            http_response_code(404);
            echo 'Архивът не е намерен. Ако проблемът продължава, направи нов импорт "Замени изцяло" за да създадеш нов архив.';
            exit;
        }

        $products = array_values($archive['products']);
        $archDate = $archive['date'] ?? '';
        // Build short clean filename: archive_2026-03-25_17-07.xlsx
        // Extract just the date+time prefix from the key (first 16 chars: "2026-03-25_17-07")
        $datePrefix = substr($key, 0, 16);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}$/', $datePrefix)) {
            // Fallback: use full date from archive metadata
            $datePrefix = $archDate ? date('Y-m-d_H-i', strtotime($archDate)) : date('Y-m-d_H-i');
        }
        $filename = 'archive_' . $datePrefix . '.xlsx';

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

        $sheetName = trim((string)($archive['label'] ?? ($_POST['label'] ?? $_GET['label'] ?? '')));
        if ($sheetName === '') {
            $sheetName = 'Archive';
        }
        $xlsx = $this->buildXlsx($headers, $products, $sheetName);

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
        $sheetName = trim($sheetName);
        $sheetName = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $sheetName);
        $sheetName = preg_replace('/\s+/u', ' ', $sheetName) ?: 'Products';
        $sheetName = mb_substr($sheetName, 0, 31);
        if ($sheetName === '') $sheetName = 'Products';

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

}
