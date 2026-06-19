<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

class Chassi
{
    private $id_chassi;
    private $chassi_nummer;
    private $tuf;
    private $esp;
    private $db;

    function __construct($id_chassi = null, $chassi_nummer = '', $tuf = '', $esp = '')
    {
        $this->db = DB::getInstance();
        $this->id_chassi = $id_chassi;
        $this->chassi_nummer = $chassi_nummer;
        $this->tuf = $tuf;
        $this->esp = $esp;
    }

    function verifyMethod($method, $route)
    {
        $res1 = $route[1] ?? null;
        switch ($method) {
            case 'GET':
                return $this->chassiGet();

            case 'POST':
                if( $res1 === null) {
                    $data = $this->getReqData();
                    $this->hydrateForm($data);
                    return $this->chassiPost();
                }
                break;
            case 'PUT':
                $data = $this->getReqData();
                $this->hydrateForm($data);
                return $this->chassiPut();
                break;
            case 'DELETE':
                return $this->deleteChassi($route);
                break;
            default:
                return ['status' => 405];
                break;
        }
    }

    function chassiGet()
    {
        $sql = 'SELECT * FROM chassi';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function requireUser()
    {
        if (!Auth::currentUser()) {
            return ['status' => 403, 'error' => 'Доступ запрещён — только сотрудники'];
        }
        return null;
    }

    function chassiPost()
    {
        if ($err = $this->requireUser()) return $err;
        $sql = 'INSERT INTO chassi (chassi_nummer, tuf, esp)
                VALUES (:chassi_nummer, :tuf, :esp)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':chassi_nummer', $this->chassi_nummer);
        $stmt->bindValue(':tuf', $this->tuf);
        $stmt->bindValue(':esp', $this->esp);
        if ($stmt->execute()) {
            $id_chassi = $this->db->lastInsertId();
            return [
                'status' => 201,
                'message' => 'chassi добавлен',
                'chassi' => [
                    'id_chassi' => $id_chassi,
                    'chassi_nummer' => $this->chassi_nummer,
                    'tuf' => $this->tuf,
                    'esp' => $this->esp,
                ]
            ];
        }
        return ['status' => 400, 'error' => 'Ошибка регистрации chassi'];
    }

    function chassiPut()
    {
        if ($err = $this->requireUser()) return $err;
        $sql = 'UPDATE chassi
                    SET chassi_nummer = :chassi_nummer,   
                       tuf = :tuf, 
                       esp = :esp
                 WHERE id_chassi = :id_chassi';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_chassi', $this->id_chassi);
        $stmt->bindValue(':chassi_nummer', $this->chassi_nummer);
        $stmt->bindValue(':tuf', $this->tuf);
        $stmt->bindValue(':esp', $this->esp);
        if ($stmt->execute()) {
            return ['status' => 200];
        }
        return ['status' => 400];
    }

    function deleteChassi($route)
    {
        if ($err = $this->requireUser()) return $err;
        $id = $route[1] ?? $this->id_chassi ?? null;
        
        if (!$id) {
            return ['status' => 400, 'error' => 'ID_chassi required'];
        }
        
        $sql = 'DELETE FROM chassi WHERE id_chassi = :id_chassi';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_chassi', $id);
        
        if ($stmt->execute()) {
           return ['status' => 200, 'message' => 'Chassi успешно удален'];
        } else {
            $errorInfo = $stmt->errorInfo();
           error_log('Ошибка удаления Chassi: ' . $errorInfo[2]);
            return ['status' => 400, 'error' => 'Ошибка удаления Chassi'];
        }
    }

    function getReqData()
    {
        $raw = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if(stripos($contentType, 'application/json') !== false) {
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        parse_str($raw, $out);
        return $out;
    }

    function hydrateForm($data)
    {
        if (isset($data['id_chassi'])) {
            $this->id_chassi = $data['id_chassi'];
        }
        if (isset($data['chassi_nummer'])) {
            $this->chassi_nummer = $data['chassi_nummer'];
        }
        if (isset($data['tuf'])) {
            $this->tuf = $data['tuf'];
        }
        if (isset($data['esp'])) {
            $this->esp = $data['esp'];
        }
    }
}
