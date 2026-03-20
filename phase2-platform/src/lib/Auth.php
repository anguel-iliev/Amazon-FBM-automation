<?php
class Auth {
    public static function login(string $username, string $password): bool {
        Session::start();
        if ($username === AUTH_USER && password_verify($password, AUTH_PASSWORD)) {
            Session::set('logged_in', true);
            Session::set('user',      $username);
            Session::set('login_at',  time());
            return true;
        }
        return false;
    }

    public static function logout(): void {
        Session::destroy();
    }

    public static function isLoggedIn(): bool {
        Session::start();
        return Session::get('logged_in') === true;
    }

    public static function requireLogin(bool $jsonResponse = false): void {
        if (!static::isLoggedIn()) {
            if ($jsonResponse) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
            header('Location: /');
            exit;
        }
    }

    public static function user(): ?string {
        return Session::get('user');
    }

    /** Генерира bcrypt hash за .env файла */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
