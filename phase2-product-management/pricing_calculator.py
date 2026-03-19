"""
Amazon FBM Automation — Фаза 2B
Модул: Изчисление на крайна цена по държави

Формула:
    Final Price = (Supplier_Price + Shipping) / (1 - Amazon_Fee%) * (1 + VAT%) + FBM_Fee
"""

import os
import logging
from dataclasses import dataclass
from typing import Optional

import gspread
from google.oauth2.service_account import Credentials
from dotenv import load_dotenv

load_dotenv()
logger = logging.getLogger(__name__)

# ============================================================
# КОНФИГУРАЦИЯ НА ТАКСИТЕ ПО ДЪРЖАВИ
# ============================================================

@dataclass
class MarketplaceConfig:
    country_code: str
    vat_pct: float
    amazon_fee_pct: float
    shipping_cost_eur: float
    fbm_fee_eur: float
    min_margin_pct: float
    currency: str = 'EUR'
    currency_rate: float = 1.0  # Спрямо EUR


# ⚠️ ВАЖНО: Тези стойности трябва да бъдат потвърдени от бизнес екипа!
MARKETPLACES = {
    'DE': MarketplaceConfig('DE', vat_pct=0.19, amazon_fee_pct=0.15,
                             shipping_cost_eur=4.50, fbm_fee_eur=1.00,
                             min_margin_pct=0.15),
    'FR': MarketplaceConfig('FR', vat_pct=0.20, amazon_fee_pct=0.15,
                             shipping_cost_eur=5.00, fbm_fee_eur=1.00,
                             min_margin_pct=0.15),
    'IT': MarketplaceConfig('IT', vat_pct=0.22, amazon_fee_pct=0.15,
                             shipping_cost_eur=5.50, fbm_fee_eur=1.00,
                             min_margin_pct=0.15),
    'ES': MarketplaceConfig('ES', vat_pct=0.21, amazon_fee_pct=0.15,
                             shipping_cost_eur=5.50, fbm_fee_eur=1.00,
                             min_margin_pct=0.15),
    'NL': MarketplaceConfig('NL', vat_pct=0.21, amazon_fee_pct=0.15,
                             shipping_cost_eur=4.50, fbm_fee_eur=1.00,
                             min_margin_pct=0.15),
    'PL': MarketplaceConfig('PL', vat_pct=0.23, amazon_fee_pct=0.15,
                             shipping_cost_eur=6.00, fbm_fee_eur=1.00,
                             min_margin_pct=0.15),
    'SE': MarketplaceConfig('SE', vat_pct=0.25, amazon_fee_pct=0.15,
                             shipping_cost_eur=7.00, fbm_fee_eur=1.00,
                             min_margin_pct=0.15, currency='SEK', currency_rate=11.50),
}


# ============================================================
# ИЗЧИСЛЕНИЕ
# ============================================================
class PriceCalculator:
    """Изчислява крайни цени за Amazon по държави"""

    def calculate_final_price(
        self,
        supplier_price: float,
        marketplace: str,
        config: Optional[MarketplaceConfig] = None
    ) -> dict:
        """
        Изчислява крайната цена за даден пазар.

        Returns:
            {
                'price_eur': крайна цена в EUR,
                'price_local': крайна цена в местна валута,
                'breakdown': детайлно разбивка,
                'margin_pct': марж в %,
                'is_viable': дали марژът е достатъчен
            }
        """
        if config is None:
            config = MARKETPLACES.get(marketplace)
            if not config:
                raise ValueError(f"Непознат пазар: {marketplace}")

        # Формула:
        # (Supplier_Price + Shipping) / (1 - Amazon_Fee%) * (1 + VAT%) + FBM_Fee
        base_cost = supplier_price + config.shipping_cost_eur
        before_vat = base_cost / (1 - config.amazon_fee_pct)
        final_eur = before_vat * (1 + config.vat_pct) + config.fbm_fee_eur

        # Закръгли до .99 центи (психологическо ценообразуване)
        final_eur = self._round_to_99(final_eur)

        # В местна валута
        final_local = round(final_eur * config.currency_rate, 2)
        if config.currency != 'EUR':
            final_local = self._round_to_99(final_local)

        # Изчисли реалния марж
        margin_eur = final_eur - base_cost - (final_eur * config.amazon_fee_pct) - config.fbm_fee_eur
        margin_pct = round(margin_eur / final_eur, 4) if final_eur > 0 else 0

        return {
            'price_eur': final_eur,
            'price_local': final_local,
            'currency': config.currency,
            'breakdown': {
                'supplier_price': supplier_price,
                'shipping': config.shipping_cost_eur,
                'amazon_fee_eur': round(final_eur * config.amazon_fee_pct, 2),
                'vat_eur': round(before_vat * config.vat_pct, 2),
                'fbm_fee': config.fbm_fee_eur,
            },
            'margin_pct': margin_pct,
            'is_viable': margin_pct >= config.min_margin_pct
        }

    def calculate_all_markets(self, supplier_price: float) -> dict:
        """Изчислява цени за всички пазари наведнъж"""
        results = {}
        for market_code in MARKETPLACES:
            try:
                results[market_code] = self.calculate_final_price(supplier_price, market_code)
            except Exception as e:
                logger.error(f"Грешка при изчисление за {market_code}: {e}")
                results[market_code] = None
        return results

    def _round_to_99(self, price: float) -> float:
        """Закръгля до .99 (напр. 45.23 → 44.99, 45.67 → 45.99)"""
        import math
        floor_val = math.floor(price)
        if price - floor_val < 0.5:
            return floor_val - 0.01 if floor_val > 0 else 0.99
        else:
            return floor_val + 0.99

    def update_central_table(self, gc: gspread.Client, sheet_id: str):
        """Обновява колоните Final_Price в централната таблица"""
        ss = gc.open_by_key(sheet_id)
        sheet = ss.worksheet('Products')
        all_data = sheet.get_all_values()

        headers = all_data[0]
        updates = []

        logger.info(f"Изчисляване на цени за {len(all_data) - 1} продукта...")

        for i, row in enumerate(all_data[1:], start=2):
            try:
                price_str = row[6]  # Supplier_Price колона
                if not price_str:
                    continue
                supplier_price = float(price_str.replace(',', '.'))
                prices = self.calculate_all_markets(supplier_price)

                # Обнови колоните Final_Price_XX
                col_map = {
                    'DE': 17, 'FR': 18, 'IT': 19, 'ES': 20,
                    'NL': 21, 'PL': 22, 'SE': 23
                }
                for market, col_idx in col_map.items():
                    if prices.get(market):
                        cell = gspread.utils.rowcol_to_a1(i, col_idx)
                        updates.append({
                            'range': cell,
                            'values': [[prices[market]['price_local']]]
                        })
            except (ValueError, IndexError) as e:
                logger.warning(f"Пропускам ред {i}: {e}")
                continue

        if updates:
            sheet.batch_update(updates)
            logger.info(f"Обновени {len(updates)} ценови клетки")


# ============================================================
# CLI / ТЕСТ
# ============================================================
if __name__ == '__main__':
    calc = PriceCalculator()

    # Тест с примерна цена
    test_price = 45.00
    print(f"\n{'='*60}")
    print(f"Ценови калкулатор — Тест с цена: €{test_price}")
    print(f"{'='*60}")

    results = calc.calculate_all_markets(test_price)
    for market, result in results.items():
        if result:
            print(f"\n{market}:")
            print(f"  Крайна цена: {result['price_local']} {result['currency']}")
            print(f"  Марж: {result['margin_pct']*100:.1f}% {'✅' if result['is_viable'] else '❌ НЕДОСТАТЪЧЕН'}")
            print(f"  Разбивка: {result['breakdown']}")
