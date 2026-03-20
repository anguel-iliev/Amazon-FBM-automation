<?php
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 50;
$offset   = ($page - 1) * $perPage;
$totalPages = max(1, (int)ceil($total / $perPage));
$paged    = array_slice($products, $offset, $perPage);
?>

<!-- Page header -->
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <span style="font-size:13px;color:var(--muted)"><?= number_format($total) ?> продукта</span>
    <?php if ($filter === 'not_uploaded'): ?>
    <span class="badge badge-gold">За качване</span>
    <?php endif; ?>
    <?php if ($search): ?>
    <span class="badge badge-muted">Търсене: <?= htmlspecialchars($search) ?></span>
    <?php endif; ?>
  </div>
  <div class="page-header-actions">
    <button onclick="importExcel()" class="btn btn-ghost btn-sm">
      <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><path d="M10 2v12M5 9l5 5 5-5M3 17h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Импорт Excel
    </button>
    <button onclick="exportCsv()" class="btn btn-ghost btn-sm">
      <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><path d="M10 18V6M5 11l5-5 5 5M3 3h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Експорт CSV
    </button>
    <a href="/sync" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 0 1 6-6 6 6 0 0 1 4.24 1.76M16 10a6 6 0 0 1-6 6 6 6 0 0 1-4.24-1.76" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14.24 4.76 16 3v3.5h-3.5M5.76 15.24 4 17v-3.5h3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      Синхронизирай
    </a>
  </div>
</div>

<!-- Filters -->
<div class="card mb-16" style="padding:14px 20px">
  <form method="GET" action="/products" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:200px">
      <input type="text" name="search" class="form-control" placeholder="Търси по EAN, ASIN, модел, марка..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div style="min-width:150px">
      <select name="source" class="form-control">
        <option value="">Всички доставчици</option>
        <?php foreach ($allSources as $s): ?>
        <option value="<?= htmlspecialchars($s) ?>" <?= $source === $s ? 'selected' : '' ?>>
          <?= htmlspecialchars($s) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:150px">
      <select name="filter" class="form-control">
        <option value="">Всички статуси</option>
        <option value="not_uploaded" <?= $filter === 'not_uploaded' ? 'selected' : '' ?>>За качване</option>
        <option value="uploaded"     <?= $filter === 'uploaded' ? 'selected' : '' ?>>Качени</option>
      </select>
    </div>
    <div style="min-width:140px">
      <select name="brand" class="form-control">
        <option value="">Всички марки</option>
        <?php foreach ($allBrands as $b): ?>
        <option value="<?= htmlspecialchars($b) ?>" <?= ($brand??'') === $b ? 'selected' : '' ?>>
          <?= htmlspecialchars($b) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-ghost">Филтрирай</button>
    <a href="/products" class="btn btn-ghost">Изчисти</a>
  </form>
</div>

<!-- Summary stats bar -->
<?php
$totalResult   = 0; $positiveCount = 0;
foreach ($products as $p) {
    $r = (float)($p['result'] ?? 0);
    $totalResult += $r;
    if ($r > 0) $positiveCount++;
}
$avgResult = $total > 0 ? round($totalResult / $total, 4) : 0;
$viablePct = $total > 0 ? round($positiveCount / $total * 100) : 0;
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:16px">
  <div style="background:var(--panel);border:1px solid var(--border);border-radius:8px;padding:12px 16px">
    <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:4px">Общо показани</div>
    <div style="font-size:22px;font-weight:800;font-family:var(--font-head)"><?= number_format($total) ?></div>
  </div>
  <div style="background:var(--panel);border:1px solid var(--border);border-top:2px solid var(--green);border-radius:8px;padding:12px 16px">
    <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:4px">С положителен резултат</div>
    <div style="font-size:22px;font-weight:800;font-family:var(--font-head);color:var(--green)"><?= number_format($positiveCount) ?></div>
    <div style="font-size:11px;color:var(--muted)"><?= $viablePct ?>% viable</div>
  </div>
  <div style="background:var(--panel);border:1px solid var(--border);border-top:2px solid var(--gold);border-radius:8px;padding:12px 16px">
    <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:4px">Среден Резултат</div>
    <div style="font-size:22px;font-weight:800;font-family:var(--font-head);color:var(--gold)"><?= number_format($avgResult, 4) ?> €</div>
  </div>
  <div style="background:var(--panel);border:1px solid var(--border);border-top:2px solid var(--blue);border-radius:8px;padding:12px 16px">
    <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:4px">Страница</div>
    <div style="font-size:22px;font-weight:800;font-family:var(--font-head)"><?= $page ?> / <?= $totalPages ?></div>
    <div style="font-size:11px;color:var(--muted)"><?= $perPage ?> на страница</div>
  </div>
</div>

<!-- Main Table -->
<div class="card" style="padding:0">
  <div class="table-wrap" style="overflow-x:auto">
    <table id="products-table" style="min-width:1400px">
      <thead>
        <tr>
          <th style="width:30px"><input type="checkbox" id="select-all" onchange="toggleAll(this)"></th>
          <th>EAN</th>
          <th>OUR SKU</th>
          <th>ASIN</th>
          <th>Марка</th>
          <th style="min-width:200px">Модел</th>
          <th>Доставчик</th>
          <th style="text-align:right;color:#f5a623">Цена Дост. €</th>
          <th style="text-align:right;color:#f5a623">ДДС Дост. €</th>
          <th style="text-align:right">Транспорт IN</th>
          <th style="text-align:right">Транспорт OUT</th>
          <th style="text-align:right;color:#4A7CFF">Продажна €</th>
          <th style="text-align:right">Цена Amazon</th>
          <th style="text-align:right">Конкурент</th>
          <th style="text-align:right">OUR No VAT</th>
          <th style="text-align:right">Amazon Fee</th>
          <th style="text-align:right;font-weight:700;color:var(--green)">Result €</th>
          <th style="text-align:right;color:#E8C97A">Нова Цена</th>
          <th style="text-align:right">ES/FR/IT</th>
          <th style="text-align:right">DM Цена</th>
          <th style="text-align:center">Дост.</th>
          <th style="text-align:center">Сл. поръчка</th>
          <th>Статус</th>
          <th style="width:80px"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($paged)): ?>
        <tr><td colspan="24" style="text-align:center;padding:60px;color:var(--muted)">
          <svg width="40" height="40" viewBox="0 0 20 20" fill="none" style="display:block;margin:0 auto 12px;opacity:.3"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.5"/><path d="M13 13l3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          Няма намерени продукти
        </td></tr>
        <?php else: ?>
        <?php foreach ($paged as $p): ?>
        <?php
          $result      = (float)($p['result'] ?? 0);
          $resultClass = $result > 0 ? 'var(--green)' : ($result < 0 ? 'var(--red)' : 'var(--muted)');
          $ean         = htmlspecialchars($p['ean'] ?? '—');
          $asin        = htmlspecialchars($p['asin_de'] ?? '');
          $st          = $p['upload_status'] ?? 'NOT_UPLOADED';
        ?>
        <tr data-ean="<?= $ean ?>" data-sku="<?= htmlspecialchars($p['our_sku'] ?? '') ?>">
          <td><input type="checkbox" class="row-check" value="<?= $ean ?>"></td>
          <td class="text-sm" style="font-family:monospace;color:var(--muted)"><?= $ean ?></td>
          <td class="text-sm" style="color:var(--muted)"><?= htmlspecialchars($p['our_sku'] ?? '—') ?></td>
          <td class="text-sm">
            <?php if ($asin): ?>
            <a href="https://www.amazon.de/dp/<?= $asin ?>" target="_blank"
               style="color:var(--gold);text-decoration:none" title="Отвори в Amazon DE">
              <?= $asin ?>
            </a>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-muted"><?= htmlspecialchars($p['brand'] ?? '—') ?></span></td>
          <td style="max-width:240px">
            <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($p['model'] ?? '') ?>">
              <?= htmlspecialchars($p['model'] ?? '—') ?>
            </span>
          </td>
          <td><span class="badge badge-muted"><?= htmlspecialchars($p['source'] ?? '—') ?></span></td>

          <!-- Supplier price & VAT -->
          <td style="text-align:right;font-weight:600;color:#f5a623">
            <?= $p['supplier_price'] !== null ? number_format((float)$p['supplier_price'], 4) : '—' ?>
          </td>
          <td style="text-align:right;color:var(--muted)">
            <?= $p['vat_supplier'] !== null ? number_format((float)$p['vat_supplier'], 4) : '—' ?>
          </td>
          <!-- Transport -->
          <td style="text-align:right;color:var(--muted)">
            <?= $p['transport_from_supplier'] !== null ? number_format((float)$p['transport_from_supplier'], 2) : '—' ?>
          </td>
          <td style="text-align:right;color:var(--muted)">
            <?= $p['transport_to_client'] !== null ? number_format((float)$p['transport_to_client'], 2) : '—' ?>
          </td>
          <!-- Selling price -->
          <td style="text-align:right;font-weight:700;color:#4A7CFF">
            <?= $p['selling_price'] !== null ? number_format((float)$p['selling_price'], 2) : '—' ?>
          </td>
          <!-- Amazon price -->
          <td style="text-align:right">
            <?= $p['price_amazon'] !== null ? number_format((float)$p['price_amazon'], 2) : '—' ?>
          </td>
          <!-- Competitor price -->
          <td style="text-align:right;color:var(--muted)">
            <?= $p['price_competitor'] !== null ? number_format((float)$p['price_competitor'], 2) : '—' ?>
          </td>
          <!-- Our price no VAT -->
          <td style="text-align:right;color:var(--muted)">
            <?= $p['our_price_no_vat'] !== null ? number_format((float)$p['our_price_no_vat'], 4) : '—' ?>
          </td>
          <!-- Amazon fees -->
          <td style="text-align:right;color:var(--red)">
            <?= $p['amazon_fees'] !== null ? number_format((float)$p['amazon_fees'], 4) : '—' ?>
          </td>
          <!-- Result (key metric) -->
          <td style="text-align:right;font-weight:800;color:<?= $resultClass ?>;font-family:var(--font-head)">
            <?= $result !== 0.0 ? number_format($result, 4) : '—' ?>
          </td>
          <!-- New price (editable) -->
          <td style="text-align:right;color:var(--gold-lt)">
            <span class="editable-cell" data-field="new_price" data-ean="<?= $ean ?>"
                  title="Кликни за редакция"
                  style="cursor:pointer;border-bottom:1px dashed rgba(232,201,122,0.4);padding:2px 4px">
              <?= $p['new_price'] !== null ? number_format((float)$p['new_price'], 2) : '—' ?>
            </span>
          </td>
          <!-- ES/FR/IT price -->
          <td style="text-align:right;color:var(--muted)">
            <?= $p['price_es_fr_it'] !== null ? number_format((float)$p['price_es_fr_it'], 2) : '—' ?>
          </td>
          <!-- DM price -->
          <td style="text-align:right;color:var(--muted)">
            <?= $p['dm_price'] !== null ? number_format((float)$p['dm_price'], 2) : '—' ?>
          </td>
          <!-- Delivered -->
          <td style="text-align:center">
            <?= $p['delivered'] ? '<span class="badge badge-green">'.htmlspecialchars((string)$p['delivered']).'</span>' : '<span style="color:var(--muted)">—</span>' ?>
          </td>
          <!-- Next order -->
          <td style="text-align:center">
            <?= $p['next_order'] ? '<span class="badge badge-gold">'.htmlspecialchars((string)$p['next_order']).'</span>' : '<span style="color:var(--muted)">—</span>' ?>
          </td>
          <!-- Status -->
          <td>
            <span class="badge <?= $st === 'UPLOADED' ? 'badge-green' : 'badge-gold' ?>">
              <?= $st === 'UPLOADED' ? 'Качен' : 'За качване' ?>
            </span>
          </td>
          <!-- Actions -->
          <td>
            <div style="display:flex;gap:4px">
              <?php if ($asin): ?>
              <a href="https://www.amazon.de/dp/<?= $asin ?>" target="_blank"
                 class="btn btn-ghost btn-icon btn-sm" title="Amazon DE">
                <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M13 3h4v4M17 3l-8 8M8 5H5a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </a>
              <?php endif; ?>
              <a href="https://www.amazon.de/s?k=<?= urlencode($p['ean'] ?? '') ?>" target="_blank"
                 class="btn btn-ghost btn-icon btn-sm" title="Търси по EAN">
                <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.8"/><path d="M13 13l3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--border)">
    <span style="font-size:12px;color:var(--muted)">
      Показани <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $total)) ?> от <?= number_format($total) ?>
    </span>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php if ($page > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-ghost btn-sm">← Предишна</a>
      <?php endif; ?>
      <?php
      $start = max(1, $page - 3);
      $end   = min($totalPages, $page + 3);
      for ($i = $start; $i <= $end; $i++):
      ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
         class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?>">
        <?= $i ?>
      </a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-ghost btn-sm">Следваща →</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Import modal -->
<div id="import-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;display:none;align-items:center;justify-content:center">
  <div style="background:var(--panel);border:1px solid var(--border2);border-radius:12px;padding:32px;width:460px;max-width:90vw">
    <div style="font-family:var(--font-head);font-size:16px;font-weight:700;margin-bottom:20px">Импорт на продукти от Excel</div>
    <div style="font-size:13px;color:var(--muted);margin-bottom:16px">
      Качи .xlsx файл с формат идентичен на "FBM Products TN Soft.xlsx".<br>
      Листове: <strong>New FBM</strong>, <strong>Uvex</strong>. Заглавния ред е ред 3.
    </div>
    <div class="form-group">
      <label class="form-label">Excel файл (.xlsx)</label>
      <input type="file" id="excel-file" class="form-control" accept=".xlsx">
    </div>
    <div id="import-status" style="font-size:12px;margin-bottom:12px;display:none"></div>
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button onclick="closeImport()" class="btn btn-ghost">Отказ</button>
      <button onclick="doImport()" class="btn btn-primary">Импортирай</button>
    </div>
  </div>
</div>

<script>
// Inline edit
document.querySelectorAll('.editable-cell').forEach(cell => {
  cell.addEventListener('click', function() {
    const ean   = this.dataset.ean;
    const field = this.dataset.field;
    const cur   = this.textContent.trim().replace('—','');
    const input = document.createElement('input');
    input.type  = 'number';
    input.step  = '0.01';
    input.value = cur;
    input.style.cssText = 'width:70px;background:var(--bg3);border:1px solid var(--gold);color:var(--text);padding:2px 4px;border-radius:3px;font-size:12px;text-align:right';
    this.innerHTML = '';
    this.appendChild(input);
    input.focus();
    input.select();
    const save = () => {
      const val = input.value;
      fetch('/products/update', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`id=${encodeURIComponent(ean)}&field=${field}&value=${encodeURIComponent(val)}`
      }).then(r=>r.json()).then(d=>{
        cell.textContent = val ? parseFloat(val).toFixed(2) : '—';
      });
    };
    input.addEventListener('blur', save);
    input.addEventListener('keydown', e => { if (e.key==='Enter') save(); if(e.key==='Escape') { cell.textContent = cur||'—'; } });
  });
});

// Select all
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
}

// Export CSV
function exportCsv() {
  const rows = [...document.querySelectorAll('#products-table tbody tr')];
  const headers = [...document.querySelectorAll('#products-table thead th')].slice(1,-1).map(th=>th.textContent.trim());
  let csv = headers.join(';') + '\n';
  rows.forEach(row => {
    const cells = [...row.querySelectorAll('td')].slice(1,-1);
    const vals  = cells.map(td => '"' + td.textContent.trim().replace(/"/g,'""') + '"');
    csv += vals.join(';') + '\n';
  });
  const a  = document.createElement('a');
  a.href   = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
  a.download = 'amz-products-export.csv';
  a.click();
}

// Import modal
function importExcel() {
  const m = document.getElementById('import-modal');
  m.style.display = 'flex';
}
function closeImport() {
  document.getElementById('import-modal').style.display = 'none';
}
function doImport() {
  const file = document.getElementById('excel-file').files[0];
  if (!file) { alert('Избери файл!'); return; }
  const st = document.getElementById('import-status');
  st.style.display = 'block';
  st.style.color   = 'var(--gold)';
  st.textContent   = 'Качване...';
  const fd = new FormData();
  fd.append('file', file);
  fetch('/api/import-excel', { method:'POST', body: fd })
    .then(r=>r.json())
    .then(d=>{
      if (d.success) {
        st.style.color = 'var(--green)';
        st.textContent = '✓ Импортирани ' + d.count + ' продукта. Страницата ще се презареди...';
        setTimeout(()=>location.reload(), 1500);
      } else {
        st.style.color = 'var(--red)';
        st.textContent = 'Грешка: ' + (d.error || 'Неизвестна');
      }
    }).catch(err => {
      st.style.color = 'var(--red)';
      st.textContent = 'Грешка при качване.';
    });
}
</script>
