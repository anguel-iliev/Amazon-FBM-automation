<?php
/**
 * Settings → Formulas — Visual Formula Engine  v1.7.0
 *
 * Only formula columns exposed:
 *   O = Цена без ДДС
 *   P = ДДС от продажна цена
 *   Q = Amazon Такси
 *   S = ДДС  от Цена Доставчик
 *   U = ДДС  от Транспорт до кр. лиент
 *   V = Резултат
 *   Y = Цена за Испания / Франция / Италия
 *
 * Features:
 *   - Visual builder: column buttons with dropdowns
 *   - Operators: + − × ÷
 *   - Live equation preview as readable text
 *   - Save (record only), Apply to All, Apply to Carriers (dropdown)
 *   - Undo (revert to last saved formula)
 *   - Independent new column addition
 */
$activeTab = 'formulas';
include __DIR__ . '/_tabs.php';

$allColumns       = DataStore::getColumns();
$formulaTemplates = DataStore::getFormulaTemplates();
$settings         = DataStore::getSettings();
$savedFormulas    = $settings['formulas'] ?? [];
$carriers         = $settings['carriers'] ?? [
    ['id'=>'c1','name'=>'DHL','active'=>true],
    ['id'=>'c2','name'=>'DPD','active'=>true],
    ['id'=>'c3','name'=>'GLS','active'=>true],
];

// ── The ONLY formula columns allowed in Formula Engine ──────────
$FORMULA_COLS = [
  'Цена без ДДС'                       => ['letter'=>'O','desc'=>'Продажна цена без ДДС',         'color'=>'#4A7CFF'],
  'ДДС от продажна цена'               => ['letter'=>'P','desc'=>'ДДС от продажната цена',         'color'=>'#4A7CFF'],
  'Amazon Такси'                        => ['letter'=>'Q','desc'=>'Комисионна Amazon',               'color'=>'#FF9900'],
  'ДДС  от Цена Доставчик'            => ['letter'=>'S','desc'=>'ДДС от цената на доставчика',     'color'=>'#4A7CFF'],
  'ДДС  от Транспорт до кр. лиент'    => ['letter'=>'U','desc'=>'ДДС от транспорта',               'color'=>'#4A7CFF'],
  'Резултат'                            => ['letter'=>'V','desc'=>'Нетна печалба',                  'color'=>'#3DBB7F'],
  'Цена за Испания / Франция / Италия' => ['letter'=>'Y','desc'=>'Цена за южни пазари',            'color'=>'#C9A84C'],
];

// Merge saved formulas into the defined ones
$formulaData = [];
foreach ($FORMULA_COLS as $colName => $meta) {
    $formulaData[$colName] = [
        'meta'    => $meta,
        'formula' => $savedFormulas[$colName] ?? ($formulaTemplates[$colName] ?? ''),
    ];
}

// Load extra (user-added) formula columns from settings
$extraFormulaCols = $settings['extra_formula_cols'] ?? [];
foreach ($extraFormulaCols as $colName) {
    if (!isset($formulaData[$colName])) {
        $formulaData[$colName] = [
            'meta'    => ['letter'=>'?','desc'=>'Потребителска колона','color'=>'#888'],
            'formula' => $savedFormulas[$colName] ?? '',
        ];
    }
}

// Numeric columns for formula picker
$numericCols = [
    'Корекция  на цена','Цена Конкурент  - Brutto','Цена Amazon  - Brutto',
    'Продажна Цена в Амазон  - Brutto','Цена без ДДС','ДДС от продажна цена',
    'Amazon Такси','Цена Доставчик -Netto','ДДС  от Цена Доставчик',
    'Транспорт от Доставчик до нас','Транспорт до кр. лиент  Netto',
    'ДДС  от Транспорт до кр. лиент','Резултат',
    'Цена за Испания / Франция / Италия','DM цена','Нова цена след намаление',
    'Доставени','За следваща поръчка',
];
?>

<style>
/* ══ Formula Engine v1.7 ═════════════════════════════════════════ */

.fe-wrap { max-width: 980px; }

/* ── Header ── */
.fe-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 14px; margin-bottom: 20px; flex-wrap: wrap;
}
.fe-title { font-family: var(--font-head); font-size: 15px; font-weight: 800; color: #fff; margin-bottom: 3px; }
.fe-subtitle { font-size: 12px; color: rgba(255,255,255,.42); line-height: 1.7; max-width: 540px; }

/* ── Formula card ── */
.fc {
  background: #181C26;
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 9px;
  margin-bottom: 8px;
  overflow: hidden;
  transition: border-color .15s, box-shadow .15s;
}
.fc:hover { border-color: rgba(255,255,255,.12); }
.fc.fc-open { border-color: rgba(201,168,76,.3); box-shadow: 0 0 0 1px rgba(201,168,76,.08); }

/* Card header */
.fc-head {
  display: flex; align-items: center; gap: 10px;
  padding: 13px 16px; cursor: pointer; user-select: none;
}
.fc-letter {
  width: 28px; height: 28px; border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 800; flex-shrink: 0;
  font-family: var(--font-head);
}
.fc-col-name { font-size: 13px; font-weight: 700; color: #fff; flex: 1; }
.fc-col-desc { font-size: 11px; color: rgba(255,255,255,.38); margin-right: 6px; }
.fc-badge {
  font-size: 10px; padding: 2px 8px; border-radius: 12px; font-weight: 600;
  font-family: 'Courier New', monospace; max-width: 260px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  background: rgba(201,168,76,.1); color: var(--gold-lt); border: 1px solid rgba(201,168,76,.2);
}
.fc-badge.empty {
  background: rgba(255,255,255,.05); color: rgba(255,255,255,.3); border-color: rgba(255,255,255,.08);
  font-family: var(--font-body);
}
.fc-chevron {
  width: 16px; height: 16px; color: rgba(255,255,255,.3);
  transition: transform .2s; flex-shrink: 0;
}
.fc.fc-open .fc-chevron { transform: rotate(180deg); }

/* Card body */
.fc-body {
  display: none; padding: 0 16px 16px;
  border-top: 1px solid rgba(255,255,255,.055);
}
.fc.fc-open .fc-body { display: block; }

/* ── Equation preview ── */
.fe-preview {
  background: rgba(0,0,0,.3);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 6px;
  padding: 12px 14px;
  margin: 12px 0;
  min-height: 56px;
}
.fe-preview-label {
  font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
  color: rgba(255,255,255,.3); margin-bottom: 8px;
}
.fe-preview-eq {
  font-size: 13px; color: #fff; line-height: 2; min-height: 28px;
  display: flex; flex-wrap: wrap; align-items: center; gap: 2px;
}
.eq-result-lbl {
  font-weight: 800; color: var(--gold-lt); margin-right: 4px; font-size: 12px;
}
.eq-field-btn {
  display: inline-flex; align-items: center; gap: 4px;
  background: rgba(74,124,255,.15); color: #7BA8FF;
  border: 1px solid rgba(74,124,255,.3);
  padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;
  cursor: pointer; transition: all .12s; position: relative;
}
.eq-field-btn:hover { background: rgba(74,124,255,.25); color: #fff; }
.eq-num-chip {
  display: inline-block;
  background: rgba(201,168,76,.1); color: var(--gold-lt);
  border: 1px solid rgba(201,168,76,.2);
  padding: 2px 7px; border-radius: 4px; font-size: 12px;
  font-family: 'Courier New', monospace;
}
.eq-op { color: rgba(255,255,255,.5); font-weight: 700; font-size: 14px; padding: 0 4px; }
.eq-empty { color: rgba(255,255,255,.25); font-style: italic; font-size: 12px; }

/* ── Visual Builder rows ── */
.fb-terms { margin: 10px 0; }
.fb-term {
  display: flex; align-items: center; gap: 6px; margin-bottom: 5px;
  animation: fadeIn .15s ease;
}
@keyframes fadeIn { from{opacity:0;transform:translateY(-3px)} to{opacity:1;transform:none} }

.fb-op-btn {
  min-width: 44px; height: 30px;
  border: 1px solid rgba(255,255,255,.12);
  background: #12151C; border-radius: 4px;
  font-size: 14px; font-weight: 700; color: rgba(255,255,255,.7);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  position: relative;
  transition: all .12s; flex-shrink: 0;
}
.fb-op-btn:hover { border-color: var(--gold); color: var(--gold-lt); background: rgba(201,168,76,.07); }
.fb-op-dropdown {
  position: absolute; top: calc(100% + 3px); left: 0;
  background: #12151C; border: 1px solid rgba(255,255,255,.15);
  border-radius: 5px; min-width: 56px; z-index: 100;
  box-shadow: 0 8px 24px rgba(0,0,0,.5);
  display: none;
}
.fb-op-dropdown.open { display: block; }
.fb-op-dropdown-item {
  padding: 7px 10px; font-size: 14px; font-weight: 700; text-align: center;
  cursor: pointer; color: rgba(255,255,255,.7); transition: all .1s;
}
.fb-op-dropdown-item:hover { background: rgba(255,255,255,.07); color: #fff; }

.fb-field-btn {
  flex: 1; height: 30px; min-width: 160px;
  background: rgba(74,124,255,.1); border: 1px solid rgba(74,124,255,.2);
  border-radius: 4px; padding: 0 10px;
  font-size: 12px; font-weight: 600; color: #7BA8FF;
  cursor: pointer; text-align: left; position: relative;
  display: flex; align-items: center; gap: 6px;
  transition: all .12s;
}
.fb-field-btn:hover { background: rgba(74,124,255,.2); color: #fff; border-color: rgba(74,124,255,.4); }
.fb-field-btn .btn-arrow { margin-left: auto; opacity: .5; font-size: 9px; }

.fb-field-dropdown {
  position: absolute; top: calc(100% + 3px); left: 0; right: 0;
  background: #12151C; border: 1px solid rgba(255,255,255,.14);
  border-radius: 5px; z-index: 100; max-height: 220px; overflow-y: auto;
  box-shadow: 0 8px 24px rgba(0,0,0,.5);
  display: none;
}
.fb-field-dropdown.open { display: block; }
.fb-field-dropdown input[type=text] {
  width: 100%; background: rgba(255,255,255,.05); border: none;
  border-bottom: 1px solid rgba(255,255,255,.1);
  padding: 7px 10px; font-size: 12px; color: #fff; outline: none;
  font-family: inherit;
}
.fb-field-opt {
  padding: 6px 10px; font-size: 12px; color: rgba(255,255,255,.7);
  cursor: pointer; transition: background .1s; white-space: nowrap;
  overflow: hidden; text-overflow: ellipsis;
}
.fb-field-opt:hover { background: rgba(255,255,255,.07); color: #fff; }
.fb-field-opt.selected { color: var(--gold-lt); background: rgba(201,168,76,.06); }

.fb-num-inp {
  width: 88px; height: 30px;
  background: rgba(201,168,76,.07); border: 1px solid rgba(201,168,76,.2);
  border-radius: 4px; padding: 0 8px;
  font-size: 12px; color: var(--gold-lt); font-family: 'Courier New', monospace;
  outline: none; text-align: right;
  transition: border-color .12s;
}
.fb-num-inp:focus { border-color: var(--gold); }

.fb-del-btn {
  width: 26px; height: 26px; border-radius: 4px;
  background: rgba(224,92,92,.07); border: 1px solid rgba(224,92,92,.15);
  color: var(--red); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; flex-shrink: 0; transition: all .12s;
}
.fb-del-btn:hover { background: rgba(224,92,92,.18); }

.fb-term-idx {
  font-size: 10px; color: rgba(255,255,255,.3);
  min-width: 18px; text-align: right; flex-shrink: 0;
}

/* Add term buttons */
.fb-add-row {
  display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px;
}

/* Raw formula textarea */
.fb-raw-section {
  margin: 8px 0;
}
.fb-raw-toggle {
  font-size: 11px; color: rgba(255,255,255,.32); cursor: pointer;
  display: inline-flex; align-items: center; gap: 3px;
  user-select: none; transition: color .15s;
}
.fb-raw-toggle:hover { color: rgba(255,255,255,.65); }
.fb-raw-area {
  margin-top: 7px; display: none;
}
.fb-raw-area.open { display: block; }
.fb-raw-inp {
  width: 100%; background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.09);
  border-radius: 4px; padding: 7px 10px; font-family: 'Courier New', monospace;
  font-size: 12px; color: var(--gold-lt); outline: none; resize: vertical; min-height: 34px;
  transition: border-color .15s;
}
.fb-raw-inp:focus { border-color: var(--gold); }

/* ── Formula Actions ── */
.fc-actions {
  display: flex; gap: 7px; flex-wrap: wrap; align-items: center;
  margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,.05);
}
.fc-actions .btn { min-width: 0; }
.fc-status {
  font-size: 12px; color: rgba(255,255,255,.4); align-self: center; margin-left: 4px;
}
.fc-undo-history {
  font-size: 11px; color: rgba(255,255,255,.28); align-self: center; margin-left: auto;
  cursor: pointer; transition: color .15s;
}
.fc-undo-history:hover { color: rgba(255,255,255,.7); text-decoration: underline; }

/* Carriers dropdown for apply */
.carriers-apply-dropdown {
  position: relative; display: inline-block;
}
.carriers-apply-list {
  position: absolute; top: calc(100% + 3px); left: 0;
  background: #12151C; border: 1px solid rgba(255,255,255,.14);
  border-radius: 5px; min-width: 170px; z-index: 100;
  box-shadow: 0 8px 24px rgba(0,0,0,.5);
  display: none;
}
.carriers-apply-list.open { display: block; }
.cap-item {
  display: flex; align-items: center; gap: 7px;
  padding: 7px 12px; cursor: pointer; font-size: 12px;
  color: rgba(255,255,255,.7); transition: background .1s;
}
.cap-item:hover { background: rgba(255,255,255,.06); color: #fff; }
.cap-item input[type=checkbox] { accent-color: var(--gold); }
.cap-apply-btn {
  padding: 6px 12px; font-size: 12px; font-weight: 700;
  background: var(--gold); color: #0D0F14; border: none;
  border-radius: 0 0 5px 5px; width: 100%; cursor: pointer;
  transition: opacity .1s;
}
.cap-apply-btn:hover { opacity: .88; }

/* ── Add column panel ── */
.add-col-section {
  margin-top: 18px;
  background: #181C26;
  border: 1px dashed rgba(255,255,255,.1);
  border-radius: 8px;
  padding: 14px 16px;
}
.add-col-title {
  font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
  color: rgba(255,255,255,.38); margin-bottom: 10px;
}
.add-col-row { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
.add-col-row select, .add-col-row input {
  background: #12151C; border: 1px solid rgba(255,255,255,.12);
  border-radius: 4px; padding: 6px 10px; font-size: 12px; color: #fff;
  font-family: inherit; outline: none; transition: border-color .15s;
  height: 30px;
}
.add-col-row select { min-width: 200px; }
.add-col-row select option { background: #12151C; color: #E8E6E1; }
.add-col-row input { flex: 1; min-width: 150px; }
.add-col-row select:focus, .add-col-row input:focus { border-color: var(--gold); }

/* ── Sticky save bar ── */
.save-all-bar {
  position: sticky; bottom: 0;
  background: linear-gradient(to top, #0D0F14 75%, transparent);
  padding: 16px 0 2px;
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 8px; z-index: 10;
}
.save-all-note { font-size: 12px; color: rgba(255,255,255,.35); }
</style>

<div class="fe-wrap">

<!-- Header -->
<div class="fe-header">
  <div>
    <div class="fe-title">⚙ Formula Engine</div>
    <div class="fe-subtitle">
      Визуален редактор на формули — изберете полета и оператори.<br>
      Натиснете поле за да смените колоната. Натиснете оператора за да изберете +&nbsp;−&nbsp;×&nbsp;÷.
    </div>
  </div>
  <button class="btn btn-ghost btn-sm" onclick="expandAll()">Разгъни всички</button>
</div>

<!-- ══ Formula Cards ══════════════════════════════════════════════ -->
<div id="formula-list">
<?php foreach ($formulaData as $colName => $fd):
  $meta    = $fd['meta'];
  $formula = $fd['formula'] ?? '';
  $letter  = $meta['letter'] ?? '?';
  $color   = $meta['color'] ?? '#888';
  $desc    = $meta['desc'] ?? '';
  $cardId  = 'fc_' . md5($colName);
?>
<div class="fc" id="<?= $cardId ?>" data-col="<?= htmlspecialchars($colName) ?>">

  <!-- Card header -->
  <div class="fc-head" onclick="toggleCard('<?= $cardId ?>')">
    <div class="fc-letter" style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>33">
      <?= htmlspecialchars($letter) ?>
    </div>
    <div class="fc-col-name"><?= htmlspecialchars($colName) ?></div>
    <?php if ($desc): ?>
    <div class="fc-col-desc"><?= htmlspecialchars($desc) ?></div>
    <?php endif; ?>
    <code class="fc-badge <?= $formula ? '' : 'empty' ?>" id="badge_<?= $cardId ?>"
          title="<?= htmlspecialchars($formula) ?>">
      <?= $formula ? htmlspecialchars($formula) : '— няма формула —' ?>
    </code>
    <svg class="fc-chevron" viewBox="0 0 20 20" fill="none">
      <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </div>

  <!-- Card body -->
  <div class="fc-body">

    <!-- Equation preview -->
    <div class="fe-preview">
      <div class="fe-preview-label">Визуализация</div>
      <div class="fe-preview-eq" id="eq_<?= $cardId ?>">
        <span class="eq-empty">Добави полета по-долу →</span>
      </div>
    </div>

    <!-- Visual term builder -->
    <div class="fb-terms" id="terms_<?= $cardId ?>"></div>

    <!-- Add buttons -->
    <div class="fb-add-row">
      <button class="btn btn-ghost btn-sm" onclick="addTermField('<?= $cardId ?>')">
        <svg width="10" height="10" viewBox="0 0 20 20" fill="none"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        + Поле
      </button>
      <button class="btn btn-ghost btn-sm" onclick="addTermNumber('<?= $cardId ?>')">
        <svg width="10" height="10" viewBox="0 0 20 20" fill="none"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        + Число
      </button>
    </div>

    <!-- Raw formula (advanced) -->
    <div class="fb-raw-section">
      <span class="fb-raw-toggle" onclick="toggleRaw('<?= $cardId ?>')">
        <svg width="9" height="9" viewBox="0 0 20 20" fill="none"><path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Ред формула (напреднали)
      </span>
      <div class="fb-raw-area" id="raw_<?= $cardId ?>">
        <div style="font-size:11px;color:rgba(255,255,255,.28);margin-bottom:5px">
          Синтаксис: <code>{Продажна Цена в Амазон  - Brutto}</code> / 1.19
        </div>
        <textarea class="fb-raw-inp"
                  id="raw_inp_<?= $cardId ?>"
                  rows="2"
                  placeholder="{Продажна Цена в Амазон  - Brutto} / 1.19"
                  oninput="parseRawToVisual('<?= $cardId ?>')"><?= htmlspecialchars($formula) ?></textarea>
      </div>
    </div>

    <!-- Actions -->
    <div class="fc-actions">

      <!-- Save only (no apply) -->
      <button class="btn btn-ghost btn-sm" onclick="saveFormula('<?= $cardId ?>')">
        <svg width="11" height="11" viewBox="0 0 20 20" fill="none"><path d="M5 13V6a1 1 0 011-1h8a1 1 0 011 1v7M4 17h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Запази
      </button>

      <!-- Apply to ALL -->
      <button class="btn btn-primary btn-sm" onclick="applyFormulaAll('<?= $cardId ?>')">
        <svg width="11" height="11" viewBox="0 0 20 20" fill="none"><path d="M4 10l5 5 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Приложи към всички
      </button>

      <!-- Apply to specific carriers -->
      <div class="carriers-apply-dropdown" id="cap_wrap_<?= $cardId ?>">
        <button class="btn btn-ghost btn-sm" onclick="toggleCarriersDropdown('<?= $cardId ?>')">
          <svg width="11" height="11" viewBox="0 0 20 20" fill="none"><path d="M3 7h14M5 10h10M7 13h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
          Към превозвачи ▾
        </button>
        <div class="carriers-apply-list" id="cap_list_<?= $cardId ?>">
          <?php foreach ($carriers as $c): ?>
          <label class="cap-item">
            <input type="checkbox" value="<?= htmlspecialchars($c['id']) ?>" <?= $c['active'] ? 'checked' : '' ?>>
            <span><?= htmlspecialchars($c['name']) ?></span>
          </label>
          <?php endforeach; ?>
          <button class="cap-apply-btn" onclick="applyFormulaCarriers('<?= $cardId ?>')">
            Приложи
          </button>
        </div>
      </div>

      <!-- Undo -->
      <button class="btn btn-ghost btn-sm" onclick="undoFormula('<?= $cardId ?>')"
              id="undo_<?= $cardId ?>" style="display:none;color:var(--amber)">
        <svg width="11" height="11" viewBox="0 0 20 20" fill="none"><path d="M4 8l1-5 2 3.5c1-1.5 3-3 6-2.5 3 .5 5 3 5 6a7 7 0 01-11 5.7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Отмени
      </button>

      <div class="fc-status" id="status_<?= $cardId ?>"></div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- ══ Add New Formula Column ════════════════════════════════════ -->
<div class="add-col-section">
  <div class="add-col-title">Добави нова формулна колона (независима)</div>
  <div class="add-col-row">
    <select id="new-col-select">
      <option value="">— Избери съществуваща колона —</option>
      <?php
      $existingFormulaCols = array_keys($formulaData);
      foreach ($allColumns as $col) {
          if (!in_array($col, $existingFormulaCols)) {
              echo '<option value="'.htmlspecialchars($col).'">'.htmlspecialchars($col).'</option>';
          }
      }
      ?>
    </select>
    <span style="font-size:12px;color:rgba(255,255,255,.28)">или</span>
    <input type="text" id="new-col-custom" placeholder="Нова колона (въведи ново名称)">
    <button class="btn btn-ghost btn-sm" onclick="addFormulaColumn()">
      <svg width="10" height="10" viewBox="0 0 20 20" fill="none"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Добави
    </button>
  </div>
</div>

<!-- ══ Save all bar ════════════════════════════════════════════════ -->
<div class="save-all-bar">
  <div class="save-all-note">Формулите се записват в settings.json. Прилагането обновява всички продукти.</div>
  <button class="btn btn-primary" id="save-all-btn" onclick="saveAllFormulas()">
    <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M5 13V6a1 1 0 011-1h8a1 1 0 011 1v7M4 17h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Запази всички формули
  </button>
</div>

</div><!-- /.fe-wrap -->

<script>
// ══ Data ══════════════════════════════════════════════════════════
const ALL_COLS = <?= json_encode($allColumns, JSON_UNESCAPED_UNICODE) ?>;
const NUM_COLS = <?= json_encode($numericCols, JSON_UNESCAPED_UNICODE) ?>;
const CARRIERS = <?= json_encode(array_values($carriers), JSON_UNESCAPED_UNICODE) ?>;

// ── Formula history per card (for undo) ──────────────────────────
const formulaHistory = {};

// ══ Card toggle ═══════════════════════════════════════════════════
function toggleCard(id) {
  const card = document.getElementById(id);
  const wasOpen = card.classList.contains('fc-open');
  card.classList.toggle('fc-open');
  if (!wasOpen) initVisualFromRaw(id);
}
function expandAll() {
  document.querySelectorAll('.fc').forEach(card => {
    if (!card.classList.contains('fc-open')) {
      card.classList.add('fc-open');
      initVisualFromRaw(card.id);
    }
  });
}

// ══ Raw textarea toggle ════════════════════════════════════════════
function toggleRaw(id) {
  const r = document.getElementById('raw_' + id);
  r.classList.toggle('open');
}

// ══ Parse formula string → term objects ═══════════════════════════
function parseFormula(f) {
  const terms = [];
  const re = /\{([^}]+)\}|([\+\-\*\/])|([\d]+(?:\.[\d]+)?)/g;
  let m;
  while ((m = re.exec(f)) !== null) {
    if (m[1])      terms.push({type:'field',  value: m[1]});
    else if (m[2]) terms.push({type:'op',     value: m[2]});
    else if (m[3]) terms.push({type:'number', value: m[3]});
  }
  return terms;
}

function termsToFormula(terms) {
  return terms.map(t => {
    if (t.type === 'field')  return '{' + t.value + '}';
    if (t.type === 'op')     return ' ' + t.value + ' ';
    if (t.type === 'number') return t.value;
    return '';
  }).join('').trim();
}

// ══ Init visual builder from raw textarea ═════════════════════════
function initVisualFromRaw(cardId) {
  const raw = document.getElementById('raw_inp_' + cardId);
  if (!raw) return;
  renderTerms(cardId, parseFormula(raw.value || ''));
}

function parseRawToVisual(cardId) {
  initVisualFromRaw(cardId);
  updatePreview(cardId);
  updateBadge(cardId);
}

// ══ Render visual term rows ═══════════════════════════════════════
function renderTerms(cardId, terms) {
  const container = document.getElementById('terms_' + cardId);
  if (!container) return;
  container.innerHTML = '';

  // Group: interleave ops and operands
  // We'll store alternating: operand, op, operand, op, operand...
  // But internally terms array has them as separate items
  let opBefore = null;
  let opIdx    = 0;

  terms.forEach((t, i) => {
    if (t.type === 'op') { opBefore = t.value; return; }

    const row   = document.createElement('div');
    row.className = 'fb-term';

    if (opIdx === 0) {
      row.innerHTML = '<span class="fb-term-idx">=</span>';
    } else {
      // Operator button
      const op = opBefore || '+';
      row.innerHTML = `
        <div style="position:relative">
          <button class="fb-op-btn" data-op="${op}" onclick="toggleOpDropdown(this)" title="Смени оператора">${opSymbol(op)}</button>
          <div class="fb-op-dropdown">
            ${['+','-','*','/'].map(o => `<div class="fb-op-dropdown-item" onclick="selectOp(this,'${o}','${cardId}')">${opSymbol(o)}</div>`).join('')}
          </div>
        </div>`;
    }

    // Operand
    if (t.type === 'field') {
      const label = t.value || '— Поле —';
      row.innerHTML += `
        <div style="position:relative;flex:1">
          <button class="fb-field-btn" onclick="toggleFieldDropdown(this,'${cardId}')" data-value="${escH(t.value)}" title="${escH(t.value)}">
            <svg width="10" height="10" viewBox="0 0 20 20" fill="none"><rect x="2" y="5" width="16" height="2.5" rx="1.2" fill="currentColor" opacity=".8"/><rect x="2" y="9" width="12" height="2.5" rx="1.2" fill="currentColor" opacity=".6"/><rect x="2" y="13" width="9" height="2.5" rx="1.2" fill="currentColor" opacity=".4"/></svg>
            <span class="btn-lbl">${escH(label)}</span>
            <span class="btn-arrow">▾</span>
          </button>
          <div class="fb-field-dropdown">
            <input type="text" placeholder="Търси поле…" oninput="filterFieldOpts(this)" autocomplete="off">
            <div class="fb-field-opts-list">
              ${NUM_COLS.map(c => `<div class="fb-field-opt${c===t.value?' selected':''}" onclick="selectField(this,'${escH(c)}','${cardId}')">${escH(c)}</div>`).join('')}
            </div>
          </div>
        </div>`;
    } else if (t.type === 'number') {
      row.innerHTML += `
        <input class="fb-num-inp" type="number" step="any" value="${escH(t.value)}"
               oninput="rebuildFromVisual('${cardId}')" onchange="rebuildFromVisual('${cardId}')">`;
    }

    // Delete button
    row.innerHTML += `<button class="fb-del-btn" onclick="deleteTerm(this,'${cardId}')" title="Изтрий">×</button>`;

    container.appendChild(row);
    opBefore = null;
    opIdx++;
  });

  updatePreview(cardId);
}

// ══ Add term: field or number ══════════════════════════════════════
function addTermField(cardId) {
  const container = document.getElementById('terms_' + cardId);
  const isFirst   = container.children.length === 0;
  const row       = document.createElement('div');
  row.className   = 'fb-term';

  if (isFirst) {
    row.innerHTML = '<span class="fb-term-idx">=</span>';
  } else {
    row.innerHTML = `
      <div style="position:relative">
        <button class="fb-op-btn" data-op="+" onclick="toggleOpDropdown(this)" title="Смени оператора">+</button>
        <div class="fb-op-dropdown">
          ${['+','-','*','/'].map(o => `<div class="fb-op-dropdown-item" onclick="selectOp(this,'${o}','${cardId}')">${opSymbol(o)}</div>`).join('')}
        </div>
      </div>`;
  }

  row.innerHTML += `
    <div style="position:relative;flex:1">
      <button class="fb-field-btn" onclick="toggleFieldDropdown(this,'${cardId}')" data-value="" title="Избери поле">
        <svg width="10" height="10" viewBox="0 0 20 20" fill="none"><rect x="2" y="5" width="16" height="2.5" rx="1.2" fill="currentColor" opacity=".8"/><rect x="2" y="9" width="12" height="2.5" rx="1.2" fill="currentColor" opacity=".6"/><rect x="2" y="13" width="9" height="2.5" rx="1.2" fill="currentColor" opacity=".4"/></svg>
        <span class="btn-lbl">— Избери поле —</span>
        <span class="btn-arrow">▾</span>
      </button>
      <div class="fb-field-dropdown">
        <input type="text" placeholder="Търси поле…" oninput="filterFieldOpts(this)" autocomplete="off">
        <div class="fb-field-opts-list">
          ${NUM_COLS.map(c => `<div class="fb-field-opt" onclick="selectField(this,'${escH(c)}','${cardId}')">${escH(c)}</div>`).join('')}
        </div>
      </div>
    </div>
    <button class="fb-del-btn" onclick="deleteTerm(this,'${cardId}')" title="Изтрий">×</button>`;

  container.appendChild(row);
  rebuildFromVisual(cardId);
}

function addTermNumber(cardId) {
  const container = document.getElementById('terms_' + cardId);
  const isFirst   = container.children.length === 0;
  const row       = document.createElement('div');
  row.className   = 'fb-term';

  if (isFirst) {
    row.innerHTML = '<span class="fb-term-idx">=</span>';
  } else {
    row.innerHTML = `
      <div style="position:relative">
        <button class="fb-op-btn" data-op="+" onclick="toggleOpDropdown(this)">+</button>
        <div class="fb-op-dropdown">
          ${['+','-','*','/'].map(o => `<div class="fb-op-dropdown-item" onclick="selectOp(this,'${o}','${cardId}')">${opSymbol(o)}</div>`).join('')}
        </div>
      </div>`;
  }

  row.innerHTML += `
    <input class="fb-num-inp" type="number" step="any" value="1"
           oninput="rebuildFromVisual('${cardId}')" onchange="rebuildFromVisual('${cardId}')">
    <button class="fb-del-btn" onclick="deleteTerm(this,'${cardId}')" title="Изтрий">×</button>`;

  container.appendChild(row);
  rebuildFromVisual(cardId);
}

function deleteTerm(btn, cardId) {
  btn.closest('.fb-term').remove();
  rebuildFromVisual(cardId);
}

// ══ Operator dropdown ══════════════════════════════════════════════
function toggleOpDropdown(btn) {
  const dd = btn.nextElementSibling;
  // Close all others first
  document.querySelectorAll('.fb-op-dropdown.open').forEach(d => {
    if (d !== dd) d.classList.remove('open');
  });
  dd.classList.toggle('open');
}
function selectOp(item, op, cardId) {
  const btn = item.closest('.fb-op-dropdown').previousElementSibling;
  btn.dataset.op   = op;
  btn.textContent  = opSymbol(op);
  item.closest('.fb-op-dropdown').classList.remove('open');
  rebuildFromVisual(cardId);
}
function opSymbol(op) {
  return {'+':'+', '-':'−', '*':'×', '/':'÷'}[op] || op;
}

// ══ Field dropdown ═════════════════════════════════════════════════
function toggleFieldDropdown(btn, cardId) {
  const dd = btn.nextElementSibling;
  // Close all others
  document.querySelectorAll('.fb-field-dropdown.open').forEach(d => {
    if (d !== dd) d.classList.remove('open');
  });
  dd.classList.toggle('open');
  if (dd.classList.contains('open')) {
    const inp = dd.querySelector('input[type=text]');
    if (inp) { inp.value = ''; filterFieldOpts(inp); setTimeout(() => inp.focus(), 30); }
  }
}
function filterFieldOpts(inp) {
  const q    = inp.value.toLowerCase();
  const list = inp.nextElementSibling;
  list.querySelectorAll('.fb-field-opt').forEach(opt => {
    opt.style.display = opt.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
function selectField(optEl, fieldName, cardId) {
  const dd  = optEl.closest('.fb-field-dropdown');
  const btn = dd.previousElementSibling;
  btn.dataset.value = fieldName;
  btn.querySelector('.btn-lbl').textContent = fieldName || '— Избери поле —';
  dd.querySelectorAll('.fb-field-opt').forEach(o => o.classList.remove('selected'));
  optEl.classList.add('selected');
  dd.classList.remove('open');
  rebuildFromVisual(cardId);
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
  if (!e.target.closest('.fb-op-btn') && !e.target.closest('.fb-op-dropdown')) {
    document.querySelectorAll('.fb-op-dropdown.open').forEach(d => d.classList.remove('open'));
  }
  if (!e.target.closest('.fb-field-btn') && !e.target.closest('.fb-field-dropdown')) {
    document.querySelectorAll('.fb-field-dropdown.open').forEach(d => d.classList.remove('open'));
  }
  if (!e.target.closest('.carriers-apply-dropdown')) {
    document.querySelectorAll('.carriers-apply-list.open').forEach(d => d.classList.remove('open'));
  }
});

// ══ Rebuild raw formula from visual ═══════════════════════════════
function rebuildFromVisual(cardId) {
  const container = document.getElementById('terms_' + cardId);
  if (!container) return;
  let formula = '';

  container.querySelectorAll('.fb-term').forEach((row, i) => {
    const opBtn    = row.querySelector('.fb-op-btn');
    const fieldBtn = row.querySelector('.fb-field-btn');
    const numInp   = row.querySelector('.fb-num-inp');

    if (i > 0 && opBtn) {
      formula += ' ' + opBtn.dataset.op + ' ';
    }
    if (fieldBtn && fieldBtn.dataset.value) {
      formula += '{' + fieldBtn.dataset.value + '}';
    } else if (numInp) {
      formula += numInp.value || '0';
    }
  });

  const rawInp = document.getElementById('raw_inp_' + cardId);
  if (rawInp) rawInp.value = formula.trim();

  updatePreview(cardId);
  updateBadge(cardId);
}

// ══ Update equation preview ════════════════════════════════════════
function updatePreview(cardId) {
  const rawInp = document.getElementById('raw_inp_' + cardId);
  const eq     = document.getElementById('eq_' + cardId);
  const card   = document.getElementById(cardId);
  if (!rawInp || !eq) return;

  const f = rawInp.value.trim();
  if (!f) { eq.innerHTML = '<span class="eq-empty">Добави полета →</span>'; return; }

  const colName = card.dataset.col;
  const tokens  = parseFormula(f);
  let html      = `<span class="eq-result-lbl">${escH(colName)} =</span> `;

  tokens.forEach(t => {
    if (t.type === 'field') {
      html += `<span class="eq-field-btn" title="${escH(t.value)}">${escH(t.value)}</span>`;
    } else if (t.type === 'op') {
      const names = {'+':'плюс', '-':'минус', '*':'по', '/':'делено на'};
      html += `<span class="eq-op"> ${names[t.value]||t.value} </span>`;
    } else if (t.type === 'number') {
      html += `<span class="eq-num-chip">${escH(t.value)}</span>`;
    }
  });
  eq.innerHTML = html;
}

function updateBadge(cardId) {
  const rawInp = document.getElementById('raw_inp_' + cardId);
  const badge  = document.getElementById('badge_' + cardId);
  if (!rawInp || !badge) return;
  const f = rawInp.value.trim();
  badge.textContent = f || '— няма формула —';
  badge.className   = 'fc-badge' + (f ? '' : ' empty');
  badge.title       = f;
}

// ══ Save formula (no apply) ════════════════════════════════════════
function saveFormula(cardId) {
  const card    = document.getElementById(cardId);
  const col     = card.dataset.col;
  const rawInp  = document.getElementById('raw_inp_' + cardId);
  const status  = document.getElementById('status_' + cardId);
  const undoBtn = document.getElementById('undo_' + cardId);
  const formula = rawInp ? rawInp.value.trim() : '';

  // Store current in history before saving new
  if (!formulaHistory[cardId]) formulaHistory[cardId] = [];
  const prev = formulaHistory[cardId].slice(-1)[0];
  if (prev !== formula) formulaHistory[cardId].push(formula);

  status.textContent = 'Запазване…'; status.style.color = 'var(--gold)';
  fetch('/settings/save-formulas', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ formulas: { [col]: formula } })
  })
  .then(r => r.json())
  .then(d => {
    status.textContent = d.success ? '✓ Запазено' : ('✗ ' + (d.error||'Грешка'));
    status.style.color = d.success ? 'var(--green)' : 'var(--red)';
    if (d.success && formulaHistory[cardId].length > 1) {
      undoBtn.style.display = '';
    }
    setTimeout(() => { status.textContent=''; status.style.color=''; }, 3000);
  })
  .catch(() => { status.textContent='✗ Грешка'; status.style.color='var(--red)'; });
}

// ══ Apply to all products ══════════════════════════════════════════
function applyFormulaAll(cardId) {
  const card    = document.getElementById(cardId);
  const col     = card.dataset.col;
  const rawInp  = document.getElementById('raw_inp_' + cardId);
  const status  = document.getElementById('status_' + cardId);
  const formula = rawInp ? rawInp.value.trim() : '';

  if (!formula) { alert('Моля въведи формула преди да прилагаш.'); return; }
  if (!confirm(`Ще приложиш формулата към "${col}" за ВСИЧКИ продукти.\n\nФормула: ${formula}\n\nПродължи?`)) return;

  status.textContent = '⏳ Изчисляване…'; status.style.color = 'var(--gold)';

  fetch('/settings/save-formulas', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ formulas: { [col]: formula } })
  })
  .then(r => r.json())
  .then(() => fetch('/api/apply-formula', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ column: col, formula })
  }))
  .then(r => r.json())
  .then(d => {
    status.textContent = d.success ? `✓ Приложено към ${d.updated||'?'} продукта` : ('✗ ' + (d.error||'Грешка'));
    status.style.color = d.success ? 'var(--green)' : 'var(--red)';
    setTimeout(() => { status.textContent=''; status.style.color=''; }, 5000);
  })
  .catch(() => { status.textContent='✗ Мрежова грешка'; status.style.color='var(--red)'; });
}

// ══ Apply to specific carriers ════════════════════════════════════
function toggleCarriersDropdown(cardId) {
  const list = document.getElementById('cap_list_' + cardId);
  document.querySelectorAll('.carriers-apply-list.open').forEach(l => {
    if (l !== list) l.classList.remove('open');
  });
  list.classList.toggle('open');
}

function applyFormulaCarriers(cardId) {
  const card     = document.getElementById(cardId);
  const col      = card.dataset.col;
  const rawInp   = document.getElementById('raw_inp_' + cardId);
  const status   = document.getElementById('status_' + cardId);
  const list     = document.getElementById('cap_list_' + cardId);
  const formula  = rawInp ? rawInp.value.trim() : '';
  const selected = [...list.querySelectorAll('input[type=checkbox]:checked')].map(cb => cb.value);

  if (!formula) { alert('Моля въведи формула.'); return; }
  if (!selected.length) { alert('Избери поне един превозвач.'); return; }

  const names = selected.map(id => {
    const c = CARRIERS.find(x => x.id === id);
    return c ? c.name : id;
  }).join(', ');

  if (!confirm(`Ще приложиш формулата към "${col}" за превозвачи: ${names}.\n\nФормула: ${formula}\n\nПродължи?`)) return;

  list.classList.remove('open');
  status.textContent = '⏳ Изчисляване…'; status.style.color = 'var(--gold)';

  fetch('/api/apply-formula', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ column: col, formula, carrier_ids: selected })
  })
  .then(r => r.json())
  .then(d => {
    status.textContent = d.success ? `✓ Приложено (${names})` : ('✗ ' + (d.error||'Грешка'));
    status.style.color = d.success ? 'var(--green)' : 'var(--red)';
    setTimeout(() => { status.textContent=''; status.style.color=''; }, 5000);
  })
  .catch(() => { status.textContent='✗ Грешка'; status.style.color='var(--red)'; });
}

// ══ Undo formula ══════════════════════════════════════════════════
function undoFormula(cardId) {
  const hist    = formulaHistory[cardId];
  if (!hist || hist.length < 2) return;
  hist.pop(); // Remove current
  const prev   = hist[hist.length - 1] || '';
  const rawInp = document.getElementById('raw_inp_' + cardId);
  if (rawInp) {
    rawInp.value = prev;
    parseRawToVisual(cardId);
  }
  if (hist.length <= 1) {
    document.getElementById('undo_' + cardId).style.display = 'none';
  }
  // Auto-save the undone state
  saveFormula(cardId);
}

// ══ Save all formulas ══════════════════════════════════════════════
function saveAllFormulas() {
  const formulas = {};
  document.querySelectorAll('.fc').forEach(card => {
    const col  = card.dataset.col;
    const rawInp = document.getElementById('raw_inp_' + card.id);
    if (rawInp && col) formulas[col] = rawInp.value.trim();
  });
  const btn = document.getElementById('save-all-btn');
  btn.disabled = true; btn.textContent = 'Запазване…';

  fetch('/settings/save-formulas', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ formulas })
  })
  .then(r => r.json())
  .then(d => {
    btn.textContent = d.success ? '✓ Запазено!' : '✗ Грешка';
    setTimeout(() => {
      btn.disabled = false;
      btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M5 13V6a1 1 0 011-1h8a1 1 0 011 1v7M4 17h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg> Запази всички формули';
    }, 2500);
  })
  .catch(() => { btn.disabled = false; btn.textContent = '✗ Грешка'; });
}

// ══ Add new formula column (independent) ══════════════════════════
function addFormulaColumn() {
  const sel    = document.getElementById('new-col-select');
  const custom = document.getElementById('new-col-custom');
  const colName = (custom.value.trim() || sel.value || '').trim();
  if (!colName) { alert('Избери или въведи название на колоната.'); return; }
  if (document.querySelector(`.fc[data-col="${CSS.escape(colName)}"]`)) {
    alert('Тази колона вече е добавена.'); return;
  }

  const idx   = 'fc_new_' + Date.now();
  const div   = document.createElement('div');
  div.id      = idx;
  div.className = 'fc';
  div.dataset.col = colName;

  div.innerHTML = `
    <div class="fc-head" onclick="toggleCard('${idx}')">
      <div class="fc-letter" style="background:#88888822;color:#888;border:1px solid #88888833">+</div>
      <div class="fc-col-name">${escH(colName)}</div>
      <code class="fc-badge empty" id="badge_${idx}">— нова колона —</code>
      <svg class="fc-chevron" viewBox="0 0 20 20" fill="none"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <div class="fc-body">
      <div class="fe-preview">
        <div class="fe-preview-label">Визуализация</div>
        <div class="fe-preview-eq" id="eq_${idx}"><span class="eq-empty">Добави полета →</span></div>
      </div>
      <div class="fb-terms" id="terms_${idx}"></div>
      <div class="fb-add-row">
        <button class="btn btn-ghost btn-sm" onclick="addTermField('${idx}')">+ Поле</button>
        <button class="btn btn-ghost btn-sm" onclick="addTermNumber('${idx}')">+ Число</button>
      </div>
      <div class="fb-raw-section">
        <span class="fb-raw-toggle" onclick="toggleRaw('${idx}')">↓ Ред формула</span>
        <div class="fb-raw-area open" id="raw_${idx}">
          <textarea class="fb-raw-inp" id="raw_inp_${idx}" rows="2"
                    placeholder="{Продажна Цена в Амазон  - Brutto} / 1.19"
                    oninput="parseRawToVisual('${idx}')"></textarea>
        </div>
      </div>
      <div class="fc-actions">
        <button class="btn btn-ghost btn-sm" onclick="saveFormula('${idx}')">Запази</button>
        <button class="btn btn-primary btn-sm" onclick="applyFormulaAll('${idx}')">Приложи към всички</button>
        <button class="btn btn-ghost btn-sm" onclick="undoFormula('${idx}')" id="undo_${idx}" style="display:none;color:var(--amber)">Отмени</button>
        <div class="fc-status" id="status_${idx}"></div>
      </div>
    </div>`;

  document.getElementById('formula-list').appendChild(div);
  div.classList.add('fc-open');
  sel.value = ''; custom.value = '';

  // Save extra column to backend
  fetch('/api/save-extra-formula-col', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ col: colName })
  }).catch(() => {});
}

// ══ Utility ═══════════════════════════════════════════════════════
function escH(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ══ Init all open cards ════════════════════════════════════════════
document.querySelectorAll('.fc.fc-open').forEach(card => initVisualFromRaw(card.id));
</script>
