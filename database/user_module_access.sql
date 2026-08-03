-- =====================================================================
-- User Module Access — явные права доступа сотрудника к разделам дашборда.
--
-- Заменяет эвристику "department_name содержит X" (Leitende/Tech Kontrolle/
-- Dispo) для видимости разделов Fahrzeuge/Betrieb/Aufgaben/Lager/Dispo.
-- Konto (Profil) — единственный безусловный дефолт, гранта не требует.
-- admin видит всё независимо от грантов (см. Auth::hasModule()); Prüfer
-- ограничен отдельным жёстко зашитым правилом в ProtectedRoute.tsx —
-- обе группы вне этой таблицы.
--
-- Применить: mysql -u <user> -p <db> < user_module_access.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS user_module_access (
    user_id INT NOT NULL,
    module  ENUM('fahrzeuge','betrieb','aufgaben','lager','dispo') NOT NULL,
    PRIMARY KEY (user_id, module),
    CONSTRAINT fk_user_module_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Backfill: переносим СЕГОДНЯШНИЙ эффективный доступ, чтобы никто ---
-- --- из существующих сотрудников не потерял то, что видит сейчас.    ---

-- Fahrzeuge/Betrieb/Aufgaben — сегодня безусловный дефолт для всех, кроме Prüfer.
INSERT IGNORE INTO user_module_access (user_id, module)
SELECT id, 'fahrzeuge' FROM users WHERE LOWER(TRIM(role)) <> 'pruefer';

INSERT IGNORE INTO user_module_access (user_id, module)
SELECT id, 'betrieb' FROM users WHERE LOWER(TRIM(role)) <> 'pruefer';

INSERT IGNORE INTO user_module_access (user_id, module)
SELECT id, 'aufgaben' FROM users WHERE LOWER(TRIM(role)) <> 'pruefer';

-- Lager — зеркалит текущую Inventory::canUseWarehouse() /
-- src/utils/roles.ts::canUseWarehouse (admin | department_head | Leitende |
-- Tech Kontrolle | role=pruefer).
INSERT IGNORE INTO user_module_access (user_id, module)
SELECT u.id, 'lager'
FROM users u
LEFT JOIN departments d ON d.id = u.department_id
WHERE LOWER(TRIM(u.role)) IN ('admin', 'pruefer', 'department_head')
   OR LOWER(TRIM(COALESCE(d.name, ''))) LIKE '%leitend%'
   OR (LOWER(TRIM(COALESCE(d.name, ''))) LIKE '%tech%' AND LOWER(TRIM(COALESCE(d.name, ''))) LIKE '%kontroll%');

-- Dispo — зеркалит текущую Dispo::canUseDispo() / canUseDispo (admin | отдел содержит "dispo").
INSERT IGNORE INTO user_module_access (user_id, module)
SELECT u.id, 'dispo'
FROM users u
LEFT JOIN departments d ON d.id = u.department_id
WHERE LOWER(TRIM(u.role)) = 'admin'
   OR LOWER(TRIM(COALESCE(d.name, ''))) LIKE '%dispo%';
