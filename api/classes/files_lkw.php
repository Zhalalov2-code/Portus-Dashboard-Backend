<?php

require_once __DIR__ . '/../config/db.php';

class files_lkw
{
    private $db;
    private $id_files;
    private $id_message;
    private $file_name;

    function __construct($id_files = null, $id_message = null, $file_name = '')
    {
        $this->db = DB::getInstance();
        $this->id_files = $id_files;
        $this->id_message = $id_message;
        $this->file_name = $file_name;
    }

    function verifyMethod($method)
    {
        try {
            switch ($method) {
                case 'GET':
                    $data = $this->reqData();
                    $this->hydrateForm($data);
                    return $this->getFiles();
                    break;
                case 'POST':
                    $this->hydrateFormFromRequest();
                    return $this->postFiles();
                    break;
                case 'DELETE':
                    $data = $this->reqData();
                    $this->hydrateForm($data);
                    return $this->deleteFiles();
                    break;
                default:
                    return ['status' => 405, 'message' => 'Метод не поддерживается'];
                    break;
            }
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Внутренняя ошибка сервера'];
        }
    }

    function getFiles()
    {
        try {
            if ($this->id_message === null) {
                return ['status' => 400, 'message' => 'id_message обязателен'];
            }

            // ИСПРАВЛЕНО: используем правильную таблицу
            $sql = "SELECT * FROM files_lkw WHERE id_message = :id_message";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_message', $this->id_message, PDO::PARAM_INT);
            $stmt->execute();

            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['status' => 200, 'data' => $files];
        } catch (PDOException $e) {
            return ['status' => 500, 'message' => 'Ошибка при получении файлов'];
        }
    }

function postFiles()
{
    try {
        if (empty($this->id_message)) {
            return ['status' => 400, 'message' => 'id_message обязателен'];
        }

        if (!isset($_FILES['file_name']) || $_FILES['file_name']['error'] !== UPLOAD_ERR_OK) {
            return ['status' => 400, 'message' => 'Файл не загружен или ошибка загрузки'];
        }

            $uploaded_file = $_FILES['file_name'];
            $target_dir = __DIR__ . "/../uploads/lkw/";        // Создаем директорию если не существует
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Генерируем уникальное имя файла для избежания коллизий
        $original_filename = basename($uploaded_file['name']);
        $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $filename = uniqid('file_', true) . '.' . $extension;
        $target_file = $target_dir . $filename;

        // Проверка на изображение (можно убрать если нужны любые файлы)
        $check = getimagesize($uploaded_file["tmp_name"]);
        if ($check === false) {
            return ['status' => 400, 'message' => 'Файл не является изображением'];
        }

        if ($uploaded_file["size"] > 5000000) { // 5MB
            return ['status' => 400, 'message' => 'Файл слишком большой'];
        }

        if (!in_array($extension, ['jpg', 'png', 'jpeg', 'gif'])) {
            return ['status' => 400, 'message' => 'Только JPG, JPEG, PNG & GIF файлы разрешены'];
        }

        if (!move_uploaded_file($uploaded_file["tmp_name"], $target_file)) {
            return ['status' => 500, 'message' => 'Ошибка при загрузке файла'];
        }

        $sql = "INSERT INTO files_lkw (id_message, file_name, created_ad) VALUES (:id_message, :file_name, CURRENT_TIMESTAMP)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_message', (int)$this->id_message, PDO::PARAM_INT);
        $stmt->bindValue(':file_name', $filename, PDO::PARAM_STR);
        $result = $stmt->execute();

        if (!$result) {
            return ['status' => 500, 'message' => 'Ошибка SQL: ' . print_r($stmt->errorInfo(), true)];
        }

        $inserted_id = $this->db->lastInsertId();
        
        return [
            'status' => 201,
            'message' => 'Файл добавлен',
            'id' => $inserted_id,
            'filename' => $filename
        ];
    } catch (PDOException $e) {
        return ['status' => 500, 'message' => 'Ошибка при добавлении файла: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Внутренняя ошибка сервера'];
    }
}

    function deleteFiles()
    {
        try {
            if (empty($this->id_files)) {
                return ['status' => 400, 'message' => 'id_files обязателен'];
            }

            // Сначала получаем имя файла для удаления с диска
            $sql_select = "SELECT file_name FROM files_lkw WHERE id_files = :id_files";
            $stmt_select = $this->db->prepare($sql_select);
            $stmt_select->bindValue(':id_files', $this->id_files, PDO::PARAM_INT);
            $stmt_select->execute();
            $file_data = $stmt_select->fetch(PDO::FETCH_ASSOC);

            if (!$file_data) {
                return ['status' => 404, 'message' => 'Файл не найден'];
            }

            // Удаляем запись из БД
            $sql = "DELETE FROM files_lkw WHERE id_files = :id_files";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_files', $this->id_files, PDO::PARAM_INT);
            $stmt->execute();

            // Удаляем физический файл с диска
            $file_path = __DIR__ . "/uploads_lkw/" . $file_data['file_name'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            return ['status' => 200, 'message' => 'Файл удален'];
        } catch (PDOException $e) {
            return ['status' => 500, 'message' => 'Ошибка при удалении файла'];
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Внутренняя ошибка сервера'];
        }
    }

    // НОВЫЙ МЕТОД: правильная обработка данных из формы
    function hydrateFormFromRequest()
    {
        if (isset($_POST['id_files'])) {
            $this->id_files = $_POST['id_files'];
        }
        if (isset($_POST['id_message'])) {
            $this->id_message = $_POST['id_message'];
        }
        // Для файлов не используем hydrateForm, работаем с $_FILES напрямую
    }

    // Старый метод оставляем для совместимости
    function reqData()
    {
        try {
            $raw = file_get_contents("php://input");
            $content_type = $_SERVER["CONTENT_TYPE"] ?? '';

            if (stripos($content_type, 'application/json') !== false) {
                $data = json_decode($raw, true);
                return is_array($data) ? $data : [];
            }

            if (stripos($content_type, 'multipart/form-data') !== false) {
                return $_POST;
            }

            parse_str($raw, $out);
            return $out;
        } catch (Exception $e) {
            return [];
        }
    }

    function hydrateForm($data)
    {
        if (isset($data['id_files'])) {
            $this->id_files = $data['id_files'];
        }
        if (isset($data['id_message'])) {
            $this->id_message = $data['id_message'];
        }
        if (isset($data['file_name'])) {
            $this->file_name = $data['file_name'];
        }
    }
}
