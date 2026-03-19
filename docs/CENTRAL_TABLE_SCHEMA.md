# Схема на Централната Таблица

**Google Sheet Name:** `Amazon FBM — Централна таблица`  
**Tabs (Sheets):**
1. `Products` — основна таблица с продукти
2. `Price History` — история на ценовите промени
3. `Config` — конфигурация на такси и правила
4. `Log` — лог на операциите

---

## Tab 1: Products

| # | Колона | Тип | Описание | Пример |
|---|--------|-----|----------|--------|
| A | `EAN` | String | Баркод EAN-13 | `4006381333931` |
| B | `Our_SKU` | String | Вътрешен SKU | `SKU-001234` |
| C | `Source` | String | Доставчик (Folder name в Drive) | `Доставчик А` |
| D | `Product_Name` | String | Пълно наименование | `Bosch GSR 12V-35 FC` |
| E | `Brand` | String | Марка | `Bosch` |
| F | `Category` | String | Amazon категория | `Power & Hand Tools` |
| G | `Supplier_Price` | Float | Цена от доставчика (€, без ДДС) | `45.00` |
| H | `Price_Updated_At` | DateTime | Последна промяна на цената | `2025-01-15 09:30:00` |
| I | `Price_Change_Flag` | String | UP / DOWN / SAME | `DOWN` |
| J | `ASIN_DE` | String | ASIN за Германия | `B08X1234YZ` |
| K | `ASIN_FR` | String | ASIN за Франция | `B08X1234YZ` |
| L | `ASIN_IT` | String | ASIN за Италия | |
| M | `ASIN_ES` | String | ASIN за Испания | |
| N | `ASIN_NL` | String | ASIN за Нидерландия | |
| O | `ASIN_PL` | String | ASIN за Полша | |
| P | `ASIN_SE` | String | ASIN за Швеция | |
| Q | `Final_Price_DE` | Float | Крайна цена DE (€, с такси) | `62.99` |
| R | `Final_Price_FR` | Float | Крайна цена FR (€, с такси) | `65.99` |
| S | `Final_Price_IT` | Float | Крайна цена IT (€, с такси) | `67.99` |
| T | `Final_Price_ES` | Float | Крайна цена ES (€, с такси) | `67.99` |
| U | `Final_Price_NL` | Float | Крайна цена NL (€, с такси) | `62.99` |
| V | `Final_Price_PL` | Float | Крайна цена PL (€, с такси) | `58.99` |
| W | `Final_Price_SE` | Float | Крайна цена SE (SEK, с такси) | `699.00` |
| X | `Amazon_Link_DE` | URL | Линк към Amazon DE листинг | `https://amazon.de/dp/...` |
| Y | `Upload_Status` | String | NOT_UPLOADED / UPLOADED / PARTIAL | `NOT_UPLOADED` |
| Z | `Uploaded_At` | DateTime | Дата на качване | |
| AA | `Notes` | String | Бележки за ръчна обработка | |

### Цветова индикация (conditional formatting):
- Колона `Supplier_Price` + `Price_Change_Flag = UP` → **червен фон** `#FFCCCC`
- Колона `Supplier_Price` + `Price_Change_Flag = DOWN` → **зелен фон** `#CCFFCC`
- Колона `Upload_Status = NOT_UPLOADED` → **жълт фон** `#FFFF99`
- Колона `Upload_Status = UPLOADED` → **зелен фон** `#CCFFCC`

---

## Tab 2: Price History

| Колона | Тип | Описание |
|--------|-----|----------|
| `Timestamp` | DateTime | Дата и час на промяната |
| `EAN` | String | EAN на продукта |
| `Our_SKU` | String | SKU на продукта |
| `Product_Name` | String | Наименование |
| `Old_Price` | Float | Предишна цена |
| `New_Price` | Float | Нова цена |
| `Change_Pct` | Float | % промяна |
| `Source` | String | Доставчик |
| `Source_File` | String | Файл, от който е взета цената |

---

## Tab 3: Config

### Такси по държави:

| `Country` | `VAT_Pct` | `Amazon_Fee_Pct` | `Shipping_Cost_EUR` | `FBM_Fee_EUR` | `Min_Margin_Pct` |
|-----------|-----------|------------------|---------------------|---------------|------------------|
| DE | 0.19 | 0.15 | 4.50 | 1.00 | 0.15 |
| FR | 0.20 | 0.15 | 5.00 | 1.00 | 0.15 |
| IT | 0.22 | 0.15 | 5.50 | 1.00 | 0.15 |
| ES | 0.21 | 0.15 | 5.50 | 1.00 | 0.15 |
| NL | 0.21 | 0.15 | 4.50 | 1.00 | 0.15 |
| PL | 0.23 | 0.15 | 6.00 | 1.00 | 0.15 |
| SE | 0.25 | 0.15 | 7.00 | 1.00 | 0.15 |

> ⚠️ **Тези стойности са примерни! Трябва да бъдат потвърдени от бизнес екипа.**

### Ценова формула:
```
Final_Price = (Supplier_Price + Shipping_Cost) ÷ (1 - Amazon_Fee_Pct) × (1 + VAT_Pct) + FBM_Fee
```

---

## Tab 4: Log

| Колона | Тип | Описание |
|--------|-----|----------|
| `Timestamp` | DateTime | Дата и час |
| `Module` | String | Phase1 / Phase2A / Phase2B / Phase3 |
| `Operation` | String | CREATE / UPDATE / UPLOAD / ERROR |
| `Details` | String | Описание на операцията |
| `Affected_Rows` | Integer | Брой засегнати редове |
| `Status` | String | SUCCESS / FAILED |
