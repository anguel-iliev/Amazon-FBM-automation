<?php
class PricingController {
    public function redirectVat(): void { View::redirect('/vat'); }
    public function index(): void {
        $settings = Settings::get();
        View::renderWithLayout('pricing/index', ['pageTitle'=>'ДДС','activePage'=>'pricing','settings'=>$settings,'marketplaces'=>$settings['marketplaces']??[]]);
    }
    public function calculate(): void {
        $price   = (float)($_POST['price']??0);
        $markets = $_POST['markets']??[];
        $settings= Settings::get();
        $results = [];
        foreach ($markets as $code) {
            $m = $settings['marketplaces'][$code] ?? null;
            if (!$m) continue;
            $sellPrice = $price * (1 + ($m['vat']??0.19));
            $fee       = $sellPrice * ($m['amazon_fee']??0.15);
            $shipping  = $m['shipping']??4.50;
            $rezultat  = $sellPrice - $fee - $shipping - $price;
            $results[$code] = ['sell_price'=>round($sellPrice,2),'fee'=>round($fee,2),'shipping'=>round($shipping,2),'rezultat'=>round($rezultat,2),'margin'=>$price>0?round($rezultat/$price*100,1):0];
        }
        View::json(['success'=>true,'results'=>$results]);
    }
}
