<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Auth.php';

/**
 * Документы транспорта (Fahrzeugschein, страховка, ADR-сертификат и т.п.).
 * Загружает сотрудник, просматривает водитель в приложении.
 * Файлы лежат в api/uploads/documents/ и раздаются Apache статикой.
 */
class VehicleDocuments
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    function verifyMethod($method, $route)
    {
        switch ($method) {
            case 'GET':
                return $this->getList();
            case 'POST':
                return $this->upload();
            case 'DELETE':
                return $this->remove($route[1] ?? null);
            default:
                return ['status' => 405, 'error' => 'Method not allowed'];
        }
    }

    // Список документов машины/прицепа — доступен любому авторизованному
    // (водителю нужен доступ к документам своего транспорта).
    function getList()
    {
        if (!Auth::currentUser() && !Auth::currentFahrer()) {
            return ['status' => 403, 'error' => 'Доступ запрещён'];
        }
        $type = $_GET['vehicle_type'] ?? '';
        $nummer = $_GET['vehicle_nummer'] ?? '';
        if (!in_array($type, ['lkw', 'chassi'], true) || $nummer === '') {
            return ['status' => 400, 'error' => 'vehicle_type и vehicle_nummer обязательны'];
        }
        $stmt = $this->db->prepare(
            'SELECT id, vehicle_type, vehicle_nummer, title, file_name, mime, created_at
             FROM vehicle_documents
             WHERE vehicle_type = :t AND vehicle_nummer = :n
             ORDER BY created_at DESC'
        );
        $stmt->bindValue(':t', $type);
        $stmt->bindValue(':n', $nummer);
        $stmt->execute();
        return ['status' => 200, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    // Загрузка — только сотрудники.
    function upload()
    {
        $staff = Auth::currentUser();
        if (!$staff) {
            return ['status' => 403, 'error' => 'Только сотрудники'];
        }
        $type = $_POST['vehicle_type'] ?? '';
        $nummer = trim($_POST['vehicle_nummer'] ?? '');
        $title = trim($_POST['title'] ?? '');
        if (!in_array($type, ['lkw', 'chassi'], true) || $nummer === '') {
            return ['status' => 400, 'error' => 'vehicle_type и vehicle_nummer обязательны'];
        }
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return ['status' => 400, 'error' => 'Файл не загружен'];
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed, true)) {
            return ['status' => 400, 'error' => 'Разрешены PDF, JPG, PNG'];
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            return ['status' => 400, 'error' => 'Файл больше 10 МБ'];
        }

        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
                return ['status' => 400, 'error' => 'Недопустимый тип файла'];
            }
        }

        $dir = __DIR__ . '/../uploads/documents/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = uniqid('doc_', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            return ['status' => 500, 'error' => 'Ошибка сохранения файла'];
        }

        if ($title === '') {
            $title = pathinfo($file['name'], PATHINFO_FILENAME);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO vehicle_documents (vehicle_type, vehicle_nummer, title, file_name, mime, uploaded_by)
             VALUES (:t, :n, :title, :fn, :mime, :uid)'
        );
        $stmt->bindValue(':t', $type);
        $stmt->bindValue(':n', $nummer);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':fn', $filename);
        $stmt->bindValue(':mime', $mime);
        $stmt->bindValue(':uid', $staff['id']);
        $stmt->execute();

        return ['status' => 201, 'id' => $this->db->lastInsertId(), 'file_name' => $filename];
    }

    // Удаление — только сотрудники.
    function remove($id)
    {
        if (!Auth::currentUser()) {
            return ['status' => 403, 'error' => 'Только сотрудники'];
        }
        if (!$id) {
            return ['status' => 400, 'error' => 'id обязателен'];
        }
        $sel = $this->db->prepare('SELECT file_name FROM vehicle_documents WHERE id = :id');
        $sel->bindValue(':id', $id);
        $sel->execute();
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['status' => 404, 'error' => 'Не найдено'];
        }
        $del = $this->db->prepare('DELETE FROM vehicle_documents WHERE id = :id');
        $del->bindValue(':id', $id);
        $del->execute();

        $path = __DIR__ . '/../uploads/documents/' . $row['file_name'];
        if (is_file($path)) {
            @unlink($path);
        }
        return ['status' => 200];
    }
}
