<?php
namespace App\Controllers\Admin;

use Core\Controller;

require_once __DIR__ . '/../../models/Admin/ApiKey.php';
use App\Models\Admin\ApiKey;

class ApiKeysController extends Controller
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ApiKey();
    }

    public function index()
    {
        $this->view('admin/apiKeys/index', [
            'ies'       => $this->model->allIes(),
            'module'    => 'admin',
            'pageTitle' => 'API Keys'
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
                'id_ies' => $_GET['filter_ies'] ?? null,
                'activo' => $_GET['filter_activo'] ?? '',
                'q'      => $_GET['search']['value'] ?? '',
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
        $this->view('admin/apiKeys/nuevo', [
            'ies'       => $this->model->allIes(),
            'module'    => 'admin',
            'pageTitle' => 'Nueva API Key',
            'errores'   => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function guardar()
    {
        $idIes  = (int)($_POST['id_ies'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? 'default');
        $errores = [];
        if ($idIes <= 0) $errores[] = 'IES es obligatorio.';

        if ($errores) {
            $_SESSION['form_errors'] = $errores;
            header('Location: ' . BASE_URL . '/admin/apiKeys/nuevo');
            exit;
        }

        $userId = $_SESSION['usuario']['id'] ?? null;
        try {
            $res = $this->model->createForIes($idIes, $nombre, $userId);
            $_SESSION['flash_success'] = 'API Key creada. Cópiala ahora: <code>'.htmlspecialchars($res['key']).'</code>';
        } catch (\Throwable $e) {
            $_SESSION['flash_success'] = 'No se pudo crear la API key.';
        }
        header('Location: ' . BASE_URL . '/admin/apiKeys');
        exit;
    }

    public function activar($id)
    {
        $this->model->activate((int)$id, true);
        $_SESSION['flash_success'] = 'API Key activada.';
        header('Location: ' . BASE_URL . '/admin/apiKeys');
        exit;
    }

    public function desactivar($id)
    {
        $this->model->activate((int)$id, false);
        $_SESSION['flash_success'] = 'API Key desactivada.';
        header('Location: ' . BASE_URL . '/admin/apiKeys');
        exit;
    }

    public function rotar($id)
    {
        try {
            $apidata = $this->model->find((int)$id);
            $idIes = $apidata['id_ies'] ?? 0;
            $res = $this->model->rotate((int)$id, (int)$idIes);
            $_SESSION['flash_success'] = 'API Key rotada. Nueva clave: <code>'.htmlspecialchars($res['key']).'</code>';
        } catch (\Throwable $e) {
            $_SESSION['flash_success'] = 'No se pudo rotar la API key.';
        }
        header('Location: ' . BASE_URL . '/admin/apiKeys');
        exit;
    }
}
