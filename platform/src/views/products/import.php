<?php $archives = $archives ?? []; ?>

<div class="page-header">
  <div></div>
  <div class="page-header-actions">
    <a href="/products/template" class="btn btn-ghost btn-sm">↓ Свали шаблон .xlsx</a>
    <a href="/products" class="btn btn-ghost btn-sm">← Към продукти</a>
  </div>
</div>

<div class="grid-2" style="align-items:start;gap:20px">

  <div class="card">
    <div class="card-title">Импортирай Excel файл</div>

    <input type="file" id="file-inp" accept=".xlsx" style="display:none" onchange="onFileChosen(this)">

    <!-- STEP 1: Choose file -->
    <div id="step-choose">
      <p class="text-sm text-muted" style="margin-bottom:16px;line-height:1.7">
        Натисни бутона за да избереш .xlsx файл от компютъра си.
      </p>
      <button class="btn btn-primary" style="width:100%;padding:16px;font-size:15px;font-weight:700" onclick="document.getElementById('file-inp').click()">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="margin-right:8px;vertical-align:middle"><path d="M4 6v-2a1 1 0 011-1h10a1 1 0 011 1v2M10 17V7M7 10l3-3 3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Избери Excel файл (.xlsx)
      </button>
      <p class="text-sm text-muted" style="margin-top:10px;text-align:center;opacity:.6">или провлачи файла върху тази страница</p>
    </div>

    <!-- STEP 2: File chosen -->
    <div id="step-import" style="display:none">

      <div style="background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.3);border-radius:6px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text)" id="chosen-name"></div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px" id="chosen-size"></div>
        </div>
        <button onclick="resetFile()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:12px;padding:4px 8px">✕ Смени</button>
      </div>

      <!-- 3 modes -->
      <div class="form-group">
        <label class="form-label">Режим на импорт</label>
        <div style="display:flex;flex-direction:column;gap:8px">

          <!-- FIRST IMPORT -->
          <label id="lbl-first" style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px 14px;border:2px solid var(--green);border-radius:6px;background:rgba(61,187,127,.05)">
            <input type="radio" name="mode" value="first" checked onchange="updateMode(this)" style="margin-top:2px;accent-color:var(--green)">
            <div>
              <div style="font-size:13px;font-weight:700;color:#5DCCA0">Първоначален импорт</div>
              <div style="font-size:12px;color:var(--muted);margin-top:2px">За празна база данни. Записва всички продукти директно. Без архив, без изтриване.</div>
            </div>
          </label>

          <!-- MERGE -->
          <label id="lbl-merge" style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px 14px;border:1px solid var(--border);border-radius:6px">
            <input type="radio" name="mode" value="merge" onchange="updateMode(this)" style="margin-top:2px;accent-color:var(--gold)">
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--text)">Добави само нови продукти</div>
              <div style="font-size:12px;color:var(--muted);margin-top:2px">Съществуващите (по EAN) НЕ се променят. Само нови EAN-и се добавят.</div>
            </div>
          </label>

          <!-- REPLACE -->
          <label id="lbl-replace" style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px 14px;border:1px solid var(--border);border-radius:6px">
            <input type="radio" name="mode" value="replace" onchange="updateMode(this)" style="margin-top:2px;accent-color:var(--gold)">
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--text)">Замени изцяло</div>
              <div style="font-size:12px;color:var(--muted);margin-top:2px">Текущите данни се архивират автоматично, после се заменят с новите.</div>
            </div>
          </label>

        </div>
      </div>

      <div id="archive-label-group" style="display:none" class="form-group">
        <label class="form-label">Наименование на архива (по желание)</label>
        <input type="text" id="archive-label" class="form-control" placeholder="напр. Преди актуализация март 2026">
      </div>

      <button class="btn btn-primary" id="import-btn" onclick="doImport()" style="width:100%;padding:14px;font-size:14px;margin-top:8px">
        Импортирай →
      </button>

      <div id="progress" style="display:none;margin-top:16px">
        <div style="height:6px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden;margin-bottom:10px">
          <div id="prog-fill" style="height:100%;background:var(--gold);border-radius:3px;transition:width .5s;width:5%"></div>
        </div>
        <div id="prog-text" style="font-size:12px;color:var(--muted);text-align:center"></div>
      </div>

      <div id="result" style="display:none;margin-top:16px;padding:14px 16px;border-radius:6px;font-size:13px;line-height:1.8;font-weight:500"></div>
    </div>
  </div>

  <!-- Right panel -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <div class="card">
      <div class="card-title">Кой режим да избера?</div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <div style="padding:10px 14px;background:rgba(61,187,127,.07);border:1px solid rgba(61,187,127,.3);border-radius:6px">
          <div style="font-size:12px;font-weight:700;color:#5DCCA0;margin-bottom:4px">Първоначален импорт</div>
          <div style="font-size:12px;color:var(--muted)">Базата е празна и качваш за първи път. Използвай това.</div>
        </div>
        <div style="padding:10px 14px;background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.2);border-radius:6px">
          <div style="font-size:12px;font-weight:700;color:var(--gold);margin-bottom:4px">Добави само нови</div>
          <div style="font-size:12px;color:var(--muted)">Вече имаш продукти. Искаш да добавиш нови без да трогваш старите.</div>
        </div>
        <div style="padding:10px 14px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:6px">
          <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:4px">Замени изцяло</div>
          <div style="font-size:12px;color:var(--muted)">Актуализирана версия на файла. Старите данни се архивират.</div>
        </div>
      </div>
    </div>

    <!-- Cache status -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <div class="card-title" style="margin:0">Локален кеш</div>
        <button class="btn btn-ghost btn-sm" onclick="rebuildCache(this)">⟳ Обнови</button>
      </div>
      <?php $cs = $cacheStatus ?? []; ?>
      <div style="display:flex;flex-direction:column;gap:6px;font-size:13px">
        <div style="display:flex;justify-content:space-between">
          <span style="color:rgba(255,255,255,.6)">Статус</span>
          <span style="color:<?= ($cs['exists']??false)?'var(--green)':'var(--red)' ?>;font-weight:600"><?= ($cs['exists']??false)?'✅ Активен':'❌ Липсва' ?></span>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span style="color:rgba(255,255,255,.6)">Продукти</span>
          <span style="font-weight:600"><?= number_format($cs['count'] ?? 0) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span style="color:rgba(255,255,255,.6)">Размер</span>
          <span><?= $cs['size'] ? number_format($cs['size']/1024/1024,1).' MB' : '—' ?></span>
        </div>
        <?php if (!empty($cs['modified'])): ?>
        <div style="display:flex;justify-content:space-between">
          <span style="color:rgba(255,255,255,.6)">Обновен</span>
          <span style="font-size:12px"><?= date('d.m.Y H:i', strtotime($cs['modified'])) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <p style="font-size:11px;color:rgba(255,255,255,.4);margin-top:10px;line-height:1.5">Кешът се обновява автоматично при всеки импорт. При нужда — натисни "Обнови" за ръчно обновяване от Firebase.</p>
    </div>

    <div class="card" style="padding:0">
      <div style="padding:14px 20px;border-bottom:1px solid var(--border)">
        <div class="card-title" style="margin:0">Архиви (<?= count($archives) ?>)</div>
      </div>
      <?php if (empty($archives)): ?>
      <div style="padding:20px;text-align:center">
        <p class="text-sm text-muted">Няма архиви. Създават се при "Замени изцяло".</p>
      </div>
      <?php else: ?>
      <div style="max-height:280px;overflow-y:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
          <thead><tr>
            <th style="padding:8px 14px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)">Дата</th>
            <th style="padding:8px 14px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)">Описание</th>
            <th style="padding:8px 14px;text-align:right;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)">Бр.</th>
            <th style="padding:8px 14px;border-bottom:1px solid var(--border)"></th>
          </tr></thead>
          <tbody>
            <?php foreach ($archives as $a): ?>
            <tr>
              <td style="padding:8px 14px;border-bottom:1px solid var(--border);color:var(--muted);white-space:nowrap"><?= date('d.m.Y H:i', strtotime($a['date'] ?? '')) ?></td>
              <td style="padding:8px 14px;border-bottom:1px solid var(--border);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($a['label']) ?>"><?= htmlspecialchars($a['label']) ?></td>
              <td style="padding:8px 14px;border-bottom:1px solid var(--border);text-align:right"><?= number_format($a['count']) ?></td>
              <td style="padding:8px 14px;border-bottom:1px solid var(--border);text-align:right">
                <div style="display:flex;gap:5px">
                  <a href="/products/export-archive?key=<?= urlencode($a['key']) ?>" class="btn btn-ghost btn-sm" title="Свали архива като Excel файл" style="color:var(--green)">↓ .xlsx</a>
                  <button class="btn btn-ghost btn-sm" onclick="restoreArchive('<?= htmlspecialchars($a['key']) ?>', '<?= htmlspecialchars($a['label']) ?>')">Зареди</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
let selectedFile = null;

// Drag & drop on whole page
document.addEventListener('dragover',  e => e.preventDefault());
document.addEventListener('drop', e => {
  e.preventDefault();
  const f = e.dataTransfer.files[0];
  if (!f) return;
  if (!f.name.toLowerCase().endsWith('.xlsx')) { alert('Само .xlsx файлове!'); return; }
  selectedFile = f;
  showFileInfo(f);
});

function onFileChosen(inp) {
  const f = inp.files[0];
  if (!f) return;
  if (!f.name.toLowerCase().endsWith('.xlsx')) { alert('Само .xlsx файлове!'); return; }
  selectedFile = f;
  showFileInfo(f);
}

function showFileInfo(f) {
  document.getElementById('chosen-name').textContent = '📄 ' + f.name;
  document.getElementById('chosen-size').textContent = (f.size/1024).toFixed(1) + ' KB';
  document.getElementById('step-choose').style.display = 'none';
  document.getElementById('step-import').style.display = 'block';
  document.getElementById('result').style.display = 'none';
}

function resetFile() {
  selectedFile = null;
  document.getElementById('file-inp').value = '';
  document.getElementById('step-choose').style.display = 'block';
  document.getElementById('step-import').style.display = 'none';
  document.getElementById('result').style.display = 'none';
  document.getElementById('progress').style.display = 'none';
}

function updateMode(radio) {
  const v = radio.value;
  document.getElementById('archive-label-group').style.display = v === 'replace' ? 'block' : 'none';
  document.getElementById('lbl-first').style.border   = v==='first'   ? '2px solid var(--green)' : '1px solid var(--border)';
  document.getElementById('lbl-first').style.background = v==='first' ? 'rgba(61,187,127,.05)' : '';
  document.getElementById('lbl-merge').style.border   = v==='merge'   ? '2px solid var(--gold)'  : '1px solid var(--border)';
  document.getElementById('lbl-replace').style.border = v==='replace' ? '2px solid var(--gold)'  : '1px solid var(--border)';

  const labels = {'first':'Импортирай (Първоначален)', 'merge':'Импортирай (Добави нови)', 'replace':'Импортирай (Замени изцяло)'};
  document.getElementById('import-btn').textContent = labels[v] + ' →';
}

function doImport() {
  if (!selectedFile) { document.getElementById('file-inp').click(); return; }
  const mode  = document.querySelector('input[name="mode"]:checked').value;
  const label = document.getElementById('archive-label')?.value?.trim() || '';
  const btn   = document.getElementById('import-btn');
  const prog  = document.getElementById('progress');
  const fill  = document.getElementById('prog-fill');
  const ptxt  = document.getElementById('prog-text');

  btn.disabled = true; btn.textContent = 'Импортиране…';
  prog.style.display = 'block';
  document.getElementById('result').style.display = 'none';
  fill.style.width = '5%';

  let pct = 5;
  const timer = setInterval(() => {
    pct = Math.min(pct + 2, 88);
    fill.style.width = pct + '%';
    const msgs = ['Четене на Excel файла…','Обработване на продуктите…','Записване в Firebase (партиди по 100)…','Финализиране…'];
    ptxt.textContent = pct<25 ? msgs[0] : pct<55 ? msgs[1] : pct<85 ? msgs[2] : msgs[3];
  }, 1200);

  const fd = new FormData();
  fd.append('file', selectedFile);
  fd.append('mode', mode);
  if (label) fd.append('label', label);

  const ctrl = new AbortController();
  const tmout = setTimeout(() => ctrl.abort(), 180000);

  fetch('/products/import', {method:'POST', body:fd, signal:ctrl.signal})
    .then(r => r.text())
    .then(text => {
      clearInterval(timer); clearTimeout(tmout);
      fill.style.width = '100%';
      let d;
      try { d = JSON.parse(text); } catch(e) {
        btn.disabled=false; btn.textContent='Опитай отново';
        showResult('✗ Сървърът върна грешен отговор:<br><code style="font-size:11px">' + escH(text.substring(0,400)) + '</code>', false);
        return;
      }
      if (d.success) {
        let msg = '';
        if (d.mode==='first')   msg = '✓ Успешно! Записани <strong>' + (d.count||0).toLocaleString() + '</strong> продукта в Firebase.';
        if (d.mode==='merge')   msg = '✓ Добавени <strong>' + d.added + '</strong> нови. Пропуснати: ' + d.skipped + '. Общо: <strong>' + (d.total||0).toLocaleString() + '</strong>.';
        if (d.mode==='replace') msg = '✓ Заменени с <strong>' + (d.count||0).toLocaleString() + '</strong> продукта.' + (d.archive_key ? '<br><small style="opacity:.7">Архив: ' + escH(d.archive_key) + '</small>' : '');
        showResult(msg, true);
        setTimeout(() => location.href='/products', 3000);
      } else {
        btn.disabled=false; btn.textContent='Опитай отново';
        let err = '✗ Грешка: <strong>' + escH(d.error||'Неизвестна грешка') + '</strong>';
        if (d.written !== undefined) err += '<br><small>Записани преди грешката: ' + d.written + '</small>';
        showResult(err, false);
      }
    })
    .catch(err => {
      clearInterval(timer); clearTimeout(tmout);
      btn.disabled=false; btn.textContent='Опитай отново';
      showResult(err.name==='AbortError'
        ? '✗ Timeout (>3 мин). Провери: <a href="/products" style="color:var(--gold)">→ Продукти</a>'
        : '✗ Мрежова грешка: ' + escH(err.message), false);
    });
}

function showResult(msg, ok) {
  const el = document.getElementById('result');
  el.innerHTML = msg;
  el.style.background = ok ? 'rgba(61,187,127,.1)' : 'rgba(224,92,92,.1)';
  el.style.border = '1px solid ' + (ok ? 'rgba(61,187,127,.3)' : 'rgba(224,92,92,.3)');
  el.style.color  = ok ? '#5DCCA0' : '#F08080';
  el.style.display = 'block';
}
function escH(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function rebuildCache(btn) {
  const orig = btn.textContent;
  btn.disabled = true; btn.textContent = '⟳ Обновяване…';
  fetch('/products/rebuild-cache', {method:'POST'})
    .then(r=>r.json())
    .then(d=>{
      alert(d.success ? '✓ ' + d.message : '✗ ' + (d.error||'Грешка'));
      if(d.success) location.reload();
    })
    .catch(()=>alert('✗ Мрежова грешка'))
    .finally(()=>{ btn.disabled=false; btn.textContent=orig; });
}

function restoreArchive(key, label) {
  if (!confirm('Зареди архив "' + label + '"?\n\nТекущите продукти ще бъдат архивирани преди зареждането.')) return;
  const fd = new FormData(); fd.append('key', key);
  fetch('/products/restore',{method:'POST',body:fd}).then(r=>r.json())
    .then(d=>{ if(d.success){alert('✓ Зареден!');location.href='/products';}else alert('✗ '+(d.error||'Грешка')); })
    .catch(()=>alert('✗ Мрежова грешка'));
}
</script>
