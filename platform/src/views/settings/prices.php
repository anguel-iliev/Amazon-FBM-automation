<?php
$columns = $columns ?? [];
$formulaMap = $formulaMap ?? [];
$history = $formulaHistory ?? [];
$versions = $formulaVersions ?? [];
$formulasLocked = !empty($formulasLocked);
$columnNames = array_values(array_map(fn($c) => $c['name'], $columns));
$currentFormulaMap = [];
foreach ($columns as $c) { $currentFormulaMap[$c['name']] = $formulaMap[$c['name']]['expression'] ?? ''; }
?>
<style>
.formula-page-actions{display:flex;gap:10px;justify-content:flex-end;align-items:center;flex-wrap:wrap;margin-bottom:14px}.formula-import-form{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.formula-shell{display:grid;grid-template-columns:minmax(420px,.95fr) minmax(560px,1.25fr);gap:18px;align-items:start}
@media (max-width:1300px){.formula-shell{grid-template-columns:1fr}}
.formula-table td,.formula-table th{vertical-align:middle}
.formula-table tr.is-active td{background:rgba(201,168,76,.08)}
.formula-status{display:inline-flex;align-items:center;gap:8px;font-size:12px;padding:7px 12px;border-radius:999px;border:1px solid var(--border2);background:rgba(255,255,255,.03)}
.formula-status.is-formula{background:rgba(201,168,76,.14);border-color:rgba(201,168,76,.28);color:var(--gold-lt)}
.editor-shell{display:flex;flex-direction:column;gap:14px}
.editor-top{display:grid;grid-template-columns:minmax(220px,.62fr) minmax(190px,.58fr) auto 110px;gap:10px;align-items:end;justify-content:start;padding-left:34px}
@media (max-width:1100px){.editor-top{grid-template-columns:1fr;padding-left:0}}
.formula-current{padding:14px 16px;border-radius:16px;background:linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.02));border:1px solid var(--border2);min-height:82px;max-width:300px}
.formula-current .eyebrow{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:6px}
.formula-current .value{font-size:18px;line-height:1.2;font-weight:800;color:var(--text)}
.editor-top .form-group{margin:0}
.editor-top .form-label{margin-bottom:6px}
.editor-top .form-control{min-width:140px}
.formula-canvas{padding:16px;border:1px solid var(--border2);border-radius:16px;background:#0B1018}
.formula-flow{display:flex;align-items:center;gap:10px;flex-wrap:wrap;min-height:112px;padding:14px;border-radius:16px;border:1px dashed rgba(201,168,76,.22);background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01))}
.formula-textbox{flex:1 1 220px;min-width:220px;border:none;background:transparent;color:var(--text);outline:none;padding:10px 6px;font-size:26px;font-weight:700;letter-spacing:.01em}
.formula-textbox::placeholder{color:#6F7889;font-weight:500;font-size:14px;letter-spacing:0}
.formula-chip{display:inline-flex;align-items:center;gap:8px;min-height:48px;border-radius:18px;padding:0 10px;border:1px solid var(--border2)}
.formula-chip.column{background:#141B26;border-color:#2D3645;color:#F8F4EA;box-shadow:inset 0 1px 0 rgba(255,255,255,.03)}
.formula-chip.text{background:rgba(255,255,255,.03);border-color:rgba(255,255,255,.05);padding:0 14px;font-size:26px;font-weight:800;color:var(--gold-lt)}
.chip-main{display:inline-flex;align-items:center;gap:10px;position:relative}
.chip-label-btn{display:inline-flex;align-items:center;gap:10px;border:none;background:transparent;color:inherit;font:inherit;padding:10px 4px 10px 2px;cursor:pointer}
.chip-label-btn span:first-child{font-size:14px;font-weight:700;line-height:1.2;max-width:240px;text-align:left}
.chip-arrow{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;background:rgba(201,168,76,.12);color:var(--gold-lt);font-size:11px}
.chip-remove{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border:none;border-radius:999px;background:rgba(255,255,255,.06);color:var(--muted);cursor:pointer;font-size:13px;padding:0}
.chip-remove:hover{color:#fff;background:rgba(255,255,255,.12)}
.chip-menu{position:absolute;top:calc(100% + 8px);left:0;z-index:40;width:300px;max-height:280px;overflow:auto;border-radius:14px;border:1px solid var(--border2);background:#111722;box-shadow:0 16px 40px rgba(0,0,0,.45);padding:8px;display:none}
.chip-menu.open{display:block}
.chip-menu button{display:block;width:100%;text-align:left;border:none;background:transparent;color:var(--text);padding:10px 12px;border-radius:10px;cursor:pointer;font:inherit}
.chip-menu button:hover{background:rgba(201,168,76,.12);color:var(--gold-lt)}
.formula-preview{display:flex;align-items:flex-start;gap:10px;margin-top:14px;padding:12px 14px;border-radius:14px;background:#111722;border:1px solid var(--border2);font-size:13px;color:var(--muted)}
.formula-preview strong{color:var(--gold-lt);white-space:nowrap}
.builder-actions{display:flex;gap:10px;flex-wrap:wrap}
.btn-insert-column{background:var(--gold);color:#0D0F14;font-weight:700;border-color:rgba(201,168,76,.35)}
.btn-insert-column:hover{background:var(--gold-lt);color:#0D0F14}
.builder-error{display:none;padding:10px 12px;border-radius:10px;background:rgba(185,28,28,.15);border:1px solid rgba(185,28,28,.28);color:#FECACA}
.builder-error.show{display:block}
.history-table{max-height:360px;overflow:auto}
.copy-btn{display:inline-flex;align-items:center;justify-content:center;min-width:78px}
.muted-note{font-size:12px;color:var(--muted)}
.hide-mobile{display:table-cell}
.action-export{background:rgba(74,124,255,.16);color:#9CC0FF;border-color:rgba(74,124,255,.35)}
.action-export:hover{background:rgba(74,124,255,.24);color:#fff}
.action-lock{background:rgba(224,92,92,.14);color:#FFB4B4;border-color:rgba(224,92,92,.35)}
.action-lock:hover{background:rgba(224,92,92,.22);color:#fff}
.action-template{background:rgba(61,187,127,.14);color:#8DE0B6;border-color:rgba(61,187,127,.35)}
.action-template:hover{background:rgba(61,187,127,.22);color:#fff}
.action-preview{background:rgba(245,158,11,.16);color:#FFD28A;border-color:rgba(245,158,11,.35)}
.action-preview:hover{background:rgba(245,158,11,.24);color:#fff}
.action-import{background:linear-gradient(180deg, var(--gold-lt), var(--gold));color:#0D0F14;border-color:rgba(201,168,76,.5);font-weight:800}
.action-import:hover{background:linear-gradient(180deg, #F8E2A6, var(--gold-lt));color:#0D0F14}
.formula-import-file{min-width:260px;border-color:rgba(74,124,255,.28);background:rgba(74,124,255,.08);color:#EAF2FF}
.formula-import-file:hover{border-color:rgba(74,124,255,.42)}
.formula-import-file::file-selector-button{margin:-8px 12px -8px -10px;padding:8px 12px;border:none;border-right:1px solid rgba(74,124,255,.35);background:rgba(74,124,255,.18);color:#B9D2FF;font-weight:700;cursor:pointer}
.formula-import-file:hover::file-selector-button{background:rgba(74,124,255,.26);color:#fff}

@media (max-width:1000px){.hide-mobile{display:none}}
</style>


<?php if ($formulasLocked): ?>
<div class="card" style="margin-bottom:14px;border-color:rgba(201,168,76,.3);background:rgba(201,168,76,.08)">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <div><strong style="color:var(--gold-lt)">Формулите са заключени</strong><div class="muted-note" style="margin-top:4px">Редакция и импорт са изключени, докато не ги отключиш.</div></div>
    <button type="button" class="btn btn-primary btn-sm" onclick="toggleFormulaLock(false,this)">Отключи формули</button>
  </div>
</div>
<?php endif; ?>
<div class="formula-page-actions">
  <a href="/settings/formulas/export-xlsx" class="btn btn-ghost btn-sm action-export">Експорт формули .xlsx</a>
  <?php if (!$formulasLocked): ?>
  <button type="button" class="btn btn-ghost btn-sm action-lock" onclick="toggleFormulaLock(true,this)">Заключи формули</button>
  <?php endif; ?>
  <a href="/settings/formulas/template" class="btn btn-ghost btn-sm action-template">Свали шаблон .xlsx</a>
  <form class="formula-import-form" id="formula-import-form" onsubmit="return false;">
    <input type="file" id="formula-import-file" accept=".xlsx" class="form-control form-control-sm formula-import-file" <?= $formulasLocked ? 'disabled' : '' ?>>
    <button type="button" class="btn btn-ghost btn-sm action-preview" onclick="previewFormulaImport(this)" <?= $formulasLocked ? 'disabled' : '' ?>>Провери файла</button>
    <button type="button" class="btn btn-primary btn-sm action-import" id="apply-formula-import-btn" onclick="applyFormulaImport(this)" disabled>Импорт формули</button>
  </form>
</div>
<div class="card" id="formula-import-preview-card" style="display:none;margin-bottom:16px;">
  <div class="card-title">Проверка преди импорт</div>
  <div id="formula-import-summary" class="muted-note" style="margin-bottom:10px"></div>
  <div class="table-wrap" style="max-height:320px;overflow:auto;">
    <table>
      <thead><tr><th>Ред</th><th>Колона</th><th>Действие</th><th>Текуща формула</th><th>Нова формула</th><th>Грешка</th></tr></thead>
      <tbody id="formula-import-preview-body"></tbody>
    </table>
  </div>
</div>

<div class="formula-shell">
  <div class="card formula-table">
    <div class="card-title">Колони и статус на формули</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Колона</th><th>Статус</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($columns as $col): ?>
          <tr data-column-name="<?= htmlspecialchars($col['name'], ENT_QUOTES) ?>">
            <td><?= htmlspecialchars($col['name']) ?></td>
            <td>
              <span class="formula-status <?= !empty($col['is_formula']) ? 'is-formula' : '' ?>">
                <?= !empty($col['is_formula']) ? 'Изчислима' : 'Статична' ?>
              </span>
            </td>
            <td style="text-align:right"><button class="btn btn-ghost btn-sm" onclick='openFormulaEditor(<?= json_encode($col, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_AMP) ?>)'>Редактирай формула</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Редактирай формула</div>
    <div class="editor-shell">
      <div class="editor-top">
        <div class="formula-current">
          <div class="eyebrow">Избрана колона</div>
          <div class="value" id="formula-column-label">Избери колона от списъка</div>
        </div>
        <div class="form-group">
          <label class="form-label">+ Колона</label>
          <select id="builder-column-select" class="form-control" <?= $formulasLocked ? 'disabled' : '' ?>>
            <option value="">Избери колона…</option>
            <?php foreach ($columns as $col): ?>
              <option value="<?= htmlspecialchars($col['name']) ?>"><?= htmlspecialchars($col['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">&nbsp;</label>
          <button class="btn btn-insert-column" type="button" onclick="insertSelectedColumn()" <?= $formulasLocked ? 'disabled' : '' ?>>Вмъкни колона</button>
        </div>
        <div class="form-group">
          <label class="form-label">Закръгляне</label>
          <select id="formula-rounding" class="form-control" style="min-width:100px" onchange="updateRestoreButton()" <?= $formulasLocked ? 'disabled' : '' ?>>
            <option value="0">0</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option>
          </select>
        </div>
      </div>

      <div class="formula-canvas">
        <div class="formula-flow" id="formula-flow"></div>
        <div class="formula-preview"><strong>Преглед:</strong> <span id="formula-preview">—</span></div>
      </div>

      <div class="builder-actions">
        <button class="btn btn-ghost" type="button" onclick="restoreFormulaDraft()" id="restore-formula-btn" disabled>Възстанови формула</button>
        <button class="btn btn-primary" type="button" onclick="saveFormula()" <?= $formulasLocked ? 'disabled' : '' ?>>Запази формула</button>
        <button class="btn btn-danger" type="button" onclick="clearFormula()" <?= $formulasLocked ? 'disabled' : '' ?>>Премахни формула</button>
      </div>
      <div class="builder-error" id="builder-error"></div>
    </div>
  </div>
</div>

<div class="card mt-16">
  <div class="card-title">История на промените</div>
  <div class="history-table">
    <table>
      <thead><tr><th>Кога</th><th>Колона</th><th class="hide-mobile">Формула</th><th>Copy</th><th>Възстанови</th><th>Променено от</th></tr></thead>
      <tbody>
      <?php if (!$versions): ?>
        <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--muted)">Няма история</td></tr>
      <?php endif; ?>
      <?php foreach ($versions as $h): ?>
        <tr>
          <td><?= !empty($h['created_at']) ? date('d.m.Y H:i', strtotime($h['created_at'])) : '—' ?></td>
          <td><?= htmlspecialchars($h['column_name'] ?? '') ?></td>
          <td class="hide-mobile" style="max-width:380px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($h['formula_expression'] ?? '') ?></td>
          <td>
            <?php $copyFormula = (string)($h['formula_expression'] ?? ''); ?>
            <button type="button" class="btn btn-ghost btn-sm copy-btn" <?= $copyFormula === '' ? 'disabled' : '' ?> onclick='copyFormulaToClipboard(<?= json_encode($copyFormula, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_AMP) ?>, this)'>Copy</button>
          </td>
          <td><button type="button" class="btn btn-ghost btn-sm" <?= ($formulasLocked || empty($h['id'])) ? 'disabled' : '' ?> onclick="restoreFormulaVersion(<?= (int)($h['id'] ?? 0) ?>, this)">Restore</button></td>
          <td><?= htmlspecialchars($h['changed_by'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const SETTINGS_CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const FORMULA_MAP = <?= json_encode($formulaMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const COLUMN_OPTIONS = <?= json_encode($columnNames, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const CURRENT_FORMULAS = <?= json_encode($currentFormulaMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const FORMULAS_LOCKED = <?= $formulasLocked ? 'true' : 'false' ?>;
let currentColumn = '';
let currentTokens = [];
let draftText = '';
let pristineTokens = [];
let pristineRounding = '2';

let formulaImportReady = false;
function getFormulaImportFile(){
  const input = document.getElementById('formula-import-file');
  return input && input.files && input.files[0] ? input.files[0] : null;
}
function renderImportPreview(data){
  const card = document.getElementById('formula-import-preview-card');
  const body = document.getElementById('formula-import-preview-body');
  const summary = document.getElementById('formula-import-summary');
  body.innerHTML = '';
  const rows = data.rows || [];
  rows.forEach(r => {
    const tr = document.createElement('tr');
    const err = r.error || '';
    tr.innerHTML = `<td>${escapeHtml(String(r.row ?? ''))}</td><td>${escapeHtml(r.column || '')}</td><td>${escapeHtml(r.action || '')}</td><td style="max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(r.current_formula || '—')}</td><td style="max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(r.new_formula || '—')}</td><td style="color:${err ? 'var(--red)' : 'var(--muted)'}">${escapeHtml(err || '—')}</td>`;
    body.appendChild(tr);
  });
  const s = data.summary || {};
  summary.textContent = `За прилагане: ${s.apply || 0}, за изчистване: ${s.clear || 0}, без промяна: ${s.skip || 0}, грешки: ${s.error || 0}`;
  card.style.display = 'block';
  formulaImportReady = !!data.ok && (rows.length > 0);
  document.getElementById('apply-formula-import-btn').disabled = !formulaImportReady;
}
function previewFormulaImport(btn){
  if (FORMULAS_LOCKED) { alert('Формулите са заключени.'); return; }
    const file = getFormulaImportFile();
  if (!file) { alert('Избери .xlsx файл за формули.'); return; }
  const fd = new FormData();
  fd.append('file', file, file.name);
  const orig = btn.textContent; btn.disabled = true; btn.textContent = 'Проверка…';
  fetch('/settings/formulas/preview-import', {method:'POST', headers:{'X-CSRF-Token': SETTINGS_CSRF_TOKEN}, body:fd})
    .then(async r => { const d = await r.json().catch(()=>({ok:false,error:'Невалиден отговор.'})); if(!r.ok || d.ok===false) throw new Error(d.error || 'Грешка при проверка.'); return d; })
    .then(d => renderImportPreview(d))
    .catch(e => alert(e.message || 'Грешка при проверка.'))
    .finally(() => { btn.disabled = false; btn.textContent = orig; });
}
function applyFormulaImport(btn){
  if (FORMULAS_LOCKED) { alert('Формулите са заключени.'); return; }
    const file = getFormulaImportFile();
  if (!file) { alert('Избери .xlsx файл за формули.'); return; }
  if (!formulaImportReady && !confirm('Файлът не е проверен. Да продължа ли?')) return;
  const fd = new FormData();
  fd.append('file', file, file.name);
  const orig = btn.textContent; btn.disabled = true; btn.textContent = 'Импорт…';
  fetch('/settings/formulas/import', {method:'POST', headers:{'X-CSRF-Token': SETTINGS_CSRF_TOKEN}, body:fd})
    .then(async r => { const d = await r.json().catch(()=>({ok:false,error:'Невалиден отговор.'})); if(!r.ok || d.ok===false) throw new Error((d.errors && d.errors[0]) || d.error || 'Грешка при импорт.'); return d; })
    .then(d => { alert(d.message || 'Формулите са импортирани.'); location.reload(); })
    .catch(e => alert(e.message || 'Грешка при импорт.'))
    .finally(() => { btn.disabled = false; btn.textContent = orig; });
}

function toggleFormulaLock(locked, btn){
  const fd = new FormData(); fd.append('locked', locked ? '1' : '0');
  const orig = btn ? btn.textContent : ''; if (btn) { btn.disabled = true; btn.textContent = locked ? 'Заключване…' : 'Отключване…'; }
  fetch('/settings/formulas/lock', {method:'POST', headers:{'X-CSRF-Token': SETTINGS_CSRF_TOKEN}, body:fd})
    .then(async r => { const d = await r.json().catch(()=>({ok:false,error:'Невалиден отговор.'})); if(!r.ok || d.ok===false) throw new Error(d.error || 'Грешка.'); return d; })
    .then(() => location.reload())
    .catch(e => alert(e.message || 'Грешка при смяна на режима.'))
    .finally(() => { if (btn) { btn.disabled = false; btn.textContent = orig; } });
}

function restoreFormulaVersion(versionId, btn){
  if (!versionId) return;
  if (!confirm('Да възстановя избраната версия на формулата?')) return;
  const fd = new FormData(); fd.append('version_id', String(versionId));
  const orig = btn ? btn.textContent : ''; if (btn) { btn.disabled = true; btn.textContent = 'Restore…'; }
  fetch('/settings/formulas/restore-version', {method:'POST', headers:{'X-CSRF-Token': SETTINGS_CSRF_TOKEN}, body:fd})
    .then(async r => { const d = await r.json().catch(()=>({ok:false,error:'Невалиден отговор.'})); if(!r.ok || d.ok===false) throw new Error(d.error || 'Грешка при възстановяване.'); return d; })
    .then(() => location.reload())
    .catch(e => alert(e.message || 'Грешка при възстановяване.'))
    .finally(() => { if (btn) { btn.disabled = false; btn.textContent = orig; } });
}

function openFormulaEditor(col){
  currentColumn = col.name;
  document.querySelectorAll('.formula-table tbody tr').forEach(tr => tr.classList.remove('is-active'));
  const row = Array.from(document.querySelectorAll('.formula-table tbody tr')).find(tr => tr.dataset.columnName === col.name);
  if (row) row.classList.add('is-active');
  document.getElementById('formula-column-label').textContent = col.name;
  const formula = FORMULA_MAP[col.name] || null;
  currentTokens = formula && Array.isArray(formula.tokens) ? JSON.parse(JSON.stringify(formula.tokens)) : [];
  pristineTokens = JSON.parse(JSON.stringify(currentTokens));
  draftText = '';
  pristineRounding = String(formula ? (formula.rounding ?? 2) : 2);
  document.getElementById('formula-rounding').value = pristineRounding;
  renderBuilder();
  window.scrollTo({top:0, behavior:'smooth'});
}

function normalizeTextTokens(){
  currentTokens = currentTokens.filter(t => !(t.type === 'text' && !String(t.value || '').trim()));
}

function renderBuilder(){
  const flow = document.getElementById('formula-flow');
  flow.innerHTML = '';

  currentTokens.forEach((token, idx) => {
    if (token.type === 'column') {
      const chip = document.createElement('span');
      chip.className = 'formula-chip column';

      const main = document.createElement('span');
      main.className = 'chip-main';

      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'chip-label-btn';
      toggle.innerHTML = `<span>${escapeHtml(token.value)}</span><span class="chip-arrow">▾</span>`;
      toggle.onclick = (e) => {
        e.stopPropagation();
        closeChipMenus();
        menu.classList.toggle('open');
      };

      const menu = document.createElement('div');
      menu.className = 'chip-menu';
      COLUMN_OPTIONS.forEach(name => {
        const item = document.createElement('button');
        item.type = 'button';
        item.textContent = name;
        item.onclick = () => { replaceColumnToken(idx, name); closeChipMenus(); };
        menu.appendChild(item);
      });

      main.appendChild(toggle);
      main.appendChild(menu);

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'chip-remove';
      remove.textContent = '×';
      remove.onclick = () => removeToken(idx);

      chip.appendChild(main);
      chip.appendChild(remove);
      flow.appendChild(chip);
    } else {
      const chip = document.createElement('span');
      chip.className = 'formula-chip text';
      chip.innerHTML = `<span>${escapeHtml(token.value)}</span>`;
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'chip-remove';
      remove.textContent = '×';
      remove.onclick = () => removeToken(idx);
      chip.appendChild(remove);
      flow.appendChild(chip);
    }
  });

  const input = document.createElement('input');
  input.type = 'text';
  input.id = 'formula-textbox';
  input.className = 'formula-textbox';
  input.placeholder = currentColumn ? 'Напиши число или оператор, например + 1.2 * ( )' : 'Избери колона от списъка вляво';
  input.value = draftText;
  input.disabled = !currentColumn || FORMULAS_LOCKED;
  input.oninput = () => { draftText = input.value; updatePreview(); };
  input.onkeydown = (e) => {
    if (e.key === 'Enter') { e.preventDefault(); commitDraftText(); }
  };
  input.onblur = () => commitDraftText();
  flow.appendChild(input);

  updatePreview();
  setTimeout(() => { if (currentColumn) input.focus(); }, 0);
}

function closeChipMenus(){
  document.querySelectorAll('.chip-menu.open').forEach(el => el.classList.remove('open'));
}

document.addEventListener('click', closeChipMenus);

function commitDraftText(){
  if (!draftText.trim()) return;
  currentTokens.push({type:'text', value:draftText.trim()});
  draftText = '';
  normalizeTextTokens();
  renderBuilder();
}

function insertSelectedColumn(){
  if (FORMULAS_LOCKED) return showError('Формулите са заключени.');
  if (!currentColumn) return showError('Първо избери колона от списъка вляво.');
  const select = document.getElementById('builder-column-select');
  const value = select.value;
  if (!value) return showError('Избери колона за вмъкване.');
  commitDraftText();
  currentTokens.push({type:'column', value});
  select.value = '';
  hideError();
  renderBuilder();
}

function replaceColumnToken(idx, value){
  currentTokens[idx] = {type:'column', value};
  renderBuilder();
}

function removeToken(idx){
  currentTokens.splice(idx, 1);
  renderBuilder();
}

function previewText(){
  const merged = [...currentTokens];
  if (draftText.trim()) merged.push({type:'text', value:draftText.trim()});
  if (!merged.length) return '—';
  return merged.map(token => token.value).join(' ');
}

function updatePreview(){
  document.getElementById('formula-preview').textContent = previewText();
  updateRestoreButton();
}

function currentStateSignature(){
  return JSON.stringify({tokens: currentTokens, draft: draftText.trim(), rounding: document.getElementById('formula-rounding')?.value || '2'});
}

function pristineStateSignature(){
  return JSON.stringify({tokens: pristineTokens, draft: '', rounding: pristineRounding});
}

function updateRestoreButton(){
  const btn = document.getElementById('restore-formula-btn');
  if (!btn) return;
  btn.disabled = !currentColumn || currentStateSignature() === pristineStateSignature();
}

function restoreFormulaDraft(){
  if (!currentColumn) return;
  currentTokens = JSON.parse(JSON.stringify(pristineTokens));
  draftText = '';
  document.getElementById('formula-rounding').value = pristineRounding;
  hideError();
  renderBuilder();
}

function saveFormula(){
  if (FORMULAS_LOCKED) return showError('Формулите са заключени.');
  if (!currentColumn) return showError('Избери колона от таблицата вляво.');
  commitDraftText();
  const fd = new FormData();
  fd.append('column_name', currentColumn);
  fd.append('tokens', JSON.stringify(currentTokens));
  fd.append('rounding', document.getElementById('formula-rounding').value || '2');

  fetch('/settings/save-formula', {method:'POST', headers:{'X-CSRF-Token': SETTINGS_CSRF_TOKEN}, body:fd})
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return showError(d.error || 'Грешка при запис.');
      hideError();
      location.reload();
    })
    .catch(() => showError('Мрежова грешка при запис.'));
}

function clearFormula(){
  if (FORMULAS_LOCKED) return showError('Формулите са заключени.');
  if (!currentColumn) return showError('Избери колона от таблицата вляво.');
  const fd = new FormData();
  fd.append('column_name', currentColumn);
  fetch('/settings/clear-formula', {method:'POST', headers:{'X-CSRF-Token': SETTINGS_CSRF_TOKEN}, body:fd})
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return showError(d.error || 'Грешка при изтриване.');
      hideError();
      location.reload();
    })
    .catch(() => showError('Мрежова грешка при изтриване.'));
}

function showError(msg){
  const box = document.getElementById('builder-error');
  box.textContent = msg;
  box.classList.add('show');
}
function hideError(){
  const box = document.getElementById('builder-error');
  box.textContent = '';
  box.classList.remove('show');
}
function escapeHtml(str){
  return String(str).replace(/[&<>\"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
}


function importFormulaFile(){
  const fileInput = document.getElementById('formula-import-file');
  const file = fileInput?.files?.[0];
  if (!file) return alert('Избери .xlsx файл с формули.');
  const fd = new FormData();
  fd.append('file', file, file.name);
  fetch('/settings/formulas/import', {method:'POST', headers:{'X-CSRF-Token': SETTINGS_CSRF_TOKEN}, body:fd})
    .then(r => r.json())
    .then(d => {
      if (!d.ok) {
        const err = Array.isArray(d.errors) && d.errors.length ? d.errors.join('\n') : (d.error || d.message || 'Грешка при импорт.');
        alert(err);
        return;
      }
      alert(d.message || 'Формулите бяха импортирани успешно.');
      location.reload();
    })
    .catch(() => alert('Мрежова грешка при импорт на формули.'));
}


function copyFormulaToClipboard(text, btn){
  if (!text) return;
  const original = btn ? btn.textContent : '';
  navigator.clipboard.writeText(text).then(() => {
    if (btn) { btn.textContent = 'Copied'; setTimeout(() => btn.textContent = original || 'Copy', 1200); }
  }).catch(() => {
    const area = document.createElement('textarea');
    area.value = text;
    document.body.appendChild(area);
    area.select();
    try { document.execCommand('copy'); if (btn) { btn.textContent = 'Copied'; setTimeout(() => btn.textContent = original || 'Copy', 1200); } } catch (e) {}
    area.remove();
  });
}
<?= count($columns) > 0 ? 'openFormulaEditor(' . json_encode($columns[0] ?? ['name' => ''], JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_AMP) . ');' : '' ?>
</script>
