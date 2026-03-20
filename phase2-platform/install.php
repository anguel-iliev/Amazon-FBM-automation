<?php
/**
 * AMZ Retail — Web Setup Wizard
 * Отвори https://amz-retail.tnsoft.eu/install.php
 * ИЗТРИЙ ТОЗИ ФАЙЛ след като завършиш setup-а!
 */

define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
define('TOKEN_EXPIRY', 86400);

// ── Security: block if admin already exists ──────────────────────
$usersFile = ROOT . '/data/users.json';
$alreadySetup = file_exists($usersFile) &&
    !empty(json_decode(file_get_contents($usersFile), true));

// ── Helpers ──────────────────────────────────────────────────────
function readEnvFile(): array {
    $file = ROOT . '/.env';
    // fallback to .env.example if .env not yet created
    if (!file_exists($file)) $file = ROOT . '/.env.example';
    if (!file_exists($file)) return [];
    $map = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') { $map[] = $line; continue; }
        if (!str_contains($line, '=')) { $map[] = $line; continue; }
        [$k, $v] = explode('=', $line, 2);
        $map[trim($k)] = trim($v);
    }
    return $map;
}
function writeEnvFile(array $map): void {
    $lines = [];
    foreach ($map as $k => $v) {
        $lines[] = is_int($k) ? $v : "$k=$v";
    }
    file_put_contents(ROOT . '/.env', implode("\n", $lines) . "\n", LOCK_EX);
}
function envVal(string $key, string $default = ''): string {
    $map = readEnvFile();
    return $map[$key] ?? $default;
}

// ── Handle POST ──────────────────────────────────────────────────
$step    = (int)($_GET['step'] ?? 1);
$errors  = [];
$success = '';

// STEP 1 POST: save .env
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    $smtpPass  = str_replace(' ', '', trim($_POST['smtp_pass'] ?? ''));
    $smtpUser  = trim($_POST['smtp_user'] ?? 'tnsoftsales@gmail.com');
    $appUrl    = rtrim(trim($_POST['app_url'] ?? 'https://amz-retail.tnsoft.eu'), '/');
    $driveId   = trim($_POST['drive_id'] ?? '1Wch88u5tZApf5UOXzeH9AO7TETXGKYnT');

    if (empty($smtpPass) || strlen($smtpPass) < 8) {
        // If field left empty and we already have a pass — keep old one
        $existing = envVal('SMTP_PASS','');
        if (!empty($existing) && $existing !== 'your_16char_app_password_here') {
            $smtpPass = $existing; // keep
        } else {
            $errors[] = 'Gmail App Password трябва да е поне 8 символа (обикновено 16).';
        }
    }
    if (empty($smtpUser) || !filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Въведи валиден Gmail адрес.';
    }

    if (empty($errors)) {
        // Create directories
        foreach (['data','data/logs','data/cache','data/cache/tmp_downloads'] as $d) {
            $p = ROOT . '/' . $d;
            if (!is_dir($p)) mkdir($p, 0755, true);
        }
        file_put_contents(ROOT . '/data/.htaccess', "Deny from all\n");

        // Write .env — load existing .env first, then .env.example as fallback
        $envPath = ROOT . '/.env';
        $example = ROOT . '/.env.example';
        if (file_exists($envPath)) {
            $map = [];
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') { $map[] = $line; continue; }
                if (!str_contains($line, '=')) { $map[] = $line; continue; }
                [$k, $v] = explode('=', $line, 2);
                $map[trim($k)] = trim($v);
            }
        } elseif (file_exists($example)) {
            $map = [];
            foreach (file($example, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') { $map[] = $line; continue; }
                if (!str_contains($line, '=')) { $map[] = $line; continue; }
                [$k, $v] = explode('=', $line, 2);
                $map[trim($k)] = trim($v);
            }
        } else {
            $map = [];
        }
        $map['APP_URL']               = $appUrl;
        $map['SMTP_USER']             = $smtpUser;
        $map['SMTP_PASS']             = $smtpPass;
        $map['SMTP_FROM']             = $smtpUser;
        $map['GOOGLE_DRIVE_FOLDER_ID']= $driveId;
        writeEnvFile($map);

        header('Location: install.php?step=2');
        exit;
    }
}

// STEP 2 POST: create admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Невалиден имейл адрес.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Паролата трябва да е поне 8 символа.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Паролите не съвпадат.';
    }

    if (empty($errors)) {
        // Need DATA_DIR for UserStore
        if (!is_dir(ROOT . '/data')) mkdir(ROOT . '/data', 0755, true);
        define('DATA_DIR', ROOT . '/data');
        require_once SRC . '/lib/UserStore.php';

        $existing = UserStore::findByEmail($email);
        if ($existing) {
            $errors[] = 'Потребител с този имейл вече съществува.';
        } else {
            $admin = [
                'id'             => bin2hex(random_bytes(8)),
                'email'          => $email,
                'password_hash'  => password_hash($password, PASSWORD_BCRYPT),
                'verified'       => true,
                'invited'        => false,
                'verify_token'   => '',
                'verify_expires' => 0,
                'reset_token'    => '',
                'reset_expires'  => 0,
                'invited_by'     => 'install.php',
                'created_at'     => date('c'),
                'last_login'     => null,
                'role'           => 'admin',
            ];
            $users   = UserStore::all();
            $users[] = $admin;
            file_put_contents(
                ROOT . '/data/users.json',
                json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
            header('Location: install.php?step=3');
            exit;
        }
    }
}

// ── Current .env values for pre-fill ─────────────────────────────
$curUrl   = envVal('APP_URL',   'https://amz-retail.tnsoft.eu');
$curUser  = envVal('SMTP_USER', 'tnsoftsales@gmail.com');
$curDrive = envVal('GOOGLE_DRIVE_FOLDER_ID', '1Wch88u5tZApf5UOXzeH9AO7TETXGKYnT');
$curPass  = envVal('SMTP_PASS', '');
$passSet  = !empty($curPass) && $curPass !== 'your_16char_app_password_here';
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AMZ Retail — Setup Wizard</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--gold:#C9A84C;--gold-lt:#E8C97A;--dark:#0A0A0F;--panel:#1A1E2A;--border:rgba(201,168,76,0.2);--text:#F0EDE6;--muted:rgba(240,237,230,0.5);--red:#E05C5C;--green:#3DBB7F;--r:6px}
body{min-height:100vh;background:var(--dark);color:var(--text);font-family:'DM Sans',sans-serif;display:flex;align-items:center;justify-content:center;padding:24px}
.wrap{width:100%;max-width:580px}
.logo{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--text);text-align:center;margin-bottom:6px}
.logo span{color:var(--gold)}
.tagline{text-align:center;font-size:12px;letter-spacing:.15em;color:var(--muted);text-transform:uppercase;margin-bottom:32px}
/* Steps */
.steps{display:flex;gap:0;margin-bottom:32px;border:1px solid var(--border);border-radius:var(--r);overflow:hidden}
.step-item{flex:1;padding:12px 8px;text-align:center;font-size:12px;color:var(--muted);background:rgba(255,255,255,.02);position:relative}
.step-item.active{background:rgba(201,168,76,.1);color:var(--gold);font-weight:600}
.step-item.done{background:rgba(61,187,127,.08);color:var(--green)}
.step-num{display:block;font-family:'Syne',sans-serif;font-size:18px;font-weight:800;margin-bottom:2px}
/* Card */
.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--r);padding:32px}
.card-title{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;color:var(--text);margin-bottom:6px}
.card-sub{font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:24px}
/* Form */
.field{margin-bottom:18px}
.field label{display:block;font-size:11px;font-weight:500;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:7px}
.field input{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(201,168,76,.2);border-radius:4px;padding:13px 15px;font-size:15px;color:var(--text);font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s}
.field input:focus{border-color:var(--gold);background:rgba(201,168,76,.05)}
.field input::placeholder{color:rgba(240,237,230,.2)}
.field .hint{font-size:12px;color:var(--muted);margin-top:6px;line-height:1.6}
.field .hint strong{color:var(--gold)}
/* Errors */
.errors{background:rgba(224,92,92,.1);border:1px solid rgba(224,92,92,.3);border-radius:4px;padding:14px 16px;margin-bottom:20px}
.errors p{font-size:13px;color:#F5A0A0;margin-bottom:4px}
.errors p:last-child{margin-bottom:0}
/* Buttons */
.btn{display:inline-block;padding:14px 28px;border:none;border-radius:4px;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;text-decoration:none;transition:background .2s}
.btn-primary{background:var(--gold);color:var(--dark);width:100%;text-align:center;margin-top:8px}
.btn-primary:hover{background:var(--gold-lt)}
/* Info box */
.info-box{background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.25);border-radius:var(--r);padding:16px 20px;margin-bottom:20px}
.info-box p{font-size:13px;color:rgba(240,237,230,.75);line-height:1.7;margin-bottom:4px}
.info-box p:last-child{margin:0}
.info-box ol{padding-left:18px}
.info-box ol li{font-size:13px;color:rgba(240,237,230,.75);line-height:1.9;margin-bottom:2px}
.info-box ol li strong{color:var(--gold-lt)}
/* Toggle password */
.pass-wrap{position:relative}
.pass-wrap input{padding-right:48px}
.pass-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:0;line-height:1}
/* Success step */
.success-icon{width:64px;height:64px;background:rgba(61,187,127,.12);border:2px solid rgba(61,187,127,.4);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:28px}
.success-card{text-align:center}
.big-link{display:block;background:var(--gold);color:var(--dark);padding:16px 32px;border-radius:4px;font-family:'Syne',sans-serif;font-weight:800;font-size:16px;letter-spacing:.05em;text-decoration:none;margin:24px 0 12px;transition:background .2s}
.big-link:hover{background:var(--gold-lt)}
.warning-box{background:rgba(224,92,92,.08);border:1px solid rgba(224,92,92,.25);border-radius:4px;padding:12px 16px;margin-top:16px;font-size:12px;color:rgba(245,160,160,.85);line-height:1.7}
.badge-set{display:inline-block;background:rgba(61,187,127,.15);border:1px solid rgba(61,187,127,.4);color:#5DCCA0;font-size:11px;padding:2px 10px;border-radius:20px;margin-left:6px}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">AMZ<span>Retail</span></div>
  <div class="tagline">TN Soft Platform — Setup Wizard</div>

  <!-- Steps indicator -->
  <div class="steps">
    <div class="step-item <?= $step===1?'active':($step>1?'done':'') ?>">
      <span class="step-num"><?= $step>1?'✓':'1' ?></span>Gmail & Drive
    </div>
    <div class="step-item <?= $step===2?'active':($step>2?'done':'') ?>">
      <span class="step-num"><?= $step>2?'✓':'2' ?></span>Admin акаунт
    </div>
    <div class="step-item <?= $step===3?'active':'' ?>">
      <span class="step-num">3</span>Готово
    </div>
  </div>

<?php if ($alreadySetup && $step < 3): ?>
  <!-- Already configured warning -->
  <div class="card">
    <div class="info-box" style="background:rgba(224,92,92,.08);border-color:rgba(224,92,92,.25);">
      <p>⚠️ <strong style="color:#F5A0A0">Платформата вече е настроена</strong> — намерен е admin потребител.</p>
      <p>Ако искаш да презапишеш настройките, изтрий <code style="color:#E05C5C">data/users.json</code> от File Manager и презареди тази страница.</p>
    </div>
    <a href="/" class="btn btn-primary" style="display:block;text-align:center;text-decoration:none">Към платформата →</a>
  </div>

<?php elseif ($step === 1): ?>
  <!-- ═══════════════════════════════ STEP 1: SMTP + Drive ═══ -->
  <div class="card">
    <div class="card-title">Стъпка 1 — Gmail и Google Drive</div>
    <div class="card-sub">Попълни данните по-долу. Ако не знаеш как да намериш App Password — прочети инструкцията вдясно от полето.</div>

    <?php if (!empty($errors)): ?>
    <div class="errors"><?php foreach ($errors as $e): ?><p>✗ <?= htmlspecialchars($e) ?></p><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="info-box">
      <p>📧 <strong>Как да намериш Gmail App Password:</strong></p>
      <ol>
        <li>Отвори <strong>myaccount.google.com</strong> в нов таб</li>
        <li>Кликни <strong>"Security & sign-in"</strong> от лявото меню</li>
        <li>Намери <strong>"2-Step Verification"</strong> — трябва да пише <em>On</em> до него</li>
        <li>Кликни върху <strong>"2-Step Verification"</strong></li>
        <li>Scroll-ни най-надолу докато намериш <strong>"App passwords"</strong></li>
        <li>В полето "App name" напиши <strong>AMZ Retail</strong> → кликни <strong>Create</strong></li>
        <li>Появява се прозорец с <strong>16 жълти букви/цифри</strong> — копирай ги</li>
        <li>Постави ги в полето по-долу (можеш да ги сложиш и с интервали — ще се изчистят автоматично)</li>
      </ol>
    </div>

    <form method="POST" action="install.php?step=1">

      <div class="field">
        <label>Gmail адрес (от който ще се изпращат покани)</label>
        <input type="email" name="smtp_user" value="<?= htmlspecialchars($curUser) ?>" required placeholder="tnsoftsales@gmail.com">
        <div class="hint">Това е Gmail акаунтът, в който ще генерираш App Password.</div>
      </div>

      <div class="field">
        <label>Gmail App Password <?= $passSet ? '<span class="badge-set">✓ вече зададена</span>' : '' ?></label>
        <div class="pass-wrap">
          <input type="password" name="smtp_pass" id="smtp_pass"
                 placeholder="<?= $passSet ? '(въведи нова само ако искаш да смениш)' : 'abcdabcdabcdabcd' ?>"
                 <?= $passSet ? '' : 'required' ?>>
          <button type="button" class="pass-toggle" onclick="togglePass('smtp_pass',this)" title="Покажи">👁</button>
        </div>
        <div class="hint">
          Изглежда така: <strong>abcd efgh ijkl mnop</strong> (16 символа, може с или без интервали).<br>
          <?= $passSet ? '⚠ Остави полето ПРАЗНО ако не искаш да сменяш текущата парола.' : '' ?>
        </div>
      </div>

      <div class="field">
        <label>URL на сайта</label>
        <input type="text" name="app_url" value="<?= htmlspecialchars($curUrl) ?>" required placeholder="https://amz-retail.tnsoft.eu">
        <div class="hint">Адресът на хостинга. Не слагай / накрая.</div>
      </div>

      <div class="field">
        <label>Google Drive Folder ID (папката с офертите)</label>
        <input type="text" name="drive_id" value="<?= htmlspecialchars($curDrive) ?>" required placeholder="1Wch88u5tZApf5UOXzeH9AO7TETXGKYnT">
        <div class="hint">
          ID-то от линка на Drive папката.<br>
          Пример: drive.google.com/drive/folders/<strong>1Wch88u5tZApf5UOXzeH9AO7TETXGKYnT</strong>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Запази и продължи →</button>
    </form>
  </div>

<?php elseif ($step === 2): ?>
  <!-- ═══════════════════════════════ STEP 2: Admin account ══ -->
  <div class="card">
    <div class="card-title">Стъпка 2 — Създай Admin акаунт</div>
    <div class="card-sub">Това е основният акаунт за влизане в платформата. Само ти ще го знаеш.</div>

    <?php if (!empty($errors)): ?>
    <div class="errors"><?php foreach ($errors as $e): ?><p>✗ <?= htmlspecialchars($e) ?></p><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="POST" action="install.php?step=2">

      <div class="field">
        <label>Имейл адрес (за влизане)</label>
        <input type="email" name="email" required autofocus placeholder="admin@example.com">
        <div class="hint">Може да е различен от Gmail адреса за изпращане.</div>
      </div>

      <div class="field">
        <label>Парола</label>
        <div class="pass-wrap">
          <input type="password" name="password" id="pass1" required placeholder="Поне 8 символа" minlength="8">
          <button type="button" class="pass-toggle" onclick="togglePass('pass1',this)" title="Покажи">👁</button>
        </div>
        <div class="hint">Използвай поне 8 символа. Препоръчва се комбинация от букви и цифри.</div>
      </div>

      <div class="field">
        <label>Повтори паролата</label>
        <div class="pass-wrap">
          <input type="password" name="confirm" id="pass2" required placeholder="Въведи паролата отново" minlength="8">
          <button type="button" class="pass-toggle" onclick="togglePass('pass2',this)" title="Покажи">👁</button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Създай Admin акаунт →</button>
    </form>
  </div>

<?php else: ?>
  <!-- ═══════════════════════════════ STEP 3: Done ══════════ -->
  <div class="card success-card">
    <div class="success-icon">✓</div>
    <div class="card-title">Платформата е готова!</div>
    <div class="card-sub">Admin акаунтът е създаден успешно. Можеш да влезеш веднага.</div>

    <a href="/" class="big-link">Влез в платформата →</a>

    <div class="warning-box">
      🗑 <strong>Важно:</strong> Изтрий файла <code>install.php</code> от File Manager след като влезеш!<br>
      Или просто го преименувай на <code>install.php.bak</code>.<br>
      Ако го оставиш, някой може да презапише настройките.
    </div>

    <div class="info-box" style="margin-top:20px;text-align:left">
      <p>📧 <strong>Следваща стъпка — покани нови потребители:</strong></p>
      <ol>
        <li>Влез в платформата</li>
        <li>В лявото меню → <strong>Покани потребител</strong></li>
        <li>Въведи имейл адреса → <strong>Изпрати покана</strong></li>
        <li>Потребителят получава имейл с линк и сам задава парола</li>
      </ol>
    </div>
  </div>
<?php endif; ?>

</div>

<script>
function togglePass(id, btn) {
  const inp = document.getElementById(id);
  if (inp.type === 'password') {
    inp.type = 'text';
    btn.textContent = '🙈';
  } else {
    inp.type = 'password';
    btn.textContent = '👁';
  }
}
// Auto-strip spaces from smtp_pass on submit
document.querySelector('form')?.addEventListener('submit', function() {
  const f = document.getElementById('smtp_pass');
  if (f) f.value = f.value.replace(/\s/g, '');
});
</script>
</body>
</html>
