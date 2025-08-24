<?php
namespace App\Controllers\Admin;

use Core\Controller;

// Si no usas autoload PSR-4 para modelos, incluye manualmente:
require_once __DIR__ . '/../../models/Admin/Ies.php';

use App\Models\Admin\Ies;

class IesController extends Controller
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Ies();
    }

    public function index()
    {
        $this->view('admin/ies/index', [
            'module'    => 'admin',
            'pageTitle' => 'IES'
        ]);
    }

    public function data()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $draw     = (int)($_GET['draw']    ?? 1);
            $start    = (int)($_GET['start']   ?? 0);
            $length   = (int)($_GET['length']  ?? 10);
            $orderCol = (int)($_GET['order'][0]['column'] ?? 1);
            $orderDir = (string)($_GET['order'][0]['dir'] ?? 'asc');
            $search   = trim($_GET['search']['value'] ?? '');

            $res = $this->model->getPaginated($search, $length, $start, $orderCol, $orderDir);

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $res['total'],
                'recordsFiltered' => $res['filtered'],
                'data'            => $res['data'],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
        }
        exit;
    }

    public function nuevo()
    {
        $this->view('admin/ies/nuevo', [
            'ies'       => [],
            'module'    => 'admin',
            'pageTitle' => 'Nuevo IES',
            'errores'   => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function editar($id)
    {
        $ies = $this->model->find((int)$id);
        if (!$ies) {
            $_SESSION['flash_success'] = 'El IES no existe.';
            header('Location: ' . BASE_URL . '/admin/ies');
            exit;
        }
        $this->view('admin/ies/editar', [
            'ies'       => $ies,
            'module'    => 'admin',
            'pageTitle' => 'Editar IES',
            'errores'   => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function guardar()
    {
        $d = [
            'id'         => $_POST['id'] ?? null,
            'ruc'        => trim($_POST['ruc'] ?? ''),
            'nombre_ies' => trim($_POST['nombre_ies'] ?? ''),
            'direccion'  => trim($_POST['direccion'] ?? ''),
            'telefono'   => trim($_POST['telefono'] ?? ''),
            'llave'      => trim($_POST['llave'] ?? ''),
            'dominio'    => trim($_POST['dominio'] ?? ''),
        ];

        $errores = [];
        if ($d['ruc'] === '')         $errores[] = 'El RUC es obligatorio.';
        if ($d['nombre_ies'] === '')  $errores[] = 'El nombre de la IES es obligatorio.';
        if ($d['dominio'] === '')     $errores[] = 'El dominio es obligatorio.';

        if ($errores) {
            $_SESSION['form_errors'] = $errores;
            $redir = empty($d['id']) ? '/admin/ies/nuevo' : '/admin/ies/editar/'.$d['id'];
            header('Location: ' . BASE_URL . $redir);
            exit;
        }

        try {
            $ok = $this->model->guardar($d);
            $_SESSION['flash_success'] = $ok ? 'IES guardado correctamente.' : 'No se pudo guardar el IES.';
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'ruc') !== false) {
                    $_SESSION['flash_success'] = 'Ya existe un IES con ese RUC.';
                } elseif (strpos($e->getMessage(), 'uq_ies_dominio') !== false || strpos($e->getMessage(), 'dominio') !== false) {
                    $_SESSION['flash_success'] = 'El dominio ya está en uso por otro IES.';
                } else {
                    $_SESSION['flash_success'] = 'Dato duplicado (verifica RUC/Dominio).';
                }
            } else {
                $_SESSION['flash_success'] = 'Error de base de datos.';
            }
        }

        header('Location: ' . BASE_URL . '/admin/ies');
        exit;
    }
    // NUEVOS
    public function suspender($id)
    {
        $motivo = trim($_POST['motivo'] ?? '');
        $this->model->suspender((int)$id, $motivo ?: null);
        $_SESSION['flash_success'] = 'IES suspendida.';
        header('Location: ' . BASE_URL . '/admin/ies');
        exit;
    }

    public function reactivar($id)
    {
        $this->model->reactivar((int)$id);
        $_SESSION['flash_success'] = 'IES reactivada.';
        header('Location: ' . BASE_URL . '/admin/ies');
        exit;
    }
}
