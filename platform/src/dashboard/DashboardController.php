<?php
class DashboardController {
    public function index(): void {
        try {
            $stats  = ProductDB::stats();
            $logs   = Firebase::getLogs(6);
            $recent = ProductDB::query([], '', 'asc', 1, 10)['products'];
        } catch (\Throwable $e) {
            Logger::error("Dashboard Firebase: " . $e->getMessage());
            $stats  = ['total'=>0,'withAsin'=>0,'notUploaded'=>0,'suppliers'=>0,'avgRez'=>0,'posRez'=>0,'negRez'=>0];
            $logs   = [];
            $recent = [];
        }
        $lastLog = $logs[0] ?? null;
        $stats['suppliers'] = ProductDB::realSupplierCount();

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
