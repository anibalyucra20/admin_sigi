<?php

namespace App\Controllers\Intranet;
use Core\Controller;

require_once __DIR__ . '/../../../app/models/Admin/Plan.php';
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
        $planes = $this->model->listar();
        $this->view('intranet/plan/index', [
            'planes' => $planes,
            'pageTitle' => 'Panel principal',
            'module'   => 'intranet'
        ]);
    }
}
