<?php
$statusLabels = [
    'NOT_UPLOADED' => ['label' => 'NOT UPLOADED', 'class' => 'badge-gold'],
    'UPLOADED'     => ['label' => 'UPLOADED',     'class' => 'badge-green'],
    'SKIPPED'      => ['label' => 'SKIPPED',      'class' => 'badge-muted'],
];
$queryBase = http_build_query(array_filter([
    'search' => $search,
    'source' => $source,
    'status' => $status,
    'filter' => $filter,
]));
?>

<!-- Page header -->
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <span class="text-muted text-sm">
      <?= number_format($total) ?> продукта
      <?php if ($total > $perPage): ?>
      · страница <?= $page ?> от <?= $totalPages ?>
      <?php endif; ?>
    </span>
    <?php if (!empty($filter) || !empty($search) || !empty($source) || !empty($status)): ?>
    <span class="badge badge-gold">Филтрирано</span>
    <?php endif; ?>
  </div>
  <div class="page-header-actions">
    <a href="/products/export?<?= $queryBase ?>" class="btn btn-ghost btn-sm">
      <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><path d="M10 3v10M6 9l4 4 4-4M4 17h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      CSV
    </a>
    <a href="/sync" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 0 1 6-6 6 6 0 0 1 4.24 1.76M16 10a6 6 0 0 1-6 6 6 6 0 0 1-4.24-1.76" stroke="currentColor" stroke-width="2"/><path d="M14.24 4.76 16 3v3.5h-3.5M5.76 15.24 4 17v-3.5h3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Синхронизирай
    </a>
  </div>
</div>

<!-- Filters -->
<div class="card mb-16" style="padding:12px 16px">
  <form method="GET" action="/products" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input type="text" name="search" class="form-control" placeholder="EAN, ASIN, продукт, SKU..."
           value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px">

    <select name="source" class="form-control" style="min-width:160px">
      <option value="">— Всички доставчици —</option>
      <?php foreach ($allSources as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= $source === $s ? 'selected' : '' ?>>
        <?= htmlspecialchars($s) ?>
      </option>
      <?php endforeach; ?>
    </select>

    <select name="status" class="form-control" style="min-width:160px">
      <option value="">— Всички статуси —</option>
      <option value="NOT_UPLOADED" <?= $status === 'NOT_UPLOADED' ? 'selected' : '' ?>>За качване</option>
      <option value="UPLOADED"     <?= $status === 'UPLOADED'     ? 'selected' : '' ?>>Качени</option>
    </select>

    <button type="submit" class="btn btn-primary btn-sm">Търси</button>
    <a href="/products" class="btn btn-ghost btn-sm">Изчисти</a>
  </form>
</div>

<?php if ($total === 0): ?>
<!-- Empty state -->
<div class="card" style="text-align:center;padding:60px 20px">
  <div style="font-size:40px;margin-bottom:16px;opacity:0.3">📦</div>
  <div style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:8px">Няма продукти</div>
  <div class="text-sm text-muted" style="margin-bottom:24px">
    <?php if (!empty($search) || !empty($source) || !empty($status)): ?>
      Няма резултати за избраните филтри.
    <?php else: ?>
      Продуктите ще се появят след стартиране на синхронизация.<br>
      Уверете се, че Google Credentials са настроени в Настройки.
    <?php endif; ?>
  </div>
  <?php if (!empty($search) || !empty($source) || !empty($status)): ?>
  <a href="/products" class="btn btn-ghost">Изчисти филтрите</a>
  <?php else: ?>
  <a href="/sync" class="btn btn-primary">Стартирай синхронизация</a>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- Products table -->
<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
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
        <?php
        $rowNum = ($page - 1) * $perPage;
        foreach ($products as $p):
          $rowNum++;
          $st    = $p['upload_status'] ?? 'NOT_UPLOADED';
          $stCfg = $statusLabels[$st] ?? ['label' => $st, 'class' => 'badge-muted'];
          $chg   = $p['price_change'] ?? '';
        ?>
        <tr>
          <td class="text-sm text-muted"><?= $rowNum ?></td>
          <td class="text-sm" style="font-family:monospace;color:var(--muted)"><?= htmlspecialchars($p['ean'] ?? '—') ?></td>
          <td class="text-sm"><?= htmlspecialchars($p['our_sku'] ?? '—') ?></td>
          <td style="max-width:240px">
            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:240px" title="<?= htmlspecialchars($p['product_name'] ?? '') ?>">
              <?php if (!empty($p['asin_de'])): ?>
              <a href="https://www.amazon.de/dp/<?= htmlspecialchars($p['asin_de']) ?>" target="_blank"
                 style="color:var(--text);text-decoration:none">
              <?php endif; ?>
              <?= htmlspecialchars($p['product_name'] ?? '—') ?>
              <?php if (!empty($p['asin_de'])): ?></a><?php endif; ?>
            </div>
          </td>
          <td><span class="badge badge-muted" style="font-size:10px"><?= htmlspecialchars($p['source'] ?? '—') ?></span></td>
          <td style="text-align:right;font-weight:600;font-variant-numeric:tabular-nums">
            <?= number_format((float)($p['supplier_price'] ?? 0), 2) ?>
          </td>
          <td style="text-align:center">
            <?php if ($chg === 'UP'): ?>
              <span class="price-up" title="Цената се е покачила">↑</span>
            <?php elseif ($chg === 'DOWN'): ?>
              <span class="price-down" title="Цената е паднала">↓</span>
            <?php elseif ($chg === 'NEW'): ?>
              <span style="color:var(--blue);font-size:10px;font-weight:600">NEW</span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-sm" style="font-family:monospace"><?= htmlspecialchars($p['asin_de'] ?? '—') ?></td>
          <td style="font-weight:600;color:var(--gold);font-variant-numeric:tabular-nums">
            <?= !empty($p['final_price_de']) ? number_format((float)$p['final_price_de'], 2) . ' €' : '—' ?>
          </td>
          <td>
            <span class="badge <?= $stCfg['class'] ?>" style="font-size:10px;white-space:nowrap">
              <?= $stCfg['label'] ?>
            </span>
          </td>
          <td>
            <a href="https://www.amazon.de/s?k=<?= urlencode($p['ean'] ?? '') ?>" target="_blank"
               class="btn btn-ghost btn-icon btn-sm" title="Търси в Amazon DE">
              <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.8"/><path d="M13 13l3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;padding:0 4px">
  <div class="text-sm text-muted">
    Показани <?= number_format(($page-1)*$perPage + 1) ?>–<?= number_format(min($page*$perPage, $total)) ?> от <?= number_format($total) ?>
  </div>
  <div style="display:flex;gap:4px">
    <?php if ($page > 1): ?>
    <a href="/products?<?= $queryBase ?>&page=1" class="btn btn-ghost btn-sm">«</a>
    <a href="/products?<?= $queryBase ?>&page=<?= $page-1 ?>" class="btn btn-ghost btn-sm">‹</a>
    <?php endif; ?>

    <?php
    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);
    for ($i = $start; $i <= $end; $i++):
    ?>
    <a href="/products?<?= $queryBase ?>&page=<?= $i ?>"
       class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
    <a href="/products?<?= $queryBase ?>&page=<?= $page+1 ?>" class="btn btn-ghost btn-sm">›</a>
    <a href="/products?<?= $queryBase ?>&page=<?= $totalPages ?>" class="btn btn-ghost btn-sm">»</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>
