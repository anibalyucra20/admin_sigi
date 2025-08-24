<?php

namespace App\Models\Admin;

use Core\Model;
use PDO;

class Plan extends Model
{
    protected $table = 'planes';

    public function getPaginated(string $search, int $length, int $start, int $orderCol, string $orderDir): array
    {
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';
        $cols = [
            0 => 'id',
            1 => 'nombre',
            2 => 'monto',
            3 => 'limite_usuarios',
            4 => 'limite_reniec',
            5 => 'limite_escale',
            6 => 'limite_facturacion',
            7 => 'activo',
        ];
        $orderBy = $cols[$orderCol] ?? 'id';

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE nombre LIKE :q";
            $params[':q'] = "%{$search}%";
        }

        $sql = "SELECT id, nombre, monto, limite_usuarios, limite_reniec, limite_escale, limite_facturacion, activo
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

        $sqlTotal = "SELECT COUNT(*) FROM {$this->table}";
        $total = (int) self::$db->query($sqlTotal)->fetchColumn();

        $sqlFiltered = "SELECT COUNT(*) FROM {$this->table} $where";
        $st2 = self::$db->prepare($sqlFiltered);
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
            ':nombre'             => trim($d['nombre']),
            ':monto'              => (float)$d['monto'],
            ':limite_usuarios'    => (int)$d['limite_usuarios'],
            ':limite_reniec'      => (int)$d['limite_reniec'],
            ':limite_escale'      => (int)$d['limite_escale'],
            ':limite_facturacion' => (int)$d['limite_facturacion'],
            ':activo'             => (int)($d['activo'] ?? 1),
        ];

        if (!empty($d['id'])) {
            $params[':id'] = (int)$d['id'];
            $sql = "UPDATE {$this->table}
                       SET nombre=:nombre, monto=:monto, limite_usuarios=:limite_usuarios,
                           limite_reniec=:limite_reniec, limite_escale=:limite_escale,
                           limite_facturacion=:limite_facturacion, activo=:activo
                     WHERE id=:id";
        } else {
            $sql = "INSERT INTO {$this->table}
                       (nombre,monto,limite_usuarios,limite_reniec,limite_escale,limite_facturacion,activo)
                    VALUES (:nombre,:monto,:limite_usuarios,:limite_reniec,:limite_escale,:limite_facturacion,:activo)";
        }

        $st = self::$db->prepare($sql);
        return $st->execute($params);
    }

    public function activar(int $id, int $estado): bool
    {
        $st = self::$db->prepare("UPDATE {$this->table} SET activo=? WHERE id=?");
        return $st->execute([$estado, $id]);
    }

    public function eliminar(int $id): bool
    {
        // Sugerencia: preferir desactivar por FKs (subscriptions)
        try {
            $st = self::$db->prepare("DELETE FROM {$this->table} WHERE id=?");
            return $st->execute([$id]);
        } catch (\PDOException $e) {
            // Si hay FK, desactivar en lugar de borrar
            $st = self::$db->prepare("UPDATE {$this->table} SET activo=0 WHERE id=?");
            return $st->execute([$id]);
        }
    }
}
