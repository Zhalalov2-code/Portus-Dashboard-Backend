<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

class Lkw
{
    private $db;
    private $id_lkw;
    private $tuf;
    private $esp;
    private $lkw_nummer;
    private $status;

    public function __construct($id_lkw = null, $tuf = null, $esp = null, $lkw_nummer = '', $status = 'active')
    {
        $this->db = DB::getInstance();
        $this->id_lkw = $id_lkw;
        $this->tuf = $tuf;
        $this->esp = $esp;
        $this->lkw_nummer = $lkw_nummer;
        $this->status = $status ?: 'active';
    }

    function verifyMethod($method, $route)
    {
        $res1 = $route[1] ?? null;
        switch ($method) {
            case 'GET':
                return $this->getLkw();
                break;
            case 'POST':
                if ($res1 === null) {
                    $data = $this->getReqData();
                    $this->hydrateForm($data);
                    return $this->postLkw();
                }
                break;
            case 'PUT':
                $data = $this->getReqData();
                $this->hydrateForm($data);
                return $this->putlkw();
                break;
            case 'DELETE':
                return $this->deleteLkw();
                break;
            default:
                return ['status' => 405];
        }
    }

    function getLkw()
    {
        $sql = 'SELECT * FROM lkw';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    private function requireUser()
    {
        if (!Auth::currentUser()) {
            return ['status' => 403, 'error' => 'Доступ запрещён — только сотрудники'];
        }
        return null;
    }

    function postLkw()
    {
        if ($err = $this->requireUser()) return $err;
        $sql = 'INSERT INTO lkw ( tuf, esp, lkw_nummer, status)
                VALUES ( :tuf, :esp, :lkw_nummer, :status)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tuf', $this->tuf);
        $stmt->bindValue(':esp', $this->esp);
        $stmt->bindValue(':lkw_nummer', $this->lkw_nummer);
        $stmt->bindValue(':status', $this->status);
        if ($stmt->execute()) {
            http_response_code(201);
            return [
                'status' => 201,
                'message' => 'LKW создан успешно',
                'lkw' => [
                    'id_lkw' => $this->db->lastInsertId(),
                    'tuf' => $this->tuf,
                    'esp' => $this->esp,
                    'lkw_nummer' => $this->lkw_nummer,
                    'status' => $this->status,
                ]
            ];
        }
        return ['status' => 400, 'message' => 'Ошибка при создании LKW'];
    }

    function putlkw()
    {
        if ($err = $this->requireUser()) return $err;
        $sql = 'UPDATE lkw
                    SET lkw_nummer = :lkw_nummer,
                       tuf = :tuf,
                       esp = :esp,
                       status = :status
                 WHERE id_lkw = :id_lkw';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_lkw', $this->id_lkw);
        $stmt->bindValue(':lkw_nummer', $this->lkw_nummer);
        $stmt->bindValue(':tuf', $this->tuf);
        $stmt->bindValue(':esp', $this->esp);
        $stmt->bindValue(':status', $this->status);
        if ($stmt->execute()) {
            return ['status' => 200, 'message' => 'LKW успешно обновлен'];
        }
        return ['status' => 400, 'message' => 'Ошибка при обновлении LKW'];
    }

    function deleteLkw()
    {
        if ($err = $this->requireUser()) return $err;
        $sql = 'DELETE FROM lkw WHERE id_lkw = :id_lkw';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_lkw', $this->id_lkw);
        if ($stmt->execute()) {
            return ['status' => 200, 'message' => 'LKW успешно удален'];
        }
        return ['status' => 400, 'message' => 'Ошибка при удалении LKW'];
    }

    private function getReqData()
    {
        $raw = file_get_contents("php://input");
        $contentType = $_SERVER["CONTENT_TYPE"] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        parse_str($raw, $out);
        return $out;
    }

    private function hydrateForm($data)
    {
        if (isset($data['id_lkw'])) {
            $this->id_lkw = $data['id_lkw'];
        }
        if (isset($data['tuf'])) {
            $this->tuf = $data['tuf'];
        }
        if (isset($data['esp'])) {
            $this->esp = $data['esp'];
        }
        if (isset($data['lkw_nummer'])) {
            $this->lkw_nummer = $data['lkw_nummer'];
        }
        if (isset($data['status'])) {
            $this->status = $data['status'];
        }
    }
}
