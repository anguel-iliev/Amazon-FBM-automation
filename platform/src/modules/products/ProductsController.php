<?php
class ProductsController {

    private const PER_PAGE = 50;

    private static array $editableFields = [
        'Корекция  на цена', 'Коментар',
        'Продажна Цена в Амазон  - Brutto', 'Цена Доставчик -Netto',
        'Транспорт до кр. лиент  Netto', 'Намерена 2ра обява',
        'DM цена', 'Нова цена след намаление', 'За следваща поръчка', 'Електоника',
    ];

    public function index(): void {
        $validPP = [25, 50, 100, 250];
        $perPage = (int)($_GET['perpage'] ?? 50);
        if (!in_array($perPage, $validPP)) $perPage = 50;
        $page = max(1, (int)($_GET['page'] ?? 1));

        $filters = array_filter([
            'dostavchik'    => $_GET['dostavchik']    ?? '',
            'brand'         => $_GET['brand']         ?? '',
            'upload_status' => $_GET['upload_status'] ?? '',
            'elektronika'   => $_GET['elektronika']   ?? '',
            'search'        => $_GET['search']        ?? '',
            'sort'          => $_GET['sort']          ?? '',
            'dir'           => $_GET['dir']           ?? '',
        ]);

        // Fetch once — everything else uses cache
        $all = Firebase::getProducts($filters);

        // Sort
        $sortCol = $filters['sort'] ?? '';
        $sortDir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        if ($sortCol) {
            usort($all, function ($a, $b) use ($sortCol, $sortDir) {
                $va = $a[$sortCol] ?? ''; $vb = $b[$sortCol] ?? '';
                $cmp = is_numeric($va) && is_numeric($vb) ? (float)$va <=> (float)$vb : strcmp((string)$va, (string)$vb);
                return $sortDir === 'desc' ? -$cmp : $cmp;
            });
        }

        $total    = count($all);
        $pages    = max(1, (int)ceil($total / $perPage));
        $page     = min($page, $pages);
        $products = array_slice($all, ($page - 1) * $perPage, $perPage);

        // These all use the same cache — no extra Firebase calls
        $stats     = Firebase::getStats();
        $suppliers = Firebase::getDistinct('Доставчик');
        $brands    = Firebase::getDistinct('Бранд');

        View::renderWithLayout('products/index', [
            'pageTitle'  => 'Продукти',
            'activePage' => 'products',
            'products'   => $products,
            'total'      => $total,
            'page'       => $page,
            'pages'      => $pages,
            'perPage'    => $perPage,
            'filters'    => $filters,
            'suppliers'  => $suppliers,
            'brands'     => $brands,
            'stats'      => $stats,
        ]);
    }

    public function update(): void {
        $ean   = trim($_POST['ean']   ?? '');
        $field = trim($_POST['field'] ?? '');
        $value = trim($_POST['value'] ?? '');

        if (!$ean || !$field) { View::json(['success' => false, 'error' => 'Missing params'], 400); return; }
        if (!in_array($field, static::$editableFields)) { View::json(['success' => false, 'error' => 'Field not editable'], 403); return; }

        $ok = Firebase::updateProduct($ean, $field, $value);
        Logger::info("Product update: EAN={$ean} {$field}={$value} by " . Auth::user());
        View::json(['success' => $ok]);
    }

    public function addPage(): void {
        View::renderWithLayout('products/add', [
            'pageTitle'  => 'Добави продукт',
            'activePage' => 'products',
        ]);
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
        if ($ok) { Logger::info("Product added: {$p['EAN Amazon']} by " . Auth::user()); View::json(['success' => true]); }
        else { View::json(['success' => false, 'error' => 'Грешка при записване в Firebase'], 500); }
    }

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

        $ext = strtolower(pathinfo($_FILES['file']['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') { View::json(['success' => false, 'error' => 'Само .xlsx файлове'], 400); return; }

        $mode    = $_POST['mode']  ?? 'merge';
        $label   = trim($_POST['label'] ?? '');
        $tmpPath = DATA_DIR . '/upload_' . time() . '.xlsx';

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $tmpPath)) {
            View::json(['success' => false, 'error' => 'Грешка при качване на файла'], 500); return;
        }

        $parsed = XlsxParser::parse($tmpPath);
        @unlink($tmpPath);

        if (empty($parsed['products'])) {
            $err = implode('; ', $parsed['errors'] ?? ['Файлът е празен или невалиден']);
            View::json(['success' => false, 'error' => $err], 400); return;
        }

        $products = $parsed['products'];

        if ($mode === 'replace') {
            $archiveKey = Firebase::archiveCurrent($label ?: 'Преди импорт ' . date('d.m.Y H:i'));
            $ok = Firebase::putProducts($products);
            if (!$ok) { View::json(['success' => false, 'error' => 'Грешка при записване в Firebase. Провери Firebase настройките.'], 500); return; }
            $count = count($products);
            Logger::info("Import REPLACE: {$count} products, archive={$archiveKey} by " . Auth::user());
            Firebase::appendLog(['type' => 'import_replace', 'count' => $count]);
            View::json(['success' => true, 'mode' => 'replace', 'count' => $count, 'archive_key' => $archiveKey]);
        } else {
            $result = Firebase::mergeProducts($products);
            Logger::info("Import MERGE: added={$result['added']} skipped={$result['skipped']} by " . Auth::user());
            Firebase::appendLog(['type' => 'import_merge', 'added' => $result['added'], 'skipped' => $result['skipped']]);
            View::json(['success' => true, 'mode' => 'merge', 'added' => $result['added'], 'skipped' => $result['skipped'], 'total' => $result['total']]);
        }
    }

    public function restoreArchive(): void {
        $key = trim($_POST['key'] ?? '');
        if (!$key) { View::json(['success' => false, 'error' => 'Невалиден архив'], 400); return; }
        Firebase::archiveCurrent('Преди възстановяване ' . date('d.m.Y H:i'));
        $ok = Firebase::restoreArchive($key);
        View::json($ok ? ['success' => true] : ['success' => false, 'error' => 'Грешка при зареждане'], 500);
    }

    public function export(): void {
        $filters = array_filter([
            'dostavchik'    => $_GET['dostavchik']    ?? '',
            'brand'         => $_GET['brand']         ?? '',
            'upload_status' => $_GET['upload_status'] ?? '',
            'search'        => $_GET['search']        ?? '',
        ]);
        $products = Firebase::getProducts($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        $headers = ['EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар','Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел','ASIN','Цена Конкурент  - Brutto','Цена Amazon  - Brutto','Продажна Цена в Амазон  - Brutto','Цена без ДДС','ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto','ДДС  от Цена Доставчик','Транспорт от Доставчик до нас','Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент','Резултат','Намерена 2ра обява','Цена за Испания / Франция / Италия','DM цена','Нова цена след намаление','Доставени','За следваща поръчка','Електоника','Статус'];
        fputcsv($out, $headers, ';');
        foreach ($products as $p) {
            $row = [];
            foreach (array_slice($headers, 0, 29) as $h) $row[] = $p[$h] ?? '';
            $row[] = $p['_upload_status'] ?? 'NOT_UPLOADED';
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    public function template(): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_template.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар','Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел','Amazon Link','ASIN','Цена Конкурент  - Brutto','Цена Amazon  - Brutto','Продажна Цена в Амазон  - Brutto','Цена без ДДС','ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto','ДДС  от Цена Доставчик','Транспорт от Доставчик до нас','Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент','Резултат','Намерена 2ра обява','Цена за Испания / Франция / Италия','DM цена','Нова цена след намаление','Доставени','За следваща поръчка','Електоника'], ';');
        fclose($out);
        exit;
    }
}
