<?php
/**
 * firebase-admin.php — Firebase Diagnostic & Admin Panel
 * Access: https://your-domain/firebase-admin
 * Auth: requires logged-in session (admin only)
 * v1.7.0
 */
define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
require_once SRC . '/config/config.php';
require_once SRC . '/lib/DataStore.php';
require_once SRC . '/lib/FirebaseDB.php';
require_once SRC . '/lib/Session.php';
require_once SRC . '/lib/Auth.php';
require_once SRC . '/lib/View.php';
require_once SRC . '/lib/Logger.php';

Session::start();

// Basic auth check
if (!Auth::check()) {
    header('Location: /login');
    exit;
}
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body style="background:#0D0F14;color:#fff;font-family:monospace;padding:40px">403 — Само за администратори</body></html>';
    exit;
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? $_POST['action'] ?? '';
    header('Content-Type: application/json');

    switch ($action) {
        case 'test':
            $result = FirebaseDB::testConnection();
            echo json_encode($result);
            exit;

        case 'migrate':
            $products  = DataStore::getProducts();
            $settings  = DataStore::getSettings();
            $suppliers = DataStore::getSuppliers();
            $results   = ['steps' => []];

            // Meta
            $r = FirebaseDB::set('/meta', [
                'project'     => 'amz-retail',
                'migrated_at' => date('Y-m-d H:i:s'),
                'version'     => '1.7.0',
                'total_products' => count($products),
            ]);
            $results['steps']['meta'] = $r['ok'] ? 'ok' : $r['error'];

            // Settings
            $r = FirebaseDB::syncSettings($settings);
            $results['steps']['settings'] = $r['ok'] ? 'ok' : $r['error'];

            // Suppliers
            $r = FirebaseDB::syncSuppliers($suppliers);
            $results['steps']['suppliers'] = $r['ok'] ? 'ok' : $r['error'];

            // Products in batches
            $synced = 0; $errors = 0; $batch = [];
            foreach ($products as $p) {
                $key = FirebaseDB::makeProductKey($p);
                $batch[$key] = FirebaseDB::sanitizeForFirebase($p);
                if (count($batch) >= 100) {
                    $r = FirebaseDB::request('PATCH', '/products', $batch);
                    if ($r['ok']) $synced += count($batch); else $errors++;
                    $batch = [];
                }
            }
            if (!empty($batch)) {
                $r = FirebaseDB::request('PATCH', '/products', $batch);
                if ($r['ok']) $synced += count($batch); else $errors++;
            }
            $results['steps']['products'] = "synced:{$synced} errors:{$errors}";
            $results['ok']     = ($errors === 0);
            $results['synced'] = $synced;
            $results['total']  = count($products);

            DataStore::appendSyncLog([
                'action' => 'firebase_full_migration',
                'synced' => $synced,
                'errors' => $errors,
                'user'   => Auth::user(),
            ]);
            Logger::info("Firebase migration: {$synced}/{$results['total']} by " . Auth::user());
            echo json_encode($results);
            exit;

        case 'read_path':
            $path = $data['path'] ?? '/meta';
            $path = '/' . ltrim($path, '/');
            $r    = FirebaseDB::get($path);
            echo json_encode(['ok' => $r['ok'], 'data' => $r['data'], 'error' => $r['error']]);
            exit;

        case 'delete_path':
            Auth::requireAdmin();
            $path = '/' . ltrim($data['path'] ?? '', '/');
            $r    = FirebaseDB::delete($path);
            echo json_encode(['ok' => $r['ok'], 'error' => $r['error']]);
            exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

// ── Page ─────────────────────────────────────────────────────
$settings = DataStore::getSettings();
$fb       = $settings['firebase'] ?? [];
$products = DataStore::getProducts();
$dbUrl    = $fb['db_url'] ?? FirebaseDB::DB_URL;
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>🔥 Firebase Admin — AMZ Retail</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0D0F14;color:#E8E6E1;font-family:'Inter',system-ui,sans-serif;padding:0;min-height:100vh}
.topbar{background:#181C26;border-bottom:1px solid rgba(255,255,255,.07);padding:0 24px;height:52px;display:flex;align-items:center;gap:16px}
.topbar-title{font-size:15px;font-weight:700;color:#fff}
.topbar-title span{color:#C9A84C}
.topbar-back{font-size:12px;color:rgba(255,255,255,.4);text-decoration:none;border:1px solid rgba(255,255,255,.1);padding:4px 12px;border-radius:5px;transition:color .15s}
.topbar-back:hover{color:#fff}
.topbar-badge{font-size:11px;padding:2px 10px;border-radius:12px;font-weight:700;background:rgba(255,202,40,.1);color:#FFCA28;border:1px solid rgba(255,202,40,.2)}
.wrap{max-width:960px;margin:0 auto;padding:24px 16px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.card{background:#181C26;border:1px solid rgba(255,255,255,.07);border-radius:9px;overflow:hidden}
.card-hdr{padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:8px}
.card-hdr-title{font-size:13px;font-weight:700;color:#fff}
.card-body{padding:16px 18px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:12px}
.info-row:last-child{border-bottom:none}
.info-label{color:rgba(255,255,255,.4)}
.info-val{color:#E8E6E1;font-family:'Courier New',monospace;font-size:11px}
code{background:rgba(255,255,255,.06);padding:2px 6px;border-radius:3px;font-family:'Courier New',monospace;font-size:11px;color:#C9A84C}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:none;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;font-family:inherit}
.btn-primary{background:#4A7CFF;color:#fff}.btn-primary:hover{background:#3a6cf0}
.btn-danger{background:rgba(224,92,92,.15);color:#E05C5C;border:1px solid rgba(224,92,92,.25)}.btn-danger:hover{background:rgba(224,92,92,.25)}
.btn-ghost{background:rgba(255,255,255,.06);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.1)}.btn-ghost:hover{background:rgba(255,255,255,.1);color:#fff}
.btn-green{background:rgba(61,187,127,.15);color:#3DBB7F;border:1px solid rgba(61,187,127,.3)}.btn-green:hover{background:rgba(61,187,127,.25)}
.status-circle{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px}
.status-ok{background:#3DBB7F}.status-err{background:#E05C5C}.status-unk{background:#C9A84C}
.log-box{background:#0D0F14;border:1px solid rgba(255,255,255,.07);border-radius:5px;padding:12px;font-family:'Courier New',monospace;font-size:11px;color:rgba(255,255,255,.6);max-height:240px;overflow-y:auto;line-height:1.8}
.log-ok{color:#3DBB7F}.log-err{color:#E05C5C}.log-info{color:#4A7CFF}.log-warn{color:#C9A84C}
.progress-bar{background:rgba(255,255,255,.06);border-radius:4px;height:6px;overflow:hidden;margin-top:8px}
.progress-fill{background:#4A7CFF;height:100%;border-radius:4px;transition:width .3s}
.path-input{background:#0D0F14;border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:6px 10px;font-size:12px;color:#fff;font-family:'Courier New',monospace;outline:none;width:100%}
.path-input:focus{border-color:#4A7CFF}
.json-viewer{background:#0D0F14;border:1px solid rgba(255,255,255,.07);border-radius:5px;padding:12px;font-family:'Courier New',monospace;font-size:11px;color:rgba(255,255,255,.7);max-height:200px;overflow:auto;white-space:pre;line-height:1.6;margin-top:8px}
@media(max-width:700px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="topbar">
  <a href="/settings/integrations" class="topbar-back">← Назад</a>
  <div class="topbar-title">🔥 Firebase <span>Admin</span></div>
  <span class="topbar-badge">Project: amz-retail</span>
  <span id="conn-indicator" style="margin-left:auto;font-size:12px;color:rgba(255,255,255,.4)">⏳ Проверяване…</span>
</div>

<div class="wrap">

<div class="grid" style="margin-bottom:16px">
  <!-- ── Connection & Info Card ── -->
  <div class="card">
    <div class="card-hdr">
      <span style="font-size:16px">🔌</span>
      <div class="card-hdr-title">Връзка & Детайли</div>
    </div>
    <div class="card-body">
      <div class="info-row">
        <span class="info-label">Project ID</span>
        <code><?= htmlspecialchars($fb['project_id'] ?? FirebaseDB::PROJECT_ID) ?></code>
      </div>
      <div class="info-row">
        <span class="info-label">Project Number</span>
        <code><?= htmlspecialchars($fb['project_num'] ?? '820571488028') ?></code>
      </div>
      <div class="info-row">
        <span class="info-label">Database URL</span>
        <code style="font-size:10px"><?= htmlspecialchars($dbUrl) ?></code>
      </div>
      <div class="info-row">
        <span class="info-label">Region</span>
        <code>europe-west1</code>
      </div>
      <div class="info-row">
        <span class="info-label">Статус</span>
        <span id="conn-status-cell"><span class="status-circle status-unk"></span>Непроверен</span>
      </div>
      <div class="info-row">
        <span class="info-label">Latency</span>
        <span id="latency-cell" class="info-val">—</span>
      </div>
      <div style="margin-top:12px;display:flex;gap:8px">
        <button class="btn btn-ghost" onclick="testConnection()">🔌 Тест</button>
        <a href="https://console.firebase.google.com/project/amz-retail/database" target="_blank" class="btn btn-ghost">🔗 Console</a>
      </div>
    </div>
  </div>

  <!-- ── Stats Card ── -->
  <div class="card">
    <div class="card-hdr">
      <span style="font-size:16px">📊</span>
      <div class="card-hdr-title">Статистики</div>
    </div>
    <div class="card-body">
      <div class="info-row">
        <span class="info-label">Продукти в JSON</span>
        <code><?= number_format(count($products)) ?></code>
      </div>
      <div class="info-row">
        <span class="info-label">С ASIN</span>
        <code><?= number_format(count(array_filter($products, fn($p) => !empty($p['ASIN'])))) ?></code>
      </div>
      <div class="info-row">
        <span class="info-label">Доставчици</span>
        <code><?= count(array_unique(array_filter(array_column($products, 'Доставчик')))) ?></code>
      </div>
      <div class="info-row">
        <span class="info-label">Firebase Enabled</span>
        <code><?= ($fb['enabled'] ?? false) ? '✓ Yes' : '✗ No' ?></code>
      </div>
      <div class="info-row">
        <span class="info-label">Sync on Import</span>
        <code><?= ($fb['sync_on_import'] ?? false) ? '✓ Yes' : '✗ No' ?></code>
      </div>
      <div class="info-row">
        <span class="info-label">products.json size</span>
        <code><?= number_format(filesize(CACHE_DIR . '/products.json') / 1024, 0) ?> KB</code>
      </div>
    </div>
  </div>
</div>

<!-- ── Setup Guide Card ── -->
<div class="card" style="margin-bottom:16px;grid-column:1/-1;border-color:rgba(255,202,40,.15)">
  <div class="card-hdr" style="background:rgba(255,202,40,.04)">
    <span style="font-size:16px">📋</span>
    <div class="card-hdr-title" style="color:#FFCA28">Първи стъпки — Как да създадеш Firebase Realtime Database</div>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;font-size:12px;color:rgba(255,255,255,.65);line-height:1.8">
      <div>
        <div style="font-weight:700;color:#FFCA28;margin-bottom:6px">1️⃣ Влез в Firebase Console</div>
        <div>Отвори <a href="https://console.firebase.google.com/project/amz-retail" target="_blank" style="color:#4A7CFF">console.firebase.google.com</a> → избери проект <code>amz-retail</code></div>
      </div>
      <div>
        <div style="font-weight:700;color:#FFCA28;margin-bottom:6px">2️⃣ Създай Realtime Database</div>
        <div>Build (лявото меню) → <strong>Realtime Database</strong> → <strong>Create Database</strong> → избери регион <code>europe-west1</code></div>
      </div>
      <div>
        <div style="font-weight:700;color:#FFCA28;margin-bottom:6px">3️⃣ Избери режим</div>
        <div>Избери <strong>Test mode</strong> (позволява четене/записване без auth за 30 дни) → кликни <strong>Enable</strong></div>
      </div>
      <div>
        <div style="font-weight:700;color:#FFCA28;margin-bottom:6px">4️⃣ Вземи Database URL</div>
        <div>След създаването ще видиш URL: <code>https://amz-retail-default-rtdb.europe-west1.firebasedatabase.app</code></div>
      </div>
      <div>
        <div style="font-weight:700;color:#FFCA28;margin-bottom:6px">5️⃣ Настрой токена</div>
        <div>Project Settings → Service Accounts → <strong>Generate new private key</strong> → запази JSON или използвай Database secret</div>
      </div>
      <div>
        <div style="font-weight:700;color:#FFCA28;margin-bottom:6px">6️⃣ Стартирай миграцията</div>
        <div>Кликни <strong>"Пълна миграция"</strong> по-горе. Всички 1,237 продукта ще се качат автоматично.</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Migration Card ── -->
<div class="card" style="margin-bottom:16px;grid-column:1/-1">
  <div class="card-hdr">
    <span style="font-size:16px">🚀</span>
    <div class="card-hdr-title">Миграция на данни → Firebase</div>
  </div>
  <div class="card-body">
    <p style="font-size:12px;color:rgba(255,255,255,.5);margin-bottom:12px">
      Изпраща ВСИЧКИ продукти (<?= count($products) ?>), настройки и доставчици към Firebase Realtime Database.
      Операцията е безопасна — съществуващи записи се merge-ват, не изтриват.
    </p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
      <button class="btn btn-primary" onclick="startMigration()">
        🔥 Пълна миграция (<?= count($products) ?> продукта)
      </button>
      <button class="btn btn-green" onclick="testConnection()">
        🔌 Тест преди старт
      </button>
      <div id="mig-status" style="font-size:12px;color:rgba(255,255,255,.4)"></div>
    </div>
    <div class="progress-bar" id="mig-progress-wrap" style="display:none">
      <div class="progress-fill" id="mig-progress" style="width:0%"></div>
    </div>
    <div class="log-box" id="mig-log" style="display:none"></div>
  </div>
</div>

<!-- ── DB Explorer ── -->
<div class="card" style="margin-bottom:16px;grid-column:1/-1">
  <div class="card-hdr">
    <span style="font-size:16px">🔍</span>
    <div class="card-hdr-title">Database Explorer</div>
  </div>
  <div class="card-body">
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
      <input type="text" class="path-input" id="explorer-path" value="/meta" placeholder="/products, /meta, /settings …"
             style="flex:1" onkeydown="if(event.key==='Enter')readPath()">
      <button class="btn btn-ghost" onclick="readPath()">📖 Прочети</button>
      <button class="btn btn-danger" onclick="deletePath()">🗑 Изтрий</button>
    </div>
    <div class="json-viewer" id="explorer-result">// Резултатът ще се появи тук…</div>
  </div>
</div>

</div><!-- /.wrap -->

<script>
// ── Test connection ────────────────────────────────────────────────
async function testConnection() {
  const ind  = document.getElementById('conn-indicator');
  const cell = document.getElementById('conn-status-cell');
  const lat  = document.getElementById('latency-cell');
  ind.textContent = '⏳ Тестване…';
  try {
    const d = await postJson({action:'test'});
    if (d.ok) {
      ind.style.color = '#3DBB7F'; ind.textContent = '✓ Свързан';
      cell.innerHTML = '<span class="status-circle status-ok"></span>Свързан';
      lat.textContent = d.latency_ms + ' ms';
    } else {
      ind.style.color = '#E05C5C'; ind.textContent = '✗ Грешка';
      let errMsg = escH(d.error||'Неуспешна връзка');
      if (d.code === 404) {
        errMsg = '404 — Базата не е създадена. <a href="https://console.firebase.google.com/project/amz-retail/database" target="_blank" style="color:#4A7CFF">Създай я тук →</a>';
      }
      cell.innerHTML = '<span class="status-circle status-err"></span>' + errMsg;
    }
  } catch(e) {
    ind.style.color='#E05C5C'; ind.textContent='✗ ' + e.message;
  }
}

// ── Migration ─────────────────────────────────────────────────────
async function startMigration() {
  if (!confirm('Ще изпратиш всички данни към Firebase. Продължи?')) return;
  const log    = document.getElementById('mig-log');
  const status = document.getElementById('mig-status');
  const prog   = document.getElementById('mig-progress');
  const wrap   = document.getElementById('mig-progress-wrap');
  log.style.display = ''; wrap.style.display = '';
  log.innerHTML = '<span class="log-info">⏳ Старт на миграцията…</span>\n';
  status.textContent = 'Работи…'; status.style.color = 'var(--gold, #C9A84C)';
  prog.style.width = '10%';

  try {
    const d = await postJson({action:'migrate'});
    prog.style.width = '100%';
    if (d.ok) {
      status.textContent = `✓ ${d.synced}/${d.total} продукта синхронизирани`;
      status.style.color = '#3DBB7F';
      log.innerHTML += `<span class="log-ok">✓ Мета: ${d.steps?.meta||'?'}</span>\n`;
      log.innerHTML += `<span class="log-ok">✓ Настройки: ${d.steps?.settings||'?'}</span>\n`;
      log.innerHTML += `<span class="log-ok">✓ Доставчици: ${d.steps?.suppliers||'?'}</span>\n`;
      log.innerHTML += `<span class="log-ok">✓ Продукти: ${d.steps?.products||'?'}</span>\n`;
      log.innerHTML += `<span class="log-ok">✓ Готово: ${d.synced}/${d.total} записа в Firebase!</span>\n`;
    } else {
      status.textContent = `⚠ Частично: ${d.synced||0}/${d.total||0}`;
      status.style.color = '#E05C5C';
      for (const [k,v] of Object.entries(d.steps||{})) {
        const cls = v === 'ok' ? 'log-ok' : 'log-err';
        log.innerHTML += `<span class="${cls}">${k}: ${escH(String(v))}</span>\n`;
      }
    }
  } catch(e) {
    status.textContent = '✗ Грешка';
    log.innerHTML += `<span class="log-err">✗ ${escH(e.message)}</span>\n`;
  }
}

// ── DB Explorer ───────────────────────────────────────────────────
async function readPath() {
  const path   = document.getElementById('explorer-path').value.trim() || '/meta';
  const viewer = document.getElementById('explorer-result');
  viewer.textContent = '⏳ Зареждане…';
  const d = await postJson({action:'read_path', path});
  if (d.ok) {
    viewer.textContent = JSON.stringify(d.data, null, 2);
  } else {
    viewer.textContent = '✗ ' + (d.error||'Грешка');
  }
}

async function deletePath() {
  const path = document.getElementById('explorer-path').value.trim();
  if (!path || path === '/') { alert('Невалиден път'); return; }
  if (!confirm(`⚠ Изтриваш '${path}' от Firebase. Сигурен ли си?`)) return;
  const d = await postJson({action:'delete_path', path});
  document.getElementById('explorer-result').textContent = d.ok ? '✓ Изтрито' : ('✗ ' + d.error);
}

// ── Helpers ───────────────────────────────────────────────────────
async function postJson(body) {
  const r = await fetch('', { method:'POST',
    headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
  return r.json();
}
function escH(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Auto test on load
window.addEventListener('DOMContentLoaded', () => testConnection());
</script>
</body>
</html>
