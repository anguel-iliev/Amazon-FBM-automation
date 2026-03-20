<?php
class ApiController {
    public function stats() {
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

    public function products() {
        require_once SRC . '/lib/DataStore.php';
        $filters = [];
        if (!empty($_GET['search']))        $filters['search']        = $_GET['search'];
        if (!empty($_GET['upload_status'])) $filters['upload_status'] = $_GET['upload_status'];
        if (!empty($_GET['source']))        $filters['source']        = $_GET['source'];

        $products = DataStore::getProducts($filters);
        View::json(['products' => array_slice($products, 0, 100), 'total' => count($products)]);
    }

    public function sync() {
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

    public function testEmail() {
        Auth::requireAdmin();
        require_once SRC . '/lib/Mailer.php';

        $to = trim($_POST['to'] ?? '') ?: (Auth::user() ?? '');

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            View::json(['success' => false, 'error' => 'Невалиден имейл адрес.'], 400);
            return;
        }

        if (empty(SMTP_PASS) || SMTP_PASS === 'your_16char_app_password_here') {
            View::json([
                'success' => false,
                'error'   => 'SMTP_PASS не е конфигуриран. Редактирай .env и добави Gmail App Password.',
            ], 400);
            return;
        }

        $subject = 'AMZ Retail — тест на SMTP (' . date('H:i:s') . ')';
        $body    = '<!DOCTYPE html><html lang="bg"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:40px 20px;background:#0D0F14;font-family:Arial,sans-serif">
  <div style="max-width:480px;margin:0 auto;background:#1A1E2A;border:1px solid rgba(255,255,255,0.08);border-radius:8px;overflow:hidden">
    <div style="background:#C9A84C;height:4px"></div>
    <div style="padding:32px 40px">
      <p style="font-size:20px;font-weight:700;color:#E8E6E1;margin:0 0 8px">AMZ<span style="color:#C9A84C">Retail</span></p>
      <h2 style="font-size:16px;color:#E8E6E1;margin:0 0 16px">SMTP работи!</h2>
      <p style="font-size:13px;color:rgba(232,230,225,0.65);line-height:1.7;margin:0">
        Тестовият имейл е изпратен успешно.<br>
        Поканите ще достигат до потребителите без проблем.
      </p>
    </div>
  </div>
</body></html>';

        $sent = Mailer::send($to, $subject, $body);

        if ($sent) {
            Logger::info("Test email sent to {$to} by " . Auth::user());
            View::json(['success' => true, 'message' => "Тестов имейл изпратен до {$to}"]);
        } else {
            View::json(['success' => false, 'error' => 'Грешка при изпращане. Провери SMTP настройките.'], 500);
        }
    }

    public function importExcel() {
        require_once SRC . '/lib/DataStore.php';

        if (empty($_FILES['file']['tmp_name'])) {
            View::json(['success' => false, 'error' => 'Не е избран файл.'], 400);
            return;
        }

        $tmp = $_FILES['file']['tmp_name'];
        $dest = CACHE_DIR . '/upload_' . time() . '.xlsx';

        if (!move_uploaded_file($tmp, $dest)) {
            View::json(['success' => false, 'error' => 'Грешка при качване на файла.'], 500);
            return;
        }

        // Use Python to parse the Excel file
        $script = ROOT . '/cron/parse_excel.py';
        $out    = CACHE_DIR . '/products.json';
        $cmd    = 'python3 ' . escapeshellarg($script) . ' ' . escapeshellarg($dest) . ' ' . escapeshellarg($out) . ' 2>&1';
        $result = shell_exec($cmd);
        @unlink($dest);

        if (!file_exists($out)) {
            View::json(['success' => false, 'error' => 'Грешка при парсване: ' . $result], 500);
            return;
        }

        $products = json_decode(file_get_contents($out), true) ?? [];
        Logger::info('Excel import: ' . count($products) . ' products by ' . Auth::user());
        View::json(['success' => true, 'count' => count($products)]);
    }
}
