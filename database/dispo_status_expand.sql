-- =====================================================================
-- Dispo — Erweiterung der Status-Werte
--
-- Fügt drei weitere Status hinzu: Erledigt, Teil erledigt, Teil abgerechnet.
-- Nur für Installationen nötig, bei denen dispo_schema.sql bereits mit dem
-- alten 3-Werte-Enum ('in_bearbeitung','abgerechnet','storno') angewendet
-- wurde — bei einer frischen Installation steht der volle Enum bereits in
-- dispo_schema.sql.
--
-- Anwenden: mysql -u <user> -p <db> < dispo_status_expand.sql
-- =====================================================================

ALTER TABLE dispo_orders
    MODIFY COLUMN status ENUM('in_bearbeitung','erledigt','teil_erledigt','abgerechnet','teil_abgerechnet','storno')
    NOT NULL DEFAULT 'in_bearbeitung';
