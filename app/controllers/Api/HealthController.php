<?php

namespace App\Controllers\Api;

require_once __DIR__ . '/BaseApiController.php';

class HealthController extends BaseApiController
{
    // GET /api/health  (sin auth)
    public function index()
    {
        $this->json(['ok' => true, 'time' => date('c')], 200);
    }
}
