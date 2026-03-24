<?php
class DashboardController {
    public function index(): void {
        $stats   = Firebase::getStats();
        $logs    = Firebase::getLogs(6);
        $recent  = array_slice(Firebase::getProducts(), 0, 10);
        $lastLog = $logs[0] ?? null;

        View::renderWithLayout('dashboard/index', [
            'pageTitle'  => 'Dashboard',
            'activePage' => 'dashboard',
            'stats'      => $stats,
            'logs'       => $logs,
            'recent'     => $recent,
            'lastSync'   => $lastLog['date'] ?? null,
        ]);
    }
}
