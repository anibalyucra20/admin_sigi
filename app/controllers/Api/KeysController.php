<?php
namespace App\Controllers\Api;

class KeysController extends BaseApiController
{
    public function store(){
        $idIes = (int)($_POST['id_ies'] ?? $this->tenantId);
        $name  = $_POST['nombre'] ?? 'default';
        $plain = bin2hex(random_bytes(24));
        $hash  = password_hash($plain, PASSWORD_BCRYPT);
        $this->db->prepare("INSERT INTO api_keys (id_ies, nombre, key_hash, creado_por) VALUES (?,?,?,NULL)")
                 ->execute([$idIes,$name,$hash]);
        $this->json(['api_key'=>$plain], 201); // mostrar sólo una vez
    }
}
