<?php
/**
 * Напоминания о TÜV/SP: push водителю, за которым закреплена машина/прицеп,
 * если срок истекает в ближайшие N дней или уже просрочен.
 *
 * Запускать по расписанию раз в день, например (Linux crontab):
 *   0 7 * * * php /path/to/portusApp1/cron/tuv_reminders.php
 * Windows (Task Scheduler): php.exe C:\xampp\htdocs\portusApp1\cron\tuv_reminders.php
 *
 * Чтобы не слать одно и то же каждый день, отправляем только когда до срока
 * осталось ровно 30, 14, 7, 3, 1 день или в день истечения (0), либо один раз
 * в неделю после просрочки (по дню недели).
 */

require_once __DIR__ . '/../api/config/db.php';
require_once __DIR__ . '/../api/classes/ExpoPush.php';

$db = DB::getInstance();

// Пороговые дни до истечения, когда шлём напоминание.
$THRESHOLDS = [30, 14, 7, 3, 1, 0];

/** Разница в днях между сегодня и датой (месяц-год) — по последнему дню месяца. */
function monthsDiffDays($dateStr)
{
    if (!$dateStr) {
        return null;
    }
    $d = new DateTime($dateStr);
    // TÜV/SP указывается как месяц — считаем действительным до конца месяца.
    $end = new DateTime($d->format('Y-m-01'));
    $end->modify('last day of this month')->setTime(23, 59, 59);
    $now = new DateTime('now');
    $diff = (int) floor(($end->getTimestamp() - $now->getTimestamp()) / 86400);
    return $diff;
}

function shouldNotify($days, array $thresholds)
{
    if ($days === null) {
        return false;
    }
    if ($days < 0) {
        // Просрочено — напоминаем раз в неделю (по понедельникам).
        return (int) date('N') === 1;
    }
    return in_array($days, $thresholds, true);
}

$sent = 0;

// Грузовики
$lkws = $db->query('SELECT lkw_nummer, tuf, esp FROM lkw')->fetchAll(PDO::FETCH_ASSOC);
foreach ($lkws as $v) {
    foreach ([['TÜV', $v['tuf']], ['SP', $v['esp']]] as [$label, $date]) {
        $days = monthsDiffDays($date);
        if (!shouldNotify($days, $THRESHOLDS)) {
            continue;
        }
        $body = $days < 0
            ? "$label грузовика {$v['lkw_nummer']} просрочен"
            : ($days === 0
                ? "$label грузовика {$v['lkw_nummer']} истекает сегодня"
                : "$label грузовика {$v['lkw_nummer']} истекает через $days дн.");
        ExpoPush::toVehicleDriver($db, 'lkw', $v['lkw_nummer'], 'Напоминание о сроке', $body);
        $sent++;
    }
}

// Прицепы
$chassis = $db->query('SELECT chassi_nummer, tuf, esp FROM chassi')->fetchAll(PDO::FETCH_ASSOC);
foreach ($chassis as $v) {
    foreach ([['TÜV', $v['tuf']], ['SP', $v['esp']]] as [$label, $date]) {
        $days = monthsDiffDays($date);
        if (!shouldNotify($days, $THRESHOLDS)) {
            continue;
        }
        $body = $days < 0
            ? "$label прицепа {$v['chassi_nummer']} просрочен"
            : ($days === 0
                ? "$label прицепа {$v['chassi_nummer']} истекает сегодня"
                : "$label прицепа {$v['chassi_nummer']} истекает через $days дн.");
        ExpoPush::toVehicleDriver($db, 'chassi', $v['chassi_nummer'], 'Напоминание о сроке', $body);
        $sent++;
    }
}

echo "TÜV/SP reminders sent: $sent\n";
