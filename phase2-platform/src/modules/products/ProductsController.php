<?php
class ProductsController {

    private const PER_PAGE = 50;

    public function index(): void {
        require_once SRC . '/lib/DataStore.php';

        $filter  = $_GET['filter']  ?? '';
        $search  = trim($_GET['search'] ?? '');
        $source  = $_GET['source']  ?? '';
        $status  = $_GET['status']  ?? '';
        $page    = max(1, (int)($_GET['page'] ?? 1));

        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($source) $filters['source'] = $source;
        if ($status) $filters['upload_status'] = $status;
        if ($filter === 'not_uploaded') $filters['upload_status'] = 'NOT_UPLOADED';

        $allProducts = DataStore::getProducts($filters);
        $total       = count($allProducts);
        $totalPages  = max(1, (int)ceil($total / self::PER_PAGE));
        $page        = min($page, $totalPages);
        $offset      = ($page - 1) * self::PER_PAGE;
        $products    = array_slice($allProducts, $offset, self::PER_PAGE);

        $allSources = array_unique(array_filter(array_column(DataStore::getProducts(), 'source')));
        sort($allSources);

        View::renderWithLayout('products/index', [
            'pageTitle'  => 'Продукти',
            'activePage' => 'products',
            'products'   => $products,
            'allSources' => $allSources,
            'filter'     => $filter,
            'search'     => $search,
            'source'     => $source,
            'status'     => $status,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => self::PER_PAGE,
        ]);
    }

    public function search(): void {
        require_once SRC . '/lib/DataStore.php';
        $q = $_GET['q'] ?? '';
        $products = DataStore::getProducts(['search' => $q]);
        View::json(['products' => array_slice($products, 0, 50), 'total' => count($products)]);
    }

    public function update(): void {
        require_once SRC . '/lib/DataStore.php';

        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $id    = $body['id']    ?? ($_POST['id']    ?? '');
        $field = $body['field'] ?? ($_POST['field'] ?? '');
        $value = $body['value'] ?? ($_POST['value'] ?? '');

        $allowed = ['asin_de','asin_fr','asin_it','asin_es','asin_nl','upload_status','notes'];
        if (!in_array($field, $allowed)) {
            View::json(['error' => 'Field not allowed'], 400);
            return;
        }

        $products = DataStore::getProducts();
        $found = false;
        foreach ($products as &$p) {
            if (($p['ean'] ?? '') === $id || ($p['our_sku'] ?? '') === $id) {
                $p[$field] = $value; $found = true; break;
            }
        }
        unset($p);

        if (!$found) { View::json(['error' => 'Product not found'], 404); return; }

        DataStore::saveProductsCache($products);
        Logger::info("Product updated: {$id} {$field}={$value}");
        View::json(['success' => true]);
    }

    public function export(): void {
        Auth::requireLogin();
        require_once SRC . '/lib/DataStore.php';

        $filters = [];
        if (!empty($_GET['search'])) $filters['search']        = $_GET['search'];
        if (!empty($_GET['source'])) $filters['source']        = $_GET['source'];
        if (!empty($_GET['status'])) $filters['upload_status'] = $_GET['status'];

        $products = DataStore::getProducts($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="products_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

        fputcsv($out, ['EAN','SKU','Продукт','Доставчик','Цена €','ASIN DE','Цена DE €','Статус'], ';');
        foreach ($products as $p) {
            fputcsv($out, [
                $p['ean']              ?? '',
                $p['our_sku']          ?? '',
                $p['product_name']     ?? '',
                $p['source']           ?? '',
                number_format((float)($p['supplier_price'] ?? 0), 2, '.', ''),
                $p['asin_de']          ?? '',
                number_format((float)($p['final_price_de'] ?? 0), 2, '.', ''),
                $p['upload_status']    ?? 'NOT_UPLOADED',
            ], ';');
        }
        fclose($out);
        exit;
    }

    public function debug(): void {
        Auth::requireLogin(true);
        require_once SRC . '/lib/DataStore.php';

        $cacheFile = CACHE_DIR . '/products.json';
        $products  = DataStore::getProducts();
        $sample    = array_slice($products, 0, 2);

        View::json([
            'version'       => VERSION,
            'cache_file'    => $cacheFile,
            'cache_exists'  => file_exists($cacheFile),
            'cache_size_kb' => file_exists($cacheFile) ? round(filesize($cacheFile)/1024, 1) : 0,
            'product_count' => count($products),
            'cache_dir'     => CACHE_DIR,
            'data_dir'      => DATA_DIR,
            'root'          => ROOT,
            'sample'        => $sample,
        ]);
    }
}
