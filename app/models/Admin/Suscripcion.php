<?php

namespace App\Models\Admin;

use Core\Model;
use PDO;
use DateTime;
use DateInterval;

class Suscripcion extends Model
{
    protected $table = 'subscriptions';

    public function getPaginated(array $filters, int $length, int $start, int $orderCol, string $orderDir): array
    {
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';
        $cols = [
            0 => 's.id',
            1 => 'i.nombre_ies',
            2 => 'p.nombre',
            3 => 's.ciclo',
            4 => 's.inicia',
            5 => 's.vence',
            6 => 's.estado',
        ];
        $orderBy = $cols[$orderCol] ?? 's.id';

        $where = [];
        $params = [];

        if (!empty($filters['id_ies'])) {
            $where[] = 's.id_ies = :id_ies';
            $params[':id_ies'] = (int)$filters['id_ies'];
        }
        if (!empty($filters['id_plan'])) {
            $where[] = 's.id_plan = :id_plan';
            $params[':id_plan'] = (int)$filters['id_plan'];
        }
        if (!empty($filters['estado'])) {
            $where[] = 's.estado = :estado';
            $params[':estado'] = $filters['estado'];
        }
        if (!empty($filters['ciclo'])) {
            $where[] = 's.ciclo = :ciclo';
            $params[':ciclo'] = $filters['ciclo'];
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT s.id, s.id_ies, s.id_plan, s.ciclo, s.inicia, s.vence, s.estado,
                       i.nombre_ies, i.dominio,
                       p.nombre AS plan_nombre, p.monto
                  FROM {$this->table} s
                  JOIN ies i     ON i.id = s.id_ies
                  JOIN planes p  ON p.id = s.id_plan
                  $sqlWhere
                 ORDER BY $orderBy $orderDir
                 LIMIT :limit OFFSET :offset";
        $st = self::$db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, is_int($v) ? $v : (string)$v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue(':limit', $length, PDO::PARAM_INT);
        $st->bindValue(':offset', $start, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);

        $sqlTotal = "SELECT COUNT(*)
                      FROM {$this->table} s
                      JOIN ies i ON i.id = s.id_ies
                      JOIN planes p ON p.id = s.id_plan
                      $sqlWhere";
        $st2 = self::$db->prepare($sqlTotal);
        foreach ($params as $k => $v) {
            $st2->bindValue($k, is_int($v) ? $v : (string)$v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st2->execute();
        $total = (int)$st2->fetchColumn();

        // total global para DataTables (sin filtros)
        $global = (int) self::$db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();

        return ['data' => $data, 'total' => $global, 'filtered' => $total];
    }

    public function find(int $id): ?array
    {
        $st = self::$db->prepare("SELECT * FROM {$this->table} WHERE id=?");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
    // NUEVO: helper
    private function hasActiveForIes(int $idIes, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table}
            WHERE id_ies = ? AND estado IN ('trial','activa')";
        $params = [$idIes];
        if ($excludeId) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";
        $st = self::$db->prepare($sql);
        $st->execute($params);
        return (bool)$st->fetchColumn();
    }


    public function guardar(array $d): bool
    {
        // Normaliza fechas y calcula 'vence' si no viene
        $inicia = new DateTime($d['inicia']);
        if (empty($d['vence']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['vence'])) {
            $vence = clone $inicia;
            if (($d['ciclo'] ?? 'mensual') === 'anual') {
                $vence->add(new DateInterval('P1Y'));
            } else {
                $vence->add(new DateInterval('P1M'));
            }
            $d['vence'] = $vence->format('Y-m-d');
        }

        $params = [
            ':id_ies' => (int)$d['id_ies'],
            ':id_plan' => (int)$d['id_plan'],
            ':ciclo' => in_array($d['ciclo'] ?? 'mensual', ['mensual', 'anual']) ? $d['ciclo'] : 'mensual',
            ':inicia' => $inicia->format('Y-m-d'),
            ':vence'  => $d['vence'],
            ':estado' => in_array($d['estado'] ?? 'activa', ['trial', 'activa', 'suspendida', 'cancelada']) ? $d['estado'] : 'activa',
        ];
        // si quedará vigente, valida unicidad por IES
        $willBeActive = in_array($params[':estado'], ['trial', 'activa'], true);
        $excludeId    = !empty($d['id']) ? (int)$d['id'] : null;

        if ($willBeActive && $this->hasActiveForIes((int)$d['id_ies'], $excludeId)) {
            throw new \RuntimeException('Ya existe una suscripción vigente (trial/activa) para este IES.');
        }

        if (!empty($d['id'])) {
            $params[':id'] = (int)$d['id'];
            $sql = "UPDATE {$this->table}
                       SET id_ies=:id_ies, id_plan=:id_plan, ciclo=:ciclo, inicia=:inicia, vence=:vence, estado=:estado, updated_at=NOW()
                     WHERE id=:id";
        } else {
            $sql = "INSERT INTO {$this->table}
                       (id_ies, id_plan, ciclo, inicia, vence, estado, created_at, updated_at)
                    VALUES (:id_ies, :id_plan, :ciclo, :inicia, :vence, :estado, NOW(), NOW())";
        }
        $st = self::$db->prepare($sql);
        return $st->execute($params);
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        $estado = in_array($estado, ['trial', 'activa', 'suspendida', 'cancelada']) ? $estado : 'activa';
        if (in_array($estado, ['trial', 'activa'], true)) {
            // averigua el IES del registro
            $st = self::$db->prepare("SELECT id_ies FROM {$this->table} WHERE id=?");
            $st->execute([$id]);
            $idIes = (int)$st->fetchColumn();

            if ($idIes && $this->hasActiveForIes($idIes, $id)) {
                throw new \RuntimeException('Ya existe una suscripción vigente (trial/activa) para este IES.');
            }
        }

        $st = self::$db->prepare("UPDATE {$this->table} SET estado=:e, updated_at=NOW() WHERE id=:id");
        return $st->execute([':e' => $estado, ':id' => $id]);
    }

    // Helpers para selects
    public function allIes(): array
    {
        $st = self::$db->query("SELECT id, nombre_ies FROM ies ORDER BY nombre_ies");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    public function allPlanes(): array
    {
        $st = self::$db->query("SELECT id, nombre FROM planes ORDER BY nombre");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
