<?php

namespace App\Models\Admin;

use Core\Model;
use PDO;

class Plan extends Model
{
    protected $table = 'planes';

    public function listar()
    {
        $stmt = self::$db->prepare("SELECT * FROM planes");
        $stmt->execute([]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function find($id)
    {
        $stmt = self::$db->prepare("SELECT * FROM sigi_planes_estudio WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function guardar($data)
    {
        if (!empty($data['id'])) {
            $sql = "UPDATE sigi_planes_estudio 
                    SET id_programa_estudios=:id_programa_estudios, nombre=:nombre, resolucion=:resolucion, perfil_egresado=:perfil_egresado 
                    WHERE id=:id";
            $params = [
                ':id_programa_estudios' => $data['id_programa_estudios'],
                ':nombre' => $data['nombre'],
                ':resolucion' => $data['resolucion'],
                ':perfil_egresado' => $data['perfil_egresado'],
                ':id' => $data['id'],
            ];
        } else {
            $sql = "INSERT INTO sigi_planes_estudio (id_programa_estudios, nombre, resolucion, perfil_egresado) 
                    VALUES (:id_programa_estudios, :nombre, :resolucion, :perfil_egresado)";
            $params = [
                ':id_programa_estudios' => $data['id_programa_estudios'],
                ':nombre' => $data['nombre'],
                ':resolucion' => $data['resolucion'],
                ':perfil_egresado' => $data['perfil_egresado'],
            ];
        }
        $stmt = self::$db->prepare($sql);
        return $stmt->execute($params);
    }
    public function getPlanes()
    {
        $stmt = self::$db->prepare("SELECT DISTINCT nombre FROM sigi_planes_estudio ORDER BY nombre");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getPlanesByPrograma($id_programa)
    {
        $stmt = self::$db->prepare("SELECT id, nombre FROM sigi_planes_estudio WHERE id_programa_estudios = ? ORDER BY nombre");
        $stmt->execute([$id_programa]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getPlanByProgramaAndPlanName($id_programa, $nombre)
    {
        $stmt = self::$db->prepare("SELECT id FROM sigi_planes_estudio WHERE id_programa_estudios = ? AND nombre = ? ORDER BY nombre");
        $stmt->execute([$id_programa, $nombre]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
