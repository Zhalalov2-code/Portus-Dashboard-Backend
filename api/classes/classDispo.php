<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

/**
 * Dispo (диспетчеризация) — таблица заказов клиентов.
 *
 * Заменяет Excel-таблицу, в которую отдел Dispo заносит заказы: статус,
 * период, клиент, услуга, номер позиции/контейнера, товар, количество,
 * цена, итог (вычисляется в БД), поступление счёта, примечания.
 *
 * Доступ к модулю: admin ИЛИ сотрудник, чей отдел содержит "dispo"
 * (без ограничения по роли внутри отдела — зеркалит Inventory::canUseWarehouse()
 * / src/utils/roles.ts::canUseDispo()).
 */
class Dispo
{
    const STATUSES = ['in_bearbeitung', 'erledigt', 'teil_erledigt', 'abgerechnet', 'teil_abgerechnet', 'storno'];

    private $db;
    private $data;

    function __construct($data = [])
    {
        $this->db = DB::getInstance();
        $body = $this->parseBody();
        $this->data = array_merge(is_array($data) ? $data : [], $body);
    }

    private function parseBody()
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        parse_str($raw, $out);
        return is_array($out) ? $out : [];
    }

    private function input($key, $default = null)
    {
        if (array_key_exists($key, $this->data) && $this->data[$key] !== '') {
            return $this->data[$key];
        }
        return $default;
    }

    // ----------------------------- Роутинг -----------------------------

    function verifyMethod($method, $route)
    {
        $resource = $route[1] ?? null;
        $id = is_numeric($resource) ? (int) $resource : null;

        if ($resource === 'orders' || $id !== null) {
            $thirdId = $route[2] ?? null;
            $orderId = $id ?? (is_numeric($thirdId) ? (int) $thirdId : null);

            switch ($method) {
                case 'GET':
                    return $orderId ? $this->getOrder($orderId) : $this->getOrders();
                case 'POST':
                    return $this->createOrder();
                case 'PUT':
                    if (!$orderId) {
                        return ['status' => 400, 'error' => 'Auftrags-ID ist erforderlich'];
                    }
                    return $this->updateOrder($orderId);
                case 'DELETE':
                    if (!$orderId) {
                        return ['status' => 400, 'error' => 'Auftrags-ID ist erforderlich'];
                    }
                    return $this->deleteOrder($orderId);
                default:
                    return ['status' => 405];
            }
        }

        return ['status' => 404, 'error' => 'Unbekannte Dispo-Route'];
    }

    // --------------------------- Права доступа ---------------------------

    /**
     * Доступ к Dispo: явный грант "dispo" в user_module_access (admin
     * проходит всегда). Зеркалит src/utils/roles.ts::hasModule() на фронтенде.
     */
    private function canUseDispo($user)
    {
        if (!$user) {
            return false;
        }
        return Auth::hasModule($this->db, 'dispo');
    }

    private function requireAccess()
    {
        $user = Auth::currentUser();
        if (!$user) {
            return null;
        }
        return $this->canUseDispo($user) ? $user : false;
    }

    // ------------------------------ Заказы ------------------------------

    function getOrders()
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf Dispo'];
        }

        $where = [];
        $params = [];

        $status = $this->input('status');
        if ($status !== null) {
            if (!in_array($status, self::STATUSES, true)) {
                return ['status' => 400, 'error' => 'Ungültiger Status'];
            }
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        $kunde = $this->input('kunde');
        if ($kunde !== null) {
            $where[] = 'kunde = :kunde';
            $params[':kunde'] = $kunde;
        }

        $dienstleistung = $this->input('dienstleistung');
        if ($dienstleistung !== null) {
            // dienstleistung speichert mehrere Werte kommagetrennt (z.B. "ALL IN,Zoll") —
            // FIND_IN_SET matcht genau ein Element der Liste, kein Substring-Treffer.
            $where[] = 'FIND_IN_SET(:dienstleistung, dienstleistung) > 0';
            $params[':dienstleistung'] = $dienstleistung;
        }

        $highlighted = $this->input('highlighted');
        if ($highlighted !== null) {
            $where[] = 'highlighted = :highlighted';
            $params[':highlighted'] = (int) $highlighted ? 1 : 0;
        }

        $vonFrom = $this->input('von_from');
        if ($vonFrom !== null) {
            $where[] = 'von >= :von_from';
            $params[':von_from'] = $vonFrom;
        }
        $vonTo = $this->input('von_to');
        if ($vonTo !== null) {
            $where[] = 'von <= :von_to';
            $params[':von_to'] = $vonTo;
        }

        $search = $this->input('search');
        if ($search !== null) {
            $where[] = '(kunde LIKE :search OR auftrag LIKE :search OR pos_nr LIKE :search OR cont_nummer LIKE :search OR ware LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = 'SELECT * FROM dispo_orders';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY von DESC, id DESC';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $rows = array_map([$this, 'castOrder'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        return ['status' => 200, 'data' => $rows];
    }

    function getOrder($id)
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf Dispo'];
        }

        $order = $this->fetchOrder($id);
        if (!$order) {
            return ['status' => 404, 'error' => 'Auftrag nicht gefunden'];
        }
        return ['status' => 200, 'data' => $this->castOrder($order)];
    }

    function createOrder()
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf Dispo'];
        }

        $kunde = trim((string) $this->input('kunde', ''));
        if ($kunde === '') {
            return ['status' => 400, 'error' => 'Kunde ist erforderlich'];
        }

        $status = $this->input('status', 'in_bearbeitung');
        if (!in_array($status, self::STATUSES, true)) {
            return ['status' => 400, 'error' => 'Ungültiger Status'];
        }

        $anzahl = $this->input('anzahl', 0);
        if (!is_numeric($anzahl) || (float) $anzahl < 0) {
            return ['status' => 400, 'error' => 'Anzahl muss eine nicht-negative Zahl sein'];
        }
        $preis = $this->input('preis', 0);
        if (!is_numeric($preis) || (float) $preis < 0) {
            return ['status' => 400, 'error' => 'Preis muss eine nicht-negative Zahl sein'];
        }

        $userId = Auth::currentUser()['id'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO dispo_orders
                (status, von, bis, kunde, dienstleistung, auftrag, pos_nr, cont_nummer, ware,
                 anzahl, preis, eingang_rechnung, bemerkungen, highlighted, created_by, updated_by)
             VALUES
                (:status, :von, :bis, :kunde, :dienstleistung, :auftrag, :pos_nr, :cont_nummer, :ware,
                 :anzahl, :preis, :eingang_rechnung, :bemerkungen, :highlighted, :created_by, :updated_by)'
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':von', $this->input('von'));
        $stmt->bindValue(':bis', $this->input('bis'));
        $stmt->bindValue(':kunde', $kunde);
        $stmt->bindValue(':dienstleistung', $this->input('dienstleistung'));
        $stmt->bindValue(':auftrag', $this->input('auftrag'));
        $stmt->bindValue(':pos_nr', $this->input('pos_nr'));
        $stmt->bindValue(':cont_nummer', $this->input('cont_nummer'));
        $stmt->bindValue(':ware', $this->input('ware'));
        $stmt->bindValue(':anzahl', (float) $anzahl);
        $stmt->bindValue(':preis', (float) $preis);
        $stmt->bindValue(':eingang_rechnung', $this->input('eingang_rechnung'));
        $stmt->bindValue(':bemerkungen', $this->input('bemerkungen'));
        $stmt->bindValue(':highlighted', (int) $this->input('highlighted', 0) ? 1 : 0);
        $stmt->bindValue(':created_by', $userId);
        $stmt->bindValue(':updated_by', $userId);
        $stmt->execute();

        $order = $this->fetchOrder($this->db->lastInsertId());
        return ['status' => 201, 'data' => $this->castOrder($order)];
    }

    function updateOrder($id)
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf Dispo'];
        }

        $order = $this->fetchOrder($id);
        if (!$order) {
            return ['status' => 404, 'error' => 'Auftrag nicht gefunden'];
        }

        $kunde = $this->input('kunde');
        $kunde = ($kunde !== null) ? trim((string) $kunde) : $order['kunde'];
        if ($kunde === '') {
            return ['status' => 400, 'error' => 'Kunde ist erforderlich'];
        }

        $status = $this->input('status', $order['status']);
        if (!in_array($status, self::STATUSES, true)) {
            return ['status' => 400, 'error' => 'Ungültiger Status'];
        }

        $anzahl = $this->input('anzahl');
        if ($anzahl === null) {
            $anzahl = $order['anzahl'];
        } elseif (!is_numeric($anzahl) || (float) $anzahl < 0) {
            return ['status' => 400, 'error' => 'Anzahl muss eine nicht-negative Zahl sein'];
        }
        $preis = $this->input('preis');
        if ($preis === null) {
            $preis = $order['preis'];
        } elseif (!is_numeric($preis) || (float) $preis < 0) {
            return ['status' => 400, 'error' => 'Preis muss eine nicht-negative Zahl sein'];
        }

        $field = function ($key) use ($order) {
            return array_key_exists($key, $this->data) ? $this->input($key) : $order[$key];
        };

        $userId = Auth::currentUser()['id'] ?? null;

        $stmt = $this->db->prepare(
            'UPDATE dispo_orders SET
                status = :status, von = :von, bis = :bis, kunde = :kunde,
                dienstleistung = :dienstleistung, auftrag = :auftrag, pos_nr = :pos_nr,
                cont_nummer = :cont_nummer, ware = :ware, anzahl = :anzahl, preis = :preis,
                eingang_rechnung = :eingang_rechnung, bemerkungen = :bemerkungen,
                highlighted = :highlighted, updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':von', $field('von'));
        $stmt->bindValue(':bis', $field('bis'));
        $stmt->bindValue(':kunde', $kunde);
        $stmt->bindValue(':dienstleistung', $field('dienstleistung'));
        $stmt->bindValue(':auftrag', $field('auftrag'));
        $stmt->bindValue(':pos_nr', $field('pos_nr'));
        $stmt->bindValue(':cont_nummer', $field('cont_nummer'));
        $stmt->bindValue(':ware', $field('ware'));
        $stmt->bindValue(':anzahl', (float) $anzahl);
        $stmt->bindValue(':preis', (float) $preis);
        $stmt->bindValue(':eingang_rechnung', $field('eingang_rechnung'));
        $stmt->bindValue(':bemerkungen', $field('bemerkungen'));
        $stmt->bindValue(':highlighted', array_key_exists('highlighted', $this->data) ? ((int) $this->input('highlighted') ? 1 : 0) : (int) $order['highlighted']);
        $stmt->bindValue(':updated_by', $userId);
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        $updated = $this->fetchOrder($id);
        return ['status' => 200, 'data' => $this->castOrder($updated)];
    }

    function deleteOrder($id)
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf Dispo'];
        }

        $order = $this->fetchOrder($id);
        if (!$order) {
            return ['status' => 404, 'error' => 'Auftrag nicht gefunden'];
        }

        $stmt = $this->db->prepare('DELETE FROM dispo_orders WHERE id = :id');
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        return ['status' => 200, 'success' => true];
    }

    // ------------------------------ Helpers ------------------------------

    private function fetchOrder($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM dispo_orders WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function castOrder($row)
    {
        return [
            'id' => (int) $row['id'],
            'status' => $row['status'],
            'von' => $row['von'],
            'bis' => $row['bis'],
            'kunde' => $row['kunde'],
            'dienstleistung' => $row['dienstleistung'],
            'auftrag' => $row['auftrag'],
            'pos_nr' => $row['pos_nr'],
            'cont_nummer' => $row['cont_nummer'],
            'ware' => $row['ware'],
            'anzahl' => (float) $row['anzahl'],
            'preis' => (float) $row['preis'],
            'gesamt' => $row['gesamt'] !== null ? (float) $row['gesamt'] : null,
            'eingang_rechnung' => $row['eingang_rechnung'],
            'bemerkungen' => $row['bemerkungen'],
            'highlighted' => (int) $row['highlighted'],
            'created_at' => $row['created_at'] ?? null,
            'created_by' => $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'updated_at' => $row['updated_at'] ?? null,
            'updated_by' => $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
        ];
    }
}
