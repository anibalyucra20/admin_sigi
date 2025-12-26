<?php

namespace App\Controllers\Admin;

use Core\Controller;

require_once __DIR__ . '/../../models/Admin/CpeSerie.php';
require_once __DIR__ . '/../../models/Admin/Ies.php';

use App\Models\Admin\CpeSerie;
use App\Models\Admin\Ies;

class CpeSeriesController extends Controller
{
    protected $model;
    protected $objIes;

    public function __construct()
    {
        parent::__construct();
        $this->model = new CpeSerie();
        $this->objIes = new Ies();
    }

    public function index()
    {
        $this->view('admin/cpeSeries/index', [
            'module'    => 'admin',
            'iesList'   => $this->objIes->getIes(),
            'pageTitle' => 'Series CPE'
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
            'id_ies'   => $_GET['filter_ies'] ?? null,
            'tipo_doc' => $_GET['filter_tipo'] ?? null,
            'activo'   => $_GET['filter_activo'] ?? null,
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
        $this->view('admin/cpeSeries/nuevo', [
            'data'     => [],
            'module'   => 'admin',
            'iesList'   => $this->objIes->getIes(),
            'pageTitle' => 'Nueva Serie CPE',
            'errores'  => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function editar($id)
    {
        $row = $this->model->find((int)$id);
        if (!$row) {
            $_SESSION['flash_success'] = 'La serie no existe.';
            header('Location: ' . BASE_URL . '/admin/cpeSeries');
            exit;
        }

        $this->view('admin/cpeSeries/editar', [
            'data'     => $row,
            'module'   => 'admin',
            'iesList'   => $this->objIes->getIes(),
            'pageTitle' => 'Editar Serie CPE',
            'errores'  => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function guardar()
    {
        $d = [
            'id'                => $_POST['id'] ?? null,
            'id_ies'            => (int)($_POST['id_ies'] ?? 0),
            'tipo_doc'          => trim((string)($_POST['tipo_doc'] ?? '')),
            'serie'             => trim((string)($_POST['serie'] ?? '')),
            'correlativo_actual' => (int)($_POST['correlativo_actual'] ?? 0),
            'activo'            => isset($_POST['activo']) ? 1 : 0,
        ];

        $err = [];
        if ($d['id_ies'] <= 0) $err[] = 'IES es obligatorio.';
        if (!preg_match('/^\d{2}$/', $d['tipo_doc'])) $err[] = 'Tipo doc inválido (ej: 01).';
        if ($d['serie'] === '' || strlen($d['serie']) > 8) $err[] = 'Serie inválida (ej: F001).';
        if ($d['correlativo_actual'] < 0) $err[] = 'Correlativo inválido.';
        if ($err) {
            $_SESSION['form_errors'] = $err;
            $redir = empty($d['id']) ? 'admin/cpeSeries/nuevo' : 'admin/cpeSeries/editar';
            $this->view($redir, [
                'data'      => $d,
                'module'    => 'admin',
                'pageTitle' => 'Series CPE',
                'errores'   => $err ?? [],
                'iesList'   => $this->objIes->getIes(),
            ]);
            exit;
        }

        try {
            $ok = $this->model->guardar($d);
            $_SESSION['flash_success'] = $ok ? 'Serie guardada.' : 'No se pudo guardar.';
        } catch (\Throwable $e) {
            $redir = empty($d['id']) ? 'admin/cpeSeries/nuevo' : 'admin/cpeSeries/editar';
            $this->view($redir, [
                'data'      => $d,
                'module'    => 'admin',
                'pageTitle' => 'Series CPE',
                'errores'   => [$e->getMessage()] ?? [],
                'iesList'   => $this->objIes->getIes(),
            ]);
            exit;
        }
        header('Location: ' . BASE_URL . '/admin/cpeSeries');
        exit;
    }
}
