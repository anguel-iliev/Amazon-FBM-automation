<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — AMZ Retail</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<div class="layout">

  <!-- Sidebar -->
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
      <li class="nav-item <?= ($activePage ?? '') === 'settings' ? 'active' : '' ?>">
        <a href="/settings" class="nav-link">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.22 4.22l1.42 1.42M14.36 14.36l1.42 1.42M4.22 15.78l1.42-1.42M14.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          <span>Настройки</span>
        </a>
      </li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar"><?= strtoupper(substr(Auth::user() ?? 'A', 0, 1)) ?></div>
        <div class="user-details">
          <div class="user-name"><?= htmlspecialchars(Auth::user() ?? '') ?></div>
          <div class="user-role">Administrator</div>
        </div>
      </div>
      <a href="/logout" class="btn-logout" title="Изход">
        <svg viewBox="0 0 20 20" fill="none" width="16" height="16"><path d="M13 10H3M10 7l3 3-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 5H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </a>
    </div>
  </nav>

  <!-- Main content -->
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

    <div class="page-content">
      <?= $content ?? '' ?>
    </div>
  </main>

</div>

<script src="/assets/js/app.js"></script>
</body>
</html>
