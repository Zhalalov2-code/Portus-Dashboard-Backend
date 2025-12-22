<?php
require_once __DIR__ . '/../config/db.php';

class Users
{
    private $id;
    private $email;
    private $password;
    private $name;
    private $lastname;
    private $role;
    private $agree;
    private $db;

    function __construct($id = null, $email = '', $password = '', $name = '', $lastname = '', $role = '', $agree = false)
    {
        $this->db = DB::getInstance();
        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
        $this->name = $name;
        $this->lastname = $lastname;
        $this->role = $role;
        $this->agree = $agree;
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
                } elseif (empty($route[1])) {
                    $data = $this->getReqData();
                    $this->hydrateForm($data);
                    return $this->userPost();
                } else {
                    return ['status' => 404, 'error' => 'Не найдено'];
                }

            case 'PUT':
                return $this->userPut();

            case 'DELETE':
                return $this->userDelete($route);

            default:
                return ['status' => 405];
        }
    }

    function userLogin()
    {
        if (empty($this->email) || empty($this->password)) {
            return ['status' => 400, 'error' => 'Email и пароль обязательны'];
        }

        $sql = 'SELECT * FROM users WHERE email = :email LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $this->email);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['password'] === $this->password) {
            return ['status' => 200, 'user' => $user];
        }

        return ['status' => 401, 'error' => 'Неверный email или пароль'];
    }

    function userGet()
    {
        $sql = 'SELECT * FROM users';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return ['status' => 200, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    function userPost()
    {
        $sql = 'INSERT INTO users (email, password, name, lastname, role, agree)
                VALUES (:email, :password, :name, :lastname, :role, :agree)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $this->email);
        $stmt->bindValue(':password', $this->password);
        $stmt->bindValue(':name', $this->name);
        $stmt->bindValue(':lastname', $this->lastname);
        $stmt->bindValue(':role', $this->role);
        $stmt->bindValue(':agree', $this->agree);
        if ($stmt->execute()) {
            $id = $this->db->lastInsertId();
            return [
                'status' => 201,
                'message' => 'Пользователь зарегистрирован',
                'user' => [
                    'id' => $id,
                    'email' => $this->email,
                    'name' => $this->name,
                    'lastname' => $this->lastname,
                    'role' => $this->role,
                    'agree' => $this->agree
                ]
            ];
        }
        return ['status' => 400, 'error' => 'Ошибка регистрации'];
    }

    function userPut()
    {
        $sql = 'UPDATE users
                   SET email = :email, 
                       password = :password, 
                       name = :name, 
                       lastname = :lastname, 
                       role = :role
                 WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $this->id);
        $stmt->bindValue(':email', $this->email);
        $stmt->bindValue(':password', $this->password);
        $stmt->bindValue(':name', $this->name);
        $stmt->bindValue(':lastname', $this->lastname);
        $stmt->bindValue(':role', $this->role);
        if ($stmt->execute()) {
            return ['status' => 200];
        }
        return ['status' => 400];
    }

    function userDelete($route)
    {
        $id = $route[2] ?? null;
        if (!$id) return ['status' => 400, 'error' => 'ID required'];

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

        if(stripos($contentType, 'application/json') !== false) {
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
        if (isset($data['email'])) {
            $this->email = $data['email'];
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
            $this->role = $data['role'];
        }
        if (isset($data['agree'])) {
            $this->agree = $data['agree'];
        }
    }
}