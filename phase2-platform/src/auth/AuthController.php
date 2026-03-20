<?php
class AuthController {
    public function loginPage(): void {
        if (Auth::isLoggedIn()) {
            View::redirect('/dashboard');
        }
        View::render('auth/login', ['error' => null]);
    }

    public function loginAction(): void {
        if (Auth::isLoggedIn()) {
            View::redirect('/dashboard');
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            View::render('auth/login', ['error' => 'Моля попълнете всички полета.']);
            return;
        }

        if (Auth::login($username, $password)) {
            Logger::info("Login: {$username} from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            View::redirect('/dashboard');
        } else {
            Logger::warn("Failed login attempt: {$username}");
            // Small delay to prevent brute force
            usleep(500000);
            View::render('auth/login', ['error' => 'Грешно потребителско иле или парола.']);
        }
    }

    public function logout(): void {
        $user = Auth::user();
        Auth::logout();
        Logger::info("Logout: {$user}");
        View::redirect('/');
    }
}
