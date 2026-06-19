<?php
// Automatische Bereinigung alter Urlaubseinträge.
//
// Aufbewahrungsregel: nur das laufende Jahr und das Vorjahr werden behalten
// (wird für die Resturlaubs-Berechnung in classVacations.php benötigt).
// Alles Ältere wird gelöscht, sobald ein neues Jahr beginnt.
//
// Beispiel: sobald wir in 2027 sind, werden alle Einträge mit
// YEAR(start_date) <= 2025 entfernt (2026 + 2027 bleiben erhalten).
//
// Einrichtung (einmalig, wie bei check_deadlines.php):
//   Windows-Aufgabenplanung -> Neue Aufgabe -> Trigger: täglich (z.B. 03:00 Uhr)
//   Aktion: php.exe C:\xampp\htdocs\portusApp1\cron\cleanup_old_vacations.php
//
// Achtung: Löschung ist endgültig. Vor dem ersten produktiven Einsatz ggf.
// die Tabelle "vacations" einmal sichern (mysqldump).

require_once __DIR__ . '/../api/config/db.php';

const RETENTION_YEARS = 2; // laufendes Jahr + Vorjahr bleiben erhalten

$db = DB::getInstance();

$currentYear = (int) date('Y');
$cutoffYear = $currentYear - RETENTION_YEARS; // alles <= diesem Jahr wird gelöscht

$countStmt = $db->prepare('SELECT COUNT(*) AS total FROM vacations WHERE YEAR(start_date) <= :cutoff');
$countStmt->bindValue(':cutoff', $cutoffYear);
$countStmt->execute();
$toDelete = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

if ($toDelete > 0) {
    $delStmt = $db->prepare('DELETE FROM vacations WHERE YEAR(start_date) <= :cutoff');
    $delStmt->bindValue(':cutoff', $cutoffYear);
    $delStmt->execute();
}

echo sprintf(
    "Urlaubsbereinigung abgeschlossen: %d Einträge aus Jahr(en) <= %d gelöscht (aktuelles Jahr: %d, Cutoff: %d).\n",
    $toDelete,
    $cutoffYear,
    $currentYear,
    $cutoffYear
);
