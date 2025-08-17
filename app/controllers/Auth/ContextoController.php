<?php
namespace App\Controllers\Auth;

use Core\Controller;
use Core\Auth;

class ContextoController extends Controller
{
    public function set()
    {
        Auth::start();
        if (Auth::user() === null) {
            header('Location: '.BASE_URL.'/login');
            exit;
        }

        if (isset($_POST['sede'])) {
            $_SESSION['sigi_sede_actual'] = (int)$_POST['sede'];
        }
        if (isset($_POST['periodo'])) {
            $_SESSION['sigi_periodo_actual_id'] = (int)$_POST['periodo'];
        }
        // Regresa a la página previa
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    public function actualizar_rol_sesion(){
        if (!empty($_GET['permiso'])) {
            list($id_sistema, $id_rol) = explode('-', $_GET['permiso']);
            $_SESSION['sigi_modulo_actual'] = $id_sistema;
            $_SESSION['sigi_rol_actual']    = $id_rol;
        }

        // Seguridad: sólo rutas internas
        if (!empty($_GET['redirect']) && strpos($_GET['redirect'], '/') === 0) {
            $redirect = $_GET['redirect'];
        } else {
            $redirect = BASE_URL . '/intranet';
        }

        header('Location: ' . $redirect);
        exit;
    }
}
