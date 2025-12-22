    <?php
    require_once __DIR__ . '/../config/db.php';

    class Fahrer
    {
        private $id_fahrer;
        private $name;
        private $lastname;
        private $lkw;
        private $chassi;
        private $email;
        private $password;
        private $phone;
        private $terms;
        private $db;

        function __construct($id_fahrer = null, $name = '', $lastname = '', $email = '', $password = '', $lkw = '', $chassi = '', $phone = '', $terms = false)
        {
            $this->db = DB::getInstance();
            $this->id_fahrer = $id_fahrer;
            $this->name = $name;
            $this->lastname = $lastname;
            $this->email = $email;
            $this->password = $password;
            $this->lkw = $lkw;
            $this->chassi = $chassi;
            $this->phone = $phone;
            $this->terms = $terms;
        }

        function verifyMethod($method, $route)
        {
            $res1 = $route[1] ?? null;
            switch ($method) {
                case 'GET':
                    return $this->fahrerGet();
                    break;
                case 'POST':
                    if ($res1 === 'login') {
                        $data = $this->getReqData();
                        $this->hydrateForm($data);
                        return $this->fahrerLogin();
                    } elseif ($res1 === null) {
                        $data = $this->getReqData();
                        $this->hydrateForm($data);
                        return $this->fahrerPost();
                    } else {
                        return ['status' => 404, 'error' => 'Не найдено'];
                    }
                    break;
                case 'PUT':
                    $data = $this->getReqData();
                    $this->hydrateForm($data);
                    return $this->fahrerPut();
                    break;
                case 'DELETE':
                    return $this->fahrerDelete($res1);
                    break;
                default:
                    return ['status' => 405, 'error' => 'Method not allowed'];
            }
        }

        function fahrerLogin()
        {
            if (empty($this->email) || empty($this->password)) {
                return ['status' => 400, 'error' => 'Email и пароль обязательны'];
            }

            $sql = 'SELECT * FROM fahrer WHERE email = :email LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', $this->email);
            $stmt->execute();

            $fahrer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fahrer && $fahrer['password'] === $this->password) {
                return ['status' => 200, 'fahrer' => $fahrer];
            }

            return ['status' => 401, 'error' => 'Неверный email или пароль'];
        }

        function fahrerGet()
        {
            $sql = 'SELECT * FROM fahrer';
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        function fahrerPost()
        {
            $sql = 'INSERT INTO fahrer (email, password, name, lastname, lkw, chassi, phone, terms)
                    VALUES (:email, :password, :name, :lastname, :lkw, :chassi, :phone, :terms)';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', $this->email);
            $stmt->bindValue(':password', $this->password);
            $stmt->bindValue(':name', $this->name);
            $stmt->bindValue(':lastname', $this->lastname);
            $stmt->bindValue(':lkw', $this->lkw);
            $stmt->bindValue(':chassi', $this->chassi);
            $stmt->bindValue(':phone', $this->phone);
            $stmt->bindValue(':terms', $this->terms, PDO::PARAM_BOOL);
            if ($stmt->execute()) {
                $id_fahrer = $this->db->lastInsertId();
                return [
                    'status' => 201,
                    'message' => 'Fahrer зарегистрирован',
                    'fahrer' => [
                        'id_fahrer' => $id_fahrer,
                        'name' => $this->name,
                        'lastname' => $this->lastname,
                        'email' => $this->email,
                        'password' => $this->password,
                        'lkw' => $this->lkw,
                        'chassi' => $this->chassi,
                        'phone' => $this->phone,
                        'terms' => $this->terms
                    ]
                ];
            }
            return ['status' => 400, 'error' => 'Ошибка регистрации Fahrer'];
        }

        function fahrerPut()
        {
            $sql = 'UPDATE fahrer
                    SET name = :name, 
                        lastname = :lastname, 
                        lkw = :lkw, 
                        chassi = :chassi,
                        phone = :phone,
                        terms = :terms
                    WHERE id_fahrer = :id_fahrer';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_fahrer', $this->id_fahrer);
            $stmt->bindValue(':name', $this->name);
            $stmt->bindValue(':lastname', $this->lastname);
            $stmt->bindValue(':lkw', $this->lkw);
            $stmt->bindValue(':chassi', $this->chassi);
            $stmt->bindValue(':phone', $this->phone);
            $stmt->bindValue(':terms', $this->terms, PDO::PARAM_BOOL);
            if ($stmt->execute()) {
                return ['status' => 200];
            }
            return ['status' => 400];
        }

        function fahrerDelete($id)
        {
            if (!$id) return ['status' => 400, 'error' => 'ID_fahrer required'];

            $sql = 'DELETE FROM fahrer WHERE id_fahrer = :id_fahrer';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_fahrer', $id);

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
            if (isset($data['id_fahrer'])) {
                $this->id_fahrer = $data['id_fahrer'];
            }
            if (isset($data['name'])) {
                $this->name = $data['name'];
            }
            if (isset($data['lastname'])) {
                $this->lastname = $data['lastname'];
            }
            if (isset($data['email'])) {
                $this->email = $data['email'];
            }
            if (isset($data['password'])) {
                $this->password = $data['password'];
            }
            if (isset($data['lkw'])) {
                $this->lkw = $data['lkw'];
            }
            if (isset($data['chassi'])) {
                $this->chassi = $data['chassi'];
            }
            if (isset($data['phone'])) {
                $this->phone = $data['phone'];
            }
            if (isset($data['terms'])) {
                $this->terms = $data['terms'];
            }
        }
    }
