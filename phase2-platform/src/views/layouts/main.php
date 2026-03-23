<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — AMZ Retail</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<div class="layout">

  <!-- ══ Left Sidebar ══════════════════════════════════════════════ -->
  <nav class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">AMZ<span>Retail</span></div>
      <div class="sidebar-tagline">TN Soft Platform</div>
    </div>

    <ul class="nav-menu">
      <li class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <a href="/dashboard" class="nav-link">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><rect x="2" y="2" width="7" height="7" rx="1.5" fill="currentColor" opacity=".9"/><rect x="11" y="2" width="7" height="7" rx="1.5" fill="currentColor" opacity=".6"/><rect x="2" y="11" width="7" height="7" rx="1.5" fill="currentColor" opacity=".6"/><rect x="11" y="11" width="7" height="7" rx="1.5" fill="currentColor" opacity=".4"/></svg>
          <span>Dashboard</span>
        </a>
      </li>
      <li class="nav-item <?= ($activePage ?? '') === 'products' ? 'active' : '' ?>">
        <a href="/products" class="nav-link">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><path d="M2 5h16M2 10h16M2 15h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          <span>Продукти</span>
          <?php if (!empty($stats['not_uploaded'])): ?>
          <span class="nav-badge"><?= $stats['not_uploaded'] ?></span>
          <?php endif; ?>
        </a>
      </li>
      <li class="nav-item <?= ($activePage ?? '') === 'suppliers' ? 'active' : '' ?>">
        <a href="/suppliers" class="nav-link">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><path d="M3 17v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/></svg>
          <span>Доставчици</span>
        </a>
      </li>
      <li class="nav-item <?= ($activePage ?? '') === 'sync' ? 'active' : '' ?>">
        <a href="/sync" class="nav-link">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 0 1 6-6 6 6 0 0 1 4.24 1.76M16 10a6 6 0 0 1-6 6 6 6 0 0 1-4.24-1.76" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14.24 4.76 16 3v3.5h-3.5M5.76 15.24 4 17v-3.5h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Синхронизация</span>
        </a>
      </li>
      <li class="nav-item <?= ($activePage ?? '') === 'pricing' ? 'active' : '' ?>">
        <a href="/pricing" class="nav-link">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.8"/><path d="M10 6v8M7.5 8.5c0-1.1.9-2 2.5-2s2.5.9 2.5 2-2.5 2-2.5 2-2.5.9-2.5 2 .9 2 2.5 2 2.5-.9 2.5-2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          <span>Ценообразуване</span>
        </a>
      </li>
      <li class="nav-divider"></li>
      <!-- Settings with submenu -->
      <li class="nav-item <?= in_array($activePage ?? '', ['settings','settings-vat','settings-prices','settings-integrations','settings-system','settings-formulas']) ? 'active open' : '' ?>" id="nav-settings">
        <button class="nav-link" onclick="toggleNav('nav-settings')">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.22 4.22l1.42 1.42M14.36 14.36l1.42 1.42M4.22 15.78l1.42-1.42M14.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          <span>Настройки</span>
          <svg class="nav-arrow" viewBox="0 0 20 20" fill="none"><path d="M7 9l3 3 3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <ul class="nav-submenu">
          <li class="<?= ($activePage ?? '') === 'settings-vat' ? 'active' : '' ?>">
            <a href="/settings/vat">ДДС по пазари</a>
          </li>
          <li class="<?= ($activePage ?? '') === 'settings-prices' ? 'active' : '' ?>">
            <a href="/settings/prices">Редакция Цени</a>
          </li>
          <li class="<?= ($activePage ?? '') === 'settings-formulas' ? 'active' : '' ?>">
            <a href="/settings/formulas">Формули</a>
          </li>
          <li class="<?= ($activePage ?? '') === 'settings-integrations' ? 'active' : '' ?>">
            <a href="/settings/integrations">Интеграции</a>
          </li>
          <li class="<?= ($activePage ?? '') === 'settings-system' ? 'active' : '' ?>">
            <a href="/settings/system">Системни</a>
          </li>
        </ul>
      </li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar"><?= strtoupper(substr(Auth::user() ?? 'A', 0, 1)) ?></div>
        <div class="user-details">
          <div class="user-name"><?= htmlspecialchars(Auth::user() ?? '') ?></div>
          <div class="user-role"><?= Auth::isAdmin() ? 'Administrator' : 'User' ?></div>
        </div>
      </div>
      <?php if (Auth::isAdmin()): ?>
      <a href="/invite" title="Покани потребител" style="padding:6px;color:rgba(255,255,255,0.4);display:flex;align-items:center;border-radius:4px;transition:color .15s,background .15s" onmouseover="this.style.color='#fff';this.style.background='rgba(255,255,255,0.06)'" onmouseout="this.style.color='rgba(255,255,255,0.4)';this.style.background=''">
        <svg width="15" height="15" viewBox="0 0 20 20" fill="none"><path d="M14 11c2.2 0 4 1.8 4 4v1M1 16v-1c0-2.2 1.8-4 4-4M10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM16 8v4M18 10h-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
      </a>
      <?php endif; ?>
      <a href="/logout" class="btn-logout" title="Изход">
        <svg viewBox="0 0 20 20" fill="none" width="16" height="16"><path d="M13 10H3M10 7l3 3-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 5H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </a>
    </div>
  </nav>

  <!-- ══ Main content area ══════════════════════════════════════════ -->
  <div class="main-with-panel">

    <!-- ── Center main ── -->
    <main class="main">
      <div class="topbar">
        <div class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
        <div class="topbar-right">
          <?php $flash = Session::getFlash('success'); if ($flash): ?>
          <div class="flash flash-success"><?= htmlspecialchars($flash) ?></div>
          <?php endif; ?>
          <?php $flash = Session::getFlash('error'); if ($flash): ?>
          <div class="flash flash-error"><?= htmlspecialchars($flash) ?></div>
          <?php endif; ?>
          <div class="topbar-time" id="clock"></div>
        </div>
      </div>

      <div class="page-content <?= ($activePage ?? '') === 'products' ? 'no-pad' : '' ?>">
        <?= $content ?? '' ?>
      </div>
    </main>

    <!-- ══ Right Sidebar Panel ══════════════════════════════════════ -->
    <aside class="right-panel">
      <?php
      // Load only lightweight stats — no full product list
      require_once SRC . '/lib/DataStore.php';
      $rpCounts  = DataStore::getProductCount();
      $rpSyncLog = array_slice(DataStore::getSyncLog(), 0, 4);
      $rpLastSync = !empty($rpSyncLog[0]['date']) ? $rpSyncLog[0]['date'] : null;
      ?>

      <!-- Quick Stats -->
      <div class="rp-section">
        <div class="rp-title">Статистика</div>
        <div class="rp-stat-row">
          <div class="rp-stat">
            <div class="rp-stat-val"><?= number_format($rpCounts['total']) ?></div>
            <div class="rp-stat-lbl">Продукти</div>
          </div>
          <div class="rp-stat accent-green">
            <div class="rp-stat-val"><?= number_format($rpCounts['withAsin']) ?></div>
            <div class="rp-stat-lbl">С ASIN</div>
          </div>
        </div>
        <div class="rp-stat-row" style="margin-top:6px">
          <div class="rp-stat accent-amber">
            <div class="rp-stat-val"><?= number_format($rpCounts['notUploaded']) ?></div>
            <div class="rp-stat-lbl">За качване</div>
          </div>
          <div class="rp-stat accent-blue">
            <div class="rp-stat-val"><?= number_format($rpCounts['suppliers']) ?></div>
            <div class="rp-stat-lbl">Доставчици</div>
          </div>
        </div>
      </div>

      <!-- Last sync -->
      <div class="rp-section">
        <div class="rp-title">Последна синхр.</div>
        <div class="rp-sync-time">
          <?= $rpLastSync ? date('d.m.Y H:i', strtotime($rpLastSync)) : '—' ?>
        </div>
        <?php if (!empty($rpSyncLog)): ?>
        <div style="margin-top:8px;display:flex;flex-direction:column;gap:4px">
          <?php foreach ($rpSyncLog as $log): ?>
          <div class="rp-log-row">
            <span style="font-size:10px;color:rgba(255,255,255,.35)"><?= date('d.m H:i', strtotime($log['date'])) ?></span>
            <span class="<?= ($log['status'] ?? '') === 'success' ? 'b-green' : 'b-red' ?>" style="font-size:10px;padding:1px 6px">
              <?= ($log['status'] ?? '') === 'success' ? 'OK' : 'Err' ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Quick Actions -->
      <div class="rp-section">
        <div class="rp-title">Бързи действия</div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <a href="/products" class="rp-action-btn">
            <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M2 5h16M2 10h16M2 15h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            Всички продукти
          </a>
          <a href="/products?upload_status=NOT_UPLOADED" class="rp-action-btn rp-action-amber">
            <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M10 6v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.8"/></svg>
            За качване (<?= $rpCounts['notUploaded'] ?>)
          </a>
          <a href="/sync" class="rp-action-btn rp-action-green">
            <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 0 1 6-6 6 6 0 0 1 4.24 1.76M16 10a6 6 0 0 1-6 6 6 6 0 0 1-4.24-1.76" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14.24 4.76 16 3v3.5h-3.5M5.76 15.24 4 17v-3.5h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Синхронизация
          </a>
          <a href="/pricing" class="rp-action-btn">
            <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.8"/><path d="M10 6v8M7.5 8.5c0-1.1.9-2 2.5-2s2.5.9 2.5 2-2.5 2-2.5 2-2.5.9-2.5 2 .9 2 2.5 2 2.5-.9 2.5-2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            Ценообразуване
          </a>
        </div>
      </div>

      <!-- Recent products (AJAX loaded to keep initial page small) -->
      <div class="rp-section" id="rp-recent-section">
        <div class="rp-title">Последни продукти</div>
        <div id="rp-recent-list" style="display:flex;flex-direction:column;gap:6px">
          <div style="font-size:11px;color:rgba(255,255,255,.25)">…</div>
        </div>
      </div>

    </aside>

  </div><!-- /.main-with-panel -->

</div><!-- /.layout -->

<script src="/assets/js/app.js"></script>
<script>
function toggleNav(id) {
  const el = document.getElementById(id);
  if (el) el.classList.toggle('open');
}

// Load recent products into right panel asynchronously
(function loadRpRecent() {
  const list = document.getElementById('rp-recent-list');
  if (!list) return;
  fetch('/api/products-grid?page=1&perpage=5')
    .then(r => r.ok ? r.json() : null)
    .then(data => {
      if (!data || !data.products || !data.products.length) {
        list.innerHTML = '<div style="font-size:11px;color:rgba(255,255,255,.25)">Няма продукти</div>';
        return;
      }
      list.innerHTML = data.products.map(p => {
        const name   = (p['\u041c\u043e\u0434\u0435\u043b'] || '—').substring(0, 28);
        const supp   = p['\u0414\u043e\u0441\u0442\u0430\u0432\u0447\u0438\u043a'] || '—';
        const status = p['_upload_status'] || 'NOT_UPLOADED';
        const badge  = status === 'UPLOADED'
          ? '<span class="b-green" style="font-size:10px;padding:1px 5px">✓</span>'
          : '<span class="b-gold"  style="font-size:10px;padding:1px 5px">⊙</span>';
        return `<div class="rp-product-row">
          <div class="rp-product-name" title="${escRp(p['\u041c\u043e\u0434\u0435\u043b']||'')}">${escRp(name)}</div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:2px">
            <span style="font-size:10px;color:rgba(255,255,255,.3)">${escRp(supp)}</span>
            ${badge}
          </div>
        </div>`;
      }).join('');
    })
    .catch(() => { list.innerHTML = ''; });
  function escRp(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
})();
</script>
</body>
</html>
