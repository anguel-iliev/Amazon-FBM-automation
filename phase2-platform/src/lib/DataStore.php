<?php
/**
 * DataStore — файлово базирано хранилище (JSON)
 * products.json structure (new format from parse_excel.py v2):
 *   { "products": [...], "columns": [...], "formula_templates": {...}, "meta": {...} }
 *
 * All product field keys are EXACT Excel column headers (Bulgarian).
 * Internal fields prefixed with "_" : _upload_status, _source.
 */
class DataStore {

    // ─────────────────────────────────────────────────────────────
    //  PRODUCTS JSON — load / save raw file
    // ─────────────────────────────────────────────────────────────

    /** Read the full products.json file structure */
    private static function readProductsFile() {
        $file = CACHE_DIR . '/products.json';
        if (!file_exists($file)) return ['products' => [], 'columns' => [], 'formula_templates' => [], 'meta' => []];
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) return ['products' => [], 'columns' => [], 'formula_templates' => [], 'meta' => []];
        // Support both old flat array and new nested format
        if (isset($data['products'])) return $data;
        // Old flat array — wrap it
        return ['products' => $data, 'columns' => [], 'formula_templates' => [], 'meta' => []];
    }

    private static function writeProductsFile($data) {
        file_put_contents(
            CACHE_DIR . '/products.json',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  PUBLIC PRODUCT API
    // ─────────────────────────────────────────────────────────────

    /** Returns flat array of products, optionally filtered */
    public static function getProducts($filters = []) {
        $data     = static::readProductsFile();
        $products = $data['products'] ?? [];

        // Source / sheet filter
        if (!empty($filters['source'])) {
            $src = $filters['source'];
            $products = array_values(array_filter($products, function($p) use ($src) {
                return ($p['_source'] ?? $p['Доставчик'] ?? '') === $src
                    || ($p['Доставчик'] ?? '') === $src;
            }));
        }
        // Upload status filter
        if (!empty($filters['upload_status'])) {
            $us = $filters['upload_status'];
            $products = array_values(array_filter($products, function($p) use ($us) {
                return ($p['_upload_status'] ?? 'NOT_UPLOADED') === $us;
            }));
        }
        // Brand filter
        if (!empty($filters['brand'])) {
            $br = $filters['brand'];
            $products = array_values(array_filter($products, function($p) use ($br) {
                return ($p['Бранд'] ?? '') === $br;
            }));
        }
        // Supplier filter
        if (!empty($filters['dostavchik'])) {
            $dv = $filters['dostavchik'];
            $products = array_values(array_filter($products, function($p) use ($dv) {
                return ($p['Доставчик'] ?? '') === $dv;
            }));
        }
        // Elektronika filter
        if (!empty($filters['elektronika'])) {
            $ek = $filters['elektronika'];
            $products = array_values(array_filter($products, function($p) use ($ek) {
                return ($p['Електоника'] ?? '') === $ek;
            }));
        }
        // Full-text search
        if (!empty($filters['search'])) {
            $q = mb_strtolower($filters['search']);
            $products = array_values(array_filter($products, function($p) use ($q) {
                $haystack = mb_strtolower(implode(' ', [
                    $p['Модел']          ?? '',
                    $p['Бранд']          ?? '',
                    $p['EAN Amazon']     ?? '',
                    $p['ASIN']           ?? '',
                    $p['Наше SKU']       ?? '',
                    $p['Доставчик']      ?? '',
                    $p['Коментар']       ?? '',
                ]));
                return strpos($haystack, $q) !== false;
            }));
        }
        return array_values($products);
    }

    /** Save a full flat products array back (preserves columns/formulas/meta) */
    public static function saveProductsCache($products) {
        $data             = static::readProductsFile();
        $data['products'] = array_values($products);
        static::writeProductsFile($data);
    }

    /** Update a single field on a product identified by EAN Amazon or Наше SKU */
    public static function updateProduct($ean, $field, $value) {
        $data     = static::readProductsFile();
        $products = &$data['products'];
        $updated  = false;
        foreach ($products as &$p) {
            if (($p['EAN Amazon'] ?? '') === $ean || ($p['Наше SKU'] ?? '') === $ean) {
                $p[$field] = $value;
                $updated   = true;
                break;
            }
        }
        unset($p);
        if ($updated) static::writeProductsFile($data);
        return $updated;
    }

    public static function getProductCount() {
        $products    = static::getProducts();
        $total       = count($products);
        $withAsin    = count(array_filter($products, function($p) { return !empty($p['ASIN']); }));
        $notUploaded = count(array_filter($products, function($p) { return ($p['_upload_status'] ?? 'NOT_UPLOADED') === 'NOT_UPLOADED'; }));
        $suppliers   = count(array_unique(array_filter(array_column($products, 'Доставчик'))));
        return compact('total', 'withAsin', 'notUploaded', 'suppliers');
    }

    /** Return distinct values for filter dropdowns */
    public static function getDistinctValues($field) {
        $products = static::getProducts();
        $vals = array_unique(array_filter(array_column($products, $field)));
        sort($vals);
        return array_values($vals);
    }

    // ─────────────────────────────────────────────────────────────
    //  COLUMNS & FORMULAS (from products.json)
    // ─────────────────────────────────────────────────────────────

    /** Returns ordered column list (exact Excel header names) */
    public static function getColumns() {
        $data = static::readProductsFile();
        return $data['columns'] ?? [];
    }

    /** Returns formula templates: column_header → formula string */
    public static function getFormulaTemplates() {
        $data = static::readProductsFile();
        return $data['formula_templates'] ?? [];
    }

    /** Save formula templates back into products.json */
    public static function saveFormulaTemplates($templates) {
        $data                     = static::readProductsFile();
        $data['formula_templates'] = $templates;
        static::writeProductsFile($data);
    }

    /** Add or remove a dynamic column from the columns list */
    public static function addColumn($colName) {
        $data = static::readProductsFile();
        $cols = $data['columns'] ?? [];
        if (!in_array($colName, $cols)) {
            $cols[]          = $colName;
            $data['columns'] = $cols;
            // Add empty value for all existing products
            foreach ($data['products'] as &$p) {
                if (!array_key_exists($colName, $p)) $p[$colName] = null;
            }
            unset($p);
            static::writeProductsFile($data);
        }
    }

    public static function removeColumn($colName) {
        $data = static::readProductsFile();
        $data['columns'] = array_values(array_filter($data['columns'] ?? [], function($c) use ($colName) {
            return $c !== $colName;
        }));
        foreach ($data['products'] as &$p) {
            unset($p[$colName]);
        }
        unset($p);
        // Also remove formula template if exists
        unset($data['formula_templates'][$colName]);
        static::writeProductsFile($data);
    }

    // ─────────────────────────────────────────────────────────────
    //  SUPPLIERS
    // ─────────────────────────────────────────────────────────────

    public static function getSuppliers() {
        $file = DATA_DIR . '/suppliers.json';
        if (!file_exists($file)) return static::seedSuppliers();
        return json_decode(file_get_contents($file), true) ?? [];
    }

    public static function saveSuppliers($suppliers) {
        file_put_contents(
            DATA_DIR . '/suppliers.json',
            json_encode(array_values($suppliers), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    private static function seedSuppliers() {
        $products  = static::getProducts();
        $sources   = array_unique(array_filter(array_column($products, 'Доставчик')));
        $suppliers = [];
        foreach ($sources as $src) {
            $suppliers[] = [
                'id'            => 'sup_' . md5($src),
                'name'          => $src,
                'email'         => '',
                'phone'         => '',
                'website'       => '',
                'currency'      => 'EUR',
                'payment_terms' => '',
                'min_order'     => 0,
                'notes'         => '',
                'active'        => true,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ];
        }
        usort($suppliers, function($a, $b) { return strcmp($a['name'], $b['name']); });
        static::saveSuppliers($suppliers);
        return $suppliers;
    }

    // ─────────────────────────────────────────────────────────────
    //  SYNC LOG
    // ─────────────────────────────────────────────────────────────

    public static function getSyncLog() {
        $file = DATA_DIR . '/sync_log.json';
        if (!file_exists($file)) return [];
        return json_decode(file_get_contents($file), true) ?? [];
    }

    public static function appendSyncLog($entry) {
        $log = static::getSyncLog();
        array_unshift($log, array_merge($entry, ['date' => date('Y-m-d H:i:s')]));
        $log = array_slice($log, 0, 100);
        file_put_contents(DATA_DIR . '/sync_log.json', json_encode($log, JSON_PRETTY_PRINT), LOCK_EX);
    }

    // ─────────────────────────────────────────────────────────────
    //  SETTINGS
    // ─────────────────────────────────────────────────────────────

    public static function getSettings() {
        $file = DATA_DIR . '/settings.json';
        if (!file_exists($file)) return static::defaultSettings();
        $saved    = json_decode(file_get_contents($file), true) ?? [];
        $defaults = static::defaultSettings();
        foreach ($defaults['marketplaces'] as $code => $def) {
            if (!isset($saved['marketplaces'][$code])) {
                $saved['marketplaces'][$code] = $def;
            }
        }
        return array_merge($defaults, $saved);
    }

    public static function saveSettings($settings) {
        file_put_contents(
            DATA_DIR . '/settings.json',
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private static function defaultSettings() {
        return [
            'marketplaces' => [
                'DE' => ['vat' => 0.19, 'amazon_fee' => 0.15, 'shipping' => 4.50, 'fbm_fee' => 1.00, 'min_margin' => 0.15, 'active' => true],
                'FR' => ['vat' => 0.20, 'amazon_fee' => 0.15, 'shipping' => 5.00, 'fbm_fee' => 1.00, 'min_margin' => 0.15, 'active' => true],
                'IT' => ['vat' => 0.22, 'amazon_fee' => 0.15, 'shipping' => 5.50, 'fbm_fee' => 1.00, 'min_margin' => 0.15, 'active' => true],
                'ES' => ['vat' => 0.21, 'amazon_fee' => 0.15, 'shipping' => 5.50, 'fbm_fee' => 1.00, 'min_margin' => 0.15, 'active' => true],
                'NL' => ['vat' => 0.21, 'amazon_fee' => 0.15, 'shipping' => 4.50, 'fbm_fee' => 1.00, 'min_margin' => 0.15, 'active' => true],
                'PL' => ['vat' => 0.23, 'amazon_fee' => 0.15, 'shipping' => 6.00, 'fbm_fee' => 1.00, 'min_margin' => 0.15, 'active' => false],
                'SE' => ['vat' => 0.25, 'amazon_fee' => 0.15, 'shipping' => 7.00, 'fbm_fee' => 1.00, 'min_margin' => 0.15, 'active' => false],
            ],
            'min_margin'      => 0.15,
            'sync_auto'       => true,
            'google_sheet_id' => '',
            'drive_folder_id' => '1Wch88u5tZApf5UOXzeH9AO7TETXGKYnT',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  SUMMARY (dashboard)
    // ─────────────────────────────────────────────────────────────

    public static function getSummary() {
        $counts   = static::getProductCount();
        $syncLog  = static::getSyncLog();
        $lastSync = !empty($syncLog[0]['date']) ? $syncLog[0]['date'] : null;
        return [
            'stats' => [
                'total_products' => $counts['total'],
                'with_asin'      => $counts['withAsin'],
                'not_uploaded'   => $counts['notUploaded'],
                'suppliers'      => $counts['suppliers'],
                'last_sync'      => $lastSync,
            ],
            'recent'  => array_slice(static::getProducts(), 0, 10),
            'syncLog' => $syncLog,
        ];
    }
}
