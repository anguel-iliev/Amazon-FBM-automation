"""
Amazon FBM Automation — Фаза 2A
Модул: Сравнение и актуализация на продукти

Изисквания:
    pip install gspread google-auth openpyxl pandas pdfplumber python-dotenv

Конфигурация:
    Копирай .env.example в .env и попълни стойностите
"""

import os
import json
import logging
from datetime import datetime
from pathlib import Path
from typing import Optional

import pandas as pd
import gspread
from google.oauth2.service_account import Credentials
from dotenv import load_dotenv

# ============================================================
# КОНФИГУРАЦИЯ
# ============================================================
load_dotenv()

SCOPES = [
    'https://www.googleapis.com/auth/spreadsheets',
    'https://www.googleapis.com/auth/drive.readonly'
]

CENTRAL_SHEET_ID = os.getenv('CENTRAL_SHEET_ID')
GOOGLE_CREDENTIALS_FILE = os.getenv('GOOGLE_CREDENTIALS_FILE', 'credentials.json')
DRIVE_ROOT_FOLDER_ID = os.getenv('DRIVE_ROOT_FOLDER_ID')

# Настройка на логове
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler('logs/phase2a.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)


# ============================================================
# КЛАС ЗА ЦЕНТРАЛНАТА ТАБЛИЦА
# ============================================================
class CentralTable:
    """Управление на централната Google Sheet таблица"""

    # Колони (0-indexed)
    COL = {
        'EAN': 0, 'Our_SKU': 1, 'Source': 2, 'Product_Name': 3,
        'Brand': 4, 'Category': 5, 'Supplier_Price': 6,
        'Price_Updated_At': 7, 'Price_Change_Flag': 8,
        'ASIN_DE': 9, 'ASIN_FR': 10, 'ASIN_IT': 11,
        'ASIN_ES': 12, 'ASIN_NL': 13, 'ASIN_PL': 14, 'ASIN_SE': 15,
        'Final_Price_DE': 16, 'Final_Price_FR': 17, 'Final_Price_IT': 18,
        'Final_Price_ES': 19, 'Final_Price_NL': 20, 'Final_Price_PL': 21,
        'Final_Price_SE': 22, 'Amazon_Link_DE': 23,
        'Upload_Status': 24, 'Uploaded_At': 25, 'Notes': 26
    }

    # Цветове за индикация
    COLOR_PRICE_UP = {'red': 1.0, 'green': 0.8, 'blue': 0.8}    # Светло червено
    COLOR_PRICE_DOWN = {'red': 0.8, 'green': 1.0, 'blue': 0.8}  # Светло зелено
    COLOR_NO_CHANGE = {'red': 1.0, 'green': 1.0, 'blue': 1.0}   # Бяло

    def __init__(self, gc: gspread.Client):
        self.gc = gc
        self.spreadsheet = gc.open_by_key(CENTRAL_SHEET_ID)
        self.sheet = self.spreadsheet.worksheet('Products')
        self._cache = None
        logger.info(f"Свързан с централна таблица: {self.spreadsheet.title}")

    def _load_cache(self):
        """Зарежда всички редове в памет за бързо търсене"""
        all_data = self.sheet.get_all_values()
        self._cache = {
            'headers': all_data[0] if all_data else [],
            'rows': all_data[1:] if len(all_data) > 1 else [],
            'ean_index': {},
            'sku_index': {}
        }
        # Индексиране по EAN и SKU
        for i, row in enumerate(self._cache['rows']):
            ean = row[self.COL['EAN']].strip() if row[self.COL['EAN']] else ''
            sku = row[self.COL['Our_SKU']].strip() if row[self.COL['Our_SKU']] else ''
            if ean:
                self._cache['ean_index'][ean] = i + 2  # +2 за header + 1-indexed
            if sku:
                self._cache['sku_index'][sku] = i + 2
        logger.info(f"Заредени {len(self._cache['rows'])} продукта в кеша")

    def find_product(self, ean: str = None, sku: str = None) -> Optional[int]:
        """
        Търси продукт по EAN или SKU.
        Връща номера на реда (1-indexed) или None ако не е намерен.
        """
        if self._cache is None:
            self._load_cache()

        if ean and ean in self._cache['ean_index']:
            return self._cache['ean_index'][ean]
        if sku and sku in self._cache['sku_index']:
            return self._cache['sku_index'][sku]
        return None

    def update_price(self, row_num: int, new_price: float, supplier: str, source_file: str):
        """Актуализира цената с цветова индикация"""
        # Вземи текущата цена
        current_price_str = self.sheet.cell(row_num, self.COL['Supplier_Price'] + 1).value
        try:
            current_price = float(current_price_str.replace(',', '.')) if current_price_str else 0
        except (ValueError, AttributeError):
            current_price = 0

        # Определи промяната
        if new_price > current_price:
            flag = 'UP'
            color = self.COLOR_PRICE_UP
        elif new_price < current_price:
            flag = 'DOWN'
            color = self.COLOR_PRICE_DOWN
        else:
            flag = 'SAME'
            color = self.COLOR_NO_CHANGE

        # Запиши историята в Price History sheet
        self._log_price_change(row_num, current_price, new_price, supplier, source_file)

        # Актуализирай реда
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        updates = [
            (row_num, self.COL['Supplier_Price'] + 1, str(new_price)),
            (row_num, self.COL['Price_Updated_At'] + 1, now),
            (row_num, self.COL['Price_Change_Flag'] + 1, flag),
        ]

        # Batch update за ефективност
        batch_data = [{'range': gspread.utils.rowcol_to_a1(r, c), 'values': [[v]]}
                      for r, c, v in updates]
        self.sheet.batch_update(batch_data)

        # Приложи цвят върху колоната с цена
        price_col_letter = gspread.utils.rowcol_to_a1(row_num, self.COL['Supplier_Price'] + 1)[:-1]
        self.sheet.format(f"{price_col_letter}{row_num}", {
            "backgroundColor": color
        })

        logger.info(f"  Обновена цена за ред {row_num}: {current_price} → {new_price} [{flag}]")
        return flag

    def add_new_product(self, product: dict):
        """Добавя нов продукт в таблицата"""
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        new_row = [''] * (max(self.COL.values()) + 1)

        new_row[self.COL['EAN']] = product.get('ean', '')
        new_row[self.COL['Our_SKU']] = product.get('sku', '')
        new_row[self.COL['Source']] = product.get('supplier', '')
        new_row[self.COL['Product_Name']] = product.get('name', '')
        new_row[self.COL['Brand']] = product.get('brand', '')
        new_row[self.COL['Supplier_Price']] = str(product.get('price', ''))
        new_row[self.COL['Price_Updated_At']] = now
        new_row[self.COL['Price_Change_Flag']] = 'NEW'
        new_row[self.COL['Upload_Status']] = 'NOT_UPLOADED'

        self.sheet.append_row(new_row)
        logger.info(f"  Добавен нов продукт: {product.get('ean')} — {product.get('name')}")

        # Инвалидирай кеша
        self._cache = None

    def _log_price_change(self, row_num: int, old_price: float, new_price: float,
                          supplier: str, source_file: str):
        """Записва промяната в Price History sheet"""
        try:
            history_sheet = self.spreadsheet.worksheet('Price History')
        except gspread.WorksheetNotFound:
            history_sheet = self.spreadsheet.add_worksheet('Price History', 1000, 10)
            history_sheet.append_row(['Timestamp', 'EAN', 'Our_SKU', 'Product_Name',
                                      'Old_Price', 'New_Price', 'Change_Pct',
                                      'Source', 'Source_File'])

        row_data = self.sheet.row_values(row_num)
        ean = row_data[self.COL['EAN']]
        sku = row_data[self.COL['Our_SKU']]
        name = row_data[self.COL['Product_Name']]
        change_pct = round((new_price - old_price) / old_price * 100, 2) if old_price else 0

        history_sheet.append_row([
            datetime.now().isoformat(), ean, sku, name,
            old_price, new_price, change_pct, supplier, source_file
        ])


# ============================================================
# ПАРСВАНЕ НА ФАЙЛОВЕ ОТ ДОСТАВЧИЦИ
# ============================================================
class PriceFileParser:
    """Парсва Excel и PDF файлове с ценови листи"""

    def parse_excel(self, file_path: str, supplier_config: dict = None) -> list[dict]:
        """
        Парсва Excel файл и връща списък с продукти.

        supplier_config позволява да се зададе кои колони съответстват на кои полета:
        {
            'ean_col': 'EAN',
            'sku_col': 'Артикул',
            'name_col': 'Наименование',
            'price_col': 'Цена нето',
            'brand_col': 'Марка',
            'skip_rows': 1  # брой редове за пропускане в началото
        }
        """
        if supplier_config is None:
            # Опит за автоматично разпознаване на колоните
            supplier_config = self._auto_detect_columns(file_path)

        df = pd.read_excel(file_path, skiprows=supplier_config.get('skip_rows', 0))
        products = []

        for _, row in df.iterrows():
            try:
                product = {
                    'ean': str(row.get(supplier_config.get('ean_col', 'EAN'), '')).strip(),
                    'sku': str(row.get(supplier_config.get('sku_col', 'SKU'), '')).strip(),
                    'name': str(row.get(supplier_config.get('name_col', 'Name'), '')).strip(),
                    'price': self._parse_price(row.get(supplier_config.get('price_col', 'Price'), 0)),
                    'brand': str(row.get(supplier_config.get('brand_col', ''), '')).strip(),
                }
                # Пропусни редове без EAN и без цена
                if product['ean'] and product['price'] > 0:
                    products.append(product)
            except Exception as e:
                logger.warning(f"Пропускам ред: {e}")
                continue

        logger.info(f"Парснати {len(products)} продукта от {Path(file_path).name}")
        return products

    def _parse_price(self, value) -> float:
        """Конвертира различни формати на цена към float"""
        if isinstance(value, (int, float)):
            return float(value)
        if isinstance(value, str):
            # Премахни валутни символи и интервали
            cleaned = value.replace('€', '').replace('$', '').replace(' ', '').replace(',', '.')
            try:
                return float(cleaned)
            except ValueError:
                return 0.0
        return 0.0

    def _auto_detect_columns(self, file_path: str) -> dict:
        """Опитва да разпознае автоматично колоните"""
        df = pd.read_excel(file_path, nrows=5)
        columns = [str(col).lower() for col in df.columns]

        config = {'skip_rows': 0}

        # EAN / Баркод
        for keyword in ['ean', 'barcode', 'баркод', 'gtin']:
            for col in df.columns:
                if keyword in str(col).lower():
                    config['ean_col'] = col
                    break

        # Цена
        for keyword in ['price', 'цена', 'netto', 'нето']:
            for col in df.columns:
                if keyword in str(col).lower():
                    config['price_col'] = col
                    break

        # Наименование
        for keyword in ['name', 'description', 'наименование', 'продукт', 'артикул']:
            for col in df.columns:
                if keyword in str(col).lower():
                    config['name_col'] = col
                    break

        return config


# ============================================================
# ГЛАВНА ФУНКЦИЯ
# ============================================================
def sync_products():
    """Главна функция за синхронизация"""
    logger.info("=" * 60)
    logger.info("Стартиране на Фаза 2A — Синхронизация на продукти")
    logger.info("=" * 60)

    # Инициализация
    creds = Credentials.from_service_account_file(GOOGLE_CREDENTIALS_FILE, scopes=SCOPES)
    gc = gspread.authorize(creds)
    central_table = CentralTable(gc)
    parser = PriceFileParser()

    # TODO: Фаза 2 — интеграция с Drive API за автоматично четене на файловете
    # За сега — ръчно указани файлове за тест
    test_files = [
        # {'path': 'data/supplier_a_prices.xlsx', 'supplier': 'Доставчик А'}
    ]

    processed = 0
    updated = 0
    added = 0

    for file_info in test_files:
        logger.info(f"\nОбработвам: {file_info['path']}")
        products = parser.parse_excel(file_info['path'])

        for product in products:
            product['supplier'] = file_info['supplier']
            existing_row = central_table.find_product(
                ean=product.get('ean'),
                sku=product.get('sku')
            )

            if existing_row:
                central_table.update_price(
                    existing_row,
                    product['price'],
                    file_info['supplier'],
                    file_info['path']
                )
                updated += 1
            else:
                central_table.add_new_product(product)
                added += 1

            processed += 1

    logger.info(f"\n{'=' * 60}")
    logger.info(f"Завършено: {processed} продукта обработени")
    logger.info(f"  Обновени: {updated}")
    logger.info(f"  Нови: {added}")


if __name__ == '__main__':
    # Създай папка за логове ако не съществува
    Path('logs').mkdir(exist_ok=True)
    sync_products()
