<?php

/**
 * Публикация real-time событий в Redis-список 'portus:events'.
 * WebSocket-сервер (realtime/ws-server.php) периодически забирает их
 * и рассылает подключённым клиентам.
 *
 * Никогда не должен ломать основной запрос: если Redis недоступен или
 * нет vendor/, всё тихо превращается в no-op.
 */
class Realtime
{
    private static $client = null;
    private static $disabled = false;

    private static function client()
    {
        if (self::$disabled) {
            return null;
        }
        if (self::$client !== null) {
            return self::$client;
        }

        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (!is_file($autoload)) {
            self::$disabled = true;
            return null;
        }
        require_once $autoload;

        try {
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = (int) (getenv('REDIS_PORT') ?: 6379);
            self::$client = new Predis\Client([
                'scheme' => 'tcp',
                'host' => $host,
                'port' => $port,
                'timeout' => 0.5,
            ]);
            return self::$client;
        } catch (\Throwable $e) {
            self::$disabled = true;
            return null;
        }
    }

    private static function push(array $entry)
    {
        $client = self::client();
        if (!$client) {
            return;
        }
        try {
            $client->rpush('portus:events', [json_encode($entry, JSON_UNESCAPED_UNICODE)]);
        } catch (\Throwable $e) {
            // Real-time — best effort. Ошибку гасим, чтобы не ломать API-запрос.
            self::$disabled = true;
        }
    }

    /** Событие конкретному пользователю (по user_id) — для уведомлений. */
    public static function notifyUser($userId, string $event, $data)
    {
        if (!$userId) {
            return;
        }
        self::push([
            'target' => 'user',
            'id' => (int) $userId,
            'event' => $event,
            'data' => $data,
        ]);
    }

    /** Событие в комнату (например "lkw-12" / "chassi-7") — для чата. */
    public static function notifyRoom(string $room, string $event, $data)
    {
        if ($room === '') {
            return;
        }
        self::push([
            'target' => 'room',
            'room' => $room,
            'event' => $event,
            'data' => $data,
        ]);
    }

    /** Событие всем подключённым клиентам. */
    public static function notifyAll(string $event, $data)
    {
        self::push([
            'target' => 'all',
            'event' => $event,
            'data' => $data,
        ]);
    }

    /**
     * Сообщить всем, что изменилась сущность (task/lkw/chassi/vacation/...),
     * чтобы клиенты перезапросили соответствующий список.
     */
    public static function entityChanged(string $entity)
    {
        self::notifyAll('data', ['entity' => $entity]);
    }
}
