<div class="page-header">
  <div></div>
  <div class="page-header-actions">
    <a href="/products" class="btn btn-ghost btn-sm">← Към продукти</a>
  </div>
</div>

<?php $suppliers = $suppliers ?? []; $brands = $brands ?? []; ?>
<div style="max-width:760px">
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
      <select id="f-supplier" class="form-control" onchange="loadBrandsForSupplier(this.value)">
        <option value="">Избери доставчик</option>
        <?php foreach ($suppliers as $supplier): ?>
        <option value="<?= htmlspecialchars($supplier) ?>"><?= htmlspecialchars($supplier) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Бранд</label>
      <select id="f-brand" class="form-control">
        <option value="">Избери бранд</option>
        <?php foreach ($brands as $brand): ?>
        <option value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
        <?php endforeach; ?>
      </select>
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
const ADD_PRODUCT_CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
function saveProduct() {
  const ean = document.getElementById('f-ean').value.trim();
  const supplier = document.getElementById('f-supplier').value.trim();
  const brand = document.getElementById('f-brand').value.trim();
  if (!ean) { showMsg('EAN Amazon е задължителен', false); return; }
  if (!supplier) { showMsg('Избери доставчик', false); return; }
  if (!brand) { showMsg('Избери бранд', false); return; }

  const btn = document.getElementById('save-btn');
  btn.disabled = true; btn.textContent = 'Записване…';

  const fd = new FormData();
  fd.append('ean', ean);
  fd.append('asin', document.getElementById('f-asin').value.trim());
  fd.append('supplier', supplier);
  fd.append('brand', brand);
  fd.append('model', document.getElementById('f-model').value.trim());
  fd.append('price', document.getElementById('f-price').value.trim());
  fd.append('link', document.getElementById('f-link').value.trim());
  fd.append('notes', document.getElementById('f-notes').value.trim());

  fetch('/products/add', {method:'POST', headers:{'X-CSRF-Token': ADD_PRODUCT_CSRF_TOKEN}, body:fd})
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showMsg('✓ Продуктът е записан в Firebase!', true);
        setTimeout(() => window.location.href = '/products', 1200);
      } else {
        showMsg('✗ ' + (d.error||'Грешка'), false);
        btn.disabled = false; btn.textContent = 'Запази в Firebase';
      }
    })
    .catch(() => { showMsg('✗ Мрежова грешка', false); btn.disabled=false; btn.textContent='Запази в Firebase'; });
}

function loadBrandsForSupplier(supplier) {
  const brandSel = document.getElementById('f-brand');
  brandSel.innerHTML = '<option value="">Зареждане…</option>';
  fetch('/products/brands?supplier=' + encodeURIComponent(supplier), {headers: {'X-CSRF-Token': ADD_PRODUCT_CSRF_TOKEN}})
    .then(r => r.json())
    .then(d => {
      brandSel.innerHTML = '<option value="">Избери бранд</option>';
      const brands = (d && d.ok && Array.isArray(d.brands) ? d.brands : []);
      brands.forEach(brand => {
        const o = document.createElement('option');
        o.value = brand; o.textContent = brand;
        brandSel.appendChild(o);
      });
    })
    .catch(() => {
      brandSel.innerHTML = '<option value="">Избери бранд</option>';
    });
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
