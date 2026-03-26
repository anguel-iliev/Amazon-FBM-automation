<?php
class Session {

    public static function start(): void {
        if (session_status() !== PHP_SESSION_NONE) return;

        session_name(SESSION_NAME);

        // Set cookie params BEFORE session_start
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFE,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(SESSION_LIFE, '/', '', true, true);
        }

        session_start();
    }

    public static function set(string $key, $value): void {
        static::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null) {
        static::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function destroy(): void {
        static::start();
        // Clear all session data
        $_SESSION = [];
        session_unset();
        session_destroy();

        // Clear session cookie with ALL the same attributes used to set it
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if (PHP_VERSION_ID >= 70300) {
            setcookie(SESSION_NAME, '', [
                'expires'  => time() - 86400,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie(SESSION_NAME, '', time() - 86400, '/; SameSite=Lax', '', $isHttps, true);
        }
    }

    public static function flash(string $key, string $message): void {
        static::set('flash_' . $key, $message);
    }

    public static function getFlash(string $key): ?string {
        $val = static::get('flash_' . $key);
        if ($val !== null) unset($_SESSION['flash_' . $key]);
        return $val;
    }
}
