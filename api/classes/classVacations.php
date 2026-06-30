<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/EmailNotifier.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Realtime.php';

class Vacations
{
    const TYPES = ['vacation', 'sick', 'unpaid', 'other'];
    const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];
    const DEFAULT_DAYS_PER_YEAR = 28;
    const HR_DEPARTMENT_NAME = 'personal';
    // Erstes Jahr, für das die Urlaubsverwaltung gepflegt wird. Resturlaub wird
    // nur ab diesem Jahr übertragen, damit für Jahre ohne echte Daten nicht
    // fälschlich "voller ungenutzter Urlaub" als Übertrag erscheint.
    const MODULE_START_YEAR = 2025;

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
        $second = $route[1] ?? null;
        $id = (is_numeric($second)) ? (int) $second : null;

        switch ($method) {
            case 'GET':
                if ($second === 'summary') {
                    return $this->getSummary();
                }
                if ($second === 'summary-all') {
                    return $this->getSummaryAll();
                }
                if ($id) {
                    return $this->getOne($id);
                }
                return $this->getList();

            case 'POST':
                return $this->create();

            case 'PUT':
                if (!$id) {
                    return ['status' => 400, 'error' => 'ID заявки обязателен'];
                }
                return $this->update($id);

            case 'DELETE':
                if (!$id) {
                    return ['status' => 400, 'error' => 'ID заявки обязателен'];
                }
                return $this->delete($id);

            default:
                return ['status' => 405];
        }
    }

    private function getRequester()
    {
        // requester_id из тела запроса больше не используется для авторизации —
        // личность берётся из проверенного bearer-токена.
        return Auth::currentUser();
    }

    /**
     * HR-Mitarbeiter = Administrator ODER Mitglied der Abteilung "Personal".
     * Nur diese dürfen Urlaubseinträge anlegen/bearbeiten/löschen.
     */
    private function isHr($requester)
    {
        if (!$requester) {
            return false;
        }
        if ($requester['role'] === 'admin') {
            return true;
        }
        if (empty($requester['department_id'])) {
            return false;
        }
        $stmt = $this->db->prepare('SELECT name FROM departments WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $requester['department_id']);
        $stmt->execute();
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);
        return $dept && strpos(strtolower(trim($dept['name'])), self::HR_DEPARTMENT_NAME) !== false;
    }

    private function fetchVacation($id)
    {
        $stmt = $this->db->prepare(
            'SELECT v.*, u.name AS user_name, u.lastname AS user_lastname,
                    a.name AS approver_name, a.lastname AS approver_lastname
             FROM vacations v
             JOIN users u ON u.id = v.user_id
             LEFT JOIN users a ON a.id = v.approver_id
             WHERE v.id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    function getList()
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }

        // Die Liste ist für alle Mitarbeiter offen einsehbar (nur lesend).
        $where = [];
        $params = [];

        if (!empty($this->data['user_id'])) {
            $where[] = 'v.user_id = :f_user_id';
            $params[':f_user_id'] = $this->data['user_id'];
        }
        if (!empty($this->data['department_id'])) {
            $where[] = 'v.department_id = :f_department_id';
            $params[':f_department_id'] = $this->data['department_id'];
        }
        if (!empty($this->data['status'])) {
            $where[] = 'v.status = :f_status';
            $params[':f_status'] = $this->data['status'];
        }
        if (!empty($this->data['type'])) {
            $where[] = 'v.type = :f_type';
            $params[':f_type'] = $this->data['type'];
        }
        if (!empty($this->data['year'])) {
            $where[] = 'YEAR(v.start_date) = :f_year';
            $params[':f_year'] = $this->data['year'];
        }

        $sql = 'SELECT v.*, u.name AS user_name, u.lastname AS user_lastname,
                       a.name AS approver_name, a.lastname AS approver_lastname
                FROM vacations v
                JOIN users u ON u.id = v.user_id
                LEFT JOIN users a ON a.id = v.approver_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY v.start_date DESC';

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
        $vacation = $this->fetchVacation($id);
        if (!$vacation) {
            return ['status' => 404, 'error' => 'Eintrag nicht gefunden'];
        }
        return ['status' => 200, 'data' => $vacation];
    }

    /**
     * Berechnet die Urlaubsbilanz für genau einen Mitarbeiter/Jahr.
     * Wird sowohl von getSummary() (einzelner Nutzer) als auch von
     * getSummaryAll() (Übersicht für HR/Admin) verwendet.
     */
    private function computeSummary($userId, $year)
    {
        $stmt = $this->db->prepare('SELECT vacation_days_per_year FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $baseDays = $user ? (int) ($user['vacation_days_per_year'] ?: self::DEFAULT_DAYS_PER_YEAR) : self::DEFAULT_DAYS_PER_YEAR;

        $usedStmt = $this->db->prepare(
            "SELECT COALESCE(SUM(days_count), 0) AS total FROM vacations
             WHERE user_id = :uid AND type = 'vacation' AND status = 'approved' AND YEAR(start_date) = :year"
        );
        $usedStmt->bindValue(':uid', $userId);
        $usedStmt->bindValue(':year', $year);
        $usedStmt->execute();
        $used = (int) ($usedStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $pendingStmt = $this->db->prepare(
            "SELECT COALESCE(SUM(days_count), 0) AS total FROM vacations
             WHERE user_id = :uid AND type = 'vacation' AND status = 'pending' AND YEAR(start_date) = :year"
        );
        $pendingStmt->bindValue(':uid', $userId);
        $pendingStmt->bindValue(':year', $year);
        $pendingStmt->execute();
        $pending = (int) ($pendingStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Resturlaub aus dem Vorjahr: nur einfacher Übertrag um genau ein Jahr
        // (kein Verketten über mehrere Jahre), und nur ab MODULE_START_YEAR,
        // damit Jahre ohne echte Daten keinen künstlichen "vollen" Übertrag erzeugen.
        $carryoverDays = 0;
        $prevYear = $year - 1;
        if ($prevYear >= self::MODULE_START_YEAR) {
            $prevUsedStmt = $this->db->prepare(
                "SELECT COALESCE(SUM(days_count), 0) AS total FROM vacations
                 WHERE user_id = :uid AND type = 'vacation' AND status = 'approved' AND YEAR(start_date) = :year"
            );
            $prevUsedStmt->bindValue(':uid', $userId);
            $prevUsedStmt->bindValue(':year', $prevYear);
            $prevUsedStmt->execute();
            $prevUsed = (int) ($prevUsedStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            $carryoverDays = max(0, $baseDays - $prevUsed);
        }

        $totalDays = $baseDays + $carryoverDays;

        return [
            'user_id' => (int) $userId,
            'year' => $year,
            'base_days' => $baseDays,
            'carryover_days' => $carryoverDays,
            'carryover_from_year' => $carryoverDays > 0 ? $prevYear : null,
            'total_days' => $totalDays,
            'used_days' => $used,
            'pending_days' => $pending,
            'remaining_days' => $totalDays - $used,
        ];
    }

    function getSummary()
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }

        $userId = $this->input('user_id') ?: $requester['id'];

        // Eigenen Stand darf jeder sehen, fremden nur HR/Admin.
        if ((int) $userId !== (int) $requester['id'] && !$this->isHr($requester)) {
            return ['status' => 403, 'error' => 'Kein Zugriff'];
        }

        $year = (int) ($this->input('year') ?: date('Y'));

        return ['status' => 200, 'data' => $this->computeSummary($userId, $year)];
    }

    /**
     * Übersicht der Urlaubsbilanz ALLER Mitarbeiter für ein Jahr.
     * Nur für HR/Admin (z.B. für eine Gesamtübersicht "wer hat noch wie viel Urlaub").
     */
    function getSummaryAll()
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }
        if (!$this->isHr($requester)) {
            return ['status' => 403, 'error' => 'Kein Zugriff'];
        }

        $year = (int) ($this->input('year') ?: date('Y'));

        $where = [];
        $params = [];
        if (!empty($this->data['department_id'])) {
            $where[] = 'department_id = :department_id';
            $params[':department_id'] = $this->data['department_id'];
        }

        $sql = 'SELECT id, name, lastname, department_id FROM users';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY name, lastname';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($users as $u) {
            $summary = $this->computeSummary($u['id'], $year);
            $summary['user_name'] = $u['name'];
            $summary['user_lastname'] = $u['lastname'];
            $summary['department_id'] = $u['department_id'];
            $result[] = $summary;
        }

        return ['status' => 200, 'data' => $result];
    }

    function create()
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }
        if (!$this->isHr($requester)) {
            return ['status' => 403, 'error' => 'Nur die Personalabteilung kann Urlaubseinträge anlegen'];
        }

        $targetUserId = $this->input('user_id');
        if (empty($targetUserId)) {
            return ['status' => 400, 'error' => 'Mitarbeiter ist erforderlich'];
        }
        $userStmt = $this->db->prepare('SELECT id, department_id FROM users WHERE id = :id LIMIT 1');
        $userStmt->bindValue(':id', $targetUserId);
        $userStmt->execute();
        $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) {
            return ['status' => 404, 'error' => 'Mitarbeiter nicht gefunden'];
        }

        $typeInput = $this->input('type');
        $type = in_array($typeInput, self::TYPES, true) ? $typeInput : 'vacation';
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');
        $reason = $this->input('reason', '');

        if (empty($startDate) || empty($endDate)) {
            return ['status' => 400, 'error' => 'Start- und Enddatum sind erforderlich'];
        }

        $start = strtotime($startDate);
        $end = strtotime($endDate);
        if ($start === false || $end === false || $end < $start) {
            return ['status' => 400, 'error' => 'Ungültiger Zeitraum'];
        }

        $daysCount = (int) floor(($end - $start) / 86400) + 1;

        $sql = 'INSERT INTO vacations (user_id, department_id, type, start_date, end_date, days_count, status, reason, approver_id)
                VALUES (:user_id, :department_id, :type, :start_date, :end_date, :days_count, "approved", :reason, :approver_id)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $targetUser['id']);
        $stmt->bindValue(':department_id', $targetUser['department_id']);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':start_date', date('Y-m-d', $start));
        $stmt->bindValue(':end_date', date('Y-m-d', $end));
        $stmt->bindValue(':days_count', $daysCount);
        $stmt->bindValue(':reason', $reason);
        $stmt->bindValue(':approver_id', $requester['id']);

        if (!$stmt->execute()) {
            return ['status' => 400, 'error' => 'Der Eintrag konnte nicht erstellt werden'];
        }

        $vacation = $this->fetchVacation($this->db->lastInsertId());
        $this->notifyCreated($vacation, $requester);

        return ['status' => 201, 'data' => $vacation];
    }

    function update($id)
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }
        if (!$this->isHr($requester)) {
            return ['status' => 403, 'error' => 'Nur die Personalabteilung kann Urlaubseinträge bearbeiten'];
        }
        $vacation = $this->fetchVacation($id);
        if (!$vacation) {
            return ['status' => 404, 'error' => 'Eintrag nicht gefunden'];
        }

        $statusInput = $this->input('status');

        // Stornieren / Status ändern
        if ($statusInput !== null && in_array($statusInput, self::STATUSES, true) && $statusInput !== $vacation['status']) {
            $comment = $this->input('approver_comment', '');

            $stmt = $this->db->prepare(
                'UPDATE vacations SET status = :status, approver_id = :approver_id, approver_comment = :comment WHERE id = :id'
            );
            $stmt->bindValue(':status', $statusInput);
            $stmt->bindValue(':approver_id', $requester['id']);
            $stmt->bindValue(':comment', $comment);
            $stmt->bindValue(':id', $id);

            if (!$stmt->execute()) {
                return ['status' => 400, 'error' => 'Der Eintrag konnte nicht aktualisiert werden'];
            }

            $updated = $this->fetchVacation($id);
            $this->notifyStatusChanged($updated, $requester, $statusInput);

            return ['status' => 200, 'data' => $updated];
        }

        // Felder bearbeiten (Art/Zeitraum/Begründung) — jederzeit durch HR/Admin
        $typeInput = $this->input('type');
        $type = in_array($typeInput, self::TYPES, true) ? $typeInput : $vacation['type'];
        $startDate = $this->input('start_date') ?: $vacation['start_date'];
        $endDate = $this->input('end_date') ?: $vacation['end_date'];
        $reason = $this->input('reason');
        $reason = $reason !== null ? $reason : $vacation['reason'];

        $start = strtotime($startDate);
        $end = strtotime($endDate);
        if ($start === false || $end === false || $end < $start) {
            return ['status' => 400, 'error' => 'Ungültiger Zeitraum'];
        }
        $daysCount = (int) floor(($end - $start) / 86400) + 1;

        $stmt = $this->db->prepare(
            'UPDATE vacations SET type = :type, start_date = :start_date, end_date = :end_date, days_count = :days_count, reason = :reason WHERE id = :id'
        );
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':start_date', date('Y-m-d', $start));
        $stmt->bindValue(':end_date', date('Y-m-d', $end));
        $stmt->bindValue(':days_count', $daysCount);
        $stmt->bindValue(':reason', $reason);
        $stmt->bindValue(':id', $id);

        if (!$stmt->execute()) {
            return ['status' => 400, 'error' => 'Der Eintrag konnte nicht aktualisiert werden'];
        }

        $updated = $this->fetchVacation($id);
        $this->notifyUpdated($updated, $requester);

        return ['status' => 200, 'data' => $updated];
    }

    function delete($id)
    {
        $requester = $this->getRequester();
        if (!$requester) {
            return ['status' => 400, 'error' => 'requester_id обязателен и должен существовать'];
        }
        if (!$this->isHr($requester)) {
            return ['status' => 403, 'error' => 'Nur die Personalabteilung kann Einträge löschen'];
        }
        $stmt = $this->db->prepare('DELETE FROM vacations WHERE id = :id');
        $stmt->bindValue(':id', $id);
        if ($stmt->execute()) {
            return ['status' => 200];
        }
        return ['status' => 400, 'error' => 'Der Eintrag konnte nicht gelöscht werden'];
    }

    // ----------------------- Уведомления -----------------------

    private function createNotification($userId, $vacationId, $type, $message)
    {
        if (empty($userId)) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, vacation_id, type, message) VALUES (:user_id, :vacation_id, :type, :message)'
        );
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':vacation_id', $vacationId);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':message', $message);
        $stmt->execute();

        Realtime::notifyUser($userId, 'notification', [
            'id' => (int) $this->db->lastInsertId(),
            'vacation_id' => $vacationId,
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

    private function periodLabel($vacation)
    {
        return sprintf(
            '%s – %s',
            date('d.m.Y', strtotime($vacation['start_date'])),
            date('d.m.Y', strtotime($vacation['end_date']))
        );
    }

    private function typeLabel($type)
    {
        $labels = [
            'vacation' => 'Urlaub',
            'sick' => 'Krankheit',
            'unpaid' => 'Unbezahlter Urlaub',
            'other' => 'Sonstige Abwesenheit',
        ];
        return $labels[$type] ?? $type;
    }

    private function notifyCreated($vacation, $requester)
    {
        if ((int) $vacation['user_id'] === (int) $requester['id']) {
            return;
        }
        $message = sprintf(
            'Für Sie wurde ein Eintrag erfasst: %s, %s.',
            $this->typeLabel($vacation['type']),
            $this->periodLabel($vacation)
        );
        $this->createNotification($vacation['user_id'], $vacation['id'], 'vacation_recorded', $message);
    }

    private function notifyUpdated($vacation, $requester)
    {
        if ((int) $vacation['user_id'] === (int) $requester['id']) {
            return;
        }
        $message = sprintf(
            'Ihr Eintrag wurde aktualisiert: %s, %s.',
            $this->typeLabel($vacation['type']),
            $this->periodLabel($vacation)
        );
        $this->createNotification($vacation['user_id'], $vacation['id'], 'vacation_updated', $message);
    }

    private function notifyStatusChanged($vacation, $requester, $status)
    {
        if ((int) $vacation['user_id'] === (int) $requester['id']) {
            return;
        }
        if ($status === 'cancelled') {
            $message = sprintf('Ihr Eintrag (%s, %s) wurde storniert.', $this->typeLabel($vacation['type']), $this->periodLabel($vacation));
        } elseif ($status === 'approved') {
            $message = sprintf('Ihr Eintrag (%s, %s) wurde bestätigt.', $this->typeLabel($vacation['type']), $this->periodLabel($vacation));
        } else {
            $message = sprintf('Der Status Ihres Eintrags (%s, %s) wurde geändert.', $this->typeLabel($vacation['type']), $this->periodLabel($vacation));
        }
        $this->createNotification($vacation['user_id'], $vacation['id'], 'vacation_' . $status, $message);
    }
}
