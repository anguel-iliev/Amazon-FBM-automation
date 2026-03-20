<?php
class PricingController {
    public function index(): void {
        require_once SRC . '/lib/DataStore.php';
        $settings = DataStore::getSettings();

        View::renderWithLayout('pricing/index', [
            'pageTitle'    => 'Ценообразуване',
            'activePage'   => 'pricing',
            'settings'     => $settings,
            'marketplaces' => $settings['marketplaces'] ?? [],
        ]);
    }

    public function calculate(): void {
        $price    = (float)($_POST['price'] ?? 0);
        $markets  = $_POST['markets'] ?? [];

        require_once SRC . '/lib/DataStore.php';
        $settings = DataStore::getSettings();
        $results  = [];

        foreach ($settings['marketplaces'] as $code => $cfg) {
            if (!in_array($code, $markets) && !empty($markets)) continue;
            $results[$code] = $this->calcPrice($price, $cfg);
        }

        View::json(['results' => $results]);
    }

    private function calcPrice(float $supplierPrice, array $cfg): array {
        $vat       = (float)($cfg['vat']        ?? 0.19);
        $amzFee    = (float)($cfg['amazon_fee'] ?? 0.15);
        $shipping  = (float)($cfg['shipping']   ?? 4.50);
        $fbmFee    = (float)($cfg['fbm_fee']    ?? 1.00);

        // Formula: (supplier + shipping) / (1 - amazon_fee%) * (1 + vat%) + fbm_fee
        $base      = $supplierPrice + $shipping;
        $beforeVat = $base / (1 - $amzFee);
        $final     = $beforeVat * (1 + $vat) + $fbmFee;

        // Round to .99
        $final = floor($final) + 0.99;
        if ($final - $supplierPrice < 0) $final = round($supplierPrice * 2.5, 2);

        $margin    = $final - $base - ($final * $amzFee) - $fbmFee;
        $marginPct = $final > 0 ? round($margin / $final * 100, 1) : 0;

        return [
            'final'      => round($final, 2),
            'margin'     => round($margin, 2),
            'margin_pct' => $marginPct,
            'viable'     => $marginPct >= 10,
            'breakdown'  => [
                'supplier'  => $supplierPrice,
                'shipping'  => $shipping,
                'amazon_fee'=> round($final * $amzFee, 2),
                'vat'       => round($beforeVat * $vat, 2),
                'fbm_fee'   => $fbmFee,
            ],
        ];
    }
}
