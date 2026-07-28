<?php

require_once __DIR__ . '/../config/db.php';

/**
 * Простая аутентификация по токену (без сторонних JWT-библиотек).
 *
 * Логика:
 *  - При логине (Users::userLogin / Fahrer::fahrerLogin) выпускается случайный
 *    токен через Auth::issueToken() и сохраняется в таблице auth_tokens.
 *  - Клиент присылает токен в заголовке "Authorization: Bearer <token>".
 *  - index.php на каждый защищённый запрос вызывает Auth::resolve(), который
 *    проверяет токен и кладёт текущего пользователя/водителя в статические
 *    свойства — дальше любой класс может обратиться к Auth::currentUser()
 *    или Auth::currentFahrer(), не доверяя ничему, что прислал клиент в теле
 *    запроса (requester_id и т.п. больше не используются для авторизации).
 */
class Auth
{
    /**
     * Срок жизни токена сотрудника (users) при бездействии, в секундах.
     * 4 часа: токен продлевается на каждый запрос (скользящее окно в resolve()),
     * поэтому у активного пользователя сессия не обрывается, а брошенная/закрытая
     * вкладка теряет валидный токен максимум через 4 часа тишины.
     *
     * Водителей (fahrer) это НЕ касается — их токены живут по старому 7-дневному
     * абсолютному сроку (мобильное приложение), см. issueToken()/resolve().
     */
    const USER_INACTIVITY_TTL = 14400;

    /** @var array|null Текущий авторизованный сотрудник (таблица users), без пароля */
    private static $currentUser = null;

    /** @var array|null Текущий авторизованный водитель (таблица fahrer), без пароля */
    private static $currentFahrer = null;

    public static function setCurrentUser($user)
    {
        self::$currentUser = $user;
    }

    public static function currentUser()
    {
        return self::$currentUser;
    }

    public static function setCurrentFahrer($fahrer)
    {
        self::$currentFahrer = $fahrer;
    }

    public static function currentFahrer()
    {
        return self::$currentFahrer;
    }

    /**
     * Достаёт Bearer-токен из заголовка Authorization.
     * PHP/Apache не всегда кладёт заголовки в $_SERVER одинаково, поэтому
     * проверяем несколько источников.
     */
    public static function bearerToken()
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if (!$header && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

        if (!$header) {
            return null;
        }

        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Выпускает новый токен для пользователя/водителя и сохраняет его в БД.
     *
     * @param PDO $db
     * @param string $subjectType 'user' | 'fahrer'
     * @param int $subjectId
     * @param int $ttlSeconds Срок жизни токена в секундах (по умолчанию 7 дней)
     */
    public static function issueToken(PDO $db, $subjectType, $subjectId, $ttlSeconds = 604800)
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $stmt = $db->prepare(
            'INSERT INTO auth_tokens (subject_type, subject_id, token, created_at, expires_at)
             VALUES (:subject_type, :subject_id, :token, NOW(), :expires_at)'
        );
        $stmt->bindValue(':subject_type', $subjectType);
        $stmt->bindValue(':subject_id', $subjectId);
        $stmt->bindValue(':token', $token);
        $stmt->bindValue(':expires_at', $expiresAt);
        $stmt->execute();

        // Оппортунистическая чистка: при каждом входе удаляем просроченные токены
        // этого же субъекта. Дешёвый DELETE по (subject_type, subject_id) —
        // страховка на случай, если ночной cron (cleanup_tokens.php) не отработает,
        // чтобы таблица auth_tokens не копила мёртвые строки.
        $cleanup = $db->prepare(
            'DELETE FROM auth_tokens
             WHERE subject_type = :subject_type AND subject_id = :subject_id AND expires_at < NOW()'
        );
        $cleanup->bindValue(':subject_type', $subjectType);
        $cleanup->bindValue(':subject_id', $subjectId);
        $cleanup->execute();

        return $token;
    }

    /**
     * Сдвигает срок жизни токена вперёд (скользящее окно бездействия).
     * Вызывается из resolve() на каждый успешный запрос.
     */
    public static function touchExpiry(PDO $db, $token, $ttlSeconds)
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $stmt = $db->prepare('UPDATE auth_tokens SET expires_at = :expires_at WHERE token = :token');
        $stmt->bindValue(':expires_at', $expiresAt);
        $stmt->bindValue(':token', $token);
        $stmt->execute();
    }

    /**
     * Проверяет токен, подтягивает связанную сущность и кладёт её
     * в Auth::currentUser()/currentFahrer(). Возвращает массив
     * ['type' => 'user'|'fahrer', 'data' => [...]] или null, если токен
     * невалиден/просрочен.
     */
    public static function resolve(PDO $db, $token)
    {
        if (empty($token)) {
            return null;
        }

        $stmt = $db->prepare('SELECT * FROM auth_tokens WHERE token = :token AND expires_at > NOW() LIMIT 1');
        $stmt->bindValue(':token', $token);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if ($row['subject_type'] === 'user') {
            $stmt = $db->prepare(
                'SELECT id, username, name, lastname, role, department_id, vacation_days_per_year
                 FROM users WHERE id = :id LIMIT 1'
            );
            $stmt->bindValue(':id', $row['subject_id']);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return null;
            }

            // Скользящее окно бездействия: пока сотрудник делает запросы, срок
            // жизни токена сдвигается вперёд на USER_INACTIVITY_TTL. Как только
            // запросы прекращаются (закрытая вкладка, ушёл от компьютера),
            // токен истекает и следующий запрос получит 401.
            self::touchExpiry($db, $token, self::USER_INACTIVITY_TTL);

            $user['role'] = strtolower(trim($user['role'] ?? ''));
            self::setCurrentUser($user);

            return ['type' => 'user', 'data' => $user];
        }

        if ($row['subject_type'] === 'fahrer') {
            $stmt = $db->prepare(
                'SELECT id_fahrer, driver_code, name, lastname, phone, lkw, chassi
                 FROM fahrer WHERE id_fahrer = :id LIMIT 1'
            );
            $stmt->bindValue(':id', $row['subject_id']);
            $stmt->execute();
            $fahrer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fahrer) {
                return null;
            }

            self::setCurrentFahrer($fahrer);

            return ['type' => 'fahrer', 'data' => $fahrer];
        }

        return null;
    }

    public static function deleteToken(PDO $db, $token)
    {
        if (empty($token)) {
            return;
        }
        $stmt = $db->prepare('DELETE FROM auth_tokens WHERE token = :token');
        $stmt->bindValue(':token', $token);
        $stmt->execute();
    }
}
