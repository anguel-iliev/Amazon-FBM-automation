<?php
class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFE,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void {
        static::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed {
        static::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function destroy(): void {
        static::start();
        session_destroy();
        setcookie(SESSION_NAME, '', time() - 3600, '/');
    }

    public static function flash(string $key, string $message): void {
        static::set('flash_' . $key, $message);
    }

    public static function getFlash(string $key): ?string {
        $msg = static::get('flash_' . $key);
        if ($msg) unset($_SESSION['flash_' . $key]);
        return $msg;
    }
}
