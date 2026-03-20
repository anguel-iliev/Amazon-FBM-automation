<?php
/**
 * DataStore — файлово базирано хранилище (JSON)
 */
class DataStore {

    public static function getProducts($filters = []) {
        $file = CACHE_DIR . '/products.json';
        if (!file_exists($file)) return [];

        $products = json_decode(file_get_contents($file), true) ?? [];

        if (!empty($filters['source'])) {
            $src = $filters['source'];
            $products = array_filter($products, function($p) use ($src) {
                return ($p['source'] ?? '') === $src;
            });
        }
        if (!empty($filters['upload_status'])) {
            $us = $filters['upload_status'];
            $products = array_filter($products, function($p) use ($us) {
                return ($p['upload_status'] ?? '') === $us;
            });
        }
        if (!empty($filters['search'])) {
            $q = strtolower($filters['search']);
            $products = array_filter($products, function($p) use ($q) {
                return strpos(strtolower($p['product_name'] ?? ''), $q) !== false ||
                       strpos(strtolower($p['ean'] ?? ''), $q) !== false ||
                       strpos(strtolower($p['asin_de'] ?? ''), $q) !== false;
            });
        }

        return array_values($products);
    }

    public static function saveProductsCache($products) {
        file_put_contents(
            CACHE_DIR . '/products.json',
            json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    public static function getProductCount() {
        $products    = static::getProducts();
        $total       = count($products);
        $withAsin    = count(array_filter($products, function($p) { return !empty($p['asin_de']); }));
        $notUploaded = count(array_filter($products, function($p) { return ($p['upload_status'] ?? '') === 'NOT_UPLOADED'; }));
        $suppliers   = count(array_unique(array_column($products, 'source')));
        return compact('total', 'withAsin', 'notUploaded', 'suppliers');
    }

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

    public static function getSettings() {
        $file = DATA_DIR . '/settings.json';
        if (!file_exists($file)) return static::defaultSettings();
        return array_merge(static::defaultSettings(), json_decode(file_get_contents($file), true) ?? []);
    }

    public static function saveSettings($settings) {
        file_put_contents(DATA_DIR . '/settings.json', json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private static function defaultSettings() {
        return [
            'marketplaces' => [
                'DE' => ['vat' => 0.19, 'amazon_fee' => 0.15, 'shipping' => 4.50, 'fbm_fee' => 1.00, 'active' => true],
                'FR' => ['vat' => 0.20, 'amazon_fee' => 0.15, 'shipping' => 5.00, 'fbm_fee' => 1.00, 'active' => true],
                'IT' => ['vat' => 0.22, 'amazon_fee' => 0.15, 'shipping' => 5.50, 'fbm_fee' => 1.00, 'active' => true],
                'ES' => ['vat' => 0.21, 'amazon_fee' => 0.15, 'shipping' => 5.50, 'fbm_fee' => 1.00, 'active' => true],
                'NL' => ['vat' => 0.21, 'amazon_fee' => 0.15, 'shipping' => 4.50, 'fbm_fee' => 1.00, 'active' => true],
                'PL' => ['vat' => 0.23, 'amazon_fee' => 0.15, 'shipping' => 6.00, 'fbm_fee' => 1.00, 'active' => false],
                'SE' => ['vat' => 0.25, 'amazon_fee' => 0.15, 'shipping' => 7.00, 'fbm_fee' => 1.00, 'active' => false],
            ],
            'min_margin'      => 0.15,
            'sync_auto'       => true,
            'google_sheet_id' => '',
            'drive_folder_id' => '1Wch88u5tZApf5UOXzeH9AO7TETXGKYnT',
        ];
    }

    public static function getSummary() {
        $counts  = static::getProductCount();
        $syncLog = static::getSyncLog();
        $lastSync= $syncLog[0]['date'] ?? null;

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
