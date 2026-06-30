<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/EmailNotifier.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Realtime.php';

class Tasks
{
    const URGENCIES = ['low', 'medium', 'high', 'urgent'];
    const IMPORTANCES = ['low', 'medium', 'high', 'critical'];
    const STATUSES = ['new', 'in_progress', 'clarification', 'done'];

    private $db;
    private $data;

    function __construct($data = [])
    {
        $this->db = DB::getInstance();
        $body = $this->parseBody();
        $this->data = array_merge(is_array($data) ? $data : [], $body);
    }

    private function parseBody()
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        parse_str($raw, $out);
        return is_array($out) ? $out : [];
    }

    private function input($key, $default = null)
    {
        if (array_key_exists($key, $this->data) && $this->data[$key] !== '') {
            return $this->data[$key];
        }
        return $default;
    }

    function verifyMethod($method, $route)
    {
        $id = (isset($route[1]) && is_numeric($route[1])) ? (int) $route[1] : null;
        $sub = $route[2] ?? null;

        switch ($method) {
            case 'GET':
                if ($id && $sub === 'comments') {
                    return $this->getComments($id);
                }
                if ($id) {
                    return $this->getOne($id);
                }
                return $this->getList();

            case 'POST':
                if ($id && $sub === 'comments') {
                    return $this->addComment($id);
                }
                return $this->create();

            case 'PUT':
                if (!$id) {
                    return ['status' => 400, 'error' => 'ID задачи обязателен'];
                }
                return $this->update($id);

            case 'DELETE':
                if (!$id) {
                    return ['status' => 400, 'error' => 'ID задачи обязателен'];
                }
                return $this->delete($id);

            default:
                return ['status' => 405];
        }
    }

    private function getRequester()
    {
        // Ранее requester_id брался из тела запроса, что позволяло
        // выдать себя за любого пользователя. Теперь личность определяется
        // только проверенным bearer-токеном (см. Auth::resolve в index.php).
        return Auth::currentUser();
    }

    private function canViewTask($task, $requester)
    {
        if ($requester['role'] === 'admin') {
            return true;
        }
        if ((int) $task['creator_id'] === (int) $requester['id']) {
            return true;
        }
        if ($task['assignee_id'] && (int) $task['assignee_id'] === (int) $requester['id']) {
            return true;
        }
        if ($requester['department_id'] && (int) $task['department_id'] === (int) $requester['department_id']) {
            return true;
        }
        return false;
    }

    private function canFullyEdit($task, $requester)
    {
        if ($requester['role'] === 'admin') {
            return true;
        }
        if ((int) $task['creator_id'] === (int) $requester['id']) {
            return true;
        }
        if ($requester['role'] === 'department_head'
            && $requester['department_id']
            && (int) $task['department_id'] === (int) $requester['department_id']) {
            return true;
        }
        return false;
    }

    private function fetchTask($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        return $task ?: null;
    }

    function getList()
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }

        $where = [];
        $params = [];

        if ($requester['role'] !== 'admin') {
            $where[] = '(creator_id = :uid OR assignee_id = :uid OR department_id = :udept)';
            $params[':uid'] = $requester['id'];
            $params[':udept'] = $requester['department_id'];
        }

        if (!empty($this->data['department_id'])) {
            $where[] = 'department_id = :f_department_id';
            $params[':f_department_id'] = $this->data['department_id'];
        }
        if (!empty($this->data['assignee_id'])) {
            $where[] = 'assignee_id = :f_assignee_id';
            $params[':f_assignee_id'] = $this->data['assignee_id'];
        }
        if (!empty($this->data['status'])) {
            $where[] = 'status = :f_status';
            $params[':f_status'] = $this->data['status'];
        }
        if (!empty($this->data['urgency'])) {
            $where[] = 'urgency = :f_urgency';
            $params[':f_urgency'] = $this->data['urgency'];
        }
        if (!empty($this->data['importance'])) {
            $where[] = 'importance = :f_importance';
            $params[':f_importance'] = $this->data['importance'];
        }
        if (!empty($this->data['created_from'])) {
            $where[] = 'created_at >= :created_from';
            $params[':created_from'] = $this->data['created_from'];
        }
        if (!empty($this->data['created_to'])) {
            $where[] = 'created_at <= :created_to';
            $params[':created_to'] = $this->data['created_to'];
        }
        if (!empty($this->data['due_from'])) {
            $where[] = 'due_date >= :due_from';
            $params[':due_from'] = $this->data['due_from'];
        }
        if (!empty($this->data['due_to'])) {
            $where[] = 'due_date <= :due_to';
            $params[':due_to'] = $this->data['due_to'];
        }
        if (!empty($this->data['overdue'])) {
            $where[] = "due_date IS NOT NULL AND due_date < NOW() AND status NOT IN ('done')";
        }
        if (!empty($this->data['search'])) {
            $where[] = '(title LIKE :search OR description LIKE :search)';
            $params[':search'] = '%' . $this->data['search'] . '%';
        }

        $sql = 'SELECT * FROM tasks';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return ['status' => 200, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    function getOne($id)
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }

        $task = $this->fetchTask($id);
        if (!$task) {
            return ['status' => 404, 'error' => 'Задача не найдена'];
        }
        if (!$this->canViewTask($task, $requester)) {
            return ['status' => 403, 'error' => 'Нет доступа к этой задаче'];
        }

        $task['comments'] = $this->fetchComments($id);

        return ['status' => 200, 'data' => $task];
    }

    private function fetchComments($taskId)
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, u.name AS user_name, u.lastname AS user_lastname
             FROM task_comments c JOIN users u ON u.id = c.user_id
             WHERE c.task_id = :id ORDER BY c.created_at ASC'
        );
        $stmt->bindValue(':id', $taskId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function create()
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }

        $title = trim((string) $this->input('title', ''));
        $departmentId = $this->input('department_id');

        if (empty($title)) {
            return ['status' => 400, 'error' => 'Название задачи обязательно'];
        }
        if (empty($departmentId)) {
            return ['status' => 400, 'error' => 'Отдел-исполнитель обязателен'];
        }

        $description = $this->input('description', '');
        $assigneeId = $this->input('assignee_id') ?: null;
        $urgencyInput = $this->input('urgency');
        $urgency = in_array($urgencyInput, self::URGENCIES, true) ? $urgencyInput : 'medium';
        $importanceInput = $this->input('importance');
        $importance = in_array($importanceInput, self::IMPORTANCES, true) ? $importanceInput : 'medium';
        $dueDate = $this->input('due_date') ?: null;

        $sql = 'INSERT INTO tasks (title, description, department_id, creator_id, assignee_id, urgency, importance, status, due_date)
                VALUES (:title, :description, :department_id, :creator_id, :assignee_id, :urgency, :importance, "new", :due_date)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':department_id', $departmentId);
        $stmt->bindValue(':creator_id', $requester['id']);
        $stmt->bindValue(':assignee_id', $assigneeId);
        $stmt->bindValue(':urgency', $urgency);
        $stmt->bindValue(':importance', $importance);
        $stmt->bindValue(':due_date', $dueDate);

        if (!$stmt->execute()) {
            return ['status' => 400, 'error' => 'Не удалось создать задачу'];
        }

        $task = $this->fetchTask($this->db->lastInsertId());
        $this->notifyTaskCreated($task);

        return ['status' => 201, 'data' => $task];
    }

    function update($id)
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }

        $task = $this->fetchTask($id);
        if (!$task) {
            return ['status' => 404, 'error' => 'Задача не найдена'];
        }

        $fullEdit = $this->canFullyEdit($task, $requester);
        $statusOnly = !$fullEdit && $task['assignee_id'] && (int) $task['assignee_id'] === (int) $requester['id'];

        if (!$fullEdit && !$statusOnly) {
            return ['status' => 403, 'error' => 'Нет прав на изменение этой задачи'];
        }

        $newValues = $task;

        if ($fullEdit) {
            if ($this->input('title') !== null) {
                $newValues['title'] = $this->input('title');
            }
            if ($this->input('description') !== null) {
                $newValues['description'] = $this->input('description');
            }
            if ($this->input('department_id') !== null) {
                $newValues['department_id'] = $this->input('department_id');
            }
            if (array_key_exists('assignee_id', $this->data)) {
                $newValues['assignee_id'] = $this->input('assignee_id') ?: null;
            }
            $urgencyInput = $this->input('urgency');
            if ($urgencyInput !== null && in_array($urgencyInput, self::URGENCIES, true)) {
                $newValues['urgency'] = $urgencyInput;
            }
            $importanceInput = $this->input('importance');
            if ($importanceInput !== null && in_array($importanceInput, self::IMPORTANCES, true)) {
                $newValues['importance'] = $importanceInput;
            }
            if (array_key_exists('due_date', $this->data)) {
                $newValues['due_date'] = $this->input('due_date') ?: null;
            }
        }

        $statusInput = $this->input('status');
        if ($statusInput !== null && in_array($statusInput, self::STATUSES, true)) {
            $newValues['status'] = $statusInput;
        }

        $sql = 'UPDATE tasks SET title = :title, description = :description, department_id = :department_id,
                    assignee_id = :assignee_id, urgency = :urgency, importance = :importance,
                    status = :status, due_date = :due_date
                WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':title', $newValues['title']);
        $stmt->bindValue(':description', $newValues['description']);
        $stmt->bindValue(':department_id', $newValues['department_id']);
        $stmt->bindValue(':assignee_id', $newValues['assignee_id']);
        $stmt->bindValue(':urgency', $newValues['urgency']);
        $stmt->bindValue(':importance', $newValues['importance']);
        $stmt->bindValue(':status', $newValues['status']);
        $stmt->bindValue(':due_date', $newValues['due_date']);
        $stmt->bindValue(':id', $id);

        if (!$stmt->execute()) {
            return ['status' => 400, 'error' => 'Не удалось обновить задачу'];
        }

        $updatedTask = $this->fetchTask($id);

        if ((string) $task['status'] !== (string) $updatedTask['status']) {
            $this->notifyStatusChanged($updatedTask, $requester);
        }
        if ((string) $task['assignee_id'] !== (string) $updatedTask['assignee_id'] && $updatedTask['assignee_id']) {
            $this->notifyAssigneeChanged($updatedTask);
        }

        return ['status' => 200, 'data' => $updatedTask];
    }

    function delete($id)
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }
        $task = $this->fetchTask($id);
        if (!$task) {
            return ['status' => 404, 'error' => 'Задача не найдена'];
        }
        $isCreator = (int) $task['creator_id'] === (int) $requester['id'];
        if ($requester['role'] !== 'admin' && !$isCreator) {
            return ['status' => 403, 'error' => 'Удалить задачу может только администратор или её создатель'];
        }

        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->bindValue(':id', $id);
        if ($stmt->execute()) {
            return ['status' => 200];
        }
        return ['status' => 400, 'error' => 'Не удалось удалить задачу'];
    }

    function getComments($taskId)
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }
        $task = $this->fetchTask($taskId);
        if (!$task) {
            return ['status' => 404, 'error' => 'Задача не найдена'];
        }
        if (!$this->canViewTask($task, $requester)) {
            return ['status' => 403, 'error' => 'Нет доступа к этой задаче'];
        }

        return ['status' => 200, 'data' => $this->fetchComments($taskId)];
    }

    function addComment($taskId)
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }
        $task = $this->fetchTask($taskId);
        if (!$task) {
            return ['status' => 404, 'error' => 'Задача не найдена'];
        }
        if (!$this->canViewTask($task, $requester)) {
            return ['status' => 403, 'error' => 'Нет доступа к этой задаче'];
        }

        $text = trim((string) $this->input('text', ''));
        if (empty($text)) {
            return ['status' => 400, 'error' => 'Текст комментария обязателен'];
        }

        $stmt = $this->db->prepare('INSERT INTO task_comments (task_id, user_id, text) VALUES (:task_id, :user_id, :text)');
        $stmt->bindValue(':task_id', $taskId);
        $stmt->bindValue(':user_id', $requester['id']);
        $stmt->bindValue(':text', $text);

        if (!$stmt->execute()) {
            return ['status' => 400, 'error' => 'Не удалось сохранить комментарий'];
        }

        $this->notifyNewComment($task, $requester, $text);

        return ['status' => 201, 'message' => 'Комментарий добавлен'];
    }

    // ----------------------- Уведомления -----------------------

    private function createNotification($userId, $taskId, $type, $message)
    {
        if (empty($userId)) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, task_id, type, message) VALUES (:user_id, :task_id, :type, :message)'
        );
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':task_id', $taskId);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':message', $message);
        $stmt->execute();

        // Real-time уведомление пользователю (WebSocket).
        Realtime::notifyUser($userId, 'notification', [
            'id' => (int) $this->db->lastInsertId(),
            'task_id' => $taskId,
            'type' => $type,
            'message' => $message,
        ]);

        $this->emailUser($userId, $message);
    }

    private function emailUser($userId, $message)
    {
        // У пользователей больше нет email — уведомления хранятся только
        // внутри платформы (таблица notifications). Метод оставлен как
        // точка расширения на случай возврата email-рассылки.
    }

    private function departmentUserIds($departmentId, $excludeUserId = null)
    {
        $sql = 'SELECT id FROM users WHERE department_id = :department_id';
        if ($excludeUserId) {
            $sql .= ' AND id != :exclude';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':department_id', $departmentId);
        if ($excludeUserId) {
            $stmt->bindValue(':exclude', $excludeUserId);
        }
        $stmt->execute();
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    private function notifyTaskCreated($task)
    {
        if ($task['assignee_id']) {
            $this->createNotification(
                $task['assignee_id'],
                $task['id'],
                'task_created',
                sprintf('Ihnen wurde eine neue Aufgabe zugewiesen: "%s".', $task['title'])
            );
            return;
        }

        $message = sprintf('Eine neue Aufgabe "%s" wurde für Ihre Abteilung erstellt.', $task['title']);
        foreach ($this->departmentUserIds($task['department_id'], $task['creator_id']) as $uid) {
            $this->createNotification($uid, $task['id'], 'task_created', $message);
        }
    }

    private function notifyAssigneeChanged($task)
    {
        $this->createNotification(
            $task['assignee_id'],
            $task['id'],
            'assignee_changed',
            sprintf('Sie wurden als Verantwortlicher für die Aufgabe "%s" festgelegt.', $task['title'])
        );
    }

    private function notifyStatusChanged($task, $requester)
    {
        $message = sprintf('Der Status der Aufgabe "%s" wurde auf "%s" geändert.', $task['title'], $this->statusLabel($task['status']));

        if ($task['creator_id'] && (int) $task['creator_id'] !== (int) $requester['id']) {
            $this->createNotification($task['creator_id'], $task['id'], 'status_changed', $message);
        }
        if ($task['assignee_id'] && (int) $task['assignee_id'] !== (int) $requester['id']) {
            $this->createNotification($task['assignee_id'], $task['id'], 'status_changed', $message);
        }
    }

    private function notifyNewComment($task, $requester, $text)
    {
        $message = sprintf('Neuer Kommentar zur Aufgabe "%s": %s', $task['title'], mb_substr($text, 0, 200));

        foreach ([$task['creator_id'], $task['assignee_id']] as $uid) {
            if ($uid && (int) $uid !== (int) $requester['id']) {
                $this->createNotification($uid, $task['id'], 'new_comment', $message);
            }
        }
    }

    private function statusLabel($status)
    {
        $labels = [
            'new' => 'Wartet auf Bestätigung',
            'in_progress' => 'In Bearbeitung',
            'clarification' => 'Rückfrage / Klärung',
            'done' => 'Erledigt',
        ];
        return $labels[$status] ?? $status;
    }
}
