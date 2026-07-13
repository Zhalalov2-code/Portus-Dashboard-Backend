-- Миграция: добавление колонки notizen для LKW и Chassi
-- Используется для хранения заметок о забронированных терминах
-- (TÜV, SP, рремонт и т.д.)
--
-- Применить на проде: mysql -u <user> -p <db> < add_notizen_column.sql
-- (локально уже применено к базе portusapp1).

ALTER TABLE lkw
    ADD COLUMN notizen TEXT DEFAULT NULL COMMENT 'Заметки про забронированные сервисные термины';

ALTER TABLE chassi
    ADD COLUMN notizen TEXT DEFAULT NULL COMMENT 'Заметки про забронированные сервисные термины';
