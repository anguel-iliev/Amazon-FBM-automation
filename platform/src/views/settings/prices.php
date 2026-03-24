<?php
$activeTab = 'prices';
include __DIR__ . '/_tabs.php';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$search  = $_GET['search'] ?? '';

$filters = [];
if ($search) $filters['search'] = $search;

$all      = DataStore::getProducts($filters);
$total    = count($all);
$products = array_slice($all, ($page - 1) * $perPage, $perPage);
$pages    = max(1, (int)ceil($total / $perPage));

// All available columns for the add-column dropdown
$allCols = DataStore::getColumns();

// User-configured visible columns for Prices view
// Stored in settings.json under 'prices_columns'
$settings   = DataStore::getSettings();
$visibleCols = $settings['prices_columns'] ?? [
    'EAN Amazon',
    'ASIN',
    'Доставчик',
    'Бранд',
    'Модел',
    'Цена Amazon  - Brutto',
    'Цена Конкурент  - Brutto',
    'Продажна Цена в Амазон  - Brutto',
    'Цена без ДДС',
    'ДДС от продажна цена',
    'Amazon Такси',
    'Цена Доставчик -Netto',
    'Резултат',
    'Нова цена след намаление',
];

// Editable columns (user can type in them)
$editableCols = [
    'Продажна Цена в Амазон  - Brutto',
    'Нова цена след намаление',
    'Цена Доставчик -Netto',
    'Транспорт до кр. лиент  Netto',
    'За следваща поръчка',
    'Коментар',
];
?>

<!-- ── Column manager ── -->
<div class="card" style="margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <span class="card-title" style="margin:0">Видими колони</span>
    <span style="font-size:11px;color:rgba(255,255,255,0.4)">Влачи за наредба · ✕ за изтриване · + за добавяне</span>
  </div>
  <div id="col-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
    <?php foreach ($visibleCols as $col): ?>
    <div class="col-chip" data-col="<?= htmlspecialchars($col) ?>">
      <span><?= htmlspecialchars($col) ?></span>
      <button type="button" onclick="removeColChip(this)" title="Изтрий колона">×</button>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:8px;align-items:flex-end">
    <div style="flex:1">
      <select id="add-col-select" class="form-control form-control-sm">
        <option value="">— Избери колона за добавяне —</option>
        <?php foreach ($allCols as $col): ?>
        <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="button" class="btn btn-ghost btn-sm" onclick="addColChip()">
      <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Добави
    </button>
    <button type="button" class="btn btn-primary btn-sm" onclick="saveColumns()">Запази колоните</button>
    <span id="col-save-status" style="font-size:12px;display:none"></span>
  </div>
</div>

<!-- ── Search ── -->
<form method="get" action="/settings/prices" style="display:flex;gap:8px;margin-bottom:14px">
  <input type="text" name="search" class="form-control form-control-sm"
         placeholder="Търси по Модел, Бранд, EAN, ASIN…"
         value="<?= htmlspecialchars($search) ?>"
         style="max-width:320px">
  <button type="submit" class="btn btn-primary btn-sm">Търси</button>
  <?php if ($search): ?>
  <a href="/settings/prices" class="btn btn-ghost btn-sm">Изчисти</a>
  <?php endif; ?>
  <span style="font-size:12px;color:rgba(255,255,255,0.4);align-self:center;margin-left:4px">
    <?= number_format($total) ?> продукта
  </span>
</form>

<!-- ── Prices table ── -->
<div class="table-scroll-container" id="prices-scroll">
  <table class="table-sticky" id="prices-table" style="min-width:900px">
    <thead>
      <tr id="prices-thead">
        <?php foreach ($visibleCols as $col): ?>
        <th><?= htmlspecialchars($col) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody id="prices-tbody">
    <?php if (empty($products)): ?>
    <tr><td colspan="<?= count($visibleCols) ?>" style="text-align:center;padding:32px;color:rgba(255,255,255,0.3)">Няма намерени продукти</td></tr>
    <?php endif; ?>
    <?php foreach ($products as $p): ?>
    <?php $ean = htmlspecialchars($p['EAN Amazon'] ?? ''); ?>
    <tr>
      <?php foreach ($visibleCols as $col): ?>
      <?php
        $val = $p[$col] ?? '';
        $safe = htmlspecialchars((string)$val);
        $isEdit = in_array($col, $editableCols);
        $isNum  = is_numeric($val) && $val !== '';
        $colStyle = $isNum ? 'text-align:right;font-variant-numeric:tabular-nums' : '';
      ?>
      <td style="<?= $colStyle ?>"
          <?php if ($isEdit): ?>
          class="td-editable"
          data-ean="<?= $ean ?>"
          data-field="<?= htmlspecialchars($col) ?>"
          onclick="editCell(this)"
          <?php endif; ?>>
        <?php if ($col === 'Amazon Link' && $val): ?>
          <a href="<?= $safe ?>" target="_blank" class="td-link">↗</a>
        <?php elseif ($col === 'ASIN' && !empty($p['Amazon Link'])): ?>
          <a href="<?= htmlspecialchars($p['Amazon Link']) ?>" target="_blank" class="td-link"><?= $safe ?></a>
        <?php elseif ($isNum): ?>
          <?= number_format((float)$val, (floor((float)$val) == (float)$val ? 0 : 2)) ?>
        <?php else: ?>
          <?= $safe ?>
        <?php endif; ?>
      </td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ── Pagination ── -->
<?php if ($pages > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:16px;flex-wrap:wrap">
  <?php if ($page > 1): ?>
  <a href="/settings/prices?page=<?= $page-1 ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="btn btn-ghost btn-sm">‹</a>
  <?php endif; ?>
  <?php for ($i = max(1,$page-3); $i <= min($pages,$page+3); $i++): ?>
  <a href="/settings/prices?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>"
     class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?>" style="min-width:32px;justify-content:center">
    <?= $i ?>
  </a>
  <?php endfor; ?>
  <?php if ($page < $pages): ?>
  <a href="/settings/prices?page=<?= $page+1 ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="btn btn-ghost btn-sm">›</a>
  <?php endif; ?>
  <span style="font-size:11px;color:rgba(255,255,255,0.3);margin-left:6px">Стр. <?= $page ?>/<?= $pages ?></span>
</div>
<?php endif; ?>

<script>
// ── Inline edit ───────────────────────────────────────────────────────────
function editCell(td) {
  if (td.querySelector('input')) return;
  const orig  = td.textContent.trim();
  const ean   = td.dataset.ean;
  const field = td.dataset.field;
  const inp = document.createElement('input');
  inp.type  = 'text'; inp.value = orig;
  inp.style.cssText = 'width:90%;background:rgba(255,255,255,0.06);border:1px solid var(--gold);border-radius:3px;padding:2px 6px;color:#fff;font-size:12px;font-family:inherit;outline:none';
  td.textContent = ''; td.appendChild(inp); inp.focus(); inp.select();
  function save() {
    const val = inp.value.trim(); td.textContent = val;
    if (val !== orig) {
      const fd = new FormData(); fd.append('ean',ean); fd.append('field',field); fd.append('value',val);
      fetch('/products/update',{method:'POST',body:fd}).catch(()=>{});
    }
  }
  inp.addEventListener('blur',save);
  inp.addEventListener('keydown',e => { if(e.key==='Enter')inp.blur(); if(e.key==='Escape')td.textContent=orig; });
}

// ── Column chip management ────────────────────────────────────────────────
function removeColChip(btn) {
  const chip = btn.closest('.col-chip');
  const col  = chip.dataset.col;
  chip.remove();
  // Add back to dropdown
  const sel = document.getElementById('add-col-select');
  const opt = document.createElement('option');
  opt.value = col; opt.textContent = col;
  sel.appendChild(opt);
}

function addColChip() {
  const sel = document.getElementById('add-col-select');
  const col = sel.value;
  if (!col) { alert('Избери колона'); return; }
  // Check not already added
  if (document.querySelector(`.col-chip[data-col="${CSS.escape(col)}"]`)) {
    alert('Колоната вече е добавена'); return;
  }
  const chip = document.createElement('div');
  chip.className = 'col-chip'; chip.dataset.col = col;
  chip.innerHTML = `<span>${col}</span><button type="button" onclick="removeColChip(this)" title="Изтрий">×</button>`;
  document.getElementById('col-chips').appendChild(chip);
  sel.querySelector(`option[value="${CSS.escape(col)}"]`)?.remove();
  sel.value = '';
  // Also add to table header & rows
  rebuildTableColumns();
}

function saveColumns() {
  const chips = document.querySelectorAll('#col-chips .col-chip');
  const cols  = Array.from(chips).map(c => c.dataset.col);
  const st    = document.getElementById('col-save-status');
  st.textContent = 'Запазване…'; st.style.color='rgba(255,255,255,0.5)'; st.style.display='inline';
  fetch('/api/save-price-columns', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ columns: cols })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) { st.textContent='✓ Запазени'; st.style.color='var(--green)'; location.reload(); }
    else           { st.textContent='✗ '+(d.error||'Грешка'); st.style.color='var(--red)'; }
    setTimeout(()=>st.style.display='none', 3000);
  }).catch(()=>{st.textContent='✗ Грешка';st.style.color='var(--red)';});
}

function rebuildTableColumns() {
  const chips = document.querySelectorAll('#col-chips .col-chip');
  const cols  = Array.from(chips).map(c => c.dataset.col);
  const thead = document.getElementById('prices-thead');
  thead.innerHTML = cols.map(c => `<th>${c}</th>`).join('');
  // Refresh data (reload for simplicity after add)
}
</script>

<style>
.col-chip {
  display:inline-flex; align-items:center; gap:5px;
  background:rgba(255,255,255,0.06);
  border:1px solid rgba(255,255,255,0.12);
  border-radius:20px;
  padding:3px 10px 3px 10px;
  font-size:12px; color:rgba(255,255,255,0.8);
  cursor:default;
  user-select:none;
}
.col-chip button {
  background:none; border:none; color:rgba(255,255,255,0.4);
  cursor:pointer; font-size:14px; line-height:1; padding:0; margin:0;
  transition:color .12s;
}
.col-chip button:hover { color:var(--red); }
</style>
