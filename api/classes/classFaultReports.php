<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Realtime.php';

/**
 * Сообщения о неисправностях: водитель сообщает о проблеме (с фото и признаком
 * «могу/не могу ехать»), сотрудник видит список и закрывает по факту устранения.
 */
class FaultReports
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
            case 'PUT':
                return $this->resolve($route[1] ?? null);
            default:
                return ['status' => 405, 'error' => 'Method not allowed'];
        }
    }

    // Список: сотрудникам — все (фильтр по статусу/машине), водителю — свои.
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
        if (!empty($_GET['status']) && in_array($_GET['status'], ['open', 'resolved'], true)) {
            $where[] = 'status = :st';
            $params[':st'] = $_GET['status'];
        }
        if (!empty($_GET['vehicle_type']) && in_array($_GET['vehicle_type'], ['lkw', 'chassi'], true)) {
            $where[] = 'vehicle_type = :vt';
            $params[':vt'] = $_GET['vehicle_type'];
        }
        if (!empty($_GET['vehicle_nummer'])) {
            $where[] = 'vehicle_nummer = :vn';
            $params[':vn'] = $_GET['vehicle_nummer'];
        }

        $sql = 'SELECT * FROM fault_reports';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY (status = 'open') DESC, created_at DESC LIMIT 500";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return ['status' => 200, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    // Создать сообщение — только водитель (поддерживает фото через multipart).
    function create()
    {
        $self = Auth::currentFahrer();
        if (!$self) {
            return ['status' => 403, 'error' => 'Только водители'];
        }

        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'multipart/form-data') !== false) {
            $d = $_POST;
        } else {
            $raw = file_get_contents('php://input');
            $d = json_decode($raw, true) ?: [];
        }

        $type = $d['vehicle_type'] ?? '';
        $nummer = trim($d['vehicle_nummer'] ?? '');
        $description = trim($d['description'] ?? '');
        $severity = ($d['severity'] ?? '') === 'cannot_drive' ? 'cannot_drive' : 'can_drive';
        if (!in_array($type, ['lkw', 'chassi'], true) || $nummer === '' || $description === '') {
            return ['status' => 400, 'error' => 'Заполните описание и транспорт'];
        }

        // Опциональное фото.
        $photo = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'], true) && $file['size'] <= 5 * 1024 * 1024) {
                $check = @getimagesize($file['tmp_name']);
                if ($check !== false) {
                    $dir = __DIR__ . '/../uploads/faults/';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $photo = uniqid('fault_', true) . '.' . $ext;
                    if (!move_uploaded_file($file['tmp_name'], $dir . $photo)) {
                        $photo = null;
                    }
                }
            }
        }

        $stmt = $this->db->prepare(
            'INSERT INTO fault_reports (id_fahrer, fahrer_name, vehicle_type, vehicle_nummer, description, severity, photo)
             VALUES (:idf, :name, :t, :n, :desc, :sev, :photo)'
        );
        $stmt->bindValue(':idf', $self['id_fahrer']);
        $stmt->bindValue(':name', trim($self['name'] . ' ' . $self['lastname']));
        $stmt->bindValue(':t', $type);
        $stmt->bindValue(':n', $nummer);
        $stmt->bindValue(':desc', $description);
        $stmt->bindValue(':sev', $severity);
        $stmt->bindValue(':photo', $photo);
        $stmt->execute();

        Realtime::entityChanged('fault');
        return ['status' => 201, 'id' => $this->db->lastInsertId()];
    }

    // Закрыть/переоткрыть — только сотрудник.
    function resolve($id)
    {
        if (!Auth::currentUser()) {
            return ['status' => 403, 'error' => 'Только сотрудники'];
        }
        if (!$id) {
            return ['status' => 400, 'error' => 'id обязателен'];
        }
        $raw = file_get_contents('php://input');
        $d = json_decode($raw, true) ?: [];
        $status = ($d['status'] ?? 'resolved') === 'open' ? 'open' : 'resolved';

        $stmt = $this->db->prepare(
            'UPDATE fault_reports
             SET status = :st, resolved_at = ' . ($status === 'resolved' ? 'NOW()' : 'NULL') . '
             WHERE id = :id'
        );
        $stmt->bindValue(':st', $status);
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        Realtime::entityChanged('fault');
        return ['status' => 200];
    }

    /** Кол-во открытых неисправностей по машине (для метки Zustand в админке). */
    public static function openCountByVehicle(PDO $db)
    {
        $stmt = $db->query(
            "SELECT vehicle_type, vehicle_nummer, COUNT(*) AS cnt
             FROM fault_reports WHERE status = 'open'
             GROUP BY vehicle_type, vehicle_nummer"
        );
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['vehicle_type'] . '|' . $r['vehicle_nummer']] = (int) $r['cnt'];
        }
        return $out;
    }
}
