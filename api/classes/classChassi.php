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

    // Поля с формы (заполняются в hydrateForm). Используются для создания
    // и для ЧАСТИЧНОГО обновления — обновляются только пришедшие колонки,
    // чтобы обычное редактирование не затирало инфо-поля (оси/ADR), и наоборот.
    private $fields = [];

    // Белый список колонок, которые можно писать через API.
    private const ALLOWED = [
        'chassi_nummer',
        'tuf',
        'esp',
        'adr',
        'a_schild',
        'achse1_links',
        'achse1_rechts',
        'achse2_links',
        'achse2_rechts',
        'achse3_links',
        'achse3_rechts',
    ];

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
                if ($res1 === null) {
                    $data = $this->getReqData();
                    $this->hydrateForm($data);
                    return $this->chassiPost();
                }
                break;
            case 'PUT':
                $data = $this->getReqData();
                $this->hydrateForm($data);
                return $this->chassiPut();
            case 'DELETE':
                return $this->deleteChassi($route);
            default:
                return ['status' => 405];
        }
    }

    function chassiGet()
    {
        $sql = 'SELECT * FROM chassi';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Булевы поля приводим к int, чтобы фронт получал 0/1, а не "0"/"1".
        foreach ($result as &$row) {
            $row['adr'] = (int) ($row['adr'] ?? 0);
            $row['a_schild'] = (int) ($row['a_schild'] ?? 0);
        }
        return $result;
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
        if ($err = $this->requireUser())
            return $err;

        // Значения по умолчанию для полей, которые не пришли с формы.
        $defaults = [
            'chassi_nummer' => '',
            'tuf' => null,
            'esp' => null,
            'adr' => 0,
            'a_schild' => 0,
            'achse1_links' => 'OK',
            'achse1_rechts' => 'OK',
            'achse2_links' => 'OK',
            'achse2_rechts' => 'OK',
            'achse3_links' => 'OK',
            'achse3_rechts' => 'OK',
        ];
        $vals = array_merge($defaults, $this->fields);
        $cols = array_keys($defaults);

        $colList = implode(', ', $cols);
        $placeholders = implode(', ', array_map(fn($c) => ':' . $c, $cols));
        $sql = "INSERT INTO chassi ($colList) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        foreach ($cols as $c) {
            $stmt->bindValue(':' . $c, $vals[$c]);
        }

        if ($stmt->execute()) {
            http_response_code(201);
            return [
                'status' => 201,
                'message' => 'chassi добавлен',
                'chassi' => array_merge(['id_chassi' => $this->db->lastInsertId()], $vals),
            ];
        }
        return ['status' => 400, 'error' => 'Ошибка регистрации chassi'];
    }

    function chassiPut()
    {
        if ($err = $this->requireUser())
            return $err;

        if (!$this->id_chassi) {
            return ['status' => 400, 'error' => 'id_chassi обязателен'];
        }
        if (empty($this->fields)) {
            return ['status' => 400, 'error' => 'Нет полей для обновления'];
        }

        // Частичный UPDATE: только переданные колонки (имена — из белого списка).
        $set = [];
        foreach ($this->fields as $col => $_val) {
            $set[] = "$col = :$col";
        }
        $sql = 'UPDATE chassi SET ' . implode(', ', $set) . ' WHERE id_chassi = :id_chassi';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_chassi', $this->id_chassi);
        foreach ($this->fields as $col => $val) {
            $stmt->bindValue(':' . $col, $val);
        }

        if ($stmt->execute()) {
            return ['status' => 200];
        }
        return ['status' => 400];
    }

    function deleteChassi($route)
    {
        if ($err = $this->requireUser())
            return $err;
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

        if (stripos($contentType, 'application/json') !== false) {
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
        foreach (self::ALLOWED as $col) {
            if (array_key_exists($col, $data)) {
                $this->fields[$col] = $this->sanitize($col, $data[$col]);
            }
        }
    }

    // Булевы поля -> 0/1, статусы осей -> только 'OK' / 'Auf Ersatz'.
    private function sanitize($col, $value)
    {
        if ($col === 'adr' || $col === 'a_schild') {
            return ($value === true || $value === 1 || $value === '1' || $value === 'true') ? 1 : 0;
        }
        if (strpos($col, 'achse') === 0) {
            // Статус оси — свободный текст (его заполняет контролёр).
            // Пусто трактуем как 'OK'.
            $v = trim((string) $value);
            return $v === '' ? 'OK' : $v;
        }
        return $value;
    }
}
