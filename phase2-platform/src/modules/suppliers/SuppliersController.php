<?php
class SuppliersController {
    public function index() {
        require_once SRC . '/lib/DataStore.php';

        // Load suppliers list
        $suppliers = DataStore::getSuppliers();

        // Enrich with product count per supplier
        $products  = DataStore::getProducts();
        $counts    = [];
        foreach ($products as $p) {
            $src = $p['source'] ?? '';
            if ($src) $counts[$src] = ($counts[$src] ?? 0) + 1;
        }
        foreach ($suppliers as &$sup) {
            $sup['product_count'] = $counts[$sup['name'] ?? ''] ?? 0;
        }
        unset($sup);

        View::renderWithLayout('suppliers/index', [
            'pageTitle'    => 'Доставчици',
            'activePage'   => 'suppliers',
            'supplierList' => $suppliers,
        ]);
    }

    public function save() {
        require_once SRC . '/lib/DataStore.php';

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $name = trim($data['name'] ?? '');
        if (!$name) {
            View::json(['success' => false, 'error' => 'Името е задължително'], 400);
            return;
        }

        $suppliers = DataStore::getSuppliers();
        $id = $data['id'] ?? '';

        if ($id) {
            // Update existing
            $found = false;
            foreach ($suppliers as &$sup) {
                if ($sup['id'] == $id) {
                    $sup = array_merge($sup, [
                        'name'          => $name,
                        'email'         => trim($data['email'] ?? ''),
                        'phone'         => trim($data['phone'] ?? ''),
                        'website'       => trim($data['website'] ?? ''),
                        'currency'      => trim($data['currency'] ?? 'EUR'),
                        'payment_terms' => trim($data['payment_terms'] ?? ''),
                        'min_order'     => (float)($data['min_order'] ?? 0),
                        'notes'         => trim($data['notes'] ?? ''),
                        'active'        => (bool)($data['active'] ?? true),
                        'updated_at'    => date('Y-m-d H:i:s'),
                    ]);
                    $found = true;
                    break;
                }
            }
            unset($sup);
            if (!$found) {
                View::json(['success' => false, 'error' => 'Доставчикът не е намерен'], 404);
                return;
            }
        } else {
            // Create new
            $suppliers[] = [
                'id'            => uniqid('sup_'),
                'name'          => $name,
                'email'         => trim($data['email'] ?? ''),
                'phone'         => trim($data['phone'] ?? ''),
                'website'       => trim($data['website'] ?? ''),
                'currency'      => trim($data['currency'] ?? 'EUR'),
                'payment_terms' => trim($data['payment_terms'] ?? ''),
                'min_order'     => (float)($data['min_order'] ?? 0),
                'notes'         => trim($data['notes'] ?? ''),
                'active'        => true,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ];
        }

        DataStore::saveSuppliers($suppliers);
        Logger::info("Supplier saved: {$name} by " . Auth::user());
        View::json(['success' => true]);
    }

    public function delete() {
        require_once SRC . '/lib/DataStore.php';

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = $data['id'] ?? '';

        if (!$id) {
            View::json(['success' => false, 'error' => 'ID липсва'], 400);
            return;
        }

        $suppliers = DataStore::getSuppliers();
        $suppliers = array_values(array_filter($suppliers, function($s) use ($id) {
            return $s['id'] !== $id;
        }));

        DataStore::saveSuppliers($suppliers);
        Logger::info("Supplier deleted: {$id} by " . Auth::user());
        View::json(['success' => true]);
    }
}
