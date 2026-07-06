<?php

/**
 * Отправка push-уведомлений водителям через Expo Push API.
 *
 * Токен (ExponentPushToken[...]) приложение сохраняет после логина
 * (POST /fahrer/push_token). Отправка — best effort: любая ошибка гасится,
 * чтобы не ломать основной запрос.
 */
class ExpoPush
{
    /** Push конкретному водителю по id. */
    public static function toFahrer(PDO $db, $idFahrer, $title, $body)
    {
        try {
            $stmt = $db->prepare('SELECT push_token FROM fahrer WHERE id_fahrer = :id LIMIT 1');
            $stmt->bindValue(':id', $idFahrer);
            $stmt->execute();
            $token = $stmt->fetchColumn();

            if (!$token || strpos($token, 'ExponentPushToken') !== 0) {
                return;
            }

            $payload = json_encode([
                'to' => $token,
                'title' => $title,
                'body' => mb_substr((string) $body, 0, 170),
                'sound' => 'default',
            ], JSON_UNESCAPED_UNICODE);

            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 3,
                ],
            ]);
            @file_get_contents('https://exp.host/--/api/v2/push/send', false, $ctx);
        } catch (\Throwable $e) {
            // push — не критичный путь
        }
    }

    /** Push водителю, за которым закреплена машина/прицеп с данным номером. */
    public static function toVehicleDriver(PDO $db, $type, $nummer, $title, $body)
    {
        $nummer = trim((string) $nummer);
        if ($nummer === '') {
            return;
        }
        try {
            $col = $type === 'chassi' ? 'chassi' : 'lkw';
            $stmt = $db->prepare("SELECT id_fahrer FROM fahrer WHERE $col = :nummer LIMIT 1");
            $stmt->bindValue(':nummer', $nummer);
            $stmt->execute();
            $id = $stmt->fetchColumn();
            if ($id) {
                self::toFahrer($db, $id, $title, $body);
            }
        } catch (\Throwable $e) {
        }
    }

    /** То же, но по id машины/прицепа (для чатов, где известен только id). */
    public static function toVehicleDriverById(PDO $db, $type, $vehicleId, $title, $body)
    {
        if (!$vehicleId) {
            return;
        }
        try {
            if ($type === 'chassi') {
                $stmt = $db->prepare('SELECT chassi_nummer FROM chassi WHERE id_chassi = :id LIMIT 1');
            } else {
                $stmt = $db->prepare('SELECT lkw_nummer FROM lkw WHERE id_lkw = :id LIMIT 1');
            }
            $stmt->bindValue(':id', $vehicleId);
            $stmt->execute();
            $nummer = $stmt->fetchColumn();
            if ($nummer) {
                self::toVehicleDriver($db, $type, $nummer, $title, $body);
            }
        } catch (\Throwable $e) {
        }
    }
}
