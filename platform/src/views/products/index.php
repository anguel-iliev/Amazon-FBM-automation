<?php
/**
 * Products v5.0
 * - Column resize (drag divider)
 * - Persistent widths + visibility + order via localStorage
 * - Column manager panel (reorder + hide/show)
 */
$stats       = $stats       ?? [];
$suppliers   = $suppliers   ?? [];
$brands      = $brands      ?? [];
$filters     = $filters     ?? [];
$perPage     = $perPage     ?? 50;
$page        = $page        ?? 1;
$columnsMeta = $columnsMeta ?? ProductDB::getAllColumnsMeta();

$coreWidths = [
  'EAN Amazon'=>130,'EAN Доставчик'=>130,'Наше SKU'=>120,'Доставчик SKU'=>120,'Доставчик'=>100,'Бранд'=>90,'Модел'=>240,
  'Amazon Link'=>46,'ASIN'=>120,'Цена Конкурент  - Brutto'=>110,'Цена Amazon  - Brutto'=>90,'Продажна Цена в Амазон  - Brutto'=>110,
  'Цена без ДДС'=>80,'ДДС от продажна цена'=>80,'Amazon Такси'=>80,'Цена Доставчик -Netto'=>90,'ДДС  от Цена Доставчик'=>80,
  'Транспорт от Доставчик до нас'=>120,'Транспорт до кр. лиент  Netto'=>90,'ДДС  от Транспорт до кр. лиент'=>80,'Резултат'=>80,
  'Намерена 2ра обява'=>110,'Цена за ES FR IT'=>90,'DM цена'=>80,'Нова цена след намаление'=>90,'Доставени'=>70,'За следваща поръчка'=>90,
  'Електоника'=>80,'Корекция  на цена'=>80,'Коментар'=>160,
];
$ALL_COLS = [];
foreach ($columnsMeta as $col) {
  if (($col['name'] ?? '') === '_upload_status') continue;
  $ALL_COLS[] = [$col['name'], $coreWidths[$col['name']] ?? 120];
}
$EDITABLE = ['Продажна Цена в Амазон  - Brutto','Цена Конкурент  - Brutto','Цена Доставчик -Netto','Транспорт от Доставчик до нас','Транспорт до кр. лиент  Netto','Намерена 2ра обява','DM цена','Нова цена след намаление','За следваща поръчка','Електоника','Корекция  на цена','Коментар'];
foreach ($columnsMeta as $col) {
  if (!empty($col['is_custom']) && empty($col['is_formula'])) $EDITABLE[] = $col['name'];
}
$EDITABLE = array_values(array_unique($EDITABLE));
$TYPE_MAP = ['Amazon Link'=>'link','ASIN'=>'asin','Електоника'=>'toggle','Резултат'=>'result'];
$NUM_COLS = ['Цена Конкурент  - Brutto','Цена Amazon  - Brutto','Продажна Цена в Амазон  - Brutto','Цена без ДДС','ДДС от продажна цена','Amazon Такси','Цена Доставчик -Netto','ДДС  от Цена Доставчик','Транспорт от Доставчик до нас','Транспорт до кр. лиент  Netto','ДДС  от Транспорт до кр. лиент','DM цена','Нова цена след намаление','Доставени','За следваща поръчка','Корекция  на цена','Резултат'];
foreach ($columnsMeta as $col) {
  if (($col['data_type'] ?? '') === 'number' && !in_array($col['name'], $NUM_COLS, true)) $NUM_COLS[] = $col['name'];
}
?>
<style>
/* ── Layout ── */
.pw{display:flex;flex-direction:column;height:calc(100vh - 62px);overflow:hidden;padding:0}

/* ── Stats ── */
.psb{display:flex;gap:10px;padding:10px 16px 0;flex-shrink:0}
.psc{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:10px 16px;display:flex;align-items:center;gap:12px;flex:1;min-width:0;position:relative;overflow:hidden}
.psc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--gold)}
.psc.g::before{background:var(--green)}.psc.b::before{background:var(--blue)}.psc.a::before{background:var(--amber)}
.psi{width:32px;height:32px;border-radius:8px;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--gold)}
.psc.g .psi{background:rgba(61,187,127,.1);border-color:rgba(61,187,127,.2);color:var(--green)}
.psc.b .psi{background:rgba(74,124,255,.1);border-color:rgba(74,124,255,.2);color:var(--blue)}
.psc.a .psi{background:rgba(245,166,35,.1);border-color:rgba(245,166,35,.2);color:var(--amber)}
.psv{font-family:var(--font-head);font-size:20px;font-weight:800;color:#fff;line-height:1}
.psl{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.6);margin-top:2px}

/* ── Filters ── */
.pf{padding:10px 16px 0;flex-shrink:0}
.pfi{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;width:100%}
.pfg{display:flex;flex-direction:column;gap:3px}
.pfg label{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.7)}
.pfg select,.pfg input{background:#0D0F14;border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:5px 10px;font-size:13px;color:#fff;font-family:inherit;outline:none;height:30px}
.pfg select{padding-right:24px;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 20 20'%3E%3Cpath d='M5 7.5l5 5 5-5' stroke='rgba(255,255,255,.6)' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 6px center}
.pfg select option{background:#0D0F14;color:#E8E6E1}
.pfg select:focus,.pfg input:focus{border-color:var(--gold)}
.pfs{flex:1;min-width:180px}
.pfa{display:flex;gap:6px;align-items:flex-end;margin-left:auto}

/* ── Action row ── */
.par{display:flex;justify-content:space-between;align-items:center;padding:8px 16px 0;flex-shrink:0;gap:6px;flex-wrap:wrap}
.par-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.btn-wrap2{display:inline-flex;flex-direction:column;align-items:center;justify-content:center;line-height:1.05;text-align:center;min-height:38px;padding-top:6px;padding-bottom:6px;white-space:normal}
.par-info{font-size:13px;color:rgba(255,255,255,.7)}
.par-info strong{color:#fff}

/* ── Grid container ── */
.pgo{flex:1;min-height:0;padding:8px 16px 10px;display:flex;flex-direction:column}
.pgw{flex:1;border:1px solid rgba(255,255,255,.07);border-radius:8px;display:flex;flex-direction:column;overflow:hidden;min-height:0;background:var(--panel)}
.pgs{flex:1;overflow:auto;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.2) rgba(255,255,255,.04)}
.pgs::-webkit-scrollbar{width:8px;height:9px}
.pgs::-webkit-scrollbar-track{background:rgba(255,255,255,.03)}
.pgs::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:4px;border:2px solid transparent;background-clip:padding-box}
.pgs::-webkit-scrollbar-thumb:hover{background:rgba(201,168,76,.5);border:2px solid transparent;background-clip:padding-box}
.pgs::-webkit-scrollbar-corner{background:#12151C}

/* ── Table ── */
.pgt{width:max-content;min-width:100%;border-collapse:collapse;font-size:13px;table-layout:fixed}
.pgt thead th{
  position:sticky;top:0;z-index:30;background:#1A1F30;
  border-bottom:2px solid rgba(201,168,76,.25);
  padding:0;vertical-align:top;
  box-shadow:0 2px 0 rgba(0,0,0,.3);
  overflow:visible; /* needed for resize handle */
}
.th-in{
  display:flex;align-items:flex-start;justify-content:center;
  gap:3px;padding:7px 8px;
  font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;
  color:rgba(255,255,255,.85);
  white-space:normal;word-break:break-word;line-height:1.35;
  border-right:1px solid rgba(255,255,255,.04);
  min-height:40px;cursor:pointer;text-align:center;
  user-select:none;
}
.th-in:hover{color:#fff}
.th-in.sorted{color:var(--gold)}
.sort-ico{flex-shrink:0;font-size:8px;opacity:.7;margin-top:1px}

/* ── COLUMN RESIZE HANDLE (Point 1) ── */
.col-resize{
  position:absolute;
  right:-3px;top:0;bottom:0;
  width:7px;
  cursor:col-resize;
  z-index:40;
  background:transparent;
  transition:background .15s;
}
.col-resize:hover,.col-resize.active{
  background:rgba(201,168,76,.6);
}

/* ── Table rows ── */
.pgt tbody tr:hover td{background:rgba(255,255,255,.03)!important}
.pgt tbody td{
  padding:5px 8px;border-bottom:1px solid rgba(255,255,255,.04);
  vertical-align:middle;color:#E8E6E1;font-size:13px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  max-width:0;border-right:1px solid rgba(255,255,255,.02);height:34px;
  text-align:center
}
.pgt tbody tr:last-child td{border-bottom:none}
td.cn{text-align:center;font-variant-numeric:tabular-nums}
td.cm{text-align:center;font-family:monospace;font-size:12px;color:rgba(232,230,225,.65)}
td.cl{text-align:center}
td.cc{text-align:center;font-variant-numeric:tabular-nums}
td.cr{text-align:center;font-variant-numeric:tabular-nums;font-weight:700}
td.cr.pos{color:#5DCCA0}td.cr.neg{color:#E05C5C}td.cr.zer{color:rgba(255,255,255,.25)}
td.ct{text-align:center}
.sel-col{width:42px;min-width:42px}.sel-box{width:16px;height:16px;accent-color:var(--gold);cursor:pointer}.btn-danger{background:rgba(224,92,92,.16);border:1px solid rgba(224,92,92,.38);color:#ff8b8b}.btn-danger:hover{background:rgba(224,92,92,.26);color:#fff}.par-actions .btn-sm{position:relative}
td.ed{cursor:text;position:relative}
td.ed::after{content:'';position:absolute;bottom:2px;left:6px;right:6px;height:1px;background:rgba(201,168,76,.2)}
td.ed:hover{background:rgba(201,168,76,.05)!important}td.ed:hover::after{background:rgba(201,168,76,.5)}
.ci{width:100%;background:rgba(201,168,76,.08);border:1px solid var(--gold);border-radius:3px;padding:2px 5px;color:#fff;font-size:13px;font-family:inherit;outline:none;height:22px}

/* ── Badges ── */
.bg{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;line-height:1.6}
.bg-g{background:rgba(61,187,127,.15);color:#5DCCA0;border:1px solid rgba(61,187,127,.3)}
.bg-a{background:rgba(201,168,76,.15);color:var(--gold-lt);border:1px solid rgba(201,168,76,.3)}
.elek{display:inline-block;width:52px;padding:2px 0;border-radius:20px;font-size:11px;font-weight:700;line-height:1.6;text-align:center;cursor:pointer;user-select:none;border:1px solid transparent;color:#fff}
.elek.y{background:rgba(61,187,127,.2);border-color:rgba(61,187,127,.4)}
.elek.n{background:rgba(255,255,255,.06);color:rgba(255,255,255,.4);border-color:rgba(255,255,255,.1)}
.al{display:inline-flex;align-items:center;justify-content:center;width:24px;height:20px;border-radius:4px;background:rgba(255,153,0,.1);border:1px solid rgba(255,153,0,.2);color:#FFA500;text-decoration:none}
.al:hover{background:rgba(255,153,0,.2);color:#fff}

/* ── Pagination ── */
.pgp{display:flex;justify-content:space-between;align-items:center;padding:7px 14px;border-top:1px solid rgba(255,255,255,.06);background:#181C26;flex-wrap:wrap;gap:6px;flex-shrink:0;border-bottom-left-radius:8px;border-bottom-right-radius:8px}
.pgpg{display:flex;gap:3px;align-items:center;flex-wrap:wrap}
.pgb{min-width:28px;height:26px;padding:0 7px;border-radius:4px;font-size:13px;font-weight:600;border:1px solid rgba(255,255,255,.1);background:transparent;color:rgba(255,255,255,.7);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.pgb:hover{background:rgba(255,255,255,.06);color:#fff}
.pgb.act{background:var(--gold);color:#0D0F14;border-color:var(--gold);font-weight:700}
.pgb[disabled]{opacity:.3;pointer-events:none}
.pgpp{display:flex;gap:5px;align-items:center;font-size:13px;color:rgba(255,255,255,.7)}
.pgpp select{background:#0D0F14;border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:3px 6px;font-size:13px;color:#fff;outline:none}

/* ── Status rows ── */
.loading-row td,.error-row td,.empty-row td{padding:40px!important}
.loading-row td{text-align:center;color:rgba(255,255,255,.5)!important}
.error-row td{text-align:center;color:#F08080!important}
.empty-row td{text-align:center;color:rgba(255,255,255,.4)!important}

/* ── Toast ── */
.toast{position:fixed;bottom:20px;right:20px;background:var(--green);color:#fff;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:700;z-index:9999;display:none}

/* ── COLUMN MANAGER PANEL (Point 3) ── */
.col-mgr-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;display:flex;align-items:flex-start;justify-content:flex-end}
.col-mgr-panel{width:340px;height:100vh;background:#1A1E2A;border-left:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;box-shadow:-8px 0 32px rgba(0,0,0,.5)}
.col-mgr-head{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.col-mgr-title{font-family:var(--font-head);font-size:15px;font-weight:800;color:#fff}
.col-mgr-actions{padding:10px 20px;display:flex;gap:8px;border-bottom:1px solid rgba(255,255,255,.06);flex-shrink:0}
.col-mgr-list{flex:1;overflow-y:auto;padding:8px 0}
.col-mgr-item{display:flex;align-items:center;gap:10px;padding:8px 20px;cursor:grab;transition:background .12s;user-select:none}
.col-mgr-item:hover{background:rgba(255,255,255,.04)}
.col-mgr-item.dragging{background:rgba(201,168,76,.1);opacity:.8}
.col-mgr-item.drag-over{border-top:2px solid var(--gold)}
.col-mgr-drag{color:rgba(255,255,255,.3);font-size:14px;cursor:grab;flex-shrink:0}
.col-mgr-drag:active{cursor:grabbing}
.col-mgr-name{flex:1;font-size:13px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.col-mgr-toggle{width:34px;height:18px;border-radius:9px;border:none;cursor:pointer;position:relative;transition:background .15s;flex-shrink:0}
.col-mgr-toggle.on{background:var(--gold)}
.col-mgr-toggle.off{background:rgba(255,255,255,.15)}
.col-mgr-toggle::after{content:'';position:absolute;top:2px;width:14px;height:14px;border-radius:50%;background:#fff;transition:left .15s}
.col-mgr-toggle.on::after{left:18px}
.col-mgr-toggle.off::after{left:2px}
.col-mgr-footer{padding:14px 20px;border-top:1px solid rgba(255,255,255,.08);flex-shrink:0}
</style>

<div class="pw">

<!-- Stats bar -->
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
  <div class="par-actions">
    <a href="/products/add" class="btn btn-ghost btn-sm btn-wrap2">+ Добави<br>продукт</a>
    <button type="button" id="delete-btn" class="btn btn-danger btn-sm" onclick="deleteSelected()" disabled>Изтрий</button>
    <button type="button" class="btn btn-ghost btn-sm btn-wrap2" onclick="openColMgr()">Колони</button>
    <button type="button" class="btn btn-ghost btn-sm" onclick="selectAllPage()">Всички на стр.</button>
    <button type="button" class="btn btn-ghost btn-sm" onclick="toggleSelectAllDb()" id="sel-all-db-btn">Всички в базата</button>
    <a href="/products/import" class="btn btn-ghost btn-sm">↑ Import</a>
    <a id="export-btn" href="/products/export" class="btn btn-ghost btn-sm">↓ CSV</a>
    <a id="export-xlsx-btn" href="/products/export-xlsx" class="btn btn-ghost btn-sm">↓ XLSX</a>
  </div>
</div>

<!-- Grid -->
<div class="pgo"><div class="pgw"><div class="pgs" id="pgs">
<table class="pgt" id="pgt">
  <thead><tr id="thead-row"></tr></thead>
  <tbody id="tbody">
    <tr class="loading-row"><td colspan="32"><div style="display:flex;align-items:center;justify-content:center;gap:10px"><span class="spinner"></span> Зареждане…</div></td></tr>
  </tbody>
</table>
</div>
<div class="pgp" id="pgp" style="display:none">
  <div style="font-size:13px;color:rgba(255,255,255,.6)" id="pgp-info"></div>
  <div class="pgpg" id="pgp-pages"></div>
  <div class="pgpp">На стр.:
    <select id="pp-sel" onchange="changePerPage(this.value)">
      <?php foreach ([25,50,100,250] as $pp): ?><option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option><?php endforeach; ?>
    </select>
  </div>
</div>
</div></div></div>
</div>

<!-- Column Manager Overlay (Point 3) -->
<div class="col-mgr-overlay" id="col-mgr" style="display:none" onclick="if(event.target===this)closeColMgr()">
  <div class="col-mgr-panel">
    <div class="col-mgr-head">
      <div class="col-mgr-title">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="none" style="margin-right:6px;vertical-align:middle"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Управление на колони
      </div>
      <button onclick="closeColMgr()" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;font-size:18px;line-height:1;padding:2px 6px">✕</button>
    </div>
    <div class="col-mgr-actions">
      <button class="btn btn-ghost btn-sm" onclick="addCustomColumn()">+ Нова колона</button>
      <button class="btn btn-ghost btn-sm" onclick="showAllCols()">Покажи всички</button>
      <button class="btn btn-ghost btn-sm" onclick="resetColSettings()">Нулирай</button>
    </div>
    <div class="col-mgr-list" id="col-mgr-list"></div>
    <div class="col-mgr-footer">
      <button class="btn btn-primary" style="width:100%" onclick="applyColSettings()">Приложи</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// ═══════════════════════════════════════════════════════════
//  COLUMN SETTINGS (Points 1, 2, 3)
//  Stored in localStorage — persists across sessions & navigation
// ═══════════════════════════════════════════════════════════
const LS_KEY = 'amz_col_settings_v2';

// All columns from PHP
const ALL_COLS_DEF = <?= json_encode(array_map(fn($c) => ['key'=>$c[0],'width'=>$c[1]], $ALL_COLS), JSON_UNESCAPED_UNICODE) ?>;

const EDITABLE_COLS = <?= json_encode($EDITABLE, JSON_UNESCAPED_UNICODE) ?>;
const NUM_COLS      = <?= json_encode($NUM_COLS, JSON_UNESCAPED_UNICODE) ?>;
const TYPE_MAP      = <?= json_encode($TYPE_MAP, JSON_UNESCAPED_UNICODE) ?>;
const CENTER_COLS   = ['EAN Amazon','EAN Доставчик','Наше SKU','Доставчик SKU'];
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
function csrfHeaders(extra = {}) {
  return Object.assign({'X-CSRF-Token': CSRF_TOKEN}, extra);
}


// ── Load settings ─────────────────────────────────────────
function loadColSettings() {
  try {
    const raw = localStorage.getItem(LS_KEY);
    if (raw) return JSON.parse(raw);
  } catch(e) {}
  return null;
}

// ── Save settings ─────────────────────────────────────────
function saveColSettings(settings) {
  try { localStorage.setItem(LS_KEY, JSON.stringify(settings)); } catch(e) {}
}

// ── Get current effective columns ─────────────────────────
// Returns array of {key, width, visible} in current order
function getAllCols() {
  const settings = loadColSettings() || {};
  const widths   = settings.widths || {};
  const hidden   = settings.hidden || [];
  const order    = settings.order || ALL_COLS_DEF.map(c => c.key);

  const cols = ALL_COLS_DEF.map(c => ({
    key: c.key,
    width: widths[c.key] || c.width,
    visible: !hidden.includes(c.key),
  }));

  cols.sort((a, b) => {
    const ai = order.indexOf(a.key);
    const bi = order.indexOf(b.key);
    const av = ai === -1 ? Number.MAX_SAFE_INTEGER : ai;
    const bv = bi === -1 ? Number.MAX_SAFE_INTEGER : bi;
    return av - bv || a.key.localeCompare(b.key);
  });

  return cols;
}

function getActiveCols() {
  return getAllCols().filter(c => c.visible);
}

// ═══════════════════════════════════════════════════════════
//  TABLE STATE
// ═══════════════════════════════════════════════════════════
const STATE = {
  selected: {},
  selectAllDb: false,
  currentProducts: [],
  dostavchik:    <?= json_encode($filters['dostavchik']    ?? '') ?>,
  brand:         <?= json_encode($filters['brand']         ?? '') ?>,
  upload_status: <?= json_encode($filters['upload_status'] ?? '') ?>,
  search:        <?= json_encode($filters['search']        ?? '') ?>,
  sort:          <?= json_encode($filters['sort']          ?? '') ?>,
  dir:           <?= json_encode($filters['dir']           ?? 'asc') ?>,
  page:          <?= (int)$page ?>,
  perpage:       <?= (int)$perPage ?>,
};

function updateExportButtons(){
  const csvBtn=document.getElementById('export-btn');
  const xlsxBtn=document.getElementById('export-xlsx-btn');
  if(!csvBtn || !xlsxBtn) return;
  const selected=Object.keys(STATE.selected);
  const qs=selected.length ? ('?eans=' + encodeURIComponent(selected.join(','))) : '';
  csvBtn.href='/products/export'+qs;
  xlsxBtn.href='/products/export-xlsx'+qs;
}

// ═══════════════════════════════════════════════════════════
//  BUILD HEADER
// ═══════════════════════════════════════════════════════════
function buildHeader() {
  const cols = getActiveCols().filter(c => c.visible);
  const tr   = document.getElementById('thead-row');
  tr.innerHTML = '';

  const selTh = document.createElement('th');
  selTh.className = 'sel-col';
  selTh.innerHTML = '<div class="th-in" style="cursor:default"><input type="checkbox" class="sel-box" id="sel-page-top" onclick="toggleHeaderSelect(this.checked)"></div>';
  tr.appendChild(selTh);

  cols.forEach(col => {
    const key      = col.key;
    const sorted   = STATE.sort === key;
    const th       = document.createElement('th');
    th.style.width    = col.width + 'px';
    th.style.minWidth = col.width + 'px';
    th.dataset.col    = key;

    th.innerHTML = `
      <div class="th-in${sorted ? ' sorted' : ''}" onclick="sortBy('${escH(key).replace(/'/g,"\\'")}')">
        <span>${escH(key)}</span>
        <span class="sort-ico">${sorted ? (STATE.dir === 'asc' ? '▲' : '▼') : ''}</span>
      </div>
      <div class="col-resize" data-col="${escH(key)}"></div>
    `;
    tr.appendChild(th);
  });

  // Status column
  const stTh = document.createElement('th');
  stTh.style.width    = '75px';
  stTh.style.minWidth = '75px';
  stTh.innerHTML = '<div class="th-in" style="cursor:default">Статус</div>';
  tr.appendChild(stTh);

  initResizeHandles();
}

// ═══════════════════════════════════════════════════════════
//  POINT 1+2: COLUMN RESIZE — drag the border line
// ═══════════════════════════════════════════════════════════
function initResizeHandles() {
  document.querySelectorAll('.col-resize').forEach(handle => {
    handle.addEventListener('mousedown', startResize);
  });
}

function startResize(e) {
  e.preventDefault();
  e.stopPropagation();

  const handle   = e.currentTarget;
  const th       = handle.closest('th');
  const colKey   = handle.dataset.col;
  const startX   = e.pageX;
  const startW   = th.offsetWidth;

  handle.classList.add('active');
  document.body.style.cursor    = 'col-resize';
  document.body.style.userSelect = 'none';

  function onMove(e) {
    const newW = Math.max(40, startW + (e.pageX - startX));
    th.style.width    = newW + 'px';
    th.style.minWidth = newW + 'px';
  }

  function onUp(e) {
    const newW = Math.max(40, startW + (e.pageX - startX));
    handle.classList.remove('active');
    document.body.style.cursor    = '';
    document.body.style.userSelect = '';

    // Point 2: Save to localStorage
    const settings = loadColSettings() || {
      order:  getActiveCols().map(c => c.key),
      widths: {},
      hidden: getActiveCols().filter(c => !c.visible).map(c => c.key),
    };
    if (!settings.widths) settings.widths = {};
    settings.widths[colKey] = newW;
    saveColSettings(settings);

    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup',   onUp);
  }

  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup',   onUp);
}

// ═══════════════════════════════════════════════════════════
//  LOAD PRODUCTS (AJAX)
// ═══════════════════════════════════════════════════════════
function loadProducts() {
  const tbody = document.getElementById('tbody');
  const cols  = getActiveCols().filter(c => c.visible);
  tbody.innerHTML = '<tr class="loading-row"><td colspan="' + (cols.length + 2) + '"><div style="display:flex;align-items:center;justify-content:center;gap:10px"><span class="spinner"></span> Зареждане…</div></td></tr>'; updateDeleteBtn();
  document.getElementById('pgp').style.display = 'none';

  buildHeader();

  const params = new URLSearchParams();
  ['dostavchik','brand','upload_status','search','sort','dir','page','perpage'].forEach(k => { const v = STATE[k]; if(v!=='' && v!==null && v!==undefined) params.set(k, v); });

  updateExportButtons();

  fetch('/products/data?' + params.toString(), {headers: csrfHeaders()})
    .then(r => r.text().then(text => {
      let d; try { d = JSON.parse(text); } catch(e) { throw new Error('Сървърът върна HTML:\n' + text.substring(0,300)); }
      return d;
    }))
    .then(data => {
      if (!data.ok) {
        let msg = '<strong>✗ Грешка:</strong> ' + escH(data.error || 'Неизвестна грешка');
        if (data.diag) {
          msg += '<br><br>• Firebase ready: ' + (data.diag.firebase_ready ? '✅' : '❌');
          msg += '<br>• cURL: ' + (data.diag.curl ? '✅' : '❌');
          msg += '<br>• Secret дължина: ' + (data.diag.secret_len||0) + ' (трябва ~40)';
        }
        msg += '<br><br><a href="/products/diagnose" target="_blank" style="color:var(--gold)">Диагностика →</a>';
        tbody.innerHTML = '<tr class="error-row"><td colspan="' + (cols.length+2) + '" style="padding:24px!important">' + msg + '</td></tr>'; updateDeleteBtn();
        document.getElementById('par-info').innerHTML = '<span style="color:var(--red)">✗ Грешка</span>';
        return;
      }
      renderTable(data.products, data);
      document.getElementById('s-total').textContent = data.total.toLocaleString();
    })
    .catch(err => {
      const msg = escH(err.message).replace(/\n/g,'<br>');
      tbody.innerHTML = '<tr class="error-row"><td colspan="' + (cols.length+2) + '" style="padding:24px!important;font-size:13px"><strong>✗ Грешка:</strong><br><br>' + msg + '</td></tr>'; updateDeleteBtn();
      document.getElementById('par-info').innerHTML = '<span style="color:var(--red)">✗ Грешка</span>';
    });
}

function renderTable(products, meta) {
  STATE.currentProducts = products || [];
  const tbody  = document.getElementById('tbody');
  const total  = meta.total, page = meta.page, pages = meta.pages, pp = meta.perPage;
  const from   = total > 0 ? (page-1)*pp+1 : 0;
  const to     = Math.min(page*pp, total);
  const cols   = getActiveCols().filter(c => c.visible);

  document.getElementById('par-info').innerHTML = total > 0
    ? 'Показани <strong>' + from.toLocaleString() + '–' + to.toLocaleString() + '</strong> от <strong>' + total.toLocaleString() + '</strong>'
    : '<span style="color:rgba(255,255,255,.4)">Няма продукти</span>';

  if (!products.length) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="' + (cols.length+2) + '">Няма намерени продукти.<br><small><a href="/products/import" style="color:var(--gold)">Импортирай файл →</a></small></td></tr>'; updateDeleteBtn();
    document.getElementById('pgp').style.display = 'none';
    return;
  }

  let html = '';
  for (const p of products) {
    const eanH   = escH(p['EAN Amazon'] || '');
    const link   = p['Amazon Link'] || '';
    const status = p['_upload_status'] || 'NOT_UPLOADED';
    const elek   = p['Електоника'] || '';
    const res    = parseFloat(p['Резултат'] || '0') || 0;
    const resC   = res > 0 ? 'pos' : (res < 0 ? 'neg' : 'zer');

    const checked = !!STATE.selected[String(p['EAN Amazon'] || '')];
    html += '<tr data-ean="' + eanH + '">';
    html += `<td class="cl sel-col"><input type="checkbox" class="sel-box row-sel" data-ean="${eanH}" ${checked ? 'checked' : ''} onchange="toggleRowSelection(this)"></td>`;

    for (const col of cols) {
      const key      = col.key;
      const raw      = p[key] ?? '';
      const valH     = escH(String(raw));
      const editable = EDITABLE_COLS.includes(key);
      const isNum    = NUM_COLS.includes(key);
      const type     = TYPE_MAP[key] || (isNum ? 'num' : 'text');
      const attr     = editable ? ` data-ean="${eanH}" data-field="${escH(key)}" onclick="editCell(this)"` : '';

      if (type === 'link') {
        html += `<td class="cl">${link ? `<a href="${escH(link)}" target="_blank" class="al"><svg width="10" height="10" viewBox="0 0 20 20" fill="none"><path d="M11 3h6v6M9 11L17 3M7 5H4a1 1 0 00-1 1v10a1 1 0 001 1h10a1 1 0 001-1v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>` : ''}</td>`;
      } else if (type === 'asin') {
        html += `<td class="cm">${link && raw ? `<a href="${escH(link)}" target="_blank" style="color:var(--gold-lt);text-decoration:none">${valH}</a>` : valH}</td>`;
      } else if (type === 'toggle') {
        html += `<td class="cl"><span class="elek ${elek==='Yes'?'y':'n'}" data-ean="${eanH}" data-val="${escH(elek)}" onclick="toggleElek(this)">${elek||'—'}</span></td>`;
      } else if (type === 'result') {
        html += `<td class="cr ${resC}">${res !== 0 ? fmtNum(res) : ''}</td>`;
      } else if (type === 'num') {
        const n = raw !== '' ? parseFloat(raw) : null;
        html += `<td class="cn${editable?' ed':''}"${attr}>${n !== null && !isNaN(n) ? fmtNum(n) : (valH||'')}</td>`;
      } else {
        const textClass = CENTER_COLS.includes(key) ? 'cc' : 'ct';
        html += `<td class="${textClass}${editable?' ed':''}"${attr} title="${valH}">${valH}</td>`;
      }
    }
    html += `<td class="cl"><span class="bg ${status==='UPLOADED'?'bg-g':'bg-a'}">${status==='UPLOADED'?'Качен':'Не качен'}</span></td>`;
    html += '</tr>';
  }
  tbody.innerHTML = html;
  syncHeaderCheckbox();
  updateDeleteBtn();
  renderPager(page, pages, total, pp);
}

function renderPager(page, pages, total, pp) {
  const pgp = document.getElementById('pgp');
  pgp.style.display = 'flex';
  document.getElementById('pgp-info').textContent = total > 0 ? `Стр. ${page}/${pages} · ${total.toLocaleString()} записа` : '';
  let btns = '';
  btns += `<button class="pgb" onclick="goPage(1)"${page>1?'':' disabled'}>«</button>`;
  btns += `<button class="pgb" onclick="goPage(${page-1})"${page>1?'':' disabled'}>‹</button>`;
  let s = Math.max(1, Math.min(page-3, pages-6)), e = Math.min(pages, Math.max(page+3, 7));
  if (s > 1) btns += '<span style="color:rgba(255,255,255,.3);padding:0 3px">…</span>';
  for (let i = s; i <= e; i++) btns += `<button class="pgb${i===page?' act':''}" onclick="goPage(${i})">${i}</button>`;
  if (e < pages) btns += '<span style="color:rgba(255,255,255,.3);padding:0 3px">…</span>';
  btns += `<button class="pgb" onclick="goPage(${page+1})"${page<pages?'':' disabled'}>›</button>`;
  btns += `<button class="pgb" onclick="goPage(${pages})"${page<pages?'':' disabled'}>»</button>`;
  document.getElementById('pgp-pages').innerHTML = btns;
}

// ═══════════════════════════════════════════════════════════
//  POINT 3: COLUMN MANAGER
// ═══════════════════════════════════════════════════════════
let dragSrc = null;

function openColMgr() {
  const overlay = document.getElementById('col-mgr');
  const list    = document.getElementById('col-mgr-list');
  const cols    = getAllCols();
  overlay.style.display = 'flex';

  list.innerHTML = '';
  cols.forEach((col, idx) => {
    const item = document.createElement('div');
    item.className   = 'col-mgr-item';
    item.draggable   = true;
    item.dataset.key = col.key;
    item.innerHTML   = `
      <span class="col-mgr-drag" title="Влачи за промяна на реда">⠿</span>
      <span class="col-mgr-name" title="${escH(col.key)}">${escH(col.key)}</span>
      <button class="col-mgr-toggle ${col.visible ? 'on' : 'off'}" onclick="toggleColItem(this)" title="${col.visible ? 'Скрий' : 'Покажи'}"></button>
    `;
    // Drag events
    item.addEventListener('dragstart', e => {
      dragSrc = item;
      item.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    item.addEventListener('dragend', () => {
      item.classList.remove('dragging');
      document.querySelectorAll('.col-mgr-item').forEach(i => i.classList.remove('drag-over'));
    });
    item.addEventListener('dragover', e => {
      e.preventDefault();
      document.querySelectorAll('.col-mgr-item').forEach(i => i.classList.remove('drag-over'));
      if (dragSrc !== item) item.classList.add('drag-over');
    });
    item.addEventListener('drop', e => {
      e.preventDefault();
      if (dragSrc && dragSrc !== item) {
        const allItems = [...list.querySelectorAll('.col-mgr-item')];
        const srcIdx   = allItems.indexOf(dragSrc);
        const tgtIdx   = allItems.indexOf(item);
        if (srcIdx < tgtIdx) { list.insertBefore(dragSrc, item.nextSibling); }
        else                 { list.insertBefore(dragSrc, item); }
      }
      item.classList.remove('drag-over');
    });

    list.appendChild(item);
  });
}

function toggleColItem(btn) {
  const on = btn.classList.contains('on');
  btn.className = 'col-mgr-toggle ' + (on ? 'off' : 'on');
  btn.title = on ? 'Покажи' : 'Скрий';
}

function closeColMgr() {
  document.getElementById('col-mgr').style.display = 'none';
}

function applyColSettings() {
  const items  = [...document.querySelectorAll('.col-mgr-item')];
  const order  = items.map(i => i.dataset.key);
  const hidden = items.filter(i => i.querySelector('.col-mgr-toggle').classList.contains('off')).map(i => i.dataset.key);

  const settings = loadColSettings() || { widths: {} };
  settings.order  = order;
  settings.hidden = hidden;
  saveColSettings(settings);

  closeColMgr();
  loadProducts();
}

function showAllCols() {
  document.querySelectorAll('.col-mgr-toggle').forEach(btn => {
    btn.className = 'col-mgr-toggle on'; btn.title = 'Скрий';
  });
}

function resetColSettings() {
  if (!confirm('Нулиране — всички колони ще се върнат към началния ред и ширини?')) return;
  localStorage.removeItem(LS_KEY);
  closeColMgr();
  loadProducts();
}

// ═══════════════════════════════════════════════════════════
//  SELECTION + DELETE
// ═══════════════════════════════════════════════════════════
function toggleRowSelection(el){ const ean = el.dataset.ean; if(!ean) return; if(el.checked) STATE.selected[ean]=true; else delete STATE.selected[ean]; STATE.selectAllDb=false; const b=document.getElementById('sel-all-db-btn'); if(b) b.textContent='Всички в базата'; syncHeaderCheckbox(); updateDeleteBtn(); updateExportButtons(); }
function toggleHeaderSelect(checked){ document.querySelectorAll('.row-sel').forEach(cb=>{ cb.checked=checked; const ean=cb.dataset.ean; if(checked) STATE.selected[ean]=true; else delete STATE.selected[ean]; }); STATE.selectAllDb=false; const b=document.getElementById('sel-all-db-btn'); if(b) b.textContent='Всички в базата'; updateDeleteBtn(); updateExportButtons(); }
function syncHeaderCheckbox(){ const top=document.getElementById('sel-page-top'); if(!top) return; const boxes=[...document.querySelectorAll('.row-sel')]; const checked=boxes.filter(b=>b.checked).length; top.checked=boxes.length>0 && checked===boxes.length; top.indeterminate=checked>0 && checked<boxes.length; }
function selectAllPage(){ toggleHeaderSelect(true); }
function toggleSelectAllDb(){ STATE.selectAllDb=!STATE.selectAllDb; if(STATE.selectAllDb){ STATE.selected={}; document.querySelectorAll('.row-sel').forEach(cb=>cb.checked=false); } const b=document.getElementById('sel-all-db-btn'); if(b) b.textContent = STATE.selectAllDb ? 'Отмени всички в базата' : 'Всички в базата'; syncHeaderCheckbox(); updateDeleteBtn(); updateExportButtons(); }
function updateDeleteBtn(){ const btn=document.getElementById('delete-btn'); if(!btn) return; const count=Object.keys(STATE.selected).length; btn.disabled = !(STATE.selectAllDb || count>0); btn.textContent = STATE.selectAllDb ? 'Изтрий всички' : (count>0 ? `Изтрий (${count})` : 'Изтрий'); }
function deleteSelected(){ const all=STATE.selectAllDb; const eans=Object.keys(STATE.selected); if(!all && !eans.length){ toast('Избери продукти', true); return; } const msg = all ? 'Сигурен ли си, че искаш да изтриеш ВСИЧКИ продукти от базата?' : `Сигурен ли си, че искаш да изтриеш ${eans.length} продукта?`; if(!confirm(msg)) return; const fd=new FormData(); fd.append('scope', all ? 'all' : 'selected'); if(!all) eans.forEach(e=>fd.append('eans[]', e)); fetch('/products/delete',{method:'POST',headers: csrfHeaders(),body:fd}).then(r=>r.json()).then(d=>{ if(!d.success) throw new Error(d.error||'Грешка'); STATE.selected={}; STATE.selectAllDb=false; const b=document.getElementById('sel-all-db-btn'); if(b) b.textContent='Всички в базата'; updateDeleteBtn(); toast(`✓ Изтрити ${d.deleted}`, false); loadProducts(); }).catch(e=>toast('✗ '+e.message,true)); }

// ═══════════════════════════════════════════════════════════
//  NAVIGATION
// ═══════════════════════════════════════════════════════════
function goPage(p)          { STATE.page=p; loadProducts(); }
function sortBy(col)        { STATE.dir=(STATE.sort===col&&STATE.dir==='asc')?'desc':'asc'; STATE.sort=col; STATE.page=1; loadProducts(); }
function changePerPage(val) { STATE.perpage=parseInt(val); STATE.page=1; loadProducts(); }

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
  ['f-dost','f-brand','f-status'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('f-search').value = '';
  loadProducts();
}
function updateBrands(supplier) {
  const sel = document.getElementById('f-brand');
  const cur = sel.value;
  fetch('/products/brands?supplier=' + encodeURIComponent(supplier), {headers: csrfHeaders()})
    .then(r => r.json()).then(d => {
      if (!d.ok) return;
      sel.innerHTML = '<option value="">— Всички —</option>';
      d.brands.forEach(b => { const o = document.createElement('option'); o.value = b; o.textContent = b; if (b===cur) o.selected=true; sel.appendChild(o); });
      applyFilters();
    }).catch(() => applyFilters());
}

// ═══════════════════════════════════════════════════════════
//  CELL EDITING
// ═══════════════════════════════════════════════════════════
function editCell(td) {
  if (td.querySelector('input')) return;
  const ean=td.dataset.ean, field=td.dataset.field;
  const orig=td.textContent.trim(), origV=orig.replace(/\s/g,'').replace(',','.');
  td.innerHTML='';
  const inp=document.createElement('input');
  inp.type='text'; inp.value=origV; inp.className='ci';
  td.appendChild(inp); inp.focus(); inp.select();
  let saved=false;
  function commit() {
    if(saved)return; saved=true;
    const nv=inp.value.trim(), nf=parseFloat(nv.replace(',','.'));
    td.textContent=(!isNaN(nf)&&nv!=='')?nf.toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2}):nv;
    if(nv!==origV&&ean&&field)saveCell(ean,field,nv.replace(',','.'));
  }
  inp.addEventListener('blur',commit);
  inp.addEventListener('keydown',e=>{
    if(e.key==='Enter')inp.blur();
    if(e.key==='Escape'){saved=true;inp.removeEventListener('blur',commit);td.textContent=orig;}
    if(e.key==='Tab'){e.preventDefault();inp.blur();const cs=[...td.closest('tr').querySelectorAll('td.ed')];const nx=cs[cs.indexOf(td)+1];if(nx)nx.click();}
  });
}
function saveCell(ean,field,value) {
  const fd=new FormData(); fd.append('ean',ean); fd.append('field',field); fd.append('value',value);
  fetch('/products/update',{method:'POST',headers: csrfHeaders(),body:fd}).then(r=>r.json())
    .then(d=>toast(d.success?'✓ Запазено':'✗ '+(d.error||'Грешка'),!d.success))
    .catch(()=>toast('✗ Мрежова грешка',true));
}
function toggleElek(el) {
  const next=el.dataset.val==='Yes'?'No':'Yes';
  el.textContent=next; el.dataset.val=next;
  el.className='elek '+(next==='Yes'?'y':'n');
  saveCell(el.dataset.ean,'Електоника',next);
}

// ═══════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════
function fmtNum(n){return n.toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2});}
function escH(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
let _tt;
function toast(msg,isErr=false){const t=document.getElementById('toast');t.textContent=msg;t.style.background=isErr?'var(--red)':'var(--green)';t.style.display='block';clearTimeout(_tt);_tt=setTimeout(()=>t.style.display='none',2200);}
document.addEventListener('keydown',e=>{
  if((e.ctrlKey||e.metaKey)&&e.key==='f'){e.preventDefault();document.getElementById('f-search').focus();}
  if(e.key==='Escape')closeColMgr();
});

function addCustomColumn(){ const name = prompt('Име на новата колона'); if(!name) return; const fd = new FormData(); fd.append('name', name.trim()); fetch('/settings/add-column',{method:'POST',headers:{'X-CSRF-Token': CSRF_TOKEN},body:fd}).then(r=>r.json()).then(d=>{ if(!d.ok && !d.success) throw new Error(d.error||'Грешка'); location.reload(); }).catch(e=>alert(e.message)); }

// ── INIT ──────────────────────────────────────────────────
loadProducts();
</script>
