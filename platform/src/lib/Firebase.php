<?php
/**
 * Firebase Realtime Database — cURL based
 * ВАЖНО: cURL вместо file_get_contents() — работи на всички shared хостинги
 */
class Firebase {

    private static string $baseUrl = '';
    private static string $secret  = '';
    private static bool   $ready   = false;
    private static array  $cache   = []; // per-request cache

    public static function init(): void {
        static::$baseUrl = rtrim(FIREBASE_DATABASE_URL, '/');
        static::$secret  = FIREBASE_SECRET;
        static::$ready   = !empty(static::$baseUrl) && !empty(static::$secret);
    }

    public static function isReady(): bool {
        if (!static::$ready) static::init();
        return static::$ready;
    }

    // ── cURL HTTP core ────────────────────────────────────────
    private static function request(string $method, string $path, mixed $data = null): array {
        if (!static::isReady()) {
            return ['ok' => false, 'error' => 'Firebase not configured', 'data' => []];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL not available', 'data' => []];
        }

        $url = static::$baseUrl . $path . '.json?auth=' . urlencode(static::$secret);
        $ch  = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $data !== null
                ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
        ]);

        $body    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlErr) {
            Logger::error("Firebase cURL [{$method} {$path}]: {$curlErr}");
            return ['ok' => false, 'error' => "cURL: {$curlErr}", 'data' => []];
        }
        if ($code >= 400) {
            Logger::error("Firebase HTTP {$code} [{$method} {$path}]: " . substr($body, 0, 200));
            return ['ok' => false, 'error' => "HTTP {$code}", 'data' => []];
        }

        return ['ok' => true, 'data' => json_decode($body, true) ?? [], 'error' => null];
    }

    public static function get(string $path): array    { return static::request('GET',    $path); }
    public static function put(string $path, mixed $d): array { return static::request('PUT',  $path, $d); }
    public static function patch(string $path, mixed $d): array { return static::request('PATCH', $path, $d); }
    public static function delete(string $path): array { return static::request('DELETE', $path); }

    // ── Products — ONE call, cached ───────────────────────────
    public static function getAllProducts(): array {
        if (isset(static::$cache['products'])) {
            return static::$cache['products'];
        }
        $res = static::get('/products');
        $products = (is_array($res['data'] ?? null)) ? array_values($res['data']) : [];
        static::$cache['products'] = $products;
        return $products;
    }

    public static function getProducts(array $filters = []): array {
        $products = static::getAllProducts();

        if (!empty($filters['dostavchik'])) {
            $v = $filters['dostavchik'];
            $products = array_values(array_filter($products, fn($p) => ($p['Доставчик'] ?? '') === $v));
        }
        if (!empty($filters['brand'])) {
            $v = $filters['brand'];
            $products = array_values(array_filter($products, fn($p) => ($p['Бранд'] ?? '') === $v));
        }
        if (!empty($filters['upload_status'])) {
            $v = $filters['upload_status'];
            $products = array_values(array_filter($products, fn($p) => ($p['_upload_status'] ?? 'NOT_UPLOADED') === $v));
        }
        if (!empty($filters['elektronika'])) {
            $v = $filters['elektronika'];
            $products = array_values(array_filter($products, fn($p) => ($p['Електоника'] ?? '') === $v));
        }
        if (!empty($filters['search'])) {
            $q = mb_strtolower((string)$filters['search']);
            $products = array_values(array_filter($products, fn($p) =>
                str_contains(mb_strtolower((string)($p['Модел']      ?? '')), $q)
             || str_contains(mb_strtolower((string)($p['Бранд']      ?? '')), $q)
             || str_contains(mb_strtolower((string)($p['EAN Amazon'] ?? '')), $q)
             || str_contains(mb_strtolower((string)($p['ASIN']       ?? '')), $q)
             || str_contains(mb_strtolower((string)($p['Наше SKU']   ?? '')), $q)
             || str_contains(mb_strtolower((string)($p['Доставчик']  ?? '')), $q)
            ));
        }
        return $products;
    }

    public static function putProducts(array $products): bool {
        static::$cache = [];
        $data = [];
        foreach ($products as $p) {
            $ean = static::sanitizeKey((string)($p['EAN Amazon'] ?? ''));
            if ($ean === '') continue;
            $p['_upload_status'] = $p['_upload_status'] ?? 'NOT_UPLOADED';
            $data[$ean] = $p;
        }
        if (empty($data)) return false;
        return static::put('/products', $data)['ok'];
    }

    public static function mergeProducts(array $newProducts): array {
        static::$cache = [];
        $res      = static::get('/products');
        $existing = is_array($res['data'] ?? null) ? $res['data'] : [];
        $added    = 0; $skipped = 0; $patch = [];

        foreach ($newProducts as $p) {
            $ean = static::sanitizeKey((string)($p['EAN Amazon'] ?? ''));
            if ($ean === '') continue;
            if (isset($existing[$ean])) { $skipped++; }
            else { $p['_upload_status'] = 'NOT_UPLOADED'; $patch[$ean] = $p; $added++; }
        }
        if (!empty($patch)) static::patch('/products', $patch);
        return ['added' => $added, 'skipped' => $skipped, 'total' => count($existing) + $added];
    }

    public static function updateProduct(string $ean, string $field, mixed $value): bool {
        static::$cache = [];
        return static::patch('/products/' . static::sanitizeKey($ean), [$field => $value])['ok'];
    }

    public static function addProduct(array $product): bool {
        static::$cache = [];
        $ean = static::sanitizeKey((string)($product['EAN Amazon'] ?? ''));
        if ($ean === '') return false;
        $product['_upload_status'] = $product['_upload_status'] ?? 'NOT_UPLOADED';
        $product['_created_at']    = date('c');
        return static::put("/products/{$ean}", $product)['ok'];
    }

    // ── Stats — computed from cache (NO extra Firebase calls) ─
    public static function getStats(): array {
        $products    = static::getAllProducts();
        $total       = count($products);
        $withAsin    = 0; $notUploaded = 0;
        $supplierSet = []; $rezValues = [];

        foreach ($products as $p) {
            if (!empty($p['ASIN']))                                              $withAsin++;
            if (($p['_upload_status'] ?? 'NOT_UPLOADED') === 'NOT_UPLOADED')    $notUploaded++;
            if (!empty($p['Доставчик']))                                         $supplierSet[$p['Доставчик']] = 1;
            if (isset($p['Резултат']) && $p['Резултат'] !== '')                  $rezValues[] = (float)$p['Резултат'];
        }

        $avgRez = count($rezValues) > 0 ? array_sum($rezValues) / count($rezValues) : 0;

        return [
            'total'       => $total,
            'withAsin'    => $withAsin,
            'notUploaded' => $notUploaded,
            'suppliers'   => count($supplierSet),
            'avgRez'      => $avgRez,
            'posRez'      => count(array_filter($rezValues, fn($v) => $v > 0)),
            'negRez'      => count(array_filter($rezValues, fn($v) => $v <= 0)),
        ];
    }

    public static function getDistinct(string $field): array {
        $vals = array_unique(array_filter(array_column(static::getAllProducts(), $field)));
        sort($vals);
        return array_values($vals);
    }

    // ── Archive ───────────────────────────────────────────────
    public static function archiveCurrent(string $label = ''): ?string {
        $res = static::get('/products');
        if (!$res['ok'] || empty($res['data'])) return null;

        $key  = date('Y-m-d_H-i') . ($label ? '_' . static::sanitizeKey(mb_substr($label, 0, 30)) : '');
        static::put("/archive/{$key}", [
            'date'     => date('c'),
            'label'    => $label ?: date('d.m.Y H:i'),
            'count'    => count((array)$res['data']),
            'products' => $res['data'],
        ]);
        return $key;
    }

    public static function listArchives(): array {
        $res = static::get('/archive');
        if (!$res['ok'] || !is_array($res['data'])) return [];
        $list = [];
        foreach ((array)$res['data'] as $key => $val) {
            $list[] = ['key' => $key, 'date' => $val['date'] ?? '', 'label' => $val['label'] ?? $key, 'count' => (int)($val['count'] ?? 0)];
        }
        usort($list, fn($a, $b) => strcmp($b['key'], $a['key']));
        return $list;
    }

    public static function restoreArchive(string $key): bool {
        static::$cache = [];
        $res = static::get("/archive/{$key}");
        if (!$res['ok'] || empty($res['data']['products'])) return false;
        return static::put('/products', $res['data']['products'])['ok'];
    }

    // ── Logs ──────────────────────────────────────────────────
    public static function appendLog(array $entry): void {
        $entry['date'] = date('c');
        static::put('/logs/' . date('Ymd_His') . '_' . bin2hex(random_bytes(2)), $entry);
    }

    public static function getLogs(int $limit = 10): array {
        $res = static::get('/logs');
        if (!$res['ok'] || !is_array($res['data'])) return [];
        $logs = array_values((array)$res['data']);
        usort($logs, fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')));
        return array_slice($logs, 0, $limit);
    }

    // ── Test / Diagnostic ─────────────────────────────────────
    public static function testConnection(): array {
        return static::put('/meta/ping', ['time' => date('c')]);
    }

    public static function diagnostic(): array {
        return [
            'ready'     => static::isReady(),
            'curl'      => function_exists('curl_init'),
            'url_fopen' => ini_get('allow_url_fopen'),
            'base_url'  => !empty(static::$baseUrl),
            'secret'    => !empty(static::$secret),
        ];
    }

    // ── Key sanitizer ─────────────────────────────────────────
    public static function sanitizeKey(string $key): string {
        return preg_replace('/[.\$#\[\]\/\s]/', '_', trim($key));
    }
}
