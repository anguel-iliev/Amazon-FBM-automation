<?php
class Auth {

    public static function login($email, $password) {
        require_once SRC . '/lib/UserStore.php';
        Session::start();

        $result = UserStore::authenticate($email, $password);
        if (!$result['ok']) return false;

        $user = $result['user'];
        Session::set('logged_in', true);
        Session::set('user',      $user['email']);
        Session::set('user_id',   $user['id']);
        Session::set('user_role', $user['role'] ?? 'user');
        Session::set('login_at',  time());
        return true;
    }

    public static function logout() {
        Session::destroy();
    }

    public static function isLoggedIn() {
        Session::start();
        return Session::get('logged_in') === true;
    }

    /** Alias for isLoggedIn() — used in ApiController */
    public static function check() {
        return static::isLoggedIn();
    }

    public static function isAdmin() {
        return Session::get('user_role') === 'admin';
    }

    public static function requireLogin($jsonResponse = false) {
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

    public static function requireAdmin() {
        static::requireLogin();
        if (!static::isAdmin()) {
            http_response_code(403);
            View::render('errors/404');
            exit;
        }
    }

    public static function user() {
        return Session::get('user');
    }

    public static function userId() {
        return Session::get('user_id');
    }
}
