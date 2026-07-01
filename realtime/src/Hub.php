<?php

namespace Portus\Realtime;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use PDO;

/**
 * Ratchet-приложение: держит подключения, аутентифицирует по токену,
 * раздаёт события (уведомления — по user_id, чат — по комнатам).
 */
class Hub implements MessageComponentInterface
{
    /** @var \SplObjectStorage conn => ['userId'=>int, 'rooms'=>[room=>true]] */
    private $clients;
    /** @var callable вернёт свежий PDO */
    private $dbConnect;
    /** @var PDO|null */
    private $pdo;

    public function __construct(callable $dbConnect)
    {
        $this->clients = new \SplObjectStorage();
        $this->dbConnect = $dbConnect;
        $this->pdo = ($dbConnect)();
    }

    private function validateToken($token)
    {
        if (!$token) {
            return null;
        }
        // 2 попытки — на случай, если соединение с БД отвалилось в долгоживущем процессе.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $stmt = $this->pdo->prepare(
                    'SELECT subject_type, subject_id FROM auth_tokens
                     WHERE token = :token AND expires_at > NOW() LIMIT 1'
                );
                $stmt->bindValue(':token', $token);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['subject_type'] !== 'user') {
                    return null;
                }
                return (int) $row['subject_id'];
            } catch (\PDOException $e) {
                $this->pdo = ($this->dbConnect)();
            }
        }
        return null;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $query = '';
        if (isset($conn->httpRequest)) {
            $query = $conn->httpRequest->getUri()->getQuery();
        }
        parse_str($query, $params);

        $userId = $this->validateToken($params['token'] ?? '');
        if (!$userId) {
            $conn->close();
            return;
        }

        $this->clients->attach($conn, ['userId' => $userId, 'rooms' => []]);
        $conn->send(json_encode(['event' => 'connected', 'data' => ['userId' => $userId]]));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        if (!$this->clients->contains($from)) {
            return;
        }
        $data = json_decode($msg, true);
        if (!is_array($data) || !isset($data['action'])) {
            return;
        }
        $room = isset($data['room']) ? (string) $data['room'] : '';
        $meta = $this->clients[$from];

        if ($data['action'] === 'subscribe' && $room !== '') {
            $meta['rooms'][$room] = true;
            $this->clients[$from] = $meta;
        } elseif ($data['action'] === 'unsubscribe' && $room !== '') {
            unset($meta['rooms'][$room]);
            $this->clients[$from] = $meta;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }

    /** Разослать событие из Redis нужным клиентам. */
    public function dispatch(array $entry)
    {
        $target = $entry['target'] ?? '';
        $payload = json_encode([
            'event' => $entry['event'] ?? 'event',
            'data' => $entry['data'] ?? null,
            'room' => $entry['room'] ?? null,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($this->clients as $conn) {
            $meta = $this->clients[$conn];
            $send = false;
            if ($target === 'all') {
                $send = true;
            } elseif ($target === 'user' && (int) ($entry['id'] ?? 0) === $meta['userId']) {
                $send = true;
            } elseif ($target === 'room' && isset($meta['rooms'][$entry['room'] ?? ''])) {
                $send = true;
            }
            if ($send) {
                $conn->send($payload);
            }
        }
    }
}
