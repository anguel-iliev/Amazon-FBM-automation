<?php
class SuppliersController {

    public function index(): void {
        $suppliers = $this->load();
        try {
            $products = Firebase::getProducts();
            $counts   = [];
            foreach ($products as $p) { $s=$p['Доставчик']??''; if($s) $counts[$s]=($counts[$s]??0)+1; }
            foreach ($suppliers as &$sup) { $sup['product_count'] = $counts[$sup['name']]??0; }
            unset($sup);
        } catch (\Throwable $e) {}
        View::renderWithLayout('suppliers/index', ['pageTitle'=>'Доставчици','activePage'=>'suppliers','supplierList'=>$suppliers]);
    }

    public function save(): void {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        $src = is_array($json) ? $json : $_POST;

        $id = trim((string)($src['id'] ?? ''));
        $name = trim((string)($src['name'] ?? ''));
        $transport = trim((string)($src['transport_to_us'] ?? '0.39'));
        if ($transport === '' || !is_numeric(str_replace(',', '.', $transport))) $transport = '0.39';
        $transport = number_format((float)str_replace(',', '.', $transport), 2, '.', '');

        if (!$name) { View::json(['success'=>false,'error'=>'Името е задължително']); return; }
        $list=$this->load(); $found=false;
        foreach ($list as &$s) {
            if (($s['id'] ?? '') === $id) {
                $s=array_merge($s,[
                    'name'=>$name,
                    'email'=>trim((string)($src['email'] ?? '')),
                    'phone'=>trim((string)($src['phone'] ?? '')),
                    'website'=>trim((string)($src['website'] ?? '')),
                    'notes'=>trim((string)($src['notes'] ?? '')),
                    'active'=>!isset($src['active']) || (bool)$src['active'],
                    'currency'=>trim((string)($src['currency'] ?? ($s['currency'] ?? 'EUR'))) ?: 'EUR',
                    'payment_terms'=>trim((string)($src['payment_terms'] ?? ($s['payment_terms'] ?? ''))),
                    'min_order'=>(float)($src['min_order'] ?? ($s['min_order'] ?? 0)),
                    'transport_to_us'=>$transport,
                    'updated_at'=>date('Y-m-d H:i:s')
                ]);
                $found=true; break;
            }
        }
        unset($s);
        if (!$found) $list[]=[
            'id'=>'sup_'.substr(md5($name.time()),0,8),
            'name'=>$name,
            'email'=>trim((string)($src['email'] ?? '')),
            'phone'=>trim((string)($src['phone'] ?? '')),
            'website'=>trim((string)($src['website'] ?? '')),
            'notes'=>trim((string)($src['notes'] ?? '')),
            'active'=>!isset($src['active']) || (bool)$src['active'],
            'currency'=>trim((string)($src['currency'] ?? 'EUR')) ?: 'EUR',
            'payment_terms'=>trim((string)($src['payment_terms'] ?? '')),
            'min_order'=>(float)($src['min_order'] ?? 0),
            'transport_to_us'=>$transport,
            'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')
        ];
        $this->save_($list);
        $this->syncTransportToProducts($name, $transport);
        View::json(['success'=>true]);
    }

    public function delete(): void {
        $id=trim($_POST['id']??'');
        if (!$id) { View::json(['success'=>false,'error'=>'Липсва ID']); return; }
        $this->save_(array_values(array_filter($this->load(), fn($s)=>$s['id']!==$id)));
        View::json(['success'=>true]);
    }

    private function load(): array {
        $f=DATA_DIR.'/suppliers.json';
        if (!file_exists($f)||empty($d=json_decode(file_get_contents($f),true))) return $this->seed();
        $changed = false;
        foreach ($d as &$row) {
            if (!isset($row['transport_to_us']) || $row['transport_to_us'] === '') {
                $row['transport_to_us'] = '0.39';
                $changed = true;
            } else {
                $row['transport_to_us'] = number_format((float)str_replace(',', '.', (string)$row['transport_to_us']), 2, '.', '');
            }
        }
        unset($row);
        if ($changed) $this->save_($d);
        return $d;
    }
    private function save_(array $list): void { file_put_contents(DATA_DIR.'/suppliers.json',json_encode(array_values($list),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX); }
    private function seed(): array {
        $names=['Agiva','Amperel','Argoprima','Axxon','Bebolino','Best whole sale company','Buldent','Comsed','Elle cosmetique','Fortuna','Giochi Giachi IT','Iventas','Makave','Orbico','Töpfer','Uvex','Yutika natural'];
        $list=[];
        foreach ($names as $n) $list[]=['id'=>'sup_'.substr(md5($n),0,8),'name'=>$n,'email'=>'','phone'=>'','website'=>'','notes'=>'','active'=>true,'currency'=>'EUR','payment_terms'=>'','min_order'=>0,'transport_to_us'=>'0.39','created_at'=>'2025-01-01','updated_at'=>date('Y-m-d')];
        $this->save_($list); return $list;
    }


    private function normalizeSupplierName(string $name): string {
        $name = trim(mb_strtolower($name, 'UTF-8'));
        $name = preg_replace('/\s+/u', ' ', $name);
        return $name;
    }

    private function syncTransportToProducts(string $supplierName, string $transport): void {
        try {
            $products = Firebase::getProducts();
            if (!$products) return;
            $changed = false;
            $target = $this->normalizeSupplierName($supplierName);
            foreach ($products as &$p) {
                $currentSupplier = $this->normalizeSupplierName((string)($p['Доставчик'] ?? ''));
                if ($currentSupplier === $target) {
                    $p['Транспорт от Доставчик до нас'] = $transport;
                    $changed = true;
                }
            }
            unset($p);
            if ($changed) {
                Firebase::putProducts($products);
                ProductDB::replaceAll($products);
            }
        } catch (\Throwable $e) {
            Logger::error('Suppliers sync transport: ' . $e->getMessage());
        }
    }
}
