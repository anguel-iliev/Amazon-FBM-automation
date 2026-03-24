<?php
$s = $stats ?? [];
$notUploaded  = $s['not_uploaded']  ?? 0;
$totalProducts= $s['total_products']?? 0;
$withAsin     = $s['with_asin']     ?? 0;
$lastSync     = $s['last_sync']     ?? null;
$suppliers    = $s['suppliers']     ?? 0;
$avgRezultat  = $s['avg_rezultat']  ?? 0;
$posRezultat  = $s['pos_rezultat']  ?? 0;
$negRezultat  = $s['neg_rezultat']  ?? 0;
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

<!-- Резултат summary -->
<div class="card mt-16" style="padding:20px 24px">
  <div class="flex-between mb-16">
    <div class="card-title" style="margin:0">Усреднен резултат (колона Y)</div>
    <a href="/products?sort=Резултат&dir=desc" class="btn btn-ghost btn-sm">Виж всички →</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
    <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;position:relative;overflow:hidden">
      <div style="position:absolute;top:0;left:0;right:0;height:2px;background:var(--gold)"></div>
      <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Среден резултат</div>
      <div style="font-family:var(--font-head);font-size:28px;font-weight:800;color:<?= $avgRezultat >= 0 ? 'var(--green)' : 'var(--red)' ?>">
        <?= ($avgRezultat >= 0 ? '+' : '') . number_format($avgRezultat, 2) ?> €
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:4px">на продукт</div>
    </div>
    <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;position:relative;overflow:hidden">
      <div style="position:absolute;top:0;left:0;right:0;height:2px;background:var(--green)"></div>
      <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Печеливши</div>
      <div style="font-family:var(--font-head);font-size:28px;font-weight:800;color:var(--green)">
        <?= number_format($posRezultat) ?>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:4px">продукта с Резултат > 0</div>
    </div>
    <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;position:relative;overflow:hidden">
      <div style="position:absolute;top:0;left:0;right:0;height:2px;background:var(--red)"></div>
      <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Губещи</div>
      <div style="font-family:var(--font-head);font-size:28px;font-weight:800;color:var(--red)">
        <?= number_format($negRezultat) ?>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:4px">продукта с Резултат ≤ 0</div>
    </div>
  </div>
</div>

<!-- Quick actions only (no sync log) -->
<div class="card mt-16">
  <div class="card-title">Бързи действия</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn btn-primary" id="sync-btn" onclick="triggerSync()">
      <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 0 1 6-6 6 6 0 0 1 4.24 1.76M16 10a6 6 0 0 1-6 6 6 6 0 0 1-4.24-1.76" stroke="currentColor" stroke-width="2"/><path d="M14.24 4.76 16 3v3.5h-3.5M5.76 15.24 4 17v-3.5h3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      Стартирай синхронизация
    </button>
    <div id="sync-status" class="text-sm text-muted" style="display:flex;align-items:center"></div>
    <a href="/products?upload_status=NOT_UPLOADED" class="btn btn-ghost">
      Виж продукти за качване (<?= $notUploaded ?>)
    </a>
    <a href="/pricing" class="btn btn-ghost">
      Изчисли цени по пазари
    </a>
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
          <th>Резултат</th>
          <th>Статус</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $p): ?>
        <tr>
          <td class="text-muted text-sm" style="font-family:monospace"><?= htmlspecialchars($p['EAN Amazon'] ?? '—') ?></td>
          <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= htmlspecialchars($p['Модел'] ?? '—') ?>
          </td>
          <td><span class="badge badge-muted"><?= htmlspecialchars($p['Доставчик'] ?? '—') ?></span></td>
          <td class="font-head"><?= number_format((float)($p['Цена Доставчик -Netto'] ?? 0), 2) ?></td>
          <td>
            <?php $res = (float)($p['Резултат'] ?? 0); ?>
            <?php if ($res > 0): ?>
            <span class="price-down text-sm" style="color:var(--green)">+<?= number_format($res, 2) ?></span>
            <?php elseif ($res < 0): ?>
            <span class="price-up text-sm" style="color:var(--red)"><?= number_format($res, 2) ?></span>
            <?php else: ?>
            <span class="text-muted text-sm">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php $status = $p['_upload_status'] ?? 'NOT_UPLOADED'; ?>
            <span class="badge <?= $status === 'UPLOADED' ? 'badge-green' : 'badge-gold' ?>">
              <?= $status === 'UPLOADED' ? 'Качен' : 'За качване' ?>
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
