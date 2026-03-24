<?php
// Shared settings submenu tabs helper
$settingsTabs = [
  'vat'          => ['label' => 'ДДС по пазари',   'href' => '/settings/vat'],
  'prices'       => ['label' => 'Редакция Цени',    'href' => '/settings/prices'],
  'formulas'     => ['label' => 'Формули',           'href' => '/settings/formulas'],
  'integrations' => ['label' => 'Интеграции',        'href' => '/settings/integrations'],
  'system'       => ['label' => 'Системни',          'href' => '/settings/system'],
];
?>
<div class="submenu-tabs">
  <?php foreach ($settingsTabs as $key => $tab): ?>
  <a href="<?= $tab['href'] ?>" class="submenu-tab <?= ($activeTab ?? '') === $key ? 'active' : '' ?>">
    <?= $tab['label'] ?>
  </a>
  <?php endforeach; ?>
</div>
