<?php
/**
 * FirebaseDB — REST-based Firebase Realtime Database client
 * Uses the Firebase REST API with an OAuth2 / legacy token.
 *
 * Project ID  : amz-retail
 * Project No  : 820571488028
 * DB URL      : https://amz-retail-default-rtdb.europe-west1.firebasedatabase.app
 */
class FirebaseDB {

    // ── Config ───────────────────────────────────────────────────
    const PROJECT_ID   = 'amz-retail';
    const DB_URL       = 'https://amz-retail-default-rtdb.europe-west1.firebasedatabase.app';
    // Token is stored in data/settings.json under 'firebase_token' key
    // Configure it via Settings → Integrations in the web UI

    // ── Internal helpers ─────────────────────────────────────────

    private static function getToken(): string {
        // Read from settings.json (set via Settings → Integrations)
        $settings = DataStore::getSettings();
        return $settings['firebase_token'] ?? '';
    }

    private static function getDbUrl(): string {
        $settings = DataStore::getSettings();
        return rtrim($settings['firebase_db_url'] ?? self::DB_URL, '/');
    }

    /**
     * Execute a Firebase REST request
     * @param string $method   GET|PUT|PATCH|DELETE
     * @param string $path     e.g. "/products/12345"
     * @param mixed  $payload  PHP array to encode as JSON (for PUT/PATCH)
     * @return array ['ok' => bool, 'data' => mixed, 'code' => int, 'error' => string]
     */
    public static function request(string $method, string $path, $payload = null): array {
        $url   = self::getDbUrl() . $path . '.json?auth=' . self::getToken();
        $ch    = curl_init($url);
        $opts  = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
        ];
        if ($payload !== null) {
            $json          = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $opts[CURLOPT_POSTFIELDS]  = $json;
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) return ['ok' => false, 'data' => null, 'code' => 0, 'error' => $curlErr];

        $decoded = json_decode($response, true);
        $ok      = ($httpCode >= 200 && $httpCode < 300);

        if (!$ok) {
            $msg = is_array($decoded) ? ($decoded['error'] ?? $response) : $response;
            return ['ok' => false, 'data' => null, 'code' => $httpCode, 'error' => $msg];
        }
        return ['ok' => true, 'data' => $decoded, 'code' => $httpCode, 'error' => ''];
    }

    // ── High-level methods ───────────────────────────────────────

    /** Test connection — returns ['ok', 'latency_ms', 'error', 'hint'] */
    public static function testConnection(): array {
        $start  = microtime(true);
        $result = self::request('GET', '/.info/serverTimeOffset');
        $ms     = round((microtime(true) - $start) * 1000);

        $hint = '';
        if (!$result['ok']) {
            if ($result['code'] === 404) {
                $hint = 'Базата данни все още не е създадена. Влез в Firebase Console → Build → Realtime Database → Create Database.';
            } elseif ($result['code'] === 401 || $result['code'] === 403) {
                $hint = 'Грешен токен или правилата на базата не позволяват достъп.';
            } elseif ($result['code'] === 0) {
                $hint = 'Мрежова грешка — провери интернет връзката или DNS.';
            }
        }

        return [
            'ok'         => $result['ok'],
            'latency_ms' => $ms,
            'error'      => $result['error'],
            'code'       => $result['code'],
            'hint'       => $hint,
        ];
    }

    /** Read a path */
    public static function get(string $path) {
        return self::request('GET', $path);
    }

    /** Write (overwrite) a path */
    public static function set(string $path, $data): array {
        return self::request('PUT', $path, $data);
    }

    /** Merge (partial update) a path */
    public static function update(string $path, array $data): array {
        return self::request('PATCH', $path, $data);
    }

    /** Delete a path */
    public static function delete(string $path): array {
        return self::request('DELETE', $path);
    }

    // ── Products ─────────────────────────────────────────────────

    /**
     * Push all products to Firebase in batches of 100.
     * Products are keyed by a sanitized EAN or SKU.
     * Returns ['ok', 'synced', 'errors', 'total']
     */
    public static function syncAllProducts(array $products): array {
        $synced = 0;
        $errors = [];
        $batch  = [];

        foreach ($products as $p) {
            $key   = self::makeProductKey($p);
            $clean = self::sanitizeForFirebase($p);
            $batch[$key] = $clean;

            if (count($batch) >= 100) {
                $r = self::request('PATCH', '/products', $batch);
                if ($r['ok']) {
                    $synced += count($batch);
                } else {
                    $errors[] = $r['error'];
                }
                $batch = [];
            }
        }
        // Remaining
        if (!empty($batch)) {
            $r = self::request('PATCH', '/products', $batch);
            if ($r['ok']) {
                $synced += count($batch);
            } else {
                $errors[] = $r['error'];
            }
        }
        return [
            'ok'     => empty($errors),
            'synced' => $synced,
            'errors' => $errors,
            'total'  => count($products),
        ];
    }

    /** Update a single product field */
    public static function updateProduct(string $key, string $field, $value): array {
        return self::update('/products/' . $key, [$field => $value]);
    }

    /** Get all products from Firebase */
    public static function getProducts(): array {
        $r = self::get('/products');
        if (!$r['ok'] || !is_array($r['data'])) return [];
        return array_values($r['data']);
    }

    // ── Settings & Meta ──────────────────────────────────────────

    /** Push settings to Firebase */
    public static function syncSettings(array $settings): array {
        $clean = self::sanitizeForFirebase($settings);
        return self::set('/settings', $clean);
    }

    /** Push suppliers to Firebase */
    public static function syncSuppliers(array $suppliers): array {
        $indexed = [];
        foreach ($suppliers as $s) {
            $key = 'sup_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($s['name'] ?? 'unknown'));
            $indexed[$key] = self::sanitizeForFirebase($s);
        }
        return self::set('/suppliers', $indexed);
    }

    // ── Helpers ──────────────────────────────────────────────────

    /** Create a Firebase-safe key from product EAN/SKU */
    public static function makeProductKey(array $product): string {
        $raw = $product['EAN Amazon'] ?? $product['Наше SKU'] ?? $product['EAN Доставчик'] ?? '';
        if (empty($raw)) $raw = 'p_' . md5(json_encode($product));
        // Firebase keys cannot contain: . # $ [ ] /
        return preg_replace('/[.#$\[\]\/]/', '_', (string)$raw);
    }

    /**
     * Firebase does not allow null values or keys with special chars.
     * Recursively sanitize an array.
     */
    public static function sanitizeForFirebase($data) {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $safeKey = preg_replace('/[.#$\[\]\/]/', '_', (string)$k);
                $out[$safeKey] = self::sanitizeForFirebase($v);
            }
            return $out;
        }
        if ($data === null) return '';
        if (is_bool($data)) return $data ? 'yes' : 'no';
        return $data;
    }
}
