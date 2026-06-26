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

    // Поля, пришедшие с формы (заполняются в hydrateForm). Используются
    // для создания и для ЧАСТИЧНОГО обновления — обновляются только те
    // колонки, что реально пришли в запросе, чтобы, например, обычное
    // редактирование (номер/TÜF/SP) не затирало статусы осей, и наоборот.
    private $fields = [];

    // Белый список колонок, которые можно писать через API.
    // Имена берутся только отсюда — в SQL не попадают произвольные ключи.
    private const ALLOWED = [
        'tuf',
        'esp',
        'lkw_nummer',
        'adr',
        'a_schild',
        'feuerloescher',
        'achse1_links',
        'achse1_rechts',
        'achse2_links',
        'achse2_rechts',
        'achse3_links',
        'achse3_rechts',
    ];

    public function __construct($id_lkw = null, $tuf = null, $esp = null, $lkw_nummer = '')
    {
        $this->db = DB::getInstance();
        $this->id_lkw = $id_lkw;
        $this->tuf = $tuf;
        $this->esp = $esp;
        $this->lkw_nummer = $lkw_nummer;
    }

    function verifyMethod($method, $route)
    {
        $res1 = $route[1] ?? null;
        switch ($method) {
            case 'GET':
                return $this->getLkw();
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
            case 'DELETE':
                return $this->deleteLkw();
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
        // Булевы поля приводим к int, чтобы фронт получал 0/1, а не "0"/"1".
        foreach ($result as &$row) {
            $row['adr'] = (int) ($row['adr'] ?? 0);
            $row['a_schild'] = (int) ($row['a_schild'] ?? 0);
            $row['feuerloescher'] = (int) ($row['feuerloescher'] ?? 0);
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

    function postLkw()
    {
        if ($err = $this->requireUser())
            return $err;

        // Значения по умолчанию для полей, которые не пришли с формы создания
        // (форма создания заполняет только номер/TÜF/SP).
        $defaults = [
            'tuf' => null,
            'esp' => null,
            'lkw_nummer' => '',
            'adr' => 0,
            'a_schild' => 0,
            'feuerloescher' => 0,
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
        $sql = "INSERT INTO lkw ($colList) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        foreach ($cols as $c) {
            $stmt->bindValue(':' . $c, $vals[$c]);
        }

        if ($stmt->execute()) {
            http_response_code(201);
            return [
                'status' => 201,
                'message' => 'LKW создан успешно',
                'lkw' => array_merge(['id_lkw' => $this->db->lastInsertId()], $vals),
            ];
        }
        return ['status' => 400, 'message' => 'Ошибка при создании LKW'];
    }

    function putlkw()
    {
        if ($err = $this->requireUser())
            return $err;

        if (!$this->id_lkw) {
            return ['status' => 400, 'message' => 'id_lkw обязателен'];
        }
        if (empty($this->fields)) {
            return ['status' => 400, 'message' => 'Нет полей для обновления'];
        }

        // Частичный UPDATE: только переданные колонки (имена — из белого списка).
        $set = [];
        foreach ($this->fields as $col => $_val) {
            $set[] = "$col = :$col";
        }
        $sql = 'UPDATE lkw SET ' . implode(', ', $set) . ' WHERE id_lkw = :id_lkw';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_lkw', $this->id_lkw);
        foreach ($this->fields as $col => $val) {
            $stmt->bindValue(':' . $col, $val);
        }

        if ($stmt->execute()) {
            return ['status' => 200, 'message' => 'LKW успешно обновлен'];
        }
        return ['status' => 400, 'message' => 'Ошибка при обновлении LKW'];
    }

    function deleteLkw()
    {
        if ($err = $this->requireUser())
            return $err;
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
        foreach (self::ALLOWED as $col) {
            if (array_key_exists($col, $data)) {
                $this->fields[$col] = $this->sanitize($col, $data[$col]);
            }
        }
    }

    // Приводим значения к ожидаемому виду: булевы — к 0/1, статусы осей —
    // только к допустимым значениям 'OK' / 'Auf Ersatz'.
    private function sanitize($col, $value)
    {
        if ($col === 'adr' || $col === 'a_schild' || $col === 'feuerloescher') {
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
