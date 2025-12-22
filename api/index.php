<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Max-Age: 86400');
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

$route = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$route = explode("?", $route)[0];
$route = str_replace('/portusApp1', '', $route);
$route = ltrim($route, '/');
$route = explode("/", $route);
$route = array_values(array_filter($route));

$arr_json = null;


if (count($route) <= 3) {
    switch ($route[0] ?? null) {
        case 'users':
            include(__DIR__ . '/classes/classUsers.php');

            if (($route[1] ?? null) === 'login') {
                $user = new Users();
            } else {
                $user = new Users(
                    $_REQUEST['id'] ?? null,
                    $_REQUEST['email'] ?? '',
                    $_REQUEST['password'] ?? '',
                    $_REQUEST['name'] ?? '',
                    $_REQUEST['lastname'] ?? '',
                    $_REQUEST['role'] ?? '',
                    $_REQUEST['agree'] ?? false
                );
            }
            $arr_json = $user->verifyMethod($method, $route);
            break;
        case 'fahrer':
            include(__DIR__ . '/classes/classFahrer.php');

            if (($route[1] ?? null) === 'login') {
                $fahrer = new Fahrer();
            } else {
                $fahrer = new Fahrer(
                    $_REQUEST['id_fahrer'] ?? $route[1] ?? null,
                    $_REQUEST['name'] ?? '',
                    $_REQUEST['lastname'] ?? '',
                    $_REQUEST['email'] ?? '',
                    $_REQUEST['password'] ?? '',
                    $_REQUEST['lkw'] ?? '',
                    $_REQUEST['chassi'] ?? '',
                    $_REQUEST['phone'] ?? '',
                    $_REQUEST['terms'] ?? false
                );
            }
            $arr_json = $fahrer->verifyMethod($method, $route);
            break;
        case 'chassi':
            include(__DIR__ . '/classes/classChassi.php');

            $chassi = new chassi(
                $_REQUEST['id_chassi'] ?? $route[1] ?? null,
                $_REQUEST['chassi_nummer'] ?? '',
                $_REQUEST['tuf'] ?? null,
                $_REQUEST['esp'] ?? null,
            );
            $arr_json = $chassi->verifyMethod($method, $route);
            break;
        case 'lkw':
            include(__DIR__ . '/classes/classLkw.php');

            $lkw = new lkw(
                $_REQUEST['id_lkw'] ?? $route[1] ?? null,
                $_REQUEST['tuf'] ?? null,
                $_REQUEST['esp'] ?? null,
                $_REQUEST['lkw_nummer'] ?? ''
            );
            $arr_json = $lkw->verifyMethod($method, $route);
            break;
        case 'message_chassi':
            include(__DIR__ . '/classes/classMessageChassi.php');

            $message_chassi = new MessageChassi(
                $_REQUEST['id_chassi'] ?? null,
                $_REQUEST['id_message'] ?? $route[1] ?? null,
                $_REQUEST['type_sender'] ?? '',
                $_REQUEST['text'] ?? null,
                $_REQUEST['action_type'] ?? '',
                $_REQUEST['latitude'] ?? '',
                $_REQUEST['longitude'] ?? '',
                $_REQUEST['address'] ?? null
            );
            $arr_json = $message_chassi->verifyMethod($method);
            break;
        case 'message_lkw':
            include(__DIR__ . '/classes/classMessageLkw.php');

            $message_lkw = new MessageLkw(
                $_REQUEST['id_lkw'] ?? null,
                $_REQUEST['id_message'] ?? $route[1] ?? null,
                $_REQUEST['type_sender'] ?? '',
                $_REQUEST['text'] ?? null,
            );
            $arr_json = $message_lkw->verifyMethod($method, $route);
            break;
        case 'files_chassi':
            include(__DIR__ . '/classes/files_chassi.php');

            $filesCHassi = new files_chassi(
                $_REQUEST['id_files'] ?? $route[1] ?? null,
                $_REQUEST['id_message'] ?? null,
                $_FILES['file_name'] ?? ''
            );
            $arr_json = $filesCHassi->verifyMethod($method);
            break;
        case 'files_lkw':
            include(__DIR__ . '/classes/files_lkw.php');

            $filesLkw = new files_lkw(
                $_REQUEST['id_files'] ?? $route[1] ?? null,
                $_REQUEST['id_message'] ?? null,
                $_FILES['file_name'] ?? ''
            );
            $arr_json = $filesLkw->verifyMethod($method);
            break;
        default:
            $arr_json = ['status' => 402, 'error' => 'Неверный маршрут'];
    }
} else {
    $arr_json = ['status' => 401, 'error' => 'Слишком много сегментов'];
}

echo json_encode($arr_json);
