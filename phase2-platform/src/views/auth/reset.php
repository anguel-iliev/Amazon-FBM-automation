<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>AMZ Retail — Нова парола</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;background:#0A0A0F;font-family:'DM Sans',sans-serif;color:#F0EDE6;display:flex;align-items:center;justify-content:center;padding:20px}
.box{width:100%;max-width:400px;background:#1A1E2A;border:1px solid rgba(201,168,76,0.25);border-radius:8px;padding:40px 44px}
.logo{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;margin-bottom:32px}.logo span{color:#C9A84C}
h1{font-family:'Syne',sans-serif;font-size:20px;font-weight:700;margin-bottom:8px}
.sub{font-size:13px;color:rgba(240,237,230,0.45);margin-bottom:28px}
.field{margin-bottom:16px}.field label{display:block;font-size:11px;font-weight:500;letter-spacing:0.1em;text-transform:uppercase;color:rgba(240,237,230,0.45);margin-bottom:7px}
.field input{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(201,168,76,0.2);border-radius:4px;padding:13px 14px;font-size:14px;color:#F0EDE6;outline:none}
.field input:focus{border-color:#C9A84C}
.btn{width:100%;background:#C9A84C;color:#0A0A0F;border:none;border-radius:4px;padding:14px;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;cursor:pointer;margin-top:8px}
.btn:hover{background:#E8C97A}
.error{background:rgba(224,92,92,0.12);border:1px solid rgba(224,92,92,0.3);border-radius:4px;padding:11px 14px;font-size:13px;color:#F5A0A0;margin-bottom:18px}
.invalid{text-align:center;padding:10px 0}
.invalid p{font-size:14px;color:rgba(240,237,230,0.5);margin-bottom:20px}
.back{font-size:13px;color:rgba(240,237,230,0.4);text-decoration:none;display:block;text-align:center;margin-top:20px}
.back:hover{color:#C9A84C}
</style>
</head>
<body>
<div class="box">
  <div class="logo">AMZ<span>Retail</span></div>
  <?php if (!$valid): ?>
    <div class="invalid">
      <h1>Линкът е изтекъл</h1>
      <p>Линкът за нулиране на паролата е невалиден или е изтекъл (24 часа).</p>
      <a href="/forgot-password" style="background:#C9A84C;color:#0A0A0F;padding:12px 24px;border-radius:4px;text-decoration:none;font-weight:700;font-size:13px;display:inline-block">Заяви нов линк</a>
    </div>
  <?php else: ?>
    <h1>Нова парола</h1>
    <p class="sub">Въведете новата си парола</p>
    <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="/reset-password">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="field">
        <label>Нова парола</label>
        <input type="password" name="password" placeholder="Минимум 8 символа" required minlength="8" autofocus>
      </div>
      <div class="field">
        <label>Потвърди паролата</label>
        <input type="password" name="confirm" placeholder="Повтори паролата" required>
      </div>
      <button type="submit" class="btn">Запази паролата →</button>
    </form>
  <?php endif; ?>
  <a href="/" class="back">← Към вход</a>
</div>
</body>
</html>
