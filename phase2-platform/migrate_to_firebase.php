<?php
/**
 * migrate_to_firebase.php
 * ─────────────────────────────────────────────────────────────
 * One-time migration script: reads products.json, settings, and
 * suppliers from the local data/ directory and pushes everything
 * to Firebase Realtime Database.
 *
 * Run from the web OR via CLI:
 *   php migrate_to_firebase.php [--dry-run]
 *
 * ⚠  Keep this file outside the web root (or behind auth) after use.
 */

// ── Bootstrap ────────────────────────────────────────────────
define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
require_once SRC . '/config/config.php';
require_once SRC . '/lib/DataStore.php';
require_once SRC . '/lib/FirebaseDB.php';

$isCli    = PHP_SAPI === 'cli';
$isDryRun = in_array('--dry-run', $argv ?? []);

function out($msg, $color = '') {
    global $isCli;
    if ($isCli) {
        $colors = ['green' => "\033[32m", 'red' => "\033[31m",
                   'yellow' => "\033[33m", 'cyan' => "\033[36m", '' => ''];
        $reset  = $color ? "\033[0m" : '';
        echo ($colors[$color] ?? '') . $msg . $reset . PHP_EOL;
    } else {
        $styles = ['green' => 'color:#00C896', 'red' => 'color:#FF5A5A',
                   'yellow' => 'color:#C9A84C', 'cyan' => 'color:#4A7CFF', '' => 'color:#E8E6E1'];
        $style = $styles[$color] ?? 'color:#E8E6E1';
        echo "<div style='font-family:monospace;padding:2px 0;{$style}'>" . htmlspecialchars($msg) . "</div>";
        ob_flush(); flush();
    }
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="bg"><head><meta charset="UTF-8">
    <title>Firebase Migration — AMZ Retail</title>
    <style>
    body{background:#0D0F14;margin:0;padding:40px 20px;font-family:monospace}
    h1{color:#C9A84C;font-size:18px;margin-bottom:20px}
    .box{background:#1A1E2A;border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:24px;max-width:800px}
    .bar{height:4px;background:linear-gradient(90deg,#C9A84C,#4A7CFF);border-radius:4px 4px 0 0}
    progress{width:100%;height:8px;margin:12px 0;border-radius:4px;background:#0D0F14}
    progress::-webkit-progress-value{background:#4A7CFF}
    .btn{display:inline-block;padding:10px 20px;background:#4A7CFF;color:#fff;text-decoration:none;border-radius:6px;margin-top:16px;font-size:13px}
    </style></head><body>
    <div class="box"><div class="bar"></div>
    <h1>🔥 Firebase Migration — AMZ Retail</h1>';
    ob_start();
}

// ── Step 1: Test connection ──────────────────────────────────
out('━━━ Step 1: Testing Firebase connection…', 'cyan');
$conn = FirebaseDB::testConnection();
if (!$conn['ok']) {
    out('✗ Firebase connection FAILED (HTTP ' . ($conn['code'] ?? '?') . '): ' . $conn['error'], 'red');
    if (!empty($conn['hint'])) {
        out('  ℹ ' . $conn['hint'], 'yellow');
    }
    if (($conn['code'] ?? 0) === 404) {
        out('', '');
        out('  ╔═══════════════════════════════════════════════════════════╗', 'yellow');
        out('  ║  ДЕЙСТВИЕ: Създай Firebase Realtime Database              ║', 'yellow');
        out('  ║  1. Отвори: https://console.firebase.google.com           ║', 'yellow');
        out('  ║  2. Избери проект: amz-retail                              ║', 'yellow');
        out('  ║  3. Build → Realtime Database → Create Database            ║', 'yellow');
        out('  ║  4. Избери регион: europe-west1                            ║', 'yellow');
        out('  ║  5. Стартирай в Test mode (30 дни) → Enable               ║', 'yellow');
        out('  ║  6. Стартирай migrate_to_firebase.php отново               ║', 'yellow');
        out('  ╚═══════════════════════════════════════════════════════════╝', 'yellow');
    }
    if (!$isCli) { echo ob_get_clean() . '</div></body></html>'; }
    exit(1);
}
out("✓ Firebase connected in {$conn['latency_ms']} ms", 'green');

// ── Step 2: Write meta / schema ──────────────────────────────
out('', '');
out('━━━ Step 2: Writing schema / meta…', 'cyan');
if (!$isDryRun) {
    $meta = [
        'project'    => 'amz-retail',
        'migrated_at' => date('Y-m-d H:i:s'),
        'version'    => '1.7.0',
        'schema'     => [
            'products'  => 'keyed by EAN Amazon',
            'settings'  => 'flat object',
            'suppliers' => 'keyed by sup_{slug}',
        ],
    ];
    $r = FirebaseDB::set('/meta', $meta);
    if ($r['ok']) out('✓ Meta written', 'green');
    else          out('✗ Meta error: ' . $r['error'], 'red');
}

// ── Step 3: Migrate settings ─────────────────────────────────
out('', '');
out('━━━ Step 3: Migrating settings…', 'cyan');
$settings = DataStore::getSettings();
if (!$isDryRun) {
    $r = FirebaseDB::syncSettings($settings);
    if ($r['ok']) out('✓ Settings written to /settings', 'green');
    else          out('✗ Settings error: ' . $r['error'], 'red');
} else {
    out('  [DRY RUN] Would write ' . count($settings) . ' setting keys', 'yellow');
}

// ── Step 4: Migrate suppliers ────────────────────────────────
out('', '');
out('━━━ Step 4: Migrating suppliers…', 'cyan');
$suppliers = DataStore::getSuppliers();
out('  Found ' . count($suppliers) . ' suppliers');
if (!$isDryRun) {
    $r = FirebaseDB::syncSuppliers($suppliers);
    if ($r['ok']) out('✓ Suppliers written to /suppliers', 'green');
    else          out('✗ Suppliers error: ' . $r['error'], 'red');
} else {
    out('  [DRY RUN] Would write ' . count($suppliers) . ' suppliers', 'yellow');
}

// ── Step 5: Migrate products ─────────────────────────────────
out('', '');
out('━━━ Step 5: Migrating products…', 'cyan');
$products = DataStore::getProducts();
$total    = count($products);
out("  Found {$total} products — sending in batches of 100…");

if (!$isDryRun && $total > 0) {
    $synced = 0;
    $errors = [];
    $batch  = [];
    $batchNum = 0;

    foreach ($products as $p) {
        $key         = FirebaseDB::makeProductKey($p);
        $batch[$key] = FirebaseDB::sanitizeForFirebase($p);

        if (count($batch) >= 100) {
            $batchNum++;
            $r = FirebaseDB::request('PATCH', '/products', $batch);
            if ($r['ok']) {
                $synced += count($batch);
                out("  Batch {$batchNum}: +100 products → total synced: {$synced}", 'green');
            } else {
                $errors[] = "Batch {$batchNum}: " . $r['error'];
                out("  Batch {$batchNum} ERROR: " . $r['error'], 'red');
            }
            $batch = [];
        }
    }
    // Final batch
    if (!empty($batch)) {
        $batchNum++;
        $r = FirebaseDB::request('PATCH', '/products', $batch);
        if ($r['ok']) {
            $synced += count($batch);
            out("  Batch {$batchNum}: +" . count($batch) . " products → total synced: {$synced}", 'green');
        } else {
            $errors[] = "Batch {$batchNum}: " . $r['error'];
            out("  Batch {$batchNum} ERROR: " . $r['error'], 'red');
        }
    }

    out('', '');
    if (empty($errors)) {
        out("✓ ALL {$synced}/{$total} products synced to Firebase!", 'green');
    } else {
        out("⚠ Synced {$synced}/{$total} with " . count($errors) . " errors:", 'yellow');
        foreach ($errors as $e) out("  → {$e}", 'red');
    }

    // Log sync
    DataStore::appendSyncLog([
        'action'   => 'firebase_migration',
        'total'    => $total,
        'synced'   => $synced,
        'errors'   => count($errors),
        'user'     => 'migration_script',
    ]);
} elseif ($isDryRun) {
    out("  [DRY RUN] Would sync {$total} products in " . ceil($total / 100) . " batches", 'yellow');
} else {
    out('⚠ No products found in products.json', 'yellow');
}

// ── Done ────────────────────────────────────────────────────
out('', '');
out('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'cyan');
out('✓ Migration complete at ' . date('Y-m-d H:i:s'), 'green');
out('  Firebase URL: ' . FirebaseDB::DB_URL, '');
out('  Console: https://console.firebase.google.com/project/amz-retail', '');
out('', '');

if (!$isCli) {
    echo ob_get_clean();
    echo '<a class="btn" href="/settings/integrations">← Back to Integrations</a>';
    echo '</div></body></html>';
}
