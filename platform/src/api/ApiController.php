<?php
class ApiController {

    public function stats(): void {
        View::json(Firebase::getStats());
    }

    public function testFirebase(): void {
        $res = Firebase::testConnection();
        View::json(['success' => $res['ok'], 'error' => $res['error'] ?? null]);
    }

    public function testEmail(): void {
        require_once SRC . '/lib/Mailer.php';
        $to   = Auth::user() ?? SMTP_FROM;
        $sent = Mailer::send($to, 'Test email — AMZ Retail', '<p>SMTP работи!</p>');
        View::json(['success' => $sent]);
    }

    public function changePassword(): void {
        require_once SRC . '/lib/UserStore.php';
        $data    = json_decode(file_get_contents('php://input'), true) ?? [];
        $current = $data['current_password'] ?? '';
        $newPw   = $data['new_password']     ?? '';
        $confirm = $data['confirm_password'] ?? '';

        if (strlen($newPw) < 8) { View::json(['success' => false, 'error' => 'Паролата трябва да е поне 8 символа'], 400); return; }
        if ($newPw !== $confirm) { View::json(['success' => false, 'error' => 'Паролите не съвпадат'], 400); return; }

        $user = UserStore::findByEmail(Auth::user() ?? '');
        if (!$user || !password_verify($current, $user['password_hash'] ?? '')) {
            View::json(['success' => false, 'error' => 'Грешна текуща парола'], 403); return;
        }
        UserStore::setPassword(Auth::user(), $newPw);
        View::json(['success' => true]);
    }

    /** Diagnostic — достъп: /api/diagnostic */
    public function diagnostic(): void {
        $diag = Firebase::diagnostic();
        $test = ($diag['curl'] && $diag['ready']) ? Firebase::testConnection() : null;
        $products = [];
        if ($diag['curl'] && $diag['ready']) {
            try { $products = Firebase::getAllProducts(); } catch (Throwable $e) {}
        }
        View::json([
            'version'       => VERSION,
            'php'           => PHP_VERSION,
            'firebase_diag' => $diag,
            'firebase_test' => $test,
            'product_count' => count($products),
            'sample_ean'    => !empty($products) ? ($products[0]['EAN Amazon'] ?? 'n/a') : 'no products',
        ]);
    }
}
