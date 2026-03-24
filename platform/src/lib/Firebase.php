<?php
/**
 * Firebase Realtime Database — v2.4
 * Използва cURL (надежден на shared хостинг).
 * Пише на ПАРТИДИ по 100 продукта — избягва Firebase payload limit.
 * Кешира продуктите в паметта — само 1 HTTP заявка на PHP процес.
 */
class Firebase {

    private static string $baseUrl = '';
    private static string $secret  = '';
    private static bool   $ready   = false;
    private static ?array $cache   = null; // in-process cache for products

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

    private static function invalidateCache(): void { static::$cache = null; }

    // ── HTTP (cURL) ───────────────────────────────────────────
    private static function request(string $method, string $path, mixed $data = null): array {
        if (!static::isReady()) {
            return ['ok' => false, 'error' => 'Firebase not configured — провери .env файла', 'data' => null];
        }

        $url = static::$baseUrl . $path . '.json?auth=' . urlencode(static::$secret);
        $ch  = curl_init();

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
        ];

        if ($data !== null) {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $opts[CURLOPT_POSTFIELDS]    = $json;
            $opts[CURLOPT_HTTPHEADER][]  = 'Content-Length: ' . strlen($json);
        }

        curl_setopt_array($ch, $opts);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlErr) {
            Logger::error("Firebase {$method} {$path}: cURL error: {$curlErr}");
            return ['ok' => false, 'error' => "cURL error: {$curlErr}", 'data' => null];
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($body, true);
            $msg = $decoded['error'] ?? $body;
            Logger::error("Firebase {$method} {$path}: HTTP {$httpCode}: {$msg}");
            return ['ok' => false, 'error' => "Firebase HTTP {$httpCode}: {$msg}", 'data' => null];
        }

        return ['ok' => true, 'data' => json_decode($body, true), 'error' => null];
    }

    public static function get(string $path): array    { return static::request('GET',    $path); }
    public static function put(string $path, $d): array{ return static::request('PUT',    $path, $d); }
    public static function patch(string $path, $d): array{ return static::request('PATCH', $path, $d); }
    public static function delete(string $path): array { return static::request('DELETE', $path); }

    // ── Products — CACHED ────────────────────────────────────
    private static function loadProducts(): array {
        if (static::$cache !== null) return static::$cache;
        $res = static::get('/products');
        if (!$res['ok'] || !is_array($res['data'])) {
            static::$cache = [];
            return [];
        }
        static::$cache = array_values($res['data']);
        return static::$cache;
    }

    public static function getProducts(array $filters = []): array {
        $products = static::loadProducts();

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

    public static function getStats(): array {
        $all = static::loadProducts();
        $total = $withAsin = $notUploaded = 0;
        $supSet = []; $rez = [];

        foreach ($all as $p) {
            $total++;
            if (!empty($p['ASIN'])) $withAsin++;
            if (($p['_upload_status'] ?? 'NOT_UPLOADED') === 'NOT_UPLOADED') $notUploaded++;
            if (!empty($p['Доставчик'])) $supSet[$p['Доставчик']] = true;
            if (isset($p['Резултат']) && $p['Резултат'] !== '') $rez[] = (float)$p['Резултат'];
        }

        $suppliers = count($supSet);
        $avgRez = count($rez) > 0 ? array_sum($rez) / count($rez) : 0;
        $posRez = count(array_filter($rez, fn($v) => $v > 0));
        $negRez = count(array_filter($rez, fn($v) => $v <= 0));

        return compact('total', 'withAsin', 'notUploaded', 'suppliers', 'avgRez', 'posRez', 'negRez');
    }

    public static function getDistinct(string $field): array {
        $all  = static::loadProducts();
        $vals = array_unique(array_filter(array_column($all, $field)));
        sort($vals);
        return array_values($vals);
    }

    // ── Write ─────────────────────────────────────────────────

    /**
     * Замества ВСИЧКИ продукти. Пише на партиди по 100.
     * Връща ['ok'=>bool, 'error'=>string|null, 'written'=>int]
     */
    public static function putProducts(array $products): array {
        // Build sanitized map
        $data = [];
        foreach ($products as $p) {
            $ean = static::sanitizeKey($p['EAN Amazon'] ?? '');
            if ($ean === '') continue;
            $p['_upload_status'] = $p['_upload_status'] ?? 'NOT_UPLOADED';
            $data[$ean] = $p;
        }

        if (empty($data)) {
            return ['ok' => false, 'error' => 'Няма валидни продукти (всички EAN са празни)', 'written' => 0];
        }

        // DELETE existing
        $del = static::delete('/products');
        if (!$del['ok']) {
            // Non-fatal — continue anyway (null means no data, that's OK too)
            Logger::warn("Firebase DELETE /products failed: " . ($del['error'] ?? ''));
        }

        // PATCH in chunks of 100
        $chunks  = array_chunk($data, 100, true);
        $written = 0;

        foreach ($chunks as $i => $chunk) {
            $res = static::patch('/products', $chunk);
            if (!$res['ok']) {
                static::invalidateCache();
                return [
                    'ok'      => false,
                    'error'   => sprintf('Firebase грешка при запис на партида %d/%d (написани %d/%d): %s',
                        $i + 1, count($chunks), $written, count($data), $res['error'] ?? ''),
                    'written' => $written,
                ];
            }
            $written += count($chunk);
        }

        static::invalidateCache();
        return ['ok' => true, 'error' => null, 'written' => $written];
    }

    /**
     * Добавя само нови продукти (MERGE по EAN).
     */
    public static function mergeProducts(array $newProducts): array {
        $res      = static::get('/products');
        $existing = ($res['ok'] && is_array($res['data'])) ? $res['data'] : [];

        $added = 0; $skipped = 0; $patch = [];

        foreach ($newProducts as $p) {
            $ean = static::sanitizeKey($p['EAN Amazon'] ?? '');
            if ($ean === '') continue;
            if (isset($existing[$ean])) { $skipped++; }
            else { $p['_upload_status'] = 'NOT_UPLOADED'; $patch[$ean] = $p; $added++; }
        }

        if (!empty($patch)) {
            foreach (array_chunk($patch, 100, true) as $chunk) {
                $r = static::patch('/products', $chunk);
                if (!$r['ok']) {
                    static::invalidateCache();
                    return ['added' => $added, 'skipped' => $skipped,
                        'total' => count($existing) + $added,
                        'error' => 'Firebase PATCH грешка: ' . ($r['error'] ?? '')];
                }
            }
        }

        static::invalidateCache();
        return ['added' => $added, 'skipped' => $skipped,
            'total' => count($existing) + $added, 'error' => null];
    }

    public static function updateProduct(string $ean, string $field, mixed $value): bool {
        $key = static::sanitizeKey($ean);
        $res = static::patch("/products/{$key}", [$field => $value]);
        if ($res['ok']) static::invalidateCache();
        return $res['ok'];
    }

    public static function addProduct(array $product): bool {
        $ean = static::sanitizeKey($product['EAN Amazon'] ?? '');
        if ($ean === '') return false;
        $product['_upload_status'] = $product['_upload_status'] ?? 'NOT_UPLOADED';
        $product['_created_at']    = date('c');
        $res = static::put("/products/{$ean}", $product);
        if ($res['ok']) static::invalidateCache();
        return $res['ok'];
    }

    // ── Archive ───────────────────────────────────────────────
    public static function archiveCurrent(string $label = ''): ?string {
        $res = static::get('/products');
        if (!$res['ok'] || empty($res['data'])) return null;
        $key = date('Y-m-d_H-i') . ($label ? '_' . static::sanitizeKey(mb_substr($label, 0, 30)) : '');
        static::put("/archive/{$key}", [
            'date'     => date('c'),
            'label'    => $label ?: date('d.m.Y H:i'),
            'count'    => count($res['data']),
            'products' => $res['data'],
        ]);
        return $key;
    }

    public static function listArchives(): array {
        $res = static::get('/archive');
        if (!$res['ok'] || !is_array($res['data'])) return [];
        $list = [];
        foreach ($res['data'] as $key => $val) {
            $list[] = ['key' => $key, 'date' => $val['date'] ?? '', 'label' => $val['label'] ?? $key, 'count' => $val['count'] ?? 0];
        }
        usort($list, fn($a, $b) => strcmp($b['key'], $a['key']));
        return $list;
    }

    public static function restoreArchive(string $key): bool {
        $res = static::get("/archive/{$key}");
        if (!$res['ok'] || empty($res['data']['products'])) return false;
        $r = static::put('/products', $res['data']['products']);
        if ($r['ok']) static::invalidateCache();
        return $r['ok'];
    }

    // ── Logs ──────────────────────────────────────────────────
    public static function appendLog(array $entry): void {
        $key          = date('Ymd_His') . '_' . bin2hex(random_bytes(2));
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

    // ── Diagnostics ───────────────────────────────────────────
    public static function testConnection(): array {
        $res = static::put('/meta/ping', ['time' => date('c'), 'from' => 'amz-retail', 'version' => VERSION]);
        return [
            'ok'      => $res['ok'],
            'error'   => $res['error'] ?? null,
            'curl'    => function_exists('curl_init'),
            'fopen'   => (bool)ini_get('allow_url_fopen'),
            'db_url'  => static::$baseUrl,
            'secret'  => substr(FIREBASE_SECRET, 0, 6) . '...' . substr(FIREBASE_SECRET, -4),
        ];
    }

    public static function sanitizeKey(string $key): string {
        return preg_replace('/[.\$#\[\]\/\s]/', '_', trim($key));
    }
}
