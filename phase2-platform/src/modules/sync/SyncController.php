<?php
class SyncController {
    public function index() {
        require_once SRC . '/lib/DataStore.php';

        $syncLog  = DataStore::getSyncLog();
        $settings = DataStore::getSettings();

        View::renderWithLayout('sync/index', [
            'pageTitle'  => 'Синхронизация',
            'activePage' => 'sync',
            'syncLog'    => $syncLog,
            'settings'   => $settings,
            'lastSync'   => $syncLog[0]['date'] ?? null,
        ]);
    }

    public function run() {
        require_once SRC . '/lib/DataStore.php';

        $pythonScript = ROOT . '/cron/sync_products.py';
        $logFile      = LOGS_DIR . '/sync_' . date('Y-m-d_His') . '.log';

        if (!file_exists($pythonScript)) {
            Session::flash('error', 'Sync скриптът не е намерен.');
            View::redirect('/sync');
            return;
        }

        $cmd = "python3 " . escapeshellarg($pythonScript) . " > " . escapeshellarg($logFile) . " 2>&1 &";
        exec($cmd);

        Logger::info("Sync started by " . Auth::user());
        Session::flash('success', 'Синхронизацията е стартирана. Виж лога за резултата.');
        View::redirect('/sync');
    }

    public function status() {
        require_once SRC . '/lib/DataStore.php';
        $log = DataStore::getSyncLog();
        View::json([
            'last_sync' => $log[0] ?? null,
            'count'     => count($log),
        ]);
    }
}
