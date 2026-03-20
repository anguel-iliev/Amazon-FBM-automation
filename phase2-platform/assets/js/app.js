// AMZ Retail — Main JS

// Clock
function updateClock() {
  const el = document.getElementById('clock');
  if (!el) return;
  const now = new Date();
  el.textContent = now.toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
setInterval(updateClock, 1000);
updateClock();

// Auto-hide flash messages
document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => el.style.opacity = '0', 3500);
  setTimeout(() => el.remove(), 4000);
  el.style.transition = 'opacity 0.5s';
});

// Confirm dialogs
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});

// AJAX helper
async function api(url, data = null) {
  const opts = { headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
  if (data) { opts.method = 'POST'; opts.body = JSON.stringify(data); }
  const res = await fetch(url, opts);
  return res.json();
}

// Trigger sync via AJAX
function triggerSync() {
  const btn = document.getElementById('sync-btn');
  const status = document.getElementById('sync-status');
  if (!btn) return;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Синхронизиране...';
  if (status) status.textContent = 'Стартиране...';

  api('/api/sync', { action: 'start' })
    .then(data => {
      if (data.success) {
        if (status) status.textContent = 'Завършено успешно';
        btn.innerHTML = '✓ Готово';
        setTimeout(() => location.reload(), 1500);
      } else {
        if (status) status.textContent = 'Грешка: ' + (data.error || 'неизвестна');
        btn.disabled = false;
        btn.innerHTML = 'Стартирай синхронизация';
      }
    })
    .catch(() => {
      if (status) status.textContent = 'Мрежова грешка';
      btn.disabled = false;
      btn.innerHTML = 'Стартирай синхронизация';
    });
}
