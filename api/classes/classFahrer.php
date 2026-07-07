    <?php
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/Auth.php';
    require_once __DIR__ . '/classVehicleHistory.php';

    class Fahrer
    {
        private $id_fahrer;
        private $driver_code;
        private $name;
        private $lastname;
        private $lkw;
        private $chassi;
        private $password;
        private $phone;
        private $terms;
        private $db;

        function __construct($id_fahrer = null, $name = '', $lastname = '', $password = '', $lkw = '', $chassi = '', $phone = '', $terms = false, $driver_code = '')
        {
            $this->db = DB::getInstance();
            $this->id_fahrer = $id_fahrer;
            $this->name = $name;
            $this->lastname = $lastname;
            $this->password = $password;
            $this->lkw = $lkw;
            $this->chassi = $chassi;
            $this->phone = $phone;
            $this->terms = $terms;
            $this->driver_code = $driver_code;
        }

        function verifyMethod($method, $route)
        {
            $res1 = $route[1] ?? null;
            switch ($method) {
                case 'GET':
                    if ($res1 === 'me') {
                        return $this->fahrerMe();
                    }
                    return $this->fahrerGet();
                    break;
                case 'POST':
                    if ($res1 === 'login') {
                        $data = $this->getReqData();
                        $this->hydrateForm($data);
                        return $this->fahrerLogin();
                    } elseif ($res1 === 'push_token') {
                        return $this->savePushToken();
                    } elseif ($res1 === 'change_password') {
                        return $this->changePassword();
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
            if (empty($this->driver_code) || empty($this->password)) {
                return ['status' => 400, 'error' => 'Код водителя и пароль обязательны'];
            }

            $sql = 'SELECT * FROM fahrer WHERE driver_code = :code LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':code', $this->driver_code);
            $stmt->execute();

            $fahrer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fahrer && $this->verifyPassword($this->password, $fahrer)) {
                unset($fahrer['password']);
                $token = Auth::issueToken($this->db, 'fahrer', $fahrer['id_fahrer']);
                return ['status' => 200, 'fahrer' => $fahrer, 'token' => $token];
            }

            return ['status' => 401, 'error' => 'Неверный код водителя или пароль'];
        }

        /**
         * password_verify() с прозрачным переходом со старых открытых
         * паролей: если хэш не совпадает, но совпадает как открытый текст,
         * пароль перехэшируется и сохраняется автоматически.
         */
        private function verifyPassword($plain, $fahrer)
        {
            $hash = $fahrer['password'] ?? '';

            if (password_verify($plain, $hash)) {
                return true;
            }

            if (hash_equals((string) $hash, (string) $plain)) {
                $newHash = password_hash($plain, PASSWORD_DEFAULT);
                $upd = $this->db->prepare('UPDATE fahrer SET password = :password WHERE id_fahrer = :id_fahrer');
                $upd->bindValue(':password', $newHash);
                $upd->bindValue(':id_fahrer', $fahrer['id_fahrer']);
                $upd->execute();
                return true;
            }

            return false;
        }

        /** Генерирует уникальный код водителя формата DR-XXXXXX (без 0/O/1/I). */
        private function generateDriverCode()
        {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $len = strlen($alphabet);
            do {
                $code = 'DR-';
                for ($i = 0; $i < 6; $i++) {
                    $code .= $alphabet[random_int(0, $len - 1)];
                }
                $chk = $this->db->prepare('SELECT COUNT(*) FROM fahrer WHERE driver_code = :c');
                $chk->bindValue(':c', $code);
                $chk->execute();
            } while ((int) $chk->fetchColumn() > 0);
            return $code;
        }

        /**
         * Свои данные для мобильного приложения (GET /fahrer/me).
         * Auth::resolve() уже загрузил свежую строку из БД по токену —
         * возвращаем её, чтобы приложение могло обновить профиль live.
         */
        function fahrerMe()
        {
            $self = Auth::currentFahrer();
            if (!$self) {
                return ['status' => 403, 'error' => 'Только для водителей'];
            }
            return ['status' => 200, 'fahrer' => $self];
        }

        /**
         * Сохранить Expo push-токен текущего водителя (POST /fahrer/push_token).
         * Приложение вызывает после логина; токен используется ExpoPush.
         */
        function savePushToken()
        {
            $self = Auth::currentFahrer();
            if (!$self) {
                return ['status' => 403, 'error' => 'Только для водителей'];
            }
            $data = $this->getReqData();
            $token = trim((string) ($data['push_token'] ?? ''));
            if ($token === '' || strlen($token) > 255) {
                return ['status' => 400, 'error' => 'push_token обязателен'];
            }
            $stmt = $this->db->prepare('UPDATE fahrer SET push_token = :t WHERE id_fahrer = :id');
            $stmt->bindValue(':t', $token);
            $stmt->bindValue(':id', $self['id_fahrer']);
            $stmt->execute();
            return ['status' => 200];
        }

        /**
         * Смена собственного пароля водителем (POST /fahrer/change_password).
         * Требует старый пароль. Тело: { old_password, new_password }.
         */
        function changePassword()
        {
            $self = Auth::currentFahrer();
            if (!$self) {
                return ['status' => 403, 'error' => 'Только для водителей'];
            }
            $data = $this->getReqData();
            $old = (string) ($data['old_password'] ?? '');
            $new = (string) ($data['new_password'] ?? '');
            if ($old === '' || $new === '') {
                return ['status' => 400, 'error' => 'Укажите старый и новый пароль'];
            }
            if (strlen($new) < 4) {
                return ['status' => 400, 'error' => 'Новый пароль слишком короткий'];
            }

            $stmt = $this->db->prepare('SELECT id_fahrer, password FROM fahrer WHERE id_fahrer = :id LIMIT 1');
            $stmt->bindValue(':id', $self['id_fahrer']);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !$this->verifyPassword($old, $row)) {
                return ['status' => 401, 'error' => 'Старый пароль неверен'];
            }

            $upd = $this->db->prepare('UPDATE fahrer SET password = :p WHERE id_fahrer = :id');
            $upd->bindValue(':p', password_hash($new, PASSWORD_DEFAULT));
            $upd->bindValue(':id', $self['id_fahrer']);
            $upd->execute();
            return ['status' => 200];
        }

        function fahrerGet()
        {
            // Список водителей содержит ПДн (телефон) — доступен только
            // авторизованным сотрудникам, не самим водителям.
            if (!Auth::currentUser()) {
                return ['status' => 403, 'error' => 'Доступ запрещён'];
            }
            $sql = 'SELECT * FROM fahrer';
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $fahrers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fahrers as &$f) {
                unset($f['password']);
            }
            return $fahrers;
        }

        function fahrerPost()
        {
            $driverCode = $this->generateDriverCode();

            $sql = 'INSERT INTO fahrer (driver_code, password, name, lastname, lkw, chassi, phone, terms)
                    VALUES (:driver_code, :password, :name, :lastname, :lkw, :chassi, :phone, :terms)';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':driver_code', $driverCode);
            $stmt->bindValue(':password', password_hash($this->password, PASSWORD_DEFAULT));
            $stmt->bindValue(':name', $this->name);
            $stmt->bindValue(':lastname', $this->lastname);
            $stmt->bindValue(':lkw', $this->lkw);
            $stmt->bindValue(':chassi', $this->chassi);
            $stmt->bindValue(':phone', $this->phone);
            $stmt->bindValue(':terms', $this->terms, PDO::PARAM_BOOL);
            if ($stmt->execute()) {
                $id_fahrer = $this->db->lastInsertId();

                // Если водителю сразу назначили транспорт — открываем историю.
                VehicleHistory::onFahrerChange(
                    $this->db,
                    $id_fahrer,
                    trim($this->name . ' ' . $this->lastname),
                    '',
                    $this->lkw,
                    '',
                    $this->chassi
                );

                return [
                    'status' => 201,
                    'message' => 'Fahrer зарегистрирован',
                    'fahrer' => [
                        'id_fahrer' => $id_fahrer,
                        'driver_code' => $driverCode,
                        'name' => $this->name,
                        'lastname' => $this->lastname,
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
            $staff = Auth::currentUser();
            $self = Auth::currentFahrer();
            $isSelf = $self && (string) $self['id_fahrer'] === (string) $this->id_fahrer;

            if (!$staff && !$isSelf) {
                return ['status' => 403, 'error' => 'Доступ запрещён'];
            }

            // Текущее состояние — чтобы зафиксировать изменение назначения в истории.
            $cur = $this->db->prepare('SELECT name, lastname, lkw, chassi FROM fahrer WHERE id_fahrer = :id LIMIT 1');
            $cur->bindValue(':id', $this->id_fahrer);
            $cur->execute();
            $old = $cur->fetch(PDO::FETCH_ASSOC) ?: ['lkw' => '', 'chassi' => '', 'name' => '', 'lastname' => ''];

            // Смена пароля — опционально: обновляем только если передан непустой.
            $changePassword = isset($this->password) && $this->password !== '';

            $sql = 'UPDATE fahrer
                    SET name = :name,
                        lastname = :lastname,
                        lkw = :lkw,
                        chassi = :chassi,
                        phone = :phone,
                        terms = :terms'
                . ($changePassword ? ', password = :password' : '') .
                ' WHERE id_fahrer = :id_fahrer';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_fahrer', $this->id_fahrer);
            $stmt->bindValue(':name', $this->name);
            $stmt->bindValue(':lastname', $this->lastname);
            $stmt->bindValue(':lkw', $this->lkw);
            $stmt->bindValue(':chassi', $this->chassi);
            $stmt->bindValue(':phone', $this->phone);
            $stmt->bindValue(':terms', $this->terms, PDO::PARAM_BOOL);
            if ($changePassword) {
                $stmt->bindValue(':password', password_hash($this->password, PASSWORD_DEFAULT));
            }
            if ($stmt->execute()) {
                $name = trim(($this->name ?: $old['name']) . ' ' . ($this->lastname ?: $old['lastname']));
                VehicleHistory::onFahrerChange(
                    $this->db,
                    $this->id_fahrer,
                    $name,
                    $old['lkw'],
                    $this->lkw,
                    $old['chassi'],
                    $this->chassi
                );

                // Push водителю — только если транспорт переназначил сотрудник
                // (о собственных действиях водителя уведомлять не нужно).
                if ($staff) {
                    $changes = [];
                    if (trim((string) $old['lkw']) !== trim((string) $this->lkw)) {
                        $changes[] = $this->lkw !== '' ? "Грузовик: {$this->lkw}" : 'Грузовик снят';
                    }
                    if (trim((string) $old['chassi']) !== trim((string) $this->chassi)) {
                        $changes[] = $this->chassi !== '' ? "Прицеп: {$this->chassi}" : 'Прицеп отцеплен';
                    }
                    if ($changes) {
                        require_once __DIR__ . '/ExpoPush.php';
                        ExpoPush::toFahrer($this->db, $this->id_fahrer, 'Ваш транспорт обновлён', implode(' · ', $changes));
                    }
                }
                return ['status' => 200];
            }
            return ['status' => 400];
        }

        function fahrerDelete($id)
        {
            if (!Auth::currentUser()) {
                return ['status' => 403, 'error' => 'Доступ запрещён'];
            }
            if (!$id) return ['status' => 400, 'error' => 'ID_fahrer required'];

            // Прежде чем удалить водителя — закрываем его активные интервалы
            // в истории, чтобы машины не остались «занятыми» несуществующим водителем.
            VehicleHistory::closeAllForFahrer($this->db, $id);

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
            if (isset($data['driver_code'])) {
                $this->driver_code = trim($data['driver_code']);
            }
            if (isset($data['name'])) {
                $this->name = $data['name'];
            }
            if (isset($data['lastname'])) {
                $this->lastname = $data['lastname'];
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
