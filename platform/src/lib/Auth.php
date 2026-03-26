<?php
class Auth {

    public static function login(string $email, string $password): bool {
        require_once SRC . '/lib/UserStore.php';
        Session::start();

        $result = UserStore::authenticate($email, $password);
        if (!$result['ok']) return false;

        $user = $result['user'];
        // Regenerate session ID on login — prevents session fixation
        session_regenerate_id(true);

        Session::set('logged_in',  true);
        Session::set('user',       $user['email']);
        Session::set('user_id',    $user['id']);
        Session::set('user_role',  $user['role'] ?? 'user');
        Session::set('login_at',   time());
        Session::set('user_agent', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100));
        return true;
    }

    public static function logout(): void {
        Session::start();
        session_regenerate_id(true);
        Session::destroy();
        $_SESSION = [];
    }

    public static function isLoggedIn(): bool {
        Session::start();

        // Must have all three markers
        if (Session::get('logged_in') !== true)   return false;
        if (empty(Session::get('user')))           return false;
        if (empty(Session::get('login_at')))       return false;

        // Session lifetime: 7 days
        if (time() - (int)Session::get('login_at') > SESSION_LIFE) {
            static::logout();
            return false;
        }

        return true;
    }

    public static function isAdmin(): bool {
        return static::isLoggedIn() && Session::get('user_role') === 'admin';
    }

    /**
     * Enforce login. Exits if not logged in.
     * $jsonResponse = true → return 401 JSON (for AJAX/API routes)
     * $jsonResponse = false → redirect to login page
     */
    public static function requireLogin(bool $jsonResponse = false): void {
        if (static::isLoggedIn()) return; // already logged in — continue

        if ($jsonResponse) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode([
                'error'    => 'Unauthorized — моля влезте в системата',
                'redirect' => '/',
            ]);
            exit;
        }

        // HTML page — redirect to login
        http_response_code(302);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Location: /');
        exit;
    }

    public static function requireAdmin(): void {
        static::requireLogin();
        if (!static::isAdmin()) {
            http_response_code(403);
            View::render('errors/404');
            exit;
        }
    }

    public static function user(): ?string  { return Session::get('user'); }
    public static function userId(): ?string { return Session::get('user_id'); }
    public static function role(): string    { return Session::get('user_role') ?? 'user'; }
}
