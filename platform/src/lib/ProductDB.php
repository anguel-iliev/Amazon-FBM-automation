<?php
/**
 * ProductDB — SQLite база данни за продукти
 *
 * АРХИТЕКТУРА:
 *   Firebase = MASTER (истинско хранилище, синхронизира при import)
 *   data/products.sqlite = РАБОТНА БАЗА (всички четения и записи)
 *
 * ЗАЩО SQLite вместо JSON кеш:
 *   - Row-level locking: двама потребители могат да пишат едновременно
 *     без да си "унищожават" промените
 *   - SQL заявки: филтрира само нужните редове, не зарежда всичко в памет
 *   - При 50 000 продукта: ~0.05 сек вместо ~2 сек
 */
class ProductDB {

    private static ?PDO $pdo = null;

    // ── Connection (lazy, singleton) ──────────────────────────
    private static function db(): PDO {
        if (static::$pdo !== null) return static::$pdo;

        $file = DATA_DIR . '/products.sqlite';
        $new  = !file_exists($file);

        static::$pdo = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Performance pragmas
        static::$pdo->exec("PRAGMA journal_mode = WAL");   // Write-Ahead Logging: safe concurrent writes
        static::$pdo->exec("PRAGMA synchronous  = NORMAL"); // Faster than FULL, still safe
        static::$pdo->exec("PRAGMA foreign_keys = ON");

        if ($new) {
            static::createSchema(static::$pdo);
            Logger::info("ProductDB: created new SQLite database");
        }

        return static::$pdo;
    }

    // ── Schema ────────────────────────────────────────────────
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
    }

    // ── Column mapping: Excel name → DB column ────────────────
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
        'ДДС от продажна цена'            => 'dds_prodazhna',
        'Amazon Такси'                     => 'amazon_taksi',
        'Цена Доставчик -Netto'           => 'cena_dostavchik',
        'ДДС  от Цена Доставчик'          => 'dds_dostavchik',
        'Транспорт от Доставчик до нас'   => 'transport_ot_dost',
        'Транспорт до кр. лиент  Netto'   => 'transport_klient',
        'ДДС  от Транспорт до кр. лиент'  => 'dds_transport',
        'Резултат'                         => 'rezultat',
        'Намерена 2ра обява'              => 'namerena_2ra',
        'Цена за ES FR IT'                => 'cena_es_fr_it',
        'DM цена'                          => 'dm_cena',
        'Нова цена след намаление'        => 'nova_cena',
        'Доставени'                        => 'dostaveni',
        'За следваща поръчка'             => 'sledvashta',
        'Електоника'                       => 'elektronika',
        'Корекция  на цена'               => 'korekciya',
        'Коментар'                         => 'komentar',
        '_upload_status'                   => 'upload_status',
    ];

    // Reverse map: DB column → Excel name
    private static function reverseMap(): array {
        static $rev = null;
        if ($rev === null) {
            $rev = array_flip(static::$colMap);
            $rev['upload_status'] = '_upload_status';
        }
        return $rev;
    }

    // ── Convert DB row → product array (Excel column names) ───
    private static function rowToProduct(array $row): array {
        $rev = static::reverseMap();
        $p   = [];
        foreach ($row as $col => $val) {
            if ($col === 'ean_key' || $col === 'extra_data' || $col === 'updated_at') continue;
            $excelName = $rev[$col] ?? $col;
            $p[$excelName] = $val ?? '';
        }
        // Merge extra_data (future-proof for unknown columns)
        if (!empty($row['extra_data']) && $row['extra_data'] !== '{}') {
            $extra = json_decode($row['extra_data'], true) ?? [];
            foreach ($extra as $k => $v) {
                if (!isset($p[$k])) $p[$k] = $v;
            }
        }
        return $p;
    }

    // ── Convert product array → DB params ────────────────────
    private static function productToParams(array $p, string $eanKey): array {
        $known  = array_keys(static::$colMap);
        $extra  = [];
        $params = [':ean_key' => $eanKey];

        foreach ($p as $excelKey => $val) {
            $dbCol = static::$colMap[$excelKey] ?? null;
            if ($dbCol && $dbCol !== 'ean_key') {
                $params[':' . $dbCol] = (string)($val ?? '');
            } elseif ($excelKey !== '_upload_status' && !in_array($excelKey, $known)) {
                $extra[$excelKey] = $val;
            }
        }

        // Ensure ean_amazon is set
        if (!isset($params[':ean_amazon'])) {
            $params[':ean_amazon'] = $eanKey;
        }
        $params[':extra_data'] = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : '{}';

        return $params;
    }

    // ══════════════════════════════════════════════════════════
    //  SERVER-SIDE QUERY (Filter + Sort + Paginate via SQL)
    // ══════════════════════════════════════════════════════════
    public static function query(
        array  $filters = [],
        string $sortCol = '',
        string $sortDir = 'asc',
        int    $page    = 1,
        int    $perPage = 50
    ): array {
        $db     = static::db();
        $where  = [];
        $params = [];

        // ── Build WHERE ────────────────────────────────────────
        if (!empty($filters['dostavchik'])) {
            $where[]          = "dostavchik = :dostavchik";
            $params[':dostavchik'] = $filters['dostavchik'];
        }
        if (!empty($filters['brand'])) {
            $where[]        = "brand = :brand";
            $params[':brand'] = $filters['brand'];
        }
        if (!empty($filters['upload_status'])) {
            $where[]          = "upload_status = :status";
            $params[':status'] = $filters['upload_status'];
        }
        if (!empty($filters['elektronika'])) {
            $where[]          = "elektronika = :elek";
            $params[':elek']   = $filters['elektronika'];
        }
        if (!empty($filters['search'])) {
            $q = '%' . $filters['search'] . '%';
            $where[] = "(model LIKE :sq OR brand LIKE :sq2 OR ean_amazon LIKE :sq3 OR asin LIKE :sq4 OR nashe_sku LIKE :sq5 OR dostavchik LIKE :sq6 OR komentar LIKE :sq7)";
            $params[':sq']  = $q; $params[':sq2'] = $q; $params[':sq3'] = $q;
            $params[':sq4'] = $q; $params[':sq5'] = $q; $params[':sq6'] = $q;
            $params[':sq7'] = $q;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // ── COUNT ──────────────────────────────────────────────
        $countSql = "SELECT COUNT(*) as cnt FROM products {$whereSQL}";
        $stmt     = $db->prepare($countSql);
        $stmt->execute($params);
        $total    = (int)$stmt->fetchColumn();

        // ── Sort ───────────────────────────────────────────────
        $orderSQL = '';
        if ($sortCol && isset(static::$colMap[$sortCol])) {
            $dbCol    = static::$colMap[$sortCol];
            $dir      = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
            // Numeric columns sorted numerically
            $numCols  = ['cena_konkurrent','cena_amazon','prodazhna_cena','cena_bez_dds',
                         'dds_prodazhna','amazon_taksi','cena_dostavchik','dds_dostavchik',
                         'transport_ot_dost','transport_klient','dds_transport','rezultat',
                         'dm_cena','nova_cena','dostaveni','sledvashta','korekciya'];
            if (in_array($dbCol, $numCols)) {
                $orderSQL = "ORDER BY CAST({$dbCol} AS REAL) {$dir}";
            } else {
                $orderSQL = "ORDER BY {$dbCol} COLLATE NOCASE {$dir}";
            }
        }

        // ── Paginate ───────────────────────────────────────────
        $pages   = max(1, (int)ceil($total / $perPage));
        $page    = min(max(1, $page), $pages);
        $offset  = ($page - 1) * $perPage;

        $sql  = "SELECT * FROM products {$whereSQL} {$orderSQL} LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        $rows     = $stmt->fetchAll();
        $products = array_map([static::class, 'rowToProduct'], $rows);

        return compact('products', 'total', 'pages', 'page', 'perPage');
    }

    // ── Stats ──────────────────────────────────────────────────
    public static function stats(): array {
        $db = static::db();

        $total       = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $withAsin    = (int)$db->query("SELECT COUNT(*) FROM products WHERE asin != ''")->fetchColumn();
        $notUploaded = (int)$db->query("SELECT COUNT(*) FROM products WHERE upload_status = 'NOT_UPLOADED'")->fetchColumn();
        $suppliers   = (int)$db->query("SELECT COUNT(DISTINCT dostavchik) FROM products WHERE dostavchik != ''")->fetchColumn();

        $rez = $db->query("SELECT rezultat FROM products WHERE rezultat != '' AND rezultat IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $rez = array_map('floatval', $rez);

        $avgRez = $rez ? array_sum($rez) / count($rez) : 0;
        $posRez = count(array_filter($rez, fn($v) => $v > 0));
        $negRez = count(array_filter($rez, fn($v) => $v <= 0));

        return compact('total', 'withAsin', 'notUploaded', 'suppliers', 'avgRez', 'posRez', 'negRez');
    }

    // ── Distinct values for filter dropdowns ──────────────────
    public static function distinct(string $excelField, string $filterBySupplier = ''): array {
        $dbCol = static::$colMap[$excelField] ?? null;
        if (!$dbCol) return [];

        $db    = static::db();
        $where = "WHERE {$dbCol} != ''";
        $params = [];

        if ($filterBySupplier !== '') {
            $where           .= " AND dostavchik = :dost";
            $params[':dost']  = $filterBySupplier;
        }

        $stmt = $db->prepare("SELECT DISTINCT {$dbCol} FROM products {$where} ORDER BY {$dbCol} COLLATE NOCASE");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ═══════════════════════════════════════════════════════════
    //  WRITE OPERATIONS (row-level, safe for concurrent users)
    // ═══════════════════════════════════════════════════════════

    /**
     * Insert or replace a batch of products.
     * Uses a transaction — all-or-nothing.
     */
    public static function upsertAll(array $products): int {
        $db  = static::db();
        $written = 0;

        // Build INSERT OR REPLACE
        $cols   = array_keys(static::$colMap);
        $dbCols = array_values(static::$colMap);

        $sql = "INSERT OR REPLACE INTO products
                (ean_key, " . implode(', ', $dbCols) . ", extra_data)
                VALUES
                (:ean_key, " . implode(', ', array_map(fn($c) => ":$c", $dbCols)) . ", :extra_data)";

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
        } catch (\Throwable $e) {
            $db->rollBack();
            Logger::error("ProductDB::upsertAll failed: " . $e->getMessage());
            throw $e;
        }

        return $written;
    }

    /**
     * Delete all products and re-insert.
     * Used for "Replace" import mode.
     */
    public static function replaceAll(array $products): int {
        $db = static::db();
        $db->exec("DELETE FROM products");
        return static::upsertAll($products);
    }

    /**
     * Update a single field on a single product.
     * Row-level lock — safe for concurrent users.
     */
    public static function updateField(string $ean, string $excelField, string $value): bool {
        $dbCol = static::$colMap[$excelField] ?? null;
        if (!$dbCol) return false;

        $db  = static::db();
        $key = Firebase::sanitizeKey($ean);

        $stmt = $db->prepare("UPDATE products SET {$dbCol} = :val, updated_at = datetime('now') WHERE ean_key = :key");
        $stmt->execute([':val' => $value, ':key' => $key]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Insert a single new product.
     */
    public static function insertOne(array $p): bool {
        $ean = Firebase::sanitizeKey($p['EAN Amazon'] ?? '');
        if ($ean === '') return false;

        try {
            static::upsertAll([$p]);
            return true;
        } catch (\Throwable $e) {
            Logger::error("ProductDB::insertOne: " . $e->getMessage());
            return false;
        }
    }

    // ── Rebuild from Firebase ─────────────────────────────────
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
        } catch (\Throwable $e) {
            Logger::error("ProductDB: rebuild failed: " . $e->getMessage());
            return false;
        }
    }

    // ── Status ────────────────────────────────────────────────
    public static function status(): array {
        $file   = DATA_DIR . '/products.sqlite';
        $exists = file_exists($file);
        $count  = 0;
        if ($exists) {
            try { $count = (int)static::db()->query("SELECT COUNT(*) FROM products")->fetchColumn(); }
            catch (\Throwable $e) {}
        }
        return [
            'exists'   => $exists,
            'size'     => $exists ? filesize($file) : 0,
            'modified' => $exists ? date('c', filemtime($file)) : null,
            'count'    => $count,
            'engine'   => 'SQLite (WAL mode)',
        ];
    }
}
