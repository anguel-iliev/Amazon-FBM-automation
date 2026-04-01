<?php
/**
 * ProductDB — SQLite база данни за продукти + метаданни за колони/формули
 */
class ProductDB {

    private static ?PDO $pdo = null;
    private static ?array $formulaCache = null;
    private static ?array $columnSlugCache = null;

    private static function db(): PDO {
        if (static::$pdo !== null) return static::$pdo;

        $file = DATA_DIR . '/products.sqlite';
        $new  = !file_exists($file);

        static::$pdo = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        static::$pdo->exec("PRAGMA journal_mode = WAL");
        static::$pdo->exec("PRAGMA synchronous  = NORMAL");
        static::$pdo->exec("PRAGMA foreign_keys = ON");

        if ($new) {
            static::createSchema(static::$pdo);
            Logger::info("ProductDB: created new SQLite database");
        } else {
            static::ensureSchema(static::$pdo);
        }

        static::migrateLegacyFormulas();
        return static::$pdo;
    }

    private static function createSchema(PDO $db): void {
        $db->exec(" 
            CREATE TABLE IF NOT EXISTS products (
                ean_key             TEXT PRIMARY KEY,
                ean_amazon          TEXT NOT NULL DEFAULT '',
                ean_dostavchik      TEXT DEFAULT '',
                nashe_sku           TEXT DEFAULT '',
                dostavchik_sku      TEXT DEFAULT '',
                dostavchik          TEXT DEFAULT '',
                brand               TEXT DEFAULT '',
                model               TEXT DEFAULT '',
                amazon_link         TEXT DEFAULT '',
                asin                TEXT DEFAULT '',
                cena_konkurrent     TEXT DEFAULT '',
                cena_amazon         TEXT DEFAULT '',
                prodazhna_cena      TEXT DEFAULT '',
                cena_bez_dds        TEXT DEFAULT '',
                dds_prodazhna       TEXT DEFAULT '',
                amazon_taksi        TEXT DEFAULT '',
                cena_dostavchik     TEXT DEFAULT '',
                dds_dostavchik      TEXT DEFAULT '',
                transport_ot_dost   TEXT DEFAULT '',
                transport_klient    TEXT DEFAULT '',
                dds_transport       TEXT DEFAULT '',
                rezultat            TEXT DEFAULT '',
                namerena_2ra        TEXT DEFAULT '',
                cena_es_fr_it       TEXT DEFAULT '',
                dm_cena             TEXT DEFAULT '',
                nova_cena           TEXT DEFAULT '',
                dostaveni           TEXT DEFAULT '',
                sledvashta          TEXT DEFAULT '',
                elektronika         TEXT DEFAULT '',
                korekciya           TEXT DEFAULT '',
                komentar            TEXT DEFAULT '',
                upload_status       TEXT DEFAULT 'NOT_UPLOADED',
                extra_data          TEXT DEFAULT '{}',
                updated_at          TEXT DEFAULT (datetime('now'))
            );

            CREATE INDEX IF NOT EXISTS idx_dostavchik ON products(dostavchik);
            CREATE INDEX IF NOT EXISTS idx_brand      ON products(brand);
            CREATE INDEX IF NOT EXISTS idx_asin       ON products(asin);
            CREATE INDEX IF NOT EXISTS idx_status     ON products(upload_status);
            CREATE INDEX IF NOT EXISTS idx_rezultat   ON products(rezultat);
        ");
        static::ensureSchema($db);
    }

    private static function ensureSchema(PDO $db): void {
        $db->exec(" 
            CREATE TABLE IF NOT EXISTS product_columns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                slug TEXT NOT NULL UNIQUE,
                data_type TEXT NOT NULL DEFAULT 'text',
                source TEXT NOT NULL DEFAULT 'custom',
                is_formula INTEGER NOT NULL DEFAULT 0,
                position INTEGER NOT NULL DEFAULT 999,
                created_by TEXT DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS column_formulas (
                column_name TEXT PRIMARY KEY,
                formula_expression TEXT NOT NULL DEFAULT '',
                formula_tokens TEXT NOT NULL DEFAULT '[]',
                rounding INTEGER NOT NULL DEFAULT 2,
                updated_by TEXT DEFAULT '',
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS formula_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                column_name TEXT NOT NULL,
                action TEXT NOT NULL DEFAULT 'save',
                formula_expression TEXT NOT NULL DEFAULT '',
                formula_tokens TEXT NOT NULL DEFAULT '[]',
                rounding INTEGER NOT NULL DEFAULT 2,
                changed_by TEXT DEFAULT '',
                changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS formula_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                column_name TEXT NOT NULL,
                formula_expression TEXT NOT NULL DEFAULT '',
                formula_tokens TEXT NOT NULL DEFAULT '[]',
                rounding INTEGER NOT NULL DEFAULT 2,
                changed_by TEXT DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    private static array $colMap = [
        'EAN Amazon'                       => 'ean_amazon',
        'EAN Доставчик'                    => 'ean_dostavchik',
        'Наше SKU'                         => 'nashe_sku',
        'Доставчик SKU'                    => 'dostavchik_sku',
        'Доставчик'                        => 'dostavchik',
        'Бранд'                            => 'brand',
        'Модел'                            => 'model',
        'Amazon Link'                      => 'amazon_link',
        'ASIN'                             => 'asin',
        'Цена Конкурент  - Brutto'         => 'cena_konkurrent',
        'Цена Amazon  - Brutto'            => 'cena_amazon',
        'Продажна Цена в Амазон  - Brutto' => 'prodazhna_cena',
        'Цена без ДДС'                     => 'cena_bez_dds',
        'ДДС от продажна цена'             => 'dds_prodazhna',
        'Amazon Такси'                     => 'amazon_taksi',
        'Цена Доставчик -Netto'            => 'cena_dostavchik',
        'ДДС  от Цена Доставчик'           => 'dds_dostavchik',
        'Транспорт от Доставчик до нас'    => 'transport_ot_dost',
        'Транспорт до кр. лиент  Netto'    => 'transport_klient',
        'ДДС  от Транспорт до кр. лиент'   => 'dds_transport',
        'Резултат'                         => 'rezultat',
        'Намерена 2ра обява'               => 'namerena_2ra',
        'Цена за ES FR IT'                 => 'cena_es_fr_it',
        'DM цена'                          => 'dm_cena',
        'Нова цена след намаление'         => 'nova_cena',
        'Доставени'                        => 'dostaveni',
        'За следваща поръчка'              => 'sledvashta',
        'Електоника'                       => 'elektronika',
        'Корекция  на цена'                => 'korekciya',
        'Коментар'                         => 'komentar',
        '_upload_status'                   => 'upload_status',
    ];

    private static function reverseMap(): array {
        static $rev = null;
        if ($rev === null) {
            $rev = array_flip(static::$colMap);
            $rev['upload_status'] = '_upload_status';
        }
        return $rev;
    }


    private static function normalizeSupplierName(string $name): string {
        $name = trim(mb_strtolower($name, 'UTF-8'));
        $name = preg_replace('/\s+/u', ' ', $name);
        return $name;
    }

    private static function supplierTransportMap(): array {
        static $map = null;
        if ($map !== null) return $map;
        $map = [];
        $file = DATA_DIR . '/suppliers.json';
        if (!file_exists($file)) return $map;
        $rows = json_decode((string)file_get_contents($file), true);
        if (!is_array($rows)) return $map;
        foreach ($rows as $row) {
            $name = static::normalizeSupplierName((string)($row['name'] ?? ''));
            if ($name === '') continue;
            $val = trim((string)($row['transport_to_us'] ?? ''));
            if ($val === '') continue;
            $map[$name] = number_format((float)str_replace(',', '.', $val), 2, '.', '');
        }
        return $map;
    }

    private static function rowToProduct(array $row): array {
        $rev = static::reverseMap();
        $p   = [];
        foreach ($row as $col => $val) {
            if ($col === 'ean_key' || $col === 'extra_data' || $col === 'updated_at') continue;
            $excelName = $rev[$col] ?? $col;
            $p[$excelName] = $val ?? '';
        }
        if (!empty($row['extra_data']) && $row['extra_data'] !== '{}') {
            $extra = json_decode($row['extra_data'], true) ?? [];
            foreach ($extra as $k => $v) {
                if (!isset($p[$k])) $p[$k] = $v;
            }
        }
        $supplier = static::normalizeSupplierName((string)($p['Доставчик'] ?? ''));
        $transportMap = static::supplierTransportMap();
        if ($supplier !== '' && isset($transportMap[$supplier])) {
            $p['Транспорт от Доставчик до нас'] = $transportMap[$supplier];
        }
        return static::applyFormulasToProduct($p);
    }

    private static function productToParams(array $p, string $eanKey): array {
        $known  = array_keys(static::$colMap);
        $extra  = [];
        $params = [':ean_key' => $eanKey];

        foreach ($p as $excelKey => $val) {
            $dbCol = static::$colMap[$excelKey] ?? null;
            if ($dbCol && $dbCol !== 'ean_key') {
                $params[':' . $dbCol] = (string)($val ?? '');
            } elseif ($excelKey !== '_upload_status' && !in_array($excelKey, $known, true)) {
                $extra[$excelKey] = $val;
            }
        }
        if (!isset($params[':ean_amazon'])) $params[':ean_amazon'] = $eanKey;
        $params[':extra_data'] = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : '{}';
        return $params;
    }

    public static function query(array $filters = [], string $sortCol = '', string $sortDir = 'asc', int $page = 1, int $perPage = 50): array {
        $db     = static::db();
        $where  = [];
        $params = [];

        if (!empty($filters['dostavchik'])) {
            $where[] = "dostavchik = :dostavchik";
            $params[':dostavchik'] = $filters['dostavchik'];
        }
        if (!empty($filters['brand'])) {
            $where[] = "brand = :brand";
            $params[':brand'] = $filters['brand'];
        }
        if (!empty($filters['upload_status'])) {
            $where[] = "upload_status = :status";
            $params[':status'] = $filters['upload_status'];
        }
        if (!empty($filters['elektronika'])) {
            $where[] = "elektronika = :elek";
            $params[':elek'] = $filters['elektronika'];
        }
        if (!empty($filters['search'])) {
            $q = '%' . $filters['search'] . '%';
            $where[] = "(model LIKE :sq OR brand LIKE :sq2 OR ean_amazon LIKE :sq3 OR asin LIKE :sq4 OR nashe_sku LIKE :sq5 OR dostavchik LIKE :sq6 OR komentar LIKE :sq7 OR extra_data LIKE :sq8)";
            for ($i=1; $i<=8; $i++) $params[':sq'.($i===1?'':$i)] = $q;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $hasFormulaSort = $sortCol && (static::hasFormula($sortCol) || !isset(static::$colMap[$sortCol]));

        if (!$hasFormulaSort && $sortCol && isset(static::$colMap[$sortCol])) {
            $orderSQL = '';
            $dbCol    = static::$colMap[$sortCol];
            $dir      = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
            $numCols  = ['cena_konkurrent','cena_amazon','prodazhna_cena','cena_bez_dds','dds_prodazhna','amazon_taksi','cena_dostavchik','dds_dostavchik','transport_ot_dost','transport_klient','dds_transport','rezultat','dm_cena','nova_cena','dostaveni','sledvashta','korekciya'];
            $orderSQL = in_array($dbCol, $numCols, true) ? "ORDER BY CAST({$dbCol} AS REAL) {$dir}" : "ORDER BY {$dbCol} COLLATE NOCASE {$dir}";

            $countSql = "SELECT COUNT(*) as cnt FROM products {$whereSQL}";
            $stmt = $db->prepare($countSql); $stmt->execute($params); $total = (int)$stmt->fetchColumn();
            $pages = max(1, (int)ceil($total / $perPage));
            $page = min(max(1, $page), $pages); $offset = ($page - 1) * $perPage;
            $sql  = "SELECT * FROM products {$whereSQL} {$orderSQL} LIMIT :limit OFFSET :offset";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $products = array_map([static::class, 'rowToProduct'], $rows);
            return compact('products', 'total', 'pages', 'page', 'perPage');
        }

        $stmt = $db->prepare("SELECT * FROM products {$whereSQL}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $products = array_map([static::class, 'rowToProduct'], $rows);
        $total = count($products);

        if ($sortCol) {
            $dir = strtolower($sortDir) === 'desc' ? -1 : 1;
            usort($products, function($a, $b) use ($sortCol, $dir) {
                $av = $a[$sortCol] ?? '';
                $bv = $b[$sortCol] ?? '';
                $an = static::toNumber($av);
                $bn = static::toNumber($bv);
                if ($an !== null && $bn !== null) return $dir * ($an <=> $bn);
                return $dir * strcasecmp((string)$av, (string)$bv);
            });
        }

        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $products = array_slice($products, $offset, $perPage);
        return compact('products', 'total', 'pages', 'page', 'perPage');
    }

    public static function stats(): array {
        $rows = static::db()->query("SELECT * FROM products")->fetchAll();
        $total = $withAsin = $notUploaded = 0;
        $rez = [];
        foreach ($rows as $row) {
            $p = static::rowToProduct($row);
            $total++;
            if (!empty($p['ASIN'])) $withAsin++;
            if (($p['_upload_status'] ?? 'NOT_UPLOADED') === 'NOT_UPLOADED') $notUploaded++;
            if (isset($p['Резултат']) && $p['Резултат'] !== '') $rez[] = (float)$p['Резултат'];
        }
        $suppliers = static::realSupplierCount();
        $avgRez = $rez ? array_sum($rez) / count($rez) : 0;
        $posRez = count(array_filter($rez, fn($v) => $v > 0));
        $negRez = count(array_filter($rez, fn($v) => $v <= 0));
        return compact('total', 'withAsin', 'notUploaded', 'suppliers', 'avgRez', 'posRez', 'negRez');
    }

    public static function realSupplierCount(): int {
        $file = DATA_DIR . '/suppliers.json';
        if (!file_exists($file)) return 0;
        $suppliers = json_decode(file_get_contents($file), true) ?? [];
        return count(array_filter($suppliers, fn($s) => $s['active'] ?? true));
    }

    public static function distinct(string $excelField, string $filterBySupplier = ''): array {
        $dbCol = static::$colMap[$excelField] ?? null;
        if ($dbCol) {
            $db = static::db();
            $where = "WHERE {$dbCol} != ''";
            $params = [];
            if ($filterBySupplier !== '') { $where .= " AND dostavchik = :dost"; $params[':dost'] = $filterBySupplier; }
            $stmt = $db->prepare("SELECT DISTINCT {$dbCol} FROM products {$where} ORDER BY {$dbCol} COLLATE NOCASE");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // custom field from extra_data
        $stmt = static::db()->query("SELECT extra_data FROM products WHERE extra_data != '{}' AND extra_data IS NOT NULL");
        $vals = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
            $extra = json_decode($json, true) ?? [];
            $v = trim((string)($extra[$excelField] ?? ''));
            if ($v !== '') $vals[$v] = true;
        }
        $out = array_keys($vals);
        natcasesort($out);
        return array_values($out);
    }

    public static function upsertAll(array $products): int {
        $db  = static::db();
        $written = 0;
        $dbCols = array_values(static::$colMap);
        $sql = "INSERT OR REPLACE INTO products (ean_key, " . implode(', ', $dbCols) . ", extra_data) VALUES (:ean_key, " . implode(', ', array_map(fn($c) => ":$c", $dbCols)) . ", :extra_data)";
        $stmt = $db->prepare($sql);
        $db->beginTransaction();
        try {
            foreach ($products as $p) {
                $ean = Firebase::sanitizeKey($p['EAN Amazon'] ?? '');
                if ($ean === '') continue;
                $params = static::productToParams($p, $ean);
                $stmt->execute($params);
                $written++;
            }
            $db->commit();
        } catch (
Throwable $e) {
            $db->rollBack();
            Logger::error("ProductDB::upsertAll failed: " . $e->getMessage());
            throw $e;
        }
        return $written;
    }

    public static function replaceAll(array $products): int {
        $db = static::db();
        $db->exec("DELETE FROM products");
        $written = static::upsertAll($products);
        static::invalidateFormulaCache();
        return $written;
    }

    public static function updateField(string $ean, string $excelField, string $value): bool {
        $db  = static::db();
        $key = Firebase::sanitizeKey($ean);
        $dbCol = static::$colMap[$excelField] ?? null;
        if ($dbCol) {
            $stmt = $db->prepare("UPDATE products SET {$dbCol} = :val, updated_at = datetime('now') WHERE ean_key = :key");
            $stmt->execute([':val' => $value, ':key' => $key]);
            return $stmt->rowCount() > 0;
        }

        // custom field in extra_data
        $stmt = $db->prepare("SELECT extra_data FROM products WHERE ean_key = :key");
        $stmt->execute([':key' => $key]);
        $json = $stmt->fetchColumn();
        if ($json === false) return false;
        $extra = json_decode((string)$json, true) ?? [];
        $extra[$excelField] = $value;
        $stmt = $db->prepare("UPDATE products SET extra_data = :extra, updated_at = datetime('now') WHERE ean_key = :key");
        $stmt->execute([':extra' => json_encode($extra, JSON_UNESCAPED_UNICODE), ':key' => $key]);
        return $stmt->rowCount() > 0;
    }

    public static function insertOne(array $p): bool {
        $ean = Firebase::sanitizeKey($p['EAN Amazon'] ?? '');
        if ($ean === '') return false;
        try { static::upsertAll([$p]); return true; }
        catch (
Throwable $e) { Logger::error("ProductDB::insertOne: " . $e->getMessage()); return false; }
    }


    public static function deleteByEans(array $eans): int {
        $keys = array_values(array_filter(array_map(fn($e) => Firebase::sanitizeKey((string)$e), $eans)));
        if (!$keys) return 0;
        $db = static::db();
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare("DELETE FROM products WHERE ean_key IN ($placeholders)");
        $stmt->execute($keys);
        return $stmt->rowCount();
    }

    public static function deleteAll(): int {
        $db = static::db();
        $count = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $db->exec("DELETE FROM products");
        return $count;
    }

    public static function rebuildFromFirebase(): bool {
        $res = Firebase::get('/products');
        if (!$res['ok'] || !is_array($res['data'])) {
            Logger::error("ProductDB: Firebase read failed: " . ($res['error'] ?? ''));
            return false;
        }
        $products = array_values($res['data']);
        try {
            static::replaceAll($products);
            Logger::info("ProductDB: rebuilt with " . count($products) . " products");
            return true;
        } catch (
Throwable $e) {
            Logger::error("ProductDB: rebuild failed: " . $e->getMessage());
            return false;
        }
    }

    public static function status(): array {
        $file = DATA_DIR . '/products.sqlite';
        $exists = file_exists($file); $count = 0;
        if ($exists) { try { $count = (int)static::db()->query("SELECT COUNT(*) FROM products")->fetchColumn(); } catch (
Throwable $e) {} }
        return ['exists'=>$exists,'size'=>$exists?filesize($file):0,'modified'=>$exists?date('c', filemtime($file)):null,'count'=>$count,'engine'=>'SQLite (WAL mode)'];
    }

    public static function getAllColumnsMeta(): array {
        static::db();
        $core = [];
        $pos = 1;
        foreach (array_keys(static::$colMap) as $name) {
            if ($name === '_upload_status') continue;
            $core[] = [
                'name' => $name,
                'slug' => static::slugForColumn($name),
                'source' => 'core',
                'data_type' => static::guessType($name),
                'position' => $pos++,
                'is_formula' => static::hasFormula($name),
                'is_custom' => false,
                'is_editable' => !static::hasFormula($name),
            ];
        }
        $stmt = static::db()->query("SELECT name, slug, source, data_type, position, is_formula, created_by, created_at FROM product_columns ORDER BY position, id");
        $custom = [];
        foreach ($stmt->fetchAll() as $row) {
            $custom[] = [
                'name' => $row['name'], 'slug' => $row['slug'] ?? static::slugify($row['name']), 'source' => $row['source'], 'data_type' => $row['data_type'],
                'position' => (int)$row['position'], 'is_formula' => (bool)$row['is_formula'],
                'is_custom' => true, 'is_editable' => !(bool)$row['is_formula'],
                'created_by' => $row['created_by'] ?? '', 'created_at' => $row['created_at'] ?? '',
            ];
        }
        return array_merge($core, $custom);
    }

    public static function getAllColumnNames(): array {
        return array_map(fn($c) => $c['name'], static::getAllColumnsMeta());
    }

    public static function addCustomColumn(string $name, string $user = 'admin', string $type = 'text'): array {
        $name = trim($name);
        if ($name === '') return ['ok'=>false,'error'=>'Името на колоната е задължително.'];
        if (mb_strlen($name) > 80) return ['ok'=>false,'error'=>'Името е твърде дълго.'];
        if (isset(static::$colMap[$name]) || in_array($name, static::getCustomColumnNames(), true)) return ['ok'=>false,'error'=>'Колоната вече съществува.'];
        $slug = static::slugify($name);
        $db = static::db();
        $pos = (int)$db->query("SELECT COALESCE(MAX(position),0)+1 FROM product_columns")->fetchColumn();
        $stmt = $db->prepare("INSERT INTO product_columns (name, slug, data_type, source, is_formula, position, created_by) VALUES (:name,:slug,:type,'custom',0,:pos,:by)");
        $stmt->execute([':name'=>$name, ':slug'=>$slug, ':type'=>$type, ':pos'=>$pos, ':by'=>$user]);
        static::$columnSlugCache = null;
        Logger::audit('column.created', ['column'=>$name,'by'=>$user]);
        return ['ok'=>true,'name'=>$name];
    }

    public static function getCustomColumnNames(): array {
        $stmt = static::db()->query("SELECT name FROM product_columns ORDER BY position, id");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function slugify(string $name): string {
        $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($name));
        return trim((string)$slug, '_') ?: 'col_' . substr(md5($name), 0, 8);
    }

    public static function slugForColumn(string $columnName): string {
        if (isset(static::$columnSlugCache[$columnName])) return static::$columnSlugCache[$columnName];
        if (isset(static::$colMap[$columnName])) return static::$columnSlugCache[$columnName] = static::$colMap[$columnName];
        $stmt = static::db()->prepare("SELECT slug FROM product_columns WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $columnName]);
        $slug = (string)($stmt->fetchColumn() ?: '');
        if ($slug === '') $slug = static::slugify($columnName);
        return static::$columnSlugCache[$columnName] = $slug;
    }

    public static function getColumnSlugMap(): array {
        if (static::$columnSlugCache !== null) return static::$columnSlugCache;
        $map = [];
        foreach (array_keys(static::$colMap) as $name) {
            if ($name === '_upload_status') continue;
            $map[$name] = static::$colMap[$name];
        }
        $stmt = static::db()->query("SELECT name, slug FROM product_columns");
        foreach ($stmt->fetchAll() as $row) $map[$row['name']] = $row['slug'];
        return static::$columnSlugCache = $map;
    }

    private static function syncSettingsFormulaStore(): void {
        $settings = Settings::get();
        $settings['formulas'] = [];
        $slugMap = static::getColumnSlugMap();
        foreach (static::getFormulaMap() as $columnName => $formula) {
            $targetSlug = $slugMap[$columnName] ?? static::slugify($columnName);
            $settings['formulas'][$targetSlug] = static::tokensToSettingsExpression($formula['tokens']);
        }
        Settings::save($settings);
    }

    private static function migrateLegacyFormulas(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        $db = static::$pdo;
        if (!$db) return;

        $settings = Settings::get();
        $settingsFormulas = is_array($settings['formulas'] ?? null) ? $settings['formulas'] : [];
        $existing = (int)$db->query("SELECT COUNT(*) FROM column_formulas")->fetchColumn();

        if ($existing === 0) {
            $seed = static::legacySeedFormulas();
            if (!$settingsFormulas) {
                $settings['formulas'] = $seed['settings'];
                Settings::save($settings);
                $settingsFormulas = $settings['formulas'];
            }
            foreach ($seed['db'] as $columnName => $payload) {
                if (!isset($settingsFormulas[$payload['slug']])) {
                    $settingsFormulas[$payload['slug']] = static::tokensToSettingsExpression($payload['tokens']);
                }
                static::saveFormula($columnName, $payload['tokens'], $payload['rounding'], 'system-migration');
            }
        } elseif (!$settingsFormulas) {
            static::syncSettingsFormulaStore();
        }
    }

    private static function legacySeedFormulas(): array {
        $defs = [
            'Продажна Цена в Амазон  - Brutto' => [
                ['type' => 'column', 'value' => 'Цена Доставчик -Netto'],
                ['type' => 'op', 'value' => '+'],
                ['type' => 'column', 'value' => 'Транспорт от Доставчик до нас'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'number', 'value' => '2'],
                ['type' => 'op', 'value' => '+'],
                ['type' => 'column', 'value' => 'Транспорт до кр. лиент  Netto'],
            ],
            'Цена без ДДС' => [
                ['type' => 'column', 'value' => 'Продажна Цена в Амазон  - Brutto'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'number', 'value' => '1.20'],
            ],
            'ДДС от продажна цена' => [
                ['type' => 'column', 'value' => 'Продажна Цена в Амазон  - Brutto'],
                ['type' => 'op', 'value' => '-'],
                ['type' => 'column', 'value' => 'Цена без ДДС'],
            ],
            'ДДС  от Цена Доставчик' => [
                ['type' => 'column', 'value' => 'Цена Доставчик -Netto'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'number', 'value' => '0.20'],
            ],
            'ДДС  от Транспорт до кр. лиент' => [
                ['type' => 'column', 'value' => 'Транспорт до кр. лиент  Netto'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'number', 'value' => '0.20'],
            ],
            'Резултат' => [
                ['type' => 'column', 'value' => 'Цена без ДДС'],
                ['type' => 'op', 'value' => '-'],
                ['type' => 'column', 'value' => 'Amazon Такси'],
                ['type' => 'op', 'value' => '-'],
                ['type' => 'column', 'value' => 'Цена Доставчик -Netto'],
                ['type' => 'op', 'value' => '-'],
                ['type' => 'column', 'value' => 'Транспорт от Доставчик до нас'],
                ['type' => 'op', 'value' => '-'],
                ['type' => 'column', 'value' => 'Транспорт до кр. лиент  Netto'],
            ],
        ];

        $dbDefs = [];
        $settingsDefs = [];
        foreach ($defs as $columnName => $tokens) {
            $slug = static::slugForColumn($columnName);
            $dbDefs[$columnName] = ['slug' => $slug, 'tokens' => $tokens, 'rounding' => 2];
            $settingsDefs[$slug] = static::tokensToSettingsExpression($tokens);
        }
        return ['db' => $dbDefs, 'settings' => $settingsDefs];
    }


    public static function getFormulaExportRows(): array {
        $columns = static::getAllColumnsMeta();
        $formulaMap = static::getFormulaMap();
        $rows = [];
        foreach ($columns as $col) {
            if ((($col['data_type'] ?? 'text') !== 'number') && empty($col['is_formula'])) continue;
            $formula = $formulaMap[$col['name']] ?? null;
            $rows[] = [
                'Колона' => $col['name'],
                'Статус' => $formula ? 'Изчислима' : 'Статична',
                'Формула' => $formula ? static::tokensToExpression($formula['tokens']) : '',
                'Закръгляне' => (string)($formula['rounding'] ?? 2),
            ];
        }
        return $rows;
    }


    public static function previewFormulaImportRows(array $rows): array {
        static::db();
        $formulaMap = static::getFormulaMap();
        $allColumns = static::getAllColumnNames();
        $preview = [];
        $summary = ['apply'=>0,'clear'=>0,'skip'=>0,'error'=>0];
        foreach ($rows as $idx => $row) {
            $columnName = trim((string)($row['Колона'] ?? $row['Column'] ?? ''));
            if ($columnName === '') {
                $summary['skip']++;
                continue;
            }
            $status = trim((string)($row['Статус'] ?? $row['Status'] ?? ''));
            $expr = trim((string)($row['Формула'] ?? $row['Formula'] ?? ''));
            $roundingRaw = trim((string)($row['Закръгляне'] ?? $row['Rounding'] ?? '2'));
            $rounding = is_numeric(str_replace(',', '.', $roundingRaw)) ? (int)str_replace(',', '.', $roundingRaw) : 2;
            $current = $formulaMap[$columnName] ?? null;
            $currentExpr = $current ? static::tokensToExpression($current['tokens']) : '';
            $currentRounding = (int)($current['rounding'] ?? 2);
            if (!in_array($columnName, $allColumns, true)) {
                $preview[] = [
                    'row' => $idx + 2,
                    'column' => $columnName,
                    'status' => $status,
                    'current_formula' => $currentExpr,
                    'new_formula' => $expr,
                    'current_rounding' => $currentRounding,
                    'new_rounding' => $rounding,
                    'action' => 'Грешка',
                    'error' => 'Непозната колона.',
                ];
                $summary['error']++;
                continue;
            }
            if ($expr === '') {
                $action = $current ? 'Изчисти' : 'Без промяна';
                $preview[] = [
                    'row' => $idx + 2,
                    'column' => $columnName,
                    'status' => $status,
                    'current_formula' => $currentExpr,
                    'new_formula' => '',
                    'current_rounding' => $currentRounding,
                    'new_rounding' => $rounding,
                    'action' => $action,
                    'error' => '',
                ];
                $summary[$current ? 'clear' : 'skip']++;
                continue;
            }
            $parsed = static::parseFormulaExpression($expr);
            if (!$parsed['ok']) {
                $preview[] = [
                    'row' => $idx + 2,
                    'column' => $columnName,
                    'status' => $status,
                    'current_formula' => $currentExpr,
                    'new_formula' => $expr,
                    'current_rounding' => $currentRounding,
                    'new_rounding' => $rounding,
                    'action' => 'Грешка',
                    'error' => $parsed['error'] ?? 'Невалидна формула.',
                ];
                $summary['error']++;
                continue;
            }
            $normalizedNew = static::tokensToExpression($parsed['tokens']);
            $same = ($normalizedNew === $currentExpr) && ($rounding === $currentRounding);
            $preview[] = [
                'row' => $idx + 2,
                'column' => $columnName,
                'status' => $status,
                'current_formula' => $currentExpr,
                'new_formula' => $normalizedNew,
                'current_rounding' => $currentRounding,
                'new_rounding' => $rounding,
                'action' => $same ? 'Без промяна' : ($current ? 'Обнови' : 'Добави'),
                'error' => '',
            ];
            $summary[$same ? 'skip' : 'apply']++;
        }
        return ['ok' => $summary['error'] === 0, 'summary' => $summary, 'rows' => $preview];
    }

    public static function importFormulasFromRows(array $rows, string $user): array {
        static::db();
        $formulaMap = static::getFormulaMap();
        $applied = 0; $cleared = 0; $skipped = 0; $errors = [];
        foreach ($rows as $row) {
            $columnName = trim((string)($row['Колона'] ?? $row['Column'] ?? ''));
            if ($columnName === '') { $skipped++; continue; }
            if (!in_array($columnName, static::getAllColumnNames(), true)) {
                $errors[] = 'Непозната колона: ' . $columnName;
                continue;
            }
            $status = trim((string)($row['Статус'] ?? $row['Status'] ?? ''));
            $expr = trim((string)($row['Формула'] ?? $row['Formula'] ?? ''));
            $roundingRaw = trim((string)($row['Закръгляне'] ?? $row['Rounding'] ?? '2'));
            $rounding = is_numeric(str_replace(',', '.', $roundingRaw)) ? (int)str_replace(',', '.', $roundingRaw) : 2;
            if ($expr === '') {
                $statusLower = mb_strtolower($status);
                if ($statusLower === 'статична' || $statusLower === 'static' || isset($formulaMap[$columnName])) {
                    static::clearFormula($columnName, $user);
                    $cleared++;
                } else {
                    $skipped++;
                }
                continue;
            }
            $parsed = static::parseFormulaExpression($expr);
            if (!$parsed['ok']) {
                $errors[] = $columnName . ': ' . $parsed['error'];
                continue;
            }
            $res = static::saveFormula($columnName, $parsed['tokens'], $rounding, $user);
            if (!$res['ok']) {
                $errors[] = $columnName . ': ' . ($res['error'] ?? 'Грешка при запис.');
                continue;
            }
            $applied++;
        }
        return [
            'ok' => empty($errors),
            'applied' => $applied,
            'cleared' => $cleared,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => 'Импортирани формули: ' . $applied . ', изчистени: ' . $cleared . ', пропуснати: ' . $skipped,
        ];
    }

    public static function parseFormulaExpression(string $expr): array {
        $expr = trim($expr);
        if ($expr === '') return ['ok' => false, 'error' => 'Празна формула.'];
        $tokens = [];
        $len = mb_strlen($expr, 'UTF-8');
        $i = 0;
        $columnLookup = array_fill_keys(static::getAllColumnNames(), '');
        while ($i < $len) {
            $rest = mb_substr($expr, $i, null, 'UTF-8');
            if (preg_match('/^\s+/u', $rest, $m)) { $i += mb_strlen($m[0], 'UTF-8'); continue; }
            if (preg_match('/^\[([^\]]+)\]/u', $rest, $m)) {
                $name = trim($m[1]);
                $resolved = static::resolveColumnName($name, $columnLookup, []);
                if (!in_array($resolved, static::getAllColumnNames(), true)) return ['ok'=>false,'error'=>'Непозната колона във формула: ' . $name];
                $tokens[] = ['type'=>'column','value'=>$resolved];
                $i += mb_strlen($m[0], 'UTF-8');
                continue;
            }
            if (preg_match('/^[\+\-\*\/\(\)]/u', $rest, $m)) {
                $tokens[] = ['type'=>'op','value'=>$m[0]];
                $i += mb_strlen($m[0], 'UTF-8');
                continue;
            }
            if (preg_match('/^\d+(?:[.,]\d+)?/u', $rest, $m)) {
                $tokens[] = ['type'=>'number','value'=>str_replace(',', '.', $m[0])];
                $i += mb_strlen($m[0], 'UTF-8');
                continue;
            }
            return ['ok'=>false,'error'=>'Неразпознат елемент около: ' . mb_substr($rest, 0, 20, 'UTF-8')];
        }
        $tokens = static::normalizeFormulaTokens($tokens);
        return $tokens ? ['ok'=>true,'tokens'=>$tokens] : ['ok'=>false,'error'=>'Формулата е празна след парсване.'];
    }


    public static function getFormulaMap(): array {
        if (static::$formulaCache !== null) return static::$formulaCache;
        $stmt = static::db()->query("SELECT column_name, formula_expression, formula_tokens, rounding, updated_by, updated_at FROM column_formulas");
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $tokens = json_decode((string)$row['formula_tokens'], true);
            $map[$row['column_name']] = [
                'expression' => $row['formula_expression'],
                'tokens' => is_array($tokens) ? $tokens : [],
                'rounding' => (int)$row['rounding'],
                'updated_by' => $row['updated_by'] ?? '',
                'updated_at' => $row['updated_at'] ?? '',
            ];
        }
        return static::$formulaCache = $map;
    }

    public static function hasFormula(string $columnName): bool {
        $map = static::getFormulaMap();
        return isset($map[$columnName]);
    }

    public static function getFormula(string $columnName): ?array {
        $map = static::getFormulaMap();
        return $map[$columnName] ?? null;
    }

    public static function getFormulaDependencies(array $tokens): array {
        $deps = [];
        foreach ($tokens as $t) if (($t['type'] ?? '') === 'column' && !empty($t['value'])) $deps[$t['value']] = true;
        return array_keys($deps);
    }

    public static function saveFormula(string $columnName, array $tokens, int $rounding, string $user): array {
        static::db();
        $columnName = trim($columnName);
        if ($columnName === '' || !in_array($columnName, static::getAllColumnNames(), true)) return ['ok'=>false,'error'=>'Невалидна колона.'];
        $tokens = static::normalizeFormulaTokens($tokens);
        if (!$tokens) return ['ok'=>false,'error'=>'Формулата е празна.'];
        $rounding = max(0, min(6, $rounding));
        $expr = static::tokensToExpression($tokens);
        $deps = static::getFormulaDependencies($tokens);
        if (in_array($columnName, $deps, true)) return ['ok'=>false,'error'=>'Колоната не може да зависи от себе си.'];
        $cycle = static::detectCircularDependency($columnName, $deps);
        if ($cycle) return ['ok'=>false,'error'=>'Circular dependency: ' . implode(' → ', $cycle)];
        $db = static::db();
        $previous = static::getFormula($columnName);
        $stmt = $db->prepare("INSERT INTO column_formulas (column_name, formula_expression, formula_tokens, rounding, updated_by, updated_at) VALUES (:col,:expr,:tokens,:rounding,:by,CURRENT_TIMESTAMP) ON CONFLICT(column_name) DO UPDATE SET formula_expression=excluded.formula_expression, formula_tokens=excluded.formula_tokens, rounding=excluded.rounding, updated_by=excluded.updated_by, updated_at=CURRENT_TIMESTAMP");
        $tokensJson = json_encode($tokens, JSON_UNESCAPED_UNICODE);
        $stmt->execute([':col'=>$columnName, ':expr'=>$expr, ':tokens'=>$tokensJson, ':rounding'=>$rounding, ':by'=>$user]);
        $prevTokens = $previous['tokens'] ?? [];
        $historyExpr = (string)($previous['expression'] ?? '');
        if ($historyExpr === '' && is_array($prevTokens) && $prevTokens) $historyExpr = static::tokensToExpression($prevTokens);
        $historyTokens = json_encode($prevTokens, JSON_UNESCAPED_UNICODE);
        $historyRounding = (int)($previous['rounding'] ?? 2);
        static::recordFormulaVersion($columnName, $historyExpr, is_array($prevTokens) ? $prevTokens : [], $historyRounding, $user);
        $stmt2 = $db->prepare("INSERT INTO formula_history (column_name, action, formula_expression, formula_tokens, rounding, changed_by) VALUES (:col, 'save', :expr, :tokens, :rounding, :by)");
        $stmt2->execute([':col'=>$columnName, ':expr'=>$historyExpr, ':tokens'=>$historyTokens, ':rounding'=>$historyRounding, ':by'=>$user]);
        $stmt3 = $db->prepare("UPDATE product_columns SET is_formula = 1 WHERE name = :name");
        $stmt3->execute([':name'=>$columnName]);
        static::invalidateFormulaCache();
        static::syncSettingsFormulaStore();
        Logger::audit('formula.saved', ['column'=>$columnName,'by'=>$user,'expression'=>$expr]);
        return ['ok'=>true];
    }

    public static function clearFormula(string $columnName, string $user): array {
        $db = static::db();
        $f = static::getFormula($columnName);
        if (!$f) return ['ok'=>true];
        $stmt = $db->prepare("DELETE FROM column_formulas WHERE column_name = :name");
        $stmt->execute([':name'=>$columnName]);
        $historyExpr = (string)($f['expression'] ?? '');
        if ($historyExpr === '' && !empty($f['tokens'])) $historyExpr = static::tokensToExpression($f['tokens']);
        static::recordFormulaVersion($columnName, $historyExpr, $f['tokens'], (int)$f['rounding'], $user);
        $stmt2 = $db->prepare("INSERT INTO formula_history (column_name, action, formula_expression, formula_tokens, rounding, changed_by) VALUES (:col, 'clear', :expr, :tokens, :rounding, :by)");
        $stmt2->execute([':col'=>$columnName, ':expr'=>$historyExpr, ':tokens'=>json_encode($f['tokens'], JSON_UNESCAPED_UNICODE), ':rounding'=>$f['rounding'], ':by'=>$user]);
        $stmt3 = $db->prepare("UPDATE product_columns SET is_formula = 0 WHERE name = :name");
        $stmt3->execute([':name'=>$columnName]);
        static::invalidateFormulaCache();
        static::syncSettingsFormulaStore();
        Logger::audit('formula.cleared', ['column'=>$columnName,'by'=>$user]);
        return ['ok'=>true];
    }

    private static function recordFormulaVersion(string $columnName, string $expression, array $tokens, int $rounding, string $user): void {
        $db = static::db();
        $stmt = $db->prepare("INSERT INTO formula_versions (column_name, formula_expression, formula_tokens, rounding, changed_by) VALUES (:col, :expr, :tokens, :rounding, :by)");
        $stmt->execute([
            ':col' => $columnName,
            ':expr' => $expression,
            ':tokens' => json_encode($tokens, JSON_UNESCAPED_UNICODE),
            ':rounding' => $rounding,
            ':by' => $user,
        ]);
    }

    public static function restoreFormulaVersion(int $versionId, string $user): array {
        $db = static::db();
        $stmt = $db->prepare("SELECT * FROM formula_versions WHERE id = :id");
        $stmt->execute([':id' => $versionId]);
        $row = $stmt->fetch();
        if (!$row) return ['ok' => false, 'error' => 'Версията не е намерена.'];
        $tokens = json_decode((string)($row['formula_tokens'] ?? '[]'), true);
        if (!is_array($tokens) || !$tokens) {
            return static::clearFormula((string)$row['column_name'], $user);
        }
        $result = static::saveFormula((string)$row['column_name'], $tokens, (int)($row['rounding'] ?? 2), $user);
        if (($result['ok'] ?? false)) {
            Logger::audit('formula.restored', ['column' => $row['column_name'], 'version_id' => $versionId, 'by' => $user]);
        }
        return $result;
    }

    public static function getFormulaVersions(?string $columnName = null): array {
        $db = static::db();
        if ($columnName) {
            $stmt = $db->prepare("SELECT * FROM formula_versions WHERE column_name = :name ORDER BY id DESC LIMIT 100");
            $stmt->execute([':name' => $columnName]);
        } else {
            $stmt = $db->query("SELECT * FROM formula_versions ORDER BY id DESC LIMIT 200");
        }
        return $stmt->fetchAll();
    }

    public static function getFormulaHistory(?string $columnName = null): array {
        $db = static::db();
        if ($columnName) {
            $stmt = $db->prepare("SELECT * FROM formula_history WHERE column_name = :name ORDER BY id DESC LIMIT 100");
            $stmt->execute([':name'=>$columnName]);
        } else {
            $stmt = $db->query("SELECT * FROM formula_history ORDER BY id DESC LIMIT 200");
        }
        return $stmt->fetchAll();
    }

    private static function invalidateFormulaCache(): void { static::$formulaCache = null; }

    public static function applyFormulasToProduct(array $product): array {
        $formulas = static::getFormulaMap();
        if (!$formulas) return $product;
        $memo = [];
        foreach ($formulas as $col => $_) {
            $product[$col] = static::evaluateFormulaColumn($col, $product, $formulas, $memo, []);
        }
        return $product;
    }

    private static function evaluateFormulaColumn(string $column, array &$product, array $formulas, array &$memo, array $visiting) {
        if (array_key_exists($column, $memo)) return $memo[$column];
        if (!isset($formulas[$column])) return $product[$column] ?? '';
        if (isset($visiting[$column])) return '';
        $visiting[$column] = true;
        $expr = '';
        foreach (static::normalizeFormulaTokens($formulas[$column]['tokens']) as $t) {
            $type = $t['type'] ?? ''; $value = $t['value'] ?? '';
            if ($type === 'column') {
                $dep = static::resolveColumnName((string)$value, $product, $formulas);
                $raw = isset($formulas[$dep]) ? static::evaluateFormulaColumn($dep, $product, $formulas, $memo, $visiting) : ($product[$dep] ?? '');
                $expr .= static::safeNumericLiteral($raw);
            } elseif ($type === 'number') {
                $expr .= static::safeNumericLiteral($value);
            } elseif ($type === 'op' && in_array($value, ['+','-','*','/','(',')'], true)) {
                $expr .= $value;
            }
        }
        $result = static::evaluateMathExpression($expr);
        if ($result === null) {
            $memo[$column] = '';
        } else {
            $rounded = round($result, (int)($formulas[$column]['rounding'] ?? 2));
            $memo[$column] = number_format($rounded, (int)($formulas[$column]['rounding'] ?? 2), '.', '');
        }
        return $memo[$column];
    }

    private static function safeNumericLiteral($value): string {
        $n = static::toNumber($value);
        return $n === null ? '0' : (string)$n;
    }

    private static function toNumber($value): ?float {
        $s = trim((string)$value);
        if ($s === '') return null;
        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        if (substr_count($s, ',') === 1 && substr_count($s, '.') === 0) $s = str_replace(',', '.', $s);
        else $s = str_replace(',', '', $s);
        return is_numeric($s) ? (float)$s : null;
    }

    private static function evaluateMathExpression(string $expr): ?float {
        if ($expr === '' || preg_match('/[^0-9\.\+\-\*\/\(\) ]/', $expr)) return null;
        try {
            set_error_handler(function() {});
            $val = eval('return ' . $expr . ';');
            restore_error_handler();
            return is_numeric($val) && is_finite((float)$val) ? (float)$val : null;
        } catch (
Throwable $e) {
            restore_error_handler();
            return null;
        }
    }

    public static function tokensToExpression(array $tokens): string {
        $parts = [];
        foreach ($tokens as $t) {
            if (($t['type'] ?? '') === 'column') $parts[] = '[' . $t['value'] . ']';
            else $parts[] = (string)$t['value'];
        }
        return implode(' ', $parts);
    }

    public static function tokensToSettingsExpression(array $tokens): string {
        $parts = [];
        foreach ($tokens as $t) {
            if (($t['type'] ?? '') === 'column') $parts[] = '{' . static::slugForColumn((string)$t['value']) . '}';
            else $parts[] = trim((string)$t['value']);
        }
        return trim(implode(' ', array_filter($parts, fn($p) => $p !== '')));
    }

    public static function tokensToHumanExpression(array $tokens): string {
        $parts = [];
        foreach ($tokens as $t) $parts[] = (string)($t['value'] ?? '');
        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts, fn($p) => $p !== ''))));
    }


    private static function normalizeFormulaTokens(array $tokens): array {
        $out = [];
        foreach ($tokens as $t) {
            if (!is_array($t) || !isset($t['type']) || !array_key_exists('value', $t)) continue;
            $type = (string)$t['type'];
            $value = is_string($t['value']) ? $t['value'] : (string)$t['value'];
            if ($type === 'column') {
                $value = trim($value);
                if ($value !== '') $out[] = ['type' => 'column', 'value' => $value];
                continue;
            }
            if ($type === 'number' || $type === 'op') {
                $value = trim($value);
                if ($value !== '') $out[] = ['type' => $type, 'value' => $value];
                continue;
            }
            if ($type === 'text') {
                preg_match_all('/\d+(?:[\.,]\d+)?|[+\-*\/()]/u', $value, $m);
                foreach ($m[0] as $part) {
                    if (preg_match('/^[+\-*\/()]$/', $part)) $out[] = ['type' => 'op', 'value' => $part];
                    else $out[] = ['type' => 'number', 'value' => str_replace(',', '.', $part)];
                }
            }
        }
        return $out;
    }

    private static function normalizeColumnKey(string $name): string {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = str_replace(['  ', '   '], ' ', preg_replace('/\s+/u', ' ', $name));
        $name = str_replace(['амазон', 'amazon'], 'amazon', $name);
        $name = str_replace([' - ', '-'], '-', $name);
        return $name;
    }

    private static function resolveColumnName(string $name, array $product, array $formulas): string {
        if (array_key_exists($name, $product) || isset($formulas[$name])) return $name;
        $target = static::normalizeColumnKey($name);
        foreach (array_keys($product + $formulas) as $candidate) {
            if (static::normalizeColumnKey((string)$candidate) === $target) return (string)$candidate;
        }
        return $name;
    }

    private static function detectCircularDependency(string $target, array $newDeps): array {
        $graph = [];
        foreach (static::getFormulaMap() as $col => $f) $graph[$col] = static::getFormulaDependencies($f['tokens']);
        $graph[$target] = $newDeps;
        $visited = []; $stack = [];
        $dfs = function($node) use (&$dfs, &$graph, &$visited, &$stack) {
            $visited[$node] = true; $stack[$node] = true;
            foreach ($graph[$node] ?? [] as $dep) {
                if (!isset($graph[$dep])) continue;
                if (!isset($visited[$dep])) {
                    $r = $dfs($dep); if ($r) return array_merge([$node], $r);
                } elseif (!empty($stack[$dep])) {
                    return [$node, $dep];
                }
            }
            unset($stack[$node]);
            return [];
        };
        return $dfs($target);
    }

    public static function guessType(string $name): string {
        if (str_contains($name, 'Цена') || str_contains($name, 'Транспорт') || str_contains($name, 'ДДС') || str_contains($name, 'Такси') || $name === 'Резултат' || $name === 'DM цена') return 'number';
        return 'text';
    }
}
