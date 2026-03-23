<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AMZ Retail — Първоначална настройка</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --gold:#C9A84C; --gold-lt:#E8C97A; --dark:#0A0A0F;
  --panel:#1A1E2A; --border:rgba(201,168,76,0.25);
  --text:#F0EDE6; --muted:rgba(240,237,230,0.5);
  --red:#E05C5C; --green:#3DBB7F; --r:6px;
}
html,body{width:100%;min-height:100vh;overflow-x:hidden;font-family:'DM Sans',sans-serif;background:var(--dark);color:var(--text)}
body{display:flex;align-items:center;justify-content:center;padding:24px}

.wrap{width:100%;max-width:460px}

/* Logo */
.logo{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;text-align:center;margin-bottom:4px}
.logo span{color:var(--gold)}
.tagline{text-align:center;font-size:11px;letter-spacing:.15em;color:var(--muted);text-transform:uppercase;margin-bottom:32px}

/* Badge */
.first-run-badge{display:flex;align-items:center;gap:10px;background:rgba(61,187,127,.08);border:1px solid rgba(61,187,127,.3);border-radius:var(--r);padding:12px 16px;margin-bottom:24px}
.badge-icon{font-size:22px;flex-shrink:0}
.badge-text{font-size:13px;color:rgba(240,237,230,.8);line-height:1.6}
.badge-text strong{color:#6DDCA0;display:block;font-size:14px;margin-bottom:2px}

/* Card */
.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--r);padding:32px}
.card-title{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:6px}
.card-sub{font-size:13px;color:var(--muted);line-height:1.65;margin-bottom:26px}

/* Error */
.error-box{background:rgba(224,92,92,.1);border:1px solid rgba(224,92,92,.3);border-radius:4px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#F5A0A0}

/* Fields */
.field{margin-bottom:18px}
.field label{display:block;font-size:11px;font-weight:500;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:7px}
.field input{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(201,168,76,.2);border-radius:4px;padding:13px 15px;font-size:15px;color:var(--text);font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s,background .2s}
.field input:focus{border-color:var(--gold);background:rgba(201,168,76,.05)}
.field input::placeholder{color:rgba(240,237,230,.2)}
.field .hint{font-size:12px;color:var(--muted);margin-top:5px;line-height:1.55}

/* Password toggle */
.pass-wrap{position:relative}
.pass-wrap input{padding-right:48px}
.pass-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:0;line-height:1;transition:color .15s}
.pass-toggle:hover{color:var(--text)}

/* Submit */
.btn-setup{width:100%;padding:15px;background:var(--gold);color:var(--dark);border:none;border-radius:4px;font-family:'Syne',sans-serif;font-size:14px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;transition:background .2s;margin-top:6px}
.btn-setup:hover{background:var(--gold-lt)}

/* Strength bar */
.strength-bar{height:3px;border-radius:2px;background:rgba(255,255,255,.08);margin-top:8px;overflow:hidden}
.strength-fill{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s}

/* Footer note */
.foot-note{text-align:center;font-size:11px;color:var(--muted);margin-top:18px;line-height:1.7}
.foot-note a{color:var(--gold);text-decoration:none}
</style>
</head>
<body>
<div class="wrap">

  <div class="logo">AMZ<span>Retail</span></div>
  <div class="tagline">TN Soft Platform — Първоначална настройка</div>

  <div class="first-run-badge">
    <div class="badge-icon">🚀</div>
    <div class="badge-text">
      <strong>Добре дошъл!</strong>
      Няма регистрирани потребители. Създай администраторски акаунт за вход в платформата.
    </div>
  </div>

  <div class="card">
    <div class="card-title">Създай Admin акаунт</div>
    <div class="card-sub">Само за теб — имейлът и паролата ще се запаметят трайно в системата.</div>

    <?php if (!empty($error)): ?>
    <div class="error-box">✗ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/setup" autocomplete="off">

      <div class="field">
        <label>Имейл адрес</label>
        <input
          type="email"
          name="email"
          required
          autofocus
          placeholder="admin@example.com"
          value="<?= htmlspecialchars($email ?? '') ?>"
          autocomplete="username"
        >
        <div class="hint">С този имейл ще влизаш в платформата.</div>
      </div>

      <div class="field">
        <label>Парола</label>
        <div class="pass-wrap">
          <input
            type="password"
            name="password"
            id="p1"
            required
            placeholder="Поне 8 символа"
            minlength="8"
            autocomplete="new-password"
            oninput="checkStrength(this.value)"
          >
          <button type="button" class="pass-toggle" onclick="togglePass('p1',this)" tabindex="-1">👁</button>
        </div>
        <div class="strength-bar"><div class="strength-fill" id="sbar"></div></div>
        <div class="hint">Минимум 8 символа. Препоръчва се комбинация от букви, цифри и символи.</div>
      </div>

      <div class="field">
        <label>Повтори паролата</label>
        <div class="pass-wrap">
          <input
            type="password"
            name="confirm"
            id="p2"
            required
            placeholder="Въведи паролата отново"
            minlength="8"
            autocomplete="new-password"
          >
          <button type="button" class="pass-toggle" onclick="togglePass('p2',this)" tabindex="-1">👁</button>
        </div>
      </div>

      <button type="submit" class="btn-setup">Създай акаунт и влез →</button>

    </form>
  </div>

  <div class="foot-note">
    Ако имаш нужда от помощ → провери <a href="/install.php" target="_blank">install.php</a>
    или <a href="/reset-admin.php" target="_blank">reset-admin.php</a>
  </div>

</div>

<script>
function togglePass(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}

function checkStrength(val) {
  const bar = document.getElementById('sbar');
  let score = 0;
  if (val.length >= 8)  score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const pct = [0, 20, 40, 65, 85, 100][score];
  const colors = ['', '#E05C5C', '#E8A84C', '#C9A84C', '#7AC95B', '#3DBB7F'];
  bar.style.width = pct + '%';
  bar.style.background = colors[score] || 'transparent';
}
</script>
</body>
</html>
