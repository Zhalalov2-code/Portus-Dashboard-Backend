-- Новые функции для водителей: документы транспорта, предрейсовый осмотр,
-- сообщения о неисправностях. Языковые/пароль-фичи схему не меняют.

-- 1) Документы транспорта (PDF/фото) — прикрепляет админ, видит водитель.
CREATE TABLE IF NOT EXISTS vehicle_documents (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_type   ENUM('lkw','chassi') NOT NULL,
  vehicle_nummer VARCHAR(100) NOT NULL,
  title          VARCHAR(200) NOT NULL,
  file_name      VARCHAR(255) NOT NULL,
  mime           VARCHAR(100) NULL,
  uploaded_by    INT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vehicle (vehicle_type, vehicle_nummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Предрейсовый осмотр — заполняет водитель перед выездом.
CREATE TABLE IF NOT EXISTS inspections (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  id_fahrer      INT NULL,
  fahrer_name    VARCHAR(200) NULL,
  vehicle_type   ENUM('lkw','chassi') NOT NULL,
  vehicle_nummer VARCHAR(100) NOT NULL,
  items          TEXT NOT NULL,           -- JSON: [{key,label,ok}]
  all_ok         TINYINT(1) NOT NULL DEFAULT 1,
  comment        TEXT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vehicle (vehicle_type, vehicle_nummer),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Сообщения о неисправностях — водитель сообщает, админ решает.
CREATE TABLE IF NOT EXISTS fault_reports (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  id_fahrer      INT NULL,
  fahrer_name    VARCHAR(200) NULL,
  vehicle_type   ENUM('lkw','chassi') NOT NULL,
  vehicle_nummer VARCHAR(100) NOT NULL,
  description    TEXT NOT NULL,
  severity       ENUM('can_drive','cannot_drive') NOT NULL DEFAULT 'can_drive',
  photo          VARCHAR(255) NULL,
  status         ENUM('open','resolved') NOT NULL DEFAULT 'open',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at    DATETIME NULL,
  INDEX idx_status (status),
  INDEX idx_vehicle (vehicle_type, vehicle_nummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
