<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

class Kunde
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
                return $this->kundenGet();
            case 'POST':
                if ($res1 === null) {
                    $data = $this->getReqData();
                    $this->hydrateForm($data);
                    return $this->kundenPost();
                }
                break;
            case 'PUT':
                $data = $this->getReqData();
                $this->hydrateForm($data);
                if ($res1 !== null) {
                    $this->id = $res1;
                }
                return $this->kundenPut();
            case 'DELETE':
                return $this->deleteKunden($route);
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
        $this->id = $data['id'] ?? $data['id_kunden'] ?? $this->id;
        $this->name = $data['name'] ?? $this->name;
        $this->created_at = $data['created_at'] ?? $this->created_at;

        if (isset($data['name'])) {
            $this->fields['name'] = $data['name'];
        }
        if (isset($data['created_at'])) {
            $this->fields['created_at'] = $data['created_at'];
        }
    }

    private function kundenGet()
    {
        $where = [];
        $params = [];
        $search = $_GET['search'] ?? null;
        if ($search !== null && $search !== '') {
            $where[] = 'name LIKE :search';
            $params[':search'] = "%$search%";
        }
        $sql = 'SELECT * FROM kunden' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
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

    private function kundenPost()
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
        $sql = "INSERT INTO kunden ($colList) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        foreach ($cols as $c) {
            $stmt->bindValue(':' . $c, $vals[$c]);
        }

        if ($stmt->execute()) {
            http_response_code(201);
            return [
                'status' => 201,
                'message' => 'kunden hinzugefügt',
                'kunden' => array_merge(['id' => $this->db->lastInsertId()], $vals),
            ];
        }
        http_response_code(400);
        return ['status' => 400, 'error' => 'Fehler beim Hinzufügen des kunden'];
    }

    private function kundenPut()
    {
        if ($err = $this->requireUser())
            return $err;

        if (!$this->id) {
            http_response_code(400);
            return ['status' => 400, 'error' => 'id_kunden erforderlich'];
        }
        if (empty($this->fields)) {
            http_response_code(400);
            return ['status' => 400, 'error' => 'Keine Felder zum Aktualisieren'];
        }

        $set = [];
        foreach ($this->fields as $k => $v) {
            $set[] = "$k = :$k";
        }
        $sql = "UPDATE kunden SET " . implode(', ', $set) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $this->id);
        foreach ($this->fields as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }

        if ($stmt->execute()) {
            return [
                'status' => 200,
                'message' => 'kunden aktualisiert',
                'kunden' => ['id' => $this->id],
            ];
        }
        http_response_code(400);
        return ['status' => 400, 'error' => 'Fehler beim Aktualisieren des kunden'];
    }

    private function deleteKunden($route)
    {
        if ($err = $this->requireUser())
            return $err;

        $id = $route[1] ?? null;
        if (!$id) {
            http_response_code(400);
            return ['status' => 400, 'error' => 'id_kunden erforderlich'];
        }

        $this->db->beginTransaction();
        try {
            // Проверка зависимостей: если у клиента есть привязанные записи в fahrzeug — нельзя удалить.
            // Если таблица fahrzeug не существует, пропускаем проверку.
            try {
                $stmt = $this->db->prepare("SELECT id FROM fahrzeug WHERE id_kunden = :id");
                $stmt->execute([':id' => $id]);
                if ($stmt->rowCount() > 0) {
                    $this->db->rollBack();
                    http_response_code(409);
                    return ['status' => 409, 'error' => 'kunden kann nicht gelöscht werden, da es noch Fahrzeuge hat'];
                }
            } catch (Throwable $e) {
                // fahrzeug table might not exist yet — skip check
            }

            $stmt = $this->db->prepare("DELETE FROM kunden WHERE id = :id");
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() > 0) {
                $this->db->commit();
                return ['status' => 200, 'message' => 'kunden erfolgreich gelöscht'];
            } else {
                $this->db->rollBack();
                http_response_code(404);
                return ['status' => 404, 'error' => 'kunden nicht gefunden'];
            }
        } catch (Throwable $e) {
            $this->db->rollBack();
            http_response_code(500);
            return ['status' => 500, 'error' => 'Fehler beim Löschen des kunden: ' . $e->getMessage()];
        }
    }
}