-- Миграция: замена email на username в таблице users.
--
-- Контекст: вход и все экраны модуля "Пользователи" переведены с email
-- на имя пользователя (benutzername), например "pavel kinsfator".
-- Таблица drivers/fahrer НЕ затрагивается — там email остаётся.
--
-- ВАЖНО: сделайте резервную копию БД перед выполнением.
--   mysqldump -u <user> -p <db_name> > backup.sql
--
-- БД из .env: DB_NAME (локально portusapp1 / в Docker Portus_Managemend).

-- 1) Переименовать колонку email -> username (данные и индексы сохраняются).
--    Если у email был UNIQUE-индекс, он автоматически переносится на username.
ALTER TABLE users
    CHANGE COLUMN email username VARCHAR(255) NOT NULL;

-- 2) (Опционально, но рекомендуется) Заполнить username реальными именами
--    вида "pavel kinsfator" вместо старых email-адресов.
--
--    ВНИМАНИЕ: username должен быть уникальным. Если у двух сотрудников
--    совпадают имя+фамилия, этот UPDATE упадёт на UNIQUE-индексе —
--    тогда поправьте дубликаты вручную (например, добавьте отдел/цифру).
UPDATE users
SET username = TRIM(CONCAT(name, ' ', lastname));

-- 3) (Если на шаге 1 UNIQUE-индекс не перенёсся автоматически —
--    например, его не было — создайте его явно, чтобы логины не дублировались.)
--    Раскомментируйте при необходимости:
-- ALTER TABLE users ADD UNIQUE KEY uq_users_username (username);
