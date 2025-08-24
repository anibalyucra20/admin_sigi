<?php

namespace App\Models\Admin;

use Core\Model;
use PDO;

class Payment extends Model
{
    protected $table = 'payments';

    public function getPaginated(array $filters, int $length, int $start, int $orderCol, string $orderDir): array
    {
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';
        $cols = [
            0 => 'p.id',
            1 => 'i.id',
            2 => 'ies.nombre_ies',
            3 => 'p.monto',
            4 => 'p.moneda',
            5 => 'p.pasarela',
            6 => 'p.estado',
            7 => 'p.paid_at',
            8 => 'p.created_at',
        ];
        $orderBy = $cols[$orderCol] ?? 'p.id';

        $where = [];
        $p = [];
        if (!empty($filters['id_invoice'])) {
            $where[] = 'p.id_invoice=:inv';
            $p[':inv'] = (int)$filters['id_invoice'];
        }
        if (!empty($filters['estado'])) {
            $where[] = 'p.estado=:e';
            $p[':e'] = $filters['estado'];
        }
        if (!empty($filters['pasarela'])) {
            $where[] = 'p.pasarela=:pas';
            $p[':pas'] = $filters['pasarela'];
        }
        if (!empty($filters['id_ies'])) {
            $where[] = 'i.id_ies=:id_ies';
            $p[':id_ies'] = (int)$filters['id_ies'];
        }
        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT p.id, p.id_invoice, p.monto, p.moneda, p.pasarela, p.external_id, p.estado, p.paid_at, p.created_at, p.updated_at,
                       i.id_ies, i.periodo_aaaamm, i.total AS invoice_total,
                       ies.nombre_ies
                  FROM {$this->table} p
                  JOIN invoices i ON i.id = p.id_invoice
                  JOIN ies ON ies.id = i.id_ies
                  $sqlWhere
                 ORDER BY $orderBy $orderDir
                 LIMIT :lim OFFSET :off";
        $st = self::$db->prepare($sql);
        foreach ($p as $k => $v) $st->bindValue($k, is_int($v) ? $v : (string)$v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $st->bindValue(':lim', $length, PDO::PARAM_INT);
        $st->bindValue(':off', $start, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);

        $sqlCount = "SELECT COUNT(*)
                       FROM {$this->table} p
                       JOIN invoices i ON i.id = p.id_invoice
                       JOIN ies ON ies.id = i.id_ies
                       $sqlWhere";
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
            ':id_invoice' => (int)$d['id_invoice'],
            ':monto'      => (float)$d['monto'],
            ':moneda'     => strtoupper(trim($d['moneda'] ?? 'PEN')),
            ':pasarela'   => trim($d['pasarela'] ?? ''),
            ':ext'        => $d['external_id'] ?? null,
            ':estado'     => in_array($d['estado'] ?? 'pendiente', ['pendiente', 'pagado', 'fallido', 'revertido']) ? $d['estado'] : 'pendiente',
            ':paid_at'    => $d['paid_at'] ?? null,
        ];

        if (!empty($d['id'])) {
            $params[':id'] = (int)$d['id'];
            $sql = "UPDATE {$this->table}
                       SET id_invoice=:id_invoice, monto=:monto, moneda=:moneda, pasarela=:pasarela,
                           external_id=:ext, estado=:estado, paid_at=:paid_at, updated_at=NOW()
                     WHERE id=:id";
        } else {
            $sql = "INSERT INTO {$this->table}
                       (id_invoice, monto, moneda, pasarela, external_id, estado, paid_at, created_at, updated_at)
                    VALUES (:id_invoice,:monto,:moneda,:pasarela,:ext,:estado,:paid_at,NOW(),NOW())";
        }
        $st = self::$db->prepare($sql);
        $ok = $st->execute($params);

        if ($ok) $this->recalcInvoice((int)$d['id_invoice']);
        return $ok;
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        $estado = in_array($estado, ['pendiente', 'pagado', 'fallido', 'revertido']) ? $estado : 'pendiente';
        $st = self::$db->prepare("UPDATE {$this->table} SET estado=:e, updated_at=NOW() WHERE id=:id");
        $ok = $st->execute([':e' => $estado, ':id' => $id]);
        if ($ok) {
            $iv = self::$db->prepare("SELECT id_invoice FROM {$this->table} WHERE id=?");
            $iv->execute([$id]);
            $idInv = (int)$iv->fetchColumn();
            if ($idInv) $this->recalcInvoice($idInv);
        }
        return $ok;
    }

    private function recalcInvoice(int $idInvoice): void
    {
        // delega al modelo de invoices
        require_once __DIR__ . '/Invoice.php';
        $invModel = new Invoice();
        $invModel->recalcEstadoFromPagos($idInvoice);
    }

    public function allInvoices(): array
    {
        $st = self::$db->query("
            SELECT i.id, CONCAT('#',i.id,' - ',ies.nombre_ies,' - ',i.periodo_aaaamm,' - ',i.moneda,' ',FORMAT(i.total,2)) AS etiqueta
            FROM invoices i JOIN ies ON ies.id = i.id_ies
            ORDER BY i.id DESC LIMIT 500
        ");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    public function allInvoicesNoPagadas(): array
    {
        $st = self::$db->query("
            SELECT i.id, CONCAT('#',i.id,' - ',ies.nombre_ies,' - ',i.periodo_aaaamm,' - ',i.moneda,' ',FORMAT(i.total,2)) AS etiqueta
            FROM invoices i JOIN ies ON ies.id = i.id_ies WHERE i.estado='pendiente' OR i.estado='vencida'
            ORDER BY i.id DESC LIMIT 500
        ");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function invoicesByIes(int $idIes): array
    {
        $st = self::$db->prepare("
            SELECT i.id, CONCAT('#',i.id,' - ',i.periodo_aaaamm,' - ',i.moneda,' ',FORMAT(i.total,2)) AS etiqueta
              FROM invoices i
             WHERE i.id_ies=? AND (i.estado='pendiente' OR i.estado='vencida')
             ORDER BY i.id DESC LIMIT 500
        ");
        $st->execute([$idIes]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
