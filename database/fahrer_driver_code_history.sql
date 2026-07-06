-- Драйвер-коды вместо email + история назначений LKW/Chassi.
-- Порядок важен: сначала добавляем/заполняем driver_code, затем убираем email.
-- Запускать ВМЕСТЕ с деплоем нового кода бэкенда (старый код ещё ссылается на email).

-- 1) Новая колонка с кодом водителя (формат DR-XXXXXX).
ALTER TABLE fahrer
  ADD COLUMN driver_code VARCHAR(20) NULL AFTER id_fahrer;

-- 2) Бэкфилл кодов для уже существующих водителей (случайные, без 0/O/1/I через MD5-hex).
UPDATE fahrer
  SET driver_code = CONCAT('DR-', UPPER(SUBSTR(MD5(CONCAT(id_fahrer, RAND(), UUID())), 1, 6)))
  WHERE driver_code IS NULL OR driver_code = '';

-- 3) Уникальность кода (по нему логинится водитель).
ALTER TABLE fahrer
  ADD CONSTRAINT uq_fahrer_driver_code UNIQUE (driver_code);

-- 4) Email больше не используется — удаляем колонку.
ALTER TABLE fahrer
  DROP COLUMN email;

-- 5) История: какой водитель работал с какой машиной/прицепом и в какой период.
CREATE TABLE IF NOT EXISTS vehicle_history (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_type  ENUM('lkw','chassi') NOT NULL,
  vehicle_nummer VARCHAR(100) NOT NULL,
  id_fahrer     INT NULL,
  fahrer_name   VARCHAR(200) NULL,
  started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at      DATETIME NULL,
  INDEX idx_vehicle (vehicle_type, vehicle_nummer),
  INDEX idx_fahrer (id_fahrer),
  INDEX idx_active (vehicle_type, vehicle_nummer, ended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6) (Опционально) Затравка истории из текущих назначений: для каждого водителя,
--    у которого сейчас назначен LKW/Chassi, открываем активную запись «с этого момента».
INSERT INTO vehicle_history (vehicle_type, vehicle_nummer, id_fahrer, fahrer_name, started_at)
  SELECT 'lkw', f.lkw, f.id_fahrer, CONCAT(f.name, ' ', f.lastname), NOW()
  FROM fahrer f
  WHERE f.lkw IS NOT NULL AND f.lkw <> '';

INSERT INTO vehicle_history (vehicle_type, vehicle_nummer, id_fahrer, fahrer_name, started_at)
  SELECT 'chassi', f.chassi, f.id_fahrer, CONCAT(f.name, ' ', f.lastname), NOW()
  FROM fahrer f
  WHERE f.chassi IS NOT NULL AND f.chassi <> '';
