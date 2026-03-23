<?php
/**
 * AMZ Retail — Reset / Create Admin Account
 * =============================================
 * Използвай САМО ако не можеш да влезеш в платформата!
 * Отвори: https://amz-retail.tnsoft.eu/reset-admin.php
 * ИЗТРИЙ ТОЗИ ФАЙЛ веднага след употреба!
 */

define('ROOT', __DIR__);
define('DATA_DIR', ROOT . '/data');

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

$usersFile  = DATA_DIR . '/users.json';
$errors     = [];
$success    = '';
$step       = (int)($_GET['step'] ?? 1);

// ── Security token (simple protection) ───────────────────────
// The form requires a confirmation phrase to prevent accidental use
$CONFIRM_PHRASE = 'RESET ADMIN';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email   = strtolower(trim($_POST['email']   ?? ''));
    $pass    = trim($_POST['password']  ?? '');
    $confirm = trim($_POST['confirm']   ?? '');
    $phrase  = strtoupper(trim($_POST['phrase']  ?? ''));

    if ($phrase !== $CONFIRM_PHRASE) {
        $errors[] = 'Трябва да въведеш точната фраза за потвърждение: <strong>' . $CONFIRM_PHRASE . '</strong>';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Невалиден имейл адрес.';
    }
    if (strlen($pass) < 8) {
        $errors[] = 'Паролата трябва да е поне 8 символа.';
    }
    if ($pass !== $confirm) {
        $errors[] = 'Паролите не съвпадат.';
    }

    if (empty($errors)) {
        // Load existing users, preserve non-admin ones
        $existing = [];
        if (file_exists($usersFile)) {
            $decoded = json_decode(file_get_contents($usersFile), true);
            if (is_array($decoded)) {
                // Keep other users but remove any with same email or old admin
                foreach ($decoded as $u) {
                    if (strtolower($u['email'] ?? '') !== $email) {
                        $existing[] = $u;
                    }
                }
            }
        }

        // Create new admin
        $admin = [
            'id'             => bin2hex(random_bytes(8)),
            'email'          => $email,
            'password_hash'  => password_hash($pass, PASSWORD_BCRYPT),
            'verified'       => true,
            'invited'        => false,
            'verify_token'   => '',
            'verify_expires' => 0,
            'reset_token'    => '',
            'reset_expires'  => 0,
            'invited_by'     => 'reset-admin.php',
            'created_at'     => date('c'),
            'last_login'     => null,
            'role'           => 'admin',
        ];

        array_unshift($existing, $admin); // put admin first
        $saved = file_put_contents(
            $usersFile,
            json_encode(array_values($existing), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        if ($saved !== false) {
            $success = $email;
        } else {
            $errors[] = 'Грешка при запис на файла! Провери правата на папка <code>data/</code> (трябва да е 755).';
        }
    }
}

// ── Check current state ───────────────────────────────────────
$hasUsers = false;
$adminList = [];
if (file_exists($usersFile)) {
    $all = json_decode(file_get_contents($usersFile), true);
    if (is_array($all) && count($all) > 0) {
        $hasUsers = true;
        foreach ($all as $u) {
            if (($u['role'] ?? '') === 'admin') {
                $adminList[] = $u['email'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AMZ Retail — Нулиране на Admin</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--gold:#C9A84C;--gold-lt:#E8C97A;--dark:#0A0A0F;--panel:#1A1E2A;--border:rgba(201,168,76,0.25);--text:#F0EDE6;--muted:rgba(240,237,230,0.5);--red:#E05C5C;--green:#3DBB7F}
body{min-height:100vh;background:var(--dark);color:var(--text);font-family:system-ui,-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;padding:24px}
.wrap{width:100%;max-width:520px}
.logo{font-size:22px;font-weight:800;color:var(--text);text-align:center;margin-bottom:4px;letter-spacing:.02em}
.logo span{color:var(--gold)}
.tagline{text-align:center;font-size:11px;letter-spacing:.15em;color:var(--muted);text-transform:uppercase;margin-bottom:28px}
.card{background:var(--panel);border:1px solid var(--border);border-radius:8px;padding:28px 32px}
.card-title{font-size:17px;font-weight:700;margin-bottom:6px}
.card-sub{font-size:13px;color:var(--muted);line-height:1.65;margin-bottom:22px}
.warn-box{background:rgba(224,92,92,.09);border:1px solid rgba(224,92,92,.3);border-radius:6px;padding:14px 16px;margin-bottom:20px;font-size:13px;color:#F5A0A0;line-height:1.65}
.warn-box strong{color:#FF9090}
.info-box{background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.25);border-radius:6px;padding:14px 16px;margin-bottom:20px;font-size:13px;color:rgba(240,237,230,.75);line-height:1.65}
.ok-box{background:rgba(61,187,127,.1);border:1px solid rgba(61,187,127,.35);border-radius:6px;padding:18px 20px;margin-bottom:20px;font-size:14px;color:#6DDCA0;line-height:1.75;text-align:center}
.ok-box strong{font-size:18px;display:block;margin-bottom:6px}
.errors{background:rgba(224,92,92,.1);border:1px solid rgba(224,92,92,.3);border-radius:6px;padding:14px 16px;margin-bottom:20px}
.errors p{font-size:13px;color:#F5A0A0;margin-bottom:4px}
.errors p:last-child{margin:0}
.field{margin-bottom:16px}
.field label{display:block;font-size:11px;font-weight:500;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:6px}
.field input{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(201,168,76,.2);border-radius:4px;padding:12px 14px;font-size:14px;color:var(--text);outline:none;transition:border-color .2s}
.field input:focus{border-color:var(--gold)}
.field input::placeholder{color:rgba(240,237,230,.2)}
.field .hint{font-size:12px;color:var(--muted);margin-top:5px}
.field input.phrase-input{border-color:rgba(224,92,92,.4);background:rgba(224,92,92,.04)}
.field input.phrase-input:focus{border-color:#E05C5C}
.pass-wrap{position:relative}
.pass-wrap input{padding-right:46px}
.pass-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;line-height:1}
.btn{width:100%;padding:13px;border:none;border-radius:4px;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;transition:background .2s;margin-top:6px}
.btn-danger{background:#C0392B;color:#fff}
.btn-danger:hover{background:#E05C5C}
.btn-go{display:block;background:var(--gold);color:var(--dark);padding:15px;border-radius:4px;font-weight:800;font-size:15px;text-align:center;text-decoration:none;margin-top:20px;transition:background .2s}
.btn-go:hover{background:var(--gold-lt)}
.delete-warning{margin-top:18px;padding:12px 16px;background:rgba(224,92,92,.07);border:1px solid rgba(224,92,92,.2);border-radius:4px;font-size:12px;color:rgba(245,160,160,.8);line-height:1.7}
code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:3px;font-size:12px}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">AMZ<span>Retail</span></div>
  <div class="tagline">Emergency Admin Reset</div>

<?php if (!empty($success)): ?>
  <!-- SUCCESS -->
  <div class="card">
    <div class="ok-box">
      <strong>✓ Admin акаунтът е създаден!</strong>
      Имейл: <strong><?= htmlspecialchars($success) ?></strong><br>
      Сега можеш да влезеш в платформата.
    </div>
    <a href="/" class="btn-go">Влез в платформата →</a>
    <div class="delete-warning">
      🗑 <strong>Изтрий веднага</strong> файла <code>reset-admin.php</code> от File Manager!<br>
      Или го преименувай на <code>reset-admin.php.bak</code>.<br>
      Ако го оставиш активен, всеки може да презапише admin паролата!
    </div>
  </div>

<?php else: ?>
  <!-- FORM -->
  <div class="card">
    <div class="card-title">🔐 Нулиране на Admin парола</div>
    <div class="card-sub">Ако не можеш да влезеш, попълни полетата по-долу, за да създадеш нов Admin акаунт.</div>

    <?php if ($hasUsers): ?>
    <div class="warn-box">
      ⚠️ <strong>Внимание!</strong> Намерени са съществуващи потребители.<br>
      <?php if (!empty($adminList)): ?>
        Текущ Admin: <strong><?= htmlspecialchars(implode(', ', $adminList)) ?></strong><br>
      <?php endif; ?>
      Ако въведеш нов имейл — ще бъде добавен нов Admin. Ако въведеш <em>същия имейл</em> — паролата ще бъде нулирана.
    </div>
    <?php else: ?>
    <div class="info-box">
      ℹ️ Не са намерени потребители — ще бъде създаден първият Admin акаунт.
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="errors">
      <?php foreach ($errors as $e): ?>
      <p>✗ <?= $e ?></p>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>Admin имейл</label>
        <input type="email" name="email" required autofocus
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="admin@example.com">
        <div class="hint">Имейлът, с който ще влизаш в платформата.</div>
      </div>

      <div class="field">
        <label>Нова парола</label>
        <div class="pass-wrap">
          <input type="password" name="password" id="p1" required placeholder="Поне 8 символа" minlength="8">
          <button type="button" class="pass-toggle" onclick="tp('p1',this)">👁</button>
        </div>
      </div>

      <div class="field">
        <label>Повтори паролата</label>
        <div class="pass-wrap">
          <input type="password" name="confirm" id="p2" required placeholder="Повтори паролата" minlength="8">
          <button type="button" class="pass-toggle" onclick="tp('p2',this)">👁</button>
        </div>
      </div>

      <div class="field">
        <label>Потвърждение — напиши точно: <strong style="color:#E05C5C">RESET ADMIN</strong></label>
        <input type="text" name="phrase" class="phrase-input" required
               placeholder="RESET ADMIN" autocomplete="off"
               value="<?= htmlspecialchars($_POST['phrase'] ?? '') ?>">
        <div class="hint">Предпазна мярка срещу случайно нулиране.</div>
      </div>

      <button type="submit" class="btn btn-danger">Създай / нулирай Admin акаунт</button>
    </form>
  </div>
<?php endif; ?>

</div>
<script>
function tp(id, btn) {
  const i = document.getElementById(id);
  i.type = i.type === 'password' ? 'text' : 'password';
  btn.textContent = i.type === 'password' ? '👁' : '🙈';
}
</script>
</body>
</html>
