<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

class Departments
{
    private $db;
    private $id;
    private $name;
    private $requesterId;

    function __construct($id = null, $name = '', $requesterId = null)
    {
        $this->db = DB::getInstance();
        $this->id = $id;
        $this->name = $name;
        $this->requesterId = $requesterId;
    }

    function verifyMethod($method, $route)
    {
        switch ($method) {
            case 'GET':
                return $this->getAll();

            case 'POST':
                if (!$this->isAdmin()) {
                    return ['status' => 403, 'error' => 'Только администратор может создавать отделы'];
                }
                $this->hydrate($this->getReqData());
                return $this->create();

            case 'PUT':
                if (!$this->isAdmin()) {
                    return ['status' => 403, 'error' => 'Только администратор может редактировать отделы'];
                }
                $this->hydrate($this->getReqData());
                if (empty($this->id)) {
                    $this->id = $route[1] ?? null;
                }
                return $this->update();

            case 'DELETE':
                if (!$this->isAdmin()) {
                    return ['status' => 403, 'error' => 'Только администратор может удалять отделы'];
                }
                $id = $route[1] ?? $this->id ?? null;
                return $this->delete($id);

            default:
                return ['status' => 405];
        }
    }

    private function isAdmin()
    {
        // Раньше требующий проверки id брался из тела запроса ($this->requesterId),
        // что позволяло любому выдать себя за админа. Теперь источник истины — это
        // личность, подтверждённая bearer-токеном на этапе авторизации в index.php.
        $current = Auth::currentUser();
        return $current && strtolower(trim($current['role'] ?? '')) === 'admin';
    }

    function getAll()
    {
        $stmt = $this->db->prepare('SELECT * FROM departments ORDER BY name');
        $stmt->execute();
        return ['status' => 200, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    function create()
    {
        if (empty($this->name)) {
            return ['status' => 400, 'error' => 'Название отдела обязательно'];
        }

        $stmt = $this->db->prepare('INSERT INTO departments (name) VALUES (:name)');
        $stmt->bindValue(':name', $this->name);

        try {
            if ($stmt->execute()) {
                return [
                    'status' => 201,
                    'department' => ['id' => $this->db->lastInsertId(), 'name' => $this->name]
                ];
            }
        } catch (PDOException $e) {
            return ['status' => 400, 'error' => 'Отдел с таким названием уже существует'];
        }

        return ['status' => 400, 'error' => 'Не удалось создать отдел'];
    }

    function update()
    {
        if (empty($this->id) || empty($this->name)) {
            return ['status' => 400, 'error' => 'id и название обязательны'];
        }

        $stmt = $this->db->prepare('UPDATE departments SET name = :name WHERE id = :id');
        $stmt->bindValue(':name', $this->name);
        $stmt->bindValue(':id', $this->id);

        try {
            if ($stmt->execute()) {
                return ['status' => 200];
            }
        } catch (PDOException $e) {
            return ['status' => 400, 'error' => 'Отдел с таким названием уже существует'];
        }

        return ['status' => 400, 'error' => 'Не удалось обновить отдел'];
    }

    function delete($id)
    {
        if (empty($id)) {
            return ['status' => 400, 'error' => 'ID обязателен'];
        }

        $stmt = $this->db->prepare('DELETE FROM departments WHERE id = :id');
        $stmt->bindValue(':id', $id);

        try {
            if ($stmt->execute()) {
                return ['status' => 200];
            }
        } catch (PDOException $e) {
            return ['status' => 400, 'error' => 'Нельзя удалить отдел: есть связанные сотрудники или задачи'];
        }

        return ['status' => 400, 'error' => 'Не удалось удалить отдел'];
    }

    function getReqData()
    {
        $raw = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        parse_str($raw, $out);
        return is_array($out) ? $out : [];
    }

    function hydrate($data)
    {
        if (isset($data['id'])) {
            $this->id = $data['id'];
        }
        if (isset($data['name'])) {
            $this->name = $data['name'];
        }
    }
}
