<?php
/**
 * UserStore — управление на потребители (JSON файл)
 * Система с покани: само предварително добавени имейли могат да се регистрират.
 *
 * users.json структура:
 * [
 *   {
 *     "id":             "uuid",
 *     "email":          "user@example.com",
 *     "password_hash":  "$2y$...",
 *     "verified":       true/false,
 *     "verify_token":   "hex_token",
 *     "verify_expires": 1234567890,
 *     "reset_token":    "hex_token",
 *     "reset_expires":  1234567890,
 *     "invited_by":     "admin",
 *     "created_at":     "2025-01-01T00:00:00",
 *     "last_login":     "2025-01-01T00:00:00",
 *     "role":           "user" | "admin"
 *   }
 * ]
 */
class UserStore {

    private static string $file = '';

    private static function file(): string {
        if (!static::$file) {
            static::$file = DATA_DIR . '/users.json';
        }
        return static::$file;
    }

    // ── Read / Write ──────────────────────────────────────────
    public static function all(): array {
        $f = static::file();
        if (!file_exists($f)) return [];
        return json_decode(file_get_contents($f), true) ?? [];
    }

    private static function save(array $users): void {
        file_put_contents(
            static::file(),
            json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    // ── Find ──────────────────────────────────────────────────
    public static function findByEmail(string $email): ?array {
        foreach (static::all() as $u) {
            if (strtolower($u['email']) === strtolower($email)) return $u;
        }
        return null;
    }

    public static function findByToken(string $token, string $type = 'verify'): ?array {
        $field = $type === 'reset' ? 'reset_token' : 'verify_token';
        foreach (static::all() as $u) {
            if (($u[$field] ?? '') === $token) return $u;
        }
        return null;
    }

    // ── Invite ────────────────────────────────────────────────
    /**
     * Добавя имейл в списъка с поканени.
     * Потребителят ще получи имейл с линк за регистрация.
     */
    public static function invite(string $email, string $invitedBy = 'admin'): array {
        $email = strtolower(trim($email));

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

    // ── Register (set password after invite) ──────────────────
    public static function setPassword(string $email, string $password): bool {
        $users = static::all();
        foreach ($users as &$u) {
            if (strtolower($u['email']) === strtolower($email)) {
                $u['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
                $u['verified']      = true;
                $u['verify_token']  = '';
                $u['verify_expires']= 0;
                static::save($users);
                return true;
            }
        }
        return false;
    }

    // ── Verify token ─────────────────────────────────────────
    public static function verifyToken(string $token): ?array {
        $users = static::all();
        foreach ($users as &$u) {
            if (($u['verify_token'] ?? '') !== $token) continue;
            if (($u['verify_expires'] ?? 0) < time()) return null; // expired
            return $u;
        }
        return null;
    }

    // ── Login ─────────────────────────────────────────────────
    public static function authenticate(string $email, string $password): array {
        $user = static::findByEmail($email);

        if (!$user) {
            return ['ok' => false, 'error' => 'Грешен имейл или парола.'];
        }
        if (empty($user['password_hash'])) {
            return ['ok' => false, 'error' => 'Акаунтът не е активиран. Провери имейла си.'];
        }
        if (!$user['verified']) {
            return ['ok' => false, 'error' => 'Имейлът не е потвърден. Провери входящата поща.'];
        }
        if (!password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Грешен имейл или парола.'];
        }

        // Update last login
        static::updateField($email, 'last_login', date('c'));

        return ['ok' => true, 'user' => $user];
    }

    // ── Password reset ────────────────────────────────────────
    public static function createResetToken(string $email): ?string {
        $users = static::all();
        foreach ($users as &$u) {
            if (strtolower($u['email']) !== strtolower($email)) continue;
            if (!$u['verified']) return null;

            $token = bin2hex(random_bytes(32));
            $u['reset_token']   = $token;
            $u['reset_expires'] = time() + TOKEN_EXPIRY;
            static::save($users);
            return $token;
        }
        return null;
    }

    public static function resetPassword(string $token, string $newPassword): bool {
        $users = static::all();
        foreach ($users as &$u) {
            if (($u['reset_token'] ?? '') !== $token) continue;
            if (($u['reset_expires'] ?? 0) < time()) return false;
            $u['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
            $u['reset_token']   = '';
            $u['reset_expires'] = 0;
            static::save($users);
            return true;
        }
        return false;
    }

    // ── Helpers ───────────────────────────────────────────────
    private static function updateField(string $email, string $field, mixed $value): void {
        $users = static::all();
        foreach ($users as &$u) {
            if (strtolower($u['email']) === strtolower($email)) {
                $u[$field] = $value;
                break;
            }
        }
        static::save($users);
    }

    public static function count(): int {
        return count(array_filter(static::all(), fn($u) => $u['verified']));
    }
}
