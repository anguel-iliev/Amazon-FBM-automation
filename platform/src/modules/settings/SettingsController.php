<?php
class SettingsController {
    public function index(): void { View::redirect('/settings/vat'); }

    private function render(string $view, string $title, string $active): void {
        View::renderWithLayout("settings/{$view}", ['pageTitle'=>$title,'activePage'=>$active,'settings'=>Settings::get()]);
    }

    public function vat(): void         { $this->render('vat',          'Настройки — ДДС',        'settings'); }
    public function prices(): void      { $this->render('prices',       'Настройки — Цени',       'settings'); }
    public function formulas(): void    { $this->render('formulas',     'Настройки — Формули',    'settings'); }
    public function integrations(): void{ $this->render('integrations', 'Настройки — Интеграции', 'settings'); }
    public function system(): void      { $this->render('system',       'Настройки — Системни',   'settings'); }

    public function save(): void {
        $current = Settings::get();
        $tab     = $_POST['tab'] ?? 'vat';
        // Merge posted data into settings
        foreach ($_POST as $k => $v) {
            if ($k === 'tab') continue;
            $current[$k] = $v;
        }
        Settings::save($current);
        Logger::info("Settings saved: tab={$tab}");
        View::json(['success'=>true]);
    }
}
