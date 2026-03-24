<?php
/**
 * Products v3.0 — AJAX-based grid
 * Страницата зарежда само shell + filters.
 * Данните идват от /products/data (JSON) — без Firebase timeout риск на page load.
 */
$stats     = $stats     ?? [];
$suppliers = $suppliers ?? [];
$brands    = $brands    ?? [];
$filters   = $filters   ?? [];
$perPage   = $perPage   ?? 50;
$page      = $page      ?? 1;

// Build query for AJAX call
$ajaxParams = array_merge($filters, ['perpage' => $perPage, 'page' => $page]);
$ajaxQuery  = http_build_query(array_filter($ajaxParams, fn($v) => $v !== '' && $v !== null));
?>
<style>
.pw{display:flex;flex-direction:column;height:calc(100vh - 62px);overflow:hidden;padding:0}
.psb{display:flex;gap:10px;padding:10px 16px 0;flex-shrink:0}
.psc{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:10px 16px;display:flex;align-items:center;gap:12px;flex:1;min-width:0;position:relative;overflow:hidden}
.psc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--gold)}
.psc.g::before{background:var(--green)}.psc.b::before{background:var(--blue)}.psc.a::before{background:var(--amber)}
.psi{width:32px;height:32px;border-radius:8px;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--gold)}
.psc.g .psi{background:rgba(61,187,127,.1);border-color:rgba(61,187,127,.2);color:var(--green)}
.psc.b .psi{background:rgba(74,124,255,.1);border-color:rgba(74,124,255,.2);color:var(--blue)}
.psc.a .psi{background:rgba(245,166,35,.1);border-color:rgba(245,166,35,.2);color:var(--amber)}
.psv{font-family:var(--font-head);font-size:20px;font-weight:800;color:#fff;line-height:1}
.psl{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-top:2px}
.pf{padding:10px 16px 0;flex-shrink:0}
.pfi{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;width:100%}
.pfg{display:flex;flex-direction:column;gap:3px}
.pfg label{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.4)}
.pfg select,.pfg input{background:#0D0F14;border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:5px 10px;font-size:12px;color:#fff;font-family:inherit;outline:none;height:30px}
.pfg select{padding-right:24px;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 20 20'%3E%3Cpath d='M5 7.5l5 5 5-5' stroke='rgba(255,255,255,.5)' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 6px center}
.pfg select option{background:#0D0F14;color:#E8E6E1}
.pfg select:focus,.pfg input:focus{border-color:var(--gold)}
.pfs{flex:1;min-width:180px}
.pfa{display:flex;gap:6px;align-items:flex-end;margin-left:auto}
.par{display:flex;justify-content:space-between;align-items:center;padding:8px 16px 0;flex-shrink:0;gap:6px;flex-wrap:wrap}
.par-info{font-size:12px;color:rgba(255,255,255,.4)}
.par-info strong{color:rgba(255,255,255,.75)}
.pgo{flex:1;min-height:0;padding:8px 16px 10px;display:flex;flex-direction:column}
.pgw{flex:1;border:1px solid rgba(255,255,255,.07);border-radius:8px;display:flex;flex-direction:column;overflow:hidden;min-height:0;background:var(--panel)}
.pgs{flex:1;overflow:auto;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.2) rgba(255,255,255,.04)}
.pgs::-webkit-scrollbar{width:8px;height:9px}
.pgs::-webkit-scrollbar-track{background:rgba(255,255,255,.03)}
.pgs::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:4px;border:2px solid transparent;background-clip:padding-box}
.pgs::-webkit-scrollbar-thumb:hover{background:rgba(201,168,76,.5);border:2px solid transparent;background-clip:padding-box}
.pgs::-webkit-scrollbar-corner{background:#12151C}
.pgt{width:max-content;min-width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}
.pgt thead th{position:sticky;top:0;z-index:30;background:#1A1F30;border-bottom:2px solid rgba(201,168,76,.25);padding:0;vertical-align:middle;box-shadow:0 2px 0 rgba(0,0,0,.3)}
.th-in{display:flex;align-items:center;gap:4px;padding:8px 10px;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:rgba(255,255,255,.6);white-space:nowrap;border-right:1px solid rgba(255,255,255,.04)}
.th-in a{color:inherit;text-decoration:none;display:flex;align-items:center;gap:4px}
.th-in a:hover{color:#fff}
.pgt tbody tr:hover td{background:rgba(255,255,255,.03)!important}
.pgt tbody td{padding:5px 10px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;color:#E8E6E1;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:0;border-right:1px solid rgba(255,255,255,.02);height:34px}
.pgt tbody tr:last-child td{border-bottom:none}
td.cn{text-align:right;font-variant-numeric:tabular-nums}
td.cm{font-family:monospace;font-size:11px;color:rgba(232,230,225,.65)}
td.cl{text-align:center}
td.cr{text-align:right;font-variant-numeric:tabular-nums;font-weight:700}
td.cr.pos{color:#5DCCA0}td.cr.neg{color:#E05C5C}td.cr.zer{color:rgba(255,255,255,.25)}
td.ed{cursor:text;position:relative}
td.ed::after{content:'';position:absolute;bottom:2px;left:8px;right:8px;height:1px;background:rgba(201,168,76,.2)}
td.ed:hover{background:rgba(201,168,76,.05)!important}td.ed:hover::after{background:rgba(201,168,76,.5)}
.ci{width:100%;background:rgba(201,168,76,.08);border:1px solid var(--gold);border-radius:3px;padding:2px 6px;color:#fff;font-size:12px;font-family:inherit;outline:none;height:22px}
.bg{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;line-height:1.6}
.bg-g{background:rgba(61,187,127,.15);color:#5DCCA0;border:1px solid rgba(61,187,127,.3)}
.bg-a{background:rgba(201,168,76,.15);color:var(--gold-lt);border:1px solid rgba(201,168,76,.3)}
.bg-m{background:rgba(255,255,255,.07);color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.1)}
.elek{display:inline-block;width:52px;padding:2px 0;border-radius:20px;font-size:10px;font-weight:700;line-height:1.6;text-align:center;cursor:pointer;user-select:none;border:1px solid transparent;color:#fff}
.elek.y{background:rgba(61,187,127,.2);border-color:rgba(61,187,127,.4)}
.elek.n{background:rgba(255,255,255,.06);color:rgba(255,255,255,.4);border-color:rgba(255,255,255,.1)}
.al{display:inline-flex;align-items:center;justify-content:center;width:24px;height:20px;border-radius:4px;background:rgba(255,153,0,.1);border:1px solid rgba(255,153,0,.2);color:#FFA500;text-decoration:none}
.al:hover{background:rgba(255,153,0,.2);color:#fff}
.pgp{display:flex;justify-content:space-between;align-items:center;padding:7px 14px;border-top:1px solid rgba(255,255,255,.06);background:#181C26;flex-wrap:wrap;gap:6px;flex-shrink:0;border-bottom-left-radius:8px;border-bottom-right-radius:8px}
.pgpg{display:flex;gap:3px;align-items:center;flex-wrap:wrap}
.pgb{min-width:28px;height:26px;padding:0 7px;border-radius:4px;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,.1);background:transparent;color:rgba(255,255,255,.5);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.pgb:hover{background:rgba(255,255,255,.06);color:#fff}
.pgb.act{background:var(--gold);color:#0D0F14;border-color:var(--gold);font-weight:700}
.pgb[disabled]{opacity:.3;pointer-events:none}
.pgpp{display:flex;gap:5px;align-items:center;font-size:12px;color:rgba(255,255,255,.4)}
.pgpp select{background:#0D0F14;border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:3px 6px;font-size:12px;color:#fff;outline:none}
.pgpp select option{background:#0D0F14}
.loading-row td{text-align:center;padding:40px!important;color:rgba(255,255,255,.35)!important}
.error-row td{text-align:center;padding:40px!important;color:#F08080!important}
.empty-row td{text-align:center;padding:40px!important;color:rgba(255,255,255,.3)!important}
.toast{position:fixed;bottom:20px;right:20px;background:var(--green);color:#fff;padding:8px 16px;border-radius:6px;font-size:12px;font-weight:700;z-index:9999;display:none}
</style>

<div class="pw">

<!-- Stats -->
<div class="psb">
  <div class="psc">
    <div class="psi"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M2 5h16M2 10h16M2 15h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
    <div><div class="psv" id="s-total"><?= number_format($stats['total'] ?? 0) ?></div><div class="psl">Общо</div></div>
  </div>
  <div class="psc g">
    <div class="psi"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M2 10l5 5 9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
    <div><div class="psv"><?= number_format($stats['withAsin'] ?? 0) ?></div><div class="psl">С ASIN</div></div>
  </div>
  <div class="psc a">
    <div class="psi"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M10 6v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.8"/></svg></div>
    <div><div class="psv"><?= number_format($stats['notUploaded'] ?? 0) ?></div><div class="psl">За качване</div></div>
  </div>
  <div class="psc b">
    <div class="psi"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M3 17v-1a5 5 0 015-5h4a5 5 0 015 5v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/></svg></div>
    <div><div class="psv"><?= number_format($stats['suppliers'] ?? 0) ?></div><div class="psl">Доставчици</div></div>
  </div>
</div>

<!-- Filters -->
<div class="pf">
<div class="pfi">
  <div class="pfg"><label>Доставчик</label>
    <select id="f-dost" style="min-width:130px" onchange="updateBrands(this.value)">
      <option value="">— Всички —</option>
      <?php foreach ($suppliers as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= ($filters['dostavchik'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="pfg"><label>Бранд</label>
    <select id="f-brand" style="min-width:100px" onchange="applyFilters()">
      <option value="">— Всички —</option>
      <?php foreach ($brands as $b): ?>
      <option value="<?= htmlspecialchars($b) ?>" <?= ($filters['brand'] ?? '') === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="pfg"><label>Статус</label>
    <select id="f-status" style="min-width:110px" onchange="applyFilters()">
      <option value="">— Всички —</option>
      <option value="NOT_UPLOADED" <?= ($filters['upload_status'] ?? '') === 'NOT_UPLOADED' ? 'selected' : '' ?>>За качване</option>
      <option value="UPLOADED" <?= ($filters['upload_status'] ?? '') === 'UPLOADED' ? 'selected' : '' ?>>Качени</option>
    </select>
  </div>
  <div class="pfg pfs"><label>Търсене</label>
    <input type="text" id="f-search" placeholder="Модел, EAN, ASIN, SKU…" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width:100%" onkeydown="if(event.key==='Enter')applyFilters()">
  </div>
  <div class="pfa">
    <button class="btn btn-primary btn-sm" onclick="applyFilters()">Търси</button>
    <button class="btn btn-ghost btn-sm" onclick="clearFilters()">Изчисти</button>
  </div>
</div>
</div>

<!-- Action row -->
<div class="par">
  <div class="par-info" id="par-info">Зареждане…</div>
  <div style="display:flex;gap:6px">
    <a href="/products/add" class="btn btn-ghost btn-sm">
      <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Добави
    </a>
    <a href="/products/import" class="btn btn-ghost btn-sm">↑ Import</a>
    <a id="export-btn" href="/products/export" class="btn btn-ghost btn-sm">↓ CSV</a>
  </div>
</div>

<!-- Grid -->
<div class="pgo"><div class="pgw"><div class="pgs" id="pgs">
<table class="pgt" id="pgt">
  <thead><tr id="thead-row">
    <th style="width:120px"><div class="th-in"><a href="#" onclick="sortBy('EAN Amazon');return false">EAN</a></div></th>
    <th style="width:110px"><div class="th-in"><a href="#" onclick="sortBy('Наше SKU');return false">Наше SKU</a></div></th>
    <th style="width:100px"><div class="th-in"><a href="#" onclick="sortBy('Доставчик');return false">Доставчик</a></div></th>
    <th style="width:88px"><div class="th-in"><a href="#" onclick="sortBy('Бранд');return false">Бранд</a></div></th>
    <th style="width:220px"><div class="th-in"><a href="#" onclick="sortBy('Модел');return false">Модел</a></div></th>
    <th style="width:46px"><div class="th-in">Link</div></th>
    <th style="width:110px"><div class="th-in"><a href="#" onclick="sortBy('ASIN');return false">ASIN</a></div></th>
    <th style="width:80px"><div class="th-in"><a href="#" onclick="sortBy('Цена Доставчик -Netto');return false">Дост.€</a></div></th>
    <th style="width:80px"><div class="th-in"><a href="#" onclick="sortBy('Продажна Цена в Амазон  - Brutto');return false">Продажна€</a></div></th>
    <th style="width:80px"><div class="th-in"><a href="#" onclick="sortBy('Amazon Такси');return false">Амз.такса</a></div></th>
    <th style="width:80px"><div class="th-in"><a href="#" onclick="sortBy('Транспорт до кр. лиент  Netto');return false">Транспорт</a></div></th>
    <th style="width:78px"><div class="th-in"><a href="#" onclick="sortBy('Резултат');return false">Резултат</a></div></th>
    <th style="width:75px"><div class="th-in">Корекция</div></th>
    <th style="width:75px"><div class="th-in">DM цена</div></th>
    <th style="width:80px"><div class="th-in">Електроника</div></th>
    <th style="width:160px"><div class="th-in">Коментар</div></th>
    <th style="width:70px"><div class="th-in">Статус</div></th>
  </tr></thead>
  <tbody id="tbody">
    <tr class="loading-row"><td colspan="17">
      <div style="display:flex;align-items:center;justify-content:center;gap:10px">
        <span class="spinner"></span> Зареждане от Firebase…
      </div>
    </td></tr>
  </tbody>
</table>
</div>
<div class="pgp" id="pgp" style="display:none">
  <div style="font-size:12px;color:rgba(255,255,255,.4)" id="pgp-info"></div>
  <div class="pgpg" id="pgp-pages"></div>
  <div class="pgpp">На стр.:
    <select id="pp-sel" onchange="changePerPage(this.value)">
      <?php foreach ([25,50,100,250] as $pp): ?><option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option><?php endforeach; ?>
    </select>
  </div>
</div>
</div></div></div>
</div>

<div class="toast" id="toast"></div>

<script>
// ── State ─────────────────────────────────────────────────────
const STATE = {
  dostavchik:    <?= json_encode($filters['dostavchik']    ?? '') ?>,
  brand:         <?= json_encode($filters['brand']         ?? '') ?>,
  upload_status: <?= json_encode($filters['upload_status'] ?? '') ?>,
  search:        <?= json_encode($filters['search']        ?? '') ?>,
  sort:          <?= json_encode($filters['sort']          ?? '') ?>,
  dir:           <?= json_encode($filters['dir']           ?? 'asc') ?>,
  page:          <?= (int)$page ?>,
  perpage:       <?= (int)$perPage ?>,
};

// Column definitions [key, type, editable]
const COLS = [
  ['EAN Amazon',                       'mono',   false],
  ['Наше SKU',                         'mono',   false],
  ['Доставчик',                        'text',   false],
  ['Бранд',                            'text',   false],
  ['Модел',                            'text',   false],
  ['Amazon Link',                      'link',   false],
  ['ASIN',                             'mono',   false],
  ['Цена Доставчик -Netto',            'num',    true],
  ['Продажна Цена в Амазон  - Brutto', 'num',    true],
  ['Amazon Такси',                     'num',    false],
  ['Транспорт до кр. лиент  Netto',    'num',    true],
  ['Резултат',                         'result', false],
  ['Корекция  на цена',                'num',    true],
  ['DM цена',                          'num',    true],
  ['Електоника',                       'toggle', true],
  ['Коментар',                         'text',   true],
];

// ── Load products via AJAX ─────────────────────────────────────
function loadProducts() {
  const tbody = document.getElementById('tbody');
  tbody.innerHTML = '<tr class="loading-row"><td colspan="17"><div style="display:flex;align-items:center;justify-content:center;gap:10px"><span class="spinner"></span> Зареждане…</div></td></tr>';
  document.getElementById('pgp').style.display = 'none';

  const params = new URLSearchParams();
  Object.entries(STATE).forEach(([k, v]) => { if (v !== '' && v !== null) params.set(k, v); });

  // Update export link
  const ep = new URLSearchParams(params);
  ep.delete('page'); ep.delete('perpage'); ep.delete('sort'); ep.delete('dir');
  document.getElementById('export-btn').href = '/products/export?' + ep.toString();

  fetch('/products/data?' + params.toString())
    .then(r => r.text().then(text => {
      let data;
      try { data = JSON.parse(text); } catch(e) {
        // PHP returned HTML error page — show it
        throw new Error('Сървърът върна HTML вместо JSON (вероятно PHP Fatal Error).\n\nПърви 300 символа:\n' + text.substring(0, 300));
      }
      return data;
    }))
    .then(data => {
      if (!data.ok) {
        let msg = '<strong>✗ Грешка:</strong> ' + escH(data.error || 'Неизвестна грешка');
        if (data.file) msg += '<br><code style="font-size:10px;opacity:.7">Файл: ' + escH(data.file) + '</code>';
        if (data.diag) {
          msg += '<br><br><strong>Диагностика:</strong>';
          msg += '<br>• Firebase ready: ' + (data.diag.firebase_ready ? '✅' : '❌ НЕ');
          msg += '<br>• cURL: ' + (data.diag.curl ? '✅' : '❌ НЕ');
          msg += '<br>• allow_url_fopen: ' + (data.diag.fopen ? '✅' : '❌ НЕ');
          msg += '<br>• DB URL: ' + escH(data.diag.db_url || 'ЛИПСВА');
          msg += '<br>• Secret дължина: ' + (data.diag.secret_len || 0) + ' символа (трябва ~40)';
        }
        msg += '<br><br><a href="/products/diagnose" target="_blank" style="color:var(--gold)">Пълна диагностика →</a>';
        tbody.innerHTML = '<tr class="error-row"><td colspan="17" style="padding:24px!important">' + msg + '</td></tr>';
        document.getElementById('par-info').innerHTML = '<span style="color:var(--red)">✗ Firebase грешка</span>';
        return;
      }
      renderTable(data.products, data);
      document.getElementById('s-total').textContent = data.total.toLocaleString();
    })
    .catch(err => {
      const msg = escH(err.message).replace(/\n/g, '<br>');
      tbody.innerHTML = '<tr class="error-row"><td colspan="17" style="padding:24px!important;font-size:12px"><strong>✗ Грешка при зареждане:</strong><br><br>' + msg + '<br><br><a href="/products/diagnose" target="_blank" style="color:var(--gold)">Отвори диагностика →</a></td></tr>';
      document.getElementById('par-info').innerHTML = '<span style="color:var(--red)">✗ Грешка</span>';
    });
}

function renderTable(products, meta) {
  const tbody = document.getElementById('tbody');
  const total  = meta.total;
  const page   = meta.page;
  const pages  = meta.pages;
  const pp     = meta.perPage;
  const from   = total > 0 ? (page - 1) * pp + 1 : 0;
  const to     = Math.min(page * pp, total);

  // Update info
  document.getElementById('par-info').innerHTML =
    total > 0 ? `Показани <strong>${from.toLocaleString()}–${to.toLocaleString()}</strong> от <strong>${total.toLocaleString()}</strong>` : '<span style="color:rgba(255,255,255,.25)">Няма продукти</span>';

  if (products.length === 0) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="17">Няма намерени продукти.<br><small><a href="/products/import" style="color:var(--gold)">Импортирай Excel →</a></small></td></tr>';
    document.getElementById('pgp').style.display = 'none';
    return;
  }

  let html = '';
  for (const p of products) {
    const ean    = p['EAN Amazon'] || '';
    const eanH   = escH(ean);
    const link   = p['Amazon Link'] || '';
    const asin   = p['ASIN'] || '';
    const status = p['_upload_status'] || 'NOT_UPLOADED';
    const elek   = p['Електоника'] || '';
    const res    = parseFloat(p['Резултат'] || '0') || 0;
    const resC   = res > 0 ? 'pos' : (res < 0 ? 'neg' : 'zer');

    html += `<tr data-ean="${eanH}">`;
    for (const [key, type, editable] of COLS) {
      const raw  = p[key] ?? '';
      const valH = escH(String(raw));
      const attr = editable ? `data-ean="${eanH}" data-field="${escH(key)}" onclick="editCell(this)"` : '';
      if (type === 'link') {
        html += `<td class="cl">${link ? `<a href="${escH(link)}" target="_blank" class="al"><svg width="10" height="10" viewBox="0 0 20 20" fill="none"><path d="M11 3h6v6M9 11L17 3M7 5H4a1 1 0 00-1 1v10a1 1 0 001 1h10a1 1 0 001-1v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>` : ''}</td>`;
      } else if (key === 'ASIN') {
        html += `<td class="cm">${link && asin ? `<a href="${escH(link)}" target="_blank" style="color:var(--gold-lt);text-decoration:none">${valH}</a>` : valH}</td>`;
      } else if (type === 'toggle') {
        const y = elek === 'Yes';
        html += `<td class="cl"><span class="elek ${y?'y':'n'}" data-ean="${eanH}" data-val="${escH(elek)}" onclick="toggleElek(this)">${elek || '—'}</span></td>`;
      } else if (type === 'result') {
        html += `<td class="cr ${resC}">${res !== 0 ? fmtNum(res) : ''}</td>`;
      } else if (type === 'num') {
        const n = raw !== '' && raw !== null ? parseFloat(raw) : null;
        html += `<td class="cn${editable?' ed':''}" ${attr}>${n !== null && !isNaN(n) ? fmtNum(n) : ''}</td>`;
      } else {
        html += `<td class="ct${editable?' ed':''}" ${attr} title="${valH}">${valH}</td>`;
      }
    }
    html += `<td class="cl"><span class="bg ${status==='UPLOADED'?'bg-g':'bg-a'}">${status==='UPLOADED'?'Качен':'Не качен'}</span></td>`;
    html += '</tr>';
  }
  tbody.innerHTML = html;

  // Pagination
  renderPager(page, pages, total, pp);
}

function renderPager(page, pages, total, pp) {
  const pgp = document.getElementById('pgp');
  pgp.style.display = 'flex';

  document.getElementById('pgp-info').textContent = total > 0 ? `Стр. ${page}/${pages} · ${total.toLocaleString()} записа` : '';

  let btns = '';
  const disabled = (on) => on ? '' : 'disabled';
  btns += `<button class="pgb" onclick="goPage(1)" ${disabled(page>1)}>«</button>`;
  btns += `<button class="pgb" onclick="goPage(${page-1})" ${disabled(page>1)}>‹</button>`;

  let s = Math.max(1, Math.min(page - 3, pages - 6));
  let e = Math.min(pages, Math.max(page + 3, 7));
  if (s > 1) btns += '<span style="color:rgba(255,255,255,.3);padding:0 3px">…</span>';
  for (let i = s; i <= e; i++) {
    btns += `<button class="pgb${i===page?' act':''}" onclick="goPage(${i})">${i}</button>`;
  }
  if (e < pages) btns += '<span style="color:rgba(255,255,255,.3);padding:0 3px">…</span>';
  btns += `<button class="pgb" onclick="goPage(${page+1})" ${disabled(page<pages)}>›</button>`;
  btns += `<button class="pgb" onclick="goPage(${pages})" ${disabled(page<pages)}>»</button>`;
  document.getElementById('pgp-pages').innerHTML = btns;
}

// ── Dynamic brand filter ──────────────────────────────────────
function updateBrands(supplier) {
  const brandSel = document.getElementById('f-brand');
  const currentBrand = brandSel.value;

  fetch('/products/brands?supplier=' + encodeURIComponent(supplier))
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;
      brandSel.innerHTML = '<option value="">— Всички —</option>';
      d.brands.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b; opt.textContent = b;
        if (b === currentBrand) opt.selected = true;
        brandSel.appendChild(opt);
      });
      // Apply filters with new supplier
      applyFilters();
    })
    .catch(() => applyFilters()); // fallback: just apply filters
}

// ── Navigation ─────────────────────────────────────────────────
function goPage(p)          { STATE.page = p; loadProducts(); }
function sortBy(col)        { STATE.dir = (STATE.sort===col && STATE.dir==='asc') ? 'desc' : 'asc'; STATE.sort = col; STATE.page = 1; loadProducts(); }
function changePerPage(val) { STATE.perpage = parseInt(val); STATE.page = 1; loadProducts(); }

function applyFilters() {
  STATE.dostavchik    = document.getElementById('f-dost').value;
  STATE.brand         = document.getElementById('f-brand').value;
  STATE.upload_status = document.getElementById('f-status').value;
  STATE.search        = document.getElementById('f-search').value;
  STATE.page = 1;
  loadProducts();
}
function clearFilters() {
  STATE.dostavchik = STATE.brand = STATE.upload_status = STATE.search = '';
  STATE.sort = ''; STATE.dir = 'asc'; STATE.page = 1;
  document.getElementById('f-dost').value = '';
  document.getElementById('f-brand').value = '';
  document.getElementById('f-status').value = '';
  document.getElementById('f-search').value = '';
  loadProducts();
}

// ── Cell edit ──────────────────────────────────────────────────
function editCell(td) {
  if (td.querySelector('input')) return;
  const ean = td.dataset.ean, field = td.dataset.field;
  const orig = td.textContent.trim(), origV = orig.replace(/\s/g,'').replace(',','.');
  td.innerHTML = '';
  const inp = document.createElement('input');
  inp.type='text'; inp.value=origV; inp.className='ci';
  td.appendChild(inp); inp.focus(); inp.select();
  let saved = false;
  function commit() {
    if (saved) return; saved = true;
    const nv = inp.value.trim(), nf = parseFloat(nv.replace(',','.'));
    td.textContent = (!isNaN(nf) && nv!=='') ? nf.toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2}) : nv;
    if (nv !== origV && ean && field) saveCell(ean, field, nv.replace(',','.'));
  }
  inp.addEventListener('blur', commit);
  inp.addEventListener('keydown', e => {
    if (e.key==='Enter') inp.blur();
    if (e.key==='Escape') { saved=true; inp.removeEventListener('blur',commit); td.textContent=orig; }
    if (e.key==='Tab') { e.preventDefault(); inp.blur(); const cells=[...td.closest('tr').querySelectorAll('td.ed')]; const nx=cells[cells.indexOf(td)+1]; if(nx)nx.click(); }
  });
}
function saveCell(ean, field, value) {
  const fd=new FormData(); fd.append('ean',ean); fd.append('field',field); fd.append('value',value);
  fetch('/products/update',{method:'POST',body:fd}).then(r=>r.json())
    .then(d=>toast(d.success?'✓ Запазено':'✗ '+(d.error||'Грешка'),!d.success))
    .catch(()=>toast('✗ Мрежова грешка',true));
}
function toggleElek(el) {
  const next=el.dataset.val==='Yes'?'No':'Yes';
  el.textContent=next; el.dataset.val=next;
  el.className='elek '+(next==='Yes'?'y':'n');
  saveCell(el.dataset.ean,'Електоника',next);
}

// ── Helpers ────────────────────────────────────────────────────
function fmtNum(n) { return n.toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
let tt;
function toast(msg,isErr=false){const t=document.getElementById('toast');t.textContent=msg;t.style.background=isErr?'var(--red)':'var(--green)';t.style.display='block';clearTimeout(tt);tt=setTimeout(()=>t.style.display='none',2000);}
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key==='f'){e.preventDefault();document.getElementById('f-search').focus();}});

// ── Init ───────────────────────────────────────────────────────
loadProducts();
</script>
