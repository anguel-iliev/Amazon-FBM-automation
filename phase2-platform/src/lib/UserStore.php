<?php
/**
 * UserStore — управление на потребители (JSON файл)
 */
class UserStore {

    private static $file = '';

    private static function file() {
        if (!static::$file) {
            static::$file = DATA_DIR . '/users.json';
        }
        return static::$file;
    }

    public static function all() {
        $f = static::file();
        if (!file_exists($f)) return [];
        return json_decode(file_get_contents($f), true) ?? [];
    }

    private static function save($users) {
        file_put_contents(
            static::file(),
            json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    /** Public alias – used by ApiController::changePassword */
    public static function saveAll($users) {
        static::save($users);
    }

    public static function findByEmail($email) {
        foreach (static::all() as $u) {
            if (strtolower($u['email']) === strtolower($email)) return $u;
        }
        return null;
    }

    public static function findByToken($token, $type = 'verify') {
        $field = $type === 'reset' ? 'reset_token' : 'verify_token';
        foreach (static::all() as $u) {
            if (($u[$field] ?? '') === $token) return $u;
        }
        return null;
    }

    public static function invite($email, $invitedBy = 'admin') {
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

    public static function setPassword($email, $password) {
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

    public static function verifyToken($token) {
        $users = static::all();
        foreach ($users as &$u) {
            if (($u['verify_token'] ?? '') !== $token) continue;
            if (($u['verify_expires'] ?? 0) < time()) return null;
            return $u;
        }
        return null;
    }

    public static function authenticate($email, $password) {
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

        static::updateField($email, 'last_login', date('c'));

        return ['ok' => true, 'user' => $user];
    }

    public static function createResetToken($email) {
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

    public static function resetPassword($token, $newPassword) {
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

    private static function updateField($email, $field, $value) {
        $users = static::all();
        foreach ($users as &$u) {
            if (strtolower($u['email']) === strtolower($email)) {
                $u[$field] = $value;
                break;
            }
        }
        static::save($users);
    }

    public static function count() {
        return count(array_filter(static::all(), function($u) { return !empty($u['verified']); }));
    }

    public static function deleteByEmail($email) {
        $users  = static::all();
        $before = count($users);
        $users  = array_values(array_filter($users, function($u) use ($email) {
            return strtolower($u['email']) !== strtolower($email);
        }));
        if (count($users) === $before) return false;
        static::save($users);
        return true;
    }

    public static function refreshInviteToken($email) {
        $users = static::all();
        foreach ($users as &$u) {
            if (strtolower($u['email']) !== strtolower($email)) continue;
            $token = bin2hex(random_bytes(32));
            $u['verify_token']   = $token;
            $u['verify_expires'] = time() + TOKEN_EXPIRY;
            $u['verified']       = false;
            static::save($users);
            return $token;
        }
        return null;
    }
}
