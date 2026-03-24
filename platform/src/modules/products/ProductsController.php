<?php
class ProductsController {

    private const PER_PAGE = 50;

    private static array $editableFields = [
        'Корекция  на цена', 'Коментар',
        'Продажна Цена в Амазон  - Brutto', 'Цена Доставчик -Netto',
        'Транспорт до кр. лиент  Netto', 'Намерена 2ра обява',
        'DM цена', 'Нова цена след намаление', 'За следваща поръчка', 'Електоника',
    ];

    // ── Index — loads page shell, data comes via AJAX ─────────
    public function index(): void {
        // Only fetch stats + filter dropdowns (uses 1 cached HTTP call total)
        $stats     = Firebase::getStats();
        $suppliers = Firebase::getDistinct('Доставчик'); // uses cache
        $brands    = Firebase::getDistinct('Бранд');     // uses cache

        $filters = [];
        if (!empty($_GET['dostavchik']))    $filters['dostavchik']    = $_GET['dostavchik'];
        if (!empty($_GET['brand']))         $filters['brand']         = $_GET['brand'];
        if (!empty($_GET['upload_status'])) $filters['upload_status'] = $_GET['upload_status'];
        if (!empty($_GET['elektronika']))   $filters['elektronika']   = $_GET['elektronika'];
        if (!empty($_GET['search']))        $filters['search']        = $_GET['search'];
        if (!empty($_GET['sort']))          $filters['sort']          = $_GET['sort'];
        if (!empty($_GET['dir']))           $filters['dir']           = $_GET['dir'];
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
        $filters = [];
        if (!empty($_GET['dostavchik']))    $filters['dostavchik']    = $_GET['dostavchik'];
        if (!empty($_GET['brand']))         $filters['brand']         = $_GET['brand'];
        if (!empty($_GET['upload_status'])) $filters['upload_status'] = $_GET['upload_status'];
        if (!empty($_GET['search']))        $filters['search']        = $_GET['search'];

        $all     = Firebase::getProducts($filters);
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

        $perPage  = (int)($_GET['perpage'] ?? 50);
        if (!in_array($perPage, [25,50,100,250])) $perPage = 50;
        $total    = count($all);
        $pages    = max(1, (int)ceil($total / $perPage));
        $page     = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
        $products = array_slice($all, ($page - 1) * $perPage, $perPage);

        View::json([
            'ok'       => true,
            'products' => $products,
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page,
            'perPage'  => $perPage,
        ]);
    }

    // ── Update single cell ────────────────────────────────────
    public function update(): void {
        $ean   = trim($_POST['ean']   ?? '');
        $field = trim($_POST['field'] ?? '');
        $value = trim($_POST['value'] ?? '');

        if (!$ean || !$field) { View::json(['success' => false, 'error' => 'Missing params'], 400); return; }
        if (!in_array($field, static::$editableFields)) { View::json(['success' => false, 'error' => 'Not editable'], 403); return; }

        $ok = Firebase::updateProduct($ean, $field, $value);
        Logger::info("Update: EAN={$ean} {$field}={$value}");
        View::json(['success' => $ok]);
    }

    // ── Add product ───────────────────────────────────────────
    public function addPage(): void {
        View::renderWithLayout('products/add', ['pageTitle' => 'Добави продукт', 'activePage' => 'products']);
    }

    public function addAction(): void {
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
        if (empty($p['EAN Amazon'])) { View::json(['success' => false, 'error' => 'EAN Amazon е задължителен'], 400); return; }
        $ok = Firebase::addProduct($p);
        Logger::info("Add: EAN={$p['EAN Amazon']}");
        View::json(['success' => $ok, 'error' => $ok ? null : 'Firebase грешка']);
    }

    // ── Import ────────────────────────────────────────────────
    public function importPage(): void {
        $archives = Firebase::listArchives();
        View::renderWithLayout('products/import', [
            'pageTitle'  => 'Import продукти',
            'activePage' => 'products',
            'archives'   => $archives,
        ]);
    }

    public function importAction(): void {
        if (empty($_FILES['file']['tmp_name'])) { View::json(['success' => false, 'error' => 'Не е избран файл'], 400); return; }
        if (strtolower(pathinfo($_FILES['file']['name'] ?? '', PATHINFO_EXTENSION)) !== 'xlsx') {
            View::json(['success' => false, 'error' => 'Само .xlsx файлове'], 400); return;
        }

        $tmpPath = DATA_DIR . '/upload_' . time() . '.xlsx';
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $tmpPath)) {
            View::json(['success' => false, 'error' => 'Грешка при качване'], 500); return;
        }

        $parsed = XlsxParser::parse($tmpPath);
        @unlink($tmpPath);

        if (empty($parsed['products'])) {
            View::json(['success' => false, 'error' => implode('; ', $parsed['errors'] ?? ['Файлът е празен'])], 400);
            return;
        }

        $mode  = $_POST['mode']  ?? 'merge';
        $label = trim($_POST['label'] ?? '');

        if ($mode === 'replace') {
            $archiveKey = Firebase::archiveCurrent($label ?: 'Преди импорт ' . date('d.m.Y H:i'));
            $ok = Firebase::putProducts($parsed['products']);
            $count = count($parsed['products']);
            Firebase::appendLog(['type' => 'import_replace', 'count' => $count]);
            View::json(['success' => $ok, 'mode' => 'replace', 'count' => $count, 'archive_key' => $archiveKey,
                'message' => "Заменени с {$count} продукта. Архив: {$archiveKey}"]);
        } else {
            $result = Firebase::mergeProducts($parsed['products']);
            Firebase::appendLog(['type' => 'import_merge', 'added' => $result['added'], 'skipped' => $result['skipped']]);
            View::json(['success' => true, 'mode' => 'merge',
                'added'   => $result['added'], 'skipped' => $result['skipped'], 'total' => $result['total'],
                'message' => "Добавени {$result['added']} нови. Пропуснати {$result['skipped']} съществуващи."]);
        }
    }

    public function restoreArchive(): void {
        $key = trim($_POST['key'] ?? '');
        if (!$key) { View::json(['success' => false, 'error' => 'Невалиден архив'], 400); return; }
        Firebase::archiveCurrent('Преди възстановяване ' . date('d.m.Y H:i'));
        $ok = Firebase::restoreArchive($key);
        View::json(['success' => $ok, 'message' => $ok ? 'Архивът е зареден!' : 'Грешка']);
    }

    // ── Export CSV ────────────────────────────────────────────
    public function export(): void {
        $filters = [];
        if (!empty($_GET['dostavchik']))    $filters['dostavchik']    = $_GET['dostavchik'];
        if (!empty($_GET['brand']))         $filters['brand']         = $_GET['brand'];
        if (!empty($_GET['upload_status'])) $filters['upload_status'] = $_GET['upload_status'];
        if (!empty($_GET['search']))        $filters['search']        = $_GET['search'];

        $products = Firebase::getProducts($filters);
        $headers  = ['EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар',
            'Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел','ASIN',
            'Цена Конкурент  - Brutto','Цена Amazon  - Brutto',
            'Продажна Цена в Амазон  - Brutto','Цена без ДДС',
            'ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto',
            'ДДС  от Цена Доставчик','Транспорт от Доставчик до нас',
            'Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент',
            'Резултат','Намерена 2ра обява','Цена за Испания / Франция / Италия',
            'DM цена','Нова цена след намаление','Доставени',
            'За следваща поръчка','Електоника','Статус'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        foreach ($products as $p) {
            $row = [];
            foreach (array_slice($headers, 0, 29) as $h) $row[] = $p[$h] ?? '';
            $row[] = $p['_upload_status'] ?? 'NOT_UPLOADED';
            fputcsv($out, $row, ';');
        }
        fclose($out); exit;
    }

    public function template(): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_template.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар',
            'Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел','Amazon Link','ASIN',
            'Цена Конкурент  - Brutto','Цена Amazon  - Brutto',
            'Продажна Цена в Амазон  - Brutto','Цена без ДДС',
            'ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto',
            'ДДС  от Цена Доставчик','Транспорт от Доставчик до нас',
            'Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент',
            'Резултат','Намерена 2ра обява','Цена за Испания / Франция / Италия',
            'DM цена','Нова цена след намаление','Доставени','За следваща поръчка','Електоника'], ';');
        fclose($out); exit;
    }

    // ── Diagnostics ───────────────────────────────────────────
    public function diagnose(): void {
        $conn   = Firebase::testConnection();
        $count  = 0;
        $sample = null;
        if ($conn['ok']) {
            $products = Firebase::getProducts();
            $count    = count($products);
            $sample   = array_slice($products, 0, 2);
        }
        View::json([
            'firebase'  => $conn,
            'products'  => $count,
            'sample'    => $sample,
            'php'       => PHP_VERSION,
            'memory'    => ini_get('memory_limit'),
            'curl'      => function_exists('curl_init'),
            'fopen'     => (bool)ini_get('allow_url_fopen'),
            'zip'       => class_exists('ZipArchive'),
        ]);
    }
}
