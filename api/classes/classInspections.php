<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Realtime.php';

/**
 * Предрейсовый осмотр: водитель проходит чек-лист (пункты OK/не OK) перед
 * выездом, результат сохраняется и виден сотрудникам в админке.
 */
class Inspections
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    function verifyMethod($method, $route)
    {
        switch ($method) {
            case 'GET':
                return $this->getList();
            case 'POST':
                return $this->create();
            default:
                return ['status' => 405, 'error' => 'Method not allowed'];
        }
    }

    private function body()
    {
        $raw = file_get_contents('php://input');
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $d = json_decode($raw, true);
            return is_array($d) ? $d : [];
        }
        parse_str($raw, $out);
        return $out;
    }

    // Список: сотрудникам — все (можно фильтр по машине), водителю — свои.
    function getList()
    {
        $staff = Auth::currentUser();
        $self = Auth::currentFahrer();
        if (!$staff && !$self) {
            return ['status' => 403, 'error' => 'Доступ запрещён'];
        }

        $where = [];
        $params = [];
        if (!$staff && $self) {
            $where[] = 'id_fahrer = :own';
            $params[':own'] = $self['id_fahrer'];
        }
        if (!empty($_GET['vehicle_type']) && in_array($_GET['vehicle_type'], ['lkw', 'chassi'], true)) {
            $where[] = 'vehicle_type = :vt';
            $params[':vt'] = $_GET['vehicle_type'];
        }
        if (!empty($_GET['vehicle_nummer'])) {
            $where[] = 'vehicle_nummer = :vn';
            $params[':vn'] = $_GET['vehicle_nummer'];
        }

        $sql = 'SELECT * FROM inspections';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 500';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['items'] = json_decode($r['items'], true) ?: [];
            $r['all_ok'] = (int) $r['all_ok'];
        }
        return ['status' => 200, 'data' => $rows];
    }

    // Создать осмотр — только водитель.
    function create()
    {
        $self = Auth::currentFahrer();
        if (!$self) {
            return ['status' => 403, 'error' => 'Только водители'];
        }
        $d = $this->body();
        $type = $d['vehicle_type'] ?? '';
        $nummer = trim($d['vehicle_nummer'] ?? '');
        $items = $d['items'] ?? [];
        if (!in_array($type, ['lkw', 'chassi'], true) || $nummer === '' || !is_array($items) || !$items) {
            return ['status' => 400, 'error' => 'Некорректные данные осмотра'];
        }

        // Нормализуем пункты и считаем all_ok на сервере (не доверяем клиенту).
        $clean = [];
        $allOk = true;
        foreach ($items as $it) {
            $ok = !empty($it['ok']);
            if (!$ok) {
                $allOk = false;
            }
            $clean[] = [
                'key' => (string) ($it['key'] ?? ''),
                'label' => (string) ($it['label'] ?? ''),
                'ok' => $ok,
            ];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO inspections (id_fahrer, fahrer_name, vehicle_type, vehicle_nummer, items, all_ok, comment)
             VALUES (:idf, :name, :t, :n, :items, :all_ok, :comment)'
        );
        $stmt->bindValue(':idf', $self['id_fahrer']);
        $stmt->bindValue(':name', trim($self['name'] . ' ' . $self['lastname']));
        $stmt->bindValue(':t', $type);
        $stmt->bindValue(':n', $nummer);
        $stmt->bindValue(':items', json_encode($clean, JSON_UNESCAPED_UNICODE));
        $stmt->bindValue(':all_ok', $allOk ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':comment', trim($d['comment'] ?? '') ?: null);
        $stmt->execute();

        Realtime::entityChanged('inspection');
        return ['status' => 201, 'id' => $this->db->lastInsertId(), 'all_ok' => $allOk];
    }
}
