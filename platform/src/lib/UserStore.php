<?php
/**
 * UserStore — управление на потребители с runtime persistence,
 * така че deploy на нов ZIP да не изтрива поканените/активирани users.
 */
class UserStore {

    private static string $runtimeFile = '';
    private static string $legacyFile = '';

    private static function runtimeFile(): string {
        if (!static::$runtimeFile) {
            $dir = DATA_DIR . '/runtime';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            static::$runtimeFile = $dir . '/users.json';
        }
        return static::$runtimeFile;
    }

    private static function legacyFile(): string {
        if (!static::$legacyFile) {
            static::$legacyFile = DATA_DIR . '/users.json';
        }
        return static::$legacyFile;
    }

    private static function ensureStorage(): string {
        $runtime = static::runtimeFile();
        $legacy = static::legacyFile();

        if (file_exists($runtime)) {
            return $runtime;
        }

        if (file_exists($legacy)) {
            $legacyData = json_decode((string)@file_get_contents($legacy), true);
            if (is_array($legacyData) && $legacyData !== []) {
                @file_put_contents($runtime, json_encode(array_values($legacyData), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
                return $runtime;
            }
        }

        return $runtime;
    }

    private static function file(): string {
        return static::ensureStorage();
    }

    public static function all(): array {
        $f = static::file();
        if (!file_exists($f)) return [];
        $data = json_decode((string)file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }

    private static function save(array $users): void {
        $json = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents(static::file(), $json, LOCK_EX);
    }

    /** Public alias – used by ApiController::changePassword */
    public static function saveAll($users): void {
        static::save(is_array($users) ? $users : []);
    }

    public static function hasUsers(): bool {
        return count(static::all()) > 0;
    }

    public static function count(): int {
        return count(array_filter(static::all(), function($u) { return !empty($u['verified']); }));
    }

    public static function findByEmail($email): ?array {
        foreach (static::all() as $u) {
            if (strtolower($u['email'] ?? '') === strtolower((string)$email)) return $u;
        }
        return null;
    }

    public static function findByToken($token, $type = 'verify'): ?array {
        $field = $type === 'reset' ? 'reset_token' : 'verify_token';
        foreach (static::all() as $u) {
            if (($u[$field] ?? '') === $token) return $u;
        }
        return null;
    }

    public static function invite($email, $invitedBy = 'admin'): array {
        $email = strtolower(trim((string)$email));

        if (static::findByEmail($email)) {
            return ['ok' => false, 'error' => 'Имейлът вече съществува.'];
        }

        $token = bin2hex(random_bytes(32));
        $user  = [
            'id'             => bin2hex(random_bytes(8)),
            'email'          => $email,
            'password_hash'  => '',
            'verified'       => false,
            'invited'        => true,
            'verify_token'   => $token,
            'verify_expires' => time() + TOKEN_EXPIRY,
            'reset_token'    => '',
            'reset_expires'  => 0,
            'invited_by'     => $invitedBy,
            'created_at'     => date('c'),
            'last_login'     => null,
            'role'           => 'user',
        ];

        $users = static::all();
        $users[] = $user;
        static::save($users);

        return ['ok' => true, 'token' => $token, 'user' => $user];
    }

    public static function setPassword($email, $password): bool {
        $users = static::all();
        foreach ($users as &$u) {
            if (strtolower($u['email'] ?? '') === strtolower((string)$email)) {
                $u['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
                $u['verified'] = true;
                $u['verify_token'] = '';
                $u['verify_expires'] = 0;
                static::save($users);
                return true;
            }
        }
        return false;
    }

    public static function verifyToken($token): ?array {
        $users = static::all();
        foreach ($users as $u) {
            if (($u['verify_token'] ?? '') !== $token) continue;
            if ((int)($u['verify_expires'] ?? 0) < time()) return null;
            return $u;
        }
        return null;
    }

    public static function authenticate($email, $password): array {
        $user = static::findByEmail($email);

        if (!$user) {
            return ['ok' => false, 'error' => 'Грешен имейл или парола.'];
        }
        if (empty($user['password_hash'])) {
            return ['ok' => false, 'error' => 'Акаунтът не е активиран. Провери имейла си.'];
        }
        if (empty($user['verified'])) {
            return ['ok' => false, 'error' => 'Имейлът не е потвърден. Провери входящата поща.'];
        }
        if (!password_verify((string)$password, (string)($user['password_hash'] ?? ''))) {
            return ['ok' => false, 'error' => 'Грешен имейл или парола.'];
        }

        static::updateField($email, 'last_login', date('c'));
        return ['ok' => true, 'user' => $user];
    }

    public static function createResetToken($email): ?string {
        $users = static::all();
        foreach ($users as &$u) {
            if (strtolower($u['email'] ?? '') !== strtolower((string)$email)) continue;
            if (empty($u['verified'])) return null;

            $token = bin2hex(random_bytes(32));
            $u['reset_token'] = $token;
            $u['reset_expires'] = time() + TOKEN_EXPIRY;
            static::save($users);
            return $token;
        }
        return null;
    }

    public static function resetPassword($token, $newPassword): bool {
        $users = static::all();
        foreach ($users as &$u) {
            if (($u['reset_token'] ?? '') !== $token) continue;
            if ((int)($u['reset_expires'] ?? 0) < time()) return false;
            $u['password_hash'] = password_hash((string)$newPassword, PASSWORD_BCRYPT);
            $u['reset_token'] = '';
            $u['reset_expires'] = 0;
            static::save($users);
            return true;
        }
        return false;
    }

    private static function updateField($email, $field, $value): void {
        $users = static::all();
        foreach ($users as &$u) {
            if (strtolower($u['email'] ?? '') === strtolower((string)$email)) {
                $u[$field] = $value;
                break;
            }
        }
        static::save($users);
    }

    public static function adminCount(): int {
        return count(array_filter(static::all(), fn($u) => ($u['role'] ?? 'user') === 'admin'));
    }

    public static function setRole($email, $role): bool {
        $role = $role === 'admin' ? 'admin' : 'user';
        $users = static::all();
        $changed = false;
        foreach ($users as &$u) {
            if (strtolower($u['email'] ?? '') === strtolower((string)$email)) {
                $u['role'] = $role;
                $changed = true;
                break;
            }
        }
        if ($changed) static::save($users);
        return $changed;
    }

    public static function deleteByEmail($email): bool {
        $users  = static::all();
        $before = count($users);
        $users  = array_values(array_filter($users, function($u) use ($email) {
            return strtolower($u['email'] ?? '') !== strtolower((string)$email);
        }));
        if (count($users) === $before) return false;
        static::save($users);
        return true;
    }

    public static function refreshInviteToken($email): ?string {
        $users = static::all();
        foreach ($users as &$u) {
            if (strtolower($u['email'] ?? '') !== strtolower((string)$email)) continue;
            $token = bin2hex(random_bytes(32));
            $u['verify_token'] = $token;
            $u['verify_expires'] = time() + TOKEN_EXPIRY;
            $u['verified'] = false;
            static::save($users);
            return $token;
        }
        return null;
    }
}
