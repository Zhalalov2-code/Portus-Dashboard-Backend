<?php
// Скрипт проверки дедлайнов задач — запускать по расписанию (например,
// каждые 15-30 минут через Планировщик заданий Windows / cron).
//
// Логика:
//   - "Срок приближается": due_date в пределах ближайших 24 часов,
//     задача ещё не завершена/закрыта/отменена, deadline_notified = 0.
//   - "Просрочена": due_date уже в прошлом, задача ещё не завершена/
//     закрыта/отменена, overdue_notified = 0.
//
// Флаги deadline_notified/overdue_notified предотвращают повторную
// отправку одного и того же уведомления при следующих запусках.

require_once __DIR__ . '/../api/config/db.php';
require_once __DIR__ . '/../api/classes/EmailNotifier.php';

$db = DB::getInstance();

$activeStatuses = ['new', 'in_progress', 'clarification'];
$placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));

function notifyUsers($db, $task, $type, $message)
{
    $userIds = [];
    if (!empty($task['assignee_id'])) {
        $userIds[] = $task['assignee_id'];
    }
    if (!empty($task['creator_id'])) {
        $userIds[] = $task['creator_id'];
    }
    $userIds = array_unique($userIds);

    foreach ($userIds as $uid) {
        $stmt = $db->prepare('INSERT INTO notifications (user_id, task_id, type, message) VALUES (:user_id, :task_id, :type, :message)');
        $stmt->bindValue(':user_id', $uid);
        $stmt->bindValue(':task_id', $task['id']);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':message', $message);
        $stmt->execute();

        $userStmt = $db->prepare('SELECT email FROM users WHERE id = :id');
        $userStmt->bindValue(':id', $uid);
        $userStmt->execute();
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['email'])) {
            EmailNotifier::send($user['email'], 'Benachrichtigung zur Aufgabe', $message);
        }
    }
}

// --- Срок приближается (в течение 24 часов) ---
$sql = "SELECT * FROM tasks
        WHERE due_date IS NOT NULL
          AND due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
          AND status IN ($placeholders)
          AND deadline_notified = 0";
$stmt = $db->prepare($sql);
$stmt->execute($activeStatuses);
$approaching = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($approaching as $task) {
    $message = sprintf('Die Frist für die Aufgabe "%s" läuft am %s ab.', $task['title'], $task['due_date']);
    notifyUsers($db, $task, 'deadline_approaching', $message);

    $upd = $db->prepare('UPDATE tasks SET deadline_notified = 1 WHERE id = :id');
    $upd->bindValue(':id', $task['id']);
    $upd->execute();
}

// --- Просроченные задачи ---
$sql = "SELECT * FROM tasks
        WHERE due_date IS NOT NULL
          AND due_date < NOW()
          AND status IN ($placeholders)
          AND overdue_notified = 0";
$stmt = $db->prepare($sql);
$stmt->execute($activeStatuses);
$overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($overdue as $task) {
    $message = sprintf('Die Aufgabe "%s" ist überfällig (Frist war %s).', $task['title'], $task['due_date']);
    notifyUsers($db, $task, 'overdue', $message);

    $upd = $db->prepare('UPDATE tasks SET overdue_notified = 1 WHERE id = :id');
    $upd->bindValue(':id', $task['id']);
    $upd->execute();
}

echo sprintf(
    "Проверка дедлайнов завершена: %d приближающихся, %d просроченных.\n",
    count($approaching),
    count($overdue)
);
