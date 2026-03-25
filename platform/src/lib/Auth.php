<?php
class Auth {

    public static function login(string $email, string $password): bool {
        require_once SRC . '/lib/UserStore.php';
        Session::start();

        $result = UserStore::authenticate($email, $password);
        if (!$result['ok']) return false;

        $user = $result['user'];
        // Regenerate session ID on login (prevents session fixation attack)
        session_regenerate_id(true);

        Session::set('logged_in', true);
        Session::set('user',      $user['email']);
        Session::set('user_id',   $user['id']);
        Session::set('user_role', $user['role'] ?? 'user');
        Session::set('login_at',  time());
        return true;
    }

    public static function logout(): void {
        Session::start();
        session_regenerate_id(true);
        Session::destroy();
        // Clear all session data
        $_SESSION = [];
    }

    public static function isLoggedIn(): bool {
        Session::start();
        // Must have: logged_in flag AND user email AND login timestamp
        if (Session::get('logged_in') !== true) return false;
        if (empty(Session::get('user')))         return false;
        if (empty(Session::get('login_at')))     return false;
        // Session lifetime check (7 days)
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
     * Enforce login. Always call this — never rely on Router alone.
     * Dual protection: Router + Controller level.
     */
    public static function requireLogin(bool $jsonResponse = false): void {
        if (!static::isLoggedIn()) {
            if ($jsonResponse) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Unauthorized — please login', 'redirect' => '/']);
                exit;
            }
            // For AJAX requests that forgot to use /api/ prefix
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                   || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
            if ($isAjax) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Unauthorized', 'redirect' => '/']);
                exit;
            }
            header('Location: /');
            exit;
        }
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
