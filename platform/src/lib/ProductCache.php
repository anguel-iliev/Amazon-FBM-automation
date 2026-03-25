<?php
/**
 * ProductCache — локален кеш за продукти
 *
 * АРХИТЕКТУРА:
 *   Firebase = MASTER (записва при import/update)
 *   data/products_cache.json = CACHE (чете PHP за филтриране/пагиниране)
 *
 * Всички четения идват от кеша (бързо, без Firebase заявка).
 * Всички записи отиват в Firebase И обновяват кеша.
 *
 * Скорост при 25 000 продукта:
 *   - Стар подход (Firebase): 40-90 сек
 *   - Нов подход (кеш): ~1-2 сек
 */
class ProductCache {

    private static string $file   = '';
    private static ?array $memory = null; // in-process memory cache

    private static function path(): string {
        if (!static::$file) {
            static::$file = DATA_DIR . '/products_cache.json';
        }
        return static::$file;
    }

    // ── Invalidate memory cache ───────────────────────────────
    public static function invalidate(): void {
        static::$memory = null;
    }

    // ── Read all products (from memory → file → Firebase) ────
    public static function all(): array {
        if (static::$memory !== null) return static::$memory;

        $path = static::path();
        if (file_exists($path)) {
            $data = @json_decode(file_get_contents($path), true);
            if (is_array($data) && !empty($data)) {
                static::$memory = $data;
                return static::$memory;
            }
        }

        // Cache miss — load from Firebase and build cache
        Logger::info("ProductCache: cache miss, loading from Firebase");
        static::rebuildFromFirebase();
        return static::$memory ?? [];
    }

    // ── Build/rebuild cache from Firebase ────────────────────
    public static function rebuildFromFirebase(): bool {
        $res = Firebase::get('/products');
        if (!$res['ok'] || !is_array($res['data'])) {
            Logger::error("ProductCache: Firebase read failed: " . ($res['error'] ?? ''));
            return false;
        }

        $products = array_values($res['data']);
        static::write($products);
        Logger::info("ProductCache: rebuilt with " . count($products) . " products");
        return true;
    }

    // ── Write products to cache file ─────────────────────────
    public static function write(array $products): void {
        file_put_contents(
            static::path(),
            json_encode(array_values($products), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
        static::$memory = array_values($products);
    }

    // ── Update single product in cache ────────────────────────
    public static function updateOne(string $ean, string $field, $value): void {
        $all = static::all();
        $ean = Firebase::sanitizeKey($ean);

        foreach ($all as &$p) {
            $pEan = Firebase::sanitizeKey($p['EAN Amazon'] ?? '');
            if ($pEan === $ean) {
                $p[$field] = $value;
                break;
            }
        }
        unset($p);

        static::write($all);
    }

    // ══════════════════════════════════════════════════════════
    //  SERVER-SIDE QUERY ENGINE
    //  Filter + Sort + Paginate — all in PHP, O(n) scan
    // ══════════════════════════════════════════════════════════

    /**
     * Main query method — returns paginated result.
     *
     * @param array $filters  ['dostavchik'=>'Orbico', 'brand'=>'', 'search'=>'']
     * @param string $sortCol Column key to sort by
     * @param string $sortDir 'asc' | 'desc'
     * @param int    $page    1-based page number
     * @param int    $perPage Products per page
     *
     * @return array ['products'=>[...], 'total'=>int, 'pages'=>int, 'page'=>int, 'perPage'=>int]
     */
    public static function query(
        array  $filters = [],
        string $sortCol = '',
        string $sortDir = 'asc',
        int    $page    = 1,
        int    $perPage = 50
    ): array {
        $all = static::all();

        // ── 1. Filter ─────────────────────────────────────────
        if (!empty($filters['dostavchik'])) {
            $dv  = $filters['dostavchik'];
            $all = array_values(array_filter($all, fn($p) => ($p['Доставчик'] ?? '') === $dv));
        }
        if (!empty($filters['brand'])) {
            $br  = $filters['brand'];
            $all = array_values(array_filter($all, fn($p) => ($p['Бранд'] ?? '') === $br));
        }
        if (!empty($filters['upload_status'])) {
            $us  = $filters['upload_status'];
            $all = array_values(array_filter($all, fn($p) => ($p['_upload_status'] ?? 'NOT_UPLOADED') === $us));
        }
        if (!empty($filters['elektronika'])) {
            $ek  = $filters['elektronika'];
            $all = array_values(array_filter($all, fn($p) => ($p['Електоника'] ?? '') === $ek));
        }
        if (!empty($filters['search'])) {
            $q   = mb_strtolower($filters['search']);
            $all = array_values(array_filter($all, function($p) use ($q) {
                return str_contains(mb_strtolower($p['Модел']          ?? ''), $q)
                    || str_contains(mb_strtolower($p['Бранд']          ?? ''), $q)
                    || str_contains(mb_strtolower($p['EAN Amazon']     ?? ''), $q)
                    || str_contains(mb_strtolower($p['ASIN']           ?? ''), $q)
                    || str_contains(mb_strtolower($p['Наше SKU']       ?? ''), $q)
                    || str_contains(mb_strtolower($p['Доставчик']      ?? ''), $q)
                    || str_contains(mb_strtolower($p['Доставчик SKU']  ?? ''), $q)
                    || str_contains(mb_strtolower($p['Коментар']       ?? ''), $q);
            }));
        }

        // ── 2. Sort ───────────────────────────────────────────
        if ($sortCol !== '') {
            $dir = $sortDir === 'desc' ? -1 : 1;
            usort($all, function ($a, $b) use ($sortCol, $dir) {
                $va = $a[$sortCol] ?? '';
                $vb = $b[$sortCol] ?? '';
                if (is_numeric($va) && is_numeric($vb)) {
                    $cmp = (float)$va <=> (float)$vb;
                } else {
                    $cmp = strcmp((string)$va, (string)$vb);
                }
                return $cmp * $dir;
            });
        }

        // ── 3. Paginate ───────────────────────────────────────
        $total   = count($all);
        $pages   = max(1, (int)ceil($total / $perPage));
        $page    = min(max(1, $page), $pages);
        $offset  = ($page - 1) * $perPage;
        $slice   = array_slice($all, $offset, $perPage);

        return [
            'products' => $slice,
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page,
            'perPage'  => $perPage,
        ];
    }

    // ── Stats (uses memory — no extra IO) ─────────────────────
    public static function stats(): array {
        $all = static::all();

        $total = $withAsin = $notUploaded = 0;
        $supSet = []; $rez = [];

        foreach ($all as $p) {
            $total++;
            if (!empty($p['ASIN']))                                        $withAsin++;
            if (($p['_upload_status'] ?? 'NOT_UPLOADED') === 'NOT_UPLOADED') $notUploaded++;
            if (!empty($p['Доставчик']))                                   $supSet[$p['Доставчик']] = true;
            if (isset($p['Резултат']) && $p['Резултат'] !== '')            $rez[] = (float)$p['Резултат'];
        }

        $suppliers = count($supSet);
        $avgRez    = $rez ? array_sum($rez) / count($rez) : 0;
        $posRez    = count(array_filter($rez, fn($v) => $v > 0));
        $negRez    = count(array_filter($rez, fn($v) => $v <= 0));

        return compact('total', 'withAsin', 'notUploaded', 'suppliers', 'avgRez', 'posRez', 'negRez');
    }

    // ── Distinct values for filter dropdowns ──────────────────
    public static function distinct(string $field, string $filterBySupplier = ''): array {
        $all = static::all();

        if ($filterBySupplier !== '') {
            $all = array_filter($all, fn($p) => ($p['Доставчик'] ?? '') === $filterBySupplier);
        }

        $vals = array_unique(array_filter(array_column($all, $field)));
        sort($vals);
        return array_values($vals);
    }

    // ── Cache status ──────────────────────────────────────────
    public static function status(): array {
        $path   = static::path();
        $exists = file_exists($path);
        return [
            'exists'   => $exists,
            'size'     => $exists ? filesize($path) : 0,
            'modified' => $exists ? date('c', filemtime($path)) : null,
            'count'    => count(static::all()),
        ];
    }
}
