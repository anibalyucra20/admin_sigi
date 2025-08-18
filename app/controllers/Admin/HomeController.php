<?php

namespace App\Controllers\Admin;

use Core\Controller;

use Core\Auth;

class HomeController extends Controller
{
    protected $datosSistema;
    public function __construct()
    {
        parent::__construct();
    }
    public function index()
    {
        $user = Auth::user();              // ya validado por middleware
        $this->view('admin/index', [
            'pageTitle' => 'Panel principal',
            'module'   => 'admin'
        ]);
    }
}
