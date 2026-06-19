<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

class Notifications
{
    private $db;
    private $data;

    function __construct($data = [])
    {
        $this->db = DB::getInstance();
        $this->data = is_array($data) ? $data : [];
    }

    function verifyMethod($method, $route)
    {
        $second = $route[1] ?? null;
        $third = $route[2] ?? null;

        switch ($method) {
            case 'GET':
                return $this->list();

            case 'PUT':
                if ($second && is_numeric($second) && $third === 'read') {
                    return $this->markRead((int) $second);
                }
                return ['status' => 404, 'error' => 'Не найдено'];

            case 'POST':
                if ($second === 'read-all') {
                    return $this->markAllRead();
                }
                return ['status' => 404, 'error' => 'Не найдено'];

            default:
                return ['status' => 405];
        }
    }

    function list()
    {
        // Берём user_id из токена, а не из тела/query запроса — иначе любой
        // авторизованный пользователь мог бы прочитать уведомления другого.
        $current = Auth::currentUser();
        $userId = $current['id'] ?? null;
        if (empty($userId)) {
            return ['status' => 401, 'error' => 'Не авторизован'];
        }

        $sql = 'SELECT * FROM notifications WHERE user_id = :user_id';
        if (!empty($this->data['unread'])) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 50';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = :user_id AND is_read = 0');
        $countStmt->bindValue(':user_id', $userId);
        $countStmt->execute();
        $unread = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        return ['status' => 200, 'data' => $data, 'unread_count' => $unread];
    }

    function markRead($id)
    {
        $current = Auth::currentUser();
        $userId = $current['id'] ?? null;
        if (empty($userId)) {
            return ['status' => 401, 'error' => 'Не авторизован'];
        }

        // Проверяем владельца, чтобы нельзя было пометить прочитанным
        // уведомление другого пользователя по чужому id.
        $stmt = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':user_id', $userId);
        if ($stmt->execute()) {
            return ['status' => 200];
        }
        return ['status' => 400, 'error' => 'Не удалось обновить уведомление'];
    }

    function markAllRead()
    {
        $current = Auth::currentUser();
        $userId = $current['id'] ?? null;
        if (empty($userId)) {
            return ['status' => 401, 'error' => 'Не авторизован'];
        }
        $stmt = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId);
        if ($stmt->execute()) {
            return ['status' => 200];
        }
        return ['status' => 400, 'error' => 'Не удалось обновить уведомления'];
    }
}
