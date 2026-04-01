<div class="grid-2" style="align-items:start">
  <div class="card">
    <div class="card-title">Firebase Realtime Database</div>
    <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
        <span class="text-muted">Project ID</span>
        <span><?= htmlspecialchars(env('FIREBASE_PROJECT_ID','—')) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
        <span class="text-muted">Database URL</span>
        <span style="font-size:11px"><?= htmlspecialchars(env('FIREBASE_DATABASE_URL','—')) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:8px 0">
        <span class="text-muted">Secret</span>
        <span><?= substr(env('FIREBASE_SECRET',''),0,6) ?>••••••</span>
      </div>
    </div>
    <div style="margin-top:16px">
      <button class="btn btn-ghost btn-sm" onclick="testFirebase(this)">Тествай връзката</button>
      <span id="fb-test-result" style="font-size:12px;margin-left:10px"></span>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Google Drive</div>
    <div style="font-size:13px">
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
        <span class="text-muted">Folder ID</span>
        <span style="font-size:11px"><?= htmlspecialchars(env('GOOGLE_DRIVE_FOLDER_ID','—')) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:8px 0">
        <span class="text-muted">Gmail</span>
        <span><?= htmlspecialchars(env('SMTP_USER','—')) ?></span>
      </div>
    </div>
    <p class="text-muted text-sm" style="margin-top:12px">Автоматичната синхронизация с Google Drive ще бъде добавена в следваща версия.</p>
  </div>
  <div class="card" style="margin-top:16px">
    <div class="card-title">Сигурна Firebase архитектура</div>
    <p class="text-sm text-muted" style="line-height:1.7">Фронтендът не трябва да достъпва Firebase директно. Всички заявки към Realtime Database минават през PHP backend-а на сървъра, който използва сървърни credentials. Задай строги Firebase rules и не оставяй глобален client read/write достъп.</p>
    <div style="margin-top:12px;font-size:12px;color:var(--muted)">Файл с примерни secure rules: <code>firebase-secure-rules.json</code></div>
  </div>
</div>
<script>
function testFirebase(btn) {
  const orig = btn.textContent; btn.disabled=true; btn.textContent='Тестване…';
  fetch('/api/test-firebase',{method:'POST'}).then(r=>r.json()).then(d=>{
    document.getElementById('fb-test-result').innerHTML = d.success
      ? '<span style="color:var(--green)">✓ Връзката работи</span>'
      : '<span style="color:var(--red)">✗ ' + (d.error||'Грешка') + '</span>';
  }).catch(()=>{
    document.getElementById('fb-test-result').innerHTML='<span style="color:var(--red)">✗ Грешка</span>';
  }).finally(()=>{btn.disabled=false;btn.textContent=orig;});
}
</script>
