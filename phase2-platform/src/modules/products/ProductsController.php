<?php
class ProductsController {

    // Allowed editable fields (whitelist for security)
    private static $editableFields = [
        'Корекция  на цена',
        'Коментар',
        'Продажна Цена в Амазон  - Brutto',
        'Цена Доставчик -Netto',
        'Транспорт до кр. лиент  Netto',
        'Намерена 2ра обява',
        'DM цена',
        'Нова цена след намаление',
        'За следваща поръчка',
        'Електоника',
    ];

    public function index() {
        require_once SRC . '/lib/DataStore.php';

        // Per-page
        $validPP = [25, 50, 100, 250];
        $perPage = (int)($_GET['perpage'] ?? 50);
        if (!in_array($perPage, $validPP)) $perPage = 50;

        $page    = max(1, (int)($_GET['page'] ?? 1));

        // Filters
        $filters = [];
        if (!empty($_GET['dostavchik']))    $filters['dostavchik']    = $_GET['dostavchik'];
        if (!empty($_GET['brand']))         $filters['brand']         = $_GET['brand'];
        if (!empty($_GET['upload_status'])) $filters['upload_status'] = $_GET['upload_status'];
        if (!empty($_GET['elektronika']))   $filters['elektronika']   = $_GET['elektronika'];
        if (!empty($_GET['search']))        $filters['search']        = $_GET['search'];

        $all = DataStore::getProducts($filters);

        // ── Sorting ────────────────────────────────────────────────
        $sortCol = $_GET['sort'] ?? '';
        $sortDir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        if ($sortCol) {
            usort($all, function($a, $b) use ($sortCol, $sortDir) {
                $va = $a[$sortCol] ?? '';
                $vb = $b[$sortCol] ?? '';
                // Numeric sort
                if (is_numeric($va) && is_numeric($vb)) {
                    $cmp = (float)$va <=> (float)$vb;
                } else {
                    $cmp = strcmp((string)$va, (string)$vb);
                }
                return $sortDir === 'desc' ? -$cmp : $cmp;
            });
        }

        $total    = count($all);
        $products = array_slice($all, ($page - 1) * $perPage, $perPage);
        $pages    = max(1, (int)ceil($total / $perPage));

        View::renderWithLayout('products/index', [
            'pageTitle'  => 'Продукти',
            'activePage' => 'products',
            'products'   => $products,
            'total'      => $total,
            'page'       => $page,
            'pages'      => $pages,
            'perPage'    => $perPage,
            'filters'    => $filters,
            'suppliers'  => DataStore::getDistinctValues('Доставчик'),
            'brands'     => DataStore::getDistinctValues('Бранд'),
            'columns'    => DataStore::getColumns(),
        ]);
    }

    public function search() {
        require_once SRC . '/lib/DataStore.php';
        $filters = [];
        if (!empty($_GET['search']))     $filters['search']     = $_GET['search'];
        if (!empty($_GET['dostavchik'])) $filters['dostavchik'] = $_GET['dostavchik'];
        if (!empty($_GET['brand']))      $filters['brand']      = $_GET['brand'];
        $products = array_slice(DataStore::getProducts($filters), 0, 100);
        View::json(['products' => $products, 'total' => count($products)]);
    }

    public function update() {
        require_once SRC . '/lib/DataStore.php';

        $ean   = trim($_POST['ean']   ?? '');
        $field = trim($_POST['field'] ?? '');
        $value = trim($_POST['value'] ?? '');

        if (!$ean || !$field) {
            View::json(['success' => false, 'error' => 'Missing params'], 400);
            return;
        }
        // Security: only allow whitelisted fields
        if (!in_array($field, static::$editableFields)) {
            View::json(['success' => false, 'error' => 'Field not editable'], 403);
            return;
        }

        $ok = DataStore::updateProduct($ean, $field, $value);
        View::json(['success' => $ok]);
    }
}
