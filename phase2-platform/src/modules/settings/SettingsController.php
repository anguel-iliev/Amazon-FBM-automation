<?php
class SettingsController {
    public function index(): void {
        require_once SRC . '/lib/DataStore.php';
        $settings = DataStore::getSettings();

        View::renderWithLayout('settings/index', [
            'pageTitle'  => 'Настройки',
            'activePage' => 'settings',
            'settings'   => $settings,
        ]);
    }

    public function save(): void {
        require_once SRC . '/lib/DataStore.php';
        $settings = DataStore::getSettings();

        // Google IDs
        $settings['google_sheet_id']  = trim($_POST['google_sheet_id']  ?? '');
        $settings['drive_folder_id']  = trim($_POST['drive_folder_id']  ?? '');
        $settings['min_margin']       = (float)($_POST['min_margin'] ?? 0.15);
        $settings['sync_auto']        = isset($_POST['sync_auto']);

        // Marketplace settings
        $codes = ['DE','FR','IT','ES','NL','PL','SE'];
        foreach ($codes as $code) {
            $settings['marketplaces'][$code] = [
                'vat'        => (float)($_POST["vat_{$code}"]        ?? 0) / 100,
                'amazon_fee' => (float)($_POST["amazon_fee_{$code}"] ?? 0) / 100,
                'shipping'   => (float)($_POST["shipping_{$code}"]   ?? 0),
                'fbm_fee'    => (float)($_POST["fbm_fee_{$code}"]    ?? 0),
                'active'     => isset($_POST["active_{$code}"]),
            ];
        }

        DataStore::saveSettings($settings);

        // Update .env if Google IDs changed
        Logger::info("Settings saved by " . Auth::user());
        Session::flash('success', 'Настройките са запазени.');
        View::redirect('/settings');
    }
}
