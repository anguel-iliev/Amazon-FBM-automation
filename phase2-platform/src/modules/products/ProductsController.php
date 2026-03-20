<?php
class ProductsController {
    public function index() {
        require_once SRC . '/lib/DataStore.php';

        $filter = $_GET['filter'] ?? '';
        $search = $_GET['search'] ?? '';
        $source = $_GET['source'] ?? '';
        $brand  = $_GET['brand']  ?? '';

        $filters = [];
        if ($filter === 'not_uploaded') $filters['upload_status'] = 'NOT_UPLOADED';
        if ($filter === 'uploaded')     $filters['upload_status'] = 'UPLOADED';
        if ($search) $filters['search'] = $search;
        if ($source) $filters['source'] = $source;
        if ($brand)  $filters['brand']  = $brand;

        $all        = DataStore::getProducts();
        $products   = DataStore::getProducts($filters);

        // Unique suppliers and brands from full dataset
        $allSources = array_unique(array_filter(array_column($all, 'source')));
        sort($allSources);
        $allBrands  = array_unique(array_filter(array_column($all, 'brand')));
        sort($allBrands);

        View::renderWithLayout('products/index', [
            'pageTitle'  => 'Продукти',
            'activePage' => 'products',
            'products'   => $products,
            'allSources' => $allSources,
            'allBrands'  => $allBrands,
            'filter'     => $filter,
            'search'     => $search,
            'source'     => $source,
            'brand'      => $brand,
            'total'      => count($products),
        ]);
    }

    public function search() {
        require_once SRC . '/lib/DataStore.php';
        $q        = $_GET['q'] ?? '';
        $products = DataStore::getProducts(['search' => $q]);
        View::json(['products' => array_slice($products, 0, 50)]);
    }

    public function update() {
        require_once SRC . '/lib/DataStore.php';
        $id    = $_POST['id']    ?? '';
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';

        $allowed = [
            'asin_de','asin_fr','asin_it','asin_es','asin_nl',
            'upload_status','notes','new_price','selling_price',
            'supplier_price','delivered','next_order','dm_price',
            'price_es_fr_it','source_catalog_nr'
        ];

        if (!in_array($field, $allowed)) {
            View::json(['error' => 'Field not allowed'], 400);
            return;
        }

        $products = DataStore::getProducts();
        $updated  = false;
        foreach ($products as &$p) {
            if (($p['ean'] ?? '') === $id || ($p['our_sku'] ?? '') === $id) {
                $p[$field] = $value;
                $updated   = true;
                break;
            }
        }
        unset($p);

        if ($updated) {
            DataStore::saveProductsCache($products);
            Logger::info("Product updated: {$id} {$field}={$value}");
        }
        View::json(['success' => $updated]);
    }
}
