<?php
/**
 * Firebase Admin View — embedded in main layout
 * v1.7.0
 */
$settings = $settings ?? [];
$fb       = $fb ?? [];
$products = $products ?? [];
$dbUrl    = $dbUrl ?? 'https://amz-retail-default-rtdb.europe-west1.firebasedatabase.app';
?>
<style>
.fa-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:24px}
@media(max-width:700px){.fa-grid{grid-template-columns:1fr}}
.fa-card{background:#181C26;border:1px solid rgba(255,255,255,.07);border-radius:9px;overflow:hidden}
.fa-card-hdr{padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:8px}
.fa-card-title{font-size:13px;font-weight:700;color:#fff}
.fa-card-body{padding:16px 18px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:12px}
.info-row:last-child{border-bottom:none}
.info-label{color:rgba(255,255,255,.4)}
code{background:rgba(255,255,255,.06);padding:2px 6px;border-radius:3px;font-family:'Courier New',monospace;font-size:11px;color:#C9A84C}
.status-circle{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px}
.status-ok{background:#3DBB7F}.status-err{background:#E05C5C}.status-unk{background:#C9A84C}
.log-box{background:#0D0F14;border:1px solid rgba(255,255,255,.07);border-radius:5px;padding:12px;font-family:'Courier New',monospace;font-size:11px;color:rgba(255,255,255,.6);max-height:240px;overflow-y:auto;line-height:1.8}
.log-ok{color:#3DBB7F}.log-err{color:#E05C5C}.log-info{color:#4A7CFF}.log-warn{color:#C9A84C}
.progress-bar{background:rgba(255,255,255,.06);border-radius:4px;height:6px;overflow:hidden;margin-top:8px}
.progress-fill{background:#4A7CFF;height:100%;border-radius:4px;transition:width .3s}
.path-input{background:#0D0F14;border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:6px 10px;font-size:12px;color:#fff;font-family:'Courier New',monospace;outline:none;width:100%}
.path-input:focus{border-color:#4A7CFF}
.json-viewer{background:#0D0F14;border:1px solid rgba(255,255,255,.07);border-radius:5px;padding:12px;font-family:'Courier New',monospace;font-size:11px;color:rgba(255,255,255,.7);max-height:200px;overflow:auto;white-space:pre;line-height:1.6;margin-top:8px}
.guide-card{background:#181C26;border:1px solid rgba(255,202,40,.15);border-radius:9px;overflow:hidden;grid-column:1/-1;margin-top:0}
.guide-steps{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;font-size:12px;color:rgba(255,255,255,.65);line-height:1.8}
</style>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:4px">
  <h1 style="font-size:18px;font-weight:700;color:#fff">🔥 Firebase Admin</h1>
  <span style="font-size:11px;padding:2px 10px;border-radius:12px;font-weight:700;background:rgba(255,202,40,.1);color:#FFCA28;border:1px solid rgba(255,202,40,.2)">Project: amz-retail</span>
  <span id="conn-indicator" style="margin-left:auto;font-size:12px;color:rgba(255,255,255,.4)">⏳ Проверяване…</span>
</div>
<p style="font-size:12px;color:rgba(255,255,255,.4);margin-bottom:0">Firebase Realtime Database management — Project #820571488028</p>

<div class="fa-grid">

<!-- ── Guide ── -->
<div class="guide-card">
  <div class="fa-card-hdr" style="background:rgba(255,202,40,.04)">
    <span style="font-size:16px">📋</span>
    <div class="fa-card-title" style="color:#FFCA28">Първи стъпки — Как да настроиш Firebase</div>
  </div>
  <div class="fa-card-body">
    <div class="guide-steps">
      <div><div style="font-weight:700;color:#FFCA28;margin-bottom:4px">1️⃣ Firebase Console</div>
        Отвори <a href="https://console.firebase.google.com/project/amz-retail" target="_blank" style="color:#4A7CFF">console.firebase.google.com</a> → проект <code>amz-retail</code></div>
      <div><div style="font-weight:700;color:#FFCA28;margin-bottom:4px">2️⃣ Създай Realtime Database</div>
        Build → <strong>Realtime Database</strong> → <strong>Create Database</strong> → регион <code>europe-west1</code></div>
      <div><div style="font-weight:700;color:#FFCA28;margin-bottom:4px">3️⃣ Режим</div>
        Избери <strong>Test mode</strong> (30 дни) → <strong>Enable</strong></div>
      <div><div style="font-weight:700;color:#FFCA28;margin-bottom:4px">4️⃣ Database URL</div>
        <code>https://amz-retail-default-rtdb.europe-west1.firebasedatabase.app</code></div>
      <div><div style="font-weight:700;color:#FFCA28;margin-bottom:4px">5️⃣ Database Secret</div>
        Project Settings → Service Accounts → <strong>Database secrets</strong> → Add secret</div>
      <div><div style="font-weight:700;color:#FFCA28;margin-bottom:4px">6️⃣ Мигрирай</div>
        Кликни <strong>"Пълна миграция"</strong> — всички <?= number_format(count($products)) ?> продукта ще се качат</div>
    </div>
  </div>
</div>

<!-- ── Connection & Info ── -->
<div class="fa-card">
  <div class="fa-card-hdr"><span>🔌</span><div class="fa-card-title">Връзка & Детайли</div></div>
  <div class="fa-card-body">
    <div class="info-row"><span class="info-label">Project ID</span><code><?= htmlspecialchars($fb['project_id'] ?? 'amz-retail') ?></code></div>
    <div class="info-row"><span class="info-label">Project Number</span><code><?= htmlspecialchars($fb['project_num'] ?? '820571488028') ?></code></div>
    <div class="info-row"><span class="info-label">Database URL</span><code style="font-size:10px;word-break:break-all"><?= htmlspecialchars($dbUrl) ?></code></div>
    <div class="info-row"><span class="info-label">Region</span><code>europe-west1</code></div>
    <div class="info-row"><span class="info-label">Статус</span><span id="conn-status-cell"><span class="status-circle status-unk"></span>Непроверен</span></div>
    <div class="info-row"><span class="info-label">Latency</span><span id="latency-cell" style="font-size:11px;font-family:monospace">—</span></div>
    <div style="margin-top:12px;display:flex;gap:8px">
      <button class="btn btn-ghost btn-sm" onclick="faTest()">🔌 Тест</button>
      <a href="https://console.firebase.google.com/project/amz-retail/database" target="_blank" class="btn btn-ghost btn-sm">🔗 Console</a>
    </div>
  </div>
</div>

<!-- ── Stats ── -->
<div class="fa-card">
  <div class="fa-card-hdr"><span>📊</span><div class="fa-card-title">Статистики</div></div>
  <div class="fa-card-body">
    <div class="info-row"><span class="info-label">Продукти в JSON</span><code><?= number_format(count($products)) ?></code></div>
    <div class="info-row"><span class="info-label">С ASIN</span><code><?= number_format(count(array_filter($products, fn($p) => !empty($p['ASIN'])))) ?></code></div>
    <div class="info-row"><span class="info-label">Доставчици</span><code><?= count(array_unique(array_filter(array_column($products, 'Доставчик')))) ?></code></div>
    <div class="info-row"><span class="info-label">Firebase enabled</span><code><?= ($fb['enabled'] ?? false) ? '✓ Yes' : '✗ No' ?></code></div>
    <div class="info-row"><span class="info-label">products.json</span><code><?= number_format(filesize(CACHE_DIR . '/products.json') / 1024, 0) ?> KB</code></div>
  </div>
</div>

<!-- ── Migration ── -->
<div class="fa-card" style="grid-column:1/-1">
  <div class="fa-card-hdr"><span>🚀</span><div class="fa-card-title">Миграция → Firebase (<?= count($products) ?> продукта)</div></div>
  <div class="fa-card-body">
    <p style="font-size:12px;color:rgba(255,255,255,.5);margin-bottom:12px">
      Изпраща ВСИЧКИ продукти, настройки и доставчици към Firebase. Безопасна операция — данните се merge-ват.
    </p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
      <button class="btn btn-primary btn-sm" onclick="faMigrate()">🔥 Пълна миграция</button>
      <button class="btn btn-ghost btn-sm" onclick="faTest()">🔌 Тест връзка</button>
      <div id="mig-status" style="font-size:12px;color:rgba(255,255,255,.4)"></div>
    </div>
    <div class="progress-bar" id="mig-progress-wrap" style="display:none"><div class="progress-fill" id="mig-progress" style="width:0%"></div></div>
    <div class="log-box" id="mig-log" style="display:none"></div>
  </div>
</div>

<!-- ── DB Explorer ── -->
<div class="fa-card" style="grid-column:1/-1">
  <div class="fa-card-hdr"><span>🔍</span><div class="fa-card-title">Database Explorer</div></div>
  <div class="fa-card-body">
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
      <input type="text" class="path-input" id="explorer-path" value="/meta" placeholder="/products, /meta, /settings …"
             style="flex:1" onkeydown="if(event.key==='Enter')faRead()">
      <button class="btn btn-ghost btn-sm" onclick="faRead()">📖 Прочети</button>
      <button class="btn btn-sm" style="background:rgba(224,92,92,.15);color:#E05C5C;border:1px solid rgba(224,92,92,.25)" onclick="faDelete()">🗑 Изтрий</button>
    </div>
    <div class="json-viewer" id="explorer-result">// Резултатът ще се появи тук…</div>
  </div>
</div>

</div><!-- /.fa-grid -->

<script>
const FA_URL = '/firebase-admin';

async function faPost(body) {
  const r = await fetch(FA_URL, { method:'POST',
    headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
  return r.json();
}
function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

async function faTest() {
  const ind  = document.getElementById('conn-indicator');
  const cell = document.getElementById('conn-status-cell');
  const lat  = document.getElementById('latency-cell');
  ind.textContent = '⏳ Тестване…'; ind.style.color = '#C9A84C';
  try {
    const d = await faPost({action:'test'});
    if (d.ok) {
      ind.style.color='#3DBB7F'; ind.textContent='✓ Свързан';
      cell.innerHTML='<span class="status-circle status-ok"></span>Свързан';
      lat.textContent = d.latency_ms + ' ms';
    } else {
      ind.style.color='#E05C5C'; ind.textContent='✗ Грешка';
      let msg = escH(d.error||'Неуспешна връзка');
      if (d.code===404) msg='404 — Базата не е създадена. <a href="https://console.firebase.google.com/project/amz-retail/database" target="_blank" style="color:#4A7CFF">Създай →</a>';
      cell.innerHTML='<span class="status-circle status-err"></span>' + msg;
      if (d.hint) cell.innerHTML += '<br><span style="color:rgba(255,255,255,.4);font-size:11px">'+escH(d.hint)+'</span>';
    }
  } catch(e) { ind.style.color='#E05C5C'; ind.textContent='✗ '+e.message; }
}

async function faMigrate() {
  if (!confirm('Ще изпратиш всички данни към Firebase. Продължи?')) return;
  const log=document.getElementById('mig-log'), status=document.getElementById('mig-status'),
        prog=document.getElementById('mig-progress'), wrap=document.getElementById('mig-progress-wrap');
  log.style.display=''; wrap.style.display='';
  log.innerHTML='<span class="log-info">⏳ Старт…</span>\n';
  status.textContent='Работи…'; status.style.color='#C9A84C';
  prog.style.width='10%';
  try {
    const d = await faPost({action:'migrate'});
    prog.style.width='100%';
    if (d.ok) {
      status.textContent=`✓ ${d.synced}/${d.total} продукта`; status.style.color='#3DBB7F';
      for(const[k,v] of Object.entries(d.steps||{})) {
        const cls=v==='ok'?'log-ok':'log-err';
        log.innerHTML+=`<span class="${cls}">✓ ${escH(k)}: ${escH(String(v))}</span>\n`;
      }
      log.innerHTML+=`<span class="log-ok">🎉 Готово! ${d.synced}/${d.total} в Firebase!</span>\n`;
    } else {
      status.textContent=`⚠ ${d.synced||0}/${d.total||0}`; status.style.color='#E05C5C';
      for(const[k,v] of Object.entries(d.steps||{}))
        log.innerHTML+=`<span class="${v==='ok'?'log-ok':'log-err'}">${escH(k)}: ${escH(String(v))}</span>\n`;
    }
  } catch(e) { status.textContent='✗ Грешка'; log.innerHTML+=`<span class="log-err">✗ ${escH(e.message)}</span>\n`; }
}

async function faRead() {
  const path=document.getElementById('explorer-path').value.trim()||'/meta';
  const viewer=document.getElementById('explorer-result');
  viewer.textContent='⏳ Зареждане…';
  const d=await faPost({action:'read_path',path});
  viewer.textContent=d.ok ? JSON.stringify(d.data,null,2) : '✗ '+(d.error||'Грешка');
}

async function faDelete() {
  const path=document.getElementById('explorer-path').value.trim();
  if(!path||path==='/'){alert('Невалиден път');return;}
  if(!confirm(`⚠ Изтриваш '${path}' от Firebase?`))return;
  const d=await faPost({action:'delete_path',path});
  document.getElementById('explorer-result').textContent=d.ok?'✓ Изтрито':'✗ '+(d.error||'Грешка');
}

window.addEventListener('DOMContentLoaded',()=>faTest());
</script>
