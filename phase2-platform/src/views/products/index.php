<?php
/**
 * Products — AJAX Grid  v2.0.0
 * Shell page: renders stats bar + filter bar + empty table skeleton.
 * Product rows are loaded via JS fetch to /api/products-grid
 * so the initial HTML is small (~8 KB) and works on the PHP dev server.
 */
$filters  = $filters  ?? [];
$total    = $total    ?? 0;
$perPage  = $perPage  ?? 50;
$page     = $page     ?? 1;
$suppliers = $suppliers ?? [];
$brands    = $brands    ?? [];

// Pass filter state to JS as JSON
$filtersJson  = htmlspecialchars(json_encode($filters, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
$suppliersJson = htmlspecialchars(json_encode($suppliers, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
$brandsJson    = htmlspecialchars(json_encode($brands, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
?>


<!-- ══ Products Layout ═══════════════════════════════════════════ -->
<div class="prod-layout">

<!-- ── Stats Bar (loaded inline from PHP, just 4 numbers) ── -->
<div class="prod-stats-bar" id="prod-stats-bar">
  <?php
  $cnt = DataStore::getProductCount();
  ?>
  <div class="prod-stat-card">
    <div class="psc-icon">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M2 5h16M2 10h16M2 15h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </div>
    <div class="psc-body">
      <div class="psc-value" id="stat-total"><?= number_format($cnt['total']) ?></div>
      <div class="psc-label">Намерени</div>
    </div>
  </div>
  <div class="prod-stat-card accent-green">
    <div class="psc-icon">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M2 10l5 5 9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <div class="psc-body">
      <div class="psc-value"><?= number_format($cnt['withAsin']) ?></div>
      <div class="psc-label">С ASIN</div>
    </div>
  </div>
  <div class="prod-stat-card accent-amber">
    <div class="psc-icon">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M10 6v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.8"/></svg>
    </div>
    <div class="psc-body">
      <div class="psc-value"><?= number_format($cnt['notUploaded']) ?></div>
      <div class="psc-label">За качване</div>
    </div>
  </div>
  <div class="prod-stat-card accent-blue">
    <div class="psc-icon">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M3 17v-1a5 5 0 015-5h4a5 5 0 015 5v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/></svg>
    </div>
    <div class="psc-body">
      <div class="psc-value"><?= number_format($cnt['suppliers']) ?></div>
      <div class="psc-label">Доставчици</div>
    </div>
  </div>
</div>

<!-- ── Filter Bar ── -->
<div class="pf-bar">
<div class="pf-bar-inner">
  <div class="pf-group">
    <label>Доставчик</label>
    <select id="f-dostavchik" style="min-width:130px">
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
    <select id="f-brand" style="min-width:110px">
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
    <select id="f-status" style="min-width:100px">
      <option value="">— Всички —</option>
      <option value="NOT_UPLOADED" <?= ($filters['upload_status'] ?? '') === 'NOT_UPLOADED' ? 'selected' : '' ?>>Не качен</option>
      <option value="UPLOADED"     <?= ($filters['upload_status'] ?? '') === 'UPLOADED'     ? 'selected' : '' ?>>Качен</option>
    </select>
  </div>

  <div class="pf-group">
    <label>Електроника</label>
    <select id="f-elek" style="min-width:90px">
      <option value="">— Всички —</option>
      <option value="Yes" <?= ($filters['elektronika'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
      <option value="No"  <?= ($filters['elektronika'] ?? '') === 'No'  ? 'selected' : '' ?>>No</option>
    </select>
  </div>

  <div class="pf-group pf-search">
    <label>Търсене</label>
    <input type="text" id="f-search"
           placeholder="Модел, Бранд, EAN, ASIN, SKU…"
           value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
  </div>

  <div class="pf-actions">
    <button type="button" class="btn btn-primary btn-sm" onclick="applyFilters()">
      <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="5" stroke="currentColor" stroke-width="1.8"/><path d="M15 15l-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      Филтрирай
    </button>
    <button type="button" class="btn btn-ghost btn-sm" onclick="clearFilters()">Изчисти</button>
  </div>
</div>
</div>

<!-- ── Action Bar ── -->
<div class="prod-actions">
  <div class="prod-count" id="prod-count">
    <span style="color:rgba(255,255,255,.25)">Зареждане…</span>
  </div>
  <div style="display:flex;gap:6px;align-items:center">
    <button type="button" class="btn btn-ghost btn-sm" onclick="exportCsv()">
      <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M4 14v2a1 1 0 001 1h10a1 1 0 001-1v-2M10 3v10M7 10l3 3 3-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
      CSV
    </button>
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
  <thead id="pg-thead">
    <tr id="pg-header-row">
      <!-- headers injected by JS -->
    </tr>
  </thead>
  <tbody id="pg-tbody">
    <tr>
      <td colspan="32" style="padding:0;border:none">
        <div class="grid-loading">
          <div class="spin"></div>
          Зареждане на продуктите…
        </div>
      </td>
    </tr>
  </tbody>
</table>
</div>

<!-- ── Pagination ── -->
<div class="pg-pager" id="pg-pager">
  <div class="pg-info" id="pg-info">…</div>
  <div class="pg-pages" id="pg-pages"></div>
  <div class="pg-perpage">
    На страница:
    <select id="pg-perpage-sel" onchange="changePerPage(this.value)">
      <option value="25">25</option>
      <option value="50" selected>50</option>
      <option value="100">100</option>
      <option value="250">250</option>
    </select>
  </div>
</div>
</div><!-- /.pg-wrap -->
</div><!-- /.pg-outer -->

</div><!-- /.prod-layout -->

<!-- Save toast -->
<div class="save-indicator" id="save-toast">✓ Запазено</div>
