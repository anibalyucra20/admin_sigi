<?php

namespace App\Controllers\Admin;

use Core\Controller;
require_once __DIR__ . '/../../models/Admin/Plan.php';
use App\Models\Admin\Plan;

class PlanController extends Controller
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Plan();
    }

    public function index()
    {
        $this->view('admin/plan/index', [
            'module'    => 'admin',
            'pageTitle' => 'Planes'
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
        $this->view('admin/plan/nuevo', [
            'plan'      => [],
            'module'    => 'admin',
            'pageTitle' => 'Nuevo Plan',
            'errores'   => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function editar($id)
    {
        $plan = $this->model->find((int)$id);
        if (!$plan) {
            $_SESSION['flash_success'] = 'El plan no existe.';
            header('Location: ' . BASE_URL . '/admin/planes');
            exit;
        }
        $this->view('admin/plan/editar', [
            'plan'      => $plan,
            'module'    => 'admin',
            'pageTitle' => 'Editar Plan',
            'errores'   => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function guardar()
    {
        $d = [
            'id'                  => $_POST['id'] ?? null,
            'nombre'              => trim($_POST['nombre'] ?? ''),
            'monto'               => $_POST['monto'] ?? '0',
            'limite_usuarios'     => $_POST['limite_usuarios'] ?? 0,
            'limite_reniec'       => $_POST['limite_reniec'] ?? 0,
            'limite_escale'       => $_POST['limite_escale'] ?? 0,
            'limite_facturacion'  => $_POST['limite_facturacion'] ?? 0,
            'activo'              => isset($_POST['activo']) ? 1 : 0,
        ];

        $errores = [];
        if ($d['nombre'] === '') $errores[] = 'El nombre es obligatorio.';
        if (!is_numeric($d['monto'])) $errores[] = 'El monto debe ser numérico.';

        if ($errores) {
            $_SESSION['form_errors'] = $errores;
            $redir = empty($d['id']) ? '/admin/plan/nuevo' : '/admin/planes/editar/' . $d['id'];
            header('Location: ' . BASE_URL . $redir);
            exit;
        }

        try {
            $ok = $this->model->guardar($d);
            $_SESSION['flash_success'] = $ok ? 'Plan guardado correctamente.' : 'No se pudo guardar el plan.';
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $_SESSION['flash_success'] = 'El nombre del plan ya existe (único).';
            } else {
                $_SESSION['flash_success'] = 'Error de base de datos.';
            }
        }

        header('Location: ' . BASE_URL . '/admin/plan');
        exit;
    }

    public function activar($id)
    {
        $this->model->activar((int)$id, 1);
        $_SESSION['flash_success'] = 'Plan activado.';
        header('Location: ' . BASE_URL . '/admin/plan');
        exit;
    }

    public function desactivar($id)
    {
        $this->model->activar((int)$id, 0);
        $_SESSION['flash_success'] = 'Plan desactivado.';
        header('Location: ' . BASE_URL . '/admin/plan');
        exit;
    }

    public function eliminar($id)
    {
        $ok = $this->model->eliminar((int)$id);
        $_SESSION['flash_success'] = $ok ? 'Plan eliminado/desactivado.' : 'No se pudo eliminar.';
        header('Location: ' . BASE_URL . '/admin/plan');
        exit;
    }
}
