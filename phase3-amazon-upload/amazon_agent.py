"""
Amazon FBM Automation — Фаза 3
Модул: Качване в Amazon чрез SP-API

Документация: https://developer-docs.amazon.com/sp-api/

Изисквания:
    pip install python-amazon-sp-api google-auth gspread openpyxl python-dotenv requests

Необходими credentials (в .env):
    SP_API_REFRESH_TOKEN=
    SP_API_CLIENT_ID=
    SP_API_CLIENT_SECRET=
    SP_API_AWS_ACCESS_KEY=
    SP_API_AWS_SECRET_KEY=
    SP_API_SELLER_ID=
    SP_API_MARKETPLACE_IDS_DE=A1PA6795UKMFR9
    SP_API_MARKETPLACE_IDS_FR=A13V1IB3VIYZZH
"""

import os
import logging
from datetime import date
from pathlib import Path
from typing import Optional

import gspread
import pandas as pd
from google.oauth2.service_account import Credentials
from dotenv import load_dotenv

load_dotenv()
logger = logging.getLogger(__name__)

# ============================================================
# AMAZON MARKETPLACE IDs
# ============================================================
MARKETPLACE_IDS = {
    'DE': 'A1PA6795UKMFR9',
    'FR': 'A13V1IB3VIYZZH',
    'IT': 'APJ6JRA9NG5V4',
    'ES': 'A1RKKUPIHCS9HS',
    'NL': 'A1805IZSGTT6HS',
    'PL': 'A1C3SOZRARQ6R3',
    'SE': 'A2NODRKZP88ZB9',
}


# ============================================================
# SP-API КЛИЕНТ (wrapper)
# ============================================================
class AmazonSPAPI:
    """
    Wrapper за Amazon Selling Partner API.

    Инсталирай: pip install python-amazon-sp-api
    Документация: https://python-amazon-sp-api.readthedocs.io/
    """

    def __init__(self):
        # Конфигурация от .env файла
        self.credentials = {
            'refresh_token': os.getenv('SP_API_REFRESH_TOKEN'),
            'lwa_app_id': os.getenv('SP_API_CLIENT_ID'),
            'lwa_client_secret': os.getenv('SP_API_CLIENT_SECRET'),
            'aws_access_key': os.getenv('SP_API_AWS_ACCESS_KEY'),
            'aws_secret_key': os.getenv('SP_API_AWS_SECRET_KEY'),
            'role_arn': os.getenv('SP_API_ROLE_ARN', ''),
        }
        self.seller_id = os.getenv('SP_API_SELLER_ID')

        # Lazy load на SP-API библиотеката
        try:
            from sp_api.api import CatalogItems, ListingsItems, Feeds
            from sp_api.base import Marketplaces

            self.catalog_api = CatalogItems(credentials=self.credentials)
            self.listings_api = ListingsItems(credentials=self.credentials)
            self.feeds_api = Feeds(credentials=self.credentials)
            self._available = True
            logger.info("SP-API клиент инициализиран успешно")
        except ImportError:
            logger.warning("SP-API библиотека не е инсталирана. Работи в mock режим.")
            self._available = False

    def search_by_ean(self, ean: str, marketplace: str) -> Optional[str]:
        """
        Търси продукт по EAN в Amazon и връща ASIN ако е намерен.

        Returns: ASIN string или None
        """
        if not self._available:
            logger.info(f"[MOCK] Търсене по EAN {ean} в {marketplace}")
            return None

        try:
            marketplace_id = MARKETPLACE_IDS.get(marketplace)
            if not marketplace_id:
                raise ValueError(f"Непознат marketplace: {marketplace}")

            from sp_api.base import Marketplaces
            response = self.catalog_api.search_catalog_items(
                keywords=[ean],
                marketplaceIds=[marketplace_id],
                includedData=['identifiers']
            )

            if response.payload and response.payload.get('items'):
                item = response.payload['items'][0]
                asin = item.get('asin')
                logger.info(f"  Намерен ASIN {asin} за EAN {ean} в {marketplace}")
                return asin

        except Exception as e:
            logger.error(f"Грешка при търсене на EAN {ean} в {marketplace}: {e}")

        return None

    def upload_via_feeds(self, upload_df: pd.DataFrame, marketplace: str) -> Optional[str]:
        """
        Качва продукти чрез Feeds API (bulk upload).

        Returns: Feed ID на качването
        """
        if not self._available:
            logger.info(f"[MOCK] Upload на {len(upload_df)} продукта в {marketplace}")
            return "MOCK_FEED_ID_12345"

        # TODO: Implement actual SP-API Feeds upload
        # Стъпки:
        # 1. Генерирай XML/JSON в Amazon формат
        # 2. POST /feeds/2021-06-30/documents → uploadUrl + feedDocumentId
        # 3. PUT uploadUrl (качи файла)
        # 4. POST /feeds/2021-06-30/feeds → feedId
        # 5. GET /feeds/2021-06-30/feeds/{feedId} → провери статуса
        raise NotImplementedError("Feeds API upload ще бъде имплементиран в следваща версия")


# ============================================================
# ГЕНЕРАТОР НА AMAZON UPLOAD ТАБЛИЦА
# ============================================================
class AmazonUploadGenerator:
    """Генерира Amazon-съвместима upload таблица"""

    # Amazon задължителни колони за FBM existing products
    REQUIRED_COLUMNS = [
        'sku', 'product-id', 'product-id-type', 'price',
        'minimum-seller-allowed-price', 'maximum-seller-allowed-price',
        'item-condition', 'quantity', 'add-delete', 'will-ship-internationally',
        'expedited-shipping', 'standard-plus', 'handling-time',
        'fulfillment-center-id'
    ]

    def generate(self, products: list[dict], marketplace: str) -> pd.DataFrame:
        """Генерира upload DataFrame за Amazon"""
        rows = []

        for product in products:
            asin_key = f'ASIN_{marketplace}'
            price_key = f'Final_Price_{marketplace}'

            row = {
                'sku': product.get('Our_SKU', ''),
                'product-id': product.get(asin_key, product.get('EAN', '')),
                'product-id-type': 'ASIN' if product.get(asin_key) else 'EAN',
                'price': product.get(price_key, ''),
                'minimum-seller-allowed-price': '',
                'maximum-seller-allowed-price': '',
                'item-condition': 11,  # New
                'quantity': 1,
                'add-delete': 'a',
                'will-ship-internationally': 'n',
                'expedited-shipping': 'n',
                'standard-plus': 'n',
                'handling-time': 1,
                'fulfillment-center-id': 'DEFAULT',
            }
            rows.append(row)

        df = pd.DataFrame(rows, columns=self.REQUIRED_COLUMNS)
        logger.info(f"Генерирана upload таблица: {len(df)} продукта за {marketplace}")
        return df


# ============================================================
# ГЛАВЕН АГЕНТ
# ============================================================
class AmazonUploadAgent:
    """Координира целия процес на качване в Amazon"""

    def __init__(self, gc: gspread.Client, sheet_id: str):
        self.gc = gc
        self.sheet_id = sheet_id
        self.sp_api = AmazonSPAPI()
        self.generator = AmazonUploadGenerator()

    def run(self, marketplaces: list[str] = None, dry_run: bool = True):
        """
        Главна функция.

        Args:
            marketplaces: Списък с пазари (напр. ['DE', 'FR'])
            dry_run: Ако True — само генерира файла, не качва в Amazon
        """
        if marketplaces is None:
            marketplaces = ['DE']

        logger.info("=" * 60)
        logger.info(f"Стартиране на Фаза 3 — Amazon Upload Agent")
        logger.info(f"Пазари: {marketplaces} | Dry run: {dry_run}")
        logger.info("=" * 60)

        # 1. Вземи немаркираните продукти
        products = self._get_unmarked_products()
        logger.info(f"Намерени {len(products)} продукта за качване")

        if not products:
            logger.info("Няма продукти за качване. Излизам.")
            return

        # 2. Намери ASIN-ите от Amazon
        for marketplace in marketplaces:
            logger.info(f"\n--- Обработка за {marketplace} ---")
            self._find_asins(products, marketplace)

            # 3. Генерирай upload таблица
            upload_df = self.generator.generate(products, marketplace)

            # 4. Запази в Google Drive
            filename = f"{marketplace}_{date.today().strftime('%Y-%m-%d')}.xlsx"
            output_path = f"output/{filename}"
            Path('output').mkdir(exist_ok=True)
            upload_df.to_excel(output_path, index=False)
            logger.info(f"Запазен файл: {output_path}")

            # TODO: Качи файла в Google Drive → 'Продукти за качване/'

            # 5. Качи в Amazon (само ако не е dry run)
            if not dry_run:
                feed_id = self.sp_api.upload_via_feeds(upload_df, marketplace)
                logger.info(f"Качено! Feed ID: {feed_id}")

        # 6. Маркирай продуктите като качени
        if not dry_run:
            self._mark_as_uploaded([p['row_num'] for p in products])

        logger.info("\n✅ Агентът завърши успешно!")

    def _get_unmarked_products(self) -> list[dict]:
        """Взима продуктите с Upload_Status = NOT_UPLOADED"""
        ss = self.gc.open_by_key(self.sheet_id)
        sheet = ss.worksheet('Products')
        all_data = sheet.get_all_values()

        products = []
        for i, row in enumerate(all_data[1:], start=2):
            # Upload_Status е колона 25 (0-indexed = 24)
            if len(row) > 24 and row[24] == 'NOT_UPLOADED':
                # Само ако има EAN
                if row[0]:
                    product = dict(zip(all_data[0], row))
                    product['row_num'] = i
                    products.append(product)
        return products

    def _find_asins(self, products: list[dict], marketplace: str):
        """Попълва ASIN-ите за даден пазар"""
        found = 0
        for product in products:
            ean = product.get('EAN', '')
            if ean and not product.get(f'ASIN_{marketplace}'):
                asin = self.sp_api.search_by_ean(ean, marketplace)
                if asin:
                    product[f'ASIN_{marketplace}'] = asin
                    found += 1
        logger.info(f"Намерени {found} нови ASIN-а за {marketplace}")

    def _mark_as_uploaded(self, row_nums: list[int]):
        """Маркира редовете като UPLOADED"""
        ss = self.gc.open_by_key(self.sheet_id)
        sheet = ss.worksheet('Products')
        from datetime import datetime
        now = datetime.now().isoformat()

        batch = []
        for row_num in row_nums:
            batch.append({'range': gspread.utils.rowcol_to_a1(row_num, 25), 'values': [['UPLOADED']]})
            batch.append({'range': gspread.utils.rowcol_to_a1(row_num, 26), 'values': [[now]]})

        if batch:
            sheet.batch_update(batch)
            logger.info(f"Маркирани {len(row_nums)} продукта като UPLOADED")


# ============================================================
# CLI
# ============================================================
if __name__ == '__main__':
    import argparse

    parser = argparse.ArgumentParser(description='Amazon FBM Upload Agent')
    parser.add_argument('--markets', nargs='+', default=['DE'],
                        help='Пазари за качване (напр. DE FR IT)')
    parser.add_argument('--live', action='store_true',
                        help='Реално качване (без --live е dry run)')
    args = parser.parse_args()

    Path('logs').mkdir(exist_ok=True)
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s [%(levelname)s] %(message)s',
        handlers=[
            logging.FileHandler('logs/phase3.log'),
            logging.StreamHandler()
        ]
    )

    SCOPES = [
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/drive'
    ]
    creds = Credentials.from_service_account_file(
        os.getenv('GOOGLE_CREDENTIALS_FILE', 'credentials.json'),
        scopes=SCOPES
    )
    gc = gspread.authorize(creds)

    agent = AmazonUploadAgent(gc, os.getenv('CENTRAL_SHEET_ID'))
    agent.run(marketplaces=args.markets, dry_run=not args.live)
