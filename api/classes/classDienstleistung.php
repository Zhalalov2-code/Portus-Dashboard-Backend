<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

class Dienstleistung
{
    private $db;
    private $id;
    private $name;
    private $created_at;

    private $fields = [];

    function __construct($id = null, $name = '', $created_at = '')
    {
        $this->db = DB::getInstance();
        $this->id = $id;
        $this->name = $name;
        $this->created_at = $created_at;
    }

    function verifyMethod($method, $route)
    {
        $res1 = $route[1] ?? null;
        switch ($method) {
            case 'GET':
                return $this->dienstleistungGet();
            case 'POST':
                if ($res1 === null) {
                    $data = $this->getReqData();
                    $this->hydrateForm($data);
                    return $this->dienstleistungPost();
                }
                break;
            case 'PUT':
                $data = $this->getReqData();
                $this->hydrateForm($data);
                if ($res1 !== null) {
                    $this->id = $res1;
                }
                return $this->dienstleistungPut();
            case 'DELETE':
                return $this->deleteDienstleistung($route);
            default:
                return ['status' => 405];
        }
    }


    private function getReqData()
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        return $data;
    }

    private function hydrateForm($data)
    {
        $this->id = $data['id'] ?? $data['id_dienstleistung'] ?? $this->id;
        $this->name = $data['name'] ?? $this->name;
        $this->created_at = $data['created_at'] ?? $this->created_at;

        if (isset($data['name'])) {
            $this->fields['name'] = $data['name'];
        }
        if (isset($data['created_at'])) {
            $this->fields['created_at'] = $data['created_at'];
        }
    }

    private function dienstleistungGet()
    {
        $where = [];
        $params = [];
        $search = $_GET['search'] ?? null;
        if ($search !== null && $search !== '') {
            $where[] = 'name LIKE :search';
            $params[':search'] = "%$search%";
        }
        $sql = 'SELECT * FROM dienstleistung' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    private function requireUser()
    {
        if (!Auth::currentUser()) {
            http_response_code(403);
            return ['status' => 403, 'error' => 'Доступ запрещён — только сотрудники'];
        }
        return null;
    }

    private function dienstleistungPost()
    {
        if ($err = $this->requireUser())
            return $err;

        if (empty($this->name)) {
            http_response_code(400);
            return ['status' => 400, 'error' => 'Name ist erforderlich'];
        }

        // Значения по умолчанию для полей, которые не пришли с формы.
        $defaults = [
            'name' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $vals = array_merge($defaults, $this->fields);
        $cols = array_keys($defaults);

        $colList = implode(', ', $cols);
        $placeholders = implode(', ', array_map(fn($c) => ':' . $c, $cols));
        $sql = "INSERT INTO dienstleistung ($colList) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        foreach ($cols as $c) {
            $stmt->bindValue(':' . $c, $vals[$c]);
        }

        if ($stmt->execute()) {
            http_response_code(201);
            return [
                'status' => 201,
                'message' => 'dienstleistung hinzugefügt',
                'dienstleistung' => array_merge(['id' => $this->db->lastInsertId()], $vals),
            ];
        }
        http_response_code(400);
        return ['status' => 400, 'error' => 'Fehler beim Hinzufügen des dienstleistung'];
    }

    private function dienstleistungPut()
    {
        if ($err = $this->requireUser())
            return $err;

        if (!$this->id) {
            http_response_code(400);
            return ['status' => 400, 'error' => 'id_dienstleistung erforderlich'];
        }
        if (empty($this->fields)) {
            http_response_code(400);
            return ['status' => 400, 'error' => 'Keine Felder zum Aktualisieren'];
        }

        $set = [];
        foreach ($this->fields as $k => $v) {
            $set[] = "$k = :$k";
        }
        $sql = "UPDATE dienstleistung SET " . implode(', ', $set) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $this->id);
        foreach ($this->fields as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }

        try {
            if ($stmt->execute()) {
                return [
                    'status' => 200,
                    'message' => 'dienstleistung aktualisiert',
                    'dienstleistung' => ['id' => $this->id],
                ];
            }
        } catch (Throwable $e) {
            http_response_code(500);
            return ['status' => 500, 'error' => 'Database error: ' . $e->getMessage()];
        }

        http_response_code(400);
        return ['status' => 400, 'error' => 'Fehler beim Aktualisieren des dienstleistung'];
    }

    private function deleteDienstleistung($route)
    {
        if ($err = $this->requireUser())
            return $err;

        $id = $route[1] ?? null;
        if (!$id) {
            http_response_code(400);
            return ['status' => 400, 'error' => 'id_dienstleistung erforderlich'];
        }

        $this->db->beginTransaction();
        try {
            // Проверка зависимостей: если к услуге привязаны записи в fahrzeug — нельзя удалить.
            // Если таблица fahrzeug не существует, пропускаем проверку.
            try {
                $stmt = $this->db->prepare("SELECT id FROM fahrzeug WHERE id_dienstleistung = :id");
                $stmt->execute([':id' => $id]);
                if ($stmt->rowCount() > 0) {
                    $this->db->rollBack();
                    http_response_code(409);
                    return ['status' => 409, 'error' => 'dienstleistung kann nicht gelöscht werden, da es noch Fahrzeuge hat'];
                }
            } catch (Throwable $e) {
                // fahrzeug table might not exist yet — skip check
            }

            $stmt = $this->db->prepare("DELETE FROM dienstleistung WHERE id = :id");
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() > 0) {
                $this->db->commit();
                return ['status' => 200, 'message' => 'dienstleistung erfolgreich gelöscht'];
            } else {
                $this->db->rollBack();
                http_response_code(404);
                return ['status' => 404, 'error' => 'dienstleistung nicht gefunden'];
            }
        } catch (Throwable $e) {
            $this->db->rollBack();
            http_response_code(500);
            return ['status' => 500, 'error' => 'Fehler beim Löschen des dienstleistung: ' . $e->getMessage()];
        }
    }
}