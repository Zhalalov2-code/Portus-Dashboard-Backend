<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

/**
 * Lagerverwaltung / Складской учёт (Inventory).
 *
 * MVP-ядро: складские позиции, расход материала на ТС с автоматическим
 * списанием остатка, приход, корректировки и неизменяемый журнал операций.
 *
 * Ключевые правила (см. ТЗ tz_5814890350):
 *  - Каждое движение по складу — отдельная строка в inventory_transactions.
 *  - Остаток и запись операции меняются в одной атомарной транзакции с
 *    блокировкой строки позиции (SELECT ... FOR UPDATE), чтобы конкурентные
 *    списания не могли увести остаток в минус.
 *  - Журнал append-only: строки не удаляются и не редактируются.
 *  - По умолчанию отрицательный остаток запрещён.
 *
 * Доступ к модулю: admin ИЛИ Abteilungsleiter (Leitende) ИЛИ Prüfer
 * (Tech Kontrolle). Все три группы получают полный доступ. Проверка прав —
 * на сервере при каждом действии (см. requireAccess()).
 */
class Inventory
{
    // Настройки модуля (в MVP — константы, без таблицы/экрана настроек).
    const ALLOW_NEGATIVE_STOCK = false;
    const REQUIRE_COMMENT_FOR_ADJUSTMENT = true;
    const REQUIRE_COMMENT_FOR_CONSUMPTION = false;

    const CONSUMPTION = 'CONSUMPTION';
    const RECEIPT = 'RECEIPT';
    const ADJUSTMENT_PLUS = 'ADJUSTMENT_PLUS';
    const ADJUSTMENT_MINUS = 'ADJUSTMENT_MINUS';
    const INITIAL_BALANCE = 'INITIAL_BALANCE';

    const VEHICLE_TYPES = ['lkw', 'chassi'];

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
        $third = $route[2] ?? null;
        $id = is_numeric($third) ? (int) $third : null;

        switch ($resource) {
            case 'items':
                return $this->handleItems($method, $id);
            case 'consume':
                return $method === 'POST' ? $this->consume() : ['status' => 405];
            case 'receive':
                return $method === 'POST' ? $this->receive() : ['status' => 405];
            case 'adjust':
                return $method === 'POST' ? $this->adjust() : ['status' => 405];
            case 'transactions':
                if ($method === 'GET')
                    return $this->getTransactions();
                if ($method === 'DELETE' && $id)
                    return $this->deleteTransaction($id);
                return ['status' => 405];
            case 'categories':
                return $method === 'GET' ? $this->getCategories() : ['status' => 405];
            case 'units':
                return $method === 'GET' ? $this->getUnits() : ['status' => 405];
            default:
                return ['status' => 404, 'error' => 'Unbekannte Lager-Route'];
        }
    }

    private function handleItems($method, $id)
    {
        switch ($method) {
            case 'GET':
                return $id ? $this->getItem($id) : $this->getItems();
            case 'POST':
                return $this->createItem();
            case 'PUT':
                if (!$id)
                    return ['status' => 400, 'error' => 'Artikel-ID ist erforderlich'];
                return $this->updateItem($id);
            case 'DELETE':
                if (!$id)
                    return ['status' => 400, 'error' => 'Artikel-ID ist erforderlich'];
                return $this->deleteItem($id);
            default:
                return ['status' => 405];
        }
    }

    // --------------------------- Права доступа ---------------------------

    /**
     * Доступ к складу: admin ИЛИ департамент содержит "leitend" ИЛИ
     * ("tech" И "kontroll"), ИЛИ роль "pruefer" (запасной вариант).
     * Зеркалит src/utils/roles.ts на фронтенде.
     */
    private function canUseWarehouse($user)
    {
        if (!$user) {
            return false;
        }
        $role = strtolower(trim($user['role'] ?? ''));
        if ($role === 'admin' || $role === 'pruefer' || $role === 'department_head') {
            return true;
        }
        if (empty($user['department_id'])) {
            return false;
        }
        $stmt = $this->db->prepare('SELECT name FROM departments WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $user['department_id']);
        $stmt->execute();
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dept) {
            return false;
        }
        $name = strtolower(trim($dept['name']));
        if (strpos($name, 'leitend') !== false) {
            return true;
        }
        return strpos($name, 'tech') !== false && strpos($name, 'kontroll') !== false;
    }

    /**
     * Возвращает текущего пользователя, если у него есть доступ к складу,
     * иначе null (вызывающий метод отдаёт 401/403).
     */
    private function requireAccess()
    {
        $user = Auth::currentUser();
        if (!$user) {
            return null;
        }
        return $this->canUseWarehouse($user) ? $user : false;
    }

    // ------------------------- Складские позиции -------------------------

    function getItems()
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];
        }

        $where = [];
        $params = [];

        $search = $this->input('search');
        if ($search !== null) {
            $where[] = '(i.name LIKE :search OR i.sku LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($this->input('category_id') !== null) {
            $where[] = 'i.category_id = :category_id';
            $params[':category_id'] = (int) $this->input('category_id');
        }
        if ($this->input('unit_id') !== null) {
            $where[] = 'i.unit_id = :unit_id';
            $params[':unit_id'] = (int) $this->input('unit_id');
        }
        // active: '1' | '0' | null (все)
        $active = $this->input('active');
        if ($active !== null) {
            $where[] = 'i.is_active = :active';
            $params[':active'] = (int) $active ? 1 : 0;
        }

        // Статус остатка фильтруем на стороне SQL — иначе при пагинации отфильтровали
        // бы уже урезанную страницу. Логика совпадает со stockStatus():
        //   out  — остаток <= 0
        //   low  — остаток > 0 и <= min_quantity (если min задан)
        //   ok   — остаток > 0 и (min не задан или остаток > min)
        $statusFilter = $this->input('stock_status'); // ok | low | out | null
        if ($statusFilter === 'out') {
            $where[] = 'i.current_quantity <= 0';
        } elseif ($statusFilter === 'low') {
            $where[] = 'i.current_quantity > 0 AND i.min_quantity IS NOT NULL AND i.current_quantity <= i.min_quantity';
        } elseif ($statusFilter === 'ok') {
            $where[] = 'i.current_quantity > 0 AND (i.min_quantity IS NULL OR i.current_quantity > i.min_quantity)';
        }

        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        // Общее число строк под текущие фильтры — для пагинации на фронте.
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM inventory_items i' . $whereSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $limit = (int) $this->input('limit', 50);
        $limit = max(1, min($limit, 1000));
        $offset = max(0, (int) $this->input('offset', 0));

        $sql = 'SELECT i.*, c.name AS category_name, u.code AS unit_code, u.name AS unit_name,
                       u.`precision` AS unit_precision, u.integer_only AS unit_integer_only,
                       (SELECT MAX(t.created_at) FROM inventory_transactions t WHERE t.item_id = i.id) AS last_movement
                FROM inventory_items i
                JOIN inventory_categories c ON c.id = i.category_id
                JOIN inventory_units u ON u.id = i.unit_id'
            . $whereSql .
            ' ORDER BY i.name ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $row['stock_status'] = $this->stockStatus($row);
            $result[] = $this->castItem($row);
        }

        return ['status' => 200, 'data' => $result, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    function getItem($id)
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];
        }

        $item = $this->fetchItem($id);
        if (!$item) {
            return ['status' => 404, 'error' => 'Artikel nicht gefunden'];
        }
        $item['stock_status'] = $this->stockStatus($item);

        $stmt = $this->db->prepare(
            'SELECT t.*, un.code AS unit_code, us.name AS user_name, us.lastname AS user_lastname
             FROM inventory_transactions t
             JOIN inventory_units un ON un.id = t.unit_id
             LEFT JOIN users us ON us.id = t.user_id
             WHERE t.item_id = :id
             ORDER BY t.created_at DESC, t.id DESC
             LIMIT 20'
        );
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $transactions = array_map([$this, 'castTransaction'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        return ['status' => 200, 'data' => ['item' => $this->castItem($item), 'transactions' => $transactions]];
    }

    function deleteItem($id)
    {
        $access = $this->requireAccess();
        if ($access === null)
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        if ($access === false)
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];

        $item = $this->fetchItem($id);
        if (!$item)
            return ['status' => 404, 'error' => 'Artikel nicht gefunden'];

        if ($this->itemHasTransactions($id)) {
            return ['status' => 400, 'error' => 'Artikel mit Buchungen kann nicht gelöscht werden. Deaktivieren Sie ihn stattdessen.'];
        }

        $stmt = $this->db->prepare('DELETE FROM inventory_items WHERE id = :id');
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return ['status' => 200, 'success' => true];
    }

    function createItem()
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];
        }

        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            return ['status' => 400, 'error' => 'Bezeichnung ist erforderlich'];
        }
        $categoryId = (int) $this->input('category_id', 0);
        $unitId = (int) $this->input('unit_id', 0);
        if (!$this->categoryExists($categoryId)) {
            return ['status' => 400, 'error' => 'Ungültige Kategorie'];
        }
        if (!$this->unitExists($unitId)) {
            return ['status' => 400, 'error' => 'Ungültige Maßeinheit'];
        }

        $sku = $this->input('sku');
        $sku = ($sku !== null) ? trim((string) $sku) : null;
        if ($sku === '') {
            $sku = null;
        }
        if ($sku !== null && $this->skuTaken($sku, null)) {
            return ['status' => 400, 'error' => 'Artikelnummer (SKU) ist bereits vergeben'];
        }

        $minQuantity = $this->input('min_quantity');
        if ($minQuantity !== null) {
            if (!is_numeric($minQuantity) || (float) $minQuantity < 0) {
                return ['status' => 400, 'error' => 'Mindestbestand darf nicht negativ sein'];
            }
            $minQuantity = (float) $minQuantity;
        }

        $userId = Auth::currentUser()['id'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO inventory_items
                (sku, name, category_id, unit_id, description, manufacturer, min_quantity, current_quantity, is_active, created_by, updated_by)
             VALUES
                (:sku, :name, :category_id, :unit_id, :description, :manufacturer, :min_quantity, 0, :is_active, :created_by, :updated_by)'
        );
        $stmt->bindValue(':sku', $sku);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':category_id', $categoryId);
        $stmt->bindValue(':unit_id', $unitId);
        $stmt->bindValue(':description', $this->input('description'));
        $stmt->bindValue(':manufacturer', $this->input('manufacturer'));
        $stmt->bindValue(':min_quantity', $minQuantity);
        $stmt->bindValue(':is_active', (int) $this->input('is_active', 1) ? 1 : 0);
        $stmt->bindValue(':created_by', $userId);
        $stmt->bindValue(':updated_by', $userId);

        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            // 23000 — нарушение уникальности (гонка при одновременном создании
            // с тем же SKU; БД-констрейнт uq_inventory_item_sku_active — бэкап
            // к проверке skuTaken() выше).
            if ($e->getCode() === '23000') {
                return ['status' => 400, 'error' => 'Artikelnummer (SKU) ist bereits vergeben'];
            }
            throw $e;
        }

        $item = $this->fetchItem($this->db->lastInsertId());
        $item['stock_status'] = $this->stockStatus($item);
        return ['status' => 201, 'data' => $this->castItem($item)];
    }

    function updateItem($id)
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];
        }

        $item = $this->fetchItem($id);
        if (!$item) {
            return ['status' => 404, 'error' => 'Artikel nicht gefunden'];
        }

        $name = $this->input('name');
        $name = ($name !== null) ? trim((string) $name) : $item['name'];
        if ($name === '') {
            return ['status' => 400, 'error' => 'Bezeichnung ist erforderlich'];
        }

        $categoryId = (int) $this->input('category_id', $item['category_id']);
        if (!$this->categoryExists($categoryId)) {
            return ['status' => 400, 'error' => 'Ungültige Kategorie'];
        }

        // Смену единицы измерения запрещаем, если по позиции уже есть операции.
        $unitId = (int) $this->input('unit_id', $item['unit_id']);
        if ($unitId !== (int) $item['unit_id']) {
            if (!$this->unitExists($unitId)) {
                return ['status' => 400, 'error' => 'Ungültige Maßeinheit'];
            }
            if ($this->itemHasTransactions($id)) {
                return ['status' => 400, 'error' => 'Maßeinheit kann nicht geändert werden: es existieren bereits Buchungen'];
            }
        }

        $sku = $this->input('sku');
        $sku = ($sku !== null) ? trim((string) $sku) : $item['sku'];
        if ($sku === '') {
            $sku = null;
        }
        if ($sku !== null && $this->skuTaken($sku, $id)) {
            return ['status' => 400, 'error' => 'Artikelnummer (SKU) ist bereits vergeben'];
        }

        $minQuantity = $this->input('min_quantity');
        if ($minQuantity === null) {
            $minQuantity = $item['min_quantity'];
        } else {
            if (!is_numeric($minQuantity) || (float) $minQuantity < 0) {
                return ['status' => 400, 'error' => 'Mindestbestand darf nicht negativ sein'];
            }
            $minQuantity = (float) $minQuantity;
        }

        // current_quantity нельзя менять напрямую — только операциями.
        $description = array_key_exists('description', $this->data) ? $this->input('description') : $item['description'];
        $manufacturer = array_key_exists('manufacturer', $this->data) ? $this->input('manufacturer') : $item['manufacturer'];
        $isActive = array_key_exists('is_active', $this->data) ? ((int) $this->input('is_active') ? 1 : 0) : (int) $item['is_active'];
        $userId = Auth::currentUser()['id'] ?? null;

        $stmt = $this->db->prepare(
            'UPDATE inventory_items SET
                sku = :sku, name = :name, category_id = :category_id, unit_id = :unit_id,
                description = :description, manufacturer = :manufacturer, min_quantity = :min_quantity,
                is_active = :is_active, updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->bindValue(':sku', $sku);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':category_id', $categoryId);
        $stmt->bindValue(':unit_id', $unitId);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':manufacturer', $manufacturer);
        $stmt->bindValue(':min_quantity', $minQuantity);
        $stmt->bindValue(':is_active', $isActive);
        $stmt->bindValue(':updated_by', $userId);
        $stmt->bindValue(':id', $id);

        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['status' => 400, 'error' => 'Artikelnummer (SKU) ist bereits vergeben'];
            }
            throw $e;
        }

        $updated = $this->fetchItem($id);
        $updated['stock_status'] = $this->stockStatus($updated);
        return ['status' => 200, 'data' => $this->castItem($updated)];
    }

    // --------------------------- Операции склада ---------------------------

    function consume()
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];
        }

        $itemId = (int) $this->input('item_id', 0);
        $vehicleType = $this->input('vehicle_type');
        $vehicleId = (int) $this->input('vehicle_id', 0);
        $quantityRaw = $this->input('quantity');
        $comment = $this->input('comment');

        if (!in_array($vehicleType, self::VEHICLE_TYPES, true) || !$vehicleId) {
            return ['status' => 400, 'error' => 'Fahrzeug ist erforderlich'];
        }
        $vehicle = $this->fetchVehicle($vehicleType, $vehicleId);
        if (!$vehicle) {
            return ['status' => 400, 'error' => 'Fahrzeug nicht verfügbar'];
        }
        if (self::REQUIRE_COMMENT_FOR_CONSUMPTION && (trim((string) $comment) === '')) {
            return ['status' => 400, 'error' => 'Kommentar ist erforderlich'];
        }

        return $this->applyMovement(self::CONSUMPTION, $itemId, $quantityRaw, [
            'vehicle_type' => $vehicleType,
            'vehicle_id' => $vehicleId,
            'vehicle_nummer' => $vehicle['nummer'],
            'comment' => $comment,
        ]);
    }

    function receive()
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];
        }

        $itemId = (int) $this->input('item_id', 0);
        $quantityRaw = $this->input('quantity');

        return $this->applyMovement(self::RECEIPT, $itemId, $quantityRaw, [
            'comment' => $this->input('comment'),
            'document_number' => $this->input('document_number'),
            'document_date' => $this->input('document_date'),
        ]);
    }

    function adjust()
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];
        }

        $itemId = (int) $this->input('item_id', 0);
        $quantityRaw = $this->input('quantity');
        $type = $this->input('type');
        $comment = $this->input('comment');

        if (!in_array($type, [self::ADJUSTMENT_PLUS, self::ADJUSTMENT_MINUS], true)) {
            return ['status' => 400, 'error' => 'Ungültiger Korrekturtyp'];
        }
        if (self::REQUIRE_COMMENT_FOR_ADJUSTMENT && (trim((string) $comment) === '')) {
            return ['status' => 400, 'error' => 'Grund der Korrektur ist erforderlich'];
        }

        return $this->applyMovement($type, $itemId, $quantityRaw, ['comment' => $comment]);
    }

    /**
     * Единое транзакционное ядро для расхода/прихода/корректировки.
     * Меняет остаток и пишет строку журнала атомарно, с блокировкой строки
     * позиции (FOR UPDATE), чтобы конкурентные операции не увели остаток в минус.
     */
    private function applyMovement($type, $itemId, $quantityRaw, array $extra = [])
    {
        if (!$itemId) {
            return ['status' => 400, 'error' => 'Artikel ist erforderlich'];
        }
        if ($quantityRaw === null || !is_numeric($quantityRaw)) {
            return ['status' => 400, 'error' => 'Bitte eine gültige Menge größer als Null eingeben'];
        }
        $quantity = (float) $quantityRaw;
        if ($quantity <= 0) {
            return ['status' => 400, 'error' => 'Die Menge muss größer als Null sein'];
        }
        // DECIMAL(18,3): защищаемся от переполнения.
        if ($quantity >= 1e15) {
            return ['status' => 400, 'error' => 'Ungültige Menge'];
        }

        $userId = Auth::currentUser()['id'] ?? null;

        try {
            $this->db->beginTransaction();

            // Блокируем строку позиции + читаем актуальный остаток и единицу.
            $stmt = $this->db->prepare(
                'SELECT i.id, i.is_active, i.current_quantity, i.unit_id,
                        u.`precision` AS unit_precision, u.integer_only AS unit_integer_only
                 FROM inventory_items i
                 JOIN inventory_units u ON u.id = i.unit_id
                 WHERE i.id = :id FOR UPDATE'
            );
            $stmt->bindValue(':id', $itemId);
            $stmt->execute();
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $this->db->rollBack();
                return ['status' => 404, 'error' => 'Artikel nicht gefunden'];
            }
            // Расход по деактивированной позиции запрещён (приход/корректировку тоже блокируем).
            if ((int) $item['is_active'] !== 1) {
                $this->db->rollBack();
                return ['status' => 400, 'error' => 'Lagerposition ist inaktiv. Buchung nicht möglich.'];
            }

            // Целочисленность / точность по единице измерения.
            if ((int) $item['unit_integer_only'] === 1) {
                if (floor($quantity) != $quantity) {
                    $this->db->rollBack();
                    return ['status' => 400, 'error' => 'Für diese Position muss die Menge eine ganze Zahl sein'];
                }
            } else {
                $quantity = round($quantity, (int) $item['unit_precision']);
            }

            $before = (float) $item['current_quantity'];
            $delta = in_array($type, [self::CONSUMPTION, self::ADJUSTMENT_MINUS], true) ? -$quantity : $quantity;
            $after = round($before + $delta, 3);

            // Запрет отрицательного остатка (по умолчанию) для списаний.
            if ($delta < 0 && $after < 0 && !self::ALLOW_NEGATIVE_STOCK) {
                $this->db->rollBack();
                $unitCode = $this->unitCode($item['unit_id']);
                return [
                    'status' => 400,
                    'error' => sprintf('Nicht genügend Bestand. Verfügbar: %s %s', $this->formatQty($before), $unitCode),
                    'available' => $before,
                ];
            }

            // Верхняя граница: остаток должен помещаться в DECIMAL(18,3)
            // (максимум 999 999 999 999 999.999). Иначе MySQL оборвал бы INSERT/UPDATE.
            if ($delta > 0 && $after >= 1e15) {
                $this->db->rollBack();
                return ['status' => 400, 'error' => 'Bestand zu groß: das erlaubte Maximum würde überschritten'];
            }

            // Пишем строку журнала.
            $ins = $this->db->prepare(
                'INSERT INTO inventory_transactions
                    (transaction_type, item_id, vehicle_type, vehicle_id, vehicle_nummer, quantity, unit_id,
                     quantity_before, quantity_after, user_id, comment, document_number, document_date, source)
                 VALUES
                    (:type, :item_id, :vehicle_type, :vehicle_id, :vehicle_nummer, :quantity, :unit_id,
                     :qbefore, :qafter, :user_id, :comment, :doc_num, :doc_date, :source)'
            );
            $ins->bindValue(':type', $type);
            $ins->bindValue(':item_id', $itemId);
            $ins->bindValue(':vehicle_type', $extra['vehicle_type'] ?? null);
            $ins->bindValue(':vehicle_id', $extra['vehicle_id'] ?? null);
            $ins->bindValue(':vehicle_nummer', $extra['vehicle_nummer'] ?? null);
            $ins->bindValue(':quantity', $quantity);
            $ins->bindValue(':unit_id', $item['unit_id']);
            $ins->bindValue(':qbefore', $before);
            $ins->bindValue(':qafter', $after);
            $ins->bindValue(':user_id', $userId);
            $ins->bindValue(':comment', $extra['comment'] ?? null);
            $ins->bindValue(':doc_num', $extra['document_number'] ?? null);
            $ins->bindValue(':doc_date', $extra['document_date'] ?? null);
            $ins->bindValue(':source', 'UI');
            $ins->execute();
            $transactionId = (int) $this->db->lastInsertId();

            // Обновляем остаток позиции.
            $upd = $this->db->prepare('UPDATE inventory_items SET current_quantity = :q WHERE id = :id');
            $upd->bindValue(':q', $after);
            $upd->bindValue(':id', $itemId);
            $upd->execute();

            $this->db->commit();

            return [
                'status' => 201,
                'data' => [
                    'transaction_id' => $transactionId,
                    'item_id' => $itemId,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                ],
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Inventory movement failed: ' . $e->getMessage());
            return ['status' => 500, 'error' => 'Buchung konnte nicht gespeichert werden. Bitte erneut versuchen.'];
        }
    }

    // ----------------------------- Журнал -----------------------------

    function deleteTransaction($id)
    {
        $access = $this->requireAccess();
        if ($access === null)
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        if ($access === false)
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];

        $stmt = $this->db->prepare('SELECT * FROM inventory_transactions WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tx)
            return ['status' => 404, 'error' => 'Transaktion nicht gefunden'];

        if ($tx['transaction_type'] === self::INITIAL_BALANCE) {
            return ['status' => 400, 'error' => 'INITIAL_BALANCE kann nicht gelöscht werden'];
        }

        try {
            $this->db->beginTransaction();

            $itemStmt = $this->db->prepare('SELECT id, current_quantity FROM inventory_items WHERE id = :id FOR UPDATE');
            $itemStmt->bindValue(':id', $tx['item_id']);
            $itemStmt->execute();
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                $this->db->rollBack();
                return ['status' => 404, 'error' => 'Zugehöriger Artikel nicht gefunden'];
            }

            $qty = (float) $tx['quantity'];
            $type = $tx['transaction_type'];
            $delta = in_array($type, [self::RECEIPT, self::ADJUSTMENT_PLUS], true) ? -$qty : $qty;
            $newQty = round((float) $item['current_quantity'] + $delta, 3);

            $del = $this->db->prepare('DELETE FROM inventory_transactions WHERE id = :id');
            $del->bindValue(':id', $id);
            $del->execute();

            $upd = $this->db->prepare('UPDATE inventory_items SET current_quantity = :q, updated_at = NOW() WHERE id = :id');
            $upd->bindValue(':q', $newQty);
            $upd->bindValue(':id', $tx['item_id']);
            $upd->execute();

            $this->db->commit();
            return ['status' => 200, 'success' => true];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction())
                $this->db->rollBack();
            error_log('Delete transaction failed: ' . $e->getMessage());
            return ['status' => 500, 'error' => 'Löschen der Transaktion fehlgeschlagen'];
        }
    }

    function getTransactions()
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];
        }

        $where = [];
        $params = [];

        if ($this->input('type') !== null) {
            $where[] = 't.transaction_type = :type';
            $params[':type'] = $this->input('type');
        }
        if ($this->input('item_id') !== null) {
            $where[] = 't.item_id = :item_id';
            $params[':item_id'] = (int) $this->input('item_id');
        }
        if ($this->input('user_id') !== null) {
            $where[] = 't.user_id = :user_id';
            $params[':user_id'] = (int) $this->input('user_id');
        }
        if ($this->input('vehicle_type') !== null) {
            $where[] = 't.vehicle_type = :vehicle_type';
            $params[':vehicle_type'] = $this->input('vehicle_type');
        }
        if ($this->input('vehicle_id') !== null) {
            $where[] = 't.vehicle_id = :vehicle_id';
            $params[':vehicle_id'] = (int) $this->input('vehicle_id');
        }
        if ($this->input('category_id') !== null) {
            $where[] = 'i.category_id = :category_id';
            $params[':category_id'] = (int) $this->input('category_id');
        }
        if ($this->input('date_from') !== null) {
            $where[] = 't.created_at >= :date_from';
            $params[':date_from'] = $this->input('date_from') . ' 00:00:00';
        }
        if ($this->input('date_to') !== null) {
            $where[] = 't.created_at <= :date_to';
            $params[':date_to'] = $this->input('date_to') . ' 23:59:59';
        }

        $limit = (int) $this->input('limit', 100);
        $limit = max(1, min($limit, 500));
        $offset = max(0, (int) $this->input('offset', 0));

        $sql = 'SELECT t.*, i.name AS item_name, i.sku AS item_sku, i.category_id,
                       un.code AS unit_code, us.name AS user_name, us.lastname AS user_lastname
                FROM inventory_transactions t
                JOIN inventory_items i ON i.id = t.item_id
                JOIN inventory_units un ON un.id = t.unit_id
                LEFT JOIN users us ON us.id = t.user_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.created_at DESC, t.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $data = array_map([$this, 'castTransaction'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        return ['status' => 200, 'data' => $data];
    }

    // --------------------------- Справочники ---------------------------

    function getCategories()
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];
        }
        $stmt = $this->db->query('SELECT id, name, is_active, sort_order FROM inventory_categories WHERE is_active = 1 ORDER BY sort_order, name');
        return ['status' => 200, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    function getUnits()
    {
        $access = $this->requireAccess();
        if ($access === null) {
            return ['status' => 401, 'error' => 'Nicht autorisiert'];
        }
        if ($access === false) {
            return ['status' => 403, 'error' => 'Kein Zugriff auf das Lager'];
        }
        $stmt = $this->db->query('SELECT id, code, name, `precision`, integer_only, is_active FROM inventory_units WHERE is_active = 1 ORDER BY id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['precision'] = (int) $r['precision'];
            $r['integer_only'] = (int) $r['integer_only'];
            $r['is_active'] = (int) $r['is_active'];
        }
        return ['status' => 200, 'data' => $rows];
    }

    // ------------------------------ Helpers ------------------------------

    private function fetchItem($id)
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, c.name AS category_name, u.code AS unit_code, u.name AS unit_name,
                    u.`precision` AS unit_precision, u.integer_only AS unit_integer_only
             FROM inventory_items i
             JOIN inventory_categories c ON c.id = i.category_id
             JOIN inventory_units u ON u.id = i.unit_id
             WHERE i.id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Возвращает ['id' => ..., 'nummer' => ...] активного ТС или null.
     */
    private function fetchVehicle($type, $id)
    {
        if ($type === 'lkw') {
            $stmt = $this->db->prepare('SELECT id_lkw AS id, lkw_nummer AS nummer FROM lkw WHERE id_lkw = :id LIMIT 1');
        } else {
            $stmt = $this->db->prepare('SELECT id_chassi AS id, chassi_nummer AS nummer FROM chassi WHERE id_chassi = :id LIMIT 1');
        }
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function categoryExists($id)
    {
        if (!$id) {
            return false;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM inventory_categories WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function unitExists($id)
    {
        if (!$id) {
            return false;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM inventory_units WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function unitCode($unitId)
    {
        $stmt = $this->db->prepare('SELECT code FROM inventory_units WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $unitId);
        $stmt->execute();
        return (string) $stmt->fetchColumn();
    }

    /**
     * SKU уникален среди активных позиций (при задании). $excludeId — id
     * редактируемой позиции, чтобы не конфликтовать с самой собой.
     */
    private function skuTaken($sku, $excludeId)
    {
        $sql = 'SELECT 1 FROM inventory_items WHERE sku = :sku AND is_active = 1';
        if ($excludeId) {
            $sql .= ' AND id <> :id';
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sku', $sku);
        if ($excludeId) {
            $stmt->bindValue(':id', $excludeId);
        }
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function itemHasTransactions($id)
    {
        $stmt = $this->db->prepare('SELECT 1 FROM inventory_transactions WHERE item_id = :id LIMIT 1');
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Статус остатка: 'out' (нет в наличии), 'low' (ниже минимума), 'ok'.
     * Если min_quantity не задан — "ниже минимума" не применяется.
     */
    private function stockStatus($item)
    {
        $qty = (float) $item['current_quantity'];
        if ($qty <= 0) {
            return 'out';
        }
        if ($item['min_quantity'] !== null && $item['min_quantity'] !== '' && $qty <= (float) $item['min_quantity']) {
            return 'low';
        }
        return 'ok';
    }

    private function formatQty($value)
    {
        // Убираем лишние нули в хвосте (1.500 -> 1.5, 20.000 -> 20).
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    private function castItem($row)
    {
        return [
            'id' => (int) $row['id'],
            'sku' => $row['sku'],
            'name' => $row['name'],
            'category_id' => (int) $row['category_id'],
            'category_name' => $row['category_name'] ?? null,
            'unit_id' => (int) $row['unit_id'],
            'unit_code' => $row['unit_code'] ?? null,
            'unit_name' => $row['unit_name'] ?? null,
            'unit_precision' => isset($row['unit_precision']) ? (int) $row['unit_precision'] : null,
            'unit_integer_only' => isset($row['unit_integer_only']) ? (int) $row['unit_integer_only'] : null,
            'description' => $row['description'],
            'manufacturer' => $row['manufacturer'],
            'min_quantity' => $row['min_quantity'] !== null ? (float) $row['min_quantity'] : null,
            'current_quantity' => (float) $row['current_quantity'],
            'is_active' => (int) $row['is_active'],
            'stock_status' => $row['stock_status'] ?? null,
            'last_movement' => $row['last_movement'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function castTransaction($row)
    {
        return [
            'id' => (int) $row['id'],
            'transaction_type' => $row['transaction_type'],
            'item_id' => (int) $row['item_id'],
            'item_name' => $row['item_name'] ?? null,
            'item_sku' => $row['item_sku'] ?? null,
            'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
            'vehicle_type' => $row['vehicle_type'],
            'vehicle_id' => $row['vehicle_id'] !== null ? (int) $row['vehicle_id'] : null,
            'vehicle_nummer' => $row['vehicle_nummer'],
            'quantity' => (float) $row['quantity'],
            'unit_id' => (int) $row['unit_id'],
            'unit_code' => $row['unit_code'] ?? null,
            'quantity_before' => (float) $row['quantity_before'],
            'quantity_after' => (float) $row['quantity_after'],
            'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'user_name' => $row['user_name'] ?? null,
            'user_lastname' => $row['user_lastname'] ?? null,
            'comment' => $row['comment'],
            'document_number' => $row['document_number'] ?? null,
            'document_date' => $row['document_date'] ?? null,
            'created_at' => $row['created_at'],
            'source' => $row['source'] ?? null,
        ];
    }
}
