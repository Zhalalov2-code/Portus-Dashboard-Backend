<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

/**
 * Локации отцепки прицепов для карты в админке.
 * Данные уже пишутся при отцепке в message_chassi (action_type='detach',
 * latitude/longitude/address) — здесь просто отдаём их с номером прицепа.
 */
class DetachLocations
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    function verifyMethod($method, $route)
    {
        if ($method !== 'GET') {
            return ['status' => 405, 'error' => 'Method not allowed'];
        }
        if (!Auth::currentUser()) {
            return ['status' => 403, 'error' => 'Только сотрудники'];
        }

        // Последняя отцепка на каждый прицеп + все точки (для истории).
        $sql = "SELECT m.id_message, m.id_chassi, c.chassi_nummer,
                       m.latitude, m.longitude, m.address, m.text, m.type_sender, m.created_ad
                FROM message_chassi m
                LEFT JOIN chassi c ON c.id_chassi = m.id_chassi
                WHERE m.action_type = 'detach'
                  AND m.latitude IS NOT NULL AND m.latitude <> ''
                  AND m.longitude IS NOT NULL AND m.longitude <> ''
                ORDER BY m.created_ad DESC
                LIMIT 500";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['latitude'] = (float) $r['latitude'];
            $r['longitude'] = (float) $r['longitude'];
        }
        return ['status' => 200, 'data' => $rows];
    }
}
