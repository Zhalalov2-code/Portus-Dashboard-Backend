<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Realtime.php';

class MessageLkw
{
    private $db;
    private $id_lkw;
    private $id_message;
    private $type_sender;
    private $text;

    function __construct($id_lkw = null, $id_message = null, $type_sender = '', $text = '')
    {
        $this->db = DB::getInstance();
        $this->id_lkw = $id_lkw;
        $this->id_message = $id_message;
        $this->type_sender = $type_sender;
        $this->text = $text;
    }

    function verifyMethod($method, $route)
    {
        switch ($method) {
            case 'GET':
                $data = $this->reqData();
                $this->hydrateForm($data);
                return $this->getMessageLkw();
                break;
            case 'POST':
                $data = $this->reqData();
                $this->hydrateForm($data);
                return $this->postMessageLkw();
                break;
            case 'DELETE':
                return $this->deleteMessageLkw();
                break;
            default:
                return ['status' => 405];
                break;
        }
    }

    function getMessageLkw()
    {
        $sql = "SELECT * FROM message_lkw WHERE id_lkw = :id_lkw";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_lkw', $this->id_lkw);
        $stmt->execute();
        $message_lkw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['status' => 200, 'data' => $message_lkw];
    }

    private function isSenderAllowed()
    {
        return Auth::currentUser() !== null || Auth::currentFahrer() !== null;
    }

    function postMessageLkw()
    {
        if (!$this->isSenderAllowed()) {
            return ['status' => 403, 'error' => 'Доступ запрещён'];
        }
        $sql = "INSERT INTO message_lkw (id_lkw, id_message, type_sender, text) VALUES (:id_lkw, :id_message, :type_sender, :text)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_lkw', $this->id_lkw);
        $stmt->bindValue(':id_message', $this->id_message);
        $stmt->bindValue(':type_sender', $this->type_sender);
        $stmt->bindValue(':text', $this->text);
        $stmt->execute();

        $newId = $this->db->lastInsertId();
        // Real-time: всем, кто открыл чат этого LKW.
        Realtime::notifyRoom('lkw-' . (int) $this->id_lkw, 'message', [
            'id_message' => (int) $newId,
            'id_lkw' => (int) $this->id_lkw,
            'type_sender' => $this->type_sender,
            'text' => $this->text,
        ]);

        return ['status' => 201, 'message' => 'Сообщение и грузовик связаны', 'id' => $newId];
    }

    function deleteMessageLkw()
    {
        if (!Auth::currentUser()) {
            return ['status' => 403, 'error' => 'Доступ запрещён — только сотрудники'];
        }
        if (empty($this->id_message)) {
            return ['status' => 400, 'message' => 'id_message обязателен'];
        }

        // Сначала получаем все файлы связанные с сообщением
        $sqlSelectFiles = "SELECT file_name FROM files_lkw WHERE id_message = :id_message";
        $stmtSelectFiles = $this->db->prepare($sqlSelectFiles);
        $stmtSelectFiles->bindValue(':id_message', $this->id_message, PDO::PARAM_INT);
        $stmtSelectFiles->execute();
        $files = $stmtSelectFiles->fetchAll(PDO::FETCH_ASSOC);

        // Удаляем физические файлы с диска
        foreach ($files as $file) {
            $file_path = __DIR__ . "/../uploads/lkw/" . $file['file_name'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // Удаляем записи файлов из БД
        $sqlFiles = "DELETE FROM files_lkw WHERE id_message = :id_message";
        $stmtFiles = $this->db->prepare($sqlFiles);
        $stmtFiles->bindValue(':id_message', $this->id_message, PDO::PARAM_INT);
        $stmtFiles->execute();

        // Удаляем само сообщение
        $sql = "DELETE FROM message_lkw WHERE id_message = :id_message";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_message', $this->id_message, PDO::PARAM_INT);
        $stmt->execute();

        return ['status' => 200, 'message' => 'Сообщение и связанные файлы удалены'];
    }

    function reqData()
    {
        $raw = file_get_contents("php://input");
        $content_type = $_SERVER["CONTENT_TYPE"] ?? '';

        if (stripos($content_type, 'application/json') !== false) {
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        parse_str($raw, $out);
        return $out;
    }

    function hydrateForm($data)
    {
        if (isset($data['id_lkw'])) {
            $this->id_lkw = $data['id_lkw'];
        }
        if (isset($data['id_message'])) {
            $this->id_message = $data['id_message'];
        }
        if (isset($data['type_sender'])) {
            $this->type_sender = $data['type_sender'];
        }
        if (isset($data['text'])) {
            $this->text = $data['text'];
        }
    }
}
