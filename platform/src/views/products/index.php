<?php
$page     = $page    ?? 1;
$pages    = $pages   ?? 1;
$total    = $total   ?? 0;
$perPage  = $perPage ?? 50;
$filters  = $filters ?? [];
$products = $products?? [];
$stats    = $stats   ?? [];

$from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
$to   = min($page * $perPage, $total);

function pq(array $extra = []): string {
    global $filters, $page;
    $base = array_filter($filters, fn($v) => $v !== '');
    $p    = array_merge($base, $extra);
    return $p ? '?' . http_build_query($p) : '';
}

$sortCol = $filters['sort'] ?? '';
$sortDir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$COLS = [
  'EAN Amazon'                       => ['EAN',          'mono',   120, false],
  'Наше SKU'                         => ['Наше SKU',     'mono',   110, false],
  'Доставчик'                        => ['Доставчик',    'text',   100, false],
  'Бранд'                            => ['Бранд',        'text',   88,  false],
  'Модел'                            => ['Модел',        'text',   220, false],
  'Amazon Link'                      => ['Link',         'link',   46,  false],
  'ASIN'                             => ['ASIN',         'mono',   110, false],
  'Цена Доставчик -Netto'            => ['Дост.€',       'num',    80,  true],
  'Цена Amazon  - Brutto'            => ['Amazon€',      'num',    80,  false],
  'Продажна Цена в Амазон  - Brutto' => ['Продажна€',   'num',    88,  true],
  'Amazon Такси'                     => ['Амз.такса',    'num',    78,  false],
  'Транспорт до кр. лиент  Netto'    => ['Транспорт',   'num',    78,  true],
  'Резултат'                         => ['Резултат',     'result', 78,  false],
  'Корекция  на цена'                => ['Корекция',     'num',    75,  true],
  'DM цена'                          => ['DM цена',      'num',    75,  true],
  'Нова цена след намаление'         => ['Нова цена',    'num',    84,  true],
  'Електоника'                       => ['Електроника',  'toggle', 80,  true],
  'Коментар'                         => ['Коментар',     'text',   160, true],
];
?>
<style>
.pw{display:flex;flex-direction:column;height:calc(100vh - 62px);overflow:hidden;padding:0}
.psb{display:flex;gap:10px;padding:10px 16px 0;flex-shrink:0}
.psc{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:10px 16px;display:flex;align-items:center;gap:12px;flex:1;min-width:0;position:relative;overflow:hidden}
.psc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--gold)}
.psc.g::before{background:var(--green)}.psc.b::before{background:var(--blue)}.psc.a::before{background:var(--amber)}
.psi{width:32px;height:32px;border-radius:8px;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--gold)}
.psc.g .psi{background:rgba(61,187,127,.1);border-color:rgba(61,187,127,.2);color:var(--green)}
.psc.b .psi{background:rgba(74,124,255,.1);border-color:rgba(74,124,255,.2);color:var(--blue)}
.psc.a .psi{background:rgba(245,166,35,.1);border-color:rgba(245,166,35,.2);color:var(--amber)}
.psv{font-family:var(--font-head);font-size:20px;font-weight:800;color:#fff;line-height:1}
.psl{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-top:2px}
.pf{padding:10px 16px 0;flex-shrink:0}
.pfi{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;width:100%}
.pfg{display:flex;flex-direction:column;gap:3px}
.pfg label{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.4)}
.pfg select,.pfg input{background:#0D0F14;border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:5px 10px;font-size:12px;color:#fff;font-family:inherit;outline:none;height:30px}
.pfg select{padding-right:24px;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 20 20'%3E%3Cpath d='M5 7.5l5 5 5-5' stroke='rgba(255,255,255,.5)' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 6px center}
.pfg select option{background:#0D0F14;color:#E8E6E1}
.pfg select:focus,.pfg input:focus{border-color:var(--gold)}
.pfs{flex:1;min-width:180px}
.pfa{display:flex;gap:6px;align-items:flex-end;margin-left:auto}
.par{display:flex;justify-content:space-between;align-items:center;padding:8px 16px 0;flex-shrink:0;gap:6px;flex-wrap:wrap}
.par-info{font-size:12px;color:rgba(255,255,255,.4)}
.par-info strong{color:rgba(255,255,255,.75)}
.pgo{flex:1;min-height:0;padding:8px 16px 10px;display:flex;flex-direction:column}
.pgw{flex:1;border:1px solid rgba(255,255,255,.07);border-radius:8px;display:flex;flex-direction:column;overflow:hidden;min-height:0;background:var(--panel)}
.pgs{flex:1;overflow:auto;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.2) rgba(255,255,255,.04)}
.pgs::-webkit-scrollbar{width:8px;height:9px}
.pgs::-webkit-scrollbar-track{background:rgba(255,255,255,.03)}
.pgs::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:4px;border:2px solid transparent;background-clip:padding-box}
.pgs::-webkit-scrollbar-thumb:hover{background:rgba(201,168,76,.5);border:2px solid transparent;background-clip:padding-box}
.pgs::-webkit-scrollbar-corner{background:#12151C}
.pgt{width:max-content;min-width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}
.pgt thead th{position:sticky;top:0;z-index:30;background:#1A1F30;border-bottom:2px solid rgba(201,168,76,.25);padding:0;vertical-align:middle;box-shadow:0 2px 0 rgba(0,0,0,.3)}
.th-i{display:flex;align-items:center;gap:4px;padding:8px 10px;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:rgba(255,255,255,.6);white-space:nowrap;border-right:1px solid rgba(255,255,255,.04)}
.th-i a{color:inherit;text-decoration:none;display:flex;align-items:center;gap:4px}
.th-i a:hover{color:#fff}
.pgt tbody tr:hover td{background:rgba(255,255,255,.03)!important}
.pgt tbody td{padding:5px 10px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;color:#E8E6E1;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:0;border-right:1px solid rgba(255,255,255,.02);height:34px}
.pgt tbody tr:last-child td{border-bottom:none}
td.cn{text-align:right;font-variant-numeric:tabular-nums}
td.cm{font-family:monospace;font-size:11px;color:rgba(232,230,225,.65)}
td.cl{text-align:center}
td.cr{text-align:right;font-variant-numeric:tabular-nums;font-weight:700}
td.cr.pos{color:#5DCCA0}td.cr.neg{color:#E05C5C}td.cr.zer{color:rgba(255,255,255,.25)}
td.ed{cursor:text;position:relative}
td.ed::after{content:'';position:absolute;bottom:2px;left:8px;right:8px;height:1px;background:rgba(201,168,76,.2)}
td.ed:hover{background:rgba(201,168,76,.05)!important}
td.ed:hover::after{background:rgba(201,168,76,.5)}
.ci{width:100%;background:rgba(201,168,76,.08);border:1px solid var(--gold);border-radius:3px;padding:2px 6px;color:#fff;font-size:12px;font-family:inherit;outline:none;height:22px}
.bg{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;line-height:1.6}
.bg-g{background:rgba(61,187,127,.15);color:#5DCCA0;border:1px solid rgba(61,187,127,.3)}
.bg-a{background:rgba(201,168,76,.15);color:var(--gold-lt);border:1px solid rgba(201,168,76,.3)}
.bg-m{background:rgba(255,255,255,.07);color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.1)}
.elek{display:inline-block;width:52px;padding:2px 0;border-radius:20px;font-size:10px;font-weight:700;line-height:1.6;text-align:center;cursor:pointer;user-select:none;transition:all .12s;border:1px solid transparent;color:#fff}
.elek.y{background:rgba(61,187,127,.2);border-color:rgba(61,187,127,.4)}
.elek.n{background:rgba(255,255,255,.06);color:rgba(255,255,255,.4);border-color:rgba(255,255,255,.1)}
.al{display:inline-flex;align-items:center;justify-content:center;width:24px;height:20px;border-radius:4px;background:rgba(255,153,0,.1);border:1px solid rgba(255,153,0,.2);color:#FFA500;text-decoration:none;transition:all .12s}
.al:hover{background:rgba(255,153,0,.2);color:#fff}
.pgp{display:flex;justify-content:space-between;align-items:center;padding:7px 14px;border-top:1px solid rgba(255,255,255,.06);background:#181C26;flex-wrap:wrap;gap:6px;flex-shrink:0;border-bottom-left-radius:8px;border-bottom-right-radius:8px}
.pgpg{display:flex;gap:3px;align-items:center;flex-wrap:wrap}
.pgb{min-width:28px;height:26px;padding:0 7px;border-radius:4px;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,.1);background:transparent;color:rgba(255,255,255,.5);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;transition:all .12s}
.pgb:hover{background:rgba(255,255,255,.06);color:#fff}
.pgb.act{background:var(--gold);color:#0D0F14;border-color:var(--gold);font-weight:700}
.pgb[disabled]{opacity:.3;pointer-events:none}
.pgpp select{background:#0D0F14;border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:3px 6px;font-size:12px;color:#fff;outline:none}
.pgpp select option{background:#0D0F14}
.pgpp{display:flex;gap:5px;align-items:center;font-size:12px;color:rgba(255,255,255,.4)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--green);color:#fff;padding:8px 16px;border-radius:6px;font-size:12px;font-weight:700;z-index:9999;display:none;animation:fiu .2s ease}
@keyframes fiu{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.pg-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;gap:14px;color:rgba(255,255,255,.2)}
.pg-empty h3{font-size:15px;font-weight:600;color:rgba(255,255,255,.35)}
.pg-empty p{font-size:12px;text-align:center;line-height:1.7;max-width:300px}
</style>

<div class="pw">

<!-- Stats -->
<div class="psb">
  <div class="psc">
    <div class="psi"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M2 5h16M2 10h16M2 15h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
    <div><div class="psv"><?= number_format($total) ?></div><div class="psl">Намерени</div></div>
  </div>
  <div class="psc g">
    <div class="psi"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M2 10l5 5 9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
    <div><div class="psv"><?= number_format($stats['withAsin'] ?? 0) ?></div><div class="psl">С ASIN</div></div>
  </div>
  <div class="psc a">
    <div class="psi"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M10 6v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.8"/></svg></div>
    <div><div class="psv"><?= number_format($stats['notUploaded'] ?? 0) ?></div><div class="psl">За качване</div></div>
  </div>
  <div class="psc b">
    <div class="psi"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M3 17v-1a5 5 0 015-5h4a5 5 0 015 5v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/></svg></div>
    <div><div class="psv"><?= number_format($stats['suppliers'] ?? 0) ?></div><div class="psl">Доставчици</div></div>
  </div>
</div>

<!-- Filters -->
<div class="pf">
<form method="get" action="/products" id="ff" style="width:100%">
<div class="pfi">
  <div class="pfg"><label>Доставчик</label>
    <select name="dostavchik" style="min-width:130px">
      <option value="">— Всички —</option>
      <?php foreach ($suppliers as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= ($filters['dostavchik'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="pfg"><label>Бранд</label>
    <select name="brand" style="min-width:100px">
      <option value="">— Всички —</option>
      <?php foreach ($brands as $b): ?>
      <option value="<?= htmlspecialchars($b) ?>" <?= ($filters['brand'] ?? '') === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="pfg"><label>Статус</label>
    <select name="upload_status" style="min-width:110px">
      <option value="">— Всички —</option>
      <option value="NOT_UPLOADED" <?= ($filters['upload_status'] ?? '') === 'NOT_UPLOADED' ? 'selected' : '' ?>>За качване</option>
      <option value="UPLOADED" <?= ($filters['upload_status'] ?? '') === 'UPLOADED' ? 'selected' : '' ?>>Качени</option>
    </select>
  </div>
  <div class="pfg pfs"><label>Търсене</label>
    <input type="text" name="search" placeholder="Модел, EAN, ASIN, SKU…" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width:100%">
  </div>
  <div class="pfa">
    <button type="submit" class="btn btn-primary btn-sm">Търси</button>
    <a href="/products" class="btn btn-ghost btn-sm">Изчисти</a>
  </div>
  <?php if ($sortCol): ?>
  <input type="hidden" name="sort" value="<?= htmlspecialchars($sortCol) ?>">
  <input type="hidden" name="dir" value="<?= $sortDir ?>">
  <?php endif; ?>
</div>
</form>
</div>

<!-- Action row -->
<div class="par">
  <div class="par-info">
    <?php if ($total > 0): ?>Показани <strong><?= number_format($from) ?>–<?= number_format($to) ?></strong> от <strong><?= number_format($total) ?></strong>
    <?php else: ?><span style="color:rgba(255,255,255,.25)">Няма продукти</span><?php endif; ?>
  </div>
  <div style="display:flex;gap:6px;align-items:center">
    <a href="/products/add" class="btn btn-ghost btn-sm">
      <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Добави продукт
    </a>
    <a href="/products/import" class="btn btn-ghost btn-sm">
      <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M4 6v-2a1 1 0 011-1h10a1 1 0 011 1v2M10 17V7M7 10l3-3 3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Import Excel
    </a>
    <a href="/products/export<?= pq() ?>" class="btn btn-ghost btn-sm">
      <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M4 14v2a1 1 0 001 1h10a1 1 0 001-1v-2M10 3v10M7 10l3 3 3-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
      CSV
    </a>
  </div>
</div>

<!-- Grid -->
<div class="pgo"><div class="pgw"><div class="pgs">
<table class="pgt">
  <thead><tr>
  <?php foreach ($COLS as $key => [$label, $type, $width, $editable]):
    $sorted  = $sortCol === $key;
    $nextDir = ($sorted && $sortDir === 'asc') ? 'desc' : 'asc';
    $icon    = $sorted ? ($sortDir === 'asc' ? '▲' : '▼') : '';
  ?>
  <th style="width:<?= $width ?>px"><div class="th-i">
    <a href="/products<?= pq(['sort' => $key, 'dir' => $nextDir, 'page' => 1]) ?>"><?= htmlspecialchars($label) ?><?php if ($icon): ?> <span style="font-size:9px;opacity:.7"><?= $icon ?></span><?php endif; ?></a>
  </div></th>
  <?php endforeach; ?>
  <th style="width:70px"><div class="th-i">Статус</div></th>
  </tr></thead>
  <tbody>
  <?php if (empty($products)): ?>
  <tr><td colspan="<?= count($COLS)+1 ?>" style="padding:0;border:none">
    <div class="pg-empty">
      <svg width="44" height="44" viewBox="0 0 20 20" fill="none"><path d="M2 5h16M2 10h16M2 15h10" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      <h3>Няма намерени продукти</h3>
      <p><?php if (!empty($filters['search']) || !empty($filters['dostavchik'])): ?>Промени филтрите.<?php else: ?>Качи Excel файл с продукти чрез бутона <strong>Import Excel</strong>.<?php endif; ?></p>
      <?php if (empty($filters['search']) && empty($filters['dostavchik'])): ?>
      <a href="/products/import" class="btn btn-primary btn-sm">Import Excel</a>
      <?php endif; ?>
    </div>
  </td></tr>
  <?php endif; ?>
  <?php foreach ($products as $p):
    $ean    = $p['EAN Amazon'] ?? '';
    $eanH   = htmlspecialchars($ean);
    $link   = $p['Amazon Link'] ?? '';
    $asin   = $p['ASIN'] ?? '';
    $status = $p['_upload_status'] ?? 'NOT_UPLOADED';
    $elek   = $p['Електоника'] ?? '';
    $res    = (float)($p['Резултат'] ?? 0);
    $resC   = $res > 0 ? 'pos' : ($res < 0 ? 'neg' : 'zer');
  ?>
  <tr data-ean="<?= $eanH ?>">
    <?php foreach ($COLS as $key => [$label, $type, $width, $editable]):
      $raw  = $p[$key] ?? null;
      $valH = htmlspecialchars((string)($raw ?? ''));
      $attr = $editable ? "data-ean=\"{$eanH}\" data-field=\"".htmlspecialchars($key)."\" onclick=\"editCell(this)\"" : '';
    ?>
    <?php if ($type === 'link'): ?>
      <td class="cl"><?php if ($link): ?><a href="<?= htmlspecialchars($link) ?>" target="_blank" class="al"><svg width="10" height="10" viewBox="0 0 20 20" fill="none"><path d="M11 3h6v6M9 11L17 3M7 5H4a1 1 0 00-1 1v10a1 1 0 001 1h10a1 1 0 001-1v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a><?php endif; ?></td>
    <?php elseif ($key === 'ASIN'): ?>
      <td class="cm"><?php if ($link && $asin): ?><a href="<?= htmlspecialchars($link) ?>" target="_blank" style="color:var(--gold-lt);text-decoration:none"><?= $valH ?></a><?php else: ?><?= $valH ?><?php endif; ?></td>
    <?php elseif ($type === 'toggle'): ?>
      <td class="cl"><span class="elek <?= $elek === 'Yes' ? 'y' : 'n' ?>" data-ean="<?= $eanH ?>" data-val="<?= htmlspecialchars($elek) ?>" onclick="toggleElek(this)"><?= $elek ?: '—' ?></span></td>
    <?php elseif ($type === 'result'): ?>
      <td class="cr <?= $resC ?>"><?= $res != 0 ? number_format($res, 2) : '' ?></td>
    <?php elseif ($type === 'num'): ?>
      <td class="cn<?= $editable ? ' ed' : '' ?>" <?= $attr ?>><?= $raw !== null && $raw !== '' ? number_format((float)$raw, 2) : '' ?></td>
    <?php else: ?>
      <td class="ct<?= $editable ? ' ed' : '' ?>" <?= $attr ?> title="<?= $valH ?>"><?= $valH ?></td>
    <?php endif; ?>
    <?php endforeach; ?>
    <td class="cl"><span class="bg <?= $status === 'UPLOADED' ? 'bg-g' : 'bg-a' ?>"><?= $status === 'UPLOADED' ? 'Качен' : 'Не качен' ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Pagination -->
<div class="pgp">
  <div style="font-size:12px;color:rgba(255,255,255,.4)"><?php if ($total > 0): ?>Стр. <?= $page ?>/<?= $pages ?> · <?= number_format($total) ?> записа<?php endif; ?></div>
  <div class="pgpg">
    <?php if ($page > 1): ?>
    <a href="/products<?= pq(['page' => 1]) ?>" class="pgb">«</a>
    <a href="/products<?= pq(['page' => $page - 1]) ?>" class="pgb">‹</a>
    <?php else: ?><span class="pgb" disabled>«</span><span class="pgb" disabled>‹</span><?php endif; ?>
    <?php
    $s = max(1, min($page - 3, $pages - 6));
    $e = min($pages, max($page + 3, 7));
    if ($s > 1) echo '<span style="color:rgba(255,255,255,.3);padding:0 4px">…</span>';
    for ($i = $s; $i <= $e; $i++):
    ?><a href="/products<?= pq(['page' => $i]) ?>" class="pgb <?= $i === $page ? 'act' : '' ?>"><?= $i ?></a><?php endfor;
    if ($e < $pages) echo '<span style="color:rgba(255,255,255,.3);padding:0 4px">…</span>';
    ?>
    <?php if ($page < $pages): ?>
    <a href="/products<?= pq(['page' => $page + 1]) ?>" class="pgb">›</a>
    <a href="/products<?= pq(['page' => $pages]) ?>" class="pgb">»</a>
    <?php else: ?><span class="pgb" disabled>›</span><span class="pgb" disabled>»</span><?php endif; ?>
  </div>
  <div class="pgpp">На стр.:
    <select onchange="location.href='/products<?= pq(['perpage'=>'__','page'=>1]) ?>'.replace('__',this.value)">
      <?php foreach ([25,50,100,250] as $pp): ?><option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option><?php endforeach; ?>
    </select>
  </div>
</div>
</div></div></div>
</div>

<div class="toast" id="toast"></div>
<script>
function editCell(td) {
  if (td.querySelector('input')) return;
  const ean = td.dataset.ean, field = td.dataset.field;
  const orig = td.textContent.trim(), origV = orig.replace(/\s/g,'').replace(',','.');
  td.innerHTML = '';
  const inp = document.createElement('input');
  inp.type = 'text'; inp.value = origV; inp.className = 'ci';
  td.appendChild(inp); inp.focus(); inp.select();
  let saved = false;
  function commit() {
    if (saved) return; saved = true;
    const nv = inp.value.trim(), nf = parseFloat(nv.replace(',','.'));
    td.textContent = (!isNaN(nf) && nv !== '') ? nf.toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2}) : nv;
    if (nv !== origV && ean && field) saveCell(ean, field, nv.replace(',','.'));
  }
  inp.addEventListener('blur', commit);
  inp.addEventListener('keydown', e => {
    if (e.key === 'Enter') inp.blur();
    if (e.key === 'Escape') { saved=true; inp.removeEventListener('blur',commit); td.textContent=orig; }
    if (e.key === 'Tab') { e.preventDefault(); inp.blur(); const cells=[...td.closest('tr').querySelectorAll('td.ed')]; const nx=cells[cells.indexOf(td)+1]; if(nx)nx.click(); }
  });
}
function saveCell(ean, field, value) {
  const fd = new FormData(); fd.append('ean',ean); fd.append('field',field); fd.append('value',value);
  fetch('/products/update',{method:'POST',body:fd}).then(r=>r.json())
    .then(d=>toast(d.success?'✓ Запазено':'✗ '+(d.error||'Грешка'),!d.success))
    .catch(()=>toast('✗ Мрежова грешка',true));
}
function toggleElek(el) {
  const next = el.dataset.val === 'Yes' ? 'No' : 'Yes';
  el.textContent = next; el.dataset.val = next;
  el.className = 'elek ' + (next==='Yes'?'y':'n');
  saveCell(el.dataset.ean,'Електоника',next);
}
let tt;
function toast(msg, isErr=false) {
  const t=document.getElementById('toast');
  t.textContent=msg; t.style.background=isErr?'var(--red)':'var(--green)';
  t.style.display='block'; clearTimeout(tt); tt=setTimeout(()=>t.style.display='none',2000);
}
document.addEventListener('keydown', e => {
  if ((e.ctrlKey||e.metaKey) && e.key==='f') { e.preventDefault(); document.querySelector('input[name="search"]')?.focus(); }
});
</script>
