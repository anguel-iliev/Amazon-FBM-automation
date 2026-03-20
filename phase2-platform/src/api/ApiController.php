<?php
class ApiController {
    public function stats(): void {
        require_once SRC . '/lib/DataStore.php';
        $counts = DataStore::getProductCount();
        $log    = DataStore::getSyncLog();
        View::json([
            'total_products' => $counts['total'],
            'with_asin'      => $counts['withAsin'],
            'not_uploaded'   => $counts['notUploaded'],
            'suppliers'      => $counts['suppliers'],
            'last_sync'      => $log[0]['date'] ?? null,
        ]);
    }

    public function products(): void {
        require_once SRC . '/lib/DataStore.php';
        $filters = [];
        if (!empty($_GET['search']))        $filters['search']        = $_GET['search'];
        if (!empty($_GET['upload_status'])) $filters['upload_status'] = $_GET['upload_status'];
        if (!empty($_GET['source']))        $filters['source']        = $_GET['source'];

        $products = DataStore::getProducts($filters);
        View::json(['products' => array_slice($products, 0, 100), 'total' => count($products)]);
    }

    public function sync(): void {
        require_once SRC . '/lib/DataStore.php';
        $action = $_POST['action'] ?? '';

        if ($action === 'start') {
            $script  = ROOT . '/cron/sync_products.py';
            $logFile = LOGS_DIR . '/sync_' . date('YmdHis') . '.log';

            if (!file_exists($script)) {
                View::json(['success' => false, 'error' => 'Sync script not found'], 500);
                return;
            }

            exec("python3 " . escapeshellarg($script) . " >> " . escapeshellarg($logFile) . " 2>&1 &");
            Logger::info("API sync started by " . Auth::user());
            View::json(['success' => true, 'message' => 'Синхронизацията е стартирана']);
            return;
        }

        View::json(['error' => 'Unknown action'], 400);
    }
}
