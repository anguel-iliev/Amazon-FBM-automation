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
        $id=$_POST['id']??''; $name=trim($_POST['name']??'');
        if (!$name) { View::json(['success'=>false,'error'=>'Името е задължително']); return; }
        $list=$this->load(); $found=false;
        foreach ($list as &$s) { if ($s['id']===$id) { $s=array_merge($s,['name'=>$name,'email'=>trim($_POST['email']??''),'phone'=>trim($_POST['phone']??''),'website'=>trim($_POST['website']??''),'notes'=>trim($_POST['notes']??''),'active'=>isset($_POST['active']),'updated_at'=>date('Y-m-d H:i:s')]); $found=true; break; } }
        unset($s);
        if (!$found) $list[]=['id'=>'sup_'.substr(md5($name.time()),0,8),'name'=>$name,'email'=>trim($_POST['email']??''),'phone'=>trim($_POST['phone']??''),'website'=>trim($_POST['website']??''),'notes'=>trim($_POST['notes']??''),'active'=>true,'currency'=>'EUR','payment_terms'=>'','min_order'=>0,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')];
        $this->save_($list); View::json(['success'=>true]);
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
        return $d;
    }
    private function save_(array $list): void { file_put_contents(DATA_DIR.'/suppliers.json',json_encode(array_values($list),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX); }
    private function seed(): array {
        $names=['Agiva','Amperel','Argoprima','Axxon','Bebolino','Best whole sale company','Buldent','Comsed','Elle cosmetique','Fortuna','Giochi Giachi IT','Iventas','Makave','Orbico','Töpfer','Uvex','Yutika natural'];
        $list=[];
        foreach ($names as $n) $list[]=['id'=>'sup_'.substr(md5($n),0,8),'name'=>$n,'email'=>'','phone'=>'','website'=>'','notes'=>'','active'=>true,'currency'=>'EUR','payment_terms'=>'','min_order'=>0,'created_at'=>'2025-01-01','updated_at'=>date('Y-m-d')];
        $this->save_($list); return $list;
    }
}
