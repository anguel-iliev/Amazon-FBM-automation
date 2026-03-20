<?php
class AuthController {

    // ── Login ────────────────────────────────────────────────
    public function loginPage() {
        if (Auth::isLoggedIn()) View::redirect('/dashboard');
        View::render('auth/login', ['error' => null]);
    }

    public function loginAction() {
        if (Auth::isLoggedIn()) View::redirect('/dashboard');

        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            View::render('auth/login', ['error' => 'Моля попълнете всички полета.']);
            return;
        }

        require_once SRC . '/lib/UserStore.php';
        $result = UserStore::authenticate($email, $password);

        if ($result['ok'] && Auth::login($email, $password)) {
            Logger::info("Login: {$email}");
            View::redirect('/dashboard');
        } else {
            usleep(500000);
            Logger::warn("Failed login: {$email}");
            View::render('auth/login', ['error' => $result['error'] ?? 'Грешен имейл или парола.']);
        }
    }

    public function logout() {
        Logger::info("Logout: " . Auth::user());
        Auth::logout();
        View::redirect('/');
    }

    // ── Register (от покана) ─────────────────────────────────
    public function registerPage() {
        if (Auth::isLoggedIn()) View::redirect('/dashboard');

        $token = $_GET['token'] ?? '';
        if (empty($token)) { View::redirect('/'); return; }

        require_once SRC . '/lib/UserStore.php';
        $user = UserStore::verifyToken($token);

        if (!$user) {
            View::render('auth/register', [
                'error' => 'Линкът е невалиден или е изтекъл. Свържете се с администратора.',
                'token' => '',
                'email' => '',
            ]);
            return;
        }

        View::render('auth/register', [
            'error' => null,
            'token' => $token,
            'email' => $user['email'],
        ]);
    }

    public function registerAction() {
        require_once SRC . '/lib/UserStore.php';

        $token    = trim($_POST['token']    ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm  = trim($_POST['confirm']  ?? '');

        $user = UserStore::verifyToken($token);
        if (!$user) {
            View::render('auth/register', [
                'error' => 'Линкът е невалиден или е изтекъл.',
                'token' => $token,
                'email' => '',
            ]);
            return;
        }

        if (strlen($password) < 8) {
            View::render('auth/register', [
                'error' => 'Паролата трябва да е поне 8 символа.',
                'token' => $token,
                'email' => $user['email'],
            ]);
            return;
        }
        if ($password !== $confirm) {
            View::render('auth/register', [
                'error' => 'Паролите не съвпадат.',
                'token' => $token,
                'email' => $user['email'],
            ]);
            return;
        }

        UserStore::setPassword($user['email'], $password);
        Logger::info("Account activated: " . $user['email']);

        Auth::login($user['email'], $password);
        Session::flash('success', 'Акаунтът е активиран успешно!');
        View::redirect('/dashboard');
    }

    // ── Forgot password ──────────────────────────────────────
    public function forgotPage() {
        if (Auth::isLoggedIn()) View::redirect('/dashboard');
        View::render('auth/forgot', ['sent' => false, 'error' => null]);
    }

    public function forgotAction() {
        require_once SRC . '/lib/UserStore.php';
        require_once SRC . '/lib/Mailer.php';

        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            View::render('auth/forgot', ['sent' => false, 'error' => 'Невалиден имейл адрес.']);
            return;
        }

        $token = UserStore::createResetToken($email);
        if ($token) {
            Mailer::sendPasswordReset($email, $token);
            Logger::info("Password reset requested: {$email}");
        }

        View::render('auth/forgot', ['sent' => true, 'error' => null]);
    }

    // ── Reset password ───────────────────────────────────────
    public function resetPage() {
        if (Auth::isLoggedIn()) View::redirect('/dashboard');

        $token = $_GET['token'] ?? '';
        require_once SRC . '/lib/UserStore.php';
        $user  = UserStore::findByToken($token, 'reset');
        $valid = $user && ($user['reset_expires'] ?? 0) > time();

        View::render('auth/reset', [
            'token' => $token,
            'valid' => $valid,
            'error' => null,
        ]);
    }

    public function resetAction() {
        require_once SRC . '/lib/UserStore.php';

        $token    = trim($_POST['token']    ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm  = trim($_POST['confirm']  ?? '');

        if (strlen($password) < 8) {
            View::render('auth/reset', ['token' => $token, 'valid' => true, 'error' => 'Паролата трябва да е поне 8 символа.']);
            return;
        }
        if ($password !== $confirm) {
            View::render('auth/reset', ['token' => $token, 'valid' => true, 'error' => 'Паролите не съвпадат.']);
            return;
        }

        if (UserStore::resetPassword($token, $password)) {
            Logger::info("Password reset completed for token {$token}");
            Session::flash('success', 'Паролата е сменена успешно. Можете да влезете.');
            View::redirect('/');
        } else {
            View::render('auth/reset', ['token' => $token, 'valid' => false, 'error' => 'Линкът е изтекъл.']);
        }
    }

    // ── Admin: Invite user ───────────────────────────────────
    public function invitePage() {
        Auth::requireAdmin();
        require_once SRC . '/lib/UserStore.php';
        View::renderWithLayout('auth/invite', [
            'pageTitle'  => 'Покани потребител',
            'activePage' => 'settings',
            'users'      => UserStore::all(),
            'smtpOk'     => !empty(SMTP_PASS) && SMTP_PASS !== 'your_16char_app_password_here',
        ]);
    }

    public function inviteAction() {
        Auth::requireAdmin();
        require_once SRC . '/lib/UserStore.php';
        require_once SRC . '/lib/Mailer.php';

        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Невалиден имейл адрес.');
            View::redirect('/invite');
            return;
        }

        $result = UserStore::invite($email, Auth::user());
        if (!$result['ok']) {
            Session::flash('error', $result['error']);
            View::redirect('/invite');
            return;
        }

        $sent = Mailer::sendInvite($email, $result['token']);
        if ($sent) {
            Session::flash('success', "Покана изпратена до {$email}");
            Logger::info("Invite sent to {$email} by " . Auth::user());
        } else {
            Session::flash('error', 'Поканата е създадена, но имейлът не беше изпратен. Провери SMTP настройките.');
        }

        View::redirect('/invite');
    }

    // ── Admin: Delete user ───────────────────────────────────
    public function deleteUserAction() {
        Auth::requireAdmin();
        require_once SRC . '/lib/UserStore.php';

        $email = strtolower(trim($_POST['email'] ?? ''));

        if ($email === strtolower(Auth::user() ?? '')) {
            Session::flash('error', 'Не можеш да изтриеш собствения си акаунт.');
            View::redirect('/invite');
            return;
        }

        if (UserStore::deleteByEmail($email)) {
            Session::flash('success', "Потребителят {$email} е изтрит.");
            Logger::info("User deleted: {$email} by " . Auth::user());
        } else {
            Session::flash('error', "Потребителят не е намерен.");
        }

        View::redirect('/invite');
    }

    // ── Admin: Resend invite ─────────────────────────────────
    public function resendInviteAction() {
        Auth::requireAdmin();
        require_once SRC . '/lib/UserStore.php';
        require_once SRC . '/lib/Mailer.php';

        $email = strtolower(trim($_POST['email'] ?? ''));
        $user  = UserStore::findByEmail($email);

        if (!$user) {
            Session::flash('error', 'Потребителят не е намерен.');
            View::redirect('/invite');
            return;
        }

        if ($user['verified'] ?? false) {
            Session::flash('error', 'Акаунтът вече е активиран — не е нужна нова покана.');
            View::redirect('/invite');
            return;
        }

        $token = UserStore::refreshInviteToken($email);
        if (!$token) {
            Session::flash('error', 'Грешка при обновяване на токена.');
            View::redirect('/invite');
            return;
        }

        $sent = Mailer::sendInvite($email, $token);
        if ($sent) {
            Session::flash('success', "Поканата е изпратена повторно до {$email}");
            Logger::info("Invite resent to {$email} by " . Auth::user());
        } else {
            Session::flash('error', 'Имейлът не беше изпратен. Провери SMTP настройките.');
        }

        View::redirect('/invite');
    }
}
