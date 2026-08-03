-- =====================================================================
-- Dispo / Диспетчеризация — Schema Migration
-- Таблица заказов клиентов, которую заполняет отдел Dispo (см. запрос:
-- переезд Excel-таблицы заказов клиентов в дашборд).
--
-- Применить на проде: mysql -u <user> -p <db> < dispo_schema.sql
-- (локально применяется к базе portusapp1).
--
-- Замечания по архитектуре:
--  - gesamt — вычисляемая колонка (anzahl * preis), не может разойтись
--    с фактическими значениями (аналогично sku_active в inventory_items).
--  - highlighted — ручной тоггл "важно/особое" на строке (красная подсветка
--    ячеек Ware/Cont.nummer на фронте), без автоматической логики.
--  - Доступ к модулю: admin ИЛИ сотрудник отдела, чьё название содержит
--    "dispo" (см. Dispo::canUseDispo() в classDispo.php).
-- =====================================================================

CREATE TABLE IF NOT EXISTS dispo_orders (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    status            ENUM('in_bearbeitung','erledigt','teil_erledigt','abgerechnet','teil_abgerechnet','storno') NOT NULL DEFAULT 'in_bearbeitung',
    von               DATE           NULL,
    bis               DATE           NULL,
    kunde             VARCHAR(255)   NOT NULL,
    dienstleistung    VARCHAR(100)   NULL,
    auftrag           VARCHAR(500)   NULL,
    pos_nr            VARCHAR(100)   NULL,
    cont_nummer       VARCHAR(100)   NULL,
    ware              VARCHAR(255)   NULL,
    anzahl            DECIMAL(12,3)  NOT NULL DEFAULT 0,
    preis             DECIMAL(12,2)  NOT NULL DEFAULT 0,
    gesamt            DECIMAL(14,2)  GENERATED ALWAYS AS (anzahl * preis) STORED,
    eingang_rechnung  DATE           NULL,
    bemerkungen       TEXT           NULL,
    highlighted       TINYINT(1)     NOT NULL DEFAULT 0,
    created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by        INT            NULL,
    updated_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by        INT            NULL,
    INDEX idx_dispo_status (status),
    INDEX idx_dispo_von (von),
    INDEX idx_dispo_kunde (kunde),
    CONSTRAINT fk_dispo_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_dispo_updated FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
