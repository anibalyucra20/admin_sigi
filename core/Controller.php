<?php

namespace Core;

use Core\Auth;

class Controller
{
    public function __construct()
    {
        \Core\Auth::start();
        $allowedNoAuth = ['logout', 'login', 'login/acceder', 'recuperar', 'restablecer']; // rutas públicas

        $current = $_GET['url'] ?? '';

        if (!function_exists('str_starts_with')) {
            function str_starts_with($haystack, $needle)
            {
                return substr($haystack, 0, strlen($needle)) === $needle;
            }
        }
        // Validar que esté logueado o tenga sesión válida
        $isAuthRoute = str_starts_with(static::class, 'App\Controllers\Auth');
        $isRutaPublica = in_array($current, $allowedNoAuth);
        $isUserLogged = \Core\Auth::user() !== null;

        if (!$isAuthRoute && !$isRutaPublica) {
            if (!$isUserLogged || !\Core\Auth::validarSesion()) {
                header('Location: ' . BASE_URL . '/login');
                exit;
            }
        }
        
    }
    public function modelo($modelo)
    {
        var_dump($modelo);
        $modelo = str_replace('/', '\\', $modelo);
        $clase = "App\\Models\\" . $modelo;
        var_dump($clase);
        return new $clase();
    }

    /**
     * Cargar una vista y pasarle datos (array keys = variables)
     */
    public function view(string $view, array $data = [])
    {
        extract($data);
        require __DIR__ . '/../app/views/' . $view . '.php';
    }
}
