<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

class MessageChassi
{
    private $db;
    private $id_chassi;
    private $id_message;
    private $type_sender;
    private $text;
    private $action_type;
    private $latitude;
    private $longitude;
    private $address;

    function __construct($id_chassi = null, $id_message = null, $type_sender = '', $text = '', $action_type = '', $latitude = '', $longitude = '', $address = '')
    {
        $this->db = DB::getInstance();
        $this->id_chassi = $id_chassi;
        $this->id_message = $id_message;
        $this->type_sender = $type_sender;
        $this->text = $text;
        $this->action_type = $action_type;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->address = $address;
    }

    function verifyMethod($method)
    {
        switch ($method) {
            case 'GET':
                $data = $this->reqData();
                $this->hydrateForm($data);
                return $this->getMessageChassi();
                break;
            case 'POST':
                $data = $this->reqData();
                $this->hydrateForm($data);
                return $this->postMessageChassi();
                break;
            case 'DELETE':
                $data = $this->reqData();
                $this->hydrateForm($data);
                return $this->deleteMessageChassi();
                break;
            default:
                return ['status' => 405];
                break;
        }
    }

    function getMessageChassi()
    {
        if (empty($this->id_chassi)) {
            return ['status' => 400, 'message' => 'id_chassi обязателен'];
        }

        $sql = "SELECT * FROM message_chassi WHERE id_chassi = :id_chassi";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_chassi', $this->id_chassi, PDO::PARAM_INT);
        $stmt->execute();
        $message_chassi = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['status' => 200, 'data' => $message_chassi];
    }

    private function isSenderAllowed()
    {
        return Auth::currentUser() !== null || Auth::currentFahrer() !== null;
    }

    function postMessageChassi()
    {
        if (!$this->isSenderAllowed()) {
            return ['status' => 403, 'error' => 'Доступ запрещён'];
        }
        $sql = "INSERT INTO message_chassi (id_chassi, id_message, type_sender, text, action_type, latitude, longitude, address) VALUES (:id_chassi, :id_message, :type_sender, :text, :action_type, :latitude, :longitude, :address)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_chassi', $this->id_chassi);
        $stmt->bindValue(':id_message', $this->id_message);
        $stmt->bindValue(':type_sender', $this->type_sender);
        $stmt->bindValue(':text', $this->text);
        $stmt->bindValue(':action_type', $this->action_type);
        $stmt->bindValue(':latitude', $this->latitude);
        $stmt->bindValue(':longitude', $this->longitude);
        $stmt->bindValue(':address', $this->address);
        $stmt->execute();

        return ['status' => 201, 'message' => 'Сообщение и шасси связаны', 'id' => $this->db->lastInsertId()];
    }

    function deleteMessageChassi()
    {
        if (!Auth::currentUser()) {
            return ['status' => 403, 'error' => 'Доступ запрещён — только сотрудники'];
        }
        if (empty($this->id_message)) {
            return ['status' => 400, 'message' => 'id_message обязателен'];
        }

        $sqlSelectFiles = "SELECT file_name FROM files_chassi WHERE id_message = :id_message";
        $stmtSelectFiles = $this->db->prepare($sqlSelectFiles);
        $stmtSelectFiles->bindValue(':id_message', $this->id_message, PDO::PARAM_INT);
        $stmtSelectFiles->execute();
        $files = $stmtSelectFiles->fetchAll(PDO::FETCH_ASSOC);

        foreach ($files as $file) {
            $file_path = __DIR__ . "/../uploads/chassi/" . $file['file_name'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $sqlDeleteFiles = "DELETE FROM files_chassi WHERE id_message = :id_message";
        $stmtDeleteFiles = $this->db->prepare($sqlDeleteFiles);
        $stmtDeleteFiles->bindValue(':id_message', $this->id_message, PDO::PARAM_INT);
        $stmtDeleteFiles->execute();

        $sql = "DELETE FROM message_chassi WHERE id_message = :id_message";
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

        if (stripos($content_type, 'multipart/form-data') !== false) {
            return $_POST;
        }
        parse_str($raw, $out);
        return $out;
    }

    function hydrateForm($data)
    {
        if (isset($data['id_chassi'])) {
            $this->id_chassi = $data['id_chassi'];
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
        if (isset($data['action_type'])) {
            $this->action_type = $data['action_type'];
        }
        if (isset($data['latitude'])) {
            $this->latitude = $data['latitude'];
        }
        if (isset($data['longitude'])) {
            $this->longitude = $data['longitude'];
        }
        if (isset($data['address'])) {
            $this->address = $data['address'];
        }
    }
}
