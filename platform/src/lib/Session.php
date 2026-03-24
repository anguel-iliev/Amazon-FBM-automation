<?php
class Session {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            // samesite support varies — use array form (PHP 7.3+)
            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => SESSION_LIFE,
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                session_set_cookie_params(SESSION_LIFE, '/', '', true, true);
            }
            session_start();
        }
    }

    public static function set($key, $value) {
        static::start();
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        static::start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    public static function destroy() {
        static::start();
        session_destroy();
        setcookie(SESSION_NAME, '', time() - 3600, '/');
    }

    public static function flash($key, $message) {
        static::set('flash_' . $key, $message);
    }

    public static function getFlash($key) {
        $val = static::get('flash_' . $key);
        if ($val !== null) unset($_SESSION['flash_' . $key]);
        return $val;
    }
}
