<?php

namespace App\Controllers\Admin;

use Core\Controller;

require_once __DIR__ . '/../../models/Admin/CpeSunatCredencial.php';
require_once __DIR__ . '/../../models/Admin/Ies.php';

use App\Models\Admin\CpeSunatCredencial;
use App\Models\Admin\Ies;

class CpeCredencialesController extends Controller
{
    protected $model;
    protected $objIes;

    public function __construct()
    {
        parent::__construct();
        $this->model = new CpeSunatCredencial();
        $this->objIes = new Ies();
    }

    public function index()
    {
        $this->view('admin/cpeCredenciales/index', [
            'module'    => 'admin',
            'iesList'   => $this->objIes->getIes(),
            'pageTitle' => 'Credenciales SUNAT'
        ]);
    }

    public function data()
    {
        header('Content-Type: application/json; charset=utf-8');
        $draw     = (int)($_GET['draw'] ?? 1);
        $start    = (int)($_GET['start'] ?? 0);
        $length   = (int)($_GET['length'] ?? 10);
        $orderCol = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir = (string)($_GET['order'][0]['dir'] ?? 'asc');

        $filters = [
            'id_ies' => $_GET['filter_ies'] ?? null,
            'modo'   => $_GET['filter_modo'] ?? null,
            'activo' => $_GET['filter_activo'] ?? null,
        ];

        $res = $this->model->getPaginated($filters, $length, $start, $orderCol, $orderDir);

        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => $res['total'],
            'recordsFiltered' => $res['filtered'],
            'data'            => $res['data'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function nuevo()
    {
        $this->view('admin/cpeCredenciales/nuevo', [
            'data'     => [],
            'module'   => 'admin',
            'pageTitle' => 'Nueva Credencial SUNAT',
            'iesList'   => $this->objIes->getIes(),
            'errores'  => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function editar($id)
    {
        $row = $this->model->find((int)$id);
        if (!$row) {
            $_SESSION['flash_success'] = 'La credencial no existe.';
            header('Location: ' . BASE_URL . '/admin/cpeCredenciales');
            exit;
        }

        $this->view('admin/cpeCredenciales/editar', [
            'data'     => $row,
            'module'   => 'admin',
            'pageTitle' => 'Editar Credencial SUNAT',
            'iesList'   => $this->objIes->getIes(),
            'errores'  => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function guardar()
    {
        $d = [
            'id'       => $_POST['id'] ?? null,
            'id_ies'   => (int)($_POST['id_ies'] ?? 0),
            'modo'     => $_POST['modo'] ?? 'beta',
            'sol_user' => trim((string)($_POST['sol_user'] ?? '')),
            'sol_pass' => (string)($_POST['sol_pass'] ?? ''),     // opcional
            'cert_pass' => (string)($_POST['cert_pass'] ?? ''),    // opcional
            'activo'   => isset($_POST['activo']) ? 1 : 0,
        ];

        $err = [];
        if ($d['id_ies'] <= 0) $err[] = 'IES es obligatorio.';
        if ($d['sol_user'] === '') $err[] = 'SOL user es obligatorio.';
        if (!in_array($d['modo'], ['beta', 'prod'], true)) $err[] = 'Modo inválido.';
        if ($err) {
            $_SESSION['form_errors'] = $err;
            $redir = empty($d['id']) ? 'admin/cpeCredenciales/nuevo' : 'admin/cpeCredenciales/editar';
            $this->view($redir, [
                    'data'      => $d,
                    'module'    => 'admin',
                    'pageTitle' => 'Credencial SUNAT',
                    'errores'   => $_SESSION['form_errors'] ?? [],
                    'iesList'   => $this->objIes->getIes(),
                ]);
            exit;
        }

        // Archivo PFX (opcional)
        $pfxBinary = null;
        if (!empty($_FILES['cert_pfx']) && ($_FILES['cert_pfx']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['cert_pfx']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['form_errors'] = ['Error subiendo el PFX.'];
                $redir = empty($d['id']) ? 'admin/cpeCredenciales/nuevo' : 'admin/cpeCredenciales/editar';
                $this->view($redir, [
                    'data'      => $d,
                    'module'    => 'admin',
                    'pageTitle' => 'Credencial SUNAT',
                    'errores'   => $_SESSION['form_errors'] ?? [],
                    'iesList'   => $this->objIes->getIes(),
                ]);
                exit;
            }
            $pfxBinary = file_get_contents($_FILES['cert_pfx']['tmp_name']);
        }

        try {
            $ok = $this->model->guardar($d, $pfxBinary); // en el modelo ciframos
            $_SESSION['flash_success'] = $ok ? 'Credenciales guardadas.' : 'No se pudo guardar.';
        } catch (\Throwable $e) {
            $_SESSION['flash_success'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/admin/cpeCredenciales');
        exit;
    }
}
