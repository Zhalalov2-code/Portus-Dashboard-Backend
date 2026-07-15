-- =====================================================================
-- Lagerverwaltung / Складской учёт (Inventory) — Schema Migration
-- MVP: Positionen, Bestand, Ein-/Ausbuchung, Korrekturen, Journal.
--
-- Автоматическое списание материалов при ремонте ТС (см. ТЗ tz_5814890350).
-- Применить на проде: mysql -u <user> -p <db> < inventory_schema.sql
-- (локально применяется к базе portusapp1).
--
-- Замечания по архитектуре:
--  - Единой таблицы ТС нет: есть раздельные lkw / chassi. Операции расхода
--    ссылаются на ТС полиморфно (vehicle_type + vehicle_id), как это уже
--    сделано в inspections / vehicle_documents. Поэтому FK на ТС отсутствует.
--  - Все количества хранятся с точностью DECIMAL(18,3).
--  - Журнал (inventory_transactions) — append-only: строки не удаляются и не
--    редактируются, исправления делаются обратными операциями/корректировками.
-- =====================================================================

-- 1) Единицы измерения ---------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_units (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(20)  NOT NULL,
    name          VARCHAR(50)  NOT NULL,
    `precision`   INT          NOT NULL DEFAULT 3,   -- знаков после запятой
    integer_only  TINYINT(1)   NOT NULL DEFAULT 0,   -- запрещать дробные значения
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_inventory_units_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO inventory_units (code, name, `precision`, integer_only, is_active)
SELECT * FROM (
    SELECT 'pcs' AS code, 'Stück' AS name, 0 AS p, 1 AS i, 1 AS a
    UNION ALL SELECT 'l',  'Liter',     3, 0, 1
    UNION ALL SELECT 'kg', 'Kilogramm', 3, 0, 1
    UNION ALL SELECT 'm',  'Meter',     3, 0, 1
) seed
WHERE NOT EXISTS (SELECT 1 FROM inventory_units);

-- 2) Категории складских позиций ----------------------------------------
CREATE TABLE IF NOT EXISTS inventory_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO inventory_categories (name, is_active, sort_order)
SELECT * FROM (
    SELECT 'Reifen' AS name, 1 AS a, 1 AS s
    UNION ALL SELECT 'Öle und Schmierstoffe', 1, 2
    UNION ALL SELECT 'Ersatzteile',           1, 3
    UNION ALL SELECT 'Anhängerteile',         1, 4
    UNION ALL SELECT 'AdBlue',                1, 5
    UNION ALL SELECT 'Sonstige Materialien',  1, 6
) seed
WHERE NOT EXISTS (SELECT 1 FROM inventory_categories);

-- 3) Складские позиции ---------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_items (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    sku               VARCHAR(100)   NULL,           -- код/артикул (уникален среди активных, если задан)
    name              VARCHAR(255)   NOT NULL,
    category_id       INT            NOT NULL,
    unit_id           INT            NOT NULL,
    description       TEXT           NULL,
    manufacturer      VARCHAR(255)   NULL,
    min_quantity      DECIMAL(18,3)  NULL,           -- минимальный остаток
    current_quantity  DECIMAL(18,3)  NOT NULL DEFAULT 0,
    is_active         TINYINT(1)     NOT NULL DEFAULT 1,
    created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by        INT            NULL,
    updated_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by        INT            NULL,
    -- Уникальность SKU только среди АКТИВНЫХ позиций (ТЗ §14.1). Виртуальный
    -- столбец = sku для активных и NULL для неактивных; UNIQUE допускает
    -- множество NULL, поэтому деактивированные позиции не конфликтуют, а два
    -- активных с одинаковым sku — конфликтуют. Бэкап к проверке skuTaken() в PHP.
    sku_active        VARCHAR(100)   GENERATED ALWAYS AS (IF(is_active = 1, sku, NULL)) VIRTUAL,
    INDEX idx_inventory_item_name (name),
    INDEX idx_inventory_item_sku (sku),
    INDEX idx_inventory_item_category (category_id),
    INDEX idx_inventory_item_active (is_active),
    UNIQUE KEY uq_inventory_item_sku_active (sku_active),
    CONSTRAINT fk_inventory_item_category FOREIGN KEY (category_id) REFERENCES inventory_categories(id),
    CONSTRAINT fk_inventory_item_unit     FOREIGN KEY (unit_id)     REFERENCES inventory_units(id),
    CONSTRAINT fk_inventory_item_created  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_item_updated  FOREIGN KEY (updated_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Журнал складских операций (append-only) ----------------------------
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type  ENUM('INITIAL_BALANCE','RECEIPT','CONSUMPTION','ADJUSTMENT_PLUS','ADJUSTMENT_MINUS') NOT NULL,
    item_id           INT            NOT NULL,
    vehicle_type      ENUM('lkw','chassi') NULL,     -- обязателен для CONSUMPTION
    vehicle_id        INT            NULL,
    vehicle_nummer    VARCHAR(100)   NULL,           -- денормализовано: остаётся стабильным в истории
    quantity          DECIMAL(18,3)  NOT NULL,       -- всегда > 0
    unit_id           INT            NOT NULL,
    quantity_before   DECIMAL(18,3)  NOT NULL,
    quantity_after    DECIMAL(18,3)  NOT NULL,
    user_id           INT            NULL,
    comment           TEXT           NULL,
    document_number   VARCHAR(100)   NULL,
    document_date     DATE           NULL,
    created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source            VARCHAR(50)    NOT NULL DEFAULT 'UI',   -- UI / API / MIGRATION
    correlation_id    VARCHAR(64)    NULL,
    INDEX idx_transaction_created_at (created_at),
    INDEX idx_transaction_item (item_id),
    INDEX idx_transaction_vehicle (vehicle_type, vehicle_id),
    INDEX idx_transaction_user (user_id),
    INDEX idx_transaction_type (transaction_type),
    CONSTRAINT fk_transaction_item FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    CONSTRAINT fk_transaction_unit FOREIGN KEY (unit_id) REFERENCES inventory_units(id),
    CONSTRAINT fk_transaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
