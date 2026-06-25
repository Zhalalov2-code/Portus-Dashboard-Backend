-- Миграция (2-й раунд): поле A-Schild (A-табличка) для LKW
-- и полный набор инфо-полей для Chassi (как у LKW).
--
-- A-Schild — есть ли у машины предупреждающая табличка (Ja/Nein).
-- Для Chassi добавляем ADR, A-Schild и статус 6 колёс (3 оси × левая/правая,
-- 'OK' | 'Auf Ersatz'). Огнетушитель у Chassi НЕ нужен (есть только у LKW).
--
-- Применить на проде: mysql -u <user> -p <db> < add_a_schild_and_chassi_info.sql
-- (локально уже применено к базе portusapp1).

-- LKW: только A-Schild (остальные поля уже есть из 1-го раунда).
ALTER TABLE lkw
    ADD COLUMN a_schild TINYINT(1) NOT NULL DEFAULT 0 AFTER adr;

-- Chassi: весь набор инфо-полей.
ALTER TABLE chassi
    ADD COLUMN adr            TINYINT(1)  NOT NULL DEFAULT 0,
    ADD COLUMN a_schild       TINYINT(1)  NOT NULL DEFAULT 0,
    ADD COLUMN achse1_links   VARCHAR(50) NOT NULL DEFAULT 'OK',
    ADD COLUMN achse1_rechts  VARCHAR(50) NOT NULL DEFAULT 'OK',
    ADD COLUMN achse2_links   VARCHAR(50) NOT NULL DEFAULT 'OK',
    ADD COLUMN achse2_rechts  VARCHAR(50) NOT NULL DEFAULT 'OK',
    ADD COLUMN achse3_links   VARCHAR(50) NOT NULL DEFAULT 'OK',
    ADD COLUMN achse3_rechts  VARCHAR(50) NOT NULL DEFAULT 'OK';
