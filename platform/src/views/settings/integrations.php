<?php
/**
 * Settings → Integrations — including Firebase configuration
 * v1.7.0
 */
$activeTab = 'integrations';
include __DIR__ . '/_tabs.php';

$settings = DataStore::getSettings();
$firebaseCfg = $settings['firebase'] ?? [
    'project_id'  => 'amz-retail',
    'project_num' => '820571488028',
    'db_url'      => 'https://amz-retail-default-rtdb.europe-west1.firebasedatabase.app',
    'enabled'     => false,
    'sync_on_import' => true,
    'sync_on_update' => true,
];
?>

<style>
.int-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
  gap: 16px;
}
.int-card {
  background: #181C26;
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 9px;
  overflow: hidden;
}
.int-card-hdr {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,.06);
}
.int-card-icon {
  width: 36px; height: 36px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.int-card-title { font-size: 13px; font-weight: 700; color: #fff; }
.int-card-desc  { font-size: 11px; color: rgba(255,255,255,.38); margin-top: 2px; }
.int-badge-on  { font-size:10px;padding:2px 8px;border-radius:12px;font-weight:700;background:rgba(61,187,127,.15);color:#fff;border:1px solid rgba(61,187,127,.3); }
.int-badge-off { font-size:10px;padding:2px 8px;border-radius:12px;font-weight:700;background:rgba(255,255,255,.06);color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.1); }
.int-card-body { padding: 16px 18px; }
.int-field { margin-bottom: 12px; }
.int-field label { display:block;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:5px; }
.int-field input, .int-field select, .int-field textarea {
  width:100%; background:#0D0F14; border:1px solid rgba(255,255,255,.12);
  border-radius:4px; padding:7px 10px; font-size:13px; color:#fff;
  font-family:inherit; outline:none; transition:border-color .15s;
}
.int-field input:focus, .int-field select:focus, .int-field textarea:focus { border-color:var(--gold); }
.int-field select option { background:#0D0F14; color:#E8E6E1; }
.int-field textarea { resize:vertical; min-height:60px; font-family:'Courier New',monospace; font-size:12px; }
.int-row { display:flex;gap:10px;flex-wrap:wrap;align-items:center; }
.int-toggle { display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none; }
.int-toggle input[type=checkbox] { width:16px;height:16px;accent-color:var(--green);cursor:pointer; }
.int-toggle span { font-size:12px;color:rgba(255,255,255,.65); }
.int-status { font-size:12px;color:rgba(255,255,255,.38);min-width:100px; }
.fb-connection-test {
  background: rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.06);
  border-radius:6px; padding:12px 14px; margin-top:12px; font-size:12px;
}
.fb-token-box {
  background: rgba(0,0,0,.3); border:1px solid rgba(255,255,255,.07);
  border-radius:5px; padding:8px 10px;
  font-family:'Courier New',monospace; font-size:11px;
  color:rgba(255,255,255,.5); word-break:break-all; line-height:1.7;
  margin-top:4px;
}
</style>

<div class="int-grid">

<!-- ── Firebase Card ── -->
<div class="int-card" style="grid-column:1/-1">
  <div class="int-card-hdr">
    <div class="int-card-icon" style="background:rgba(255,202,40,.1);">🔥</div>
    <div>
      <div class="int-card-title">Firebase Realtime Database</div>
      <div class="int-card-desc">Синхронизация на продукти с Google Firebase — Project: amz-retail</div>
    </div>
    <div style="margin-left:auto">
      <span class="<?= $firebaseCfg['enabled'] ? 'int-badge-on' : 'int-badge-off' ?>" id="fb-status-badge">
        <?= $firebaseCfg['enabled'] ? 'Активен' : 'Неактивен' ?>
      </span>
    </div>
  </div>
  <div class="int-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
      <div class="int-field">
        <label>Project ID</label>
        <input type="text" id="fb-project-id" value="<?= htmlspecialchars($firebaseCfg['project_id']) ?>" placeholder="amz-retail">
      </div>
      <div class="int-field">
        <label>Project Number</label>
        <input type="text" id="fb-project-num" value="<?= htmlspecialchars($firebaseCfg['project_num']) ?>" placeholder="820571488028">
      </div>
    </div>
    <div class="int-field">
      <label>Database URL</label>
      <input type="text" id="fb-db-url" value="<?= htmlspecialchars($firebaseCfg['db_url']) ?>"
             placeholder="https://amz-retail-default-rtdb.europe-west1.firebasedatabase.app">
    </div>
    <div class="int-field">
      <label>Service Account Token / Secret</label>
      <div class="fb-token-box" id="fb-token-display" title="Кликни за показване/скриване" onclick="toggleToken()">
        ••••••••••••••••••••••••••••••••••••••••••••••••
      </div>
      <textarea id="fb-token" rows="3"
                placeholder="Постави Firebase Service Account JSON или Token тук…"
                style="display:none;margin-top:6px"><?= htmlspecialchars($settings['firebase_token'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px">
      <label class="int-toggle">
        <input type="checkbox" id="fb-enabled" <?= $firebaseCfg['enabled'] ? 'checked' : '' ?> onchange="updateFbBadge()">
        <span>Активирай Firebase sync</span>
      </label>
      <label class="int-toggle">
        <input type="checkbox" id="fb-sync-import" <?= $firebaseCfg['sync_on_import'] ? 'checked' : '' ?>>
        <span>Sync при Excel импорт</span>
      </label>
      <label class="int-toggle">
        <input type="checkbox" id="fb-sync-update" <?= $firebaseCfg['sync_on_update'] ? 'checked' : '' ?>>
        <span>Sync при редакция на клетка</span>
      </label>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <button class="btn btn-primary btn-sm" onclick="saveFbConfig()">
        <svg width="11" height="11" viewBox="0 0 20 20" fill="none"><path d="M5 13V6a1 1 0 011-1h8a1 1 0 011 1v7M4 17h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Запази Firebase настройки
      </button>
      <button class="btn btn-ghost btn-sm" onclick="testFbConnection()">
        <svg width="11" height="11" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 0112 0M10 4v3M10 13v3M4 10H1M19 10h-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        Тествай връзка
      </button>
      <button class="btn btn-ghost btn-sm" onclick="syncAllToFirebase()">
        <svg width="11" height="11" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 016-6 6 6 0 014.24 1.76M16 10a6 6 0 01-6 6 6 6 0 01-4.24-1.76" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14.24 4.76L16 3v3.5h-3.5M5.76 15.24L4 17v-3.5h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Sync всички продукти
      </button>
      <div id="fb-op-status" style="font-size:12px;color:rgba(255,255,255,.4)"></div>
    </div>

    <div class="fb-connection-test" id="fb-test-result" style="display:none"></div>

    <div style="margin-top:16px;padding-top:12px;border-top:1px solid rgba(255,255,255,.06)">
      <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:8px">
        Детайли на проекта
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px;color:rgba(255,255,255,.5)">
        <div>Project ID: <code style="color:var(--gold-lt)"><?= htmlspecialchars($firebaseCfg['project_id']) ?></code></div>
        <div>Project Number: <code style="color:var(--gold-lt)"><?= htmlspecialchars($firebaseCfg['project_num']) ?></code></div>
        <div>Region: <code style="color:var(--gold-lt)">europe-west1</code></div>
        <div>DB Path: <code style="color:var(--gold-lt)">/products</code></div>
      </div>
    </div>
  </div>
</div>

<!-- ── Google Drive Card ── -->
<div class="int-card">
  <div class="int-card-hdr">
    <div class="int-card-icon" style="background:rgba(66,133,244,.1)">📁</div>
    <div>
      <div class="int-card-title">Google Drive</div>
      <div class="int-card-desc">Качване на файлове в Drive папка</div>
    </div>
  </div>
  <div class="int-card-body">
    <div class="int-field">
      <label>Drive Folder ID</label>
      <input type="text" id="drive-folder-id"
             value="<?= htmlspecialchars($settings['drive_folder_id'] ?? '') ?>"
             placeholder="1Wch88u5tZApf5UOXzeH9AO7TETXGKYnT">
    </div>
    <div class="int-field">
      <label>Google Credentials JSON</label>
      <input type="text" value="<?= htmlspecialchars(defined('SRC') ? (file_exists(SRC.'/config/google-credentials.json') ? '✓ Намерен' : '✗ Липсва') : 'N/A') ?>"
             readonly style="color:<?= (defined('SRC') && file_exists(SRC.'/config/google-credentials.json')) ? 'var(--green)' : 'var(--red)' ?>">
    </div>
    <button class="btn btn-ghost btn-sm" onclick="saveDriveConfig()">Запази Drive настройки</button>
    <span id="drive-status" style="font-size:12px;color:rgba(255,255,255,.38);margin-left:8px"></span>
  </div>
</div>

<!-- ── SMTP / Email Card ── -->
<div class="int-card">
  <div class="int-card-hdr">
    <div class="int-card-icon" style="background:rgba(234,67,53,.1)">✉️</div>
    <div>
      <div class="int-card-title">SMTP / Email</div>
      <div class="int-card-desc">Изпращане на имейли за покани и пароли</div>
    </div>
  </div>
  <div class="int-card-body">
    <div class="int-field">
      <label>SMTP User (Gmail)</label>
      <input type="email" id="smtp-user"
             value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>"
             placeholder="user@gmail.com">
    </div>
    <div class="int-field">
      <label>App Password</label>
      <input type="password" id="smtp-pass" placeholder="xxxx xxxx xxxx xxxx">
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <button class="btn btn-ghost btn-sm" onclick="saveSmtpConfig()">Запази</button>
      <button class="btn btn-ghost btn-sm" onclick="testEmail()">Тест имейл</button>
      <span id="smtp-status" style="font-size:12px;color:rgba(255,255,255,.38)"></span>
    </div>
  </div>
</div>

</div><!-- /.int-grid -->

<script>
// ── Firebase token toggle ──────────────────────────────────────────
let tokenVisible = false;
function toggleToken() {
  tokenVisible = !tokenVisible;
  const box = document.getElementById('fb-token-display');
  const ta  = document.getElementById('fb-token');
  if (tokenVisible) {
    box.style.display = 'none';
    ta.style.display  = '';
    ta.focus();
  } else {
    box.style.display = '';
    ta.style.display  = 'none';
  }
}

// ── Firebase badge update ──────────────────────────────────────────
function updateFbBadge() {
  const enabled = document.getElementById('fb-enabled').checked;
  const badge   = document.getElementById('fb-status-badge');
  badge.textContent = enabled ? 'Активен' : 'Неактивен';
  badge.className   = enabled ? 'int-badge-on' : 'int-badge-off';
}

// ── Save Firebase config ───────────────────────────────────────────
function saveFbConfig() {
  const status = document.getElementById('fb-op-status');
  status.textContent = 'Запазване…'; status.style.color = 'var(--gold)';

  const payload = {
    action: 'save_firebase',
    firebase: {
      project_id:      document.getElementById('fb-project-id').value.trim(),
      project_num:     document.getElementById('fb-project-num').value.trim(),
      db_url:          document.getElementById('fb-db-url').value.trim(),
      enabled:         document.getElementById('fb-enabled').checked,
      sync_on_import:  document.getElementById('fb-sync-import').checked,
      sync_on_update:  document.getElementById('fb-sync-update').checked,
    },
    firebase_token: document.getElementById('fb-token').value.trim(),
  };

  fetch('/settings/save', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(d => {
    status.textContent = d.success ? '✓ Запазено' : ('✗ ' + (d.error||'Грешка'));
    status.style.color = d.success ? 'var(--green)' : 'var(--red)';
    setTimeout(() => { status.textContent=''; status.style.color=''; }, 3000);
  })
  .catch(() => { status.textContent='✗ Грешка'; status.style.color='var(--red)'; });
}

// ── Test Firebase connection ───────────────────────────────────────
function testFbConnection() {
  const resultBox = document.getElementById('fb-test-result');
  const status    = document.getElementById('fb-op-status');
  status.textContent = '⏳ Тестване…'; status.style.color = 'var(--gold)';
  resultBox.style.display = 'none';

  fetch('/api/test-firebase', { method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      db_url:  document.getElementById('fb-db-url').value.trim(),
      token:   document.getElementById('fb-token').value.trim(),
    })
  })
  .then(r => r.json())
  .then(d => {
    status.textContent = '';
    resultBox.style.display = '';
    resultBox.style.borderColor = d.success ? 'rgba(61,187,127,.3)' : 'rgba(224,92,92,.3)';
    resultBox.innerHTML = d.success
      ? `<span style="color:var(--green)">✓ Връзката е успешна</span><br><span style="color:rgba(255,255,255,.4);font-size:11px">${escH(d.message||'Firebase Realtime DB достъпна')}</span>`
      : `<span style="color:var(--red)">✗ Грешка при свързване</span><br><span style="color:rgba(255,255,255,.4);font-size:11px">${escH(d.error||'Неуспешна връзка')}</span>`;
  })
  .catch(e => {
    status.textContent = '';
    resultBox.style.display = '';
    resultBox.innerHTML = `<span style="color:var(--red)">✗ Мрежова грешка: ${escH(e.message)}</span>`;
  });
}

// ── Sync all products to Firebase ─────────────────────────────────
function syncAllToFirebase() {
  const status = document.getElementById('fb-op-status');
  if (!confirm('Ще синхронизираш ВСИЧКИ продукти с Firebase. Продължи?')) return;
  status.textContent = '⏳ Синхронизиране…'; status.style.color = 'var(--gold)';

  fetch('/api/sync-to-firebase', { method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({})
  })
  .then(r => r.json())
  .then(d => {
    status.textContent = d.success ? `✓ Синхронизирани ${d.count||'?'} продукта` : ('✗ ' + (d.error||'Грешка'));
    status.style.color = d.success ? 'var(--green)' : 'var(--red)';
    setTimeout(() => { status.textContent=''; status.style.color=''; }, 6000);
  })
  .catch(() => { status.textContent='✗ Грешка'; status.style.color='var(--red)'; });
}

// ── Save Drive config ──────────────────────────────────────────────
function saveDriveConfig() {
  const st = document.getElementById('drive-status');
  st.textContent = 'Запазване…';
  fetch('/settings/save', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ action:'save_drive', drive_folder_id: document.getElementById('drive-folder-id').value.trim() })
  }).then(r=>r.json()).then(d=>{
    st.textContent = d.success ? '✓ Запазено' : '✗ Грешка';
    st.style.color = d.success ? 'var(--green)' : 'var(--red)';
    setTimeout(()=>{st.textContent='';st.style.color='';},3000);
  });
}

// ── Save SMTP ──────────────────────────────────────────────────────
function saveSmtpConfig() {
  const st = document.getElementById('smtp-status');
  st.textContent = 'Запазване…';
  fetch('/settings/save', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      action:'save_smtp',
      smtp_user: document.getElementById('smtp-user').value.trim(),
      smtp_pass: document.getElementById('smtp-pass').value,
    })
  }).then(r=>r.json()).then(d=>{
    st.textContent = d.success ? '✓ Запазено' : '✗ Грешка';
    st.style.color = d.success ? 'var(--green)' : 'var(--red)';
    setTimeout(()=>{st.textContent='';st.style.color='';},3000);
  });
}

function testEmail() {
  const st = document.getElementById('smtp-status');
  st.textContent = 'Изпращане…'; st.style.color = 'var(--gold)';
  fetch('/api/test-email', { method:'POST' })
    .then(r=>r.json()).then(d=>{
      st.textContent = d.success ? '✓ Изпратен' : '✗ Грешка';
      st.style.color = d.success ? 'var(--green)' : 'var(--red)';
    });
}

function escH(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
