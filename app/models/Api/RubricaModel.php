<?php

namespace App\Models\Api;

use Core\Model;
use PDO;

class RubricaModel extends Model
{
    /**
     * Retorna todas las rúbricas maestras activas.
     * Solo extraemos los metadatos esenciales para el listado (aligera la red).
     */
    public function obtenerListadoActivas(): array
    {
        $db = static::getDB();
        $sql = "SELECT id, nombre, tipo_tecnica, descripcion, created_at 
                FROM rubricas_evaluacion 
                WHERE estado = 1 
                ORDER BY nombre ASC";
        
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene una rúbrica específica incluyendo su JSON.
     */
    public function obtenerPorId(int $id): ?array
    {
        $db = static::getDB();
        $sql = "SELECT id, nombre, tipo_tecnica, descripcion, contenido_json, created_at 
                FROM rubricas_evaluacion 
                WHERE id = :id AND estado = 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ?: null;
    }
}