// AMZ Retail — Main JS

function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
}

const nativeFetch = window.fetch.bind(window);
window.fetch = function(input, init = {}) {
  const request = new Request(input, init);
  const url = new URL(request.url, window.location.origin);
  if (url.origin === window.location.origin) {
    const headers = new Headers(init.headers || request.headers || {});
    headers.set('X-Requested-With', 'XMLHttpRequest');
    const method = (init.method || request.method || 'GET').toUpperCase();
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
      const csrf = getCsrfToken();
      if (csrf) headers.set('X-CSRF-Token', csrf);
    }
    init.headers = headers;
  }
  return nativeFetch(input, init);
};

function updateClock() {
  const el = document.getElementById('clock');
  if (!el) return;
  const now = new Date();
  el.textContent = now.toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
setInterval(updateClock, 1000);
updateClock();

document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => el.style.opacity = '0', 3500);
  setTimeout(() => el.remove(), 4000);
  el.style.transition = 'opacity 0.5s';
});

document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});

async function api(url, data = null) {
  const opts = { headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
  if (data) { opts.method = 'POST'; opts.body = JSON.stringify(data); }
  const res = await fetch(url, opts);
  return res.json();
}

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
