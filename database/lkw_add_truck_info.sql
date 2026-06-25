-- Миграция: доп. информация о грузовике (LKW).
-- ADR (можно ли возить опасные грузы), огнетушитель и статус 6 колёс
-- (3 оси × левая/правая). Статус: 'OK' или 'Auf Ersatz' (на замену).
--
-- Применить на проде: mysql -u <user> -p <db> < lkw_add_truck_info.sql
-- (локально уже применено к базе portusapp1).

ALTER TABLE lkw
    ADD COLUMN adr            TINYINT(1)  NOT NULL DEFAULT 0,
    ADD COLUMN feuerloescher  TINYINT(1)  NOT NULL DEFAULT 0,
    ADD COLUMN achse1_links   VARCHAR(50) NOT NULL DEFAULT 'OK',
    ADD COLUMN achse1_rechts  VARCHAR(50) NOT NULL DEFAULT 'OK',
    ADD COLUMN achse2_links   VARCHAR(50) NOT NULL DEFAULT 'OK',
    ADD COLUMN achse2_rechts  VARCHAR(50) NOT NULL DEFAULT 'OK',
    ADD COLUMN achse3_links   VARCHAR(50) NOT NULL DEFAULT 'OK',
    ADD COLUMN achse3_rechts  VARCHAR(50) NOT NULL DEFAULT 'OK';
