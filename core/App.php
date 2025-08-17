<?php

namespace Core;

class App
{
    protected  $module     = 'Sigi';
    protected $controller = 'HomeController';
    protected  $method     = 'index';
    protected  $params     = [];

    public function __construct()
    {
        date_default_timezone_set('America/Lima');

        $segments = $this->parseUrl();   // ej: ['sigi','docentes','edit',5]
        if (!empty($segments[0]) && strtolower($segments[0]) === 'logout') {
            $this->module     = 'Auth';
            $this->controller = 'LoginController';
            $this->method     = 'salir';
            require_once __DIR__ . "/../app/controllers/Auth/LoginController.php";
            // Ejecuta y termina
            $class = "App\\Controllers\\Auth\\LoginController";
            $controller = new $class();
            $controller->salir();
            exit;
        }
        if (!empty($segments[0]) && ($segments[0]) === 'recuperar') {
            $this->module     = 'Auth';
            $this->controller = 'LoginController';
            $this->method     = 'recuperar';
            require_once __DIR__ . "/../app/controllers/Auth/LoginController.php";
            // Ejecuta y termina
            $class = "App\\Controllers\\Auth\\LoginController";
            $controller = new $class();
            $controller->recuperar();
            exit;
        }
        if (!empty($segments[0]) && ($segments[0]) === 'resetPassword') {
            $this->module     = 'Auth';
            $this->controller = 'LoginController';
            $this->method     = 'resetPassword';
            require_once __DIR__ . "/../app/controllers/Auth/LoginController.php";
            // Ejecuta y termina
            $class = "App\\Controllers\\Auth\\LoginController";
            $controller = new $class();
            $controller->resetPassword();
            exit;
        }
        if (!empty($segments[0]) && ($segments[0]) === 'enviarRecuperacion') {
            $this->module     = 'Auth';
            $this->controller = 'LoginController';
            $this->method     = 'enviarRecuperacion';
            require_once __DIR__ . "/../app/controllers/Auth/LoginController.php";
            // Ejecuta y termina
            $class = "App\\Controllers\\Auth\\LoginController";
            $controller = new $class();
            $controller->enviarRecuperacion();
            exit;
        }
        if (!empty($segments[0]) && strtolower($segments[0]) === 'restablecer') {
            $this->module     = 'Auth';
            $this->controller = 'LoginController';
            $this->method     = 'restablecer';
            require_once __DIR__ . "/../app/controllers/Auth/LoginController.php";
            // Ejecuta y termina
            $class = "App\\Controllers\\Auth\\LoginController";
            $controller = new $class();
            $controller->restablecer();
            exit;
        }
        if (!empty($segments[0]) && ($segments[0]) === 'guardarNuevaPassword') {
            $this->module     = 'Auth';
            $this->controller = 'LoginController';
            $this->method     = 'guardarNuevaPassword';
            require_once __DIR__ . "/../app/controllers/Auth/LoginController.php";
            // Ejecuta y termina
            $class = "App\\Controllers\\Auth\\LoginController";
            $controller = new $class();
            $controller->guardarNuevaPassword();
            exit;
        }
        if (!empty($segments[0])) {
            // para poder hacer el logo dinamico segun el modulo donde se encuentre
            \Core\Auth::start();
            $_SESSION['modulo_vista'] = $segments[0];
            /* ➊ excepción para login / logout */
            if (in_array(strtolower($segments[0]), ['login', 'logout'])) {
                $this->module     = 'Auth';                // módulo Auth
                $segments[0]      = 'login';               // controlador LoginController
            }
            // resto igual ─ si el nombre es carpeta de módulo, cámbialo
            elseif (is_dir(__DIR__ . '/../app/controllers/' . ucfirst(strtolower($segments[0])))) {
                $this->module = ucfirst(strtolower(array_shift($segments)));
            }
        }
        /* ---------- 1. Módulo ---------- */
        if (
            !empty($segments[0]) &&
            is_dir(__DIR__ . '/../app/controllers/' . ucfirst(strtolower($segments[0])))
        ) {
            $this->module = ucfirst(strtolower(array_shift($segments)));
        }

        /* ---------- 2. Controlador ---------- */
        if (!empty($segments[0])) {
            $ctrlName = ucfirst($segments[0]) . 'Controller';
            //$ctrlName = ucfirst(strtolower($segments[0])) . 'Controller';
            $ctrlFile = __DIR__ . "/../app/controllers/{$this->module}/{$ctrlName}.php";
            if (file_exists($ctrlFile)) {
                $this->controller = $ctrlName;
                require_once $ctrlFile;
                array_shift($segments);
            } else {
                return $this->render404("Controlador no encontrado");
            }
        } else {
            // controlador por defecto dentro del módulo
            $ctrlFile = __DIR__ . "/../app/controllers/{$this->module}/HomeController.php";
            require_once $ctrlFile;
        }

        /* ---------- 3. Instancia ---------- */
        $class = "App\\Controllers\\{$this->module}\\{$this->controller}";
        if (!class_exists($class)) {
            return $this->render404("Clase {$class} no existe");
        }
        $this->controller = new $class();

        /* ---------- 4. Método ---------- */
        if (!empty($segments[0]) && method_exists($this->controller, $segments[0])) {
            $this->method = array_shift($segments);
        } elseif (!empty($segments[0])) {
            return $this->render404("Método {$segments[0]} no encontrado");
        }

        /* ---------- 5. Parámetros ---------- */
        $this->params = $segments;

        set_error_handler(function ($severity, $message, $file, $line) {
            error_log("[Error $severity] $message in $file on line $line");
        });
        /* ---------- 6. Ejecutar ---------- */
        return call_user_func_array([$this->controller, $this->method], $this->params);
    }

    /* ------------------------------------ */
    /* Parseo de URL (sin /public)          */
    /* ------------------------------------ */
    private function parseUrl(): array
    {
        if (!empty($_GET['url'])) {
            $route = rtrim($_GET['url'], '/');
        } else {
            $base  = '/'; // ajusta si cambias folder
            $uri   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
            $route = trim(substr($uri, strlen($base)), '/');
        }
        $route = filter_var($route, FILTER_SANITIZE_URL);
        // Aquí agregas la redirección a intranet si está vacío
        if ($route === '' || $route === false) {
            header('Location: ' . BASE_URL . '/intranet');
            exit;
        }
        return $route === '' ? [] : explode('/', $route);
    }

    /* ------------------------------------ */
    private function render404(string $msg)
    {
        http_response_code(404);
        echo "<h1>404</h1><p>{$msg}</p>";
        exit;
    }
}
