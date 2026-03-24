<?php
/**
 * Settings — reads/writes data/settings.json
 * Замества DataStore::getSettings() за всички контролери
 */
class Settings {

    private static ?array $cache = null;

    public static function get(): array {
        if (static::$cache !== null) return static::$cache;
        $file = DATA_DIR . '/settings.json';
        if (!file_exists($file)) {
            static::$cache = static::defaults();
            return static::$cache;
        }
        $saved = json_decode(file_get_contents($file), true) ?? [];
        static::$cache = array_merge(static::defaults(), $saved);
        // Merge marketplace defaults
        foreach (static::defaults()['marketplaces'] as $code => $def) {
            if (!isset(static::$cache['marketplaces'][$code])) {
                static::$cache['marketplaces'][$code] = $def;
            }
        }
        return static::$cache;
    }

    public static function save(array $settings): void {
        file_put_contents(
            DATA_DIR . '/settings.json',
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
        static::$cache = null;
    }

    private static function defaults(): array {
        return [
            'marketplaces' => [
                'DE' => ['vat'=>0.19,'amazon_fee'=>0.15,'shipping'=>4.50,'fbm_fee'=>1.00,'min_margin'=>0.15,'active'=>true],
                'FR' => ['vat'=>0.20,'amazon_fee'=>0.15,'shipping'=>5.00,'fbm_fee'=>1.00,'min_margin'=>0.15,'active'=>true],
                'IT' => ['vat'=>0.22,'amazon_fee'=>0.15,'shipping'=>5.50,'fbm_fee'=>1.00,'min_margin'=>0.15,'active'=>true],
                'ES' => ['vat'=>0.21,'amazon_fee'=>0.15,'shipping'=>5.50,'fbm_fee'=>1.00,'min_margin'=>0.15,'active'=>true],
                'NL' => ['vat'=>0.21,'amazon_fee'=>0.15,'shipping'=>4.50,'fbm_fee'=>1.00,'min_margin'=>0.15,'active'=>true],
                'PL' => ['vat'=>0.23,'amazon_fee'=>0.15,'shipping'=>6.00,'fbm_fee'=>1.00,'min_margin'=>0.15,'active'=>false],
                'SE' => ['vat'=>0.25,'amazon_fee'=>0.15,'shipping'=>7.00,'fbm_fee'=>1.00,'min_margin'=>0.15,'active'=>false],
            ],
            'min_margin'      => 0.15,
            'sync_auto'       => true,
            'google_sheet_id' => '',
            'drive_folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', ''),
        ];
    }
}
