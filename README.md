# 🚀 Amazon FBM Automation Platform

Автоматизирана платформа за управление на доставчици, продукти и качване в Amazon FBM.

## 📋 Структура на проекта

```
amazon-fbm-automation/
├── docs/                          # Техническа документация
│   ├── TECHNICAL_SPEC.md
│   ├── CENTRAL_TABLE_SCHEMA.md
│   └── PRICING_RULES.md
├── phase1-google-apps-script/     # Фаза 1: Организация на документи
│   ├── Code.gs
│   └── README.md
├── phase2-product-management/     # Фаза 2: Обработка на продукти
│   ├── sync_products.py
│   ├── pricing_calculator.py
│   └── README.md
└── phase3-amazon-upload/          # Фаза 3: Качване в Amazon
    ├── amazon_agent.py
    └── README.md
```

## 🗺️ Фази на разработка

| Фаза | Описание | Технологии | Статус |
|------|----------|-----------|--------|
| **Фаза 1** | Организация на документи от доставчици | Google Apps Script | 🟡 В разработка |
| **Фаза 2A** | Сравнение и актуализация на продукти | Python + Google Sheets API | 🔴 Планирано |
| **Фаза 2B** | Изчисление на крайна цена | Python | 🔴 Планирано |
| **Фаза 3** | Качване в Amazon | Python + SP-API | 🔴 Планирано |

## 🔑 Необходими акаунти и достъпи

- [ ] Google Workspace акаунт с Gmail + Drive
- [ ] Google Cloud Project с APIs: Sheets, Drive, Gmail
- [ ] Amazon Seller Central акаунт
- [ ] Amazon SP-API credentials
