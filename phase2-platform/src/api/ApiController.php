<?php
class ApiController {

    // ── Stats ─────────────────────────────────────────────────
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

    // ── Products (JSON list) ──────────────────────────────────
    public function products() {
        require_once SRC . '/lib/DataStore.php';
        $filters = [];
        if (!empty($_GET['search']))        $filters['search']        = $_GET['search'];
        if (!empty($_GET['upload_status'])) $filters['upload_status'] = $_GET['upload_status'];
        if (!empty($_GET['source']))        $filters['source']        = $_GET['source'];
        if (!empty($_GET['brand']))         $filters['brand']         = $_GET['brand'];

        $products = DataStore::getProducts($filters);
        View::json(['products' => array_slice($products, 0, 100), 'total' => count($products)]);
    }

    // ── Suppliers (JSON list) ─────────────────────────────────
    public function suppliersApi() {
        require_once SRC . '/lib/DataStore.php';
        $suppliers = DataStore::getSuppliers();
        View::json(['suppliers' => $suppliers, 'total' => count($suppliers)]);
    }

    // ── Sync ──────────────────────────────────────────────────
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

    // ── Test email ────────────────────────────────────────────
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

    // ── Import Excel ──────────────────────────────────────────
    public function importExcel() {
        require_once SRC . '/lib/DataStore.php';

        if (empty($_FILES['file']['tmp_name'])) {
            View::json(['success' => false, 'error' => 'Не е избран файл.'], 400);
            return;
        }

        $tmp  = $_FILES['file']['tmp_name'];
        $dest = CACHE_DIR . '/upload_' . time() . '.xlsx';

        if (!move_uploaded_file($tmp, $dest)) {
            View::json(['success' => false, 'error' => 'Грешка при качване на файла.'], 500);
            return;
        }

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

    // ── Change password ───────────────────────────────────────
    public function changePassword() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $current = $data['current_password'] ?? '';
        $newPw   = $data['new_password']     ?? '';
        $confirm = $data['confirm_password'] ?? '';

        if (empty($current) || empty($newPw) || empty($confirm)) {
            View::json(['success' => false, 'error' => 'Всички полета са задължителни.'], 400);
            return;
        }
        if ($newPw !== $confirm) {
            View::json(['success' => false, 'error' => 'Новите пароли не съвпадат.'], 400);
            return;
        }
        if (strlen($newPw) < 8) {
            View::json(['success' => false, 'error' => 'Паролата трябва да е минимум 8 символа.'], 400);
            return;
        }

        require_once SRC . '/lib/UserStore.php';
        $email = Auth::user();
        $users = UserStore::all();
        $found = false;

        foreach ($users as &$u) {
            if (($u['email'] ?? '') === $email) {
                if (!password_verify($current, $u['password_hash'] ?? '')) {
                    View::json(['success' => false, 'error' => 'Текущата парола е грешна.'], 400);
                    return;
                }
                $u['password_hash'] = password_hash($newPw, PASSWORD_BCRYPT);
                $found = true;
                break;
            }
        }
        unset($u);

        if (!$found) {
            View::json(['success' => false, 'error' => 'Потребителят не е намерен.'], 404);
            return;
        }

        UserStore::saveAll($users);
        Logger::info("Password changed for {$email}");
        View::json(['success' => true, 'message' => 'Паролата е сменена успешно.']);
    }

    // ── Apply formula to products ─────────────────────────────
    public function applyFormula() {
        require_once SRC . '/lib/DataStore.php';
        $data    = json_decode(file_get_contents('php://input'), true) ?? [];
        $field   = $data['field']   ?? '';
        $formula = $data['formula'] ?? '';

        if (!$field) {
            View::json(['success' => false, 'error' => 'Полето е задължително'], 400);
            return;
        }

        // Persist formula in products.json formula_templates
        DataStore::saveFormulaTemplates(
            array_merge(DataStore::getFormulaTemplates(), [$field => $formula])
        );
        // Also persist in settings for backward compat
        $settings = DataStore::getSettings();
        $settings['formulas'] = $settings['formulas'] ?? [];
        $settings['formulas'][$field] = $formula;
        DataStore::saveSettings($settings);

        // Apply formula to all products if not empty
        $updated = 0;
        if ($formula !== '') {
            $products = DataStore::getProducts();
            // Build a lookup of field -> numeric value for each product
            foreach ($products as &$p) {
                $expr = $formula;
                // Replace {Column Name} tokens with their numeric values
                preg_match_all('/\{([^}]+)\}/', $formula, $matches);
                foreach ($matches[1] as $col) {
                    $colVal = (float)($p[$col] ?? 0);
                    $expr   = str_replace('{' . $col . '}', $colVal, $expr);
                }
                // Also replace single-letter column references (legacy: {N} etc.)
                // Safety: only allow digits, operators, dots, parentheses, spaces
                $clean = preg_replace('/[^0-9\.\+\-\*\/\(\)\s]/', '', $expr);
                if (trim($clean) !== '') {
                    $val = null;
                    try {
                        $val = eval("return (" . $clean . ");");
                    } catch (\Throwable $e) {
                        $val = null;
                    }
                    if ($val !== null && is_numeric($val)) {
                        $p[$field] = round((float)$val, 4);
                        $updated++;
                    }
                }
            }
            unset($p);
            DataStore::saveProductsCache($products);
        }

        Logger::info("Formula [{$field}] applied by " . Auth::user());
        View::json(['success' => true, 'updated' => $updated, 'formula' => $formula]);
    }

    // ── Save price columns config ─────────────────────────────
    public function savePriceColumns() {
        require_once SRC . '/lib/DataStore.php';
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $cols = $data['columns'] ?? [];
        if (!is_array($cols) || empty($cols)) {
            View::json(['success' => false, 'error' => 'Невалидни колони'], 400);
            return;
        }
        $settings = DataStore::getSettings();
        $settings['prices_columns'] = array_values($cols);
        DataStore::saveSettings($settings);
        Logger::info("Price columns saved by " . Auth::user());
        View::json(['success' => true]);
    }

    // ── Export CSV ────────────────────────────────────────────
    public function exportCsv() {
        require_once SRC . '/lib/DataStore.php';
        $filters = [];
        if (!empty($_POST['search']))        $filters['search']        = $_POST['search'];
        if (!empty($_POST['upload_status'])) $filters['upload_status'] = $_POST['upload_status'];
        if (!empty($_POST['dostavchik']))    $filters['dostavchik']    = $_POST['dostavchik'];

        $products = DataStore::getProducts($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM for Excel

        // Headers — exact Excel column names
        $headers = [
            'EAN Amazon','EAN Доставчик','Корекция  на цена','Коментар',
            'Наше SKU','Доставчик SKU','Доставчик','Бранд','Модел','ASIN',
            'Цена Конкурент  - Brutto','Цена Amazon  - Brutto',
            'Продажна Цена в Амазон  - Brutto','Цена без ДДС',
            'ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto',
            'ДДС  от Цена Доставчик','Транспорт от Доставчик до нас',
            'Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент',
            'Резултат','Намерена 2ра обява','Цена за Испания / Франция / Италия',
            'DM цена','Нова цена след намаление','Доставени',
            'За следваща поръчка','Електоника','Статус',
        ];
        fputcsv($out, $headers, ';');

        foreach ($products as $p) {
            $row = [];
            foreach (array_slice($headers, 0, 29) as $h) {
                $row[] = $p[$h] ?? '';
            }
            $row[] = $p['_upload_status'] ?? 'NOT_UPLOADED';
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    // ── Save carriers ─────────────────────────────────────────
    public function saveCarriers() {
        require_once SRC . '/lib/DataStore.php';
        if (!Auth::check()) { View::json(['success' => false, 'error' => 'Unauthorized'], 401); return; }
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $carriers = $data['carriers'] ?? [];
        if (!is_array($carriers)) {
            View::json(['success' => false, 'error' => 'Невалидни данни'], 400);
            return;
        }
        // Sanitize carriers
        $clean = [];
        foreach ($carriers as $c) {
            if (!empty($c['name'])) {
                $clean[] = [
                    'id'     => $c['id']     ?? ('c_' . uniqid()),
                    'name'   => trim($c['name']),
                    'active' => (bool)($c['active'] ?? true),
                ];
            }
        }
        $settings             = DataStore::getSettings();
        $settings['carriers'] = $clean;
        DataStore::saveSettings($settings);
        View::json(['success' => true, 'count' => count($clean)]);
    }

    // ── Save extra (user-added) formula column ────────────────
    public function saveExtraFormulaCol() {
        require_once SRC . '/lib/DataStore.php';
        if (!Auth::check()) { View::json(['success' => false, 'error' => 'Unauthorized'], 401); return; }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $col  = trim($data['col'] ?? '');
        if (!$col) { View::json(['success' => false, 'error' => 'Missing col'], 400); return; }
        $settings = DataStore::getSettings();
        $extras   = $settings['extra_formula_cols'] ?? [];
        if (!in_array($col, $extras)) {
            $extras[] = $col;
            $settings['extra_formula_cols'] = array_values($extras);
            DataStore::saveSettings($settings);
        }
        View::json(['success' => true]);
    }

    // ── Test Firebase connection ──────────────────────────────
    public function testFirebase() {
        require_once SRC . '/lib/DataStore.php';
        if (!Auth::check()) { View::json(['success' => false, 'error' => 'Unauthorized'], 401); return; }
        $data    = json_decode(file_get_contents('php://input'), true) ?? [];
        $dbUrl   = rtrim(trim($data['db_url'] ?? ''), '/');
        $token   = trim($data['token'] ?? '');

        if (!$dbUrl) {
            $settings = DataStore::getSettings();
            $dbUrl    = rtrim($settings['firebase']['db_url'] ?? '', '/');
            $token    = $settings['firebase_token'] ?? '';
        }

        if (!$dbUrl) {
            View::json(['success' => false, 'error' => 'Database URL не е конфигуриран']);
            return;
        }

        // Attempt a lightweight read from Firebase REST API
        $testUrl = $dbUrl . '/.json?shallow=true&print=silent';
        if ($token) $testUrl .= '&auth=' . urlencode($token);

        $ch = curl_init($testUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'AMZRetail/1.7',
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            View::json(['success' => false, 'error' => 'CURL грешка: ' . $err]);
            return;
        }
        if ($code >= 200 && $code < 300) {
            View::json(['success' => true, 'message' => "HTTP {$code} — Firebase достъпна", 'code' => $code]);
        } else {
            View::json(['success' => false, 'error' => "HTTP {$code} — " . substr($resp, 0, 200)]);
        }
    }

    // ── Sync all products to Firebase ────────────────────────
    public function syncToFirebase() {
        require_once SRC . '/lib/DataStore.php';
        if (!Auth::check()) { View::json(['success' => false, 'error' => 'Unauthorized'], 401); return; }

        $settings = DataStore::getSettings();
        $fb       = $settings['firebase'] ?? [];
        $dbUrl    = rtrim($fb['db_url'] ?? '', '/');
        $token    = $settings['firebase_token'] ?? '';

        if (!$dbUrl) {
            View::json(['success' => false, 'error' => 'Firebase URL не е конфигуриран. Моля настрои го в Настройки → Интеграции.']);
            return;
        }

        $products  = DataStore::getProducts();
        $payload   = [];
        foreach ($products as $p) {
            $key            = preg_replace('/[^a-zA-Z0-9_-]/', '_', $p['EAN Amazon'] ?? $p['Наше SKU'] ?? uniqid());
            $payload[$key]  = $p;
        }

        // Write to Firebase via REST (PATCH to merge, not overwrite)
        $url = $dbUrl . '/products.json';
        if ($token) $url .= '?auth=' . urlencode($token);

        // Chunk if too large (Firebase REST limit ~10MB)
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 9_000_000) {
            // Send in chunks of 200 products
            $chunks  = array_chunk($payload, 200, true);
            $updated = 0;
            foreach ($chunks as $chunk) {
                $r = static::firebasePatch($dbUrl . '/products.json', $token, $chunk);
                if ($r['ok']) $updated += count($chunk);
            }
            View::json(['success' => true, 'count' => $updated]);
            return;
        }

        $result = static::firebasePatch($url, '', $payload, true);
        View::json($result['ok']
            ? ['success' => true,  'count'  => count($payload)]
            : ['success' => false, 'error'  => $result['error'] ?? 'Грешка при запис']
        );
    }

    private static function firebasePatch($url, $token, $data, $urlHasAuth = false) {
        $sendUrl = $urlHasAuth ? $url : ($url . ($token ? '?auth=' . urlencode($token) : ''));
        $json    = json_encode($data, JSON_UNESCAPED_UNICODE);
        $ch      = curl_init($sendUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PATCH',
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err) return ['ok' => false, 'error' => $err];
        if ($code >= 200 && $code < 300) return ['ok' => true];
        return ['ok' => false, 'error' => "HTTP {$code}: " . substr($resp, 0, 200)];
    }
}
