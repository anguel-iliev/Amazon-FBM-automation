<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, private">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — AMZ Retail</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
<meta name="csrf-token" content="<?= htmlspecialchars(Security::csrfTokenForJs(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>

<div class="layout">
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
      <?php if (Auth::isAdmin()): ?>
      <li class="nav-item <?= ($activePage ?? '') === 'suppliers' ? 'active' : '' ?>">
        <a href="/suppliers" class="nav-link">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><path d="M3 17v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/></svg>
          <span>Доставчици</span>
        </a>
      </li>
      <?php endif; ?>
      <li class="nav-item <?= ($activePage ?? '') === 'pricing' ? 'active' : '' ?>">
        <a href="/vat" class="nav-link">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.8"/><path d="M10 6v8M7.5 8.5c0-1.1.9-2 2.5-2s2.5.9 2.5 2-2.5 2-2.5 2-2.5.9-2.5 2 .9 2 2.5 2 2.5-.9 2.5-2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          <span>ДДС</span>
        </a>
      </li>
      <?php if (Auth::isAdmin()): ?>
      <li class="nav-divider"></li>
      <li class="nav-item <?= in_array($activePage ?? '', ['settings','settings-vat','settings-prices','settings-sync','settings-integrations','settings-system','sync']) ? 'active open' : '' ?>" id="nav-settings">
        <button class="nav-link" onclick="toggleNav('nav-settings')">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.22 4.22l1.42 1.42M14.36 14.36l1.42 1.42M4.22 15.78l1.42-1.42M14.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          <span>Настройки</span>
          <svg class="nav-arrow" viewBox="0 0 20 20" fill="none"><path d="M7 9l3 3 3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <ul class="nav-submenu">
          <li class="<?= ($activePage ?? '') === 'settings-prices' ? 'active' : '' ?>"><a href="/settings/prices">Формули</a></li>
          <li class="<?= in_array(($activePage ?? ''), ['settings-sync','sync'], true) ? 'active' : '' ?>"><a href="/sync">Синхронизация</a></li>
          <li class="<?= ($activePage ?? '') === 'settings-integrations' ? 'active' : '' ?>"><a href="/settings/integrations">Интеграции</a></li>
          <li class="<?= ($activePage ?? '') === 'settings-system' ? 'active' : '' ?>"><a href="/settings/system">Системни</a></li>
        </ul>
      </li>
      <?php endif; ?>
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
      <form method="POST" action="/logout" style="margin:0">
        <?= View::csrfField() ?>
        <button type="submit" class="btn-logout" title="Изход" style="border:none;background:transparent;cursor:pointer">
          <svg viewBox="0 0 20 20" fill="none" width="16" height="16"><path d="M13 10H3M10 7l3 3-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 5H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </button>
      </form>
    </div>
  </nav>

  <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
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
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
function toggleNav(id) {
  const el = document.getElementById(id);
  if (el) el.classList.toggle('open');
}
window.addEventListener('pageshow', function (event) {
  if (event.persisted) window.location.reload();
});
</script>
</body>
</html>
