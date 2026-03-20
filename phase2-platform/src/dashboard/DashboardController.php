<?php
class DashboardController {
    public function index() {
        require_once SRC . '/lib/DataStore.php';

        $data = DataStore::getSummary();

        View::renderWithLayout('dashboard/index', [
            'pageTitle'  => 'Dashboard',
            'activePage' => 'dashboard',
            'stats'      => $data['stats'],
            'recent'     => $data['recent'],
            'syncLog'    => $data['syncLog'],
        ]);
    }
}
