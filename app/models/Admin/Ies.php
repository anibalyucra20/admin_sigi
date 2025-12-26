<?php
namespace App\Models\Admin;

use Core\Model;
use PDO;

class Ies extends Model
{
    protected $table = 'ies';

    public function getIes(): array
    {
        $db = static::getDB();
        $st = $db->query("SELECT id, nombre_ies FROM ies ORDER BY nombre_ies ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public function getPaginated(string $search, int $length, int $start, int $orderCol, string $orderDir): array
    {
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';
        $cols = [
            0 => 'id',
            1 => 'ruc',
            2 => 'nombre_ies',
            3 => 'dominio',
            4 => 'telefono',
            5 => 'direccion',
            6 => 'estado',
        ];
        $orderBy = $cols[$orderCol] ?? 'id';

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE (ruc LIKE :q OR nombre_ies LIKE :q OR dominio LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        $sql = "SELECT id, ruc, nombre_ies, direccion, telefono, llave, dominio, estado, suspendido_at, created_at, updated_at
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
            ':ruc'        => trim($d['ruc']),
            ':nombre'     => trim($d['nombre_ies']),
            ':direccion'  => trim($d['direccion'] ?? ''),
            ':telefono'   => trim($d['telefono'] ?? ''),
            ':llave'      => trim($d['llave'] ?? ''),
            ':dominio'    => trim($d['dominio']),
        ];

        if (!empty($d['id'])) {
            $params[':id'] = (int)$d['id'];
            $sql = "UPDATE {$this->table}
                       SET ruc=:ruc, nombre_ies=:nombre, direccion=:direccion,
                           telefono=:telefono, llave=:llave, dominio=:dominio, updated_at=NOW()
                     WHERE id=:id";
        } else {
            $sql = "INSERT INTO {$this->table}
                       (ruc, nombre_ies, direccion, telefono, llave, dominio, estado, created_at, updated_at)
                    VALUES (:ruc, :nombre, :direccion, :telefono, :llave, :dominio, 'activa', NOW(), NOW())";
        }

        $st = self::$db->prepare($sql);
        return $st->execute($params);
    }

    public function suspender(int $id, ?string $motivo = null): bool
    {
        $st = self::$db->prepare("UPDATE {$this->table}
                                  SET estado='suspendida', suspendido_at=NOW(), suspendido_motivo=:m, updated_at=NOW()
                                  WHERE id=:id");
        return $st->execute([':m' => $motivo, ':id' => $id]);
    }

    public function reactivar(int $id): bool
    {
        $st = self::$db->prepare("UPDATE {$this->table}
                                  SET estado='activa', suspendido_at=NULL, suspendido_motivo=NULL, updated_at=NOW()
                                  WHERE id=:id");
        return $st->execute([':id' => $id]);
    }
}
