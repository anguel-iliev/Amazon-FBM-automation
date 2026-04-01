<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>AMZ Retail — Активиране на акаунт</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--gold:#C9A84C;--gold-lt:#E8C97A;--dark:#0A0A0F;--panel:#1A1E2A;--border:rgba(201,168,76,0.25);--text:#F0EDE6;--muted:rgba(240,237,230,0.45);--red:#E05C5C}
body{min-height:100vh;background:var(--dark);font-family:'DM Sans',sans-serif;color:var(--text);display:flex;align-items:center;justify-content:center;padding:20px}
.box{width:100%;max-width:420px;background:var(--panel);border:1px solid var(--border);border-radius:8px;padding:40px 44px}
.logo{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--text);margin-bottom:32px}
.logo span{color:var(--gold)}
h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;margin-bottom:8px}
.sub{font-size:13px;color:var(--muted);margin-bottom:32px}
.email-badge{background:rgba(201,168,76,0.1);border:1px solid var(--border);border-radius:4px;padding:10px 14px;font-size:13px;color:var(--gold);margin-bottom:24px}
.field{margin-bottom:18px}
.field label{display:block;font-size:11px;font-weight:500;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:7px}
.field input{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(201,168,76,0.2);border-radius:4px;padding:13px 14px;font-family:'DM Sans',sans-serif;font-size:14px;color:var(--text);outline:none;transition:border-color 0.2s}
.field input:focus{border-color:var(--gold)}
.hint{font-size:11px;color:var(--muted);margin-top:5px}
.error{background:rgba(224,92,92,0.12);border:1px solid rgba(224,92,92,0.3);border-radius:4px;padding:11px 14px;font-size:13px;color:#F5A0A0;margin-bottom:18px}
.btn{width:100%;background:var(--gold);color:#0A0A0F;border:none;border-radius:4px;padding:14px;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;cursor:pointer;transition:background 0.2s;margin-top:8px}
.btn:hover{background:var(--gold-lt)}
.invalid-box{text-align:center;padding:20px 0}
.invalid-box p{font-size:14px;color:var(--muted);margin-bottom:20px}
.back-link{font-size:13px;color:var(--muted);text-decoration:none;display:block;text-align:center;margin-top:16px}
.back-link:hover{color:var(--gold)}
</style>
</head>
<body>
<div class="box">
  <div class="logo">AMZ<span>Retail</span></div>
  <?php if (empty($token) || !empty($error) && empty($email)): ?>
    <div class="invalid-box">
      <h1>Невалиден линк</h1>
      <p><?= htmlspecialchars($error ?? 'Линкът е невалиден или е изтекъл.') ?></p>
    </div>
  <?php else: ?>
    <h1>Активиране на акаунт</h1>
    <p class="sub">Задайте парола за вашия акаунт</p>
    <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="email-badge"><?= htmlspecialchars($email) ?></div>
    <form method="POST" action="/register">
      <?= View::csrfField() ?>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="field">
        <label>Нова парола</label>
        <input type="password" name="password" placeholder="Минимум 8 символа" required minlength="8" autocomplete="new-password">
        <div class="hint">Поне 8 символа</div>
      </div>
      <div class="field">
        <label>Потвърди паролата</label>
        <input type="password" name="confirm" placeholder="Повтори паролата" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn">Активирай акаунта &rarr;</button>
    </form>
  <?php endif; ?>
  <a href="/" class="back-link">← Към вход</a>
</div>
</body>
</html>
