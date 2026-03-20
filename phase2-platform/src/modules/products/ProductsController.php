<?php
class ProductsController {
    public function index(): void {
        require_once SRC . '/lib/DataStore.php';

        $filter = $_GET['filter'] ?? '';
        $search = $_GET['search'] ?? '';
        $source = $_GET['source'] ?? '';

        $filters = [];
        if ($filter === 'not_uploaded') $filters['upload_status'] = 'NOT_UPLOADED';
        if ($search) $filters['search'] = $search;
        if ($source) $filters['source'] = $source;

        $products  = DataStore::getProducts($filters);
        $allSources= array_unique(array_column(DataStore::getProducts(), 'source'));
        sort($allSources);

        View::renderWithLayout('products/index', [
            'pageTitle'  => 'Продукти',
            'activePage' => 'products',
            'products'   => $products,
            'allSources' => $allSources,
            'filter'     => $filter,
            'search'     => $search,
            'source'     => $source,
            'total'      => count($products),
        ]);
    }

    public function search(): void {
        require_once SRC . '/lib/DataStore.php';
        $q = $_GET['q'] ?? '';
        $products = DataStore::getProducts(['search' => $q]);
        View::json(['products' => array_slice($products, 0, 50)]);
    }

    public function update(): void {
        require_once SRC . '/lib/DataStore.php';
        $id     = $_POST['id'] ?? '';
        $field  = $_POST['field'] ?? '';
        $value  = $_POST['value'] ?? '';

        // Validate allowed fields
        $allowed = ['asin_de', 'asin_fr', 'asin_it', 'asin_es', 'asin_nl', 'upload_status', 'notes'];
        if (!in_array($field, $allowed)) {
            View::json(['error' => 'Field not allowed'], 400);
            return;
        }

        // TODO: update in Google Sheets via Python script
        // For now: update local cache
        $products = DataStore::getProducts();
        foreach ($products as &$p) {
            if (($p['ean'] ?? '') === $id || ($p['our_sku'] ?? '') === $id) {
                $p[$field] = $value;
                break;
            }
        }
        DataStore::saveProductsCache($products);
        Logger::info("Product updated: {$id} {$field}={$value}");
        View::json(['success' => true]);
    }
}
