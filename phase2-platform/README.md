# AMZ Retail Platform — Phase 2

PHP уеб платформа за управление на Amazon FBM продукти, синхронизация с Google Sheets и ценообразуване.

---

## Бърз старт (Server Setup)

### 1. Качи файловете на сървъра

```bash
# SSH вход
ssh idev7euj@amz-retail.tnsoft.eu

# Отиди в директорията
cd /home/idev7euj/amz-retail.tnsoft.eu
```

### 2. Gmail App Password → .env

**Преди** да стартираш `setup.php`, генерирай Gmail App Password:

1. Влез в [myaccount.google.com](https://myaccount.google.com)
2. **Security** → **2-Step Verification** (трябва да е включена!)
3. Scroll надолу → **App passwords**
4. `Select app: Mail` | `Select device: Other` → напиши `AMZ Retail` → **Generate**
5. Получаваш 16-символна парола: `xxxx xxxx xxxx xxxx`

### 3. Стартирай setup.php (интерактивен)

```bash
cd /home/idev7euj/amz-retail.tnsoft.eu
php setup.php
```

Скриптът ще:
- Създаде `.env` от `.env.example` (ако не съществува)
- Попита за **Gmail App Password** → записва го в `SMTP_PASS`
- Създаде нужните директории (`data/`, `data/logs/`, `data/cache/`)
- Създаде **първия admin потребител**

Пример:
```
✓ Created .env from .env.example

===========================================
 AMZ Retail Platform — Setup v1.2
===========================================

──────────────────────────────────────────
 Gmail App Password (SMTP_PASS)
──────────────────────────────────────────
  Въведи Gmail App Password: abcdabcdabcdabcd
✓ SMTP_PASS записан в .env

──────────────────────────────────────────
 Admin потребител
──────────────────────────────────────────
Enter admin email: admin@example.com
Enter admin password (min 8 chars): MyPass123!

✅ Admin създаден: admin@example.com
✅ Отвори: https://amz-retail.tnsoft.eu
```

### 4. Влез в платформата

Отвори [https://amz-retail.tnsoft.eu](https://amz-retail.tnsoft.eu) и влез с имейла и паролата от `setup.php`.

### 5. Покани потребители

- Влез като Admin → в лявото меню → **Покани потребител** (или `/invite`)
- Въведи имейл → потребителят получава имейл с 24-часов линк за активиране

---

## Ръчна конфигурация на .env

Ако предпочиташ да не ползваш `setup.php`:

```bash
cp .env.example .env
nano .env
```

```env
APP_URL=https://amz-retail.tnsoft.eu
APP_DEBUG=false

# SMTP — Gmail App Password (без интервали)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=tnsoftsales@gmail.com
SMTP_PASS=abcdabcdabcdabcd        ← твоята 16-символна парола
SMTP_FROM=tnsoftsales@gmail.com
SMTP_FROM_NAME=AMZ Retail

# Google Drive / Sheets
GOOGLE_DRIVE_FOLDER_ID=100T4KgyVIXhKlJczQv7DR9CJlV27DbUx
GOOGLE_SHEET_ID=
```

---

## Тест на имейл (SMTP)

След вход → **Настройки** → секция **Тест на имейл (SMTP)** → въведи имейл → **Изпрати тест**.

Или директно от cURL:
```bash
curl -X POST https://amz-retail.tnsoft.eu/api/test-email \
     -b "amz_session=SESSION_ID" \
     -d "to=you@example.com"
```

---

## Структура на платформата

```
phase2-platform/
├── index.php               # Front controller (router)
├── .htaccess               # Apache rewrite rules + security
├── setup.php               # Интерактивен setup (admin + .env)
├── .env.example            # Шаблон за .env
├── assets/
│   ├── css/app.css
│   └── js/app.js
├── src/
│   ├── config/config.php   # Зарежда .env + autoloader
│   ├── lib/
│   │   ├── Router.php      # URL routing (поддържа /path/:param)
│   │   ├── Auth.php        # Session-based auth
│   │   ├── UserStore.php   # JSON-базирано хранилище за потребители
│   │   ├── Mailer.php      # SMTP over fsockopen (без ext зависимости)
│   │   ├── DataStore.php   # JSON cache за продукти, лог, настройки
│   │   ├── View.php        # Template renderer
│   │   ├── Session.php     # Session wrapper
│   │   └── Logger.php      # File logger
│   ├── auth/AuthController.php
│   ├── dashboard/DashboardController.php
│   ├── modules/
│   │   ├── products/
│   │   ├── sync/
│   │   ├── pricing/
│   │   └── settings/
│   ├── api/ApiController.php
│   └── views/
├── cron/sync_products.py   # Python sync (Phase 2A + 2B)
└── data/                   # Runtime data (не се качва в git)
    ├── users.json
    ├── settings.json
    ├── sync_log.json
    └── cache/products.json
```

---

## Маршрути (Routes)

| Метод | URL | Описание |
|-------|-----|----------|
| GET | `/` | Страница за вход |
| POST | `/` | Вход (login action) |
| GET | `/register/:token` | Активиране на акаунт от покана |
| GET | `/forgot-password` | Забравена парола |
| GET/POST | `/reset-password/:token` | Нулиране на парола |
| GET | `/dashboard` | Главна страница |
| GET | `/products` | Списък продукти |
| GET | `/sync` | Синхронизация |
| GET | `/pricing` | Ценообразуване |
| GET | `/settings` | Настройки |
| GET/POST | `/invite` | *(Admin)* Покани/управлявай потребители |
| POST | `/invite/delete` | *(Admin)* Изтрий потребител |
| POST | `/invite/resend` | *(Admin)* Изпрати поканата повторно |
| POST | `/api/test-email` | *(Admin)* Тест на SMTP |

---

## Cron job

```bash
# Ежедневна синхронизация в 08:00
0 8 * * * python3 /home/idev7euj/amz-retail.tnsoft.eu/cron/sync_products.py >> /home/idev7euj/amz-retail.tnsoft.eu/data/logs/cron.log 2>&1
```

---

## Изисквания

- PHP 8.1+ (fsockopen, json, session)
- Apache с mod_rewrite
- Python 3.8+ (за cron sync)
- Gmail акаунт с 2FA + App Password
