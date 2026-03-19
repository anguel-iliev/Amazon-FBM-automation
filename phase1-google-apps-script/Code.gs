/**
 * Amazon FBM Automation — Фаза 1
 * Организация на документи от доставчици
 * 
 * Инсталация:
 * 1. Отвори script.google.com
 * 2. Създай нов проект "Amazon FBM - Phase 1"
 * 3. Постави целия код тук
 * 4. Попълни CONFIG обекта с твоите стойности
 * 5. Изпълни setupTrigger() ВЕДНЪЖ за да настроиш автоматичното изпълнение
 * 6. Дай необходимите permissions при поискване
 */

// ============================================================
// КОНФИГУРАЦИЯ — попълни преди първо стартиране
// ============================================================
const CONFIG = {
  // Gmail лейбъл за имейли от доставчици
  GMAIL_LABEL: 'доставчици',

  // Името на root папката в Google Drive
  DRIVE_ROOT_FOLDER: 'Цени доставчици',

  // Google Sheet ID за логове (от URL-а на Sheet-а)
  // Пример: https://docs.google.com/spreadsheets/d/SHEET_ID_HERE/edit
  LOG_SHEET_ID: 'ПОПЪЛНИ_SHEET_ID_ТУК',

  // Разширения за ценови листи
  PRICE_LIST_EXTENSIONS: ['.xlsx', '.xls', '.csv'],

  // Разширения за фактури
  INVOICE_EXTENSIONS: ['.pdf'],

  // Подпапки в папката на всеки доставчик
  PRICE_SUBFOLDER: 'Ценови листи',
  INVOICE_SUBFOLDER: 'Фактури',

  // Брой дни назад за сканиране при първо изпълнение
  INITIAL_SCAN_DAYS: 30
};

// ============================================================
// ГЛАВНА ФУНКЦИЯ — изпълнява се от trigger
// ============================================================
function processSupplierEmails() {
  const startTime = new Date();
  Logger.log('=== Стартиране на обработка: ' + startTime.toISOString() + ' ===');

  try {
    // Вземи или създай root папката в Drive
    const rootFolder = getOrCreateFolder(CONFIG.DRIVE_ROOT_FOLDER, DriveApp.getRootFolder());

    // Намери Gmail лейбъла
    const label = GmailApp.getUserLabelByName(CONFIG.GMAIL_LABEL);
    if (!label) {
      throw new Error('Gmail лейбъл "' + CONFIG.GMAIL_LABEL + '" не е намерен! Моля, създай го в Gmail.');
    }

    // Вземи непрочетените теми (threads)
    const threads = label.getThreads();
    Logger.log('Намерени теми: ' + threads.length);

    let processedCount = 0;
    let errorCount = 0;

    for (const thread of threads) {
      const messages = thread.getMessages();

      for (const message of messages) {
        // Пропусни вече прочетените съобщения
        if (!message.isUnread()) continue;

        try {
          processMessage(message, rootFolder);
          message.markRead();
          processedCount++;
        } catch (msgError) {
          Logger.log('ГРЕШКА при обработка на съобщение: ' + msgError.message);
          errorCount++;
          logToSheet('Phase1', 'ERROR', 'Грешка при ' + message.getSubject() + ': ' + msgError.message, 0, 'FAILED');
        }
      }
    }

    const summary = `Обработени: ${processedCount}, Грешки: ${errorCount}`;
    Logger.log('=== Завършено: ' + summary + ' ===');
    logToSheet('Phase1', 'RUN_COMPLETE', summary, processedCount, 'SUCCESS');

  } catch (error) {
    Logger.log('КРИТИЧНА ГРЕШКА: ' + error.message);
    logToSheet('Phase1', 'CRITICAL_ERROR', error.message, 0, 'FAILED');
  }
}

// ============================================================
// ОБРАБОТКА НА ЕДНО СЪОБЩЕНИЕ
// ============================================================
function processMessage(message, rootFolder) {
  const subject = message.getSubject();
  const sender = message.getFrom();
  const date = message.getDate();

  Logger.log('Обработвам: "' + subject + '" от ' + sender);

  // Извличане на прикачени файлове
  const attachments = message.getAttachments();
  if (attachments.length === 0) {
    Logger.log('Няма прикачени файлове — пропускам.');
    return;
  }

  // Извличане на името на доставчика от подателя
  const supplierName = extractSupplierName(sender, subject);
  Logger.log('Доставчик: ' + supplierName);

  // Създаване на структурата в Drive
  const supplierFolder = getOrCreateFolder(supplierName, rootFolder);
  const priceFolder = getOrCreateFolder(CONFIG.PRICE_SUBFOLDER, supplierFolder);
  const invoiceFolder = getOrCreateFolder(CONFIG.INVOICE_SUBFOLDER, supplierFolder);

  let savedFiles = 0;

  for (const attachment of attachments) {
    const fileName = attachment.getName().toLowerCase();
    const originalName = attachment.getName();

    // Добавяме timestamp към името на файла за уникалност
    const timestamp = Utilities.formatDate(date, 'Europe/Sofia', 'yyyy-MM-dd_HHmm');
    const newFileName = timestamp + '_' + originalName;

    let targetFolder = null;

    if (CONFIG.PRICE_LIST_EXTENSIONS.some(ext => fileName.endsWith(ext))) {
      targetFolder = priceFolder;
      Logger.log('  → Ценова листа: ' + originalName);
    } else if (CONFIG.INVOICE_EXTENSIONS.some(ext => fileName.endsWith(ext))) {
      targetFolder = invoiceFolder;
      Logger.log('  → Фактура: ' + originalName);
    } else {
      Logger.log('  → Пропускам (неизвестен тип): ' + originalName);
      continue;
    }

    // Проверка за дублиране — ако файлът вече съществува, пропускаме
    if (fileExistsInFolder(newFileName, targetFolder)) {
      Logger.log('  → Вече съществува, пропускам: ' + newFileName);
      continue;
    }

    // Запазване на файла
    targetFolder.createFile(attachment.copyBlob().setName(newFileName));
    savedFiles++;
    Logger.log('  → Запазен: ' + newFileName);
  }

  logToSheet('Phase1', 'PROCESS_EMAIL',
    `Доставчик: ${supplierName} | Тема: ${subject} | Файлове: ${savedFiles}`,
    savedFiles, 'SUCCESS');
}

// ============================================================
// ПОМОЩНИ ФУНКЦИИ
// ============================================================

/**
 * Извлича чисто наименование на доставчика от имейл адреса или темата
 */
function extractSupplierName(sender, subject) {
  // Опит 1: Вземи display name от "Иван Иванов <ivan@company.com>"
  const nameMatch = sender.match(/^([^<]+)</);
  if (nameMatch) {
    return nameMatch[1].trim().replace(/[\/\\:*?"<>|]/g, '_');
  }

  // Опит 2: Вземи домейна от имейл адреса
  const emailMatch = sender.match(/<(.+)>/);
  if (emailMatch) {
    const domain = emailMatch[1].split('@')[1];
    return domain.split('.')[0]; // company от company.com
  }

  // Fallback: Използвай темата, изчистена
  return subject.replace(/[\/\\:*?"<>|]/g, '_').substring(0, 50);
}

/**
 * Вземи съществуваща папка или създай нова
 */
function getOrCreateFolder(name, parentFolder) {
  const folders = parentFolder.getFoldersByName(name);
  if (folders.hasNext()) {
    return folders.next();
  }
  Logger.log('Създавам папка: ' + name);
  return parentFolder.createFolder(name);
}

/**
 * Проверява дали файл с дадено ime вече съществува в папката
 */
function fileExistsInFolder(fileName, folder) {
  const files = folder.getFilesByName(fileName);
  return files.hasNext();
}

/**
 * Записва лог в Google Sheet
 */
function logToSheet(module, operation, details, affectedRows, status) {
  try {
    if (!CONFIG.LOG_SHEET_ID || CONFIG.LOG_SHEET_ID === 'ПОПЪЛНИ_SHEET_ID_ТУК') {
      Logger.log('[LOG] ' + module + ' | ' + operation + ' | ' + details);
      return;
    }

    const ss = SpreadsheetApp.openById(CONFIG.LOG_SHEET_ID);
    let sheet = ss.getSheetByName('Log');

    if (!sheet) {
      sheet = ss.insertSheet('Log');
      // Хедъри
      sheet.getRange(1, 1, 1, 6).setValues([
        ['Timestamp', 'Module', 'Operation', 'Details', 'Affected Rows', 'Status']
      ]);
      sheet.getRange(1, 1, 1, 6).setFontWeight('bold');
    }

    sheet.appendRow([
      new Date().toISOString(),
      module,
      operation,
      details,
      affectedRows,
      status
    ]);
  } catch (e) {
    Logger.log('Грешка при логване: ' + e.message);
  }
}

// ============================================================
// SETUP — изпълни само ВЕДНЪЖ при инсталация
// ============================================================

/**
 * Настройва time-based trigger за ежедневно изпълнение
 * Изпълни тази функция ВЕДНЪЖ ръчно!
 */
function setupTrigger() {
  // Изтрий стари triggers ако има
  const triggers = ScriptApp.getProjectTriggers();
  for (const trigger of triggers) {
    if (trigger.getHandlerFunction() === 'processSupplierEmails') {
      ScriptApp.deleteTrigger(trigger);
    }
  }

  // Създай нов trigger — всеки ден в 08:00
  ScriptApp.newTrigger('processSupplierEmails')
    .timeBased()
    .everyDays(1)
    .atHour(8)
    .create();

  Logger.log('✅ Trigger настроен! Скриптът ще се изпълнява всеки ден в 08:00.');
  Logger.log('Моля, провери в: Extensions → Apps Script → Triggers');
}

/**
 * Тестова функция — изпълни ръчно за да провериш работата
 */
function testRun() {
  Logger.log('=== ТЕСТОВО ИЗПЪЛНЕНИЕ ===');
  Logger.log('CONFIG: ' + JSON.stringify(CONFIG));

  // Проверка на Drive
  try {
    const rootFolder = getOrCreateFolder(CONFIG.DRIVE_ROOT_FOLDER, DriveApp.getRootFolder());
    Logger.log('✅ Drive папка намерена/създадена: ' + rootFolder.getName());
  } catch (e) {
    Logger.log('❌ Drive грешка: ' + e.message);
  }

  // Проверка на Gmail лейбъл
  try {
    const label = GmailApp.getUserLabelByName(CONFIG.GMAIL_LABEL);
    if (label) {
      const threads = label.getThreads();
      Logger.log('✅ Gmail лейбъл намерен. Теми: ' + threads.length);
    } else {
      Logger.log('❌ Gmail лейбъл "' + CONFIG.GMAIL_LABEL + '" НЕ е намерен!');
    }
  } catch (e) {
    Logger.log('❌ Gmail грешка: ' + e.message);
  }

  Logger.log('=== Края на теста ===');
}
