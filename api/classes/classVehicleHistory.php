<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

/**
 * История назначений: какой водитель работал с каким LKW/Chassi и когда.
 *
 * Запись ведётся автоматически из classFahrer при изменении назначения
 * (смена грузовика/прицепа, отцепка, создание водителя с транспортом).
 * Одна строка = один интервал (водитель ↔ машина): started_at..ended_at.
 * ended_at IS NULL означает «назначение активно сейчас».
 */
class VehicleHistory
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
                return $this->historyGet();
            default:
                return ['status' => 405, 'error' => 'Method not allowed'];
        }
    }

    /**
     * Список истории. Сотрудники видят всё (с фильтрами),
     * водитель — только собственную историю.
     * Фильтры (query): vehicle_type=lkw|chassi, vehicle_nummer=..., id_fahrer=...
     */
    function historyGet()
    {
        $staff = Auth::currentUser();
        $self = Auth::currentFahrer();
        if (!$staff && !$self) {
            return ['status' => 403, 'error' => 'Доступ запрещён'];
        }

        $where = [];
        $params = [];

        // Водитель — принудительно только свои записи.
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
        if (!empty($_GET['id_fahrer'])) {
            $where[] = 'id_fahrer = :idf';
            $params[':idf'] = $_GET['id_fahrer'];
        }

        $sql = 'SELECT id, vehicle_type, vehicle_nummer, id_fahrer, fahrer_name, started_at, ended_at
                FROM vehicle_history';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY started_at DESC, id DESC';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------- Статические хелперы записи (вызываются из classFahrer) ----------

    /** Открыть новый интервал назначения (машина получила водителя). */
    public static function open(PDO $db, $type, $nummer, $id_fahrer, $name)
    {
        $nummer = trim((string) $nummer);
        if ($nummer === '') {
            return;
        }

        // У машины может быть только один активный водитель. Если по этой
        // машине остались незакрытые интервалы (например, прошлое назначение
        // не сняли явно, а транспорт передали другому) — закрываем их.
        $closeStale = $db->prepare(
            'UPDATE vehicle_history SET ended_at = NOW()
             WHERE vehicle_type = :type AND vehicle_nummer = :nummer AND ended_at IS NULL'
        );
        $closeStale->bindValue(':type', $type);
        $closeStale->bindValue(':nummer', $nummer);
        $closeStale->execute();

        $stmt = $db->prepare(
            'INSERT INTO vehicle_history (vehicle_type, vehicle_nummer, id_fahrer, fahrer_name, started_at)
             VALUES (:type, :nummer, :id_fahrer, :name, NOW())'
        );
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':nummer', $nummer);
        $stmt->bindValue(':id_fahrer', $id_fahrer);
        $stmt->bindValue(':name', $name);
        $stmt->execute();
    }

    /** Закрыть активный интервал (водитель ушёл с машины). */
    public static function close(PDO $db, $type, $nummer, $id_fahrer)
    {
        $nummer = trim((string) $nummer);
        if ($nummer === '') {
            return;
        }
        $stmt = $db->prepare(
            'UPDATE vehicle_history SET ended_at = NOW()
             WHERE vehicle_type = :type AND vehicle_nummer = :nummer
               AND id_fahrer = :id_fahrer AND ended_at IS NULL'
        );
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':nummer', $nummer);
        $stmt->bindValue(':id_fahrer', $id_fahrer);
        $stmt->execute();
    }

    /** Закрыть все активные интервалы водителя (например, при его удалении). */
    public static function closeAllForFahrer(PDO $db, $id_fahrer)
    {
        $stmt = $db->prepare(
            'UPDATE vehicle_history SET ended_at = NOW()
             WHERE id_fahrer = :id_fahrer AND ended_at IS NULL'
        );
        $stmt->bindValue(':id_fahrer', $id_fahrer);
        $stmt->execute();

        require_once __DIR__ . '/Realtime.php';
        Realtime::entityChanged('vehicle_history');
    }

    /**
     * Реакция на изменение назначения водителя: закрываем старые интервалы,
     * открываем новые — по LKW и Chassi отдельно. Вызывать после успешного UPDATE.
     */
    public static function onFahrerChange(PDO $db, $id_fahrer, $name, $oldLkw, $newLkw, $oldChassi, $newChassi)
    {
        $changed = false;

        if (trim((string) $oldLkw) !== trim((string) $newLkw)) {
            self::close($db, 'lkw', $oldLkw, $id_fahrer);
            self::open($db, 'lkw', $newLkw, $id_fahrer, $name);
            $changed = true;
        }
        if (trim((string) $oldChassi) !== trim((string) $newChassi)) {
            self::close($db, 'chassi', $oldChassi, $id_fahrer);
            self::open($db, 'chassi', $newChassi, $id_fahrer, $name);
            $changed = true;
        }

        if ($changed) {
            require_once __DIR__ . '/Realtime.php';
            Realtime::entityChanged('vehicle_history');
        }
    }
}
