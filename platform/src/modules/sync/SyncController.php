<?php
class SyncController {
    public function index(): void {
        $logs = [];
        try { $logs = Firebase::getLogs(10); } catch (\Throwable $e) {}
        View::renderWithLayout('sync/index', ['pageTitle'=>'Синхронизация','activePage'=>'sync','syncLog'=>$logs,'lastSync'=>$logs[0]['date']??null,'settings'=>Settings::get()]);
    }
    public function run(): void {
        View::json(['success'=>false,'error'=>'Автоматичната синхронизация ще бъде добавена в следваща версия. Използвай Import Excel за ръчен импорт.']);
    }
}
