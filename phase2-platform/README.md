# AMZ Retail Platform

Вътрешна платформа за управление на доставчици, продукти и ценообразуване за Amazon FBM.

## Структура

```
amz-retail/
├── index.php              ← Front controller (router)
├── .htaccess              ← Apache rules
├── setup.php              ← Инсталационен скрипт
├── .env.example           ← Шаблон за .env
├── assets/
│   ├── css/app.css        ← Design system
│   ├── js/app.js          ← Frontend JS
│   └── video/login-bg.mp4 ← Video за login (качи ръчно!)
├── src/
│   ├── config/config.php  ← Конфигурация + autoloader
│   ├── lib/               ← Router, Session, Auth, View, DataStore, Logger
│   ├── auth/              ← Login/logout
│   ├── dashboard/         ← Главен екран
│   ├── modules/
│   │   ├── products/      ← Списък продукти
│   │   ├── sync/          ← Синхронизация
│   │   ├── pricing/       ← Калкулатор
│   │   └── settings/      ← Настройки
│   ├── api/               ← JSON API endpoints
│   └── views/             ← HTML templates
├── cron/
│   └── sync_products.py   ← Модул 2A + 2B (Python)
└── data/                  ← Runtime данни (не се качва в git)
    ├── cache/products.json
    ├── settings.json
    └── sync_log.json
```

## Инсталация на Superhosting

1. Качи съдържанието на `amz-retail/` в `public_html/amz-retail/`
2. Качи видеото в `assets/video/login-bg.mp4`
3. Качи `src/config/google-credentials.json`
4. Стартирай: `php setup.php` (SSH) или ръчно обнови `.env`
5. Инсталирай Python пакетите: `pip3 install gspread google-auth openpyxl pandas --user`

## Cron job (Superhosting cPanel)

```
0 8 * * * python3 /home/USERNAME/public_html/amz-retail/cron/sync_products.py
```

## URLs

| URL | Описание |
|-----|---------|
| `/` | Login страница |
| `/dashboard` | Главен екран |
| `/products` | Списък продукти |
| `/sync` | Синхронизация |
| `/pricing` | Ценови калкулатор |
| `/settings` | Настройки |
