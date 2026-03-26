<?php $activeTab = 'system'; include __DIR__ . '/_tabs.php'; ?>
<div class="grid-2" style="align-items:start">
  <div class="card">
    <div class="card-title">Тест на имейл (SMTP)</div>
    <div class="form-group">
      <label class="form-label">Изпрати до</label>
      <input type="email" id="test-email" class="form-control" value="<?= htmlspecialchars(env('SMTP_USER','')) ?>">
    </div>
    <button class="btn btn-ghost btn-sm" onclick="sendTestEmail(this)">✉ Изпрати тест</button>
    <div id="email-result" style="margin-top:10px;font-size:13px"></div>
  </div>

  <div class="card">
    <div class="card-title">Смяна на парола</div>
    <div class="form-group">
      <label class="form-label">Текуща парола</label>
      <input type="password" id="cur-pw" class="form-control">
    </div>
    <div class="form-group">
      <label class="form-label">Нова парола</label>
      <input type="password" id="new-pw" class="form-control" placeholder="минимум 8 символа">
    </div>
    <div class="form-group">
      <label class="form-label">Повтори новата парола</label>
      <input type="password" id="new-pw2" class="form-control">
    </div>
    <button class="btn btn-primary btn-sm" onclick="changePw(this)">🔒 Смени парола</button>
    <div id="pw-result" style="margin-top:10px;font-size:13px"></div>
  </div>
</div>

<div class="card mt-16">
  <div class="card-title">Системна информация</div>
  <div style="display:flex;flex-direction:column;gap:6px;font-size:13px">
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)">
      <span class="text-muted">PHP версия</span><span><?= PHP_VERSION ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)">
      <span class="text-muted">Платформа версия</span><span><?= VERSION ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)">
      <span class="text-muted">Memory limit</span><span><?= ini_get('memory_limit') ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;padding:6px 0">
      <span class="text-muted">SQLite кеш</span>
      <?php $st = ProductDB::status(); ?>
      <span style="color:<?= $st['exists']?'var(--green)':'var(--red)' ?>"><?= $st['exists']?'✅ Активен ('.$st['count'].' продукта)':'❌ Липсва' ?></span>
    </div>
  </div>
</div>

<script>
function sendTestEmail(btn) {
  const to=document.getElementById('test-email').value;
  if(!to){alert('Въведи имейл адрес');return;}
  btn.disabled=true; btn.textContent='Изпращане…';
  fetch('/api/test-email',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({to})})
    .then(r=>r.json()).then(d=>{
      document.getElementById('email-result').innerHTML=d.success
        ?'<span style="color:var(--green)">✓ Изпратен успешно</span>'
        :'<span style="color:var(--red)">✗ '+(d.error||'Грешка')+'</span>';
    }).finally(()=>{btn.disabled=false;btn.textContent='✉ Изпрати тест';});
}
function changePw(btn) {
  const cur=document.getElementById('cur-pw').value;
  const nw=document.getElementById('new-pw').value;
  const nw2=document.getElementById('new-pw2').value;
  if(nw!==nw2){document.getElementById('pw-result').innerHTML='<span style="color:var(--red)">✗ Паролите не съвпадат</span>';return;}
  if(nw.length<8){document.getElementById('pw-result').innerHTML='<span style="color:var(--red)">✗ Минимум 8 символа</span>';return;}
  btn.disabled=true;
  fetch('/api/change-password',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({current_password:cur,new_password:nw,confirm_password:nw2})})
    .then(r=>r.json()).then(d=>{
      document.getElementById('pw-result').innerHTML=d.success
        ?'<span style="color:var(--green)">✓ Паролата е сменена</span>'
        :'<span style="color:var(--red)">✗ '+(d.error||'Грешка')+'</span>';
    }).finally(()=>{btn.disabled=false;});
}
</script>
