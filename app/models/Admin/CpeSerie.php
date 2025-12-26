<?php

namespace App\Models\Admin;

use Core\Model;
use PDO;
use PDOException;

class CpeSerie extends Model
{
    protected string $table = 'cpe_series';

    public function find(int $id): ?array
    {
        $st = static::getDB()->prepare("SELECT * FROM {$this->table} WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }


    public function guardar(array $d): bool
    {
        $db = static::getDB();

        try {
            if (!empty($d['id'])) {
                $sql = "UPDATE {$this->table}
                       SET id_ies=?, tipo_doc=?, serie=?, correlativo_actual=?, activo=?, updated_at=NOW()
                     WHERE id=?
                     LIMIT 1";
                $st = $db->prepare($sql);

                return $st->execute([
                    (int)$d['id_ies'],
                    (string)$d['tipo_doc'],
                    (string)$d['serie'],
                    (int)$d['correlativo_actual'],
                    (int)$d['activo'],
                    (int)$d['id']
                ]);
            }

            $sql = "INSERT INTO {$this->table}
                (id_ies, tipo_doc, serie, correlativo_actual, activo, created_at, updated_at)
                VALUES (?,?,?,?,?, NOW(), NOW())";
            $st = $db->prepare($sql);

            return $st->execute([
                (int)$d['id_ies'],
                (string)$d['tipo_doc'],
                (string)$d['serie'],
                (int)$d['correlativo_actual'],
                (int)$d['activo']
            ]);
        } catch (PDOException $e) {
            // MySQL/MariaDB: Duplicate entry -> error code 1062
            $driverCode = $e->errorInfo[1] ?? null;

            if ((int)$driverCode === 1062) {
                // constraint: uq_cpe_serie (id_ies, tipo_doc, serie)
                $ies  = (int)($d['id_ies'] ?? 0);
                $tipo = (string)($d['tipo_doc'] ?? '');
                $ser  = (string)($d['serie'] ?? '');

                throw new \RuntimeException(
                    "Ya existe una serie registrada con estos datos: IES={$ies}, Tipo Doc={$tipo}, Serie={$ser}."
                );
            }

            // Otros errores SQL
            throw new \RuntimeException("Error de base de datos: " . $e->getMessage());
        }
    }


    public function getPaginated(array $filters, int $limit, int $offset, int $orderCol, string $orderDir): array
    {
        $db = static::getDB();

        $cols = [
            0 => 's.id',
            1 => 's.id_ies',
            2 => 'i.nombre_ies',
            3 => 's.tipo_doc',
            4 => 's.serie',
            5 => 's.correlativo_actual',
            6 => 's.activo',
            7 => 's.updated_at',
        ];
        $orderBy = $cols[$orderCol] ?? 's.id';
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $where = ["1=1"];
        $bind  = [];

        if (!empty($filters['id_ies'])) {
            $where[] = "s.id_ies=?";
            $bind[] = (int)$filters['id_ies'];
        }
        if (!empty($filters['tipo_doc'])) {
            $where[] = "s.tipo_doc=?";
            $bind[] = $filters['tipo_doc'];
        }
        if ($filters['activo'] !== null && $filters['activo'] !== '') {
            $where[] = "s.activo=?";
            $bind[] = (int)$filters['activo'];
        }

        $W = implode(' AND ', $where);

        $total = (int)$db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();

        $st = $db->prepare("SELECT COUNT(*) FROM {$this->table} s JOIN ies i ON i.id=s.id_ies WHERE $W");
        $st->execute($bind);
        $filtered = (int)$st->fetchColumn();

        $sql = "
          SELECT s.*, i.nombre_ies
            FROM {$this->table} s
            JOIN ies i ON i.id=s.id_ies
           WHERE $W
           ORDER BY $orderBy $orderDir
           LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $st = $db->prepare($sql);
        $st->execute($bind);
        $data = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return compact('total', 'filtered', 'data');
    }
}
