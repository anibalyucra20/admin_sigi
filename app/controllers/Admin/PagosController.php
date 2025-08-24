<?php
namespace App\Controllers\Admin;

use Core\Controller;

require_once __DIR__ . '/../../models/Admin/Payment.php';
require_once __DIR__ . '/../../models/Admin/Invoice.php';
use App\Models\Admin\Payment;
use App\Models\Admin\Invoice;

class PagosController extends Controller
{
    protected $model;
    protected $invModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Payment();
        $this->invModel = new Invoice();
    }

    public function index()
    {
        $this->view('admin/pagos/index', [
            'ies'       => $this->invModel->allIes(),
            'invoices'  => $this->model->allInvoices(),
            'module'    => 'admin',
            'pageTitle' => 'Pagos'
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
            'id_invoice' => $_GET['filter_invoice'] ?? null,
            'estado'     => $_GET['filter_estado'] ?? null,
            'pasarela'   => $_GET['filter_pasarela'] ?? null,
            'id_ies'     => $_GET['filter_ies'] ?? null,
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
        $this->view('admin/pagos/nuevo', [
            'pago'     => [],
            'invoices' => $this->model->allInvoicesNoPagadas(),
            'ies'      => $this->invModel->allIes(),
            'module'   => 'admin',
            'pageTitle'=> 'Nuevo Pago',
            'errores'  => $_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function editar($id)
    {
        $pago = $this->model->find((int)$id);
        if (!$pago) {
            $_SESSION['flash_success']='El pago no existe.';
            header('Location: ' . BASE_URL . '/admin/pagos'); exit;
        }
        $this->view('admin/pagos/editar', [
            'pago'=>$pago,
            'invoices'=>$this->model->allInvoices(),
            'ies'=>$this->invModel->allIes(),
            'module'=>'admin',
            'pageTitle'=>'Editar Pago',
            'errores'=>$_SESSION['form_errors'] ?? []
        ]);
        unset($_SESSION['form_errors']);
    }

    public function guardar()
    {
        $d = [
            'id'         => $_POST['id'] ?? null,
            'id_invoice' => $_POST['id_invoice'] ?? null,
            'monto'      => $_POST['monto'] ?? 0,
            'moneda'     => $_POST['moneda'] ?? 'PEN',
            'pasarela'   => $_POST['pasarela'] ?? '',
            'external_id'=> $_POST['external_id'] ?? null,
            'estado'     => $_POST['estado'] ?? 'pendiente',
            'paid_at'    => $_POST['paid_at'] ?? null,
        ];

        $err = [];
        if (empty($d['id_invoice'])) $err[]='Recibo es obligatorio.';
        if ((float)$d['monto']<=0) $err[]='Monto debe ser mayor a 0.';
        if ($err){
            $_SESSION['form_errors']=$err;
            $redir = empty($d['id']) ? '/admin/pagos/nuevo' : '/admin/pagos/editar/'.$d['id'];
            header('Location: '.BASE_URL.$redir); exit;
        }

        $ok = $this->model->guardar($d);
        $_SESSION['flash_success'] = $ok ? 'Pago guardado.' : 'No se pudo guardar.';
        header('Location: ' . BASE_URL . '/admin/pagos'); exit;
    }

    public function marcar($id, $estado)
    {
        $ok = $this->model->cambiarEstado((int)$id, $estado);
        $_SESSION['flash_success'] = $ok ? 'Estado de pago actualizado.' : 'No se pudo actualizar.';
        header('Location: ' . BASE_URL . '/admin/pagos'); exit;
    }

    // Ajax: listar recibos por IES (para el formulario)
    public function recibosPorIes($idIes)
    {
        header('Content-Type: application/json');
        require_once __DIR__ . '/../../models/Admin/Payment.php';
        $m = new Payment();
        echo json_encode($m->invoicesByIes((int)$idIes), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
