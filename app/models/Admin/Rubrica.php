<?php

namespace App\Models\Admin;

use Core\Model;
use PDO;

class Rubrica extends Model
{
    protected $table = 'rubricas_evaluacion';

    public function getPaginated(string $search, int $length, int $start, int $orderCol, string $orderDir): array
    {
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';
        $cols = [
            0 => 'id',
            1 => 'nombre',
            2 => 'tipo_tecnica',
            3 => 'estado',
            4 => 'created_at'
        ];
        $orderBy = $cols[$orderCol] ?? 'id';

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE (nombre LIKE :q OR tipo_tecnica LIKE :q OR descripcion LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        $sql = "SELECT id, nombre, tipo_tecnica, descripcion, estado, created_at, updated_at
                  FROM {$this->table}
                  $where
                 ORDER BY $orderBy $orderDir
                 LIMIT :limit OFFSET :offset";
                 
        $st = self::$db->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
        $st->bindValue(':limit', $length, PDO::PARAM_INT);
        $st->bindValue(':offset', $start, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);

        $total = (int) self::$db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
        
        $st2 = self::$db->prepare("SELECT COUNT(*) FROM {$this->table} $where");
        foreach ($params as $k => $v) $st2->bindValue($k, $v, PDO::PARAM_STR);
        $st2->execute();
        $filtered = (int) $st2->fetchColumn();

        return ['data' => $data, 'total' => $total, 'filtered' => $filtered];
    }

    public function find(int $id): ?array
    {
        $st = self::$db->prepare("SELECT * FROM {$this->table} WHERE id=?");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function guardar(array $d): bool
    {
        $params = [
            ':nombre'         => trim($d['nombre']),
            ':tipo_tecnica'   => trim($d['tipo_tecnica']),
            ':descripcion'    => trim($d['descripcion'] ?? ''),
            ':contenido_json' => $d['contenido_json'] // El JSON ya validado en el controlador
        ];

        if (!empty($d['id'])) {
            $params[':id'] = (int)$d['id'];
            $sql = "UPDATE {$this->table}
                       SET nombre=:nombre, tipo_tecnica=:tipo_tecnica, descripcion=:descripcion,
                           contenido_json=:contenido_json, updated_at=NOW()
                     WHERE id=:id";
        } else {
            $sql = "INSERT INTO {$this->table}
                       (nombre, tipo_tecnica, descripcion, contenido_json, estado, created_at, updated_at)
                    VALUES (:nombre, :tipo_tecnica, :descripcion, :contenido_json, 1, NOW(), NOW())";
        }

        $st = self::$db->prepare($sql);
        return $st->execute($params);
    }

    public function suspender(int $id): bool
    {
        // Soft delete/suspensión
        $st = self::$db->prepare("UPDATE {$this->table} SET estado=0, updated_at=NOW() WHERE id=:id");
        return $st->execute([':id' => $id]);
    }

    public function reactivar(int $id): bool
    {
        $st = self::$db->prepare("UPDATE {$this->table} SET estado=1, updated_at=NOW() WHERE id=:id");
        return $st->execute([':id' => $id]);
    }
}