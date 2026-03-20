<?php
$driveId  = $settings['drive_folder_id'] ?? '';
$sheetId  = $settings['google_sheet_id'] ?? '';
?>
<div class="page-header">
  <div></div>
  <div class="page-header-actions">
    <form method="POST" action="/sync/run">
      <button type="submit" class="btn btn-primary"
              data-confirm="Стартиране на синхронизация — това ще прочете всички файлове от Google Drive и ще обнови централната таблица. Продължи?">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 0 1 6-6 6 6 0 0 1 4.24 1.76M16 10a6 6 0 0 1-6 6 6 6 0 0 1-4.24-1.76" stroke="currentColor" stroke-width="2"/><path d="M14.24 4.76 16 3v3.5h-3.5M5.76 15.24 4 17v-3.5h3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Стартирай синхронизация
      </button>
    </form>
  </div>
</div>

<div class="grid-2">

  <!-- Status card -->
  <div class="card">
    <div class="card-title">Статус</div>
    <div style="display:flex;flex-direction:column;gap:14px">
      <div class="flex-between" style="padding:10px 0;border-bottom:1px solid var(--border)">
        <span class="text-muted text-sm">Последна синхронизация</span>
        <span class="font-head text-sm"><?= $lastSync ? date('d.m.Y H:i', strtotime($lastSync)) : '—' ?></span>
      </div>
      <div class="flex-between" style="padding:10px 0;border-bottom:1px solid var(--border)">
        <span class="text-muted text-sm">Google Drive Folder</span>
        <?php if ($driveId): ?>
        <a href="https://drive.google.com/drive/folders/<?= htmlspecialchars($driveId) ?>" target="_blank"
           class="badge badge-green">Свързан ↗</a>
        <?php else: ?>
        <span class="badge badge-red">Не е настроен</span>
        <?php endif; ?>
      </div>
      <div class="flex-between" style="padding:10px 0">
        <span class="text-muted text-sm">Google Sheet</span>
        <?php if ($sheetId): ?>
        <a href="https://docs.google.com/spreadsheets/d/<?= htmlspecialchars($sheetId) ?>" target="_blank"
           class="badge badge-green">Свързан ↗</a>
        <?php else: ?>
        <span class="badge badge-gold">Локален кеш</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- How it works -->
  <div class="card">
    <div class="card-title">Как работи</div>
    <ol style="list-style:none;display:flex;flex-direction:column;gap:10px">
      <?php $steps = [
        ['Чете Excel/PDF файлове от Google Drive (папки на доставчиците)', 'blue'],
        ['Сравнява EAN/SKU с централната таблица', 'amber'],
        ['Нови продукти → добавя | Съществуващи → обновява цената', 'green'],
        ['Изчислява крайни цени по пазари (DE, FR, IT...)', 'gold'],
        ['Записва резултата в Google Sheets и локален кеш', 'blue'],
      ]; ?>
      <?php foreach ($steps as $i => [$text, $color]): ?>
      <li style="display:flex;gap:10px;align-items:flex-start">
        <span style="width:20px;height:20px;background:rgba(201,168,76,0.12);border:1px solid rgba(201,168,76,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold);flex-shrink:0;margin-top:1px"><?= $i+1 ?></span>
        <span class="text-sm" style="color:var(--muted)"><?= $text ?></span>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>

</div>

<!-- Sync log -->
<div class="card mt-16" style="padding:0">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border)">
    <div class="card-title" style="margin:0">История на синхронизациите</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Дата</th><th>Качени</th><th>Обновени</th><th>Нови</th><th>Грешки</th><th>Продължителност</th><th>Статус</th></tr>
      </thead>
      <tbody>
        <?php if (empty($syncLog)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">
          Няма записи. Стартирай първата синхронизация.
        </td></tr>
        <?php else: ?>
        <?php foreach ($syncLog as $log): ?>
        <tr>
          <td class="text-sm"><?= date('d.m.Y H:i:s', strtotime($log['date'])) ?></td>
          <td><?= $log['uploaded'] ?? 0 ?></td>
          <td><?= $log['updated'] ?? 0 ?></td>
          <td><?= $log['new'] ?? 0 ?></td>
          <td><?= $log['errors'] ?? 0 ?></td>
          <td class="text-muted text-sm"><?= isset($log['duration']) ? $log['duration'] . 's' : '—' ?></td>
          <td>
            <span class="badge <?= ($log['status'] ?? '') === 'success' ? 'badge-green' : 'badge-red' ?>">
              <?= ($log['status'] ?? '') === 'success' ? 'OK' : 'Грешка' ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
