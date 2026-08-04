-- =====================================================================
-- Dispo — Pack-Anzahl (Paletten/Packstücke), Gewicht
--
-- pack_anzahl: Anzahl Paletten/Packstücke — eigenständig von "anzahl"
-- (das ist die Menge für die Preisberechnung, Anzahl*Preis=Gesamt).
-- gewicht: Gewicht in kg, mit Nachkommastellen.
--
-- Anwenden: mysql -u <user> -p <db> < dispo_pack_gewicht_container.sql
-- =====================================================================

ALTER TABLE dispo_orders
    ADD COLUMN pack_anzahl INT NULL AFTER anzahl,
    ADD COLUMN gewicht DECIMAL(10,2) NULL AFTER pack_anzahl;
