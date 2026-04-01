<?php
class Security {
    private const CSRF_KEY = '_csrf_token';
    private const RATE_FILE = DATA_DIR . '/rate_limits.json';

    public static function csrfToken(): string {
        Session::start();
        $token = Session::get(self::CSRF_KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::CSRF_KEY, $token);
        }
        return $token;
    }

    public static function csrfField(): string {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function csrfTokenForJs(): string {
        return self::csrfToken();
    }

    public static function validateCsrf(?string $token = null, bool $allowSameOriginFallback = false): bool {
        Session::start();
        $sessionToken = Session::get(self::CSRF_KEY);
        if (!is_string($sessionToken) || $sessionToken === '') {
            if ($allowSameOriginFallback && self::isSameOriginRequest()) {
                return true;
            }
            return false;
        }
        if ($token === null || $token === '') {
            $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }
        if (is_string($token) && hash_equals($sessionToken, $token)) {
            return true;
        }
        if ($allowSameOriginFallback && self::isSameOriginRequest()) {
            return true;
        }
        return false;
    }

    public static function requireCsrf(bool $jsonResponse = false, bool $allowSameOriginFallback = false): void {
        if (self::validateCsrf(null, $allowSameOriginFallback)) return;
        Logger::warn('CSRF blocked: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNK') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . ' ip=' . self::clientIp());
        if ($jsonResponse) {
            View::json(['success' => false, 'error' => 'Невалиден CSRF token. Презареди страницата и опитай отново.'], 419);
        }
        http_response_code(419);
        echo 'CSRF validation failed. Моля презаредете страницата.';
        exit;
    }


    public static function isSameOriginRequest(): bool {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') return false;

        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
            $value = $_SERVER[$header] ?? '';
            if (!$value) continue;
            $parts = parse_url($value);
            if (!is_array($parts)) continue;
            $originHost = $parts['host'] ?? '';
            $originScheme = $parts['scheme'] ?? '';
            $currentScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            if ($originHost === $host && ($originScheme === '' || $originScheme === $currentScheme)) {
                return true;
            }
        }

        return false;
    }

    public static function sendNoCacheHeaders(): void {
        header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public static function sendLogoutHeaders(): void {
        self::sendNoCacheHeaders();
        header('Clear-Site-Data: "cache"', false);
    }

    public static function enforceRateLimit(string $bucket, string $key, int $maxAttempts, int $windowSeconds): array {
        $data = self::readRateFile();
        $now = time();
        $bucketKey = self::rateLimitBucketKey($bucket, $key);
        $entry = $data[$bucketKey] ?? ['count' => 0, 'reset_at' => $now + $windowSeconds];

        if (($entry['reset_at'] ?? 0) <= $now) {
            $entry = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }

        if (($entry['count'] ?? 0) >= $maxAttempts) {
            return ['allowed' => false, 'retry_after' => max(1, (int)$entry['reset_at'] - $now), 'remaining' => 0];
        }

        $entry['count'] = ((int)($entry['count'] ?? 0)) + 1;
        $data[$bucketKey] = $entry;
        self::writeRateFile($data);

        return ['allowed' => true, 'retry_after' => 0, 'remaining' => max(0, $maxAttempts - (int)$entry['count'])];
    }

    public static function clearRateLimit(string $bucket, string $key): void {
        $data = self::readRateFile();
        $bucketKey = self::rateLimitBucketKey($bucket, $key);
        if (isset($data[$bucketKey])) {
            unset($data[$bucketKey]);
            self::writeRateFile($data);
        }
    }

    public static function clientIp(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = $_SERVER[$key] ?? '';
            if (!$value) continue;
            $parts = array_map('trim', explode(',', $value));
            if (!empty($parts[0])) return substr($parts[0], 0, 64);
        }
        return 'unknown';
    }

    private static function rateLimitBucketKey(string $bucket, string $key): string {
        return $bucket . ':' . hash('sha256', strtolower(trim($key)));
    }

    private static function readRateFile(): array {
        $file = self::RATE_FILE;
        if (!file_exists($file)) return [];
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) return [];
        $now = time();
        foreach ($data as $k => $entry) {
            if (($entry['reset_at'] ?? 0) <= $now) unset($data[$k]);
        }
        return $data;
    }

    private static function writeRateFile(array $data): void {
        file_put_contents(self::RATE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
