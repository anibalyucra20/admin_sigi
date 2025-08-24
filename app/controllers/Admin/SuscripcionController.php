<?php

namespace App\Controllers\Admin;

use Core\Controller;

// requires si no hay autoload PSR-4
require_once __DIR__ . '/../../models/Admin/Suscripcion.php';
// ...
require_once __DIR__ . '/../../models/Admin/ApiKey.php';

use App\Models\Admin\ApiKey;


use App\Models\Admin\Suscripcion;

class SuscripcionController extends Controller
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Suscripcion();
    }

    public function index()
    {
        // Para filtros de la vista
        $ies    = $this->model->allIes();
        $planes = $this->model->allPlanes();

        $this->view('admin/suscripcion/index', [
            'ies'       => $ies,
            'planes'    => $planes,
            'module'    => 'admin',
            'pageTitle' => 'suscripcion'
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

            $filters = [
                'id_ies' => $_GET['filter_ies']   ?? null,
                'id_plan' => $_GET['filter_plan']  ?? null,
                'estado' => $_GET['filter_estado'] ?? null,
                'ciclo'  => $_GET['filter_ciclo'] ?? null,
            ];

            $res = $this->model->getPaginated($filters, $length, $start, $orderCol, $orderDir);

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
        $this->view('admin/suscripcion/nuevo', [
            'sub'      => [],
            'ies'      => $this->model->allIes(),
            'planes'   => $this->model->allPlanes(),
            'module'   => 'admin',
            'pageTitle' => 'Nueva Suscripción',
            'errores'  => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function editar($id)
    {
        $sub = $this->model->find((int)$id);
        if (!$sub) {
            $_SESSION['flash_success'] = 'La suscripción no existe.';
            header('Location: ' . BASE_URL . '/admin/suscripcion');
            exit;
        }
        $this->view('admin/suscripcion/editar', [
            'sub'      => $sub,
            'ies'      => $this->model->allIes(),
            'planes'   => $this->model->allPlanes(),
            'module'   => 'admin',
            'pageTitle' => 'Editar Suscripción',
            'errores'  => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function guardar()
    {
        $d = [
            'id'      => $_POST['id'] ?? null,
            'id_ies'  => $_POST['id_ies'] ?? null,
            'id_plan' => $_POST['id_plan'] ?? null,
            'ciclo'   => $_POST['ciclo'] ?? 'mensual',
            'inicia'  => $_POST['inicia'] ?? '',
            'vence'   => $_POST['vence'] ?? '',
            'estado'  => $_POST['estado'] ?? 'activa',
        ];

        $errores = [];
        if (empty($d['id_ies']))  $errores[] = 'IES es obligatorio.';
        if (empty($d['id_plan'])) $errores[] = 'Plan es obligatorio.';
        if ($d['inicia'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['inicia'])) $errores[] = 'Fecha de inicio inválida (YYYY-MM-DD).';
        if ($d['vence'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['vence'])) $errores[] = 'Fecha de vencimiento inválida (YYYY-MM-DD).';

        if ($errores) {
            $_SESSION['form_errors'] = $errores;
            $redir = empty($d['id']) ? '/admin/suscripcion/nuevo' : '/admin/suscripcion/editar/' . $d['id'];
            header('Location: ' . BASE_URL . $redir);
            exit;
        }

        try {
            $ok = $this->model->guardar($d);
            // Mensaje por defecto
            $_SESSION['flash_success'] = $ok ? 'Suscripción guardada correctamente.' : 'No se pudo guardar.';

            // Si es creación (no edición), y se pidió generar API key
            if ($ok && empty($d['id']) && !empty($_POST['generar_api']) && $_POST['generar_api'] == '1') {
                try {
                    $akModel  = new ApiKey();
                    $userId   = $_SESSION['usuario']['id'] ?? null;
                    $resKey   = $akModel->createForIes((int)$d['id_ies'], 'default', $userId);
                    $_SESSION['flash_success'] .= ' Se generó una API Key: <code>' . htmlspecialchars($resKey['key']) . '</code>';
                } catch (\Throwable $e) {
                    $_SESSION['flash_success'] .= ' (No se pudo generar la API Key automáticamente)';
                }
            }
        } catch (\RuntimeException $e) {
            $_SESSION['form_errors'] = [$e->getMessage()];
            $redir = empty($d['id']) ? '/admin/suscripcion/nuevo' : '/admin/suscripcion/editar/' . $d['id'];
            header('Location: ' . BASE_URL . $redir);
            exit;
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'uq_subs_unique_active') !== false) {
                $_SESSION['form_errors'] = ['Ya existe una suscripción vigente (trial/activa) para este IES.'];
                $redir = empty($d['id']) ? '/admin/suscripcion/nuevo' : '/admin/suscripcion/editar/' . $d['id'];
                header('Location: ' . BASE_URL . $redir);
                exit;
            }
            $_SESSION['flash_success'] = 'Error de base de datos.';
        }

        header('Location: ' . BASE_URL . '/admin/suscripcion');
        exit;
    }

    public function suspender($id)
    {
        $this->model->cambiarEstado((int)$id, 'suspendida');
        $_SESSION['flash_success'] = 'Suscripción suspendida.';
        header('Location: ' . BASE_URL . '/admin/suscripcion');
        exit;
    }

    public function reactivar($id)
    {
        try {
            $this->model->cambiarEstado((int)$id, 'activa');
            $_SESSION['flash_success'] = 'Suscripción reactivada.';
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        header('Location: ' . BASE_URL . '/admin/suscripcion');
        exit;
    }

    public function cancelar($id)
    {
        $this->model->cambiarEstado((int)$id, 'cancelada');
        $_SESSION['flash_success'] = 'Suscripción cancelada.';
        header('Location: ' . BASE_URL . '/admin/suscripcion');
        exit;
    }
}
