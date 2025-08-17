<?php

namespace App\Controllers\Intranet;

use Core\Controller;

require_once __DIR__ . '/../../../app/models/Sigi/DatosSistema.php';

use App\Models\Sigi\DatosSistema;
use Core\Auth;
use Core\Model;
use PDO;

class HomeController extends Controller
{
    protected $datosSistema;
    public function __construct()
    {
        parent::__construct();

        $this->datosSistema = new DatosSistema();
    }
    public function index()
    {
        $user = Auth::user();              // ya validado por middleware
        $db   = (new Model())->getDB();
        $sql = "SELECT s.codigo, s.nombre, s.icono
        FROM sigi_sistemas_integrados s
        JOIN sigi_permisos_usuarios pu ON pu.id_sistema = s.id
        WHERE pu.id_usuario = :uid
        GROUP BY s.id
        ORDER BY s.id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $user['sigi_user_id']]);
        $sistemas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $_SESSION['sigi_modulo_actual'] = 0;
        $_SESSION['sigi_rol_actual']    = 0;
        $datos_sistema = $this->datosSistema->buscar();
        $this->view('intranet/index', [
            'sistemas' => $sistemas,
            'datos_sistema' => $datos_sistema,
            'pageTitle' => 'Panel principal',
            'module'   => 'intranet'
        ]);
    }
}
