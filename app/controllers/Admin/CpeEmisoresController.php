<?php

namespace App\Controllers\Admin;

use Core\Controller;

require_once __DIR__ . '/../../models/Admin/CpeEmisor.php';
require_once __DIR__ . '/../../models/Admin/Ies.php';

use App\Models\Admin\CpeEmisor;
use App\Models\Admin\Ies;

class CpeEmisoresController extends Controller
{
    protected $model;
    protected $objIes;

    public function __construct()
    {
        parent::__construct();
        $this->model = new CpeEmisor();
        $this->objIes = new Ies();
    }

    public function index()
    {
        $this->view('admin/cpeEmisores/index', [
            'module'    => 'admin',
            'pageTitle' => 'Emisores CPE',
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
            'ruc'          => $_GET['filter_ruc'] ?? null,
            'razon_social' => $_GET['filter_razon'] ?? null,
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
        $this->view('admin/cpeEmisores/nuevo', [
            'data'      => [],
            'module'    => 'admin',
            'pageTitle' => 'Nuevo Emisor CPE',
            'errores'   => $_SESSION['form_errors'] ?? [],
            'iesList'   => $this->objIes->getIes(),
        ]);
        unset($_SESSION['form_errors']);
    }

    public function editar($id)
    {
        $row = $this->model->find((int)$id);
        if (!$row) {
            $_SESSION['flash_success'] = 'El emisor no existe.';
            header('Location: ' . BASE_URL . '/admin/cpe-emisores');
            exit;
        }

        $this->view('admin/cpeEmisores/editar', [
            'data'      => $row,
            'module'    => 'admin',
            'pageTitle' => 'Editar Emisor CPE',
            'errores'   => $_SESSION['form_errors'] ?? [],
            'iesList'   => $this->objIes->getIes(),
        ]);
        unset($_SESSION['form_errors']);
    }

    public function guardar()
    {
        $d = [
            'id'              => $_POST['id'] ?? null,
            'id_ies'           => (int)($_POST['id_ies'] ?? 0),
            'ruc'              => trim((string)($_POST['ruc'] ?? '')),
            'razon_social'     => trim((string)($_POST['razon_social'] ?? '')),
            'nombre_comercial' => trim((string)($_POST['nombre_comercial'] ?? '')),
            'ubigeo'           => trim((string)($_POST['ubigeo'] ?? '')),
            'direccion'        => trim((string)($_POST['direccion'] ?? '')),
            'departamento'     => trim((string)($_POST['departamento'] ?? '')),
            'provincia'        => trim((string)($_POST['provincia'] ?? '')),
            'distrito'         => trim((string)($_POST['distrito'] ?? '')),
            'email'            => trim((string)($_POST['email'] ?? '')),
            'telefono'         => trim((string)($_POST['telefono'] ?? '')),
        ];

        $err = [];

        if ($d['id_ies'] <= 0) $err[] = 'IES es obligatorio.';
        if ($d['ruc'] === '') $err[] = 'RUC es obligatorio.';
        if ($d['razon_social'] === '') $err[] = 'Razón social es obligatoria.';

        // Validaciones ligeras
        if ($d['ruc'] !== '' && (!ctype_digit($d['ruc']) || strlen($d['ruc']) !== 11)) {
            $err[] = 'RUC inválido (debe tener 11 dígitos).';
        }
        if ($d['ubigeo'] !== '' && (strlen($d['ubigeo']) < 4 || !ctype_digit($d['ubigeo']))) {
            $err[] = 'Ubigeo inválido (4 dígitos).';
        }
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $err[] = 'Email inválido.';
        }

        if ($err) {
            $redir = empty($d['id']) ? 'admin/cpeEmisores/nuevo' : 'admin/cpeEmisores/editar';
            $this->view($redir, [
                'data'      => $d,
                'module'    => 'admin',
                'pageTitle' => 'Nuevo Emisor CPE',
                'errores'   => $err ?? [],
                'iesList'   => $this->objIes->getIes(),
            ]);
            exit;
        }

        try {
            // Nota: por UNIQUE (id_ies) permitimos “upsert” lógico: si ya existe para esa IES y viene sin id, actualiza.
            $ok = $this->model->guardar($d);

            $_SESSION['flash_success'] = $ok ? 'Emisor guardado.' : 'No se pudo guardar.';
            header('Location: ' . BASE_URL . '/admin/cpeEmisores');
            exit;
        } catch (\Throwable $e) {

            $errores = [$e->getMessage()];
            $dupEditUrl = null;

            // ✅ Detectar UNIQUE/duplicate
            $isDup = ((int)$e->getCode() === 1062) || stripos($e->getMessage(), 'Ya existe un emisor para esta IES') !== false;

            if ($isDup && !empty($d['id_ies'])) {
                // Si existe, armar URL para editar
                $exist = $this->model->findByIes((int)$d['id_ies']);
                if ($exist && !empty($exist['id'])) {
                    $dupEditUrl = BASE_URL . '/admin/cpeEmisores/editar/' . (int)$exist['id'];
                }

                // Si no vino el mensaje “bonito”, forzarlo
                $errores = ['Ya existe un emisor para esta IES.'];
            }

            $redir = empty($d['id']) ? 'admin/cpeEmisores/nuevo' : 'admin/cpeEmisores/editar';

            $this->view($redir, [
                'data'       => $d,
                'module'     => 'admin',
                'pageTitle'  => 'Nuevo Emisor CPE',
                'errores'    => $errores,
                'iesList'    => $this->objIes->getIes(), // usa el mismo que arriba
                'dupEditUrl' => $dupEditUrl,            // ✅ para mostrar botón/acción
            ]);
            exit;
        }
    }

    public function eliminar($id)
    {
        try {
            $ok = $this->model->deleteById((int)$id);
            $_SESSION['flash_success'] = $ok ? 'Emisor eliminado.' : 'No se pudo eliminar.';
        } catch (\Throwable $e) {
            $_SESSION['flash_success'] = $e->getMessage();
        }
        header('Location: ' . BASE_URL . '/admin/cpeEmisores');
        exit;
    }
}
