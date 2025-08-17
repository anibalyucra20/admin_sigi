<?php
// Calcula el path base del proyecto automáticamente
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


// Configuración general
//require_once __DIR__ . '/../config/config.php';
require_once '../config/config.php';
// Núcleo MVC
/*require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/App.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Router.php';*/

require_once '../core/Model.php';
require_once '../core/App.php';
require_once '../core/Auth.php';
require_once '../core/Controller.php';
require_once '../core/Router.php';

// Arranca la aplicación
$app = new \Core\App();
