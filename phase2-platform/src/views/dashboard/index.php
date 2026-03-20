<?php
$s = $stats ?? [];
$notUploaded  = $s['not_uploaded']  ?? 0;
$totalProducts= $s['total_products']?? 0;
$withAsin     = $s['with_asin']     ?? 0;
$lastSync     = $s['last_sync']     ?? null;
$suppliers    = $s['suppliers']     ?? 0;
?>

<!-- Stat cards -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Общо продукти</div>
    <div class="stat-value"><?= number_format($totalProducts) ?></div>
    <div class="stat-sub"><?= $suppliers ?> доставчика</div>
  </div>
  <div class="stat-card accent-green">
    <div class="stat-label">С ASIN</div>
    <div class="stat-value"><?= number_format($withAsin) ?></div>
    <div class="stat-sub"><?= $totalProducts ? round($withAsin / $totalProducts * 100) : 0 ?>% от общо</div>
  </div>
  <div class="stat-card accent-amber">
    <div class="stat-label">За качване</div>
    <div class="stat-value"><?= number_format($notUploaded) ?></div>
    <div class="stat-sub">NOT_UPLOADED</div>
  </div>
  <div class="stat-card accent-blue">
    <div class="stat-label">Последна синхронизация</div>
    <div class="stat-value" style="font-size:18px;line-height:1.3">
      <?= $lastSync ? date('d.m H:i', strtotime($lastSync)) : '—' ?>
    </div>
    <div class="stat-sub"><?= $lastSync ? date('Y', strtotime($lastSync)) : 'Никога' ?></div>
  </div>
</div>

<div class="grid-2">

  <!-- Quick actions -->
  <div class="card">
    <div class="card-title">Бързи действия</div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <button class="btn btn-primary" id="sync-btn" onclick="triggerSync()">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 0 1 6-6 6 6 0 0 1 4.24 1.76M16 10a6 6 0 0 1-6 6 6 6 0 0 1-4.24-1.76" stroke="currentColor" stroke-width="2"/><path d="M14.24 4.76 16 3v3.5h-3.5M5.76 15.24 4 17v-3.5h3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Стартирай синхронизация
      </button>
      <div id="sync-status" class="text-sm text-muted"></div>
      <a href="/products?filter=not_uploaded" class="btn btn-ghost">
        Виж продукти за качване (<?= $notUploaded ?>)
      </a>
      <a href="/pricing" class="btn btn-ghost">
        Изчисли цени по пазари
      </a>
    </div>
  </div>

  <!-- Sync log -->
  <div class="card">
    <div class="card-title">Последни синхронизации</div>
    <?php if (!empty($syncLog)): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Дата</th><th>Качени</th><th>Обновени</th><th>Статус</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($syncLog, 0, 6) as $log): ?>
          <tr>
            <td class="text-muted text-sm"><?= date('d.m H:i', strtotime($log['date'])) ?></td>
            <td><?= $log['uploaded'] ?? 0 ?></td>
            <td><?= $log['updated'] ?? 0 ?></td>
            <td>
              <?php if (($log['status'] ?? '') === 'success'): ?>
              <span class="badge badge-green">OK</span>
              <?php else: ?>
              <span class="badge badge-red">Грешка</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted text-sm" style="padding:12px 0">Няма записи. Стартирай първата синхронизация.</p>
    <?php endif; ?>
  </div>

</div>

<!-- Recent products -->
<div class="card mt-16">
  <div class="flex-between mb-16">
    <div class="card-title" style="margin:0">Последно обновени продукти</div>
    <a href="/products" class="btn btn-ghost btn-sm">Всички →</a>
  </div>
  <?php if (!empty($recent)): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>EAN</th>
          <th>Продукт</th>
          <th>Доставчик</th>
          <th>Цена €</th>
          <th>Промяна</th>
          <th>Статус</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $p): ?>
        <tr>
          <td class="text-muted text-sm"><?= htmlspecialchars($p['ean'] ?? '—') ?></td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= htmlspecialchars($p['product_name'] ?? '—') ?>
          </td>
          <td><span class="badge badge-muted"><?= htmlspecialchars($p['source'] ?? '—') ?></span></td>
          <td class="font-head"><?= number_format((float)($p['supplier_price'] ?? 0), 2) ?></td>
          <td>
            <?php $change = $p['price_change'] ?? 'SAME'; ?>
            <?php if ($change === 'UP'): ?>
            <span class="price-up text-sm">↑ UP</span>
            <?php elseif ($change === 'DOWN'): ?>
            <span class="price-down text-sm">↓ DOWN</span>
            <?php else: ?>
            <span class="text-muted text-sm">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php $status = $p['upload_status'] ?? 'NOT_UPLOADED'; ?>
            <span class="badge <?= $status === 'UPLOADED' ? 'badge-green' : 'badge-gold' ?>">
              <?= $status ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p class="text-muted text-sm" style="padding:12px 0">Няма продукти. Стартирай синхронизация.</p>
  <?php endif; ?>
</div>
