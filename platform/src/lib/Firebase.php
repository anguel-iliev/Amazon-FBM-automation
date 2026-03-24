<?php
/**
 * Firebase Realtime Database — единственото хранилище
 *
 * Структура в Firebase:
 *   /products/{ean}          — текущи продукти
 *   /archive/{date}/{ean}    — архиви преди импорт
 *   /meta/last_import        — мета данни
 *   /meta/stats              — статистика
 */
class Firebase {

    private static string $baseUrl  = '';
    private static string $secret   = '';
    private static bool   $ready    = false;

    // ── Init ─────────────────────────────────────────────────
    public static function init(): void {
        static::$baseUrl = rtrim(FIREBASE_DATABASE_URL, '/');
        static::$secret  = FIREBASE_SECRET;
        static::$ready   = !empty(static::$baseUrl) && !empty(static::$secret);
    }

    public static function isReady(): bool {
        if (!static::$ready) static::init();
        return static::$ready;
    }

    // ── Low-level HTTP ────────────────────────────────────────
    private static function request(string $method, string $path, $data = null): array {
        if (!static::isReady()) {
            return ['ok' => false, 'error' => 'Firebase not configured', 'data' => null];
        }

        $url  = static::$baseUrl . $path . '.json?auth=' . urlencode(static::$secret);
        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => "Content-Type: application/json\r\n",
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ];

        if ($data !== null) {
            $opts['http']['content'] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $ctx      = stream_context_create($opts);
        $body     = @file_get_contents($url, false, $ctx);
        $httpCode = 0;

        if (isset($http_response_header)) {
            preg_match('/HTTP\/\d\.\d (\d+)/', $http_response_header[0] ?? '', $m);
            $httpCode = (int)($m[1] ?? 0);
        }

        if ($body === false || ($httpCode >= 400)) {
            Logger::error("Firebase {$method} {$path} failed: HTTP {$httpCode}");
            return ['ok' => false, 'error' => "HTTP {$httpCode}", 'data' => null];
        }

        $decoded = json_decode($body, true);
        return ['ok' => true, 'data' => $decoded, 'error' => null];
    }

    public static function get(string $path)  { return static::request('GET',    $path); }
    public static function put(string $path, $data) { return static::request('PUT', $path, $data); }
    public static function patch(string $path, $data) { return static::request('PATCH', $path, $data); }
    public static function delete(string $path) { return static::request('DELETE', $path); }

    // ── Products ──────────────────────────────────────────────
    /**
     * Взима всички продукти като плосък масив.
     * Firebase връща обект {ean: {...}, ...} — конвертираме в масив.
     */
    public static function getProducts(array $filters = []): array {
        $res = static::get('/products');
        if (!$res['ok'] || !is_array($res['data'])) return [];

        $products = array_values($res['data']);

        // Filters
        if (!empty($filters['dostavchik'])) {
            $dv = $filters['dostavchik'];
            $products = array_values(array_filter($products, fn($p) => ($p['Доставчик'] ?? '') === $dv));
        }
        if (!empty($filters['brand'])) {
            $br = $filters['brand'];
            $products = array_values(array_filter($products, fn($p) => ($p['Бранд'] ?? '') === $br));
        }
        if (!empty($filters['upload_status'])) {
            $us = $filters['upload_status'];
            $products = array_values(array_filter($products, fn($p) => ($p['_upload_status'] ?? 'NOT_UPLOADED') === $us));
        }
        if (!empty($filters['elektronika'])) {
            $ek = $filters['elektronika'];
            $products = array_values(array_filter($products, fn($p) => ($p['Електоника'] ?? '') === $ek));
        }
        if (!empty($filters['search'])) {
            $q = mb_strtolower($filters['search']);
            $products = array_values(array_filter($products, function($p) use ($q) {
                return str_contains(mb_strtolower($p['Модел'] ?? ''), $q)
                    || str_contains(mb_strtolower($p['Бранд'] ?? ''), $q)
                    || str_contains(mb_strtolower($p['EAN Amazon'] ?? ''), $q)
                    || str_contains(mb_strtolower($p['ASIN'] ?? ''), $q)
                    || str_contains(mb_strtolower($p['Наше SKU'] ?? ''), $q)
                    || str_contains(mb_strtolower($p['Доставчик'] ?? ''), $q);
            }));
        }

        return $products;
    }

    /**
     * Записва всички продукти в Firebase.
     * Ключ = EAN Amazon (санитизиран за Firebase path).
     */
    public static function putProducts(array $products): bool {
        $data = [];
        foreach ($products as $p) {
            $ean = static::sanitizeKey($p['EAN Amazon'] ?? '');
            if ($ean === '') continue;
            $p['_upload_status'] = $p['_upload_status'] ?? 'NOT_UPLOADED';
            $data[$ean] = $p;
        }

        $res = static::put('/products', $data);
        return $res['ok'];
    }

    /**
     * Добавя само нови продукти (по EAN) — не изтрива съществуващите.
     */
    public static function mergeProducts(array $newProducts): array {
        // Вземи съществуващите
        $existing = [];
        $res = static::get('/products');
        if ($res['ok'] && is_array($res['data'])) {
            $existing = $res['data'];
        }

        $added   = 0;
        $skipped = 0;
        $patch   = [];

        foreach ($newProducts as $p) {
            $ean = static::sanitizeKey($p['EAN Amazon'] ?? '');
            if ($ean === '') continue;

            if (isset($existing[$ean])) {
                $skipped++;
            } else {
                $p['_upload_status'] = 'NOT_UPLOADED';
                $patch[$ean] = $p;
                $added++;
            }
        }

        if (!empty($patch)) {
            static::patch('/products', $patch);
        }

        return ['added' => $added, 'skipped' => $skipped, 'total' => count($existing) + $added];
    }

    /**
     * Обновява едно поле на един продукт по EAN.
     */
    public static function updateProduct(string $ean, string $field, $value): bool {
        $key = static::sanitizeKey($ean);
        $res = static::patch("/products/{$key}", [$field => $value]);
        return $res['ok'];
    }

    /**
     * Добавя нов продукт.
     */
    public static function addProduct(array $product): bool {
        $ean = static::sanitizeKey($product['EAN Amazon'] ?? '');
        if ($ean === '') return false;
        $product['_upload_status'] = $product['_upload_status'] ?? 'NOT_UPLOADED';
        $product['_created_at']    = date('c');
        $res = static::put("/products/{$ean}", $product);
        return $res['ok'];
    }

    // ── Stats ─────────────────────────────────────────────────
    public static function getStats(): array {
        $products = static::getProducts();
        $total       = count($products);
        $withAsin    = count(array_filter($products, fn($p) => !empty($p['ASIN'])));
        $notUploaded = count(array_filter($products, fn($p) => ($p['_upload_status'] ?? 'NOT_UPLOADED') === 'NOT_UPLOADED'));
        $suppliers   = count(array_unique(array_filter(array_column($products, 'Доставчик'))));

        // Avg Резултат
        $rez = array_filter(array_map(fn($p) => isset($p['Резултат']) && $p['Резултат'] !== '' ? (float)$p['Резултат'] : null, $products), fn($v) => $v !== null);
        $avgRez = count($rez) > 0 ? array_sum($rez) / count($rez) : 0;
        $posRez = count(array_filter($rez, fn($v) => $v > 0));
        $negRez = count(array_filter($rez, fn($v) => $v <= 0));

        return compact('total', 'withAsin', 'notUploaded', 'suppliers', 'avgRez', 'posRez', 'negRez');
    }

    public static function getDistinct(string $field): array {
        $products = static::getProducts();
        $vals = array_unique(array_filter(array_column($products, $field)));
        sort($vals);
        return array_values($vals);
    }

    // ── Archive ───────────────────────────────────────────────
    /**
     * Архивира текущите продукти преди замяна.
     * Записва под /archive/YYYY-MM-DD_HH-MM/
     */
    public static function archiveCurrent(string $label = ''): ?string {
        $res = static::get('/products');
        if (!$res['ok'] || empty($res['data'])) return null;

        $archiveKey = date('Y-m-d_H-i') . ($label ? '_' . static::sanitizeKey($label) : '');
        $meta = [
            'date'       => date('c'),
            'label'      => $label ?: date('d.m.Y H:i'),
            'count'      => count($res['data']),
            'products'   => $res['data'],
        ];

        static::put("/archive/{$archiveKey}", $meta);
        return $archiveKey;
    }

    /**
     * Листи всички архиви (без продуктите — само мета).
     */
    public static function listArchives(): array {
        $res = static::get('/archive');
        if (!$res['ok'] || !is_array($res['data'])) return [];

        $list = [];
        foreach ($res['data'] as $key => $val) {
            $list[] = [
                'key'   => $key,
                'date'  => $val['date']  ?? '',
                'label' => $val['label'] ?? $key,
                'count' => $val['count'] ?? 0,
            ];
        }
        // Sort newest first
        usort($list, fn($a, $b) => strcmp($b['key'], $a['key']));
        return $list;
    }

    /**
     * Зарежда архив като текущи продукти.
     */
    public static function restoreArchive(string $archiveKey): bool {
        $res = static::get("/archive/{$archiveKey}");
        if (!$res['ok'] || empty($res['data']['products'])) return false;

        $res2 = static::put('/products', $res['data']['products']);
        return $res2['ok'];
    }

    // ── Meta ──────────────────────────────────────────────────
    public static function saveMeta(string $key, $value): void {
        static::put("/meta/{$key}", $value);
    }

    public static function getMeta(string $key) {
        $res = static::get("/meta/{$key}");
        return $res['ok'] ? $res['data'] : null;
    }

    // ── Sync log ──────────────────────────────────────────────
    public static function appendLog(array $entry): void {
        $key = date('Ymd_His') . '_' . bin2hex(random_bytes(2));
        $entry['date'] = date('c');
        static::put("/logs/{$key}", $entry);
    }

    public static function getLogs(int $limit = 20): array {
        $res = static::get('/logs');
        if (!$res['ok'] || !is_array($res['data'])) return [];
        $logs = array_values($res['data']);
        usort($logs, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        return array_slice($logs, 0, $limit);
    }

    // ── Helpers ───────────────────────────────────────────────
    /**
     * Firebase ключовете не могат да съдържат: . $ # [ ] /
     */
    public static function sanitizeKey(string $key): string {
        return preg_replace('/[.\$#\[\]\/]/', '_', trim($key));
    }

    // ── Test connection ───────────────────────────────────────
    public static function testConnection(): array {
        $res = static::put('/meta/ping', ['time' => date('c'), 'from' => 'amz-retail']);
        return $res;
    }
}
