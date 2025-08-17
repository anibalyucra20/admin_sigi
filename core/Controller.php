<?php

namespace Core;

use Core\Auth;

class Controller
{
    public function __construct()
    {
        \Core\Auth::start();
        $allowedNoAuth = ['logout', 'login', 'login/acceder', 'recuperar', 'reestablecer']; // rutas públicas

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
        // Refresca permisos SOLO si está logueado y la ruta no es pública
        if ($isUserLogged && !$isRutaPublica && isset($_SESSION['sigi_user_id'])) {
            $id_usuario = $_SESSION['sigi_user_id'];
            $db = (new \Core\Model())->getDB();
            $sql = "SELECT psu.id_sistema, s.nombre as sistema, psu.id_rol, r.nombre as rol
                FROM sigi_permisos_usuarios psu
                INNER JOIN sigi_sistemas_integrados s ON s.id = psu.id_sistema
                INNER JOIN sigi_roles r ON r.id = psu.id_rol
                WHERE psu.id_usuario = ? ORDER BY r.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id_usuario]);
            $_SESSION['sigi_permisos_usuario'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            //obtener informacion de logos para cargar en vista
            $sql2 = "SELECT favicon, logo FROM sigi_datos_sistema WHERE id=1";
            $datos_logos = $db->prepare($sql2);
            $datos_logos->execute();
            $datos_logos = $datos_logos->fetch(\PDO::FETCH_ASSOC);
            $_SESSION['favicon'] = $datos_logos['favicon'];
            $_SESSION['logo'] = $datos_logos['logo'];
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
