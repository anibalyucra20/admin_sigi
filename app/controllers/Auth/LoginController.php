<?php

namespace App\Controllers\Auth;

require_once __DIR__ . '/../../../vendor/autoload.php';

use Core\Controller;
use PDO;

class LoginController extends Controller
{

    public function __construct()
    {
        parent::__construct();
    }
    /* Formulario */
    public function index()
    {
        $this->view('auth/login', [
            'pageTitle' => 'Iniciar Sesión',
            'module'    => 'auth'
        ]);
    }

    /* POST credenciales */
    public function acceder()
    {

        $dni  = $_POST['dni']      ?? '';
        $pass = $_POST['password'] ?? '';

        $db = (new \Core\Model())->getDB();

        $sql = "SELECT * FROM usuarios WHERE dni = :dni AND estado = 1 LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':dni' => $dni]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);



        if ($user && password_verify($pass, $user['password'])) {
            //guardamos el registro de session
            $llave = bin2hex(random_bytes(5));
            $token = password_hash($llave, PASSWORD_DEFAULT);

            $sql = "INSERT INTO sesiones (id_usuario, fecha_hora_inicio, fecha_hora_fin, token, ip, estado) VALUES (?,  NOW(), NOW(), ?, ?, 1)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$user['id'], $llave, $_SERVER['REMOTE_ADDR']]);

            $user['id_session'] = $db->lastInsertId();
            $user['token'] = $token;

            \Core\Auth::login($user);
           
            // registro de log
            (new \Core\Model())->log($user['id'], 'LOGIN', 'Ingreso al sistema');
            // --- FIN BLOQUE CARGA DE PERMISOS ---

            header('Location: ' . BASE_URL . '/admin');
            exit;                                   // ← imprescindible
        }

        header('Location: ' . BASE_URL . '/login?error=1');
        exit;
    }
    public function salir()
    {
        \Core\Auth::logout();
        header('Location: ' . BASE_URL . '/login');
        exit;
    }



    public function recuperar()
    {
        // Form para pedir correo/DNI
        $this->view('auth/recuperar', ['pageTitle' => 'Recuperar contraseña']);
    }


}
