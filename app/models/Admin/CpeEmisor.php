<?php

namespace App\Models\Admin;

use Core\Model;
use PDO;

class CpeEmisor extends Model
{
    protected string $table = 'cpe_emisores';

    public function find(int $id): ?array
    {
        $st = static::getDB()->prepare("SELECT * FROM {$this->table} WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function findByIes(int $idIes): ?array
    {
        $db = static::getDB();
        $st = $db->prepare("SELECT * FROM {$this->table} WHERE id_ies=? LIMIT 1");
        $st->execute([(int)$idIes]);

        $r = $st->fetch(\PDO::FETCH_ASSOC); // ✅ namespace-safe
        return $r ?: null;
    }

    /**
     * Retorna el emisor que ya usa ese id_ies, excluyendo un id (para edición).
     */
    private function findDupForIes(int $idIes, ?int $excludeId = null): ?array
    {
        $db = static::getDB();

        if ($excludeId) {
            $st = $db->prepare("SELECT id, id_ies FROM {$this->table} WHERE id_ies=? AND id<>? LIMIT 1");
            $st->execute([(int)$idIes, (int)$excludeId]);
        } else {
            $st = $db->prepare("SELECT id, id_ies FROM {$this->table} WHERE id_ies=? LIMIT 1");
            $st->execute([(int)$idIes]);
        }

        $r = $st->fetch(\PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function guardar(array $d): bool
    {
        $db = static::getDB();

        $id    = !empty($d['id']) ? (int)$d['id'] : null;
        $idIes = (int)($d['id_ies'] ?? 0);

        // ✅ Respetar UNIQUE uq_cpe_emisor_ies (id_ies)
        // Si es nuevo y ya existe -> error
        // Si es edición y otro registro ya usa ese id_ies -> error
        $dup = $this->findDupForIes($idIes, $id);
        if ($dup) {
            // code 1062 para que tu controller lo detecte
            throw new \RuntimeException('Ya existe un emisor para esta IES.', 1062);
        }

        // Normalizar longitudes según BD
        $ruc              = substr(trim((string)($d['ruc'] ?? '')), 0, 11);
        $razon_social     = substr(trim((string)($d['razon_social'] ?? '')), 0, 255);
        $nombre_comercial = trim((string)($d['nombre_comercial'] ?? ''));
        $ubigeo           = trim((string)($d['ubigeo'] ?? ''));
        $direccion        = trim((string)($d['direccion'] ?? ''));
        $departamento     = trim((string)($d['departamento'] ?? ''));
        $provincia        = trim((string)($d['provincia'] ?? ''));
        $distrito         = trim((string)($d['distrito'] ?? ''));
        $email            = trim((string)($d['email'] ?? ''));
        $telefono         = trim((string)($d['telefono'] ?? ''));

        $nombre_comercial = ($nombre_comercial !== '') ? substr($nombre_comercial, 0, 255) : null;
        $ubigeo           = ($ubigeo !== '') ? substr($ubigeo, 0, 6) : null;
        $direccion        = ($direccion !== '') ? substr($direccion, 0, 300) : null;
        $departamento     = ($departamento !== '') ? substr($departamento, 0, 100) : null;
        $provincia        = ($provincia !== '') ? substr($provincia, 0, 100) : null;
        $distrito         = ($distrito !== '') ? substr($distrito, 0, 100) : null;
        $email            = ($email !== '') ? substr($email, 0, 150) : null;
        $telefono         = ($telefono !== '') ? substr($telefono, 0, 30) : null;

        try {
            if ($id) {
                $sql = "
                UPDATE {$this->table}
                   SET id_ies=?,
                       ruc=?,
                       razon_social=?,
                       nombre_comercial=?,
                       ubigeo=?,
                       direccion=?,
                       departamento=?,
                       provincia=?,
                       distrito=?,
                       email=?,
                       telefono=?,
                       updated_at=NOW()
                 WHERE id=?
                 LIMIT 1
            ";
                $st = $db->prepare($sql);
                return $st->execute([
                    $idIes,
                    $ruc,
                    $razon_social,
                    $nombre_comercial,
                    $ubigeo,
                    $direccion,
                    $departamento,
                    $provincia,
                    $distrito,
                    $email,
                    $telefono,
                    $id
                ]);
            }

            $sql = "
            INSERT INTO {$this->table}
                (id_ies, ruc, razon_social, nombre_comercial, ubigeo, direccion, departamento, provincia, distrito, email, telefono, created_at, updated_at)
            VALUES
                (?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())
        ";
            $st = $db->prepare($sql);
            return $st->execute([
                $idIes,
                $ruc,
                $razon_social,
                $nombre_comercial,
                $ubigeo,
                $direccion,
                $departamento,
                $provincia,
                $distrito,
                $email,
                $telefono
            ]);
        } catch (\PDOException $e) {
            // ✅ por si igual viene desde DB (Duplicate entry)
            $driverCode = $e->errorInfo[1] ?? null;
            if ((int)$driverCode === 1062) {
                throw new \RuntimeException('Ya existe un emisor para esta IES.', 1062);
            }
            throw $e;
        }
    }


    public function deleteById(int $id): bool
    {
        $st = static::getDB()->prepare("DELETE FROM {$this->table} WHERE id=? LIMIT 1");
        return $st->execute([$id]);
    }

    public function getPaginated(array $filters, int $limit, int $offset, int $orderCol, string $orderDir): array
    {
        $db = static::getDB();

        $cols = [
            0 => 'e.id',
            1 => 'e.id_ies',
            2 => 'i.nombre_ies',
            3 => 'e.ruc',
            4 => 'e.razon_social',
            5 => 'e.nombre_comercial',
            6 => 'e.ubigeo',
            7 => 'e.email',
            8 => 'e.telefono',
            9 => 'e.updated_at',
        ];
        $orderBy  = $cols[$orderCol] ?? 'e.id';
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $where = ["1=1"];
        $bind  = [];

        if (!empty($filters['ruc'])) {
            $where[] = "e.ruc LIKE ?";
            $bind[]  = '%' . trim((string)$filters['ruc']) . '%';
        }
        if (!empty($filters['razon_social'])) {
            $where[] = "e.razon_social LIKE ?";
            $bind[]  = '%' . trim((string)$filters['razon_social']) . '%';
        }

        $W = implode(' AND ', $where);

        $total = (int)$db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();

        $st = $db->prepare("SELECT COUNT(*) FROM {$this->table} e JOIN ies i ON i.id=e.id_ies WHERE $W");
        $st->execute($bind);
        $filtered = (int)$st->fetchColumn();

        $sql = "
            SELECT e.*, i.nombre_ies
              FROM {$this->table} e
              JOIN ies i ON i.id=e.id_ies
             WHERE $W
             ORDER BY $orderBy $orderDir
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
        ";
        $st = $db->prepare($sql);
        $st->execute($bind);
        $data = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return compact('total', 'filtered', 'data');
    }
}
