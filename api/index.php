<?php
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/classes/Auth.php';

// --- CORS ---
// В проде ALLOWED_ORIGIN должен быть точным адресом фронтенда, а не "*",
// иначе API открыт для запросов с любого сайта.
$allowedOrigin = getenv('ALLOWED_ORIGIN') ?: 'http://localhost:3000';
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Max-Age: 86400');

// Preflight-запросы браузера не должны идти дальше роутинга и не требуют авторизации.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

$isProduction = getenv('APP_ENV') !== 'development';
error_reporting(E_ALL);
ini_set('display_errors', $isProduction ? '0' : '1');

$route = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$route = explode("?", $route)[0];
$route = str_replace('/portusApp1', '', $route);
$route = ltrim($route, '/');
$route = explode("/", $route);
$route = array_values(array_filter($route));

// --- Авторизация ---
// Публичные маршруты — единственные, доступные без токена:
//  - вход сотрудника / водителя
//  - саморегистрация водителя (анкета "стать водителем")
// Всё остальное требует валидный Bearer-токен (выданный после логина),
// иначе запрос отклоняется до того, как дойдёт до бизнес-логики.
$publicRoutes = [
    ['users', 'login', 'POST'],
    ['fahrer', 'login', 'POST'],
];

// Регистрация водителя публична, но ограничена по частоте: не более 5 попыток с одного IP в час.
if (($route[0] ?? null) === 'fahrer' && ($route[1] ?? null) === null && $method === 'POST') {
    $db = DB::getInstance();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $s = $db->prepare("SELECT COUNT(*) FROM fahrer_reg_attempts WHERE ip = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $s->bindValue(':ip', $ip);
    $s->execute();
    if ((int)$s->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['status' => 429, 'error' => 'Zu viele Registrierungsversuche. Bitte warten Sie eine Stunde.']);
        exit;
    }
    $i = $db->prepare("INSERT INTO fahrer_reg_attempts (ip) VALUES (:ip)");
    $i->bindValue(':ip', $ip);
    $i->execute();
}

$isPublic = false;
foreach ($publicRoutes as [$seg0, $seg1, $m]) {
    if (($route[0] ?? null) === $seg0 && ($route[1] ?? null) === $seg1 && $method === $m) {
        $isPublic = true;
        break;
    }
}

if (!$isPublic) {
    $db = DB::getInstance();
    $token = Auth::bearerToken();
    $resolved = $token ? Auth::resolve($db, $token) : null;

    if (!$resolved) {
        http_response_code(401);
        echo json_encode(['status' => 401, 'error' => 'Не авторизован']);
        exit;
    }
}

$arr_json = null;

// Объединяем только GET и POST, без COOKIE — исключаем cookie-pollution.
$req = array_merge($_GET, $_POST);

if (count($route) <= 3) {
    switch ($route[0] ?? null) {
        case 'users':
            include(__DIR__ . '/classes/classUsers.php');

            if (($route[1] ?? null) === 'login') {
                $user = new Users();
            } else {
                $user = new Users(
                    $req['id'] ?? null,
                    $req['email'] ?? '',
                    $req['password'] ?? '',
                    $req['name'] ?? '',
                    $req['lastname'] ?? '',
                    $req['role'] ?? '',
                    $req['agree'] ?? false,
                    $req['department_id'] ?? null
                );
            }
            $arr_json = $user->verifyMethod($method, $route);
            break;
        case 'fahrer':
            include(__DIR__ . '/classes/classFahrer.php');

            if (($route[1] ?? null) === 'logout' && $method === 'POST') {
                $db = DB::getInstance();
                $token = Auth::bearerToken();
                if ($token) {
                    Auth::deleteToken($db, $token);
                }
                $arr_json = ['status' => 200];
                break;
            }

            if (($route[1] ?? null) === 'login') {
                $fahrer = new Fahrer();
            } else {
                $fahrer = new Fahrer(
                    $req['id_fahrer'] ?? $route[1] ?? null,
                    $req['name'] ?? '',
                    $req['lastname'] ?? '',
                    $req['email'] ?? '',
                    $req['password'] ?? '',
                    $req['lkw'] ?? '',
                    $req['chassi'] ?? '',
                    $req['phone'] ?? '',
                    $req['terms'] ?? false
                );
            }
            $arr_json = $fahrer->verifyMethod($method, $route);
            break;
        case 'chassi':
            include(__DIR__ . '/classes/classChassi.php');

            $chassi = new chassi(
                $req['id_chassi'] ?? $route[1] ?? null,
                $req['chassi_nummer'] ?? '',
                $req['tuf'] ?? null,
                $req['esp'] ?? null,
            );
            $arr_json = $chassi->verifyMethod($method, $route);
            break;
        case 'lkw':
            include(__DIR__ . '/classes/classLkw.php');

            $lkw = new lkw(
                $req['id_lkw'] ?? $route[1] ?? null,
                $req['tuf'] ?? null,
                $req['esp'] ?? null,
                $req['lkw_nummer'] ?? '',
                $req['status'] ?? 'active'
            );
            $arr_json = $lkw->verifyMethod($method, $route);
            break;
        case 'message_chassi':
            include(__DIR__ . '/classes/classMessageChassi.php');

            $message_chassi = new MessageChassi(
                $req['id_chassi'] ?? null,
                $req['id_message'] ?? $route[1] ?? null,
                $req['type_sender'] ?? '',
                $req['text'] ?? null,
                $req['action_type'] ?? '',
                $req['latitude'] ?? '',
                $req['longitude'] ?? '',
                $req['address'] ?? null
            );
            $arr_json = $message_chassi->verifyMethod($method);
            break;
        case 'message_lkw':
            include(__DIR__ . '/classes/classMessageLkw.php');

            $message_lkw = new MessageLkw(
                $req['id_lkw'] ?? null,
                $req['id_message'] ?? $route[1] ?? null,
                $req['type_sender'] ?? '',
                $req['text'] ?? null,
            );
            $arr_json = $message_lkw->verifyMethod($method, $route);
            break;
        case 'files_chassi':
            include(__DIR__ . '/classes/files_chassi.php');

            $filesCHassi = new files_chassi(
                $req['id_files'] ?? $route[1] ?? null,
                $req['id_message'] ?? null,
                $_FILES['file_name'] ?? ''
            );
            $arr_json = $filesCHassi->verifyMethod($method);
            break;
        case 'files_lkw':
            include(__DIR__ . '/classes/files_lkw.php');

            $filesLkw = new files_lkw(
                $req['id_files'] ?? $route[1] ?? null,
                $req['id_message'] ?? null,
                $_FILES['file_name'] ?? ''
            );
            $arr_json = $filesLkw->verifyMethod($method);
            break;
        case 'departments':
            include(__DIR__ . '/classes/classDepartments.php');

            $departments = new Departments(
                $req['id'] ?? $route[1] ?? null,
                $req['name'] ?? '',
                $req['requester_id'] ?? null
            );
            $arr_json = $departments->verifyMethod($method, $route);
            break;
        case 'tasks':
            include(__DIR__ . '/classes/classTasks.php');

            $tasks = new Tasks($_REQUEST);
            $arr_json = $tasks->verifyMethod($method, $route);
            break;
        case 'notifications':
            include(__DIR__ . '/classes/classNotifications.php');

            $notifications = new Notifications($_REQUEST);
            $arr_json = $notifications->verifyMethod($method, $route);
            break;
        case 'vacations':
            include(__DIR__ . '/classes/classVacations.php');

            $vacations = new Vacations($_REQUEST);
            $arr_json = $vacations->verifyMethod($method, $route);
            break;
        default:
            http_response_code(404);
            $arr_json = ['status' => 404, 'error' => 'Маршрут не найден'];
    }
} else {
    $arr_json = ['status' => 401, 'error' => 'Слишком много сегментов'];
}

echo json_encode($arr_json);
