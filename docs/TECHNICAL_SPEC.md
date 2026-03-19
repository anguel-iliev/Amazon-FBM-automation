# Техническа Спецификация — Amazon FBM Automation Platform

**Версия:** 1.0.0  
**Дата:** 2025-01-01  
**Статус:** Draft  

---

## 1. Обзор на системата

### 1.1 Цел
Автоматизирана платформа за:
- Организиране на документи от доставчици (имейли, ценови листи, фактури)
- Синхронизация на продуктови данни в централна таблица
- Автоматично ценообразуване по пазари
- Качване на продукти в Amazon Seller Central

### 1.2 Архитектурна диаграма

```
┌─────────────────────────────────────────────────────────────┐
│                        GMAIL                                 │
│              (Имейли от доставчици с лейбъл)                 │
└──────────────────────────┬──────────────────────────────────┘
                           │
                    [Фаза 1: Apps Script]
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                     GOOGLE DRIVE                             │
│  Цени доставчици/                                            │
│  ├── Доставчик А/                                            │
│  │   ├── Ценови листи/  (Excel/PDF)                          │
│  │   └── Фактури/       (PDF)                                │
│  └── Доставчик Б/                                            │
└──────────────────────────┬──────────────────────────────────┘
                           │
                 [Фаза 2: Python + Sheets API]
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                  ЦЕНТРАЛНА ТАБЛИЦА (Google Sheets)           │
│  EAN | Our SKU | Source | Продукт | ASIN | DE_цена | ...     │
└──────────────────────────┬──────────────────────────────────┘
                           │
                    [Фаза 3: Agent]
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                   AMAZON SELLER CENTRAL                      │
│              Upload Spreadsheet → FBM Listings               │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Фаза 1 — Организация на документи

### 2.1 Описание
Google Apps Script, изпълняван ежедневно чрез time-based trigger. Сканира Gmail за нови имейли с лейбъл `доставчици`, организира прикачените файлове в Google Drive.

### 2.2 Логика

```
За всеки имейл с лейбъл "доставчици" и статус UNREAD:
  1. Извличане на данни:
     - Подател → Имe на доставчик (от адресна книга или subject)
     - Прикачени файлове → .xlsx, .xls, .pdf
  
  2. Структура в Drive:
     /Цени доставчици/
       └── [Доставчик]/
             ├── Ценови листи/   ← Excel файлове
             └── Фактури/        ← PDF файлове
  
  3. Проверка:
     - Папката съществува? → Само добавя нови файлове
     - Папката НЕ съществува? → Създава структурата
  
  4. Маркира имейла като READ (обработен)
  5. Логва операцията в лог-таблица
```

### 2.3 Конфигурация (config обект в скрипта)

```javascript
const CONFIG = {
  GMAIL_LABEL: 'доставчици',
  DRIVE_ROOT_FOLDER: 'Цени доставчици',
  PRICE_LIST_EXTENSIONS: ['.xlsx', '.xls', '.csv'],
  INVOICE_EXTENSIONS: ['.pdf'],
  LOG_SHEET_ID: 'YOUR_SHEET_ID', // Google Sheet за логове
  RUN_INTERVAL_HOURS: 24
};
```

### 2.4 Triggers
- Тип: Time-based trigger
- Честота: Всеки ден в 08:00
- Функция: `processSupplierEmails()`

### 2.5 Изходни данни
- Структуриран Google Drive с папки за всеки доставчик
- Google Sheet с лог: Дата, Доставчик, Файлове, Статус

---

## 3. Фаза 2 — Обработка на продукти и ценообразуване

### 3.1 Технологии
- **Език:** Python 3.10+
- **Хостинг:** Superhosting (Apache + CGI или cron job)
- **APIs:** Google Sheets API v4, Google Drive API v3
- **Библиотеки:** `gspread`, `openpyxl`, `pandas`, `pdfplumber`

### 3.2 Модул 2A — Сравнение и актуализация

#### Входни данни:
- Excel/PDF файлове от Google Drive (папки на доставчиците)

#### Алгоритъм:

```python
for supplier_folder in drive.get_supplier_folders():
    for price_file in supplier_folder.get_new_files():
        products = parse_price_file(price_file)
        for product in products:
            existing = central_table.find_by_ean_or_sku(product.ean)
            if existing:
                update_product(existing, product)  # с цветова индикация
            else:
                add_new_product(product)
```

#### Цветова индикация при актуализация:
| Промяна | Цвят | Hex |
|---------|------|-----|
| Цената се покачва | Червено | `#FF4444` |
| Цената пада | Зелено | `#44BB44` |
| Без промяна | Бяло | `#FFFFFF` |

#### Записвани данни при промяна:
- Нова цена
- Дата и час на промяна (timestamp)
- Предишна цена (за история)

### 3.3 Модул 2B — Изчисление на крайна цена

#### Формула:
```
Крайна цена = (Доставчик цена + Доставка) × (1 + Amazon комисионна) × (1 + VAT) + Фиксирани такси
```

#### Конфигурационна таблица по държави:
| Държава | VAT | Amazon % | Доставка (€) | FBM такса (€) |
|---------|-----|----------|--------------|----------------|
| DE      | 19% | 15%      | 4.50         | 1.00           |
| FR      | 20% | 15%      | 5.00         | 1.00           |
| IT      | 22% | 15%      | 5.50         | 1.00           |
| ES      | 21% | 15%      | 5.50         | 1.00           |
| NL      | 21% | 15%      | 4.50         | 1.00           |
| PL      | 23% | 15%      | 6.00         | 1.00           |
| SE      | 25% | 15%      | 7.00         | 1.00           |

> ⚠️ **Важно:** Таксите трябва да бъдат финализирани от бизнес екипа преди имплементация на Модул 2B.

---

## 4. Централна таблица — Схема

Виж: `CENTRAL_TABLE_SCHEMA.md`

---

## 5. Фаза 3 — Качване в Amazon

### 5.1 Препоръчан подход: Amazon SP-API (НЕ browser automation)

> ⚠️ **Важна забележка:** Selenium/Puppeteer за Seller Central е нарушение на ToS на Amazon и може да доведе до суспендиране на акаунта. Препоръчваме SP-API.

#### Amazon SP-API Endpoints:
| Операция | API | Endpoint |
|----------|-----|---------|
| Търсене по EAN | Catalog Items API | `GET /catalog/2022-04-01/items` |
| Качване на листинги | Listings Items API | `PUT /listings/2021-08-01/items/{sellerId}/{sku}` |
| Bulk upload | Feeds API | `POST /feeds/2021-06-30/feeds` |

### 5.2 Алгоритъм на агента

```python
# Стъпка 1: Взима немаркирани продукти
products = central_table.get_unmarked_products()

# Стъпка 2: За всеки продукт — търсене в Amazon по EAN
for product in products:
    for marketplace in ['DE', 'FR', 'IT', 'ES']:
        result = sp_api.catalog.search_by_ean(product.ean, marketplace)
        if result.found:
            product.asin[marketplace] = result.asin

# Стъпка 3: Генериране на upload файл
upload_df = generate_amazon_template(products)

# Стъпка 4: Запазване в Drive
filename = f"{marketplace}_{date.today()}.xlsx"
drive.save(upload_df, f"Продукти за качване/{filename}")

# Стъпка 5: Маркиране като "качен"
central_table.mark_as_uploaded(products)
```

### 5.3 Изходни файлове
- Формат: `[Държава]_[YYYY-MM-DD].xlsx`
- Местоположение: Google Drive → `Продукти за качване/`

---

## 6. Сигурност и достъп

### 6.1 Google APIs
- Service Account с ограничени права (само четене/писане в конкретни Drive папки)
- OAuth2 за Apps Script (User OAuth)
- Credentials файл: `credentials.json` (НЕ се commit-ва в git!)

### 6.2 Amazon SP-API
- LWA (Login with Amazon) credentials
- IAM Role за SP-API достъп
- Всички secrets в `.env` файл (НЕ се commit-ва в git!)

### 6.3 .gitignore задължително съдържа:
```
credentials.json
.env
*.key
service_account.json
```

---

## 7. Следващи стъпки

### Приоритет 1 (Тази седмица):
- [ ] Финализиране на схемата на централната таблица
- [ ] Създаване на Google Sheet за централна таблица
- [ ] Тест на Фаза 1 Apps Script с реален имейл

### Приоритет 2 (Следваща седмица):
- [ ] Документиране на правилата за ценообразуване
- [ ] Setup на Google Cloud Project + Service Account
- [ ] Тест на Модул 2A с реален Excel файл от доставчик

### Приоритет 3 (Следващ месец):
- [ ] Amazon SP-API регистрация и тест в Sandbox
- [ ] Имплементация на Фаза 3
- [ ] End-to-end тест на целия pipeline

---

## 8. Рискове и ограничения

| Риск | Вероятност | Влияние | Митигация |
|------|-----------|---------|-----------|
| Amazon SP-API rate limits | Средна | Висока | Retry logic + exponential backoff |
| Различен формат на Excel файловете от доставчици | Висока | Средна | Конфигурируем column mapping per supplier |
| EAN не намерен в Amazon | Средна | Ниска | Маркиране за ръчна обработка |
| Google API quota exceeded | Ниска | Средна | Batch операции + caching |
