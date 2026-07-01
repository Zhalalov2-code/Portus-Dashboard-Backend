<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

class Users
{
    private $id;
    private $username;
    private $password;
    private $name;
    private $lastname;
    private $role;
    private $department_id;
    private $vacation_days_per_year = 28;
    private $db;

    function __construct($id = null, $username = '', $password = '', $name = '', $lastname = '', $role = '', $department_id = null)
    {
        $this->db = DB::getInstance();
        $this->id = $id;
        $this->username = $username;
        $this->password = $password;
        $this->name = $name;
        $this->lastname = $lastname;
        $this->role = $role;
        $this->department_id = $department_id;
    }

    function verifyMethod($method, $route)
    {
        switch ($method) {
            case 'GET':
                return $this->userGet();

            case 'POST':
                if (($route[1] ?? null) === 'login') {
                    $data = $this->getReqData();
                    $this->hydrateForm($data);
                    return $this->userLogin();
                } elseif (($route[1] ?? null) === 'logout') {
                    return $this->userLogout();
                } elseif (empty($route[1])) {
                    $data = $this->getReqData();
                    $this->hydrateForm($data);
                    return $this->userPost();
                } else {
                    return ['status' => 404, 'error' => 'Не найдено'];
                }

            case 'PUT':
                $data = $this->getReqData();
                $this->hydrateForm($data);
                return $this->userPut();

            case 'DELETE':
                return $this->userDelete($route);

            default:
                return ['status' => 405];
        }
    }

    function userLogin()
    {
        $login = trim((string) $this->username);

        if ($login === '' || empty($this->password)) {
            return ['status' => 400, 'error' => 'Имя пользователя и пароль обязательны'];
        }

        // department_name нужен фронту для доступа по департаменту
        // (Tech Kontrolle / Leitende), поэтому подтягиваем его JOIN-ом.
        $sql = 'SELECT u.*, d.name AS department_name
                  FROM users u
                  LEFT JOIN departments d ON u.department_id = d.id
                 WHERE LOWER(u.username) = LOWER(:login) LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':login', $login);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $this->verifyPassword($this->password, $user)) {
            unset($user['password']);
            $user['role'] = strtolower(trim($user['role'] ?? ''));
            $token = Auth::issueToken($this->db, 'user', $user['id']);
            return ['status' => 200, 'user' => $user, 'token' => $token];
        }

        return ['status' => 401, 'error' => 'Неверное имя пользователя или пароль'];
    }

    function userLogout()
    {
        $token = Auth::bearerToken();
        if ($token) {
            Auth::deleteToken($this->db, $token);
        }
        return ['status' => 200];
    }

    /**
     * Проверяет пароль через password_verify(); если хэш ещё в старом
     * виде (открытый текст), сравнивает напрямую и затем прозрачно
     * перехэшивает пароль в БД, чтобы постепенно перевести всех
     * пользователей на безопасное хранение без принудительного сброса.
     */
    private function verifyPassword($plain, $user)
    {
        $hash = $user['password'] ?? '';

        if (password_verify($plain, $hash)) {
            return true;
        }

        if (hash_equals((string) $hash, (string) $plain)) {
            $newHash = password_hash($plain, PASSWORD_DEFAULT);
            $upd = $this->db->prepare('UPDATE users SET password = :password WHERE id = :id');
            $upd->bindValue(':password', $newHash);
            $upd->bindValue(':id', $user['id']);
            $upd->execute();
            return true;
        }

        return false;
    }

    private function isAdmin()
    {
        $current = Auth::currentUser();
        return $current && strtolower(trim($current['role'] ?? '')) === 'admin';
    }

    /**
     * Проверяет, занято ли имя пользователя (без учёта регистра).
     * $excludeId позволяет исключить самого пользователя при обновлении.
     */
    private function usernameTaken($username, $excludeId = null)
    {
        $sql = 'SELECT id FROM users WHERE LOWER(username) = LOWER(:username)';
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':username', $username);
        if ($excludeId !== null) {
            $stmt->bindValue(':id', $excludeId);
        }
        $stmt->execute();

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    function userGet()
    {
        // Список пользователей нужен и не-админам — например, чтобы выбрать
        // исполнителя задачи (Verantwortlich). Поэтому доступ есть у любого
        // авторизованного, но набор полей разный:
        //   - админ: полные данные (для управления в Abteilungen);
        //   - остальные: только базовое (id/имя/роль/отдел) для выбора людей.
        if (!Auth::currentUser()) {
            return ['status' => 403, 'error' => 'Доступ запрещён'];
        }

        if ($this->isAdmin()) {
            $sql = 'SELECT id, username, name, lastname, role, department_id, vacation_days_per_year FROM users';
        } else {
            $sql = 'SELECT id, name, lastname, role, department_id FROM users';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as &$u) {
            $u['role'] = strtolower(trim($u['role'] ?? ''));
        }
        return ['status' => 200, 'data' => $users];
    }

    function userPost()
    {
        if (!$this->isAdmin()) {
            return ['status' => 403, 'error' => 'Доступ запрещён'];
        }
        if (empty($this->password)) {
            return ['status' => 400, 'error' => 'Пароль обязателен'];
        }

        $this->username = trim((string) $this->username);
        if ($this->username === '') {
            return ['status' => 400, 'error' => 'Benutzername ist erforderlich'];
        }
        if ($this->usernameTaken($this->username)) {
            return ['status' => 409, 'error' => 'Benutzername ist bereits vergeben'];
        }

        $sql = 'INSERT INTO users (username, password, name, lastname, role, department_id, vacation_days_per_year)
                VALUES (:username, :password, :name, :lastname, :role, :department_id, :vacation_days_per_year)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':username', $this->username);
        $stmt->bindValue(':password', password_hash($this->password, PASSWORD_DEFAULT));
        $stmt->bindValue(':name', $this->name);
        $stmt->bindValue(':lastname', $this->lastname);
        $stmt->bindValue(':role', $this->role);
        $stmt->bindValue(':department_id', $this->department_id ?: null);
        $stmt->bindValue(':vacation_days_per_year', (int) $this->vacation_days_per_year);

        try {
            $ok = $stmt->execute();
        } catch (PDOException $e) {
            // Гонка: имя занято между проверкой и вставкой (UNIQUE-индекс).
            if ($e->getCode() === '23000') {
                return ['status' => 409, 'error' => 'Benutzername ist bereits vergeben'];
            }
            throw $e;
        }

        if ($ok) {
            $id = $this->db->lastInsertId();
            return [
                'status' => 201,
                'message' => 'Пользователь зарегистрирован',
                'user' => [
                    'id' => $id,
                    'username' => $this->username,
                    'name' => $this->name,
                    'lastname' => $this->lastname,
                    'role' => $this->role,
                    'department_id' => $this->department_id,
                    'vacation_days_per_year' => (int) $this->vacation_days_per_year
                ]
            ];
        }
        return ['status' => 400, 'error' => 'Ошибка регистрации'];
    }

    function userPut()
    {
        $current = Auth::currentUser();
        $isAdmin = $this->isAdmin();
        $isSelf = $current && (string) $current['id'] === (string) $this->id;

        if (!$isAdmin && !$isSelf) {
            return ['status' => 403, 'error' => 'Доступ запрещён'];
        }

        if (!$isAdmin) {
            // Обычный пользователь может менять только свои имя/фамилию/пароль,
            // но не роль и не отдел.
            $this->role = $current['role'] ?? $this->role;
            $this->department_id = $current['department_id'] ?? $this->department_id;
        }

        $this->username = trim((string) $this->username);
        if ($this->username === '') {
            return ['status' => 400, 'error' => 'Benutzername ist erforderlich'];
        }
        if ($this->usernameTaken($this->username, $this->id)) {
            return ['status' => 409, 'error' => 'Benutzername ist bereits vergeben'];
        }

        if (!empty($this->password)) {
            $sql = 'UPDATE users
                       SET username = :username,
                           password = :password,
                           name = :name,
                           lastname = :lastname,
                           role = :role,
                           department_id = :department_id,
                           vacation_days_per_year = :vacation_days_per_year
                     WHERE id = :id';
        } else {
            $sql = 'UPDATE users
                       SET username = :username,
                           name = :name,
                           lastname = :lastname,
                           role = :role,
                           department_id = :department_id,
                           vacation_days_per_year = :vacation_days_per_year
                     WHERE id = :id';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $this->id);
        $stmt->bindValue(':username', $this->username);
        $stmt->bindValue(':name', $this->name);
        $stmt->bindValue(':lastname', $this->lastname);
        $stmt->bindValue(':role', $this->role);
        $stmt->bindValue(':department_id', $this->department_id ?: null);
        $stmt->bindValue(':vacation_days_per_year', (int) $this->vacation_days_per_year);
        if (!empty($this->password)) {
            $stmt->bindValue(':password', password_hash($this->password, PASSWORD_DEFAULT));
        }

        try {
            $ok = $stmt->execute();
        } catch (PDOException $e) {
            // Гонка: имя занято между проверкой и обновлением (UNIQUE-индекс).
            if ($e->getCode() === '23000') {
                return ['status' => 409, 'error' => 'Benutzername ist bereits vergeben'];
            }
            throw $e;
        }

        if ($ok) {
            return ['status' => 200];
        }
        return ['status' => 400];
    }

    function userDelete($route)
    {
        if (!$this->isAdmin()) {
            return ['status' => 403, 'error' => 'Доступ запрещён'];
        }
        $id = $route[1] ?? $this->id ?? null;
        if (!$id)
            return ['status' => 400, 'error' => 'ID required'];

        $sql = 'DELETE FROM users WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            return ['status' => 200];
        }
        return ['status' => 400];
    }

    function getReqData()
    {
        $raw = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        parse_str($raw, $out);
        return $out;
    }

    function hydrateForm($data)
    {
        if (isset($data['id'])) {
            $this->id = $data['id'];
        }
        if (isset($data['username'])) {
            $this->username = $data['username'];
        }
        if (isset($data['password'])) {
            $this->password = $data['password'];
        }
        if (isset($data['name'])) {
            $this->name = $data['name'];
        }
        if (isset($data['lastname'])) {
            $this->lastname = $data['lastname'];
        }
        if (isset($data['role'])) {
            $this->role = strtolower(trim($data['role']));
        }
        if (isset($data['department_id'])) {
            $this->department_id = $data['department_id'];
        }
        if (isset($data['vacation_days_per_year']) && $data['vacation_days_per_year'] !== '') {
            $this->vacation_days_per_year = max(0, (int) $data['vacation_days_per_year']);
        }
    }
}