#!/usr/bin/env python3
"""
parse_excel.py — парсва FBM Products Excel файл и генерира products.json
Употреба: python3 parse_excel.py input.xlsx output.json
"""
import sys
import json
import os

try:
    import openpyxl
except ImportError:
    os.system('pip install openpyxl -q')
    import openpyxl

if len(sys.argv) < 3:
    print("Usage: parse_excel.py <input.xlsx> <output.json>")
    sys.exit(1)

input_file  = sys.argv[1]
output_file = sys.argv[2]

# Column mapping (1-based index → field key)
FIELD_MAP = {
    1:  'ean',
    2:  'ean_source',
    3:  'price_correction_note',
    5:  'our_sku',
    6:  'source_catalog_nr',
    7:  'source',
    8:  'brand',
    9:  'model',
    10: 'electronic',
    11: 'product_url',
    12: 'asin_de',
    13: 'price_competitor',
    14: 'price_amazon',
    15: 'selling_price',
    16: 'our_price_no_vat',
    17: 'our_price_vat',
    18: 'amazon_fees',
    19: 'supplier_price',
    20: 'vat_supplier',
    21: 'transport_from_supplier',
    22: 'transport_to_client',
    23: 'vat_transport',
    24: 'result',
    25: 'found_2nd_listing',
    26: 'price_es_fr_it',
    27: 'dm_price',
    28: 'new_price',
    29: 'delivered',
    30: 'next_order',
}

try:
    wb = openpyxl.load_workbook(input_file, data_only=True)
except Exception as e:
    print(f"Error opening Excel: {e}")
    sys.exit(1)

products = []
SHEETS   = ['New FBM', 'Uvex']

for sheet_name in SHEETS:
    if sheet_name not in wb.sheetnames:
        continue
    ws = wb[sheet_name]
    for row in range(4, ws.max_row + 1):
        ean_cell = ws.cell(row=row, column=1)
        if not ean_cell.value:
            continue

        p = {'sheet': sheet_name, 'upload_status': 'NOT_UPLOADED'}

        for col, key in FIELD_MAP.items():
            val = ws.cell(row=row, column=col).value
            if val is None:
                p[key] = None
            elif isinstance(val, float):
                p[key] = round(val, 4)
            elif isinstance(val, str):
                p[key] = val.strip() or None
            else:
                p[key] = val

        # Ensure EAN is string
        for fld in ('ean', 'ean_source'):
            if p.get(fld) is not None:
                try:
                    p[fld] = str(int(float(p[fld])))
                except Exception:
                    p[fld] = str(p[fld])

        # Extract ASIN from product_url if not present
        if not p.get('asin_de') and p.get('product_url'):
            url = str(p['product_url'])
            if '/dp/' in url:
                asin = url.split('/dp/')[-1].split('/')[0].split('?')[0].strip()
                if len(asin) == 10:
                    p['asin_de'] = asin

        products.append(p)

# Write output
with open(output_file, 'w', encoding='utf-8') as f:
    json.dump(products, f, ensure_ascii=False, indent=2, default=str)

print(f"OK: {len(products)} products written to {output_file}")
