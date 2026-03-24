<?php
$activeTab = 'vat';
include __DIR__ . '/_tabs.php';

$s     = $settings ?? [];
$mkts  = $s['marketplaces'] ?? [];
$codes = ['DE','FR','IT','ES','NL','PL','SE'];
$flags = ['DE'=>'🇩🇪','FR'=>'🇫🇷','IT'=>'🇮🇹','ES'=>'🇪🇸','NL'=>'🇳🇱','PL'=>'🇵🇱','SE'=>'🇸🇪'];
$names = ['DE'=>'Germany','FR'=>'France','IT'=>'Italy','ES'=>'Spain','NL'=>'Netherlands','PL'=>'Poland','SE'=>'Sweden'];
?>

<form method="POST" action="/settings/save">
  <input type="hidden" name="_section" value="vat">

  <!-- Marketplace table -->
  <div class="card">
    <div class="card-title">Настройки по пазари</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="min-width:150px">Пазар</th>
            <th>ДДС %</th>
            <th>Amazon Fee %</th>
            <th>Доставка €</th>
            <th>FBM такса €</th>
            <th>Мин. марж %</th>
            <th>Активен</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($codes as $code): ?>
          <?php $cfg = $mkts[$code] ?? []; ?>
          <tr>
            <td>
              <span style="font-size:20px;margin-right:8px"><?= $flags[$code] ?></span>
              <strong><?= $names[$code] ?></strong>
            </td>
            <td>
              <input type="number" name="vat_<?= $code ?>" class="form-control"
                     style="width:72px" step="0.1" min="0" max="100"
                     value="<?= round(($cfg['vat'] ?? 0) * 100, 1) ?>">
            </td>
            <td>
              <input type="number" name="amazon_fee_<?= $code ?>" class="form-control"
                     style="width:72px" step="0.1" min="0" max="100"
                     value="<?= round(($cfg['amazon_fee'] ?? 0.15) * 100, 1) ?>">
            </td>
            <td>
              <input type="number" name="shipping_<?= $code ?>" class="form-control"
                     style="width:80px" step="0.01" min="0"
                     value="<?= number_format($cfg['shipping'] ?? 4.50, 2) ?>">
            </td>
            <td>
              <input type="number" name="fbm_fee_<?= $code ?>" class="form-control"
                     style="width:72px" step="0.01" min="0"
                     value="<?= number_format($cfg['fbm_fee'] ?? 1.00, 2) ?>">
            </td>
            <td>
              <input type="number" name="min_margin_<?= $code ?>" class="form-control"
                     style="width:72px" step="1" min="0" max="100"
                     value="<?= round(($cfg['min_margin'] ?? ($s['min_margin'] ?? 0.15)) * 100) ?>">
            </td>
            <td style="text-align:center">
              <input type="checkbox" name="active_<?= $code ?>"
                     <?= ($cfg['active'] ?? false) ? 'checked' : '' ?>>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- General -->
  <div class="card mt-16">
    <div class="card-title">Общи настройки</div>
    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Глобален минимален марж %</label>
        <input type="number" name="min_margin" class="form-control"
               value="<?= round(($s['min_margin'] ?? 0.15) * 100) ?>"
               min="0" max="100" step="1">
        <div class="text-sm text-muted mt-4">Използва се за пазари без индивидуален марж</div>
      </div>
      <div class="form-group" style="padding-top:28px">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
          <input type="checkbox" name="sync_auto" <?= ($s['sync_auto'] ?? true) ? 'checked' : '' ?>>
          <div>
            <div style="font-size:13px;font-weight:600;color:#fff">Автоматична синхронизация</div>
            <div class="text-sm text-muted">Стартира от cron job</div>
          </div>
        </label>
      </div>
    </div>
  </div>

  <div class="mt-16" style="display:flex;gap:10px">
    <button type="submit" class="btn btn-primary">Запази настройките</button>
    <a href="/settings/vat" class="btn btn-ghost">Отказ</a>
  </div>
</form>
