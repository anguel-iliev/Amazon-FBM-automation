// ╔══════════════════════════════════════════════════════════════╗
//  GMAIL → DRIVE  |  АВТОМАТИЗАЦИЯ НА ДОСТАВЧИЦИ  v4.6
//  Стартирай: testScript() → processEmailsAndUpload() → setupTrigger()
//
//  Промени v4.6:
//  - SELF-CHAINING: при достигане на 5-мин лимит скриптът сам
//    планира продължение след 90 секунди — напълно автоматично.
//    Пусни processEmailsAndUpload() ВЕДНЪЖ — всичко останало
//    се случва само.
//  Промени v4.5: BATCH MODE
//  Промени v4.4: STRICT MODE (само MANUAL_MAPPING)
// ╚══════════════════════════════════════════════════════════════╝

// ============================================================
//  КОНФИГУРАЦИЯ  —  редактирай само тук
// ============================================================
var CONFIG = {
  SUPPLIER_LABEL:  'Доставчик',
  PROCESSED_LABEL: 'Обработени',
  PARENT_FOLDER_ID: '100T4KgyVIXhKlJczQv7DR9CJlV27DbUx',
  SUBFOLDERS: {
    invoice: 'Фактури',
    price:   'Цени',
    other:   'Други',
  },

  // Домейн (или частта от него) → точно иле на папката в Drive
  MANUAL_MAPPING: {
    'axxon':                       'Axxon',
    'amperel':                     'Amperel',
    'fortuna':                     'Fortuna',
    'orbico':                      'Orbico',
    'iventas':                     'Iventas',
    'bebolino':                    'Bebolino',
    'buldent':                     'Buldent',
    'remedium':                    'Remedium',
    'argoprima':                   'Argoprima',
    'uvex':                        'Uvex',
    'comsed':                      'Comsed',
    'bestwholesalecompany':        'Best whole sale company',
    'blackweeknovemberpromotions': 'Giochi Giachi IT',
    'giochigiachi':                'Giochi Giachi IT',
    'ellecosmetique':              'Elle cosmetique',
    'yutikanatural':               'Yutika natural',
    'irbis':                       'Uvex',
    'agiva':                       'Agiva',
    'makave':                      'Makave',
    'njpartners-bg':               'Töpfer Bulgaria',
    'njpartnersbg':                'Töpfer Bulgaria',
    'topfer':                      'Töpfer Bulgaria',
  },

  // Имейл адреси, чиито съобщения се игнорират напълно
  BLOCKED_SENDERS: [
    'bsabalans@gmail.com',
    'balansvarna@gmail.com',
    'idev7.office@gmail.com',
  ],

  EXCLUDE_KEYWORDS: ['test', 'draft', 'sample', 'пробен', 'чернова'],

  // Разширения, които изобщо не се качват
  EXCLUDE_EXTENSIONS: [
    // Системни / опасни
    'tmp', 'cache', 'log', 'bak', 'exe', 'bat', 'cmd', 'scr', 'vbs', 'js',
    // Презентации
    'pptx', 'ppt', 'pptm', 'ppsx', 'potx', 'potm', 'ppsm', 'ppa', 'ppam', 'odp',
    // Аудио
    'mp3', 'aac', 'm4a', 'wav', 'flac', 'wma', 'ogg', 'aiff', 'mid', 'midi',
  ],

  IMAGE_EXTENSIONS: ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'svg', 'webp', 'ico', 'tiff', 'tif'],
  DANGEROUS_MIME:   ['application/x-msdownload', 'application/x-executable'],

  MAX_FILE_SIZE_MB:    50,
  MAX_RETRIES:         3,
  BATCH_SIZE:          20,
  MAX_FOLDER_NAME_LEN: 40,
  LOG_TO_SHEET:        false,
  LOG_SHEET_ID:        '',

  // true  = всяка версия получава дата-префикс, старите се пазят
  // false = пропуска файл ако вече съществува
  TIMESTAMP_FILES: true,

  // Обработвай само имейли след тази дата (само при първо пускане)
  // При следващи пускания скриптът помни последната си дата автоматично
  INITIAL_START_DATE: '2025/01/01',
};

// ============================================================
//  ТИП НА ФАЙЛ
// ============================================================
function getFileType(fileName) {
  var f   = fileName.toLowerCase();
  var ext = f.split('.').pop();

  // FIX v4.1: добавен .ods (OpenDocument Spreadsheet)
  if (ext === 'xlsx' || ext === 'xls' || ext === 'xlsm' || ext === 'csv' || ext === 'ods') return 'price';

  if (ext === 'pdf') {
    if (/price|прайс|цена|цени|quote|quotation|offer|оферт|каталог|catalog|tariff/.test(f)) return 'price';
    return 'invoice';
  }

  if (ext === 'doc' || ext === 'docx') {
    if (/price|прайс|цена|цени|quote|offer|оферт|каталог|catalog/.test(f)) return 'price';
    return 'invoice';
  }

  if (/invoice|фактур|сч[её]т|bill|receipt/.test(f)) return 'invoice';
  if (/price|прайс|цена|цени|quote|quotation|offer|оферт|каталог|catalog|tariff/.test(f)) return 'price';

  return 'other';
}

// ============================================================
//  ГЛАВНИ ФУНКЦИИ
// ============================================================
function processEmailsAndUpload() { _run('all'); }
function processUnreadEmails()    { _run('new'); }

// ============================================================
//  ЯДРО  —  BATCH MODE (v4.5)
//  Обработва по BATCH_SIZE нишки на пускане.
//  Запазва прогреса и продължава при следващо пускане.
//  Пусни processEmailsAndUpload() многократно докато
//  логът не покаже "Всички нишки обработени".
// ============================================================
function _run(mode) {
  var t0        = Date.now();
  var stats     = _emptyStats();
  var MAX_MS    = 5 * 60 * 1000; // 5 мин — с буфер преди лимита от 6 мин
  var props     = PropertiesService.getScriptProperties();
  var batchKey  = 'BATCH_OFFSET_' + mode;

  try {
    var processedLabel = _getOrCreateLabel(CONFIG.PROCESSED_LABEL);
    var threads        = _getThreads(mode);

    // Вземи offset от предишно прекъснато пускане
    var offset = parseInt(props.getProperty(batchKey) || '0');
    if (offset >= threads.length) {
      // Всичко е обработено — нулирай offset
      props.deleteProperty(batchKey);
      offset = 0;
    }

    var total = threads.length;
    Logger.log('');
    Logger.log('════════════════════════════════════════════════════════════');
    Logger.log('  СТАРТ  |  ' + (mode === 'all' ? 'всички' : 'нови') +
               '  |  нишки: ' + total + '  |  старт от: ' + offset);
    Logger.log('════════════════════════════════════════════════════════════');
    stats.threads = total;

    for (var i = offset; i < threads.length; i++) {

      // Провери дали оставащото време е достатъчно
      if (Date.now() - t0 > MAX_MS) {
        var nextOffset = i;
        props.setProperty(batchKey, nextOffset.toString());
        Logger.log('');
        Logger.log('⏱ Лимит наближава — паузиран при нишка ' + (i+1) + '/' + total);
        _scheduleNextBatch(); // ← автоматично продължение след 90 сек
        _printStats(stats, Date.now() - t0);
        return;
      }

      var thread  = threads[i];
      var subject = thread.getFirstMessageSubject();

      if (mode === 'all' && _threadHasLabel(thread, CONFIG.PROCESSED_LABEL)) {
        Logger.log('[' + (i+1) + '/' + total + '] Пропусната: ' + subject);
        continue;
      }

      if (_shouldExclude(subject)) {
        Logger.log('[' + (i+1) + '/' + total + '] Отфилтрирана: ' + subject);
        stats.excluded++;
        thread.addLabel(processedLabel);
        continue;
      }

      Logger.log('');
      Logger.log('[' + (i+1) + '/' + total + '] ' + subject);

      var messages = thread.getMessages();
      for (var j = 0; j < messages.length; j++) {
        _processMessage(messages[j], stats);
      }
      thread.addLabel(processedLabel);
    }

    // Всичко обработено успешно
    props.deleteProperty(batchKey);
    Logger.log('');
    Logger.log('✅ Всички ' + total + ' нишки обработени!');

  } catch (err) {
    Logger.log('КРИТИЧНА ГРЕШКА: ' + err.message + '\n' + err.stack);
    stats.fatalErrors++;
  }

  var removed = _cleanEmptyFolders();
  if (removed > 0) Logger.log('Изтрити ' + removed + ' празни папки.');

  _saveLastRunTimestamp();
  _printStats(stats, Date.now() - t0);
  _logToSheet('PROCESS', 'Качени: ' + stats.uploaded + ', Грешки: ' + stats.errors);
}

function _processMessage(message, stats) {
  stats.emails++;

  var from         = message.getFrom();
  var subject      = message.getSubject();

  // FIX v4.3: пропусни имейли от блокирани адреси
  if (_isBlockedSender(from)) {
    Logger.log('  [BLK] Блокиран подател: ' + from);
    stats.excluded++;
    return;
  }

  var body         = _safeGetBody(message);
  var htmlBody     = _safeGetHtmlBody(message);
  var supplierName = extractSupplierName(body, from, subject);

  // FIX v4.4: ако доставчикът не е в MANUAL_MAPPING → пропусни
  if (!supplierName) {
    Logger.log('  [---] Непознат доставчик от: ' + from + ' — добави в MANUAL_MAPPING за да се обработва');
    stats.excluded++;
    return;
  }

  var attachments  = message.getAttachments();
  var sheetLinks   = _extractSheetLinks(htmlBody || body);

  if (attachments.length === 0 && sheetLinks.length === 0) return;

  var hasRealFiles = false;
  for (var k = 0; k < attachments.length; k++) {
    var att  = attachments[k];
    var name = att.getName();
    var mime = att.getContentType();
    if (!_isImage(name, mime) && _isSafe(name, mime).ok && !_isExcludedExt(name)) {
      hasRealFiles = true;
      break;
    }
  }
  if (sheetLinks.length > 0) hasRealFiles = true;

  // FIX v4.1: images статистиката брои всеки attachment правилно
  if (!hasRealFiles) {
    Logger.log('  [---] ' + supplierName + ' — само изображения, папка не се създава');
    stats.images += attachments.length;
    return;
  }

  var folder = getOrCreateFolder(supplierName);
  Logger.log('  Доставчик: ' + supplierName + ' | файлове: ' + attachments.length + ' | sheet линкове: ' + sheetLinks.length);

  for (var k = 0; k < attachments.length; k++) {
    var result = _processAttachment(attachments[k], message, folder);
    if (stats[result] !== undefined) { stats[result]++; } else { stats[result] = 1; }
    if (result === 'uploaded') stats.byType[getFileType(attachments[k].getName())]++;
  }

  for (var s = 0; s < sheetLinks.length; s++) {
    var sr = _processSheetLink(sheetLinks[s], folder);
    if (stats[sr] !== undefined) { stats[sr]++; } else { stats[sr] = 1; }
    if (sr === 'uploaded') stats.byType['price']++;
  }
}

function _extractSheetLinks(body) {
  if (!body) return [];
  var links = [];
  var seen  = {};
  var patterns = [
    /https:\/\/docs\.google\.com\/spreadsheets\/d\/([a-zA-Z0-9_\-]+)/g,
    /https:\/\/drive\.google\.com\/file\/d\/([a-zA-Z0-9_\-]+)/g,
    /https:\/\/drive\.google\.com\/open\?id=([a-zA-Z0-9_\-]+)/g,
  ];
  for (var p = 0; p < patterns.length; p++) {
    var re = new RegExp(patterns[p].source, 'g');
    var match;
    while ((match = re.exec(body)) !== null) {
      var id = match[1];
      if (!seen[id]) { seen[id] = true; links.push(id); }
    }
  }
  return links;
}

function _processSheetLink(fileId, supplierFolder) {
  try {
    var file      = DriveApp.getFileById(fileId);
    var name      = file.getName();
    var subFolder = getOrCreateSubFolder(supplierFolder, CONFIG.SUBFOLDERS['price']);
    if (_fileExists(subFolder, name)) { Logger.log('    [dup] Sheet: ' + name); return 'duplicates'; }
    file.makeCopy(name, subFolder);
    Logger.log('    [ OK] [Цени/Sheet] ' + name);
    return 'uploaded';
  } catch (err) {
    Logger.log('    [---] Sheet без достъп: ' + fileId + ' (' + err.message + ')');
    return 'skipped';
  }
}

function _safeGetHtmlBody(message) { try { return message.getBody() || ''; } catch(e) { return ''; } }

function _processAttachment(att, message, supplierFolder) {
  var fileName = att.getName();
  var mimeType = att.getContentType();

  if (_isImage(fileName, mimeType)) { Logger.log('    [img] ' + fileName); return 'images'; }

  var safe = _isSafe(fileName, mimeType);
  if (!safe.ok) { Logger.log('    [BLK] ' + fileName + ' (' + safe.reason + ')'); return 'blocked'; }

  if (_isExcludedExt(fileName)) { Logger.log('    [ext] ' + fileName); return 'skipped'; }

  if (CONFIG.MAX_FILE_SIZE_MB > 0) {
    var sizeMB = att.getSize() / (1024 * 1024);
    if (sizeMB > CONFIG.MAX_FILE_SIZE_MB) { Logger.log('    [big] ' + fileName + ' (' + sizeMB.toFixed(1) + ' MB)'); return 'skipped'; }
  }

  if (CONFIG.MAX_EMAIL_AGE_DAYS > 0) {
    var days = (Date.now() - message.getDate().getTime()) / 86400000;
    if (days > CONFIG.MAX_EMAIL_AGE_DAYS) { Logger.log('    [old] ' + fileName); return 'skipped'; }
  }

  try {
    var fileType   = getFileType(fileName);
    var subFolder  = getOrCreateSubFolder(supplierFolder, CONFIG.SUBFOLDERS[fileType]);
    var savedName  = _buildFileName(fileName, message.getDate());

    // Дубликат проверка:
    // При TIMESTAMP_FILES=true  → проверяваме точното ime с timestamp (уникално)
    // При TIMESTAMP_FILES=false → проверяваме оригиналното ime
    if (_fileExists(subFolder, savedName)) {
      Logger.log('    [dup] ' + savedName);
      return 'duplicates';
    }

    // Качи с новото (евентуално timestamped) иле
    var blob = att.copyBlob().setName(savedName);
    _uploadBlobWithRetry(blob, subFolder);
    Logger.log('    [ OK] [' + CONFIG.SUBFOLDERS[fileType] + '] ' + savedName);
    return 'uploaded';
  } catch (err) {
    Logger.log('    [ERR] ' + fileName + ' — ' + err.message);
    return 'errors';
  }
}

// Изгражда финалното иле за запазване.
// При TIMESTAMP_FILES=true: "2025-01-15_price_list.xlsx"
// При TIMESTAMP_FILES=false: "price_list.xlsx" (оригиналното иле)
function _buildFileName(originalName, emailDate) {
  if (!CONFIG.TIMESTAMP_FILES) return originalName;
  var d      = emailDate || new Date();
  var year   = d.getFullYear();
  var month  = ('0' + (d.getMonth() + 1)).slice(-2);
  var day    = ('0' + d.getDate()).slice(-2);
  var prefix = year + '-' + month + '-' + day + '_';
  // Не добавяй префикс ако вече започва с дата (2025-...)
  if (/^\d{4}-\d{2}-\d{2}_/.test(originalName)) return originalName;
  return prefix + originalName;
}

function _uploadBlobWithRetry(blob, folder) {
  for (var attempt = 1; attempt <= CONFIG.MAX_RETRIES; attempt++) {
    try { folder.createFile(blob); return; }
    catch (err) {
      if (attempt === CONFIG.MAX_RETRIES) throw err;
      Logger.log('    Опит ' + attempt + ' неуспешен, повтарям...');
      Utilities.sleep(1500 * attempt);
    }
  }
}

function _uploadWithRetry(att, folder) {
  for (var attempt = 1; attempt <= CONFIG.MAX_RETRIES; attempt++) {
    try { folder.createFile(att); return; }
    catch (err) {
      if (attempt === CONFIG.MAX_RETRIES) throw err;
      Logger.log('    Опит ' + attempt + ' неуспешен, повтарям...');
      Utilities.sleep(1500 * attempt);
    }
  }
}

// ============================================================
//  РАЗПОЗНАВАНЕ НА ДОСТАВЧИК  —  STRICT MODE (v4.4)
//
//  Единственият надежден начин за разпознаване е MANUAL_MAPPING.
//  Ако подателят не е в MANUAL_MAPPING → връща null → имейлът се пропуска.
//  Така НИКОГА няма да се създадат папки "Gmail", "Abv", "Agiva" и т.н.
//
//  За да добавиш нов доставчик: добави ред в MANUAL_MAPPING в CONFIG.
// ============================================================
function extractSupplierName(body, emailAddress, subject) {
  return _fromManualMap(emailAddress);
  // Забележка: _fromSignature, _fromBody, _fromSubject, _fromDomain са
  // умишлено ИЗКЛЮЧЕНИ — създаваха грешни папки от неразпознати домейни.
}

function _fromManualMap(emailAddress) {
  var domain = _parseDomain(emailAddress);
  var keys   = Object.keys(CONFIG.MANUAL_MAPPING);
  for (var i = 0; i < keys.length; i++) {
    if (domain.indexOf(keys[i]) !== -1) return CONFIG.MANUAL_MAPPING[keys[i]];
  }
  return null;
}

function _parseDomain(emailAddress) {
  var match = emailAddress.match(/<(.+)>/);
  var email = match ? match[1] : emailAddress;
  return ((email.split('@')[1]) || email).toLowerCase();
}

// ============================================================
//  ПОМОЩНИ ФУНКЦИИ
// ============================================================
function cleanSupplierName(name) {
  if (!name) return '';
  // FIX v4.1: включени кирилски символи в regex
  name = name.replace(/[^\w\s\-&.а-яА-ЯёЁ]/g, ' ').trim().replace(/\s+/g, ' ').replace(/\.+$/, '');
  name = name.split(' ').filter(function(w) { return w.length > 0; })
    .map(function(w) { return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase(); })
    .join(' ');
  if (name.length > CONFIG.MAX_FOLDER_NAME_LEN) {
    name = name.split(' ').slice(0, 3).join(' ').substring(0, CONFIG.MAX_FOLDER_NAME_LEN);
  }
  return name;
}

function _isImage(fileName, mimeType) {
  var ext = fileName.split('.').pop().toLowerCase();
  return CONFIG.IMAGE_EXTENSIONS.indexOf(ext) !== -1 || mimeType.indexOf('image/') === 0;
}

function _isExcludedExt(fileName) {
  var ext = fileName.split('.').pop().toLowerCase();
  return CONFIG.EXCLUDE_EXTENSIONS.indexOf(ext) !== -1;
}

function _isSafe(fileName, mimeType) {
  var ext = fileName.split('.').pop().toLowerCase();
  if (CONFIG.EXCLUDE_EXTENSIONS.indexOf(ext) !== -1) return { ok: false, reason: 'опасно разширение' };
  for (var i = 0; i < CONFIG.DANGEROUS_MIME.length; i++) {
    if (mimeType.indexOf(CONFIG.DANGEROUS_MIME[i]) !== -1) return { ok: false, reason: 'опасен MIME' };
  }
  return { ok: true };
}

function _shouldExclude(subject) {
  var s = (subject || '').toLowerCase();
  for (var i = 0; i < CONFIG.EXCLUDE_KEYWORDS.length; i++) {
    if (s.indexOf(CONFIG.EXCLUDE_KEYWORDS[i]) !== -1) return true;
  }
  return false;
}

// FIX v4.3: проверява дали подателят е в блокирания списък
function _isBlockedSender(emailAddress) {
  // Извлича чистия имейл адрес от "Иван Иванов <ivan@example.com>"
  var match = emailAddress.match(/<(.+)>/);
  var email = (match ? match[1] : emailAddress).toLowerCase().trim();
  for (var i = 0; i < CONFIG.BLOCKED_SENDERS.length; i++) {
    if (email === CONFIG.BLOCKED_SENDERS[i].toLowerCase()) return true;
  }
  return false;
}

function _fileExists(folder, fileName) { try { return folder.getFilesByName(fileName).hasNext(); } catch(e) { return false; } }

function _threadHasLabel(thread, labelName) {
  var labels = thread.getLabels();
  for (var i = 0; i < labels.length; i++) { if (labels[i].getName() === labelName) return true; }
  return false;
}

function _requireLabel(name) {
  var l = GmailApp.getUserLabelByName(name);
  if (!l) throw new Error('Лейбълът "' + name + '" не съществува!');
  return l;
}

function _getOrCreateLabel(name) { return GmailApp.getUserLabelByName(name) || GmailApp.createLabel(name); }
function _safeGetBody(message)   { try { return message.getPlainTextBody() || ''; } catch(e) { return ''; } }

// FIX v4.5: премахнато in:inbox — имейлите с лейбъл "Доставчик" може да са
// архивирани и да нямат INBOX флаг. Лейбълът е достатъчен филтър.
// Блокираните адреси (BLOCKED_SENDERS) защитават от нежелани имейли.
function _getThreads(mode) {
  var afterDate = _getStartDate();
  var d    = new Date(afterDate);
  var yyyy = d.getFullYear();
  var mm   = ('0' + (d.getMonth() + 1)).slice(-2);
  var dd   = ('0' + d.getDate()).slice(-2);
  var afterStr = 'after:' + yyyy + '/' + mm + '/' + dd;

  // Само лейбъл Доставчик + след дата (без in:inbox)
  var query = 'label:"' + CONFIG.SUPPLIER_LABEL + '" ' + afterStr;
  if (mode === 'new') {
    query += ' -label:"' + CONFIG.PROCESSED_LABEL + '"';
  }

  Logger.log('  Gmail query: ' + query);
  return GmailApp.search(query);
}

// Връща Date от който да започне обработката:
// - Ако има записана дата от предишно пускане → ползва нея
// - Ако не → ползва INITIAL_START_DATE от CONFIG
function _getStartDate() {
  var props = PropertiesService.getScriptProperties();
  var saved = props.getProperty('LAST_RUN_TIMESTAMP');
  if (saved) {
    Logger.log('  Последно пускане: ' + new Date(parseInt(saved)).toLocaleString('bg-BG'));
    return new Date(parseInt(saved));
  }
  Logger.log('  Първо пускане — старт от: ' + CONFIG.INITIAL_START_DATE);
  return new Date(CONFIG.INITIAL_START_DATE);
}

// Записва текущото време като "последно пускане"
function _saveLastRunTimestamp() {
  PropertiesService.getScriptProperties().setProperty('LAST_RUN_TIMESTAMP', Date.now().toString());
  Logger.log('  Записана дата на пускане: ' + new Date().toLocaleString('bg-BG'));
}

// Нулира записаната дата (при нужда от пълна преобработка)
function resetLastRunDate() {
  PropertiesService.getScriptProperties().deleteProperty('LAST_RUN_TIMESTAMP');
  Logger.log('Датата на последно пускане е нулирана. Следващото пускане ще започне от ' + CONFIG.INITIAL_START_DATE);
}

function getOrCreateFolder(folderName) {
  var parent = DriveApp.getFolderById(CONFIG.PARENT_FOLDER_ID);
  var it     = parent.getFoldersByName(folderName);
  if (it.hasNext()) return it.next();
  Logger.log('    Нова папка: ' + folderName);
  return parent.createFolder(folderName);
}

function getOrCreateSubFolder(parent, name) {
  var it = parent.getFoldersByName(name);
  return it.hasNext() ? it.next() : parent.createFolder(name);
}

// ============================================================
//  СТАТИСТИКА
// ============================================================
function _emptyStats() {
  return { threads: 0, emails: 0, uploaded: 0, duplicates: 0, skipped: 0,
           images: 0, blocked: 0, errors: 0, fatalErrors: 0, excluded: 0,
           byType: { invoice: 0, price: 0, other: 0 } };
}

function _printStats(stats, durationMs) {
  var sep = '════════════════════════════════════════════════════════════';
  Logger.log(''); Logger.log(sep);
  Logger.log('  ЗАВЪРШЕНО за ' + (durationMs / 1000).toFixed(1) + 's'); Logger.log(sep);
  Logger.log('  Нишки:         ' + stats.threads);
  Logger.log('  Имейли:        ' + stats.emails);
  Logger.log('  Качени:        ' + stats.uploaded + '  (фактури: ' + stats.byType.invoice + ', цени: ' + stats.byType.price + ', др: ' + stats.byType.other + ')');
  Logger.log('  Дубликати:     ' + stats.duplicates);
  Logger.log('  Пропуснати:    ' + stats.skipped);
  Logger.log('  Изображения:   ' + stats.images);
  Logger.log('  Блокирани:     ' + stats.blocked);
  Logger.log('  Отфилтрирани:  ' + stats.excluded);
  Logger.log('  Грешки:        ' + (stats.errors + stats.fatalErrors));
  Logger.log(sep); Logger.log('');
}

function _logToSheet(action, details) {
  if (!CONFIG.LOG_TO_SHEET || !CONFIG.LOG_SHEET_ID) return;
  try {
    var sheet = SpreadsheetApp.openById(CONFIG.LOG_SHEET_ID).getActiveSheet();
    sheet.appendRow([new Date(), action, details, Session.getActiveUser().getEmail()]);
  } catch(err) { Logger.log('Логването в Sheet неуспешно: ' + err.message); }
}

// ============================================================
//  TRIGGERS — SELF-CHAINING (v4.5)
//
//  setupTrigger()         — настройва ежедневен trigger в 08:00
//  processEmailsAndUpload() — при нужда от продължение, сам
//                             планира нов trigger след 1 минута
// ============================================================
function setupTrigger() {
  _clearTriggers(['processUnreadEmails', 'processEmailsAndUpload', '_continueProcessing']);

  // Ежедневен trigger за нови имейли
  ScriptApp.newTrigger('processUnreadEmails')
    .timeBased().everyDays(1).atHour(8).inTimezone('Europe/Sofia').create();

  Logger.log('✅ Trigger настроен! processUnreadEmails() — всеки ден в 08:00 EET.');
}

function removeTrigger() {
  _clearTriggers(['processUnreadEmails', 'processEmailsAndUpload', '_continueProcessing']);
  Logger.log('Всички triggers изтрити.');
}

/** Изтрива triggers по списък с имена на функции */
function _clearTriggers(funcNames) {
  var triggers = ScriptApp.getProjectTriggers();
  for (var i = 0; i < triggers.length; i++) {
    if (funcNames.indexOf(triggers[i].getHandlerFunction()) !== -1) {
      ScriptApp.deleteTrigger(triggers[i]);
    }
  }
}

/**
 * Планира _continueProcessing() след 1 минута.
 * Извиква се автоматично когато _run() засече лимита.
 */
function _scheduleNextBatch() {
  // Изтрий стари continuation triggers
  _clearTriggers(['_continueProcessing']);

  var nextRun = new Date(Date.now() + 90 * 1000); // след 90 секунди
  ScriptApp.newTrigger('_continueProcessing')
    .timeBased().at(nextRun).create();

  Logger.log('⏭ Следващ batch планиран за: ' + nextRun.toLocaleTimeString('bg-BG'));
}

/**
 * Извиква се автоматично от trigger — продължава незавършената обработка.
 */
function _continueProcessing() {
  Logger.log('▶ Автоматично продължение...');
  processEmailsAndUpload();
}

// ============================================================
//  РЕСЕТ  (FIX v4.1: DRY_RUN по подразбиране)
// ============================================================
function resetAndReprocess() {
  var DRY_RUN = true; // ← СМЕНИ НА false ЗА РЕАЛНО ИЗТРИВАНЕ

  Logger.log(''); Logger.log('=== ' + (DRY_RUN ? '[DRY RUN] ' : '') + 'ПЪЛЕН РЕСЕТ ==='); Logger.log('');
  if (DRY_RUN) {
    Logger.log('⚠️  DRY RUN — нищо няма да бъде изтрито! Смени DRY_RUN = false за реално изтриване.');
    Logger.log('');
  }

  var processed = GmailApp.getUserLabelByName(CONFIG.PROCESSED_LABEL);
  if (processed) {
    var threads = processed.getThreads();
    Logger.log((DRY_RUN ? '[DRY] ' : '') + 'Нишки за нулиране: ' + threads.length);
    if (!DRY_RUN) {
      for (var i = 0; i < threads.length; i++) threads[i].removeLabel(processed);
      Logger.log('OK — лейбълът премахнат');
    }
  } else {
    Logger.log('Лейбълът "' + CONFIG.PROCESSED_LABEL + '" не съществува — OK');
  }

  try {
    var parent = DriveApp.getFolderById(CONFIG.PARENT_FOLDER_ID);
    var subs = parent.getFolders(); var count = 0;
    while (subs.hasNext()) {
      var f = subs.next();
      Logger.log((DRY_RUN ? '[DRY] Ще изтрие: ' : '  Изтрита: ') + f.getName());
      if (!DRY_RUN) f.setTrashed(true);
      count++;
    }
    Logger.log('OK — ' + (DRY_RUN ? 'Ще изтрие ' : 'Изтрити ') + count + ' папки');
  } catch(err) { Logger.log('Грешка при Drive: ' + err.message); }

  Logger.log('');
  if (DRY_RUN) Logger.log('Смени DRY_RUN = false и стартирай отново.');
  else Logger.log('Ресетът е завършен. Стартирай processEmailsAndUpload()');
  Logger.log('');
}

// ============================================================
//  ТЕСТ
// ============================================================
function testScript() {
  Logger.log(''); Logger.log('=== ТЕСТ РЕЖИМ (без качване) ==='); Logger.log('');
  var label = GmailApp.getUserLabelByName(CONFIG.SUPPLIER_LABEL);
  if (!label) { Logger.log('ГРЕШКА: Лейбълът "' + CONFIG.SUPPLIER_LABEL + '" не съществува!'); return; }
  var threads = label.getThreads(0, 5);
  if (threads.length === 0) { Logger.log('Няма имейли с лейбъла!'); return; }
  Logger.log('Преглед на първите ' + threads.length + ' нишки:'); Logger.log('');
  for (var i = 0; i < threads.length; i++) {
    var thread    = threads[i];
    var messages  = thread.getMessages();
    var excluded  = _shouldExclude(thread.getFirstMessageSubject());
    var processed = _threadHasLabel(thread, CONFIG.PROCESSED_LABEL);
    Logger.log((excluded ? '[SKIP] ' : processed ? '[DONE] ' : '[ OK ] ') + thread.getFirstMessageSubject());
    Logger.log('  Съобщения: ' + messages.length);
    for (var j = 0; j < messages.length; j++) {
      var message  = messages[j];
      var from     = message.getFrom();
      var subject  = message.getSubject();
      var body     = _safeGetBody(message);
      var htmlBody = _safeGetHtmlBody(message);
      var supplier = extractSupplierName(body, from, subject);
      var atts     = message.getAttachments();
      var sheets   = _extractSheetLinks(htmlBody || body);
      Logger.log('  [msg ' + (j+1) + '] От: ' + from);
      Logger.log('           Доставчик: ' + supplier);
      if (atts.length > 0) {
        Logger.log('           Файлове (' + atts.length + '):');
        for (var k = 0; k < atts.length; k++) {
          var att    = atts[k];
          var name   = att.getName();
          var mime   = att.getContentType();
          var type   = getFileType(name);
          var img    = _isImage(name, mime);
          var safe   = _isSafe(name, mime);
          var sizeMB = (att.getSize() / (1024 * 1024)).toFixed(2);
          var dest   = img ? 'изображение' : (!safe.ok ? 'БЛОКИРАН' : CONFIG.SUBFOLDERS[type]);
          var saved  = (!img && safe.ok) ? _buildFileName(name, message.getDate()) : name;
          Logger.log('             ' + name + '  →  ' + dest + '  →  ' + saved + '  (' + sizeMB + ' MB)');
        }
      }
      if (sheets.length > 0) {
        Logger.log('           Google Sheet линкове (' + sheets.length + '):');
        for (var s = 0; s < sheets.length; s++) Logger.log('             ' + sheets[s] + '  →  Цени');
      }
    }
    Logger.log('');
  }
}

// ============================================================
//  ДАШБОРД  (FIX v4.1: показва trigger статус)
// ============================================================
function showDashboard() {
  Logger.log(''); Logger.log('════════════════════════════════════════════════════════════');
  Logger.log('  ДАШБОРД'); Logger.log('════════════════════════════════════════════════════════════');
  var supplierLabel  = GmailApp.getUserLabelByName(CONFIG.SUPPLIER_LABEL);
  var processedLabel = GmailApp.getUserLabelByName(CONFIG.PROCESSED_LABEL);
  if (!supplierLabel) { Logger.log('ГРЕШКА: Лейбълът "Доставчик" не съществува!'); return; }
  var total = supplierLabel.getThreads().length;
  var done  = processedLabel ? processedLabel.getThreads().length : 0;
  Logger.log(''); Logger.log('  ИМЕЙЛИ');
  Logger.log('    Общо:       ' + total);
  Logger.log('    Обработени: ' + done);
  Logger.log('    Чакащи:     ' + (total - done));
  var triggers = ScriptApp.getProjectTriggers();
  Logger.log(''); Logger.log('  TRIGGERS (' + triggers.length + ')');
  for (var i = 0; i < triggers.length; i++) Logger.log('    ' + triggers[i].getHandlerFunction() + ' — ' + triggers[i].getEventType());
  if (triggers.length === 0) Logger.log('    ⚠️  Няма активни triggers! Стартирай setupTrigger().');
  try {
    var parent = DriveApp.getFolderById(CONFIG.PARENT_FOLDER_ID);
    var stats  = _driveStats(parent);
    Logger.log(''); Logger.log('  DRIVE');
    Logger.log('    Доставчици: ' + stats.suppliers);
    Logger.log('    Файлове:    ' + stats.files + '  (фактури: ' + stats.invoices + ', цени: ' + stats.prices + ', др: ' + stats.other + ')');
    Logger.log('    Размер:     ' + (stats.bytes / (1024 * 1024)).toFixed(1) + ' MB');
  } catch(err) { Logger.log('  Drive: ' + err.message); }
  Logger.log(''); Logger.log('════════════════════════════════════════════════════════════'); Logger.log('');
}

function _driveStats(parent) {
  var s = { suppliers: 0, files: 0, invoices: 0, prices: 0, other: 0, bytes: 0 };
  var supplierFolders = parent.getFolders();
  while (supplierFolders.hasNext()) {
    var sup = supplierFolders.next(); s.suppliers++;
    var subFolders = sup.getFolders();
    while (subFolders.hasNext()) {
      var sub = subFolders.next(); var files = sub.getFiles();
      while (files.hasNext()) {
        var f = files.next(); var ft = getFileType(f.getName());
        s.files++; s.bytes += f.getSize();
        if (ft === 'invoice') s.invoices++; else if (ft === 'price') s.prices++; else s.other++;
      }
    }
    var rootFiles = sup.getFiles();
    while (rootFiles.hasNext()) { var rf = rootFiles.next(); s.files++; s.bytes += rf.getSize(); }
  }
  return s;
}

// ============================================================
//  ПОЧИСТВАНЕ НА ПРАЗНИ ПАПКИ
// ============================================================
function cleanEmptyFolders() {
  Logger.log(''); Logger.log('=== ПОЧИСТВАНЕ НА ПРАЗНИ ПАПКИ ===');
  var removed = _cleanEmptyFolders();
  Logger.log('Изтрити ' + removed + ' празни папки.'); Logger.log('');
}

function _cleanEmptyFolders() {
  var parent = DriveApp.getFolderById(CONFIG.PARENT_FOLDER_ID); var removed = 0;
  var supplierIt = parent.getFolders();
  while (supplierIt.hasNext()) {
    var supplierFolder = supplierIt.next();
    var subIt = supplierFolder.getFolders();
    while (subIt.hasNext()) {
      var sub = subIt.next();
      if (!sub.getFiles().hasNext() && !sub.getFolders().hasNext()) {
        Logger.log('  Изтрита празна подпапка: ' + supplierFolder.getName() + '/' + sub.getName());
        sub.setTrashed(true); removed++;
      }
    }
    if (!supplierFolder.getFiles().hasNext() && !supplierFolder.getFolders().hasNext()) {
      Logger.log('  Изтрита празна папка: ' + supplierFolder.getName());
      supplierFolder.setTrashed(true); removed++;
    }
  }
  return removed;
}

// ============================================================
//  ДУБЛИКАТИ  (FIX v4.1: пази по-новия файл)
// ============================================================
function cleanupDuplicates() {
  Logger.log(''); Logger.log('=== ПОЧИСТВАНЕ НА ДУБЛИКАТИ (пази по-новия) ==='); Logger.log('');
  var parent = DriveApp.getFolderById(CONFIG.PARENT_FOLDER_ID);
  var total  = _dedup(parent);
  Logger.log(''); Logger.log('Изтрити ' + total + ' дубликата'); Logger.log('');
}

function _dedup(folder) {
  var total = 0;
  var subs  = folder.getFolders();
  while (subs.hasNext()) total += _dedup(subs.next());
  var fileMap = {}; var files = folder.getFiles();
  while (files.hasNext()) {
    var f = files.next(); var n = f.getName();
    if (!fileMap[n]) fileMap[n] = [];
    fileMap[n].push(f);
  }
  var names = Object.keys(fileMap);
  for (var i = 0; i < names.length; i++) {
    var group = fileMap[names[i]];
    if (group.length <= 1) continue;
    // Сортирай: по-нов първи
    group.sort(function(a, b) { return b.getDateCreated().getTime() - a.getDateCreated().getTime(); });
    for (var j = 1; j < group.length; j++) {
      Logger.log('  Изтрит по-стар дубликат: ' + group[j].getName() + ' (в "' + folder.getName() + '")');
      group[j].setTrashed(true); total++;
    }
  }
  return total;
}

// ============================================================
//  АРХИВИРАНЕ  (FIX v4.1: getDateCreated())
// ============================================================
function archiveOldFiles() {
  var DAYS = 180;
  Logger.log(''); Logger.log('=== АРХИВИРАНЕ (>' + DAYS + ' дни от създаване) ==='); Logger.log('');
  var parent    = DriveApp.getFolderById(CONFIG.PARENT_FOLDER_ID);
  var yearName  = 'ARCHIVE_' + new Date().getFullYear();
  var archiveIt = parent.getFoldersByName(yearName);
  var archive   = archiveIt.hasNext() ? archiveIt.next() : parent.createFolder(yearName);
  var threshold = Date.now() - DAYS * 86400000;
  var count     = 0;
  function walk(folder) {
    var files = folder.getFiles();
    while (files.hasNext()) {
      var f = files.next();
      if (f.getDateCreated().getTime() < threshold) {
        archive.addFile(f); folder.removeFile(f);
        Logger.log('  Архивиран: ' + f.getName() + ' (' + f.getDateCreated().toLocaleDateString('bg-BG') + ')');
        count++;
      }
    }
    var subs = folder.getFolders();
    while (subs.hasNext()) walk(subs.next());
  }
  walk(parent);
  Logger.log(''); Logger.log('Архивирани ' + count + ' файла в ' + yearName); Logger.log('');
}

// ============================================================
//  СИНХРОНИЗИРАНЕ В SHEET  (FIX v4.1: getDateCreated + freeze)
// ============================================================
function syncToSheet() {
  Logger.log(''); Logger.log('=== СИНХРОНИЗИРАНЕ В GOOGLE SHEET ==='); Logger.log('');
  var parent = DriveApp.getFolderById(CONFIG.PARENT_FOLDER_ID);
  var rows   = [['Доставчик', 'Подпапка', 'Файл', 'Тип', 'Размер (MB)', 'Дата създаден']];
  function walk(folder, supplier, subfolder) {
    var subs = folder.getFolders();
    while (subs.hasNext()) { var sub = subs.next(); walk(sub, supplier || sub.getName(), sub.getName()); }
    var files = folder.getFiles();
    while (files.hasNext()) {
      var f = files.next();
      rows.push([supplier || folder.getName(), subfolder || '—', f.getName(), getFileType(f.getName()),
        (f.getSize() / (1024 * 1024)).toFixed(2), f.getDateCreated().toLocaleDateString('bg-BG')]);
    }
  }
  walk(parent, null, null);
  var ss;
  try { var existing = DriveApp.getFilesByName('Supplier Sync'); ss = existing.hasNext() ? SpreadsheetApp.open(existing.next()) : SpreadsheetApp.create('Supplier Sync'); }
  catch(e) { ss = SpreadsheetApp.create('Supplier Sync'); }
  var sheet = ss.getActiveSheet();
  sheet.clearContents();
  sheet.getRange(1, 1, rows.length, rows[0].length).setValues(rows);
  sheet.autoResizeColumns(1, rows[0].length);
  sheet.setFrozenRows(1);
  Logger.log('Синхронизирани ' + (rows.length - 1) + ' записа');
  Logger.log('Sheet: ' + ss.getUrl()); Logger.log('');
}

// ============================================================
//  ПОКАЗВА НЕПОЗНАТИ ИЗПРАЩАЧИ  (v4.4)
//  Стартирай след processEmailsAndUpload() за да видиш кои
//  адреси трябва да добавиш в MANUAL_MAPPING
// ============================================================
function showUnknownSenders() {
  Logger.log('');
  Logger.log('=== НЕПОЗНАТИ ИЗПРАЩАЧИ (не са в MANUAL_MAPPING) ===');
  Logger.log('');

  var startDate = _getStartDate();
  var d = startDate;
  var afterStr = 'after:' + d.getFullYear() + '/' + ('0'+(d.getMonth()+1)).slice(-2) + '/' + ('0'+d.getDate()).slice(-2);
  var query = 'in:inbox label:"' + CONFIG.SUPPLIER_LABEL + '" ' + afterStr;
  var threads = GmailApp.search(query);

  var unknown = {};

  for (var i = 0; i < threads.length; i++) {
    var msgs = threads[i].getMessages();
    for (var j = 0; j < msgs.length; j++) {
      var from = msgs[j].getFrom();
      if (_isBlockedSender(from)) continue;
      var supplier = _fromManualMap(from);
      if (!supplier) {
        var domain = _parseDomain(from);
        if (!unknown[domain]) unknown[domain] = { from: from, count: 0 };
        unknown[domain].count++;
      }
    }
  }

  var keys = Object.keys(unknown);
  if (keys.length === 0) {
    Logger.log('✅ Всички имейли са разпознати! Няма непознати изпращачи.');
  } else {
    Logger.log('Намерени ' + keys.length + ' непознати домейна:');
    Logger.log('');
    Logger.log('Добави в MANUAL_MAPPING:');
    for (var k = 0; k < keys.length; k++) {
      var info = unknown[keys[k]];
      Logger.log("  '" + keys[k] + "': 'ИМЕ НА ДОСТАВЧИК',  // " + info.count + ' имейл(а) от ' + info.from);
    }
  }
  Logger.log('');
}

// ============================================================
//  МЕНЮ
// ============================================================
function showMenu() {
  Logger.log(''); Logger.log('════════════════════════════════════════════════════════════');
  Logger.log('  GMAIL → DRIVE  v4.2  |  ФУНКЦИИ');
  Logger.log('════════════════════════════════════════════════════════════'); Logger.log('');
  Logger.log('  СТАРТИРАЙ В ТОЗИ РЕД:');
  Logger.log('    1. testScript()              — провери без качване');
  Logger.log('    2. resetAndReprocess()        — нулирай (dry_run=true по подразбиране!)');
  Logger.log('    3. processEmailsAndUpload()   — обработи всички');
  Logger.log('    4. setupTrigger()             — настрой автоматизация 08:00 EET'); Logger.log('');
  Logger.log('  ЕЖЕДНЕВЕН ТРИГЕР:');
  Logger.log('    processUnreadEmails()         — само нови имейли'); Logger.log('');
  Logger.log('  УПРАВЛЕНИЕ:');
  Logger.log('    showDashboard()               — статус (с trigger info)');
  Logger.log('    cleanEmptyFolders()           — изтрий празни папки');
  Logger.log('    cleanupDuplicates()           — изтрий по-стари дубликати');
  Logger.log('    archiveOldFiles()             — архивирай >180 дни');
  Logger.log('    syncToSheet()                 — експортирай в Sheet');
  Logger.log('    removeTrigger()               — спри автоматизацията');
  Logger.log('    showUnknownSenders()          — виж непознати изпращачи за MANUAL_MAPPING');
  Logger.log(''); Logger.log('════════════════════════════════════════════════════════════'); Logger.log('');
}
