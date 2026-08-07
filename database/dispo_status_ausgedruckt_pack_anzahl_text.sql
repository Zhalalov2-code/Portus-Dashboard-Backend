-- =====================================================================
-- Dispo — Status "ausgedruckt", Pack-Anzahl als Freitext
--
-- status: neuer Zwischenstatus "Ausgedruckt" (zwischen in_bearbeitung
-- und erledigt — Auftrag wurde ausgedruckt).
-- pack_anzahl: bisher INT, jetzt VARCHAR — soll auch nicht-numerische
-- Angaben zulassen (z.B. "3 Paletten + 2 Kartons").
--
-- Anwenden: mysql -u <user> -p <db> < dispo_status_ausgedruckt_pack_anzahl_text.sql
-- =====================================================================

ALTER TABLE dispo_orders
    MODIFY COLUMN status ENUM('in_bearbeitung','ausgedruckt','erledigt','teil_erledigt','abgerechnet','teil_abgerechnet','storno')
    NOT NULL DEFAULT 'in_bearbeitung';

ALTER TABLE dispo_orders
    MODIFY COLUMN pack_anzahl VARCHAR(50) NULL;
