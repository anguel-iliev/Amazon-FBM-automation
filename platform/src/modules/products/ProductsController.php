<?php
class ProductsController {

    private const PER_PAGE = 50;

    private static array $editableFields = [
        'Корекция  на цена', 'Коментар',
        'Продажна Цена в Амазон  - Brutto', 'Цена Доставчик -Netto',
        'Транспорт до кр. лиент  Netto', 'Намерена 2ра обява',
        'DM цена', 'Нова цена след намаление', 'За следваща поръчка', 'Електоника',
    ];

    // ── Index — loads page shell only ────────────────────────
    public function index(): void {
        // Wrap in try-catch so even if Firebase fails, page still loads
        try {
            $stats     = Firebase::getStats();
            $suppliers = Firebase::getDistinct('Доставчик');
            $brands    = Firebase::getDistinct('Бранд');
        } catch (\Throwable $e) {
            Logger::error("Products::index Firebase error: " . $e->getMessage());
            $stats = ['total'=>0,'withAsin'=>0,'notUploaded'=>0,'suppliers'=>0];
            $suppliers = [];
            $brands    = [];
        }

        $filters = [];
        foreach (['dostavchik','brand','upload_status','elektronika','search','sort','dir'] as $k) {
            if (!empty($_GET[$k])) $filters[$k] = $_GET[$k];
        }
        $perPage = (int)($_GET['perpage'] ?? 50);
        if (!in_array($perPage, [25,50,100,250])) $perPage = 50;
        $page = max(1, (int)($_GET['page'] ?? 1));

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

    // ── AJAX: get paginated products ──────────────────────────
    public function data(): void {
        // Always return JSON — never let PHP crash silently
        header('Content-Type: application/json; charset=utf-8');

        try {
            if (!Firebase::isReady()) {
                echo json_encode([
                    'ok'    => false,
                    'error' => 'Firebase не е конфигуриран. Провери FIREBASE_DATABASE_URL и FIREBASE_SECRET в .env файла.',
                    'diag'  => self::getDiag(),
                ]);
                return;
            }

            $filters = [];
            foreach (['dostavchik','brand','upload_status','search'] as $k) {
                if (!empty($_GET[$k])) $filters[$k] = $_GET[$k];
            }

            $all = Firebase::getProducts($filters);

            // Sort
            $sortCol = $_GET['sort'] ?? '';
            $sortDir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            if ($sortCol) {
                usort($all, function($a, $b) use ($sortCol, $sortDir) {
                    $va = $a[$sortCol] ?? ''; $vb = $b[$sortCol] ?? '';
                    $cmp = (is_numeric($va) && is_numeric($vb))
                        ? (float)$va <=> (float)$vb
                        : strcmp((string)$va, (string)$vb);
                    return $sortDir === 'desc' ? -$cmp : $cmp;
                });
            }

            $perPage = (int)($_GET['perpage'] ?? 50);
            if (!in_array($perPage, [25,50,100,250])) $perPage = 50;
            $total   = count($all);
            $pages   = max(1, (int)ceil($total / $perPage));
            $page    = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
            $products= array_slice($all, ($page-1)*$perPage, $perPage);

            echo json_encode([
                'ok'       => true,
                'products' => $products,
                'total'    => $total,
                'pages'    => $pages,
                'page'     => $page,
                'perPage'  => $perPage,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            Logger::error("Products::data error: " . $e->getMessage());
            echo json_encode([
                'ok'    => false,
                'error' => 'PHP грешка: ' . $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
                'diag'  => self::getDiag(),
            ]);
        }
    }

    // ── Diagnostics ───────────────────────────────────────────
    public function diagnose(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $conn  = Firebase::testConnection();
            $count = 0; $sample = null; $error = null;
            if ($conn['ok']) {
                try {
                    $products = Firebase::getProducts();
                    $count    = count($products);
                    $sample   = array_slice($products, 0, 1);
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
            echo json_encode([
                'firebase_ready'  => Firebase::isReady(),
                'firebase_test'   => $conn,
                'products_count'  => $count,
                'products_error'  => $error,
                'sample'          => $sample,
                'php_version'     => PHP_VERSION,
                'memory_limit'    => ini_get('memory_limit'),
                'curl_available'  => function_exists('curl_init'),
                'fopen_enabled'   => (bool)ini_get('allow_url_fopen'),
                'zip_available'   => class_exists('ZipArchive'),
                'env_vars'        => [
                    'APP_URL'              => env('APP_URL', ''),
                    'FIREBASE_PROJECT_ID'  => env('FIREBASE_PROJECT_ID', ''),
                    'FIREBASE_DATABASE_URL'=> env('FIREBASE_DATABASE_URL', ''),
                    'FIREBASE_SECRET_LEN'  => strlen(env('FIREBASE_SECRET', '')),
                    'FIREBASE_SECRET_PREVIEW' => substr(env('FIREBASE_SECRET',''),0,6).'...',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['fatal_error' => $e->getMessage(), 'file' => $e->getFile().':'.$e->getLine()]);
        }
    }

    private static function getDiag(): array {
        return [
            'firebase_ready' => Firebase::isReady(),
            'curl'           => function_exists('curl_init'),
            'fopen'          => (bool)ini_get('allow_url_fopen'),
            'db_url'         => env('FIREBASE_DATABASE_URL', 'NOT SET'),
            'secret_len'     => strlen(env('FIREBASE_SECRET', '')),
        ];
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
            $ok = Firebase::updateProduct($ean, $field, $value);
            echo json_encode(['success' => $ok]);
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
            echo json_encode(['success'=>$ok,'error'=>$ok?null:'Firebase грешка']);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    // ── Import ────────────────────────────────────────────────
    public function importPage(): void {
        try { $archives = Firebase::listArchives(); } catch (\Throwable $e) { $archives = []; }
        View::renderWithLayout('products/import', [
            'pageTitle'=>'Import продукти','activePage'=>'products','archives'=>$archives,
        ]);
    }

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
            $mode  = $_POST['mode']  ?? 'merge';
            $label = trim($_POST['label'] ?? '');
            if ($mode === 'replace') {
                $cnt        = count($parsed['products']);
                $archiveKey = Firebase::archiveCurrent($label ?: 'Преди импорт '.date('d.m.Y H:i'));
                $result     = Firebase::putProducts($parsed['products']);
                if (!$result['ok']) {
                    echo json_encode([
                        'success' => false,
                        'error'   => $result['error'] ?? 'Firebase грешка при запис',
                        'written' => $result['written'] ?? 0,
                        'total'   => $cnt,
                    ]);
                    return;
                }
                Firebase::appendLog(['type'=>'import_replace','count'=>$result['written']]);
                echo json_encode(['success'=>true,'mode'=>'replace','count'=>$result['written'],
                    'archive_key'=>$archiveKey,
                    'message'=>"Заменени с {$result['written']} продукта."]);
            } else {
                $result = Firebase::mergeProducts($parsed['products']);
                Firebase::appendLog(['type'=>'import_merge','added'=>$result['added'],'skipped'=>$result['skipped']]);
                echo json_encode(['success'=>true,'mode'=>'merge','added'=>$result['added'],
                    'skipped'=>$result['skipped'],'total'=>$result['total'],
                    'message'=>"Добавени {$result['added']} нови. Пропуснати {$result['skipped']} съществуващи."]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>'PHP: '.$e->getMessage()]);
        }
    }

    public function restoreArchive(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $key = trim($_POST['key'] ?? '');
            if (!$key) { echo json_encode(['success'=>false,'error'=>'Невалиден архив']); return; }
            Firebase::archiveCurrent('Преди възстановяване '.date('d.m.Y H:i'));
            $ok = Firebase::restoreArchive($key);
            echo json_encode(['success'=>$ok,'message'=>$ok?'Архивът е зареден!':'Firebase грешка']);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    // ── Export CSV ────────────────────────────────────────────
    public function export(): void {
        try {
            $filters = [];
            foreach (['dostavchik','brand','upload_status','search'] as $k) {
                if (!empty($_GET[$k])) $filters[$k] = $_GET[$k];
            }
            $products = Firebase::getProducts($filters);
            $headers  = ['EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар',
                'Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел','ASIN',
                'Цена Конкурент  - Brutto','Цена Amazon  - Brutto',
                'Продажна Цена в Амазон  - Brutto','Цена без ДДС',
                'ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto',
                'ДДС  от Цена Доставчик','Транспорт от Доставчик до нас',
                'Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент',
                'Резултат','Намерена 2ра обява','Цена за Испания / Франция / Италия',
                'DM цена','Нова цена след намаление','Доставени','За следваща поръчка','Електоника','Статус'];
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="products_'.date('Ymd_His').'.csv"');
            $out = fopen('php://output','w'); fputs($out,"\xEF\xBB\xBF");
            fputcsv($out,$headers,';');
            foreach ($products as $p) {
                $row = [];
                foreach (array_slice($headers,0,29) as $h) $row[] = $p[$h]??'';
                $row[] = $p['_upload_status']??'NOT_UPLOADED';
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
        $out = fopen('php://output','w'); fputs($out,"\xEF\xBB\xBF");
        fputcsv($out,['EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар',
            'Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел','Amazon Link','ASIN',
            'Цена Конкурент  - Brutto','Цена Amazon  - Brutto',
            'Продажна Цена в Амазон  - Brutto','Цена без ДДС',
            'ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto',
            'ДДС  от Цена Доставчик','Транспорт от Доставчик до нас',
            'Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент',
            'Резултат','Намерена 2ра обява','Цена за Испания / Франция / Италия',
            'DM цена','Нова цена след намаление','Доставени','За следваща поръчка','Електоника'],';');
        fclose($out); exit;
    }

    // ── Debug import ──────────────────────────────────────────
    public function debugImport(): void {
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_FILES['file']['tmp_name'])) { echo json_encode(['error'=>'No file']); return; }
        $parsed = XlsxParser::parse($_FILES['file']['tmp_name']);
        if (empty($parsed['products'])) { echo json_encode(['error'=>'Parse failed','errors'=>$parsed['errors']]); return; }
        $first3 = array_slice($parsed['products'], 0, 3);
        $json   = json_encode($first3, JSON_UNESCAPED_UNICODE);
        $keys   = [];
        foreach ($first3 as $p) {
            $ean    = Firebase::sanitizeKey($p['EAN Amazon']??'');
            $keys[] = ['raw'=>$p['EAN Amazon']??'','sanitized'=>$ean];
        }
        echo json_encode(['count'=>count($parsed['products']),'first3_keys'=>$keys,'json_ok'=>$json!==false,'json_error'=>json_last_error_msg()]);
    }
}
