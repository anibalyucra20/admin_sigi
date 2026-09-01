<?php

namespace App\Controllers\Admin;

use Core\Controller;

require_once __DIR__ . '/../../models/Admin/Rubrica.php';

use App\Models\Admin\Rubrica;

class RubricasController extends Controller
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Rubrica();
    }

    public function index()
    {
        $this->view('admin/rubricas/index', [
            'module'    => 'admin',
            'pageTitle' => 'Rúbricas Institucionales'
        ]);
    }

    public function data()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $draw     = (int)($_GET['draw']    ?? 1);
            $start    = (int)($_GET['start']   ?? 0);
            $length   = (int)($_GET['length']  ?? 10);
            $orderCol = (int)($_GET['order'][0]['column'] ?? 0);
            $orderDir = (string)($_GET['order'][0]['dir'] ?? 'desc'); // Mejor DESC por defecto para rúbricas nuevas
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
        $this->view('admin/rubricas/nuevo', [
            'rubrica'   => [],
            'module'    => 'admin',
            'pageTitle' => 'Nueva Rúbrica Institucional',
            'errores'   => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function guardar()
    {
        $d = [
            'id'             => $_POST['id'] ?? null,
            'nombre'         => trim($_POST['nombre'] ?? ''),
            'tipo_tecnica'   => trim($_POST['tipo_tecnica'] ?? ''),
            'descripcion'    => trim($_POST['descripcion'] ?? ''),
            'contenido_json' => trim($_POST['contenido_json'] ?? '')
        ];

        $errores = [];
        if ($d['nombre'] === '')       $errores[] = 'El nombre de la rúbrica es obligatorio.';
        if ($d['tipo_tecnica'] === '') $errores[] = 'La técnica de evaluación es obligatoria.';

        // Validación proactiva del JSON antes de llegar al motor MySQL
        $jsonDecoded = json_decode($d['contenido_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($jsonDecoded['criterios']) || empty($jsonDecoded['criterios'])) {
            $errores[] = 'La estructura de la rúbrica (criterios y niveles) está vacía o es inválida.';
        }

        if ($errores) {
            $_SESSION['form_errors'] = $errores;
            $redir = empty($d['id']) ? '/admin/rubricas/nuevo' : '/admin/rubricas/editar/' . $d['id'];
            header('Location: ' . BASE_URL . $redir);
            exit;
        }

        try {
            $ok = $this->model->guardar($d);
            $_SESSION['flash_success'] = $ok ? 'Rúbrica guardada correctamente.' : 'No se pudo guardar la rúbrica.';
        } catch (\PDOException $e) {
            $_SESSION['flash_success'] = 'Error de base de datos: ' . $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/admin/rubricas');
        exit;
    }

    public function editar($id)
    {
        $rubrica = $this->model->find((int)$id);

        if (!$rubrica) {
            $_SESSION['flash_success'] = 'La rúbrica maestra no existe o fue eliminada.';
            header('Location: ' . BASE_URL . '/admin/rubricas');
            exit;
        }

        $this->view('admin/rubricas/editar', [
            'rubrica'   => $rubrica,
            'module'    => 'admin',
            'pageTitle' => 'Editar Rúbrica Máster',
            'errores'   => $_SESSION['form_errors'] ?? []
        ]);

        unset($_SESSION['form_errors']);
    }

    public function suspender($id)
    {
        $this->model->suspender((int)$id);
        $_SESSION['flash_success'] = 'Rúbrica inhabilitada.';
        header('Location: ' . BASE_URL . '/admin/rubricas');
        exit;
    }

    public function reactivar($id)
    {
        $this->model->reactivar((int)$id);
        $_SESSION['flash_success'] = 'Rúbrica reactivada.';
        header('Location: ' . BASE_URL . '/admin/rubricas');
        exit;
    }
}
