<?php $archives = $archives ?? []; ?>

<div class="page-header">
  <div></div>
  <div class="page-header-actions">
    <a href="/products/template" class="btn btn-ghost btn-sm">↓ Свали шаблон .xlsx</a>
    <a href="/products" class="btn btn-ghost btn-sm">← Към продукти</a>
  </div>
</div>

<div class="grid-2" style="align-items:start;gap:20px">

  <!-- Upload form -->
  <div class="card">
    <div class="card-title">Импортирай Excel файл</div>

    <!-- Drop zone + file select -->
    <label for="file-inp" id="drop-zone" style="display:block;border:2px dashed rgba(201,168,76,.3);border-radius:8px;padding:32px 20px;text-align:center;cursor:pointer;transition:all .2s;background:rgba(201,168,76,.03);margin-bottom:20px"
       ondragover="event.preventDefault();this.style.borderColor='var(--gold)';this.style.background='rgba(201,168,76,.07)'"
       ondragleave="this.style.borderColor='rgba(201,168,76,.3)';this.style.background='rgba(201,168,76,.03)'"
       ondrop="handleDrop(event)">
      <svg width="40" height="40" viewBox="0 0 20 20" fill="none" style="color:var(--gold);opacity:.6;margin-bottom:10px;display:block;margin-left:auto;margin-right:auto"><path d="M4 6v-2a1 1 0 011-1h10a1 1 0 011 1v2M10 17V7M7 10l3-3 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <p style="font-size:14px;color:rgba(255,255,255,.8);font-weight:600;margin-bottom:4px">Кликни тук за да избереш файл</p>
      <p style="font-size:12px;color:rgba(255,255,255,.4)">или провлачи .xlsx файл</p>
      <p id="file-name" style="font-size:12px;color:var(--gold);margin-top:8px;font-weight:600"></p>
    </label>
    <input type="file" id="file-inp" accept=".xlsx" style="display:none" onchange="onFileSelect(this)">

    <!-- Mode -->
    <div class="form-group">
      <label class="form-label">Режим на импорт</label>
      <div style="display:flex;flex-direction:column;gap:10px">
        <label id="lbl-merge" style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px 14px;border:1px solid var(--gold);border-radius:6px">
          <input type="radio" name="mode" value="merge" checked onchange="updateMode(this)" style="margin-top:2px;flex-shrink:0;accent-color:var(--gold)">
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--text)">Добави само нови продукти</div>
            <div style="font-size:12px;color:var(--muted);margin-top:3px">Съществуващите (по EAN) НЕ се променят. Само нови EAN-и се добавят.</div>
          </div>
        </label>
        <label id="lbl-replace" style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px 14px;border:1px solid var(--border);border-radius:6px">
          <input type="radio" name="mode" value="replace" onchange="updateMode(this)" style="margin-top:2px;flex-shrink:0;accent-color:var(--gold)">
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--text)">Замени изцяло</div>
            <div style="font-size:12px;color:var(--muted);margin-top:3px">Текущите данни се архивират автоматично, после се заменят с новите.</div>
          </div>
        </label>
      </div>
    </div>

    <div class="form-group" id="archive-label-group" style="display:none">
      <label class="form-label">Наименование на архива (по желание)</label>
      <input type="text" id="archive-label" class="form-control" placeholder="напр. Преди актуализация март 2026">
    </div>

    <button class="btn btn-primary" id="import-btn" onclick="doImport()" disabled style="width:100%;margin-top:8px">
      Избери файл...
    </button>

    <!-- Progress -->
    <div id="progress" style="display:none;margin-top:16px">
      <div style="height:4px;background:rgba(255,255,255,.1);border-radius:2px;overflow:hidden;margin-bottom:8px">
        <div id="prog-fill" style="height:100%;background:var(--gold);border-radius:2px;transition:width .3s;width:0"></div>
      </div>
      <div id="prog-text" style="font-size:12px;color:var(--muted);text-align:center">Зареждане…</div>
    </div>

    <!-- Result -->
    <div id="result" style="display:none;margin-top:16px;padding:14px 16px;border-radius:6px;font-size:13px;line-height:1.7"></div>
  </div>

  <!-- How it works + Archives -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <div class="card">
      <div class="card-title">Как работи</div>
      <ol style="list-style:none;display:flex;flex-direction:column;gap:10px">
        <?php foreach ([
          'Кликни в жълтата зона горе за да избереш .xlsx файл',
          'Избери режим: Добави нови или Замени всичко',
          'При "Замени" — старите данни се архивират автоматично',
          'Продуктите се записват директно в Firebase',
          'При голям файл (500+ продукта) изчакай ~30 секунди',
        ] as $i => $text): ?>
        <li style="display:flex;gap:10px;align-items:flex-start">
          <span style="width:20px;height:20px;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold);flex-shrink:0;margin-top:1px"><?= $i+1 ?></span>
          <span class="text-sm text-muted"><?= $text ?></span>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>

    <div class="card" style="padding:0">
      <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <div class="card-title" style="margin:0">Архиви (<?= count($archives) ?>)</div>
      </div>
      <?php if (empty($archives)): ?>
      <div style="padding:24px;text-align:center">
        <p class="text-sm text-muted">Няма архиви. Създават се автоматично при "Замени изцяло".</p>
      </div>
      <?php else: ?>
      <div style="max-height:300px;overflow-y:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
          <thead><tr>
            <th style="padding:8px 16px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)">Дата</th>
            <th style="padding:8px 16px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)">Описание</th>
            <th style="padding:8px 16px;text-align:right;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)">Продукти</th>
            <th style="padding:8px 16px;border-bottom:1px solid var(--border)"></th>
          </tr></thead>
          <tbody>
            <?php foreach ($archives as $arch): ?>
            <tr>
              <td style="padding:9px 16px;border-bottom:1px solid var(--border);color:var(--muted)"><?= date('d.m.Y H:i', strtotime($arch['date'] ?? '')) ?></td>
              <td style="padding:9px 16px;border-bottom:1px solid var(--border);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($arch['label']) ?></td>
              <td style="padding:9px 16px;border-bottom:1px solid var(--border);text-align:right"><?= number_format($arch['count']) ?></td>
              <td style="padding:9px 16px;border-bottom:1px solid var(--border);text-align:right">
                <button class="btn btn-ghost btn-sm" onclick="restoreArchive('<?= htmlspecialchars($arch['key']) ?>', '<?= htmlspecialchars($arch['label']) ?>')">Зареди</button>
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

function updateMode(radio) {
  document.getElementById('archive-label-group').style.display = radio.value === 'replace' ? 'block' : 'none';
  document.getElementById('lbl-merge').style.borderColor   = radio.value === 'merge'   ? 'var(--gold)' : 'var(--border)';
  document.getElementById('lbl-replace').style.borderColor = radio.value === 'replace' ? 'var(--gold)' : 'var(--border)';
}

function onFileSelect(inp) {
  const f = inp.files[0]; if (!f) return;
  if (!f.name.toLowerCase().endsWith('.xlsx')) { showResult('✗ Само .xlsx файлове са позволени', false); return; }
  selectedFile = f;
  const kb = (f.size / 1024).toFixed(1);
  document.getElementById('file-name').textContent = '📄 ' + f.name + ' (' + kb + ' KB)';
  const btn = document.getElementById('import-btn');
  btn.disabled = false;
  btn.textContent = 'Импортирай ' + f.name.substring(0, 30) + ' →';
}

function handleDrop(e) {
  e.preventDefault();
  document.getElementById('drop-zone').style.borderColor = 'rgba(201,168,76,.3)';
  document.getElementById('drop-zone').style.background = 'rgba(201,168,76,.03)';
  const f = e.dataTransfer.files[0];
  if (!f) return;
  // Manually trigger file selection
  const dt = new DataTransfer();
  dt.items.add(f);
  document.getElementById('file-inp').files = dt.files;
  onFileSelect(document.getElementById('file-inp'));
}

function doImport() {
  if (!selectedFile) return;
  const mode    = document.querySelector('input[name="mode"]:checked').value;
  const label   = document.getElementById('archive-label').value.trim();
  const btn     = document.getElementById('import-btn');
  const prog    = document.getElementById('progress');
  const fill    = document.getElementById('prog-fill');
  const ptxt    = document.getElementById('prog-text');
  const result  = document.getElementById('result');

  btn.disabled = true;
  prog.style.display = 'block';
  result.style.display = 'none';
  fill.style.width = '15%';
  ptxt.textContent = 'Четене на файла…';

  const fd = new FormData();
  fd.append('file', selectedFile);
  fd.append('mode', mode);
  if (label) fd.append('label', label);

  // Animate progress
  let pct = 15;
  const progressTimer = setInterval(() => {
    pct = Math.min(pct + 5, 85);
    fill.style.width = pct + '%';
    if (pct < 40) ptxt.textContent = 'Четене на Excel файла…';
    else if (pct < 70) ptxt.textContent = 'Обработване на продуктите…';
    else ptxt.textContent = 'Записване в Firebase… (може да отнеме ~30 сек за голям файл)';
  }, 1500);

  // 120 second timeout for large files
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 120000);

  fetch('/products/import', { method: 'POST', body: fd, signal: controller.signal })
    .then(r => r.text().then(text => {
      let data;
      try { data = JSON.parse(text); }
      catch(e) {
        throw new Error('Сървърът върна грешен отговор:\n' + text.substring(0, 500));
      }
      return data;
    }))
    .then(d => {
      clearInterval(progressTimer);
      clearTimeout(timeout);
      fill.style.width = '100%';

      if (d.success) {
        let msg = '';
        if (d.mode === 'replace') {
          msg = '✓ Успешно! Заменени с <strong>' + d.count.toLocaleString() + '</strong> продукта.<br>';
          if (d.archive_key) msg += '<small style="opacity:.7">Архив: ' + d.archive_key + '</small>';
        } else {
          msg = '✓ Успешно!<br>Добавени: <strong>' + d.added + '</strong> нови продукта.<br>Пропуснати: ' + d.skipped + ' (вече съществуват).<br>Общо в базата: <strong>' + (d.total||0).toLocaleString() + '</strong>';
        }
        showResult(msg, true);
        setTimeout(() => window.location.href = '/products', 2500);
      } else {
        showResult('✗ Грешка при импорт:<br><strong>' + escH(d.error || 'Неизвестна грешка') + '</strong>', false);
        btn.disabled = false;
        btn.textContent = 'Опитай отново';
      }
    })
    .catch(err => {
      clearInterval(progressTimer);
      clearTimeout(timeout);
      fill.style.width = '100%';
      const isTimeout = err.name === 'AbortError';
      if (isTimeout) {
        showResult('✗ Timeout — операцията отне повече от 120 секунди.<br><br>Вероятна причина: Firebase е бавен при запис на много продукти.<br><br>Провери дали продуктите са записани: <a href="/products" style="color:var(--gold)">→ Продукти</a>', false);
      } else {
        showResult('✗ Мрежова грешка:<br>' + escH(err.message), false);
      }
      btn.disabled = false;
      btn.textContent = 'Опитай отново';
    });
}

function showResult(msg, ok) {
  const el = document.getElementById('result');
  el.innerHTML = msg;
  el.style.background = ok ? 'rgba(61,187,127,.1)' : 'rgba(224,92,92,.1)';
  el.style.border = '1px solid ' + (ok ? 'rgba(61,187,127,.3)' : 'rgba(224,92,92,.3)');
  el.style.color  = ok ? '#5DCCA0' : '#F08080';
  el.style.display = 'block';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function restoreArchive(key, label) {
  if (!confirm('Зареди архив "' + label + '"?\n\nТекущите продукти ще бъдат архивирани преди зареждането.')) return;
  const fd = new FormData(); fd.append('key', key);
  fetch('/products/restore', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) { alert('✓ Архивът е зареден успешно!'); window.location.href = '/products'; }
      else alert('✗ Грешка: ' + (d.error || 'неизвестна'));
    })
    .catch(() => alert('✗ Мрежова грешка'));
}
</script>
