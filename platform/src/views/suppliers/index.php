<?php
$suppliers = $supplierList ?? [];
?>

<div class="page-header">
  <div>
    <span style="font-size:13px;color:rgba(255,255,255,0.5)"><?= count($suppliers) ?> доставчика</span>
  </div>
  <div class="page-header-actions">
    <button onclick="openAddModal()" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Добави доставчик
    </button>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px" id="suppliers-grid">
  <?php foreach ($suppliers as $sup): ?>
  <?php
    $id         = htmlspecialchars($sup['id'] ?? '');
    $name       = htmlspecialchars($sup['name'] ?? '');
    $email      = htmlspecialchars($sup['email'] ?? '');
    $phone      = htmlspecialchars($sup['phone'] ?? '');
    $website    = htmlspecialchars($sup['website'] ?? '');
    $notes      = htmlspecialchars($sup['notes'] ?? '');
    $currency   = htmlspecialchars($sup['currency'] ?? 'EUR');
    $payTerms   = htmlspecialchars($sup['payment_terms'] ?? '');
    $active     = $sup['active'] ?? true;
    $productCnt = (int)($sup['product_count'] ?? 0);
  ?>
  <div class="supplier-card <?= $active ? 'active' : '' ?>" id="sup-<?= $id ?>">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
      <div>
        <div style="font-size:15px;font-weight:700;color:#ffffff"><?= $name ?></div>
        <?php if ($productCnt > 0): ?>
        <div style="font-size:11px;color:rgba(255,255,255,0.4);margin-top:2px"><?= $productCnt ?> продукта</div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:4px;align-items:center">
        <span class="badge <?= $active ? 'badge-green' : 'badge-muted' ?>"><?= $active ? 'Активен' : 'Неактивен' ?></span>
        <button onclick="editSupplier('<?= $id ?>')" class="btn btn-ghost btn-icon btn-sm" title="Редактирай">
          <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M14.7 3.3a2.4 2.4 0 0 1 3.4 3.4L6.5 18.5 2 19l.5-4.5z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>
    </div>

    <!-- Contact info -->
    <div style="display:flex;flex-direction:column;gap:5px;margin-bottom:10px">
      <?php if ($email): ?>
      <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,0.55)">
        <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><rect x="2" y="5" width="16" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M2 7l8 5 8-5" stroke="currentColor" stroke-width="1.5"/></svg>
        <a href="mailto:<?= $email ?>" style="color:rgba(255,255,255,0.55);text-decoration:none"><?= $email ?></a>
      </div>
      <?php endif; ?>
      <?php if ($phone): ?>
      <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,0.55)">
        <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M4 4h4l1.5 3.5-2 1.2a9 9 0 0 0 3.8 3.8l1.2-2L16 12v4a1 1 0 0 1-1 1C7 17 3 13 3 5a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <?= $phone ?>
      </div>
      <?php endif; ?>
      <?php if ($website): ?>
      <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,0.55)">
        <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M2 10h16M10 2c-2.5 2.5-4 5-4 8s1.5 5.5 4 8M10 2c2.5 2.5 4 5 4 8s-1.5 5.5-4 8" stroke="currentColor" stroke-width="1.5"/></svg>
        <a href="<?= $website ?>" target="_blank" style="color:rgba(255,255,255,0.55);text-decoration:none"><?= $website ?></a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Tags row -->
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
      <?php if ($currency): ?>
      <span class="badge badge-blue"><?= $currency ?></span>
      <?php endif; ?>
      <?php if ($payTerms): ?>
      <span class="badge badge-muted"><?= $payTerms ?></span>
      <?php endif; ?>
    </div>

    <!-- Notes -->
    <?php if ($notes): ?>
    <div style="background:var(--bg);border-radius:4px;padding:8px 10px;font-size:12px;color:rgba(255,255,255,0.5);line-height:1.5;border:1px solid var(--border)">
      <?= nl2br($notes) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if (empty($suppliers)): ?>
  <div style="grid-column:1/-1;text-align:center;padding:60px;color:rgba(255,255,255,0.3)">
    <svg width="48" height="48" viewBox="0 0 20 20" fill="none" style="display:block;margin:0 auto 12px;opacity:.3"><path d="M3 17v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="1.5"/></svg>
    Няма добавени доставчици. Добави първия.
  </div>
  <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="sup-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--panel);border:1px solid var(--border2);border-radius:12px;padding:28px;width:500px;max-width:94vw;max-height:90vh;overflow-y:auto">
    <div style="font-family:var(--font-head);font-size:16px;font-weight:700;color:#fff;margin-bottom:20px" id="modal-title">Добави доставчик</div>
    <input type="hidden" id="sup-id">
    <div class="grid-2" style="gap:12px">
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Име на доставчик *</label>
        <input type="text" id="sup-name" class="form-control" placeholder="напр. Orbico">
      </div>
      <div class="form-group">
        <label class="form-label">Имейл</label>
        <input type="email" id="sup-email" class="form-control" placeholder="orders@supplier.com">
      </div>
      <div class="form-group">
        <label class="form-label">Телефон</label>
        <input type="text" id="sup-phone" class="form-control" placeholder="+49 ...">
      </div>
      <div class="form-group">
        <label class="form-label">Уебсайт</label>
        <input type="text" id="sup-website" class="form-control" placeholder="https://...">
      </div>
      <div class="form-group">
        <label class="form-label">Валута</label>
        <select id="sup-currency" class="form-control">
          <option value="EUR">EUR €</option>
          <option value="USD">USD $</option>
          <option value="GBP">GBP £</option>
          <option value="BGN">BGN лв</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Условия на плащане</label>
        <input type="text" id="sup-payment" class="form-control" placeholder="30 дни, предплата...">
      </div>
      <div class="form-group">
        <label class="form-label">Мин. поръчка (€)</label>
        <input type="number" id="sup-minorder" class="form-control" placeholder="0" step="0.01">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Бележки</label>
        <textarea id="sup-notes" class="form-control" rows="3" placeholder="Специални условия, контакти, коментари..."></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" id="sup-active" checked>
          <span style="font-size:13px;font-weight:600;color:#fff">Активен доставчик</span>
        </label>
      </div>
    </div>
    <div id="sup-error" style="font-size:12px;color:var(--red);margin-bottom:10px;display:none"></div>
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button onclick="closeSupModal()" class="btn btn-ghost">Отказ</button>
      <button onclick="saveSupplier()" class="btn btn-primary">Запази</button>
    </div>
  </div>
</div>

<script>
const suppliersData = <?= json_encode($suppliers) ?>;

function openAddModal() {
  document.getElementById('modal-title').textContent = 'Добави доставчик';
  document.getElementById('sup-id').value = '';
  ['name','email','phone','website','notes','payment'].forEach(f => {
    const el = document.getElementById('sup-'+f);
    if (el) el.value = '';
  });
  document.getElementById('sup-currency').value = 'EUR';
  document.getElementById('sup-minorder').value = '';
  document.getElementById('sup-active').checked = true;
  document.getElementById('sup-error').style.display = 'none';
  document.getElementById('sup-modal').style.display = 'flex';
}

function editSupplier(id) {
  const sup = suppliersData.find(s => s.id == id);
  if (!sup) return;
  document.getElementById('modal-title').textContent = 'Редактирай доставчик';
  document.getElementById('sup-id').value = sup.id || '';
  document.getElementById('sup-name').value = sup.name || '';
  document.getElementById('sup-email').value = sup.email || '';
  document.getElementById('sup-phone').value = sup.phone || '';
  document.getElementById('sup-website').value = sup.website || '';
  document.getElementById('sup-currency').value = sup.currency || 'EUR';
  document.getElementById('sup-payment').value = sup.payment_terms || '';
  document.getElementById('sup-minorder').value = sup.min_order || '';
  document.getElementById('sup-notes').value = sup.notes || '';
  document.getElementById('sup-active').checked = sup.active !== false;
  document.getElementById('sup-error').style.display = 'none';
  document.getElementById('sup-modal').style.display = 'flex';
}

function closeSupModal() {
  document.getElementById('sup-modal').style.display = 'none';
}

function saveSupplier() {
  const name = document.getElementById('sup-name').value.trim();
  const errEl = document.getElementById('sup-error');
  if (!name) { errEl.textContent = 'Въведи име!'; errEl.style.display='block'; return; }

  const payload = {
    id:            document.getElementById('sup-id').value,
    name:          name,
    email:         document.getElementById('sup-email').value,
    phone:         document.getElementById('sup-phone').value,
    website:       document.getElementById('sup-website').value,
    currency:      document.getElementById('sup-currency').value,
    payment_terms: document.getElementById('sup-payment').value,
    min_order:     document.getElementById('sup-minorder').value,
    notes:         document.getElementById('sup-notes').value,
    active:        document.getElementById('sup-active').checked,
  };

  fetch('/suppliers/save', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(d=>{
    if (d.success) { location.reload(); }
    else { errEl.textContent = d.error || 'Грешка'; errEl.style.display='block'; }
  });
}
</script>
