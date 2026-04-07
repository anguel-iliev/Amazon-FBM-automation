<?php $shippingMode = Settings::get()['courier_shipping_mode'] ?? 'untracked'; ?>
<?php $couriers = $couriers ?? []; ?>
<?php $importSuccess = !empty($importSuccess); $importCourierId = $importCourierId ?? ''; $importCount = (int)($importCount ?? 0); $modeSaved = !empty($modeSaved); $deleteOk = !empty($deleteOk); $deleteCourierId = $deleteCourierId ?? ''; $historyDeleteOk = !empty($historyDeleteOk); $errorMessage = trim((string)($errorMessage ?? '')); ?>
<?php if ($modeSaved): ?><div class="alert alert-success" style="margin-bottom:12px">Режимът по подразбиране е запазен успешно.</div><?php endif; ?>
<?php if ($importSuccess): ?><div class="alert alert-success" style="margin-bottom:12px">Успешен импорт на цени за куриер. Куриер ID: <?= htmlspecialchars($importCourierId) ?> · Записани редове: <?= (int)$importCount ?></div><?php endif; ?>
<?php if ($deleteOk): ?><div class="alert alert-success" style="margin-bottom:12px">Ценовите записи за избрания куриер са изтрити успешно.</div><?php endif; ?>
<?php if ($errorMessage !== ''): ?><div class="alert alert-danger" style="margin-bottom:12px"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
<div class="muted-note" style="margin:-4px 0 12px 2px">Всички текущи и нови продукти използват глобалния активен куриер. Режимът за доставка по подразбиране е зададен глобално, но може да се override-не на ниво продукт през колоната „Режим доставка“.</div>
<form method="POST" action="/couriers/save-mode" style="display:flex;gap:10px;align-items:center;margin:0 0 14px 2px"><?= View::csrfField() ?><label class="form-label" style="margin:0">Режим по подразбиране</label><select name="shipping_mode" class="form-control form-control-sm" style="max-width:220px"><option value="untracked" <?= $shippingMode==='untracked'?'selected':'' ?>>Без проследяване</option><option value="tracked" <?= $shippingMode==='tracked'?'selected':'' ?>>С проследяване</option></select><button class="btn btn-primary btn-sm" type="submit">Запази режима</button></form>
<div class="page-header"><div><span style="font-size:13px;color:rgba(255,255,255,0.5)"><?= count($couriers) ?> куриера</span></div><div class="page-header-actions"><button class="btn btn-primary btn-sm" onclick="openCourierModal()">+ Добави куриер</button></div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:14px">
<?php foreach($couriers as $c): ?><div class="card"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><div><div class="card-title" style="margin:0"><?= htmlspecialchars($c['name']) ?></div><div class="muted-note"><?= (int)($c['rate_count'] ?? 0) ?> активни реда цени</div></div><div style="display:flex;gap:8px;align-items:center"><?php if(!empty($c['active'])): ?><span class="badge badge-green">Глобален активен куриер</span><?php else: ?><form method="POST" action="/couriers/activate" style="margin:0"><?= View::csrfField() ?><input type="hidden" name="courier_id" value="<?= htmlspecialchars($c['id']) ?>"><button class="btn btn-ghost btn-sm" type="submit">Направи глобален</button></form><?php endif; ?></div></div><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><a class="btn btn-ghost btn-sm" href="/couriers/template">↓ Свали шаблон .xlsx</a><a class="btn btn-ghost btn-sm" href="/couriers/export?id=<?= urlencode($c['id']) ?>">↓ Export</a></div><form method="POST" action="/couriers/import" enctype="multipart/form-data" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><?= View::csrfField() ?><input type="hidden" name="courier_id" value="<?= htmlspecialchars($c['id']) ?>"><input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.csv" style="max-width:240px"><button class="btn btn-primary btn-sm" type="submit">↑ Import</button></form><form method="POST" action="/couriers/delete-rates" onsubmit="return confirmCourierDelete(this, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" style="margin-top:10px"><?= View::csrfField() ?><input type="hidden" name="courier_id" value="<?= htmlspecialchars($c['id']) ?>"><input type="hidden" name="confirm_delete" value=""><input type="hidden" name="confirm_phrase" value=""><button class="btn btn-danger btn-sm" type="submit">Изтрий записи цени доставки</button></form><form method="POST" action="/couriers/delete" onsubmit="return confirmCourierRemove(this, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" style="margin-top:10px"><?= View::csrfField() ?><input type="hidden" name="courier_id" value="<?= htmlspecialchars($c['id']) ?>"><input type="hidden" name="confirm_delete" value=""><input type="hidden" name="confirm_phrase" value=""><button class="btn btn-danger btn-sm" type="submit">Изтрий куриер</button></form><?php if (!empty($c['imports'])): ?><div style="margin-top:14px;border-top:1px solid var(--border);padding-top:10px"><div class="muted-note" style="margin-bottom:8px">История на качените файлове</div><?php foreach(($c['imports'] ?? []) as $imp): ?><div id="import-row-<?= (int)$imp['id'] ?>" style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px"><div style="font-size:13px;color:rgba(255,255,255,.78)"><?= htmlspecialchars($imp['original_filename']) ?><br><span class="muted-note"><?= htmlspecialchars($c['name']) ?> · <?= (int)($imp['row_count'] ?? 0) ?> реда · <?= htmlspecialchars($imp['created_at'] ?? '') ?></span></div><div style="display:flex;gap:6px;align-items:center"><a class="btn btn-ghost btn-sm" href="/couriers/download-import?id=<?= (int)$imp['id'] ?>">Свали</a><form method="POST" action="/couriers/delete-import" onsubmit="return submitCourierHistoryDelete(this, '<?= htmlspecialchars($imp['original_filename'], ENT_QUOTES) ?>', <?= (int)$imp['id'] ?>)" style="margin:0"><?= View::csrfField() ?><input type="hidden" name="courier_id" value="<?= htmlspecialchars($c['id']) ?>"><input type="hidden" name="import_id" value="<?= (int)$imp['id'] ?>"><input type="hidden" name="confirm_delete" value=""><input type="hidden" name="confirm_phrase" value=""><button class="btn btn-danger btn-sm" type="submit">Изтрий</button></form></div></div><?php endforeach; ?></div><?php endif; ?></div><?php endforeach; ?>
</div>
<div id="courier-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center"><div style="background:var(--panel);border:1px solid var(--border2);border-radius:12px;padding:24px;width:420px;max-width:94vw"><div class="card-title">Добави куриер</div><form method="POST" action="/couriers/save"><?= View::csrfField() ?><div class="form-group"><label class="form-label">Име</label><input type="text" name="name" class="form-control" required></div><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="active" value="1" checked><span>Активен</span></label><div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button type="button" class="btn btn-ghost" onclick="closeCourierModal()">Отказ</button><button type="submit" class="btn btn-primary">Запази</button></div></form></div></div>
<script>
function openCourierModal(){document.getElementById('courier-modal').style.display='flex'}
function closeCourierModal(){document.getElementById('courier-modal').style.display='none'}
function confirmCourierDelete(form, courierName){
  if(!confirm('Сигурни ли сте, че искате да изтриете ценовите записи на куриер: ' + courierName + '?')) return false;
  if(!confirm('Това ще изтрие само цените за доставка за този куриер. Продължавате ли?')) return false;
  form.querySelector('input[name="confirm_delete"]').value='YES';
  form.querySelector('input[name="confirm_phrase"]').value='DELETE';
  return true;
}
function confirmCourierRemove(form, courierName){
  if(!confirm('Сигурни ли сте, че искате да изтриете куриер: ' + courierName + '?')) return false;
  if(!confirm('Това ще изтрие куриера, ценовите му записи и историята на импортите. Продължавате ли?')) return false;
  form.querySelector('input[name="confirm_delete"]').value='YES';
  form.querySelector('input[name="confirm_phrase"]').value='DELETE';
  return true;
}

function submitCourierHistoryDelete(form, fileName, rowId){
  if(!confirm('Сигурни ли сте, че искате да изтриете файла от историята: ' + fileName + '?')) return false;
  if(!confirm('Това ще изтрие само този запис от историята. Продължавате ли?')) return false;
  form.querySelector('input[name="confirm_delete"]').value='YES';
  form.querySelector('input[name="confirm_phrase"]').value='DELETE';

  const fd = new FormData(form);
  fetch(form.action, {
    method: 'POST',
    body: fd,
    credentials: 'same-origin',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    }
  })
  .then(async r => {
    const txt = await r.text();
    try { return JSON.parse(txt); } catch(e) { throw new Error(txt || 'Невалиден отговор от сървъра'); }
  })
  .then(d => {
    if (!d.success) throw new Error(d.error || 'Грешка при изтриването');
    const row = document.getElementById('import-row-' + rowId);
    if (row) row.remove();
  })
  .catch(err => {
    alert(err.message || 'Грешка при изтриването');
  });

  return false;
}


</script>
