<?php
class SettingsController {

    // ── Sub-page router ────────────────────────────────────────
    public function index() {
        View::redirect('/settings/vat');
    }

    public function vat() {
        require_once SRC . '/lib/DataStore.php';
        View::renderWithLayout('settings/vat', [
            'pageTitle'  => 'Настройки — ДДС по пазари',
            'activePage' => 'settings-vat',
            'settings'   => DataStore::getSettings(),
        ]);
    }

    public function prices() {
        require_once SRC . '/lib/DataStore.php';
        $search   = $_GET['search'] ?? '';
        $filters  = [];
        if ($search) $filters['search'] = $search;
        View::renderWithLayout('settings/prices', [
            'pageTitle'  => 'Настройки — Редакция Цени',
            'activePage' => 'settings-prices',
            'products'   => DataStore::getProducts($filters),
        ]);
    }

    public function formulas() {
        require_once SRC . '/lib/DataStore.php';
        View::renderWithLayout('settings/formulas', [
            'pageTitle'  => 'Настройки — Формули',
            'activePage' => 'settings-formulas',
        ]);
    }

    public function integrations() {
        require_once SRC . '/lib/DataStore.php';
        View::renderWithLayout('settings/integrations', [
            'pageTitle'  => 'Настройки — Интеграции',
            'activePage' => 'settings-integrations',
            'settings'   => DataStore::getSettings(),
        ]);
    }

    public function system() {
        require_once SRC . '/lib/DataStore.php';
        View::renderWithLayout('settings/system', [
            'pageTitle'  => 'Настройки — Системни',
            'activePage' => 'settings-system',
        ]);
    }

    // ── Save (unified, uses _section OR action key) ───────────
    public function save() {
        require_once SRC . '/lib/DataStore.php';

        // Accept both form-POST and JSON-POST
        $isJson   = (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
        $json     = $isJson ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];
        $section  = $json['action'] ?? $_POST['_section'] ?? 'vat';
        $settings = DataStore::getSettings();

        // ── Firebase settings ──
        if ($section === 'save_firebase') {
            $fb = $json['firebase'] ?? [];
            $settings['firebase'] = [
                'project_id'     => trim($fb['project_id']    ?? $settings['firebase']['project_id']    ?? 'amz-retail'),
                'project_num'    => trim($fb['project_num']   ?? $settings['firebase']['project_num']   ?? '820571488028'),
                'db_url'         => trim($fb['db_url']        ?? $settings['firebase']['db_url']        ?? ''),
                'enabled'        => (bool)($fb['enabled']     ?? $settings['firebase']['enabled']       ?? false),
                'sync_on_import' => (bool)($fb['sync_on_import'] ?? $settings['firebase']['sync_on_import'] ?? true),
                'sync_on_update' => (bool)($fb['sync_on_update'] ?? $settings['firebase']['sync_on_update'] ?? true),
            ];
            if (!empty($json['firebase_token'])) {
                $settings['firebase_token'] = trim($json['firebase_token']);
            }
            DataStore::saveSettings($settings);
            Logger::info("Firebase settings saved by " . Auth::user());
            View::json(['success' => true]);
            return;
        }

        // ── Drive settings ──
        if ($section === 'save_drive') {
            $settings['drive_folder_id'] = trim($json['drive_folder_id'] ?? $_POST['drive_folder_id'] ?? '');
            DataStore::saveSettings($settings);
            View::json(['success' => true]);
            return;
        }

        // ── SMTP settings ──
        if ($section === 'save_smtp') {
            // Update .env file
            $envFile = ROOT . '/.env';
            if (file_exists($envFile)) {
                $env     = file_get_contents($envFile);
                $user    = trim($json['smtp_user'] ?? $_POST['smtp_user'] ?? '');
                $pass    = trim($json['smtp_pass'] ?? $_POST['smtp_pass'] ?? '');
                if ($user) $env = preg_replace('/^SMTP_USER=.*/m', 'SMTP_USER=' . $user, $env);
                if ($pass) $env = preg_replace('/^SMTP_PASS=.*/m', 'SMTP_PASS=' . $pass, $env);
                file_put_contents($envFile, $env);
            }
            View::json(['success' => true]);
            return;
        }

        // ── VAT / General (form POST) ──
        if ($section === 'vat' || $section === 'general') {
            $codes = ['DE','FR','IT','ES','NL','PL','SE'];
            foreach ($codes as $code) {
                $settings['marketplaces'][$code] = [
                    'vat'        => (float)($_POST["vat_{$code}"]        ?? 0) / 100,
                    'amazon_fee' => (float)($_POST["amazon_fee_{$code}"] ?? 0) / 100,
                    'shipping'   => (float)($_POST["shipping_{$code}"]   ?? 0),
                    'fbm_fee'    => (float)($_POST["fbm_fee_{$code}"]    ?? 0),
                    'min_margin' => isset($_POST["min_margin_{$code}"])
                                    ? (float)$_POST["min_margin_{$code}"] / 100
                                    : ($settings['marketplaces'][$code]['min_margin'] ?? 0.15),
                    'active'     => isset($_POST["active_{$code}"]),
                ];
            }
            $settings['min_margin'] = (float)($_POST['min_margin'] ?? 0.15) / 100;
            $settings['sync_auto']  = isset($_POST['sync_auto']);
        }

        if ($section === 'integrations') {
            $settings['google_sheet_id'] = trim($_POST['google_sheet_id'] ?? '');
            $settings['drive_folder_id'] = trim($_POST['drive_folder_id'] ?? '');
        }

        DataStore::saveSettings($settings);
        Logger::info("Settings [{$section}] saved by " . Auth::user());
        Session::flash('success', 'Настройките са запазени.');
        View::redirect('/settings/' . ($section === 'vat' ? 'vat' : 'integrations'));
    }

    // ── Firebase Admin page ────────────────────────────────────
    public function firebaseAdmin() {
        Auth::requireAdmin();
        require_once SRC . '/lib/DataStore.php';
        require_once SRC . '/lib/FirebaseDB.php';
        $settings = DataStore::getSettings();
        $fb       = $settings['firebase'] ?? [
            'project_id'  => 'amz-retail',
            'project_num' => '820571488028',
            'db_url'      => 'https://amz-retail-default-rtdb.europe-west1.firebasedatabase.app',
            'enabled'     => false,
        ];
        $products = DataStore::getProducts();
        View::renderWithLayout('firebase_admin', [
            'pageTitle'  => '🔥 Firebase Admin',
            'activePage' => 'firebase-admin',
            'settings'   => $settings,
            'fb'         => $fb,
            'products'   => $products,
            'dbUrl'      => $fb['db_url'] ?? FirebaseDB::DB_URL,
        ]);
    }

    public function firebaseAdminAction() {
        Auth::requireAdmin();
        require_once SRC . '/lib/DataStore.php';
        require_once SRC . '/lib/FirebaseDB.php';
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $data['action'] ?? '';
        header('Content-Type: application/json');

        switch ($action) {
            case 'test':
                echo json_encode(FirebaseDB::testConnection());
                return;
            case 'migrate':
                $products  = DataStore::getProducts();
                $settings  = DataStore::getSettings();
                $suppliers = DataStore::getSuppliers();
                $r1 = FirebaseDB::set('/meta', [
                    'project' => 'amz-retail', 'migrated_at' => date('Y-m-d H:i:s'),
                    'version' => '1.7.0', 'total_products' => count($products),
                ]);
                $r2 = FirebaseDB::syncSettings($settings);
                $r3 = FirebaseDB::syncSuppliers($suppliers);
                $r4 = FirebaseDB::syncAllProducts($products);
                DataStore::appendSyncLog(['action' => 'firebase_migration',
                    'synced' => $r4['synced'], 'total' => $r4['total'],
                    'errors' => count($r4['errors']), 'user' => Auth::user()]);
                Logger::info("Firebase migration: {$r4['synced']}/{$r4['total']} by " . Auth::user());
                echo json_encode([
                    'ok' => $r4['ok'],
                    'synced' => $r4['synced'], 'total' => $r4['total'],
                    'steps' => [
                        'meta'      => $r1['ok'] ? 'ok' : $r1['error'],
                        'settings'  => $r2['ok'] ? 'ok' : $r2['error'],
                        'suppliers' => $r3['ok'] ? 'ok' : $r3['error'],
                        'products'  => "synced:{$r4['synced']} errors:" . count($r4['errors']),
                    ],
                ]);
                return;
            case 'read_path':
                $r = FirebaseDB::get('/' . ltrim($data['path'] ?? 'meta', '/'));
                echo json_encode(['ok' => $r['ok'], 'data' => $r['data'], 'error' => $r['error']]);
                return;
            case 'delete_path':
                $r = FirebaseDB::delete('/' . ltrim($data['path'] ?? '', '/'));
                echo json_encode(['ok' => $r['ok'], 'error' => $r['error']]);
                return;
        }
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }

    // ── Save formulas (JSON POST) ──────────────────────────────
    public function saveFormulas() {
        require_once SRC . '/lib/DataStore.php';
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $formulas = $data['formulas'] ?? [];

        // Save to products.json formula_templates (primary store)
        DataStore::saveFormulaTemplates($formulas);

        // Also mirror to settings.json for backward compat
        $settings             = DataStore::getSettings();
        $settings['formulas'] = $formulas;
        DataStore::saveSettings($settings);

        Logger::info("Formulas saved by " . Auth::user());
        View::json(['success' => true]);
    }
}
