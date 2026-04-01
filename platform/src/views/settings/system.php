<div class="grid-2" style="align-items:start">
  <div class="card">
    <div class="card-title">Покани потребител</div>
    <form method="POST" action="/invite">
      <?= View::csrfField() ?>
      <div class="form-group">
        <label class="form-label">Имейл адрес</label>
        <input type="email" name="email" id="invite-email" class="form-control" placeholder="user@example.com" required>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Покани</button>
    </form>
  </div>

  <div class="card">
    <div class="card-title">Смяна на парола</div>
    <div class="form-group"><label class="form-label">Текуща парола</label><input type="password" id="cur-pw" class="form-control"></div>
    <div class="form-group"><label class="form-label">Нова парола</label><input type="password" id="new-pw" class="form-control" placeholder="минимум 8 символа"></div>
    <div class="form-group"><label class="form-label">Повтори новата парола</label><input type="password" id="new-pw2" class="form-control"></div>
    <button class="btn btn-primary btn-sm" onclick="changePw(this)">🔒 Смени парола</button>
    <div id="pw-result" style="margin-top:10px;font-size:13px"></div>
  </div>
</div>

<div class="card mt-16">
  <div class="card-title">Системна информация</div>
  <div style="display:flex;flex-direction:column;gap:6px;font-size:13px">
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)"><span class="text-muted">PHP версия</span><span><?= PHP_VERSION ?></span></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)"><span class="text-muted">Платформа версия</span><span><?= VERSION ?></span></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)"><span class="text-muted">Memory limit</span><span><?= ini_get('memory_limit') ?></span></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0"><span class="text-muted">SQLite кеш</span><?php $st = ProductDB::status(); ?><span style="color:<?= $st['exists']?'var(--green)':'var(--red)' ?>"><?= $st['exists']?'✅ Активен ('.$st['count'].' продукта)':'❌ Липсва' ?></span></div>
  </div>
</div>

<?php if (Auth::isAdmin()): ?>
<div class="card mt-16">
  <div class="card-title">Потребители</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Имейл</th>
          <th>Роля</th>
          <th>Статус</th>
          <th>Покана</th>
          <th>Създаден</th>
          <th>Последен вход</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($users ?? []) as $u): ?>
        <?php $isVerified = !empty($u['verified']); $inviteState = $isVerified ? 'Приета' : (!empty($u['verify_token']) ? 'Изпратена' : 'Няма'); ?>
        <tr>
          <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
          <td>
            <select class="form-control form-control-sm" style="min-width:110px" onchange="changeUserRole('<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>', this.value, this)" <?= (($u['email'] ?? '') === (Auth::user() ?? '')) ? 'disabled' : '' ?>>
              <option value="user" <?= (($u['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>user</option>
              <option value="admin" <?= (($u['role'] ?? 'user') === 'admin') ? 'selected' : '' ?>>admin</option>
            </select>
          </td>
          <td><span class="badge <?= $isVerified ? 'badge-green' : 'badge-muted' ?>"><?= $isVerified ? 'Активен' : 'Чака активация' ?></span></td>
          <td><?= htmlspecialchars($inviteState) ?></td>
          <td><?= !empty($u['created_at']) ? date('d.m.Y H:i', strtotime($u['created_at'])) : '—' ?></td>
          <td><?= !empty($u['last_login']) ? date('d.m.Y H:i', strtotime($u['last_login'])) : '—' ?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <?php if (!$isVerified): ?>
              <form method="POST" action="/invite/resend" style="margin:0"><?= View::csrfField() ?><input type="hidden" name="email" value="<?= htmlspecialchars($u['email'] ?? '') ?>"><button class="btn btn-ghost btn-sm" type="submit">Повтори</button></form>
              <?php endif; ?>
              <?php if (($u['email'] ?? '') !== (Auth::user() ?? '')): ?>
              <form method="POST" action="/invite/delete" style="margin:0" onsubmit="return confirm('Сигурни ли сте?')"><?= View::csrfField() ?><input type="hidden" name="email" value="<?= htmlspecialchars($u['email'] ?? '') ?>"><button class="btn btn-danger btn-sm" type="submit">Изтрий</button></form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
const SETTINGS_CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
function changePw(btn) {
  const cur=document.getElementById('cur-pw').value; const nw=document.getElementById('new-pw').value; const nw2=document.getElementById('new-pw2').value;
  if(nw!==nw2){document.getElementById('pw-result').innerHTML='<span style="color:var(--red)">✗ Паролите не съвпадат</span>';return;}
  if(nw.length<8){document.getElementById('pw-result').innerHTML='<span style="color:var(--red)">✗ Минимум 8 символа</span>';return;}
  btn.disabled=true;
  fetch('/api/change-password',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token': SETTINGS_CSRF_TOKEN},body:JSON.stringify({current_password:cur,new_password:nw,confirm_password:nw2})})
  .then(r=>r.json()).then(d=>{document.getElementById('pw-result').innerHTML=d.success?'<span style="color:var(--green)">✓ Паролата е сменена</span>':'<span style="color:var(--red)">✗ '+(d.error||'Грешка')+'</span>';})
  .finally(()=>{btn.disabled=false;});
}
function changeUserRole(email, role, el){
  const fd = new FormData(); fd.append('email', email); fd.append('role', role);
  fetch('/settings/change-user-role',{method:'POST',headers:{'X-CSRF-Token': SETTINGS_CSRF_TOKEN},body:fd})
    .then(r=>r.json())
    .then(d=>{ if(!d.success){ alert(d.error||'Грешка'); location.reload(); } })
    .catch(()=>{ alert('Мрежова грешка'); location.reload(); });
}
</script>
