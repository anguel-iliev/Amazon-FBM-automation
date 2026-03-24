<?php $archives = $archives ?? []; ?>

<div class="page-header">
  <div></div>
  <div class="page-header-actions">
    <a href="/products/template" class="btn btn-ghost btn-sm">↓ Свали шаблон .xlsx</a>
    <a href="/products" class="btn btn-ghost btn-sm">← Към продукти</a>
  </div>
</div>

<div class="grid-2" style="align-items:start;gap:20px">

  <!-- Import card -->
  <div class="card">
    <div class="card-title">Импортирай Excel файл</div>

    <!-- Hidden file input -->
    <input type="file" id="file-inp" accept=".xlsx" style="display:none" onchange="onFileChosen(this)">

    <!-- STEP 1: Choose file -->
    <div id="step-choose">
      <p class="text-sm text-muted" style="margin-bottom:16px;line-height:1.7">
        Натисни бутона за да избереш .xlsx файл от компютъра си.
      </p>
      <button class="btn btn-primary" style="width:100%;padding:16px;font-size:14px" onclick="document.getElementById('file-inp').click()">
        <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="margin-right:8px"><path d="M4 6v-2a1 1 0 011-1h10a1 1 0 011 1v2M10 17V7M7 10l3-3 3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Избери Excel файл (.xlsx)
      </button>
      <p class="text-sm text-muted" style="margin-top:10px;text-align:center">или провлачи файла тук</p>
      <!-- Drag overlay -->
      <div id="drag-overlay" style="display:none;border:2px dashed var(--gold);border-radius:8px;padding:20px;text-align:center;margin-top:10px;color:var(--gold);font-size:13px">
        Пусни файла тук
      </div>
    </div>

    <!-- STEP 2: File chosen, configure & import -->
    <div id="step-import" style="display:none">

      <!-- File info -->
      <div style="background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.3);border-radius:6px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text)" id="chosen-name"></div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px" id="chosen-size"></div>
        </div>
        <button onclick="resetFile()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:12px;padding:4px 8px" title="Смени файл">✕ Смени</button>
      </div>

      <!-- Mode -->
      <div class="form-group">
        <label class="form-label">Режим на импорт</label>
        <div style="display:flex;flex-direction:column;gap:8px">
          <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px 14px;border:1px solid var(--gold);border-radius:6px;transition:border-color .15s" id="lbl-merge">
            <input type="radio" name="mode" value="merge" checked onchange="updateMode(this)" style="margin-top:2px;accent-color:var(--gold)">
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--text)">Добави само нови продукти</div>
              <div style="font-size:12px;color:var(--muted);margin-top:2px">Съществуващите (по EAN) НЕ се променят. Само нови EAN-и се добавят.</div>
            </div>
          </label>
          <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px 14px;border:1px solid var(--border);border-radius:6px;transition:border-color .15s" id="lbl-replace">
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

      <!-- Import button -->
      <button class="btn btn-primary" id="import-btn" onclick="doImport()" style="width:100%;padding:14px;font-size:14px;margin-top:8px">
        Импортирай →
      </button>

      <!-- Progress -->
      <div id="progress" style="display:none;margin-top:16px">
        <div style="height:6px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden;margin-bottom:10px">
          <div id="prog-fill" style="height:100%;background:var(--gold);border-radius:3px;transition:width .5s;width:5%"></div>
        </div>
        <div id="prog-text" style="font-size:12px;color:var(--muted);text-align:center">Зареждане…</div>
      </div>

      <!-- Result -->
      <div id="result" style="display:none;margin-top:16px;padding:14px 16px;border-radius:6px;font-size:13px;line-height:1.8;font-weight:500"></div>
    </div>
  </div>

  <!-- Right panel -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <div class="card">
      <div class="card-title">Как работи</div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach ([
          ['1', 'Натисни "Избери Excel файл" и избери .xlsx от компютъра'],
          ['2', 'Избери режим: Добави нови или Замени всичко'],
          ['3', 'При "Замени" — старите данни се архивират автоматично'],
          ['4', 'Натисни "Импортирай" и изчакай (928 продукта ≈ 60 сек)'],
          ['5', 'Продуктите се записват директно в Firebase'],
        ] as [$n, $text]): ?>
        <div style="display:flex;gap:10px;align-items:flex-start">
          <span style="width:22px;height:22px;background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--gold);flex-shrink:0"><?= $n ?></span>
          <span style="font-size:13px;color:var(--muted);padding-top:2px"><?= $text ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Archives -->
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
                <button class="btn btn-ghost btn-sm" onclick="restoreArchive('<?= htmlspecialchars($a['key']) ?>', '<?= htmlspecialchars($a['label']) ?>')">Зареди</button>
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

// ── Drag & drop on whole page ──────────────────────────────────
document.addEventListener('dragover', e => { e.preventDefault(); document.getElementById('drag-overlay').style.display = 'block'; });
document.addEventListener('dragleave', e => { if (!e.relatedTarget) document.getElementById('drag-overlay').style.display = 'none'; });
document.addEventListener('drop', e => {
  e.preventDefault();
  document.getElementById('drag-overlay').style.display = 'none';
  const f = e.dataTransfer.files[0];
  if (f && f.name.toLowerCase().endsWith('.xlsx')) {
    selectedFile = f;
    showFileInfo(f);
  } else if (f) {
    showResult('✗ Само .xlsx файлове са позволени. Избери правилния файл.', false);
  }
});

// ── File chosen via dialog ────────────────────────────────────
function onFileChosen(inp) {
  const f = inp.files[0];
  if (!f) return;
  if (!f.name.toLowerCase().endsWith('.xlsx')) {
    showResult('✗ Само .xlsx файлове са позволени.', false);
    return;
  }
  selectedFile = f;
  showFileInfo(f);
}

function showFileInfo(f) {
  document.getElementById('chosen-name').textContent = '📄 ' + f.name;
  document.getElementById('chosen-size').textContent = (f.size / 1024).toFixed(1) + ' KB';
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

// ── Mode toggle ────────────────────────────────────────────────
function updateMode(radio) {
  document.getElementById('archive-label-group').style.display = radio.value === 'replace' ? 'block' : 'none';
  document.getElementById('lbl-merge').style.borderColor   = radio.value === 'merge'   ? 'var(--gold)' : 'var(--border)';
  document.getElementById('lbl-replace').style.borderColor = radio.value === 'replace' ? 'var(--gold)' : 'var(--border)';
}

// ── Import ─────────────────────────────────────────────────────
function doImport() {
  if (!selectedFile) { document.getElementById('file-inp').click(); return; }

  const mode  = document.querySelector('input[name="mode"]:checked').value;
  const label = document.getElementById('archive-label').value.trim();
  const btn   = document.getElementById('import-btn');
  const prog  = document.getElementById('progress');
  const fill  = document.getElementById('prog-fill');
  const ptxt  = document.getElementById('prog-text');

  btn.disabled  = true;
  btn.textContent = 'Импортиране…';
  prog.style.display = 'block';
  document.getElementById('result').style.display = 'none';

  // Animated progress
  let pct = 5;
  const timer = setInterval(() => {
    pct = Math.min(pct + 3, 88);
    fill.style.width = pct + '%';
    if (pct < 30)      ptxt.textContent = 'Четене на Excel файла…';
    else if (pct < 60) ptxt.textContent = 'Обработване на ' + selectedFile.name.split('.')[0] + '…';
    else if (pct < 85) ptxt.textContent = 'Записване в Firebase (партиди по 100)…';
    else               ptxt.textContent = 'Финализиране…';
  }, 1000);

  const fd = new FormData();
  fd.append('file', selectedFile);
  fd.append('mode', mode);
  if (label) fd.append('label', label);

  const ctrl    = new AbortController();
  const timeout = setTimeout(() => ctrl.abort(), 180000); // 3 min

  fetch('/products/import', { method: 'POST', body: fd, signal: ctrl.signal })
    .then(r => r.text())
    .then(text => {
      clearInterval(timer);
      clearTimeout(timeout);
      fill.style.width = '100%';

      let d;
      try { d = JSON.parse(text); }
      catch(e) {
        btn.disabled = false; btn.textContent = 'Опитай отново';
        showResult('✗ Сървърът върна неочакван отговор:<br><code style="font-size:11px;opacity:.8">' + escH(text.substring(0, 400)) + '</code>', false);
        return;
      }

      if (d.success) {
        fill.style.width = '100%';
        let msg = '';
        if (d.mode === 'replace') {
          msg = '✓ Успешно! Записани <strong>' + (d.count || 0).toLocaleString() + '</strong> продукта в Firebase.';
          if (d.archive_key) msg += '<br><small style="opacity:.7">Архив: ' + escH(d.archive_key) + '</small>';
        } else {
          msg = '✓ Успешно!<br>Добавени: <strong>' + d.added + '</strong> нови.<br>Пропуснати: ' + d.skipped + ' (вече съществуват).<br>Общо: <strong>' + (d.total||0).toLocaleString() + '</strong>';
        }
        showResult(msg, true);
        setTimeout(() => window.location.href = '/products', 3000);
      } else {
        btn.disabled = false; btn.textContent = 'Опитай отново';
        let errMsg = '✗ Грешка при импорт:<br><strong>' + escH(d.error || 'Неизвестна грешка') + '</strong>';
        if (d.written !== undefined) errMsg += '<br><small>Записани преди грешката: ' + d.written + '</small>';
        showResult(errMsg, false);
      }
    })
    .catch(err => {
      clearInterval(timer);
      clearTimeout(timeout);
      btn.disabled = false; btn.textContent = 'Опитай отново';
      const msg = err.name === 'AbortError'
        ? '✗ Timeout — операцията отне повече от 3 минути.<br>Провери дали продуктите са записани: <a href="/products" style="color:var(--gold)">→ Продукти</a>'
        : '✗ Мрежова грешка: ' + escH(err.message);
      showResult(msg, false);
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

function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Restore archive ────────────────────────────────────────────
function restoreArchive(key, label) {
  if (!confirm('Зареди архив "' + label + '"?\n\nТекущите продукти ще бъдат архивирани преди зареждането.')) return;
  const fd = new FormData(); fd.append('key', key);
  fetch('/products/restore', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { if (d.success) { alert('✓ Архивът е зареден!'); location.href='/products'; } else alert('✗ ' + (d.error||'Грешка')); })
    .catch(() => alert('✗ Мрежова грешка'));
}
</script>
