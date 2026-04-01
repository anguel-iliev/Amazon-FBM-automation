<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AMZ Retail — Вход</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --gold: #C9A84C; --gold-lt: #E8C97A; --dark: #0A0A0F;
  --panel: rgba(10,10,15,0.82); --border: rgba(201,168,76,0.25);
  --text: #F0EDE6; --muted: rgba(240,237,230,0.45);
  --red: #E05C5C; --radius: 4px;
}
html, body { width:100%;height:100%;overflow:hidden;font-family:'DM Sans',sans-serif;background:var(--dark);color:var(--text); }
.video-bg { position:fixed;inset:0;z-index:0;overflow:hidden; }
.video-bg video { width:100%;height:100%;object-fit:cover;opacity:0.55;filter:saturate(0.7) brightness(0.6); }
.video-overlay { position:fixed;inset:0;z-index:1;background:linear-gradient(135deg,rgba(10,10,15,0.75) 0%,rgba(10,10,15,0.45) 50%,rgba(10,10,15,0.75) 100%); }
.login-wrap { position:relative;z-index:10;min-height:100vh;display:grid;grid-template-columns:1fr 480px;align-items:center; }
.brand-area { padding:60px 80px;animation:fadeUp 0.8s ease both; }
.brand-logo { font-family:'Syne',sans-serif;font-size:13px;font-weight:700;letter-spacing:0.35em;color:var(--gold);text-transform:uppercase;margin-bottom:48px; }
.brand-headline { font-family:'Syne',sans-serif;font-size:clamp(32px,4vw,52px);font-weight:800;line-height:1.1;color:var(--text);margin-bottom:20px; }
.brand-headline span { color:var(--gold); }
.brand-sub { font-size:16px;color:var(--muted);line-height:1.7;max-width:420px; }
.brand-stats { display:flex;gap:40px;margin-top:56px; }
.stat { border-left:2px solid var(--gold);padding-left:16px; }
.stat-val { font-family:'Syne',sans-serif;font-size:28px;font-weight:700;color:var(--text);line-height:1; }
.stat-label { font-size:11px;letter-spacing:0.1em;color:var(--muted);text-transform:uppercase;margin-top:4px; }
.login-panel { height:100vh;display:flex;align-items:center;justify-content:center;padding:40px 56px;background:var(--panel);backdrop-filter:blur(20px);border-left:1px solid var(--border);animation:fadeIn 0.6s ease both 0.2s; }
.login-box { width:100%;max-width:360px; }
.login-title { font-family:'Syne',sans-serif;font-size:24px;font-weight:700;color:var(--text);margin-bottom:8px; }
.login-subtitle { font-size:13px;color:var(--muted);margin-bottom:40px; }
.field { margin-bottom:20px; }
.field label { display:block;font-size:11px;font-weight:500;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:8px; }
.field input { width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(201,168,76,0.2);border-radius:var(--radius);padding:14px 16px;font-family:'DM Sans',sans-serif;font-size:15px;color:var(--text);outline:none;transition:border-color 0.2s,background 0.2s;-webkit-appearance:none; }
.field input:focus { border-color:var(--gold);background:rgba(201,168,76,0.06); }
.field input::placeholder { color:rgba(240,237,230,0.2); }
.error-msg { background:rgba(224,92,92,0.12);border:1px solid rgba(224,92,92,0.35);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:#F5A0A0;margin-bottom:20px; }
.btn-login { width:100%;background:var(--gold);color:#0A0A0F;border:none;border-radius:var(--radius);padding:15px;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;cursor:pointer;transition:background 0.2s;margin-top:8px; }
.btn-login:hover { background:var(--gold-lt); }
.login-links { margin-top:20px;text-align:center; }
.login-links a { font-size:12px;color:var(--muted);text-decoration:none;transition:color 0.15s; }
.login-links a:hover { color:var(--gold); }
.login-footer { margin-top:40px;padding-top:24px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between; }
.login-footer span { font-size:11px;color:var(--muted); }
.version-badge { font-size:10px;letter-spacing:0.1em;color:var(--gold);background:rgba(201,168,76,0.1);border:1px solid var(--border);border-radius:20px;padding:3px 10px; }
@keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@media(max-width:860px){.login-wrap{grid-template-columns:1fr}.brand-area{display:none}.login-panel{height:100vh;border-left:none;padding:40px 24px}}
</style>
</head>
<body>
<div class="video-bg">
  <video autoplay muted loop playsinline>
    <source src="/assets/video/login-bg.mp4" type="video/mp4">
  </video>
</div>
<div class="video-overlay"></div>
<div class="login-wrap">
  <div class="brand-area">
    <div class="brand-logo">TN Soft &nbsp;/&nbsp; AMZ Retail</div>
    <h1 class="brand-headline">Amazon FBM<br><span>Automation</span><br>Platform</h1>
    <p class="brand-sub">Автоматизирано управление на доставчици, продукти и ценообразуване за Amazon FBM пазари в Европа.</p>
    <div class="brand-stats">
      <div class="stat"><div class="stat-val">7</div><div class="stat-label">Пазара</div></div>
      <div class="stat"><div class="stat-val"><?= (int)($supplierCount ?? 0) ?></div><div class="stat-label">Доставчика</div></div>
    </div>
  </div>
  <div class="login-panel">
    <div class="login-box">
      <h2 class="login-title">Добре дошли</h2>
      <p class="login-subtitle">Влезте с вашия акаунт</p>
      <?php if (!empty($error)): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php $success = Session::getFlash('success'); if ($success): ?>
      <div style="background:rgba(61,187,127,0.12);border:1px solid rgba(61,187,127,0.3);border-radius:4px;padding:12px 16px;font-size:13px;color:#5DCCA0;margin-bottom:20px"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <form method="POST" action="/" autocomplete="off">
        <?= View::csrfField() ?>
        <div class="field">
          <label>Имейл</label>
          <input type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
        </div>
        <div class="field">
          <label>Парола</label>
          <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn-login">Вход &rarr;</button>
      </form>
      <div class="login-links">
        <a href="/forgot-password">Забравена парола?</a>
      </div>
      <div class="login-footer">
        <span>&copy; <?= date('Y') ?> TN Soft</span>
        <span class="version-badge">v<?= VERSION ?></span>
      </div>
    </div>
  </div>
</div>
</body>
</html>
