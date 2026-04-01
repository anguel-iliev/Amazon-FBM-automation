<?php
class Auth {

    public static function login(string $email, string $password): bool {
        require_once SRC . '/lib/UserStore.php';
        Session::start();
        $result = UserStore::authenticate($email, $password);
        if (!$result['ok']) return false;

        $user = $result['user'];
        session_regenerate_id(true);
        Session::set('logged_in', true);
        Session::set('user', $user['email']);
        Session::set('user_id', $user['id']);
        Session::set('user_role', $user['role'] ?? 'user');
        Session::set('login_at', time());
        Session::set('last_seen_at', time());
        Session::set('user_agent', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255));
        return true;
    }

    public static function logout(): void {
        Session::start();
        $_SESSION = [];
        session_regenerate_id(true);
        Session::destroy();
        $_SESSION = [];
    }

    public static function isLoggedIn(): bool {
        Session::start();
        if (Session::get('logged_in') !== true) return false;
        $email = Session::get('user');
        $loginAt = (int)Session::get('login_at');
        if (!is_string($email) || $email === '' || $loginAt <= 0) return false;
        if (time() - $loginAt > SESSION_LIFE) {
            static::logout();
            return false;
        }

        require_once SRC . '/lib/UserStore.php';
        $user = UserStore::findByEmail($email);
        if (!$user || empty($user['verified']) || empty($user['password_hash'])) {
            static::logout();
            return false;
        }

        $sessionUa = (string)Session::get('user_agent', '');
        $currentUa = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        if ($sessionUa !== '' && $currentUa !== '' && $sessionUa !== $currentUa) {
            Logger::warn('Session invalidated due to user-agent mismatch for ' . $email);
            static::logout();
            return false;
        }

        $role = $user['role'] ?? 'user';
        if (Session::get('user_role') !== $role) {
            Session::set('user_role', $role);
        }
        Session::set('last_seen_at', time());
        return true;
    }

    public static function isAdmin(): bool {
        return static::isLoggedIn() && Session::get('user_role') === 'admin';
    }

    public static function requireLogin(bool $jsonResponse = false): void {
        if (static::isLoggedIn()) return;
        if ($jsonResponse) {
            Security::sendNoCacheHeaders();
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Unauthorized — моля влезте в системата', 'redirect' => '/'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        Security::sendNoCacheHeaders();
        http_response_code(302);
        header('Location: /');
        exit;
    }

    public static function requireAdmin(bool $jsonResponse = false): void {
        static::requireLogin($jsonResponse);
        if (static::isAdmin()) return;
        Logger::warn('Forbidden admin route for user=' . (static::user() ?? 'guest') . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
        if ($jsonResponse) {
            View::json(['success' => false, 'error' => 'Достъпът е отказан.'], 403);
        }
        http_response_code(403);
        View::render('errors/404');
        exit;
    }

    public static function user(): ?string  { return Session::get('user'); }
    public static function userId(): ?string { return Session::get('user_id'); }
    public static function role(): string    { return Session::get('user_role') ?? 'user'; }
}
