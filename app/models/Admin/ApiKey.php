<?php

namespace App\Models\Admin;

use Core\Model;
use PDO;

class ApiKey extends Model
{
    protected $table = 'api_keys';
    public function find($id): array
    {
        $st = self::$db->query("SELECT * FROM api_keys WHERE id='$id' LIMIT 1");
        return $st->fetch(PDO::FETCH_ASSOC);
    }
    /** DataTables server-side */
    public function getPaginated(array $filters, int $length, int $start, int $orderCol, string $orderDir): array
    {
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';
        $cols = [
            0 => 'k.id',
            1 => 'i.nombre_ies',
            2 => 'k.nombre',
            3 => 'k.activo',
            4 => 'k.ultimo_uso',
            5 => 'k.created_at',
        ];
        $orderBy = $cols[$orderCol] ?? 'k.id';

        $where = [];
        $params = [];

        if (!empty($filters['id_ies'])) {
            $where[] = 'k.id_ies = :id_ies';
            $params[':id_ies'] = (int)$filters['id_ies'];
        }
        if (isset($filters['activo']) && $filters['activo'] !== '') {
            $where[] = 'k.activo = :activo';
            $params[':activo'] = (int)$filters['activo'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(k.nombre LIKE :q OR i.nombre_ies LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT k.id, k.id_ies, k.nombre, k.activo, k.ultimo_uso, k.created_at,
                       i.nombre_ies
                  FROM {$this->table} k
                  JOIN ies i ON i.id = k.id_ies
                  $sqlWhere
                 ORDER BY $orderBy $orderDir
                 LIMIT :limit OFFSET :offset";
        $st = self::$db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue(':limit', $length, PDO::PARAM_INT);
        $st->bindValue(':offset', $start, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);

        $st2 = self::$db->prepare("SELECT COUNT(*) FROM {$this->table} k JOIN ies i ON i.id=k.id_ies $sqlWhere");
        foreach ($params as $k => $v) {
            $st2->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st2->execute();
        $filtered = (int)$st2->fetchColumn();

        $global = (int) self::$db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();

        return ['data' => $data, 'total' => $global, 'filtered' => $filtered];
    }

    public function allIes(): array
    {
        $st = self::$db->query("SELECT id, nombre_ies FROM ies ORDER BY nombre_ies");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Crear una API key para un IES y devolver la clave en claro (mostrar una vez) */
    public function createForIes(int $idIes, string $nombreDeseado = 'default', ?int $creadoPor = null): array
    {
        // resolver nombre único por uq_keys_ies_nombre
        $base = trim($nombreDeseado) !== '' ? trim($nombreDeseado) : 'default';
        $nombre = $base;
        $i = 1;
        while ($this->existsName($idIes, $nombre) && $i < 50) {
            $nombre = $base . '-' . $i;
            $i++;
        }

        $plaintext = $this->generatePlainKey($idIes);
        $hash = password_hash($plaintext, PASSWORD_DEFAULT);

        $st = self::$db->prepare("INSERT INTO {$this->table}
            (id_ies, nombre, key_hash, ultimo_uso, activo, creado_por, created_at)
            VALUES (:id_ies, :nombre, :hash, NULL, 1, :creado_por, NOW())");
        $st->execute([
            ':id_ies' => $idIes,
            ':nombre' => $nombre,
            ':hash' => $hash,
            ':creado_por' => $creadoPor,
        ]);

        $id = (int) self::$db->lastInsertId();
        return ['id' => $id, 'nombre' => $nombre, 'key' => $plaintext];
    }

    public function rotate(int $id, int $idIes): array
    {
        $plaintext = $this->generatePlainKey($idIes);
        $hash = password_hash($plaintext, PASSWORD_DEFAULT);
        $st = self::$db->prepare("UPDATE {$this->table} SET key_hash=:h, ultimo_uso=NULL, created_at=NOW() WHERE id=:id");
        $st->execute([':h' => $hash, ':id' => $id]);
        return ['id' => $id, 'key' => $plaintext];
    }

    public function activate(int $id, bool $on): bool
    {
        $st = self::$db->prepare("UPDATE {$this->table} SET activo=:a WHERE id=:id");
        return $st->execute([':a' => $on ? 1 : 0, ':id' => $id]);
    }

    private function existsName(int $idIes, string $nombre): bool
    {
        $st = self::$db->prepare("SELECT 1 FROM {$this->table} WHERE id_ies=? AND nombre=? LIMIT 1");
        $st->execute([$idIes, $nombre]);
        return (bool)$st->fetchColumn();
    }

    private function generatePlainKey(int $idIes): string
    {
        $rand = bin2hex(random_bytes(24)); // 48 hex
        return 'SIGI-' . ($idIes ?: 'X') . '-' . $rand;
    }
}
