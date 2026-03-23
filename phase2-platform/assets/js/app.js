// AMZ Retail — Main JS

// Clock
function updateClock() {
  const el = document.getElementById('clock');
  if (!el) return;
  const now = new Date();
  el.textContent = now.toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
setInterval(updateClock, 1000);
updateClock();

// Auto-hide flash messages
document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => el.style.opacity = '0', 3500);
  setTimeout(() => el.remove(), 4000);
  el.style.transition = 'opacity 0.5s';
});

// Confirm dialogs
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});

// AJAX helper
async function api(url, data = null) {
  const opts = { headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
  if (data) { opts.method = 'POST'; opts.body = JSON.stringify(data); }
  const res = await fetch(url, opts);
  return res.json();
}

// Trigger sync via AJAX
function triggerSync() {
  const btn = document.getElementById('sync-btn');
  const status = document.getElementById('sync-status');
  if (!btn) return;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Синхронизиране...';
  if (status) status.textContent = 'Стартиране...';

  api('/api/sync', { action: 'start' })
    .then(data => {
      if (data.success) {
        if (status) status.textContent = 'Завършено успешно';
        btn.innerHTML = '✓ Готово';
        setTimeout(() => location.reload(), 1500);
      } else {
        if (status) status.textContent = 'Грешка: ' + (data.error || 'неизвестна');
        btn.disabled = false;
        btn.innerHTML = 'Стартирай синхронизация';
      }
    })
    .catch(() => {
      if (status) status.textContent = 'Мрежова грешка';
      btn.disabled = false;
      btn.innerHTML = 'Стартирай синхронизация';
    });
}
/* ══════════════════════════════════════════════════════════════════
   Products Grid v2.0 — AJAX loader
   ══════════════════════════════════════════════════════════════════ */

// ── State ────────────────────────────────────────────────────────
const GRID = {
  page:     1,
  perPage:  50,
  total:    0,
  pages:    1,
  sort:     '',
  dir:      'asc',
  filters:  <?= json_encode($filters, JSON_UNESCAPED_UNICODE) ?>,
  loading:  false,
};

// ── Column definitions (mirrors PHP $COLS) ────────────────────────
const COLS = [
  ['EAN Amazon',                         'EAN Amazon',           'mono',   130, false, true],
  ['EAN Доставчик',                      'EAN Дост.',            'mono',   120, false, true],
  ['Корекция  на цена',                  'Корекция',             'num',    90,  true,  true],
  ['Коментар',                           'Коментар',             'text',   180, true,  false],
  ['Наше SKU',                           'Наше SKU',             'mono',   130, false, true],
  ['Доставчик SKU',                      'Дост. SKU',            'mono',   110, false, true],
  ['Доставчик',                          'Доставчик',            'text',   100, false, true],
  ['Бранд',                              'Бранд',                'text',   90,  false, true],
  ['Модел',                              'Модел',                'text',   240, false, true],
  ['Amazon Link',                        'Link',                 'link',   50,  false, false],
  ['ASIN',                               'ASIN',                 'mono',   110, false, true],
  ['Цена Конкурент  - Brutto',           'Конкурент €',          'num',    95,  false, true],
  ['Цена Amazon  - Brutto',              'Amazon €',             'num',    95,  false, true],
  ['Продажна Цена в Амазон  - Brutto',   'Продажна €',           'num',    95,  true,  true],
  ['Цена без ДДС',                       'Без ДДС',              'num',    88,  false, true],
  ['ДДС от продажна цена',               'ДДС прод.',            'num',    88,  false, true],
  ['Amazon Такси',                       'Амз. такса',           'num',    88,  false, true],
  ['Цена Доставчик -Netto',              'Дост. Netto',          'num',    88,  true,  true],
  ['ДДС  от Цена Доставчик',            'ДДС дост.',            'num',    80,  false, true],
  ['Транспорт от Доставчик до нас',      'Транспорт ДН',         'num',    88,  false, true],
  ['Транспорт до кр. лиент  Netto',      'Транспорт КЛ',         'num',    88,  true,  true],
  ['ДДС  от Транспорт до кр. лиент',    'ДДС транс.',           'num',    80,  false, true],
  ['Резултат',                           'Резултат',             'result', 88,  false, true],
  ['Намерена 2ра обява',                 '2ра обява',            'text',   100, true,  false],
  ['Цена за Испания / Франция / Италия', 'ES/FR/IT',             'num',    88,  false, true],
  ['DM цена',                            'DM цена',              'num',    80,  true,  true],
  ['Нова цена след намаление',           'Нова цена',            'num',    88,  true,  true],
  ['Доставени',                          'Доставени',            'num',    75,  false, true],
  ['За следваща поръчка',                'За поръчка',           'num',    88,  true,  true],
  ['Електоника',                         'Електроника',          'toggle', 90,  true,  true],
];

// ── Build table header ────────────────────────────────────────────
function buildHeader() {
  const tr = document.getElementById('pg-header-row');
  tr.innerHTML = '';
  COLS.forEach(([key, label, type, width, editable, sortable]) => {
    const th = document.createElement('th');
    th.style.width  = width + 'px';
    th.dataset.key  = key;
    if (sortable) th.classList.add('th-sortable');
    if (GRID.sort === key) th.classList.add('th-sorted');

    const inner = document.createElement(sortable ? 'a' : 'div');
    inner.className = 'th-inner';
    inner.title = key;
    if (sortable) {
      inner.href = '#';
      inner.addEventListener('click', e => { e.preventDefault(); sortBy(key); });
    }
    inner.appendChild(document.createTextNode(label));
    if (sortable) {
      const icon = document.createElement('span');
      icon.className = 'th-sort-icon';
      icon.textContent = (GRID.sort === key) ? (GRID.dir === 'asc' ? '▲' : '▼') : '⇅';
      inner.appendChild(icon);
    }

    // Resize handle
    const rh = document.createElement('div');
    rh.className = 'th-resize';
    rh.addEventListener('mousedown', e => {
      e.preventDefault();
      const startX = e.pageX, startW = th.offsetWidth;
      const onMove = e2 => { th.style.width = Math.max(50, startW + e2.pageX - startX) + 'px'; };
      const onUp   = () => { document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp); };
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });

    th.appendChild(inner);
    th.appendChild(rh);
    tr.appendChild(th);
  });
  // Status column
  const thS = document.createElement('th');
  thS.style.width = '80px';
  const divS = document.createElement('div');
  divS.className = 'th-inner';
  divS.textContent = 'Статус';
  thS.appendChild(divS);
  tr.appendChild(thS);
}

// ── Render one product row ────────────────────────────────────────
function renderRow(p) {
  const tr = document.createElement('tr');
  const ean  = p['EAN Amazon'] || '';
  const link = p['Amazon Link'] || '';
  const asin = p['ASIN'] || '';
  const status = p['_upload_status'] || 'NOT_UPLOADED';
  const elek = p['Електоника'] || '';
  const res  = parseFloat(p['Резултат'] || 0);
  tr.dataset.ean = ean;

  COLS.forEach(([key, label, type, width, editable, sortable]) => {
    const td  = document.createElement('td');
    const raw = p[key];
    const val = (raw !== null && raw !== undefined) ? String(raw) : '';
    const valE = escHtml(val);

    if (type === 'link') {
      td.className = 'c-link';
      if (link) {
        td.innerHTML = `<a href="${escHtml(link)}" target="_blank" class="amz-link" title="${escHtml(asin||'Amazon')}"><svg width="10" height="10" viewBox="0 0 20 20" fill="none"><path d="M11 3h6v6M9 11L17 3M7 5H4a1 1 0 00-1 1v10a1 1 0 001 1h10a1 1 0 001-1v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>`;
      }
    } else if (key === 'ASIN') {
      td.className = 'c-mono';
      td.innerHTML = (link && asin) ? `<a href="${escHtml(link)}" target="_blank" style="color:var(--gold-lt);text-decoration:none;font-size:11px">${valE}</a>` : valE;
    } else if (type === 'toggle') {
      td.className = 'c-toggle';
      td.innerHTML = `<span class="elek-btn ${elek==='Yes'?'is-yes':'is-no'}" data-ean="${escHtml(ean)}" data-val="${escHtml(elek)}" onclick="toggleElek(this)">${escHtml(elek||'—')}</span>`;
    } else if (type === 'result') {
      const cls = res > 0 ? 'pos' : (res < 0 ? 'neg' : 'zer');
      td.className = 'c-result ' + cls;
      td.textContent = res !== 0 ? res.toLocaleString('de-DE', {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
    } else if (type === 'num') {
      td.className = 'c-num' + (editable ? ' editable' : '');
      const num = parseFloat(val.replace(',', '.'));
      td.textContent = (!isNaN(num) && val !== '') ? num.toLocaleString('de-DE', {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
      if (editable) { td.dataset.ean = ean; td.dataset.field = key; td.addEventListener('click', () => editCell(td)); td.title = 'Кликни за редакция'; }
    } else if (type === 'mono') {
      td.className = 'c-mono';
      td.textContent = val;
    } else {
      td.className = 'c-text' + (editable ? ' editable' : '');
      td.textContent = val;
      td.title = val;
      if (editable) { td.dataset.ean = ean; td.dataset.field = key; td.addEventListener('click', () => editCell(td)); td.title = 'Кликни за редакция'; }
    }
    tr.appendChild(td);
  });

  // Status cell
  const tdS = document.createElement('td');
  tdS.className = 'c-toggle';
  tdS.innerHTML = `<span class="${status==='UPLOADED'?'b-green':'b-gold'}">${status==='UPLOADED'?'Качен':'Не качен'}</span>`;
  tr.appendChild(tdS);
  return tr;
}

// ── Load products via AJAX ────────────────────────────────────────
function loadProducts() {
  if (GRID.loading) return;
  GRID.loading = true;

  const tbody = document.getElementById('pg-tbody');
  tbody.innerHTML = `<tr><td colspan="${COLS.length+1}" style="padding:0;border:none"><div class="grid-loading"><div class="spin"></div>Зареждане…</div></td></tr>`;
  document.getElementById('prod-count').innerHTML = '<span style="color:rgba(255,255,255,.25)">Зареждане…</span>';

  const params = new URLSearchParams({
    page:    GRID.page,
    perpage: GRID.perPage,
    sort:    GRID.sort,
    dir:     GRID.dir,
    ...GRID.filters,
  });

  fetch('/api/products-grid?' + params.toString())
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(data => {
      GRID.total = data.total;
      GRID.pages = data.pages;
      GRID.loading = false;
      renderGrid(data.products, data.total, data.pages, data.from, data.to);
    })
    .catch(err => {
      GRID.loading = false;
      tbody.innerHTML = `<tr><td colspan="${COLS.length+1}" style="padding:0;border:none"><div class="products-empty"><svg width="48" height="48" viewBox="0 0 20 20" fill="none"><path d="M2 5h16M2 10h16M2 15h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><h3>Грешка при зареждане</h3><p>${escHtml(err.message)}</p></div></td></tr>`;
      document.getElementById('prod-count').textContent = '';
    });
}

function renderGrid(products, total, pages, from, to) {
  const tbody = document.getElementById('pg-tbody');
  tbody.innerHTML = '';

  if (!products || products.length === 0) {
    tbody.innerHTML = `<tr><td colspan="${COLS.length+1}" style="padding:0;border:none"><div class="products-empty"><svg width="48" height="48" viewBox="0 0 20 20" fill="none"><path d="M2 5h16M2 10h16M2 15h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><h3>Няма намерени продукти</h3><p>Опитай да промениш филтрите или качи Excel файл</p></div></td></tr>`;
    document.getElementById('prod-count').innerHTML = '<span style="color:rgba(255,255,255,.25)">Няма намерени продукти</span>';
    updatePager(0, 0, 0, 0);
    return;
  }

  // Fragment for performance
  const frag = document.createDocumentFragment();
  products.forEach(p => frag.appendChild(renderRow(p)));
  tbody.appendChild(frag);

  // Info bar
  document.getElementById('prod-count').innerHTML =
    `Показани <strong>${from}–${to}</strong> от <strong>${total.toLocaleString()}</strong> продукта`;

  updatePager(total, pages, from, to);
  updatePerPageSel();
}

// ── Pager ─────────────────────────────────────────────────────────
function updatePager(total, pages, from, to) {
  document.getElementById('pg-info').textContent =
    total ? `Стр. ${GRID.page}/${pages} · ${total.toLocaleString()} записа` : '';

  const wrap = document.getElementById('pg-pages');
  wrap.innerHTML = '';
  if (!total) return;

  const addBtn = (text, page, active, disabled) => {
    const el = document.createElement(disabled ? 'span' : 'a');
    el.className = 'pg-btn' + (active ? ' active' : '');
    el.textContent = text;
    if (disabled) { el.setAttribute('disabled', ''); }
    else { el.href = '#'; el.addEventListener('click', e => { e.preventDefault(); gotoPage(page); }); }
    wrap.appendChild(el);
  };

  addBtn('«', 1, false, GRID.page <= 1);
  addBtn('‹', GRID.page - 1, false, GRID.page <= 1);

  let start = Math.max(1, Math.min(GRID.page - 3, pages - 6));
  let end   = Math.min(pages, Math.max(GRID.page + 3, 7));
  if (start > 1) { const sp = document.createElement('span'); sp.textContent = '…'; sp.style.cssText = 'color:rgba(255,255,255,.3);padding:0 2px;line-height:26px'; wrap.appendChild(sp); }
  for (let i = start; i <= end; i++) addBtn(i, i, i === GRID.page, false);
  if (end < pages) { const sp = document.createElement('span'); sp.textContent = '…'; sp.style.cssText = 'color:rgba(255,255,255,.3);padding:0 2px;line-height:26px'; wrap.appendChild(sp); }

  addBtn('›', GRID.page + 1, false, GRID.page >= pages);
  addBtn('»', pages, false, GRID.page >= pages);
}

function updatePerPageSel() {
  const sel = document.getElementById('pg-perpage-sel');
  if (sel) sel.value = GRID.perPage;
}

function gotoPage(p) {
  GRID.page = p;
  loadProducts();
}

function changePerPage(val) {
  GRID.perPage = parseInt(val, 10);
  GRID.page    = 1;
  loadProducts();
}

// ── Filters ───────────────────────────────────────────────────────
function applyFilters() {
  const f = {};
  const d = document.getElementById('f-dostavchik').value;
  const b = document.getElementById('f-brand').value;
  const s = document.getElementById('f-status').value;
  const e = document.getElementById('f-elek').value;
  const q = document.getElementById('f-search').value.trim();
  if (d) f.dostavchik    = d;
  if (b) f.brand         = b;
  if (s) f.upload_status = s;
  if (e) f.elektronika   = e;
  if (q) f.search        = q;
  GRID.filters = f;
  GRID.page    = 1;
  loadProducts();
}

function clearFilters() {
  document.getElementById('f-dostavchik').value = '';
  document.getElementById('f-brand').value      = '';
  document.getElementById('f-status').value     = '';
  document.getElementById('f-elek').value       = '';
  document.getElementById('f-search').value     = '';
  GRID.filters = {};
  GRID.page    = 1;
  loadProducts();
}

// ── Sort ──────────────────────────────────────────────────────────
function sortBy(col) {
  if (GRID.sort === col) {
    GRID.dir = GRID.dir === 'asc' ? 'desc' : 'asc';
  } else {
    GRID.sort = col;
    GRID.dir  = 'asc';
  }
  GRID.page = 1;
  buildHeader(); // re-render header with new sort indicator
  loadProducts();
}

// ── Inline edit ───────────────────────────────────────────────────
function editCell(td) {
  if (td.querySelector('input')) return;
  const ean      = td.dataset.ean;
  const field    = td.dataset.field;
  const origText = td.textContent.trim();
  const origVal  = origText.replace(/\s/g,'').replace(',','.');
  td.innerHTML   = '';
  const inp = document.createElement('input');
  inp.type = 'text'; inp.value = origVal; inp.className = 'cell-input';
  td.appendChild(inp); inp.focus(); inp.select();
  let saved = false;

  function commit() {
    if (saved) return; saved = true;
    const newVal = inp.value.trim();
    const num    = parseFloat(newVal.replace(',','.'));
    td.textContent = (!isNaN(num) && newVal !== '')
      ? num.toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})
      : newVal;
    if (newVal !== origVal && ean && field) saveCellValue(ean, field, newVal.replace(',','.'));
  }
  inp.addEventListener('blur', commit);
  inp.addEventListener('keydown', e => {
    if (e.key === 'Enter')  inp.blur();
    if (e.key === 'Escape') { saved=true; inp.removeEventListener('blur',commit); td.textContent=origText; }
    if (e.key === 'Tab') {
      e.preventDefault(); inp.blur();
      const cells = [...td.closest('tr').querySelectorAll('td.editable')];
      const idx   = cells.indexOf(td);
      if (cells[idx+1]) cells[idx+1].click();
    }
  });
}

function saveCellValue(ean, field, value) {
  const fd = new FormData();
  fd.append('ean', ean); fd.append('field', field); fd.append('value', value);
  fetch('/products/update', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(d=>{ showToast(d.success ? '✓ Запазено' : ('✗ '+(d.error||'Грешка')), !d.success); })
    .catch(()=>showToast('✗ Мрежова грешка', true));
}

// ── Electronics toggle ────────────────────────────────────────────
function toggleElek(el) {
  const ean  = el.dataset.ean;
  const cur  = el.dataset.val;
  const next = cur === 'Yes' ? 'No' : 'Yes';
  el.textContent = next; el.dataset.val = next;
  el.className = 'elek-btn ' + (next === 'Yes' ? 'is-yes' : 'is-no');
  saveCellValue(ean, 'Електоника', next);
}

// ── CSV Export ────────────────────────────────────────────────────
function exportCsv() {
  const fd = new FormData();
  Object.entries(GRID.filters).forEach(([k,v]) => fd.append(k,v));
  // Use hidden form for POST download
  const form = document.createElement('form');
  form.method = 'post'; form.action = '/api/export-csv'; form.target = '_blank';
  Object.entries(GRID.filters).forEach(([k,v]) => {
    const inp = document.createElement('input');
    inp.type='hidden'; inp.name=k; inp.value=v; form.appendChild(inp);
  });
  document.body.appendChild(form); form.submit(); form.remove();
}

// ── Import Excel ──────────────────────────────────────────────────
function importExcel(input) {
  const file = input.files[0]; if (!file) return;
  const label = input.closest('label');
  const origHTML = label.innerHTML;
  label.innerHTML = '<span style="opacity:.7">Зареждане…</span>';
  label.style.pointerEvents = 'none';
  const fd = new FormData(); fd.append('file', file);
  fetch('/api/import-excel', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(d=>{
      if (d.success) { showToast('✓ Импортирани '+d.count+' продукта'); setTimeout(()=>{ GRID.page=1; loadProducts(); }, 1200); }
      else { showToast('✗ '+(d.error||'Грешка'), true); label.innerHTML=origHTML; label.style.pointerEvents=''; }
    })
    .catch(()=>{ showToast('✗ Мрежова грешка',true); label.innerHTML=origHTML; label.style.pointerEvents=''; })
    .finally(()=>{ input.value=''; });
}

// ── Toast ──────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, isError=false) {
  const t = document.getElementById('save-toast');
  t.textContent = msg;
  t.style.background = isError ? 'var(--red)' : 'var(--green)';
  t.style.display = 'block';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=>{ t.style.display='none'; }, 2000);
}

// ── Keyboard shortcut Ctrl+F → focus search ──────────────────────
document.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
    const inp = document.getElementById('f-search');
    if (inp) { e.preventDefault(); inp.focus(); inp.select(); }
  }
  if (e.key === 'Enter' && document.activeElement === document.getElementById('f-search')) {
    applyFilters();
  }
});

// ── Utility ───────────────────────────────────────────────────────
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ──────────────────────────────────────────────────────────
buildHeader();
loadProducts();