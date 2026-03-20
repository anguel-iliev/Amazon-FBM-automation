#!/usr/bin/env python3
"""
AMZ Retail Platform — Модул 2A + 2B
Синхронизация на продукти от Google Drive + ценообразуване

Изисквания (инсталирай веднъж):
  pip3 install gspread google-auth openpyxl pandas pdfplumber --user

Настройка:
  1. Постави google-credentials.json в src/config/
  2. Обнови SHEET_ID и DRIVE_FOLDER_ID в .env или директно тук
"""

import os, sys, json, time, logging
from datetime import datetime
from pathlib import Path

# ── Пътища ────────────────────────────────────────────────────────────────────
ROOT      = Path(__file__).parent.parent
DATA_DIR  = ROOT / 'data'
LOGS_DIR  = DATA_DIR / 'logs'
CACHE_DIR = DATA_DIR / 'cache'
CREDS     = ROOT / 'src' / 'config' / 'google-credentials.json'
ENV_FILE  = ROOT / '.env'

for d in [DATA_DIR, LOGS_DIR, CACHE_DIR]:
    d.mkdir(parents=True, exist_ok=True)

# ── Logging ────────────────────────────────────────────────────────────────────
log_file = LOGS_DIR / f"sync_{datetime.now().strftime('%Y-%m-%d')}.log"
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[logging.FileHandler(log_file), logging.StreamHandler()]
)
log = logging.getLogger('sync')

# ── Load .env ──────────────────────────────────────────────────────────────────
def load_env():
    env = {}
    if ENV_FILE.exists():
        for line in ENV_FILE.read_text().splitlines():
            line = line.strip()
            if line and not line.startswith('#') and '=' in line:
                k, v = line.split('=', 1)
                env[k.strip()] = v.strip()
    return env

ENV = load_env()
DRIVE_FOLDER_ID = ENV.get('GOOGLE_DRIVE_FOLDER_ID', '100T4KgyVIXhKlJczQv7DR9CJlV27DbUx')
SHEET_ID        = ENV.get('GOOGLE_SHEET_ID', '')

# ── Google API setup ───────────────────────────────────────────────────────────
def get_google_clients():
    try:
        import gspread
        from google.oauth2.service_account import Credentials
        from googleapiclient.discovery import build

        scopes = [
            'https://www.googleapis.com/auth/spreadsheets',
            'https://www.googleapis.com/auth/drive.readonly',
        ]
        creds  = Credentials.from_service_account_file(str(CREDS), scopes=scopes)
        gc     = gspread.authorize(creds)
        drive  = build('drive', 'v3', credentials=creds)
        return gc, drive
    except Exception as e:
        log.error(f"Google API init failed: {e}")
        return None, None

# ── Read settings.json ─────────────────────────────────────────────────────────
def load_settings() -> dict:
    f = DATA_DIR / 'settings.json'
    if f.exists():
        return json.loads(f.read_text())
    return {
        'marketplaces': {
            'DE': {'vat': 0.19, 'amazon_fee': 0.15, 'shipping': 4.50, 'fbm_fee': 1.00, 'active': True},
            'FR': {'vat': 0.20, 'amazon_fee': 0.15, 'shipping': 5.00, 'fbm_fee': 1.00, 'active': True},
            'IT': {'vat': 0.22, 'amazon_fee': 0.15, 'shipping': 5.50, 'fbm_fee': 1.00, 'active': True},
            'ES': {'vat': 0.21, 'amazon_fee': 0.15, 'shipping': 5.50, 'fbm_fee': 1.00, 'active': True},
            'NL': {'vat': 0.21, 'amazon_fee': 0.15, 'shipping': 4.50, 'fbm_fee': 1.00, 'active': True},
            'PL': {'vat': 0.23, 'amazon_fee': 0.15, 'shipping': 6.00, 'fbm_fee': 1.00, 'active': False},
            'SE': {'vat': 0.25, 'amazon_fee': 0.15, 'shipping': 7.00, 'fbm_fee': 1.00, 'active': False},
        }
    }

# ── Module 2B: Price calculator ────────────────────────────────────────────────
def calc_price(supplier_price: float, cfg: dict) -> dict:
    vat      = float(cfg.get('vat', 0.19))
    amz_fee  = float(cfg.get('amazon_fee', 0.15))
    shipping = float(cfg.get('shipping', 4.50))
    fbm_fee  = float(cfg.get('fbm_fee', 1.00))

    base       = supplier_price + shipping
    before_vat = base / (1 - amz_fee)
    final      = before_vat * (1 + vat) + fbm_fee

    # Round to .99
    import math
    final = math.floor(final) + 0.99

    margin     = final - base - (final * amz_fee) - fbm_fee
    margin_pct = round(margin / final * 100, 2) if final > 0 else 0

    return {'final': round(final, 2), 'margin_pct': margin_pct}

def calc_all_prices(supplier_price: float, settings: dict) -> dict:
    result = {}
    for code, cfg in settings.get('marketplaces', {}).items():
        if cfg.get('active', False):
            result[code] = calc_price(supplier_price, cfg)
    return result

# ── Module 2A: Read supplier files from Drive ──────────────────────────────────
def list_drive_files(drive, folder_id: str) -> list:
    """Листи всички Excel/PDF файлове рекурсивно"""
    files = []
    try:
        query   = f"'{folder_id}' in parents and trashed=false"
        resp    = drive.files().list(q=query, fields='files(id,name,mimeType,modifiedTime,parents)').execute()
        items   = resp.get('files', [])

        for item in items:
            mime = item['mimeType']
            if mime == 'application/vnd.google-apps.folder':
                # Recurse into subfolder
                files.extend(list_drive_files(drive, item['id']))
            elif any(item['name'].lower().endswith(ext) for ext in ['.xlsx', '.xls', '.csv']):
                files.append(item)
    except Exception as e:
        log.error(f"Drive list error: {e}")
    return files

def download_drive_file(drive, file_id: str, dest: Path) -> bool:
    """Сваля файл от Drive"""
    try:
        import io
        from googleapiclient.http import MediaIoBaseDownload
        req    = drive.files().get_media(fileId=file_id)
        buf    = io.BytesIO()
        dl     = MediaIoBaseDownload(buf, req)
        done   = False
        while not done:
            _, done = dl.next_chunk()
        dest.write_bytes(buf.getvalue())
        return True
    except Exception as e:
        log.error(f"Download failed {file_id}: {e}")
        return False

def parse_excel(file_path: Path, supplier_name: str) -> list:
    """Парсва Excel файл — извлича EAN, цена, продукт"""
    try:
        import pandas as pd
        df = pd.read_excel(str(file_path), dtype=str)
        df.columns = [str(c).strip().lower() for c in df.columns]

        products = []
        # Намери колоните автоматично
        ean_col   = next((c for c in df.columns if any(k in c for k in ['ean','barcode','баркод','gtin'])), None)
        price_col = next((c for c in df.columns if any(k in c for k in ['цена','netto','net','price','прайс'])), None)
        name_col  = next((c for c in df.columns if any(k in c for k in ['product','наим','name','артикул','model'])), None)
        sku_col   = next((c for c in df.columns if any(k in c for k in ['sku','our sku','артикул'])), None)

        if not ean_col and not price_col:
            log.warning(f"Cannot detect columns in {file_path.name}")
            return []

        for _, row in df.iterrows():
            ean   = str(row.get(ean_col, '')   or '').strip().split('.')[0]
            price = str(row.get(price_col, '') or '').strip().replace(',', '.').replace('€', '').strip()
            name  = str(row.get(name_col, '')  or '').strip() if name_col else ''
            sku   = str(row.get(sku_col, '')   or '').strip() if sku_col else ''

            try:
                price_f = float(price)
            except ValueError:
                continue

            if not ean or price_f <= 0:
                continue

            products.append({
                'ean':            ean,
                'our_sku':        sku or f"{supplier_name[:3].upper()}-{ean}",
                'source':         supplier_name,
                'product_name':   name,
                'supplier_price': price_f,
            })

        log.info(f"  Parsed {len(products)} products from {file_path.name}")
        return products

    except Exception as e:
        log.error(f"Excel parse error {file_path}: {e}")
        return []

# ── Merge with existing products ───────────────────────────────────────────────
def merge_products(existing: list, new_products: list, settings: dict) -> tuple:
    """Сравнява и обновява. Връща (merged_list, stats)"""
    # Build index
    idx_ean = {p['ean']: i for i, p in enumerate(existing) if p.get('ean')}
    idx_sku = {p['our_sku']: i for i, p in enumerate(existing) if p.get('our_sku')}

    stats = {'updated': 0, 'new': 0, 'unchanged': 0}

    for np in new_products:
        ean = np.get('ean', '')
        sku = np.get('our_sku', '')

        existing_idx = idx_ean.get(ean) or idx_sku.get(sku)

        # Calculate prices for all active markets
        prices = calc_all_prices(np['supplier_price'], settings)

        if existing_idx is not None:
            ep = existing[existing_idx]
            old_price = float(ep.get('supplier_price', 0) or 0)
            new_price = np['supplier_price']

            if abs(old_price - new_price) > 0.001:
                # Price changed
                change = 'UP' if new_price > old_price else 'DOWN'
                existing[existing_idx].update({
                    'supplier_price':   new_price,
                    'price_change':     change,
                    'price_updated_at': datetime.now().isoformat(),
                    **{f'final_price_{c}': v['final'] for c, v in prices.items()},
                })
                stats['updated'] += 1
                log.info(f"  Updated {ean}: {old_price} → {new_price} [{change}]")
            else:
                stats['unchanged'] += 1
        else:
            # New product
            new_entry = {
                'ean':              ean,
                'our_sku':          sku,
                'source':           np.get('source', ''),
                'product_name':     np.get('product_name', ''),
                'supplier_price':   np['supplier_price'],
                'price_change':     'NEW',
                'price_updated_at': datetime.now().isoformat(),
                'asin_de': '', 'asin_fr': '', 'asin_it': '',
                'asin_es': '', 'asin_nl': '',
                'upload_status': 'NOT_UPLOADED',
                **{f'final_price_{c}': v['final'] for c, v in prices.items()},
            }
            existing.append(new_entry)
            if ean:
                idx_ean[ean] = len(existing) - 1
            stats['new'] += 1
            log.info(f"  New product: {ean} {np.get('product_name', '')[:40]}")

    return existing, stats

# ── Save sync log ──────────────────────────────────────────────────────────────
def save_sync_log(stats: dict, duration: float, status: str):
    log_file = DATA_DIR / 'sync_log.json'
    logs = []
    if log_file.exists():
        try:
            logs = json.loads(log_file.read_text())
        except Exception:
            logs = []

    entry = {
        'date':     datetime.now().isoformat(),
        'status':   status,
        'duration': round(duration, 1),
        **stats,
    }
    logs.insert(0, entry)
    logs = logs[:100]
    log_file.write_text(json.dumps(logs, ensure_ascii=False, indent=2))

# ── Main ───────────────────────────────────────────────────────────────────────
def main():
    t0       = time.time()
    settings = load_settings()

    log.info("=" * 60)
    log.info("AMZ Retail — Sync start")
    log.info("=" * 60)

    # Load existing products from cache
    cache_file = CACHE_DIR / 'products.json'
    existing   = []
    if cache_file.exists():
        try:
            existing = json.loads(cache_file.read_text())
            log.info(f"Loaded {len(existing)} existing products from cache")
        except Exception:
            existing = []

    # Connect to Google
    if not CREDS.exists():
        log.error(f"Google credentials not found: {CREDS}")
        log.error("Place google-credentials.json in src/config/")
        save_sync_log({'uploaded': 0, 'updated': 0, 'new': 0, 'errors': 1}, time.time() - t0, 'error')
        sys.exit(1)

    gc, drive = get_google_clients()
    if not drive:
        save_sync_log({'uploaded': 0, 'updated': 0, 'new': 0, 'errors': 1}, time.time() - t0, 'error')
        sys.exit(1)

    # List supplier files from Drive
    log.info(f"Scanning Drive folder: {DRIVE_FOLDER_ID}")
    files = list_drive_files(drive, DRIVE_FOLDER_ID)
    log.info(f"Found {len(files)} Excel files")

    all_stats = {'uploaded': 0, 'updated': 0, 'new': 0, 'errors': 0}
    tmp_dir   = CACHE_DIR / 'tmp_downloads'
    tmp_dir.mkdir(exist_ok=True)

    for f in files:
        file_name    = f['name']
        supplier_name= Path(file_name).stem.split('_')[0] if '_' in file_name else 'Unknown'
        tmp_path     = tmp_dir / file_name

        log.info(f"Processing: {file_name}")

        if download_drive_file(drive, f['id'], tmp_path):
            new_products = parse_excel(tmp_path, supplier_name)
            existing, stats = merge_products(existing, new_products, settings)
            for k in ['updated', 'new']:
                all_stats[k] += stats.get(k, 0)
            all_stats['uploaded'] += len(new_products)
            try:
                tmp_path.unlink()
            except Exception:
                pass
        else:
            all_stats['errors'] += 1

    # Save updated cache
    cache_file.write_text(json.dumps(existing, ensure_ascii=False, indent=2))
    log.info(f"Saved {len(existing)} products to cache")

    duration = time.time() - t0
    save_sync_log(all_stats, duration, 'success')

    log.info("=" * 60)
    log.info(f"DONE in {duration:.1f}s | Updated: {all_stats['updated']} | New: {all_stats['new']} | Errors: {all_stats['errors']}")
    log.info("=" * 60)

if __name__ == '__main__':
    main()
