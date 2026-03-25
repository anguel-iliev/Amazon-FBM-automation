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
            $stats     = ProductCache::stats();
            $suppliers = $this->loadSupplierNames();
            $brands    = ProductCache::distinct('Бранд');
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
            $result = ProductCache::query($filters, $sortCol, $sortDir, $page, $perPage);

            echo json_encode(array_merge($result, ['ok' => true]), JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            Logger::error("Products::data: " . $e->getMessage());
            echo json_encode([
                'ok'    => false,
                'error' => 'PHP грешка: ' . $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
                'diag'  => [
                    'cache_status'   => ProductCache::status(),
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
            $brands   = ProductCache::distinct('Бранд', $supplier);
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
            ProductCache::updateOne($ean, $field, $value);

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
                // Rebuild cache to include new product
                ProductCache::rebuildFromFirebase();
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
        $cacheStatus = ProductCache::status();
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
                // Rebuild local cache
                ProductCache::write($parsed['products']);
                Firebase::appendLog(['type'=>'import_first','count'=>$result['written']]);
                echo json_encode(['success'=>true,'mode'=>'first','count'=>$result['written'],
                    'message'=>"Импортирани {$result['written']} продукта."]);

            } elseif ($mode === 'replace') {
                $archiveKey = Firebase::archiveCurrent($label ?: 'Преди импорт '.date('d.m.Y H:i'));
                $result     = Firebase::putProducts($parsed['products']);
                if (!$result['ok']) { echo json_encode(['success'=>false,'error'=>$result['error'],'written'=>$result['written']]); return; }
                // Rebuild local cache
                ProductCache::write($parsed['products']);
                Firebase::appendLog(['type'=>'import_replace','count'=>$result['written']]);
                echo json_encode(['success'=>true,'mode'=>'replace','count'=>$result['written'],
                    'archive_key'=>$archiveKey,'message'=>"Заменени с {$result['written']} продукта."]);

            } else {
                // merge
                $result = Firebase::mergeProducts($parsed['products']);
                if (!empty($result['error'])) { echo json_encode(['success'=>false,'error'=>$result['error']]); return; }
                // Rebuild cache from Firebase to include merged data
                ProductCache::rebuildFromFirebase();
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
                ProductCache::rebuildFromFirebase();
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
            $ok     = ProductCache::rebuildFromFirebase();
            $status = ProductCache::status();
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
            $result   = ProductCache::query($filters, '', 'asc', 1, 999999);
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
            $cacheStatus = ProductCache::status();
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
