<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>AMZ Retail — Забравена парола</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;background:#0A0A0F;font-family:'DM Sans',sans-serif;color:#F0EDE6;display:flex;align-items:center;justify-content:center;padding:20px}
.box{width:100%;max-width:400px;background:#1A1E2A;border:1px solid rgba(201,168,76,0.25);border-radius:8px;padding:40px 44px}
.logo{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;margin-bottom:32px}
.logo span{color:#C9A84C}
h1{font-family:'Syne',sans-serif;font-size:20px;font-weight:700;margin-bottom:8px}
.sub{font-size:13px;color:rgba(240,237,230,0.45);margin-bottom:28px;line-height:1.6}
.field label{display:block;font-size:11px;font-weight:500;letter-spacing:0.1em;text-transform:uppercase;color:rgba(240,237,230,0.45);margin-bottom:7px}
.field input{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(201,168,76,0.2);border-radius:4px;padding:13px 14px;font-size:14px;color:#F0EDE6;outline:none}
.field input:focus{border-color:#C9A84C}
.btn{width:100%;background:#C9A84C;color:#0A0A0F;border:none;border-radius:4px;padding:14px;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;cursor:pointer;margin-top:18px}
.btn:hover{background:#E8C97A}
.success{background:rgba(61,187,127,0.1);border:1px solid rgba(61,187,127,0.3);border-radius:4px;padding:14px 16px;font-size:13px;color:#5DCCA0;margin-bottom:20px;line-height:1.6}
.error{background:rgba(224,92,92,0.12);border:1px solid rgba(224,92,92,0.3);border-radius:4px;padding:11px 14px;font-size:13px;color:#F5A0A0;margin-bottom:18px}
.back{font-size:13px;color:rgba(240,237,230,0.4);text-decoration:none;display:block;text-align:center;margin-top:20px}
.back:hover{color:#C9A84C}
</style>
</head>
<body>
<div class="box">
  <div class="logo">AMZ<span>Retail</span></div>
  <h1>Забравена парола</h1>
  <?php if ($sent): ?>
    <div class="success">
      Ако имейлът съществува в системата, ще получите линк за нулиране в рамките на няколко минути.<br><br>
      Проверете и папката Spam/Junk.
    </div>
  <?php else: ?>
    <p class="sub">Въведете имейла си и ще получите линк за нулиране на паролата.</p>
    <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="/forgot-password">
      <?= View::csrfField() ?>
      <div class="field">
        <label>Имейл адрес</label>
        <input type="email" name="email" placeholder="you@example.com" required autofocus>
      </div>
      <button type="submit" class="btn">Изпрати линк →</button>
    </form>
  <?php endif; ?>
  <a href="/" class="back">← Към вход</a>
</div>
</body>
</html>
