<?php
$activeTab = 'system';
include __DIR__ . '/_tabs.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">

  <!-- SMTP Test -->
  <div class="card">
    <div class="card-title">Тест на имейл (SMTP)</div>
    <p style="font-size:12px;color:rgba(255,255,255,0.5);margin-bottom:16px;line-height:1.6">
      Изпрати тестов имейл за да потвърдиш конфигурацията на Gmail.<br>
      Ако не получиш имейл — провери <code>SMTP_PASS</code> в <code>.env</code>.
    </p>
    <div class="form-group">
      <label class="form-label">Изпрати до</label>
      <input type="email" id="test-email-to" class="form-control"
             placeholder="you@example.com"
             value="<?= htmlspecialchars(Auth::user() ?? '') ?>">
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" class="btn btn-primary" onclick="sendTestEmail()">
        <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><path d="M3 10l14-7-4 14-4-5-6-2z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" fill="none"/></svg>
        Изпрати тест
      </button>
      <span id="test-email-status" style="font-size:12px;display:none"></span>
    </div>

    <!-- SMTP config display -->
    <div style="margin-top:16px;padding:12px 14px;background:var(--bg3);border-radius:6px;border:1px solid var(--border)">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,0.4);margin-bottom:8px">Текуща конфигурация</div>
      <div style="font-size:12px;display:flex;flex-direction:column;gap:4px;font-family:monospace;color:rgba(255,255,255,0.6)">
        <div><span style="color:rgba(255,255,255,0.3)">HOST: </span><?= defined('SMTP_HOST') ? htmlspecialchars(SMTP_HOST) : '—' ?></div>
        <div><span style="color:rgba(255,255,255,0.3)">PORT: </span><?= defined('SMTP_PORT') ? htmlspecialchars(SMTP_PORT) : '—' ?></div>
        <div><span style="color:rgba(255,255,255,0.3)">USER: </span><?= defined('SMTP_USER') ? htmlspecialchars(SMTP_USER) : '—' ?></div>
        <div><span style="color:rgba(255,255,255,0.3)">PASS: </span>
          <?php
          if (defined('SMTP_PASS') && SMTP_PASS && SMTP_PASS !== 'your_16char_app_password_here') {
            echo '<span style="color:var(--green)">✓ Конфигуриран (' . strlen(SMTP_PASS) . ' chars)</span>';
          } else {
            echo '<span style="color:var(--red)">✗ Не е конфигуриран</span>';
          }
          ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-title">Смяна на парола</div>
    <p style="font-size:12px;color:rgba(255,255,255,0.5);margin-bottom:16px;line-height:1.6">
      Смени паролата на текущия администраторски акаунт.
    </p>
    <div class="form-group">
      <label class="form-label">Текуща парола</label>
      <input type="password" id="pw-current" class="form-control" placeholder="••••••••">
    </div>
    <div class="form-group">
      <label class="form-label">Нова парола</label>
      <input type="password" id="pw-new" class="form-control" placeholder="минимум 8 символа">
    </div>
    <div class="form-group">
      <label class="form-label">Повтори новата парола</label>
      <input type="password" id="pw-confirm" class="form-control" placeholder="••••••••">
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" class="btn btn-primary" onclick="changePassword()">
        <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><rect x="4" y="9" width="12" height="9" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M7 9V6a3 3 0 0 1 6 0v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        Смени парола
      </button>
      <span id="pw-status" style="font-size:12px;display:none"></span>
    </div>
  </div>

</div>

<!-- System Info -->
<div class="card mt-16">
  <div class="card-title">Системна информация</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px">
    <?php
    $info = [
      'Версия платформа' => defined('VERSION') ? VERSION : '1.4.0',
      'PHP версия'       => phpversion(),
      'Сървър'           => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
      'Продукти (брой)'  => number_format(count(DataStore::getProducts())),
      'Data директория'  => is_writable(DATA_DIR) ? '✓ Записваема' : '✗ Само четене',
      'Cache директория' => is_writable(CACHE_DIR) ? '✓ Записваема' : '✗ Само четене',
      'Logs директория'  => is_writable(LOGS_DIR) ? '✓ Записваема' : '✗ Само четене',
      'Текуща дата/час'  => date('d.m.Y H:i:s'),
    ];
    foreach ($info as $k => $v):
    ?>
    <div style="background:var(--bg3);border-radius:6px;padding:10px 14px;border:1px solid var(--border)">
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,0.35);margin-bottom:4px"><?= $k ?></div>
      <div style="font-size:13px;font-weight:600;color:#fff"><?= htmlspecialchars((string)$v) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
function sendTestEmail() {
  const to  = document.getElementById('test-email-to').value.trim();
  const st  = document.getElementById('test-email-status');
  if (!to) { st.textContent='Въведи имейл'; st.style.color='var(--red)'; st.style.display='inline'; return; }

  st.textContent='Изпращане…'; st.style.color='rgba(255,255,255,0.5)'; st.style.display='inline';

  const fd = new FormData(); fd.append('to', to);
  fetch('/api/test-email', { method:'POST', body:fd })
    .then(r=>r.json()).then(d=>{
      if (d.success) { st.textContent='✓ '+(d.message||'Изпратен!'); st.style.color='var(--green)'; }
      else           { st.textContent='✗ '+(d.error||'Грешка');       st.style.color='var(--red)';   }
    }).catch(()=>{ st.textContent='✗ Мрежова грешка'; st.style.color='var(--red)'; });
}

function changePassword() {
  const cur  = document.getElementById('pw-current').value;
  const nw   = document.getElementById('pw-new').value;
  const conf = document.getElementById('pw-confirm').value;
  const st   = document.getElementById('pw-status');

  if (!cur || !nw || !conf) { st.textContent='Попълни всички полета'; st.style.color='var(--red)'; st.style.display='inline'; return; }
  if (nw.length < 8) { st.textContent='Минимум 8 символа'; st.style.color='var(--red)'; st.style.display='inline'; return; }
  if (nw !== conf)   { st.textContent='Паролите не съвпадат'; st.style.color='var(--red)'; st.style.display='inline'; return; }

  st.textContent='Обновяване…'; st.style.color='rgba(255,255,255,0.5)'; st.style.display='inline';

  fetch('/api/change-password', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ current_password: cur, new_password: nw, confirm_password: conf })
  }).then(r=>r.json()).then(d=>{
    if (d.success) {
      st.textContent='✓ Паролата е сменена'; st.style.color='var(--green)';
      ['pw-current','pw-new','pw-confirm'].forEach(id=>document.getElementById(id).value='');
    } else {
      st.textContent='✗ '+(d.error||'Грешка'); st.style.color='var(--red)';
    }
  }).catch(()=>{ st.textContent='✗ Грешка'; st.style.color='var(--red)'; });
}
</script>
