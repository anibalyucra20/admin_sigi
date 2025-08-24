<?php

namespace App\Controllers\Admin;

use Core\Controller;

require_once __DIR__ . '/../../models/Admin/Invoice.php';

use App\Models\Admin\Invoice;

class RecibosController extends Controller
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Invoice();
    }

    public function index()
    {
        $this->view('admin/recibos/index', [
            'ies'       => $this->model->allIes(),
            'module'    => 'admin',
            'pageTitle' => 'Recibos'
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
            'id_ies'  => $_GET['filter_ies']  ?? null,
            'estado'  => $_GET['filter_estado'] ?? null,
            'pasarela' => $_GET['filter_pasarela'] ?? null,
            'periodo' => $_GET['filter_periodo'] ?? null,
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
        $this->view('admin/recibos/nuevo', [
            'iv'       => [],
            'ies'      => $this->model->allIes(),
            'module'   => 'admin',
            'pageTitle' => 'Nuevo Recibo',
            'errores'  => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function editar($id)
    {
        $iv = $this->model->find((int)$id);
        if (!$iv) {
            $_SESSION['flash_success'] = 'El recibo no existe.';
            header('Location: ' . BASE_URL . '/admin/recibos');
            exit;
        }
        $this->view('admin/recibos/editar', [
            'iv' => $iv,
            'ies' => $this->model->allIes(),
            'module' => 'admin',
            'pageTitle' => 'Editar Recibo',
            'errores' => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function guardar()
    {
        $d = [
            'id'             => $_POST['id'] ?? null,
            'id_ies'         => $_POST['id_ies'] ?? null,
            'periodo_aaaamm' => $_POST['periodo_aaaamm'] ?? '',
            'total'          => $_POST['total'] ?? 0,
            'moneda'         => $_POST['moneda'] ?? 'PEN',
            'estado'         => $_POST['estado'] ?? 'pendiente',
            'pasarela'       => $_POST['pasarela'] ?? null,
            'external_id'    => $_POST['external_id'] ?? null,
            'due_at'         => $_POST['due_at'] ?? null,
        ];

        $err = [];
        if (empty($d['id_ies'])) $err[] = 'IES es obligatorio.';
        if (!preg_match('/^\d{6}$/', $d['periodo_aaaamm'])) $err[] = 'Periodo debe ser AAAAMM.';
        if ((float)$d['total'] <= 0) $err[] = 'Total debe ser mayor a 0.';
        if ($err) {
            $_SESSION['form_errors'] = $err;
            $redir = empty($d['id']) ? '/admin/recibos/nuevo' : '/admin/recibos/editar/' . $d['id'];
            header('Location: ' . BASE_URL . $redir);
            exit;
        }

        $ok = $this->model->guardar($d);
        $_SESSION['flash_success'] = $ok ? 'Recibo guardado.' : 'No se pudo guardar.';
        header('Location: ' . BASE_URL . '/admin/recibos');
        exit;
    }

    public function anular($id)
    {
        $this->model->anular((int)$id);
        $_SESSION['flash_success'] = 'Recibo anulado.';
        header('Location: ' . BASE_URL . '/admin/recibos');
        exit;
    }

    public function marcarPagado($id)
    {
        $this->model->marcarPagada((int)$id);
        $_SESSION['flash_success'] = 'Recibo marcado como pagado.';
        header('Location: ' . BASE_URL . '/admin/recibos');
        exit;
    }
    public function generarPendientes()
    {
        $res = $this->model->generarPendientes(); // hasta hoy
        $det = [];
        foreach ($res['por_ies'] as $idIes => $n) {
            if ($n > 0) $det[] = "IES {$idIes}: {$n}";
        }
        $_SESSION['flash_success'] = "Generación completada. Suscripciones procesadas: {$res['procesadas']}. Recibos creados: {$res['creadas']}" . (empty($det) ? '' : (' — ' . implode(', ', $det)));
        header('Location: ' . BASE_URL . '/admin/recibos');
        exit;
    }
}
