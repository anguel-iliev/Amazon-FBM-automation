<?php
$markets = ['DE','FR','IT','ES','NL','PL','SE'];
?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <span class="text-muted text-sm"><?= number_format($total) ?> продукта</span>
    <?php if ($filter === 'not_uploaded'): ?>
    <span class="badge badge-gold">За качване</span>
    <?php endif; ?>
  </div>
  <div class="page-header-actions">
    <a href="/sync" class="btn btn-primary btn-sm">Синхронизирай</a>
  </div>
</div>

<!-- Filters -->
<div class="card mb-16" style="padding:14px 20px">
  <form method="GET" action="/products" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:1;min-width:200px">
      <input type="text" name="search" class="form-control" placeholder="Търси EAN, ASIN, продукт..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div>
      <select name="source" class="form-control">
        <option value="">Всички доставчици</option>
        <?php foreach ($allSources as $s): ?>
        <option value="<?= htmlspecialchars($s) ?>" <?= $source === $s ? 'selected' : '' ?>>
          <?= htmlspecialchars($s) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <select name="filter" class="form-control">
        <option value="">Всички статуси</option>
        <option value="not_uploaded" <?= $filter === 'not_uploaded' ? 'selected' : '' ?>>За качване</option>
      </select>
    </div>
    <button type="submit" class="btn btn-ghost">Филтрирай</button>
    <a href="/products" class="btn btn-ghost">Изчисти</a>
  </form>
</div>

<!-- Table -->
<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>EAN</th>
          <th>SKU</th>
          <th>Продукт</th>
          <th>Доставчик</th>
          <th style="text-align:right">Цена €</th>
          <th>Промяна</th>
          <th>ASIN DE</th>
          <th>Цена DE</th>
          <th>Статус</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
        <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--muted)">
          Няма намерени продукти
        </td></tr>
        <?php else: ?>
        <?php foreach ($products as $p): ?>
        <tr>
          <td class="text-sm text-muted"><?= htmlspecialchars($p['ean'] ?? '—') ?></td>
          <td class="text-sm"><?= htmlspecialchars($p['our_sku'] ?? '—') ?></td>
          <td style="max-width:220px">
            <?php if (!empty($p['asin_de'])): ?>
            <a href="https://www.amazon.de/dp/<?= htmlspecialchars($p['asin_de']) ?>" target="_blank"
               style="color:var(--text);text-decoration:none" title="Отвори в Amazon">
            <?php endif; ?>
            <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px">
              <?= htmlspecialchars($p['product_name'] ?? '—') ?>
            </span>
            <?php if (!empty($p['asin_de'])): ?></a><?php endif; ?>
          </td>
          <td><span class="badge badge-muted"><?= htmlspecialchars($p['source'] ?? '—') ?></span></td>
          <td style="text-align:right;font-weight:600">
            <?= number_format((float)($p['supplier_price'] ?? 0), 2) ?>
          </td>
          <td>
            <?php $chg = $p['price_change'] ?? 'SAME'; ?>
            <?php if ($chg === 'UP'): ?>
              <span class="price-up text-sm">↑</span>
            <?php elseif ($chg === 'DOWN'): ?>
              <span class="price-down text-sm">↓</span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-sm"><?= htmlspecialchars($p['asin_de'] ?? '—') ?></td>
          <td style="font-weight:600;color:var(--gold)">
            <?= !empty($p['final_price_de']) ? number_format((float)$p['final_price_de'], 2) . ' €' : '—' ?>
          </td>
          <td>
            <?php $st = $p['upload_status'] ?? 'NOT_UPLOADED'; ?>
            <span class="badge <?= $st === 'UPLOADED' ? 'badge-green' : 'badge-gold' ?>">
              <?= $st ?>
            </span>
          </td>
          <td>
            <a href="https://www.amazon.de/s?k=<?= urlencode($p['ean'] ?? '') ?>" target="_blank"
               class="btn btn-ghost btn-icon btn-sm" title="Търси в Amazon">
              <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.8"/><path d="M13 13l3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
