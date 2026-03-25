<?php
class DashboardController {
    public function index(): void {
        try {
            $stats  = ProductDB::stats();     // reads local cache — instant
            $logs   = Firebase::getLogs(6);      // small log data — ok
            $recent = ProductDB::query([], '', 'asc', 1, 10)['products'];
        } catch (\Throwable $e) {
            Logger::error("Dashboard Firebase: " . $e->getMessage());
            $stats  = ['total'=>0,'withAsin'=>0,'notUploaded'=>0,'suppliers'=>0,'avgRez'=>0,'posRez'=>0,'negRez'=>0];
            $logs   = [];
            $recent = [];
        }
        $lastLog = $logs[0] ?? null;

        // Override supplier count with local file (authoritative 17)
        $suppFile = DATA_DIR . '/suppliers.json';
        if (file_exists($suppFile)) {
            $sl = json_decode(file_get_contents($suppFile), true) ?? [];
            $stats['suppliers'] = count(array_filter($sl, fn($s) => $s['active'] ?? true));
        }

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
