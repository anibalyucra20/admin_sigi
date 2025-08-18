<?php
namespace App\Controllers\Api;

require_once __DIR__ . '/BaseApiController.php';

class HealthController extends BaseApiController
{
    public function index(){
        $this->json(['ok'=>true,'ts'=>date('c')]);
    }
}
