<?php

namespace App\Models\Admin;

use Core\Model;
use PDO;
use DateTimeImmutable;
use DateInterval;

class Invoice extends Model
{
    protected $table = 'invoices';

    public function getPaginated(array $filters, int $length, int $start, int $orderCol, string $orderDir): array
    {
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';
        $cols = [
            0 => 'i.id',
            1 => 'ies.nombre_ies',
            2 => 'i.periodo_aaaamm',
            3 => 'i.total',
            4 => 'i.moneda',
            5 => 'i.estado',
            6 => 'i.pasarela',
            7 => 'i.due_at',
            8 => 'i.created_at',
        ];
        $orderBy = $cols[$orderCol] ?? 'i.id';

        $where = [];
        $p = [];
        if (!empty($filters['id_ies'])) {
            $where[] = 'i.id_ies=:id_ies';
            $p[':id_ies'] = (int)$filters['id_ies'];
        }
        if (!empty($filters['estado'])) {
            $where[] = 'i.estado=:estado';
            $p[':estado'] = $filters['estado'];
        }
        if (!empty($filters['pasarela'])) {
            $where[] = 'i.pasarela=:pas';
            $p[':pas'] = $filters['pasarela'];
        }
        if (!empty($filters['periodo'])) {
            $where[] = 'i.periodo_aaaamm=:per';
            $p[':per'] = $filters['periodo'];
        }
        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT i.id, i.id_ies, i.periodo_aaaamm, i.total, i.moneda, i.estado, i.pasarela, i.external_id,
                       i.created_at, i.updated_at, i.due_at,
                       ies.nombre_ies,
                       COALESCE(pg.pagado,0) AS pagado,
                       (i.total - COALESCE(pg.pagado,0)) AS saldo
                  FROM {$this->table} i
                  JOIN ies ON ies.id = i.id_ies
             LEFT JOIN (
                       SELECT id_invoice, SUM(monto) AS pagado
                         FROM payments
                        WHERE estado='pagado'
                        GROUP BY id_invoice
                       ) pg ON pg.id_invoice = i.id
                  $sqlWhere
                 ORDER BY $orderBy $orderDir
                 LIMIT :lim OFFSET :off";
        $st = self::$db->prepare($sql);
        foreach ($p as $k => $v) $st->bindValue($k, is_int($v) ? $v : (string)$v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $st->bindValue(':lim', $length, PDO::PARAM_INT);
        $st->bindValue(':off', $start, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);

        $sqlCount = "SELECT COUNT(*) FROM {$this->table} i $sqlWhere";
        $stc = self::$db->prepare($sqlCount);
        foreach ($p as $k => $v) $stc->bindValue($k, is_int($v) ? $v : (string)$v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stc->execute();
        $filtered = (int)$stc->fetchColumn();
        $total = (int) self::$db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();

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
            ':id_ies' => (int)$d['id_ies'],
            ':periodo' => strtoupper(trim($d['periodo_aaaamm'])),
            ':total'  => (float)$d['total'],
            ':moneda' => strtoupper(trim($d['moneda'] ?? 'PEN')),
            ':estado' => in_array($d['estado'] ?? 'pendiente', ['pendiente', 'pagada', 'vencida', 'anulada']) ? $d['estado'] : 'pendiente',
            ':pas'    => $d['pasarela'] ?? null,
            ':ext'    => $d['external_id'] ?? null,
            ':due'    => ($d['due_at'] ?? null) ?: null,
        ];

        if (!empty($d['id'])) {
            $params[':id'] = (int)$d['id'];
            $sql = "UPDATE {$this->table}
                       SET id_ies=:id_ies, periodo_aaaamm=:periodo, total=:total, moneda=:moneda,
                           estado=:estado, pasarela=:pas, external_id=:ext, due_at=:due, updated_at=NOW()
                     WHERE id=:id";
        } else {
            $sql = "INSERT INTO {$this->table}
                       (id_ies, periodo_aaaamm, total, moneda, estado, pasarela, external_id, created_at, updated_at, due_at)
                    VALUES (:id_ies,:periodo,:total,:moneda,:estado,:pas,:ext,NOW(),NOW(),:due)";
        }
        $st = self::$db->prepare($sql);
        $ok = $st->execute($params);

        // Si está marcada 'pagada' o 'pendiente', recalcular estado vs pagos
        if ($ok) {
            $id = !empty($d['id']) ? (int)$d['id'] : (int)self::$db->lastInsertId();
            $this->recalcEstadoFromPagos($id);
        }
        return $ok;
    }

    public function anular(int $id): bool
    {
        $st = self::$db->prepare("UPDATE {$this->table} SET estado='anulada', updated_at=NOW() WHERE id=:id");
        return $st->execute([':id' => $id]);
    }

    public function marcarPagada(int $id): bool
    {
        $st = self::$db->prepare("UPDATE {$this->table} SET estado='pagada', updated_at=NOW() WHERE id=:id");
        return $st->execute([':id' => $id]);
    }

    public function recalcEstadoFromPagos(int $id): void
    {
        $st = self::$db->prepare("SELECT total, estado, due_at FROM {$this->table} WHERE id=?");
        $st->execute([$id]);
        $iv = $st->fetch(PDO::FETCH_ASSOC);
        if (!$iv) return;

        $st2 = self::$db->prepare("SELECT COALESCE(SUM(monto),0) FROM payments WHERE id_invoice=? AND estado='pagado'");
        $st2->execute([$id]);
        $pagado = (float)$st2->fetchColumn();

        $nuevoEstado = $iv['estado'];
        if ($iv['estado'] !== 'anulada') {
            if ($pagado + 0.00001 >= (float)$iv['total']) {
                $nuevoEstado = 'pagada';
            } else {
                // si venció y no está pagada
                if (!empty($iv['due_at']) && $iv['due_at'] <= date('Y-m-d')) {
                    $nuevoEstado = 'vencida';
                } else {
                    $nuevoEstado = 'pendiente';
                }
            }
        }
        if ($nuevoEstado !== $iv['estado']) {
            $up = self::$db->prepare("UPDATE {$this->table} SET estado=:e, updated_at=NOW() WHERE id=:id");
            $up->execute([':e' => $nuevoEstado, ':id' => $id]);
        }
    }

    public function allIes(): array
    {
        $st = self::$db->query("SELECT id, nombre_ies FROM ies ORDER BY nombre_ies");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }



    public function generarPendientes(?DateTimeImmutable $asOf = null): array
    {
        $asOf = $asOf ?: new DateTimeImmutable('now');              // Hoy
        $endYm = $asOf->format('Ym');                               // Límite superior (mes actual)

        // Trae suscripciones vigentes con su plan
        $sql = "SELECT s.id, s.id_ies, s.id_plan, s.ciclo, s.inicia, s.vence, s.estado,
                   p.monto, p.id AS plan_id, p.nombre
              FROM subscriptions s
              JOIN planes p ON p.id = s.id_plan
             WHERE s.estado IN ('trial','activa')";
        $subs = self::$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $resumen = ['procesadas' => 0, 'creadas' => 0, 'por_ies' => []];

        foreach ($subs as $s) {
            $resumen['procesadas']++;

            // Determinar mes inicial y final en formato Y-m-01
            $start = (new DateTimeImmutable($s['inicia']))->modify('first day of this month');
            $end   = (new DateTimeImmutable($s['vence']))->modify('first day of this month');

            // Limitar por fecha de corte (asOf)
            $corte = (new DateTimeImmutable($asOf->format('Y-m-01')));
            if ($end > $corte) $end = $corte;                        // no generamos futuro

            if ($start > $end) continue; // nada que generar

            // Averigua el último período ya facturado de ese IES dentro del rango
            $startYm = $start->format('Ym');
            $endRangeYm = $end->format('Ym');

            $st = self::$db->prepare(
                "SELECT MAX(periodo_aaaamm) FROM {$this->table}
             WHERE id_ies = ? AND periodo_aaaamm BETWEEN ? AND ?"
            );
            $st->execute([(int)$s['id_ies'], $startYm, $endRangeYm]);
            $maxPer = $st->fetchColumn();

            // Punto de partida
            $periodo = $maxPer ? DateTimeImmutable::createFromFormat('Ym-d', $maxPer . '-01')
                : $start->modify('-1 month');

            $creadasIes = 0;

            if ($s['ciclo'] === 'mensual') {
                // Generar mes a mes
                while (true) {
                    $periodo = $periodo->add(new DateInterval('P1M'));      // siguiente mes
                    if ($periodo > $end) break;

                    if ($this->crearSiFalta(
                        (int)$s['id_ies'],
                        $periodo->format('Ym'),
                        (float)$s['monto'],
                        'PEN',                                             // moneda fija en tu esquema
                        $this->calcularVencimiento($periodo)
                    )) {
                        $creadasIes++;
                        $resumen['creadas']++;
                    }
                }
            } else { // 'anual'
                // Un recibo por año, en el mismo mes de inicio
                $mesAnual = (int)$start->format('m'); // mes de corte anual
                while (true) {
                    $periodo = $periodo->add(new DateInterval('P1M'));
                    if ($periodo > $end) break;
                    if ((int)$periodo->format('m') !== $mesAnual) continue;

                    if ($this->crearSiFalta(
                        (int)$s['id_ies'],
                        $periodo->format('Ym'),
                        (float)$s['monto'],
                        'PEN',
                        $this->calcularVencimiento($periodo)
                    )) {
                        $creadasIes++;
                        $resumen['creadas']++;
                    }
                }
            }

            if (!isset($resumen['por_ies'][$s['id_ies']])) {
                $resumen['por_ies'][$s['id_ies']] = 0;
            }
            $resumen['por_ies'][$s['id_ies']] += $creadasIes;
        }

        return $resumen;
    }

    /**
     * Crea la factura si no existe; usa UNIQUE (id_ies, periodo_aaaamm) si lo tienes.
     * Devuelve true si se creó, false si ya existía o no se pudo crear.
     */
    private function crearSiFalta(int $idIes, string $periodoAaaamm, float $total, string $moneda, string $dueAt): bool
    {
        // Si NO tienes el UNIQUE, descomenta este check extra:
        /*
    $st = self::$db->prepare("SELECT 1 FROM {$this->table} WHERE id_ies=? AND periodo_aaaamm=? LIMIT 1");
    $st->execute([$idIes, $periodoAaaamm]);
    if ($st->fetchColumn()) return false;
    */

        // Con UNIQUE: usa INSERT IGNORE o DUPLICATE KEY no-op
        $sql = "INSERT INTO {$this->table}
               (id_ies, periodo_aaaamm, total, moneda, estado, pasarela, external_id, created_at, updated_at, due_at)
            VALUES (:ies, :per, :total, :mon, 'pendiente', NULL, NULL, NOW(), NOW(), :due)
            ON DUPLICATE KEY UPDATE id = id";
        $st = self::$db->prepare($sql);
        try {
            $ok = $st->execute([
                ':ies' => $idIes,
                ':per' => $periodoAaaamm,
                ':total' => $total,
                ':mon' => $moneda,
                ':due' => $dueAt,
            ]);
            // rowCount() será 1 si insertó, 0 si no hizo nada en el DUP KEY
            return $ok && ($st->rowCount() > 0);
        } catch (\PDOException $e) {
            // Si no tienes UNIQUE y colisiona por otro motivo
            return false;
        }
    }

    /** Regla de vencimiento: día 10 del mes siguiente al período */
    private function calcularVencimiento(DateTimeImmutable $periodo): string
    {
        // $periodo es el 1er día del mes Y-m-01
        $next = $periodo->modify('first day of next month');
        // Día 10 del mes siguiente
        return $next->setDate((int)$next->format('Y'), (int)$next->format('m'), 10)->format('Y-m-d');
    }
}
