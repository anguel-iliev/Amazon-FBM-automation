<div class="page-header">
  <div></div>
  <div class="page-header-actions">
    <a href="/products" class="btn btn-ghost btn-sm">← Към продукти</a>
  </div>
</div>

<div style="max-width:640px">
<div class="card">
  <div class="card-title">Нов продукт</div>
  <div id="form-result" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:6px;font-size:13px;font-weight:600"></div>

  <div class="grid-2">
    <div class="form-group">
      <label class="form-label">EAN Amazon <span style="color:var(--red)">*</span></label>
      <input type="text" id="f-ean" class="form-control" placeholder="4015400259275" required>
    </div>
    <div class="form-group">
      <label class="form-label">ASIN</label>
      <input type="text" id="f-asin" class="form-control" placeholder="B0XXXXXXXXX">
    </div>
    <div class="form-group">
      <label class="form-label">Доставчик</label>
      <input type="text" id="f-supplier" class="form-control" placeholder="Orbico">
    </div>
    <div class="form-group">
      <label class="form-label">Бранд</label>
      <input type="text" id="f-brand" class="form-control" placeholder="Always">
    </div>
  </div>

  <div class="form-group">
    <label class="form-label">Модел / Описание</label>
    <input type="text" id="f-model" class="form-control" placeholder="Always Classic Damenbinden Normal 10 Stück">
  </div>

  <div class="grid-2">
    <div class="form-group">
      <label class="form-label">Цена доставчик (Netto €)</label>
      <input type="number" id="f-price" class="form-control" placeholder="1.18" step="0.01" min="0">
    </div>
    <div class="form-group">
      <label class="form-label">Amazon Link</label>
      <input type="url" id="f-link" class="form-control" placeholder="https://www.amazon.de/dp/...">
    </div>
  </div>

  <div class="form-group">
    <label class="form-label">Коментар</label>
    <input type="text" id="f-notes" class="form-control" placeholder="Бележки...">
  </div>

  <div style="display:flex;gap:10px;margin-top:8px">
    <button class="btn btn-primary" id="save-btn" onclick="saveProduct()">Запази в Firebase</button>
    <a href="/products" class="btn btn-ghost">Отказ</a>
  </div>
</div>
</div>

<script>
function saveProduct() {
  const ean = document.getElementById('f-ean').value.trim();
  if (!ean) { showMsg('EAN Amazon е задължителен', false); return; }

  const btn = document.getElementById('save-btn');
  btn.disabled = true; btn.textContent = 'Записване…';

  const fd = new FormData();
  fd.append('ean',      ean);
  fd.append('asin',     document.getElementById('f-asin').value.trim());
  fd.append('supplier', document.getElementById('f-supplier').value.trim());
  fd.append('brand',    document.getElementById('f-brand').value.trim());
  fd.append('model',    document.getElementById('f-model').value.trim());
  fd.append('price',    document.getElementById('f-price').value.trim());
  fd.append('link',     document.getElementById('f-link').value.trim());
  fd.append('notes',    document.getElementById('f-notes').value.trim());

  fetch('/products/add', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showMsg('✓ Продуктът е записан в Firebase!', true);
        setTimeout(() => window.location.href = '/products', 1500);
      } else {
        showMsg('✗ ' + (d.error||'Грешка'), false);
        btn.disabled = false; btn.textContent = 'Запази в Firebase';
      }
    })
    .catch(() => { showMsg('✗ Мрежова грешка', false); btn.disabled=false; btn.textContent='Запази в Firebase'; });
}

function showMsg(msg, ok) {
  const el = document.getElementById('form-result');
  el.textContent = msg;
  el.style.background = ok ? 'rgba(61,187,127,.1)' : 'rgba(224,92,92,.1)';
  el.style.border = '1px solid ' + (ok ? 'rgba(61,187,127,.3)' : 'rgba(224,92,92,.3)');
  el.style.color  = ok ? '#5DCCA0' : '#F08080';
  el.style.display = 'block';
}
document.addEventListener('keydown', e => { if (e.key==='Enter' && e.ctrlKey) saveProduct(); });
</script>
