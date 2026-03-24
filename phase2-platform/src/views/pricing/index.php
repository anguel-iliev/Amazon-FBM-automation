<?php
$markets = $marketplaces ?? [];
$codes = ['DE','FR','IT','ES','NL','PL','SE'];
$flags = ['DE'=>'🇩🇪','FR'=>'🇫🇷','IT'=>'🇮🇹','ES'=>'🇪🇸','NL'=>'🇳🇱','PL'=>'🇵🇱','SE'=>'🇸🇪'];
$names = ['DE'=>'Germany','FR'=>'France','IT'=>'Italy','ES'=>'Spain','NL'=>'Netherlands','PL'=>'Poland','SE'=>'Sweden'];
?>

<div class="grid-2" style="align-items:start">

  <!-- Calculator -->
  <div class="card">
    <div class="card-title">Калкулатор на цени</div>
    <div class="form-group">
      <label class="form-label">Цена от доставчик (€, без ДДС)</label>
      <input type="number" id="calc-price" class="form-control" placeholder="0.00" step="0.01" min="0">
    </div>
    <div class="form-group">
      <label class="form-label">Пазари</label>
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($codes as $code): ?>
        <?php $cfg = $markets[$code] ?? []; ?>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 12px;border:1px solid var(--border2);border-radius:6px;font-size:14px;background:var(--bg3);transition:background .15s">
          <input type="checkbox" name="markets[]" value="<?= $code ?>"
            <?= ($cfg['active'] ?? false) ? 'checked' : '' ?>>
          <span style="font-size:18px"><?= $flags[$code] ?? '' ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="btn btn-primary" onclick="calcPrices()">Изчисли</button>
    <div id="calc-error" class="text-sm" style="color:var(--red);margin-top:8px;display:none"></div>
  </div>

  <!-- Results -->
  <div class="card" id="results-card" style="display:none">
    <div class="card-title">Резултати</div>
    <div id="calc-results"></div>
  </div>

</div>

<!-- Marketplace settings -->
<div class="card mt-16">
  <div class="flex-between mb-16">
    <div class="card-title" style="margin:0">Настройки по пазари</div>
    <a href="/settings/vat" class="btn btn-ghost btn-sm">Редактирай →</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Пазар</th><th>ДДС %</th><th>Amazon %</th><th>Доставка €</th><th>FBM такса €</th><th>Статус</th></tr>
      </thead>
      <tbody>
        <?php foreach ($codes as $code): ?>
        <?php $cfg = $markets[$code] ?? []; ?>
        <tr>
          <td>
            <span style="font-size:18px;margin-right:8px"><?= $flags[$code] ?? '' ?></span>
            <strong style="font-size:13px"><?= $names[$code] ?? $code ?></strong>
          </td>
          <td><?= round(($cfg['vat'] ?? 0) * 100, 0) ?>%</td>
          <td><?= round(($cfg['amazon_fee'] ?? 0) * 100, 0) ?>%</td>
          <td><?= number_format($cfg['shipping'] ?? 0, 2) ?></td>
          <td><?= number_format($cfg['fbm_fee'] ?? 0, 2) ?></td>
          <td>
            <span class="badge <?= ($cfg['active'] ?? false) ? 'badge-green' : 'badge-muted' ?>">
              <?= ($cfg['active'] ?? false) ? 'Активен' : 'Неактивен' ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const flags = <?= json_encode($flags) ?>;
const names = <?= json_encode($names) ?>;

async function calcPrices() {
  const price = parseFloat(document.getElementById('calc-price').value);
  const errEl = document.getElementById('calc-error');
  errEl.style.display = 'none';

  if (!price || price <= 0) {
    errEl.textContent = 'Въведи валидна цена.';
    errEl.style.display = 'block';
    return;
  }

  const checked = [...document.querySelectorAll('input[name="markets[]"]:checked')].map(el => el.value);

  const res = await fetch('/pricing/calculate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'price=' + price + '&' + checked.map(m => 'markets[]=' + m).join('&'),
  });

  const data = await res.json();
  const results = data.results || {};

  let html = '<div style="display:flex;flex-direction:column;gap:10px">';
  for (const [code, r] of Object.entries(results)) {
    const viable = r.viable;
    html += `
      <div style="padding:14px;background:var(--bg3);border-radius:6px;border:1px solid ${viable ? 'rgba(61,187,127,0.2)' : 'var(--border)'}">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:20px">${flags[code] || ''}</span>
            <strong style="font-size:14px">${names[code] || code}</strong>
          </div>
          <span style="font-family:var(--font-head);font-size:22px;font-weight:800;color:var(--gold)">${r.final.toFixed(2)} €</span>
        </div>
        <div style="display:flex;gap:16px;font-size:12px;color:rgba(255,255,255,0.5)">
          <span>Марж: <strong style="color:${viable ? 'var(--green)' : 'var(--red)'}">${r.margin_pct}%</strong></span>
          <span>Amazon: ${r.breakdown.amazon_fee.toFixed(2)} €</span>
          <span>ДДС: ${r.breakdown.vat.toFixed(2)} €</span>
          <span>Доставка: ${r.breakdown.shipping.toFixed(2)} €</span>
        </div>
      </div>`;
  }
  html += '</div>';

  document.getElementById('calc-results').innerHTML = html;
  document.getElementById('results-card').style.display = 'block';
}
</script>
