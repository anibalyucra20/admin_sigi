<?php

// public/index.php (muy arriba)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
define('IS_API', strpos($path, '/api/') === 0);

if (IS_API) {
    // CORS abierto (ya lo tienes)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, X-Idempotency-Key');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // JSON error handling para API:
    set_error_handler(function ($severity, $message, $file, $line) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => ['code' => 'PHP_ERROR', 'message' => $message, 'file' => $file, 'line' => $line]], JSON_UNESCAPED_UNICODE);
        exit;
    });
    set_exception_handler(function ($ex) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => ['code' => 'UNCAUGHT', 'message' => $ex->getMessage()]], JSON_UNESCAPED_UNICODE);
        exit;
    });
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => ['code' => 'FATAL', 'message' => $e['message'], 'file' => $e['file'], 'line' => $e['line']]], JSON_UNESCAPED_UNICODE);
        }
    });
}



// Base path y cookies
$basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
if ($basePath === '\\' || $basePath === '' || $basePath === '/') {
    $basePath = '/';
}
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $basePath,
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

require_once '../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../core/Model.php';
require_once '../core/App.php';
require_once '../core/Auth.php';
require_once '../core/Controller.php';
require_once '../core/Router.php';

$app = new \Core\App();
