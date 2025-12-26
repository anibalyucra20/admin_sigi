<?php

namespace App\Controllers\Api;

require_once __DIR__ . '/BaseApiController.php';

class KeysController extends BaseApiController
{
    public function store()
    {
        // (opcional) aquí podrías protegerlo con sesión/admin,
        // pero por ahora lo dejamos tal cual.
        $idIes = (int)($_POST['id_ies'] ?? $this->tenantId);
        $name  = trim((string)($_POST['nombre'] ?? 'default'));

        if ($idIes <= 0) {
            return $this->json(['ok' => false, 'error' => ['code' => 'VALIDATION', 'message' => 'id_ies inválido']], 422);
        }

        $plain = bin2hex(random_bytes(24));          // secreto
        $raw   = "SIGI-{$idIes}-{$plain}";           // formato completo que tu API exige
        $hash  = password_hash($raw, PASSWORD_BCRYPT);

        $this->db->prepare("INSERT INTO api_keys (id_ies, nombre, key_hash, creado_por) VALUES (?,?,?,NULL)")
            ->execute([$idIes, $name, $hash]);

        // mostrar solo una vez
        return $this->json(['ok' => true, 'api_key' => $raw], 201);
    }
}
