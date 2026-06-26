-- Статусы осей стали свободным текстом (их заполняет контролёр),
-- поэтому расширяем колонки с varchar(50) до varchar(255).
-- Применить на проде: mysql -u <user> -p <db> < widen_axle_columns.sql

ALTER TABLE lkw
    MODIFY achse1_links  VARCHAR(255) NOT NULL DEFAULT 'OK',
    MODIFY achse1_rechts VARCHAR(255) NOT NULL DEFAULT 'OK',
    MODIFY achse2_links  VARCHAR(255) NOT NULL DEFAULT 'OK',
    MODIFY achse2_rechts VARCHAR(255) NOT NULL DEFAULT 'OK',
    MODIFY achse3_links  VARCHAR(255) NOT NULL DEFAULT 'OK',
    MODIFY achse3_rechts VARCHAR(255) NOT NULL DEFAULT 'OK';

ALTER TABLE chassi
    MODIFY achse1_links  VARCHAR(255) NOT NULL DEFAULT 'OK',
    MODIFY achse1_rechts VARCHAR(255) NOT NULL DEFAULT 'OK',
    MODIFY achse2_links  VARCHAR(255) NOT NULL DEFAULT 'OK',
    MODIFY achse2_rechts VARCHAR(255) NOT NULL DEFAULT 'OK',
    MODIFY achse3_links  VARCHAR(255) NOT NULL DEFAULT 'OK',
    MODIFY achse3_rechts VARCHAR(255) NOT NULL DEFAULT 'OK';
