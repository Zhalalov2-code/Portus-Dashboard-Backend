<?php

/**
 * Real-time WebSocket-сервер (Ratchet) для уведомлений и чата.
 *
 * Запуск (CLI, долгоживущий процесс):
 *   php realtime/ws-server.php
 *
 * Слушает ws://0.0.0.0:WS_PORT (по умолчанию 8090). Клиент подключается
 * с токеном: ws://host:8090/?token=<bearer>. Аутентификация — по auth_tokens.
 *
 * Источник событий — Redis-список 'portus:events' (его наполняет API
 * через класс Realtime). Сервер периодически забирает события и рассылает.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../api/config/env.php';   // подгружает .env (getenv)
require __DIR__ . '/src/Hub.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Portus\Realtime\Hub;

/** Свежее подключение к БД (для валидации токенов). */
function ws_db_connect(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'portusapp1';
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    return new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $user === false ? null : $user,
        $pass === false ? null : $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

$loop = Loop::get();
$hub = new Hub('ws_db_connect');

// --- Redis: периодически забираем события из списка ---
// ВАЖНО: подключение к Redis синхронное и может блокировать событийный
// цикл, если Redis недоступен. Поэтому переподключение делаем РЕДКО и с
// коротким таймаутом, а частый тик «забора событий» при отсутствии Redis
// сразу выходит — чтобы не подвешивать WebSocket-соединения.
$redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
$redisPort = (int) (getenv('REDIS_PORT') ?: 6379);
$redis = null;
$connectRedis = function () use (&$redis, $redisHost, $redisPort) {
    try {
        $client = new Predis\Client([
            'scheme' => 'tcp',
            'host' => $redisHost,
            'port' => $redisPort,
            'timeout' => 0.3,            // короткий таймаут подключения
            'read_write_timeout' => 0,
        ]);
        $client->connect();
        $redis = $client;
    } catch (\Throwable $e) {
        $redis = null;
    }
};
$connectRedis();

// Забор событий — часто. Если Redis нет, выходим мгновенно (не блокируем цикл).
$loop->addPeriodicTimer(0.25, function () use (&$redis, $hub) {
    if (!$redis) {
        return;
    }
    try {
        for ($i = 0; $i < 100; $i++) {
            $item = $redis->lpop('portus:events');
            if ($item === null) {
                break;
            }
            $entry = json_decode($item, true);
            if (is_array($entry)) {
                $hub->dispatch($entry);
            }
        }
    } catch (\Throwable $e) {
        $redis = null; // потеряли соединение — переподключит медленный таймер
    }
});

// Переподключение к Redis — редко (раз в 3 сек), только когда соединения нет.
$loop->addPeriodicTimer(3.0, function () use (&$redis, $connectRedis) {
    if (!$redis) {
        $connectRedis();
    }
});

$port = (int) (getenv('WS_PORT') ?: 8090);
$socket = new SocketServer("0.0.0.0:$port", [], $loop);
$server = new IoServer(new HttpServer(new WsServer($hub)), $socket, $loop);

echo "Realtime WebSocket-Server läuft auf :$port\n";
$loop->run();
