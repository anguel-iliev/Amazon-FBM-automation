<?php
class SuppliersController {

    public function index(): void {
        $suppliers = ProductDB::getSuppliers();
        try {
            $products = Firebase::getProducts();
            $counts   = [];
            foreach ($products as $p) {
                $s = $p['Доставчик'] ?? '';
                if ($s) $counts[$s] = ($counts[$s] ?? 0) + 1;
            }
            foreach ($suppliers as &$sup) {
                $sup['product_count'] = $counts[$sup['name']] ?? 0;
            }
            unset($sup);
        } catch (\Throwable $e) {}
        View::renderWithLayout('suppliers/index', ['pageTitle'=>'Доставчици','activePage'=>'suppliers','supplierList'=>$suppliers]);
    }

    public function save(): void {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        $src = is_array($json) ? $json : $_POST;

        $name = trim((string)($src['name'] ?? ''));
        if (!$name) { View::json(['success'=>false,'error'=>'Името е задължително']); return; }

        $result = ProductDB::saveSupplier($src);
        if (!($result['ok'] ?? false)) {
            View::json(['success'=>false,'error'=>$result['error'] ?? 'Грешка при запис на доставчик']);
            return;
        }

        $transport = (string)($result['transport_to_us'] ?? '0.39');
        $this->syncTransportToProducts($name, $transport);
        Logger::audit(($result['created'] ?? false) ? 'supplier.created' : 'supplier.updated', ['name'=>$name, 'transport_to_us'=>$transport, 'by'=>$_SESSION['user'] ?? '']);
        View::json(['success'=>true, 'id'=>$result['id'] ?? '']);
    }

    public function delete(): void {
        $id=trim($_POST['id']??'');
        if (!$id) { View::json(['success'=>false,'error'=>'Липсва ID']); return; }
        $ok = ProductDB::deleteSupplier($id);
        if ($ok) Logger::audit('supplier.deleted', ['id'=>$id, 'by'=>$_SESSION['user'] ?? '']);
        View::json(['success'=>$ok]);
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
