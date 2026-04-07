<?php
$s        = $stats   ?? [];
$logs     = $logs    ?? [];
$recent   = $recent  ?? [];
$lastSync = $lastSync?? null;
$avgRez   = $s['avgRez'] ?? 0;
$posRez   = $s['posRez'] ?? 0;
$negRez   = $s['negRez'] ?? 0;

$supplierCount = $s['suppliers'] ?? 0;
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Общо продукти</div>
    <div class="stat-value"><?= number_format($s['total'] ?? 0) ?></div>
    <div class="stat-sub"><?= $supplierCount ?> доставчика</div>
  </div>
  <div class="stat-card accent-green">
    <div class="stat-label">С ASIN</div>
    <div class="stat-value"><?= number_format($s['withAsin'] ?? 0) ?></div>
    <div class="stat-sub"><?= ($s['total'] ?? 0) > 0 ? round(($s['withAsin'] ?? 0) / $s['total'] * 100) : 0 ?>% от общо</div>
  </div>
  <div class="stat-card accent-amber">
    <div class="stat-label">За качване</div>
    <div class="stat-value"><?= number_format($s['notUploaded'] ?? 0) ?></div>
    <div class="stat-sub">NOT_UPLOADED</div>
  </div>
  <div class="stat-card accent-blue">
    <div class="stat-label">Последна промяна</div>
    <div class="stat-value" style="font-size:18px;line-height:1.3">
      <?= $lastSync ? date('d.m H:i', strtotime($lastSync)) : '—' ?>
    </div>
    <div class="stat-sub"><?= $lastSync ? date('Y', strtotime($lastSync)) : 'Никога' ?></div>
  </div>
</div>

<!-- Финансов резултат (бивш Усреднен резултат) -->
<div class="card mt-16">
  <div class="flex-between mb-16">
    <div class="card-title" style="margin:0">Финансов резултат</div>
    <a href="/products?sort=Резултат&dir=desc" class="btn btn-ghost btn-sm">Сортирай →</a>
  </div>
  <div class="grid-3" style="grid-template-columns:repeat(3,1fr)">
    <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;position:relative;overflow:hidden">
      <div style="position:absolute;top:0;left:0;right:0;height:2px;background:var(--gold)"></div>
      <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Среден резултат</div>
      <div style="font-family:var(--font-head);font-size:28px;font-weight:800;color:<?= $avgRez >= 0 ? 'var(--green)' : 'var(--red)' ?>">
        <?= ($avgRez >= 0 ? '+' : '') . number_format($avgRez, 2) ?> €
      </div>
      <div class="text-sm text-muted mt-4">на продукт</div>
    </div>
    <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;position:relative;overflow:hidden">
      <div style="position:absolute;top:0;left:0;right:0;height:2px;background:var(--green)"></div>
      <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Печеливши</div>
      <div style="font-family:var(--font-head);font-size:28px;font-weight:800;color:var(--green)"><?= number_format($posRez) ?></div>
      <div class="text-sm text-muted mt-4">Резултат &gt; 0</div>
    </div>
    <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;position:relative;overflow:hidden">
      <div style="position:absolute;top:0;left:0;right:0;height:2px;background:var(--red)"></div>
      <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Губещи</div>
      <div style="font-family:var(--font-head);font-size:28px;font-weight:800;color:var(--red)"><?= number_format($negRez) ?></div>
      <div class="text-sm text-muted mt-4">Резултат ≤ 0</div>
    </div>
  </div>
</div>

<!-- Последно добавени продукти -->
<div class="card mt-16">
  <div class="flex-between mb-16">
    <div class="card-title" style="margin:0">Последно добавени продукти</div>
    <a href="/products" class="btn btn-ghost btn-sm">Всички →</a>
  </div>
  <?php if (!empty($recent)): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>EAN</th><th>Продукт</th><th>Доставчик</th><th>Цена €</th><th>Резултат</th><th>Статус</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $p): ?>
        <tr>
          <td class="text-muted text-sm" style="font-family:monospace"><?= htmlspecialchars($p['EAN Amazon'] ?? '—') ?></td>
          <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($p['Модел'] ?? '—') ?></td>
          <td><span class="badge badge-muted"><?= htmlspecialchars($p['Доставчик'] ?? '—') ?></span></td>
          <td class="font-head"><?= number_format((float)($p['Цена Доставчик -Netto'] ?? 0), 2) ?></td>
          <td><?php $res=(float)($p['Резултат']??0);
            if($res>0) echo '<span style="color:var(--green)">+'.number_format($res,2).'</span>';
            elseif($res<0) echo '<span style="color:var(--red)">'.number_format($res,2).'</span>';
            else echo '<span class="text-muted">—</span>'; ?>
          </td>
          <td><span class="badge <?= ($p['_upload_status']??'')!=='UPLOADED'?'badge-gold':'badge-green' ?>"><?= ($p['_upload_status']??'')!=='UPLOADED'?'За качване':'Качен' ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p class="text-muted text-sm" style="padding:12px 0">Няма продукти. <a href="/products/import" style="color:var(--gold)">Импортирай Excel →</a></p>
  <?php endif; ?>
</div>
