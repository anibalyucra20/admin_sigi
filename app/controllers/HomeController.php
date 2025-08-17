<?php
namespace App\Controllers;

use Core\Controller;

class HomeController extends Controller
{
    
    public function index()
    {
        // Por ejemplo muestra una vista simple
        $this->view('home/index');
    }
    
}
