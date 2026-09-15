# CrashAlert

**[RU]** Монитор фатальных ошибок WordPress: мгновенные алерты в **Telegram, email и вебхук**, атрибуция виновника (плагин/тема/ядро с версией), человеческое объяснение ошибки, сообщение о восстановлении и история сбоев. Узнай о падении раньше клиента.

**[EN]** A WordPress fatal-error monitor: instant alerts to **Telegram, email and webhook**, culprit attribution (plugin/theme/core with version), plain-language explanations, a recovery notice and failure history. Know about a crash before your clients do.

🔗 **[Лендинг / Landing](https://yodzira.github.io/crashalert/)** · [**Скачать бесплатно / Download free**](https://github.com/Yodzira/crashalert/releases/latest/download/crashalert.zip) · [**Купить Pro — 2 990 ₽/год**](https://yodsira.com/ru/buy/crashalert)

---

## Зачем / Why

WordPress с версии 5.2 сам шлёт письмо о фатальной ошибке — но оно приходит владельцу сайта, легко теряется в спаме, содержит сырой стектрейс, молчит о повторах и никогда не сообщает, что сайт снова работает.

**CrashAlert закрывает ровно эти дыры:**

- 🔴 **Алерты в Telegram** — быстрее любого email; email и произвольный вебхук — тоже.
- 🏷 **Атрибуция виновника**: «плагин Super Slider v2.1», а не безымянный стектрейс.
- 🗣 **Человеческие объяснения**: «Call to undefined function» → «конфликт версий — откатите последнее обновление», RU и EN.
- 🟢 **Сообщение о восстановлении**: «сайт снова работает, был недоступен ~4 минуты».
- 🔁 **Рейт-лимит**: 500 одинаковых ошибок в минуту = один алерт с пометкой ×500.
- 🕘 **История сбоев** с таймлайном, фильтрами и «что изменилось за 24 ч до падения».
- 🛡 **Приватность**: IP не хранятся, стектрейсы санитизируются (относительные пути, без аргументов), ничего не отправляется третьим лицам.
- 🧹 **Чистый uninstall**: таблицы, опции, кроны и файлы удаляются полностью.

## Установка / Install

1. Скачайте [`crashalert.zip`](https://github.com/Yodzira/crashalert/releases/latest/download/crashalert.zip) (всегда последняя версия)
2. WP-админка → **Плагины → Добавить новый → Загрузить плагин** → zip → Активировать
3. CrashAlert → Settings → вставьте Telegram bot token и chat id → **Send test** — готово

## Требования / Requirements

- WordPress 6.0+ (протестировано до 7.1), PHP 7.4+

## Качество / Quality

- PHPUnit: 37 тестов, 74 assertions ✅
- Официальный Plugin Checker: 0 errors (release build) ✅
- Конфликт-матрица: WooCommerce + LiteSpeed Cache + Autoptimize ✅
- Perf: healthy request без дополнительных SQL-запросов; алерты уходят через cron-очередь, не блокируя страницу
- Собственный фатал внутри монитора невозможен: все пути перехвата защищены (см. `qa.md`)

## CrashAlert Pro (опционально)

- **PHP deprecation-радар**: собирает deprecated-варнинги и предупреждает, что сломается в будущих версиях PHP
- **Неограниченные каналы**: несколько Telegram-чатов и вебхуков
- **Недельный health-отчёт**: топ виновников, версии-деградации

**[Купить Pro — 2 990 ₽/год](https://yodsira.com/ru/buy/crashalert)** · лицензия на 1 сайт. Pro — плагин-компаньон: ставится поверх бесплатной версии, ничего перенастраивать не надо. Бесплатная версия остаётся полноценной и не ограничена по срокам.

## Лицензия / License

GPL-2.0-or-later (совместимо с WordPress).
