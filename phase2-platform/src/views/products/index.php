<?php
/**
 * Products — Database Grid View  v1.9.0
 * - No carriers panel (moved to Settings)
 * - Dashboard-style card layout
 * - Frozen sticky header
 * - Dark dropdown menus
 * - Always-visible horizontal scrollbar
 * - Full-width grid
 */
$page      = $page      ?? 1;
$pages     = $pages     ?? 1;
$total     = $total     ?? 0;
$perPage   = $perPage   ?? 50;
$filters   = $filters   ?? [];
$products  = $products  ?? [];
$suppliers = $suppliers ?? [];
$brands    = $brands    ?? [];

$from = ($page - 1) * $perPage + 1;
$to   = min($page * $perPage, $total);

// Column definitions: key => [label, type, width, editable, sortable]
$COLS = [
  'EAN Amazon'                           => ['EAN Amazon',           'mono',   130, false, true],
  'EAN Доставчик'                        => ['EAN Доставчик',        'mono',   130, false, true],
  'Корекция  на цена'                    => ['Корекция',             'num',    90,  true,  true],
  'Коментар'                             => ['Коментар',             'text',   180, true,  false],
  'Наше SKU'                             => ['Наше SKU',             'mono',   140, false, true],
  'Доставчик SKU'                        => ['Доставчик SKU',        'mono',   120, false, true],
  'Доставчик'                            => ['Доставчик',            'text',   100, false, true],
  'Бранд'                                => ['Бранд',                'text',   90,  false, true],
  'Модел'                                => ['Модел',                'text',   240, false, true],
  'Amazon Link'                          => ['Link',                 'link',   60,  false, false],
  'ASIN'                                 => ['ASIN',                 'mono',   110, false, true],
  'Цена Конкурент  - Brutto'             => ['Конкурент €',          'num',    95,  false, true],
  'Цена Amazon  - Brutto'                => ['Amazon €',             'num',    95,  false, true],
  'Продажна Цена в Амазон  - Brutto'     => ['Продажна €',           'num',    95,  true,  true],
  'Цена без ДДС'                         => ['Без ДДС',              'num',    88,  false, true],
  'ДДС от продажна цена'                 => ['ДДС прод.',            'num',    88,  false, true],
  'Amazon Такси'                         => ['Амз. такса',           'num',    88,  false, true],
  'Цена Доставчик -Netto'                => ['Дост. Netto',          'num',    88,  true,  true],
  'ДДС  от Цена Доставчик'              => ['ДДС дост.',             'num',    80,  false, true],
  'Транспорт от Доставчик до нас'        => ['Транспорт ДН',         'num',    88,  false, true],
  'Транспорт до кр. лиент  Netto'        => ['Транспорт КЛ',         'num',    88,  true,  true],
  'ДДС  от Транспорт до кр. лиент'      => ['ДДС транс.',            'num',    80,  false, true],
  'Резултат'                             => ['Резултат',             'result', 88,  false, true],
  'Намерена 2ра обява'                   => ['2ра обява',            'text',   100, true,  false],
  'Цена за Испания / Франция / Италия'   => ['ES/FR/IT',             'num',    88,  false, true],
  'DM цена'                              => ['DM цена',              'num',    80,  true,  true],
  'Нова цена след намаление'             => ['Нова цена',            'num',    88,  true,  true],
  'Доставени'                            => ['Доставени',            'num',    75,  false, true],
  'За следваща поръчка'                  => ['За поръчка',           'num',    88,  true,  true],
  'Електоника'                           => ['Електроника',          'toggle', 90,  true,  true],
];

// Build query helper
function pq($extra = []) {
    global $filters, $page;
    return ($p = array_merge($filters, $extra)) ? '?' . http_build_query($p) : '';
}

$sortCol = $_GET['sort']  ?? '';
$sortDir = ($_GET['dir']  ?? 'asc') === 'desc' ? 'desc' : 'asc';
?>

<style>
/* ══ Products v1.9 — Dashboard-style, No Carriers Panel ═══════════ */

/* ── Layout: full-width column ── */
.prod-layout {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 62px);
  overflow: hidden;
  padding: 0;
}

/* ── Stats bar ── */
.prod-stats-bar {
  display: flex;
  gap: 10px;
  padding: 10px 16px 0;
  flex-shrink: 0;
}
.prod-stat-card {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
  position: relative;
  overflow: hidden;
}
.prod-stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: var(--gold);
}
.prod-stat-card.accent-green::before { background: var(--green); }
.prod-stat-card.accent-blue::before  { background: var(--blue); }
.prod-stat-card.accent-amber::before { background: var(--amber); }
.psc-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  background: rgba(201,168,76,.12);
  border: 1px solid rgba(201,168,76,.2);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  color: var(--gold);
}
.prod-stat-card.accent-green .psc-icon { background: rgba(61,187,127,.1); border-color: rgba(61,187,127,.2); color: var(--green); }
.prod-stat-card.accent-blue  .psc-icon { background: rgba(74,124,255,.1); border-color: rgba(74,124,255,.2); color: var(--blue); }
.prod-stat-card.accent-amber .psc-icon { background: rgba(245,166,35,.1); border-color: rgba(245,166,35,.2); color: var(--amber); }
.psc-body { min-width: 0; }
.psc-value { font-family: var(--font-head); font-size: 20px; font-weight: 800; color: #fff; line-height: 1; }
.psc-label { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.45); margin-top: 2px; }

/* ── Filter Bar ── */
.pf-bar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: flex-end;
  padding: 10px 16px 0;
  flex-shrink: 0;
}
.pf-bar-inner {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: flex-end;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 10px 14px;
  width: 100%;
}
.pf-group { display: flex; flex-direction: column; gap: 3px; }
.pf-group label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: rgba(255,255,255,.45);
}
.pf-group select,
.pf-group input {
  background: #0D0F14;
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 4px;
  padding: 5px 10px;
  font-size: 12px;
  color: #fff;
  font-family: inherit;
  outline: none;
  transition: border-color .15s;
  height: 30px;
}
/* DARK dropdown options */
.pf-group select {
  padding-right: 26px;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5l5 5 5-5' stroke='rgba(255,255,255,0.5)' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 7px center;
}
.pf-group select option { background: #0D0F14 !important; color: #E8E6E1 !important; }
.pf-group select:focus, .pf-group input:focus { border-color: var(--gold); }
.pf-search { flex: 1; min-width: 200px; }
.pf-actions { display: flex; gap: 6px; align-items: flex-end; margin-left: auto; }

/* ── Action Row ── */
.prod-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 16px 0;
  flex-wrap: wrap;
  gap: 6px;
  flex-shrink: 0;
}
.prod-count { font-size: 12px; color: rgba(255,255,255,.4); }
.prod-count strong { color: rgba(255,255,255,.75); }

/* ── Grid Wrapper ── */
.pg-outer {
  flex: 1;
  min-height: 0;
  padding: 8px 16px 10px;
  display: flex;
  flex-direction: column;
}
.pg-wrap {
  flex: 1;
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-height: 0;
  position: relative;
  background: var(--panel);
}

/* ── Scroll container ── */
.pg-scroll {
  flex: 1;
  overflow: auto;
  overflow-x: scroll;
  /* Always show horizontal scrollbar */
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.25) rgba(255,255,255,.04);
}
.pg-scroll::-webkit-scrollbar { width: 8px; height: 9px; }
.pg-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.03); }
.pg-scroll::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,.22);
  border-radius: 4px;
  border: 2px solid transparent;
  background-clip: padding-box;
}
.pg-scroll::-webkit-scrollbar-thumb:hover { background: rgba(201,168,76,.5); border: 2px solid transparent; background-clip: padding-box; }
.pg-scroll::-webkit-scrollbar-corner { background: #12151C; }

/* ── Table ── */
.pg-table {
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
  font-size: 12px;
  table-layout: fixed;
}

/* ── Frozen Header ── */
.pg-table thead th {
  position: sticky;
  top: 0;
  z-index: 30;
  background: #1A1F30;
  border-bottom: 2px solid rgba(201,168,76,.25);
  white-space: nowrap;
  user-select: none;
  padding: 0;
  vertical-align: middle;
  box-shadow: 0 2px 0 rgba(0,0,0,.3);
}
.th-inner {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px 10px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: rgba(255,255,255,.65);
  cursor: default;
  white-space: nowrap;
  border-right: 1px solid rgba(255,255,255,.05);
}
.th-sortable .th-inner { cursor: pointer; }
.th-sortable .th-inner:hover { color: #fff; background: rgba(255,255,255,.05); }
.th-sorted .th-inner { color: var(--gold-lt); }
.th-sort-icon { opacity: .5; font-size: 9px; flex-shrink: 0; }
.th-sorted .th-sort-icon { opacity: 1; }

/* Resize handle */
.th-resize {
  position: absolute;
  right: 0; top: 0; bottom: 0;
  width: 5px;
  cursor: col-resize;
  z-index: 2;
}
.th-resize:hover { background: rgba(201,168,76,.35); }

/* ── Rows ── */
.pg-table tbody tr { transition: background .08s; }
.pg-table tbody tr:hover td { background: rgba(255,255,255,.03) !important; }
.pg-table tbody tr.row-selected td { background: rgba(201,168,76,.06); }
.pg-table tbody td {
  padding: 6px 10px;
  border-bottom: 1px solid rgba(255,255,255,.04);
  vertical-align: middle;
  color: #E8E6E1;
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 0;
  border-right: 1px solid rgba(255,255,255,.025);
  height: 34px;
  line-height: 1;
}
.pg-table tbody tr:last-child td { border-bottom: none; }

/* ── Cell types — all same height ── */
td.c-num    { text-align: right; font-variant-numeric: tabular-nums; font-size: 12px; }
td.c-mono   { font-family: 'Courier New', monospace; font-size: 11px; color: rgba(232,230,225,.7); }
td.c-text   { font-size: 12px; }
td.c-link   { text-align: center; }
td.c-result { text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; font-size: 12px; }
td.c-toggle { text-align: center; }

/* ── Editable cells ── */
td.editable { position: relative; cursor: text; }
td.editable::after {
  content: '';
  position: absolute;
  bottom: 2px; left: 8px; right: 8px;
  height: 1px;
  background: rgba(201,168,76,.25);
}
td.editable:hover { background: rgba(201,168,76,.06) !important; }
td.editable:hover::after { background: rgba(201,168,76,.55); }
.cell-input {
  width: 100%;
  background: rgba(201,168,76,.08);
  border: 1px solid var(--gold);
  border-radius: 3px;
  padding: 2px 6px;
  color: #fff;
  font-size: 12px;
  font-family: inherit;
  outline: none;
  box-shadow: 0 0 0 2px rgba(201,168,76,.2);
  height: 22px;
}

/* ── Badges ── */
.b-green  { display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;line-height:1.6;background:rgba(61,187,127,.15);color:#fff;border:1px solid rgba(61,187,127,.3); }
.b-gold   { display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;line-height:1.6;background:rgba(201,168,76,.15);color:#fff;border:1px solid rgba(201,168,76,.3); }
.b-muted  { display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;line-height:1.6;background:rgba(255,255,255,.07);color:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.1); }
.b-blue   { display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;line-height:1.6;background:rgba(74,124,255,.15);color:#fff;border:1px solid rgba(74,124,255,.3); }
.b-red    { display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;line-height:1.6;background:rgba(224,92,92,.15);color:#fff;border:1px solid rgba(224,92,92,.3); }

/* ── Toggle (Електроника) ── */
.elek-btn {
  display: inline-block;
  width: 60px;
  padding: 2px 0;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  line-height: 1.6;
  text-align: center;
  cursor: pointer;
  user-select: none;
  transition: all .12s;
  border: 1px solid transparent;
  color: #fff;
}
.elek-btn.is-yes { background: rgba(61,187,127,.2); border-color: rgba(61,187,127,.4); }
.elek-btn.is-no  { background: rgba(255,255,255,.07); color: rgba(255,255,255,.5); border-color: rgba(255,255,255,.12); }
.elek-btn:hover  { filter: brightness(1.15); }

/* ── Amazon link ── */
.amz-link {
  display: inline-flex;
  align-items: center; justify-content: center;
  width: 26px; height: 22px;
  border-radius: 4px;
  background: rgba(255,153,0,.1);
  border: 1px solid rgba(255,153,0,.2);
  color: #FFA500;
  text-decoration: none;
  transition: all .12s;
  font-size: 10px;
}
.amz-link:hover { background: rgba(255,153,0,.2); color: #fff; }

/* ── Result coloring ── */
td.c-result.pos { color: #5DCCA0; }
td.c-result.neg { color: #E05C5C; }
td.c-result.zer { color: rgba(255,255,255,.3); }

/* ── Pagination ── */
.pg-pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 14px;
  border-top: 1px solid rgba(255,255,255,.06);
  background: #181C26;
  flex-wrap: wrap;
  gap: 6px;
  flex-shrink: 0;
  border-bottom-left-radius: 8px;
  border-bottom-right-radius: 8px;
}
.pg-pages { display: flex; gap: 3px; align-items: center; flex-wrap: wrap; }
.pg-btn {
  min-width: 28px; height: 26px;
  padding: 0 7px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid rgba(255,255,255,.1);
  background: transparent;
  color: rgba(255,255,255,.55);
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all .12s;
}
.pg-btn:hover { background: rgba(255,255,255,.06); color: #fff; }
.pg-btn.active { background: var(--gold); color: #0D0F14; border-color: var(--gold); font-weight: 700; }
.pg-btn:disabled, .pg-btn[disabled] { opacity: .3; pointer-events: none; }
.pg-info { font-size: 12px; color: rgba(255,255,255,.4); }
.pg-perpage { display: flex; gap: 5px; align-items: center; font-size: 12px; color: rgba(255,255,255,.4); }
.pg-perpage select {
  background: #0D0F14;
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 4px;
  padding: 3px 6px;
  font-size: 12px;
  color: #fff;
  outline: none;
}
.pg-perpage select option { background: #0D0F14; color: #E8E6E1; }

/* ── Toast ── */
.save-indicator {
  position: fixed;
  bottom: 20px; right: 20px;
  background: var(--green);
  color: #fff;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  z-index: 9999;
  display: none;
  animation: fadeInUp .2s ease;
}
@keyframes fadeInUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }

/* ── Empty state ── */
.products-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  gap: 12px;
  color: rgba(255,255,255,.2);
}
.products-empty svg { opacity: .3; }
.products-empty h3 { font-size: 15px; font-weight: 600; color: rgba(255,255,255,.35); }
.products-empty p { font-size: 12px; }
</style>

<!-- ══ Products Layout ═══════════════════════════════════════════ -->
<div class="prod-layout">

<!-- ── Stats Bar ── -->
<div class="prod-stats-bar">
  <div class="prod-stat-card">
    <div class="psc-icon">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M2 5h16M2 10h16M2 15h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </div>
    <div class="psc-body">
      <div class="psc-value"><?= number_format($total) ?></div>
      <div class="psc-label">Намерени</div>
    </div>
  </div>
  <?php
  // Get full stats from DataStore for the stat cards
  $allStats = DataStore::getProductCount();
  $totalAll = $allStats['total'];
  $withAsin = $allStats['withAsin'];
  $notUp    = $allStats['notUploaded'];
  $supCount = $allStats['suppliers'];
  ?>
  <div class="prod-stat-card accent-green">
    <div class="psc-icon">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M2 10l5 5 9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <div class="psc-body">
      <div class="psc-value"><?= number_format($withAsin) ?></div>
      <div class="psc-label">С ASIN</div>
    </div>
  </div>
  <div class="prod-stat-card accent-amber">
    <div class="psc-icon">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M10 6v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.8"/></svg>
    </div>
    <div class="psc-body">
      <div class="psc-value"><?= number_format($notUp) ?></div>
      <div class="psc-label">За качване</div>
    </div>
  </div>
  <div class="prod-stat-card accent-blue">
    <div class="psc-icon">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M3 17v-1a5 5 0 015-5h4a5 5 0 015 5v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/></svg>
    </div>
    <div class="psc-body">
      <div class="psc-value"><?= number_format($supCount) ?></div>
      <div class="psc-label">Доставчици</div>
    </div>
  </div>
</div>

<!-- Filter Bar -->
<div class="pf-bar">
<form method="get" action="/products" id="filter-form" style="width:100%">
<div class="pf-bar-inner">

  <div class="pf-group">
    <label>Доставчик</label>
    <select name="dostavchik" style="min-width:130px">
      <option value="">— Всички —</option>
      <?php foreach ($suppliers as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= ($filters['dostavchik'] ?? '') === $s ? 'selected' : '' ?>>
        <?= htmlspecialchars($s) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="pf-group">
    <label>Бранд</label>
    <select name="brand" style="min-width:110px">
      <option value="">— Всички —</option>
      <?php foreach ($brands as $b): ?>
      <option value="<?= htmlspecialchars($b) ?>" <?= ($filters['brand'] ?? '') === $b ? 'selected' : '' ?>>
        <?= htmlspecialchars($b) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="pf-group">
    <label>Статус</label>
    <select name="upload_status" style="min-width:100px">
      <option value="">— Всички —</option>
      <option value="NOT_UPLOADED" <?= ($filters['upload_status'] ?? '') === 'NOT_UPLOADED' ? 'selected' : '' ?>>Не качен</option>
      <option value="UPLOADED"     <?= ($filters['upload_status'] ?? '') === 'UPLOADED'     ? 'selected' : '' ?>>Качен</option>
    </select>
  </div>

  <div class="pf-group">
    <label>Електроника</label>
    <select name="elektronika" style="min-width:90px">
      <option value="">— Всички —</option>
      <option value="Yes" <?= ($filters['elektronika'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
      <option value="No"  <?= ($filters['elektronika'] ?? '') === 'No'  ? 'selected' : '' ?>>No</option>
    </select>
  </div>

  <div class="pf-group pf-search">
    <label>Търсене</label>
    <input type="text" name="search"
           placeholder="Модел, Бранд, EAN, ASIN, SKU…"
           value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
           style="width:100%">
  </div>

  <div class="pf-actions">
    <button type="submit" class="btn btn-primary btn-sm">
      <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="5" stroke="currentColor" stroke-width="1.8"/><path d="M15 15l-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      Филтрирай
    </button>
    <a href="/products" class="btn btn-ghost btn-sm">Изчисти</a>
  </div>

  <?php if ($sortCol): ?>
  <input type="hidden" name="sort" value="<?= htmlspecialchars($sortCol) ?>">
  <input type="hidden" name="dir"  value="<?= $sortDir ?>">
  <?php endif; ?>
</div>
</form>
</div>

<!-- Action Bar -->
<div class="prod-actions">
  <div class="prod-count">
    <?php if ($total > 0): ?>
      Показани <strong><?= number_format($from) ?>–<?= number_format($to) ?></strong>
      от <strong><?= number_format($total) ?></strong> продукта
    <?php else: ?>
      <span style="color:rgba(255,255,255,.25)">Няма намерени продукти</span>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:6px;align-items:center">
    <form method="post" action="/api/export-csv" target="_blank" style="display:inline">
      <?php foreach ($filters as $k => $v): ?>
      <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
      <?php endforeach; ?>
      <button type="submit" class="btn btn-ghost btn-sm">
        <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M4 14v2a1 1 0 001 1h10a1 1 0 001-1v-2M10 3v10M7 10l3 3 3-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        CSV
      </button>
    </form>
    <label class="btn btn-ghost btn-sm" style="cursor:pointer">
      <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M4 6v-2a1 1 0 011-1h10a1 1 0 011 1v2M10 17V7M7 10l3-3 3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Import Excel
      <input type="file" accept=".xlsx" onchange="importExcel(this)" style="display:none">
    </label>
  </div>
</div>

<!-- ══ Grid ══════════════════════════════════════════════════════ -->
<div class="pg-outer">
<div class="pg-wrap">
<div class="pg-scroll" id="pg-scroll">
<table class="pg-table" id="pg-table">
  <thead>
    <tr>
<?php
foreach ($COLS as $key => $def) {
    [$label, $type, $width, $editable, $sortable] = $def;
    $thClass = '';
    if ($sortable) $thClass .= ' th-sortable';
    if ($sortCol === $key) $thClass .= ' th-sorted';
    $nextDir = ($sortCol === $key && $sortDir === 'asc') ? 'desc' : 'asc';
    $sortIcon = $sortCol === $key ? ($sortDir === 'asc' ? '▲' : '▼') : '⇅';
    echo '<th style="width:'.$width.'px" class="'.trim($thClass).'" data-key="'.htmlspecialchars($key).'">';
    if ($sortable) {
        $href = '/products' . pq(['sort' => $key, 'dir' => $nextDir, 'page' => 1]);
        echo '<a href="'.htmlspecialchars($href).'" class="th-inner" title="'.htmlspecialchars($key).'">';
    } else {
        echo '<div class="th-inner" title="'.htmlspecialchars($key).'">';
    }
    echo htmlspecialchars($label);
    if ($sortable) echo '<span class="th-sort-icon">'.$sortIcon.'</span>';
    echo $sortable ? '</a>' : '</div>';
    echo '</th>';
}
echo '<th style="width:78px"><div class="th-inner">Статус</div></th>';
?>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($products)): ?>
  <tr>
    <td colspan="<?= count($COLS) + 1 ?>" style="padding:0;border:none">
      <div class="products-empty">
        <svg width="48" height="48" viewBox="0 0 20 20" fill="none"><path d="M2 5h16M2 10h16M2 15h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        <h3>Няма намерени продукти</h3>
        <p>Опитай да промениш филтрите или качи Excel файл</p>
      </div>
    </td>
  </tr>
  <?php endif; ?>

  <?php foreach ($products as $p):
    $ean    = $p['EAN Amazon'] ?? '';
    $eanH   = htmlspecialchars($ean);
    $link   = $p['Amazon Link'] ?? '';
    $asin   = $p['ASIN'] ?? '';
    $status = $p['_upload_status'] ?? 'NOT_UPLOADED';
    $elek   = $p['Електоника'] ?? '';
    $res    = (float)($p['Резултат'] ?? 0);
    $resClass = $res > 0 ? 'pos' : ($res < 0 ? 'neg' : 'zer');
  ?>
  <tr data-ean="<?= $eanH ?>">
    <?php foreach ($COLS as $key => [$label, $type, $width, $editable, $sortable]): ?>
    <?php
      $raw  = $p[$key] ?? null;
      $valH = htmlspecialchars((string)($raw ?? ''));
    ?>
    <?php if ($type === 'link'): ?>
      <td class="c-link">
        <?php if ($link): ?>
        <a href="<?= htmlspecialchars($link) ?>" target="_blank" class="amz-link" title="<?= htmlspecialchars($asin ?: 'Amazon') ?>">
          <svg width="10" height="10" viewBox="0 0 20 20" fill="none"><path d="M11 3h6v6M9 11L17 3M7 5H4a1 1 0 00-1 1v10a1 1 0 001 1h10a1 1 0 001-1v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <?php endif; ?>
      </td>
    <?php elseif ($key === 'ASIN'): ?>
      <td class="c-mono"><?php if ($link && $asin): ?><a href="<?= htmlspecialchars($link) ?>" target="_blank" style="color:var(--gold-lt);text-decoration:none;font-size:11px"><?= $valH ?></a><?php else: ?><?= $valH ?><?php endif; ?></td>
    <?php elseif ($type === 'toggle'): ?>
      <td class="c-toggle">
        <span class="elek-btn <?= $elek === 'Yes' ? 'is-yes' : 'is-no' ?>"
              data-ean="<?= $eanH ?>" data-val="<?= htmlspecialchars($elek) ?>"
              onclick="toggleElek(this)" title="Кликни за промяна">
          <?= $elek ?: '—' ?>
        </span>
      </td>
    <?php elseif ($type === 'result'): ?>
      <td class="c-result <?= $resClass ?>">
        <?= $res != 0 ? number_format($res, 2) : '' ?>
      </td>
    <?php elseif ($type === 'num'): ?>
      <td class="c-num<?= $editable ? ' editable' : '' ?>"
          <?= $editable ? 'data-ean="'.$eanH.'" data-field="'.htmlspecialchars($key).'" onclick="editCell(this)" title="Кликни за редакция"' : '' ?>>
        <?= $raw !== null && $raw !== '' ? number_format((float)$raw, 2) : '' ?>
      </td>
    <?php elseif ($type === 'mono'): ?>
      <td class="c-mono"><?= $valH ?></td>
    <?php else: ?>
      <td class="c-text<?= $editable ? ' editable' : '' ?>"
          <?= $editable ? 'data-ean="'.$eanH.'" data-field="'.htmlspecialchars($key).'" onclick="editCell(this)" title="Кликни за редакция"' : '' ?>
          title="<?= $valH ?>">
        <?= $valH ?>
      </td>
    <?php endif; ?>
    <?php endforeach; ?>
    <td class="c-toggle">
      <span class="<?= $status === 'UPLOADED' ? 'b-green' : 'b-gold' ?>">
        <?= $status === 'UPLOADED' ? 'Качен' : 'Не качен' ?>
      </span>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- ══ Pagination ════════════════════════════════════════════════ -->
<div class="pg-pager">
  <div class="pg-info">
    <?php if ($total > 0): ?>
    Стр. <?= $page ?>/<?= $pages ?> &nbsp;·&nbsp; <?= number_format($total) ?> записа
    <?php endif; ?>
  </div>

  <div class="pg-pages">
    <?php if ($page > 1): ?>
    <a href="/products<?= pq(['page' => 1]) ?>" class="pg-btn" title="Първа">«</a>
    <a href="/products<?= pq(['page' => $page - 1]) ?>" class="pg-btn">‹</a>
    <?php else: ?>
    <span class="pg-btn" disabled>«</span>
    <span class="pg-btn" disabled>‹</span>
    <?php endif; ?>

    <?php
    $start = max(1, min($page - 3, $pages - 6));
    $end   = min($pages, max($page + 3, 7));
    if ($start > 1) echo '<span style="color:rgba(255,255,255,.3);padding:0 2px;line-height:26px">…</span>';
    for ($i = $start; $i <= $end; $i++):
    ?>
    <a href="/products<?= pq(['page' => $i]) ?>"
       class="pg-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($end < $pages) echo '<span style="color:rgba(255,255,255,.3);padding:0 2px;line-height:26px">…</span>'; ?>

    <?php if ($page < $pages): ?>
    <a href="/products<?= pq(['page' => $page + 1]) ?>" class="pg-btn">›</a>
    <a href="/products<?= pq(['page' => $pages]) ?>"    class="pg-btn" title="Последна">»</a>
    <?php else: ?>
    <span class="pg-btn" disabled>›</span>
    <span class="pg-btn" disabled>»</span>
    <?php endif; ?>
  </div>

  <div class="pg-perpage">
    На страница:
    <select onchange="changePerPage(this.value)">
      <?php foreach ([25, 50, 100, 250] as $pp): ?>
      <option value="<?= $pp ?>" <?= $pp === $perPage ? 'selected' : '' ?>><?= $pp ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>
</div><!-- /.pg-wrap -->
</div><!-- /.pg-outer -->

</div><!-- /.prod-layout -->

<!-- Save toast -->
<div class="save-indicator" id="save-toast">✓ Запазено</div>

<script>
// ══ Inline cell edit ═════════════════════════════════════════════
function editCell(td) {
  if (td.querySelector('input')) return;
  const ean      = td.dataset.ean;
  const field    = td.dataset.field;
  const origText = td.textContent.trim();
  const origVal  = origText.replace(/\s/g, '').replace(',', '.');
  td.innerHTML = '';
  const inp = document.createElement('input');
  inp.type = 'text'; inp.value = origVal; inp.className = 'cell-input';
  td.appendChild(inp);
  inp.focus(); inp.select();
  let saved = false;

  function commit() {
    if (saved) return; saved = true;
    const newVal  = inp.value.trim();
    const numVal  = parseFloat(newVal.replace(',', '.'));
    const display = (!isNaN(numVal) && newVal !== '')
      ? numVal.toLocaleString('de-DE', {minimumFractionDigits:2, maximumFractionDigits:2})
      : newVal;
    td.textContent = display;
    if (newVal !== origVal && ean && field) saveCellValue(ean, field, newVal.replace(',', '.'));
  }
  inp.addEventListener('blur', commit);
  inp.addEventListener('keydown', function(e) {
    if (e.key === 'Enter')  { inp.blur(); }
    if (e.key === 'Escape') { saved = true; inp.removeEventListener('blur', commit); td.textContent = origText; }
    if (e.key === 'Tab') {
      e.preventDefault(); inp.blur();
      const cells = [...td.closest('tr').querySelectorAll('td.editable')];
      const idx   = cells.indexOf(td);
      if (cells[idx + 1]) cells[idx + 1].click();
    }
  });
}

function saveCellValue(ean, field, value) {
  const fd = new FormData();
  fd.append('ean', ean); fd.append('field', field); fd.append('value', value);
  fetch('/products/update', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { showToast(d.success ? '✓ Запазено' : ('✗ ' + (d.error||'Грешка')), !d.success); })
    .catch(() => showToast('✗ Мрежова грешка', true));
}

// ══ Електроника toggle ═══════════════════════════════════════════
function toggleElek(el) {
  const ean  = el.dataset.ean;
  const cur  = el.dataset.val;
  const next = cur === 'Yes' ? 'No' : 'Yes';
  el.textContent = next; el.dataset.val = next;
  el.className = 'elek-btn ' + (next === 'Yes' ? 'is-yes' : 'is-no');
  saveCellValue(ean, 'Електоника', next);
}

// ══ Per-page ══════════════════════════════════════════════════════
function changePerPage(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('perpage', val);
  url.searchParams.set('page', '1');
  window.location.href = url.toString();
}

// ══ Import Excel ══════════════════════════════════════════════════
function importExcel(input) {
  const file = input.files[0]; if (!file) return;
  const label = input.closest('label');
  const origHTML = label.innerHTML;
  label.innerHTML = '<span style="opacity:.7">Зареждане…</span>';
  label.style.pointerEvents = 'none';
  const fd = new FormData(); fd.append('file', file);
  fetch('/api/import-excel', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) { showToast('✓ Импортирани ' + d.count + ' продукта'); setTimeout(() => location.reload(), 1200); }
      else { showToast('✗ ' + (d.error || 'Грешка'), true); label.innerHTML = origHTML; label.style.pointerEvents = ''; }
    })
    .catch(() => { showToast('✗ Мрежова грешка', true); label.innerHTML = origHTML; label.style.pointerEvents = ''; })
    .finally(() => { input.value = ''; });
}

// ══ Toast ═════════════════════════════════════════════════════════
let toastTimer;
function showToast(msg, isError = false) {
  const t = document.getElementById('save-toast');
  t.textContent = msg;
  t.style.background = isError ? 'var(--red)' : 'var(--green)';
  t.style.display = 'block';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { t.style.display = 'none'; }, 2000);
}

// ══ Keyboard ══════════════════════════════════════════════════════
document.addEventListener('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
    const inp = document.querySelector('input[name="search"]');
    if (inp) { e.preventDefault(); inp.focus(); inp.select(); }
  }
});

// ══ Column resize ══════════════════════════════════════════════════
(function initResize() {
  const table = document.getElementById('pg-table');
  if (!table) return;
  table.querySelectorAll('thead th').forEach(th => {
    th.style.position = 'relative';
    const h = document.createElement('div');
    h.className = 'th-resize';
    h.addEventListener('mousedown', function(e) {
      e.preventDefault();
      const startX = e.pageX, startW = th.offsetWidth;
      function onMove(e) { th.style.width = Math.max(50, startW + e.pageX - startX) + 'px'; }
      function onUp()    { document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp); }
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
    th.appendChild(h);
  });
})();

// ══ Utility ═══════════════════════════════════════════════════════
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
