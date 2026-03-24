<?php
class DashboardController {
    public function index() {
        require_once SRC . '/lib/DataStore.php';

        $data    = DataStore::getSummary();
        $stats   = $data['stats'];

        // Calculate avg/pos/neg Резултат (column Y)
        $products = DataStore::getProducts();
        $rezValues = array_filter(
            array_map(fn($p) => isset($p['Резултат']) && $p['Резултат'] !== '' ? (float)$p['Резултат'] : null, $products),
            fn($v) => $v !== null
        );

        $stats['avg_rezultat'] = count($rezValues) > 0 ? array_sum($rezValues) / count($rezValues) : 0;
        $stats['pos_rezultat'] = count(array_filter($rezValues, fn($v) => $v > 0));
        $stats['neg_rezultat'] = count(array_filter($rezValues, fn($v) => $v <= 0));

        View::renderWithLayout('dashboard/index', [
            'pageTitle'  => 'Dashboard',
            'activePage' => 'dashboard',
            'stats'      => $stats,
            'recent'     => $data['recent'],
            'syncLog'    => $data['syncLog'],
        ]);
    }
}
