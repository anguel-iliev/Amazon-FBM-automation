<?php
$s     = $settings ?? [];
$mkts  = $s['marketplaces'] ?? [];
$codes = ['DE','FR','IT','ES','NL','PL','SE'];
$flags = ['DE'=>'🇩🇪','FR'=>'🇫🇷','IT'=>'🇮🇹','ES'=>'🇪🇸','NL'=>'🇳🇱','PL'=>'🇵🇱','SE'=>'🇸🇪'];
?>

<form method="POST" action="/settings/save">
  <?= View::csrfField() ?>

  <div class="grid-2" style="align-items:start">

    <!-- Google API -->
    <div class="card">
      <div class="card-title">Google API</div>
      <div class="form-group">
        <label class="form-label">Google Sheet ID (Централна таблица)</label>
        <input type="text" name="google_sheet_id" class="form-control"
               placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms"
               value="<?= htmlspecialchars($s['google_sheet_id'] ?? '') ?>">
        <div class="text-sm text-muted mt-4">От URL-а на Google Sheet</div>
      </div>
      <div class="form-group">
        <label class="form-label">Google Drive Folder ID</label>
        <input type="text" name="drive_folder_id" class="form-control"
               placeholder="100T4KgyVIXhKlJczQv7DR9CJlV27DbUx"
               value="<?= htmlspecialchars($s['drive_folder_id'] ?? '') ?>">
        <div class="text-sm text-muted mt-4">ID на папката "Цени доставчици"</div>
      </div>
      <div style="padding:12px;background:var(--bg3);border-radius:4px;border:1px solid var(--border)">
        <div class="text-sm text-muted">
          Service Account файлът трябва да е в:<br>
          <code style="color:var(--gold);font-size:11px">src/config/google-credentials.json</code>
        </div>
      </div>
    </div>

    <!-- General -->
    <div class="card">
      <div class="card-title">Общи настройки</div>
      <div class="form-group">
        <label class="form-label">Минимален марж %</label>
        <input type="number" name="min_margin" class="form-control"
               value="<?= round(($s['min_margin'] ?? 0.15) * 100) ?>"
               min="0" max="100" step="1">
      </div>
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px 0;border-top:1px solid var(--border)">
        <input type="checkbox" name="sync_auto" <?= ($s['sync_auto'] ?? true) ? 'checked' : '' ?>>
        <div>
          <div class="text-sm">Автоматична синхронизация</div>
          <div class="text-sm text-muted">Стартира от cron job</div>
        </div>
      </label>
    </div>

  </div>

  <!-- Marketplace settings -->
  <div class="card mt-16">
    <div class="card-title">Настройки по пазари</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Пазар</th>
            <th>ДДС %</th>
            <th>Amazon %</th>
            <th>Доставка €</th>
            <th>FBM такса €</th>
            <th>Активен</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($codes as $code): ?>
          <?php $cfg = $mkts[$code] ?? []; ?>
          <tr>
            <td><strong><?= $flags[$code] ?> <?= $code ?></strong></td>
            <td>
              <input type="number" name="vat_<?= $code ?>" class="form-control"
                     style="width:70px" step="0.1"
                     value="<?= round(($cfg['vat'] ?? 0) * 100, 1) ?>">
            </td>
            <td>
              <input type="number" name="amazon_fee_<?= $code ?>" class="form-control"
                     style="width:70px" step="0.1"
                     value="<?= round(($cfg['amazon_fee'] ?? 0.15) * 100, 1) ?>">
            </td>
            <td>
              <input type="number" name="shipping_<?= $code ?>" class="form-control"
                     style="width:80px" step="0.1"
                     value="<?= number_format($cfg['shipping'] ?? 4.50, 2) ?>">
            </td>
            <td>
              <input type="number" name="fbm_fee_<?= $code ?>" class="form-control"
                     style="width:70px" step="0.1"
                     value="<?= number_format($cfg['fbm_fee'] ?? 1.00, 2) ?>">
            </td>
            <td>
              <input type="checkbox" name="active_<?= $code ?>"
                     <?= ($cfg['active'] ?? false) ? 'checked' : '' ?>>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-16" style="display:flex;gap:10px">
    <button type="submit" class="btn btn-primary">Запази настройките</button>
    <a href="/dashboard" class="btn btn-ghost">Отказ</a>
  </div>

</form>

<!-- SMTP test -->
<div class="card mt-16" style="border-color:rgba(201,168,76,0.2)">
  <div class="card-title">Тест на имейл (SMTP)</div>
  <div class="text-sm text-muted" style="margin-bottom:14px;line-height:1.7">
    Изпрати тестов имейл за да провериш дали Gmail App Password е верен.<br>
    Ако не получиш имейл — провери <code style="color:var(--gold)">SMTP_PASS</code> в <code style="color:var(--gold)">.env</code>.
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="email" id="test-email-to" class="form-control"
           style="max-width:260px" placeholder="you@example.com"
           value="<?= htmlspecialchars(Auth::user() ?? '') ?>">
    <button type="button" class="btn btn-ghost" onclick="sendTestEmail()">
      Изпрати тест →
    </button>
    <span id="test-email-status" class="text-sm" style="display:none"></span>
  </div>
</div>

<!-- Password change hint -->
<div class="card mt-16" style="border-color:rgba(201,168,76,0.2)">
  <div class="card-title">Смяна на парола</div>
  <div class="text-sm text-muted">
    За смяна на паролата — генерирай нов hash и обнови <code style="color:var(--gold)">.env</code> файла:<br><br>
    <code style="display:block;background:var(--bg3);padding:8px 12px;border-radius:4px;color:var(--gold-lt);font-size:12px;margin-top:6px">
      php -r "echo password_hash('новата_парола', PASSWORD_BCRYPT);"
    </code>
  </div>
</div>

<script>
function sendTestEmail() {
  const to  = document.getElementById('test-email-to').value.trim();
  const st  = document.getElementById('test-email-status');
  if (!to) { st.textContent = 'Въведи имейл адрес'; st.style.color='var(--red,#E05C5C)'; st.style.display='inline'; return; }

  st.textContent = 'Изпращане…';
  st.style.color = 'var(--muted)';
  st.style.display = 'inline';

  const fd = new FormData();
  fd.append('to', to);

  fetch('/api/test-email', { method:'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        st.textContent = '✓ ' + (d.message || 'Изпратен!');
        st.style.color = '#5DCCA0';
      } else {
        st.textContent = '✗ ' + (d.error || 'Грешка');
        st.style.color = '#E05C5C';
      }
    })
    .catch(() => { st.textContent = '✗ Мрежова грешка'; st.style.color='#E05C5C'; });
}
</script>
