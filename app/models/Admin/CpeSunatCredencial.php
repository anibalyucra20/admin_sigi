<?php
namespace App\Models\Admin;

use Core\Model;
use PDO;

class CpeSunatCredencial extends Model
{
    protected string $table = 'cpe_sunat_credenciales';

    private function appKey(): string
    {
        $k = getenv('APP_SECRET') ?: (defined('APP_SECRET') ? APP_SECRET : '');
        if (strlen($k) < 32) {
            throw new \RuntimeException('APP_SECRET no configurado (mínimo 32 chars).');
        }
        return $k;
    }

    private function enc(string $plain): string
    {
        $key = hash('sha256', $this->appKey(), true);
        $iv  = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new \RuntimeException('No se pudo cifrar.');
        return base64_encode($iv . $tag . $cipher);
    }

    public function find(int $id): ?array
    {
        $st = static::getDB()->prepare("SELECT * FROM {$this->table} WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function findByIes(int $idIes): ?array
    {
        $st = static::getDB()->prepare("SELECT * FROM {$this->table} WHERE id_ies=? LIMIT 1");
        $st->execute([$idIes]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function guardar(array $d, ?string $pfxBinary = null): bool
    {
        $db = static::getDB();

        $modo = in_array($d['modo'], ['beta','prod'], true) ? $d['modo'] : 'beta';
        $solUser = trim((string)$d['sol_user']);
        $idIes = (int)$d['id_ies'];

        // mantener secretos si no llegan
        $current = $this->findByIes($idIes);

        $solPassEnc = $current['sol_pass_enc'] ?? null;
        $certPfxEnc = $current['cert_pfx_enc'] ?? null;
        $certPassEnc = $current['cert_pass_enc'] ?? null;

        if (!empty($d['sol_pass'])) $solPassEnc = $this->enc((string)$d['sol_pass']);
        if (!empty($d['cert_pass'])) $certPassEnc = $this->enc((string)$d['cert_pass']);
        if ($pfxBinary !== null && $pfxBinary !== '') $certPfxEnc = $this->enc($pfxBinary);

        if (!$current && (!$solPassEnc || !$certPfxEnc || !$certPassEnc)) {
            throw new \InvalidArgumentException('Primera vez: requiere SOL password + PFX + password del PFX.');
        }

        // upsert por id_ies (UNIQUE uq_sunat_ies)
        $sql = "
          INSERT INTO {$this->table}
            (id_ies, modo, sol_user, sol_pass_enc, cert_pfx_enc, cert_pass_enc, activo, created_at, updated_at)
          VALUES
            (?,?,?,?,?,?,?, NOW(), NOW())
          ON DUPLICATE KEY UPDATE
            modo=VALUES(modo),
            sol_user=VALUES(sol_user),
            sol_pass_enc=VALUES(sol_pass_enc),
            cert_pfx_enc=VALUES(cert_pfx_enc),
            cert_pass_enc=VALUES(cert_pass_enc),
            activo=VALUES(activo),
            updated_at=NOW()
        ";
        $st = $db->prepare($sql);
        return $st->execute([
            $idIes,
            $modo,
            $solUser,
            $solPassEnc,
            $certPfxEnc,
            $certPassEnc,
            (int)$d['activo']
        ]);
    }

    public function getPaginated(array $filters, int $limit, int $offset, int $orderCol, string $orderDir): array
    {
        $db = static::getDB();

        $cols = [
            0 => 'c.id',
            1 => 'c.id_ies',
            2 => 'i.nombre_ies',
            3 => 'c.modo',
            4 => 'c.sol_user',
            5 => 'c.activo',
            6 => 'c.updated_at',
        ];
        $orderBy = $cols[$orderCol] ?? 'c.id';
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $where = ["1=1"];
        $bind  = [];

        if (!empty($filters['id_ies'])) { $where[] = "c.id_ies=?"; $bind[] = (int)$filters['id_ies']; }
        if (!empty($filters['modo']))   { $where[] = "c.modo=?";   $bind[] = $filters['modo']; }
        if ($filters['activo'] !== null && $filters['activo'] !== '') {
            $where[] = "c.activo=?";
            $bind[] = (int)$filters['activo'];
        }

        $W = implode(' AND ', $where);

        $total = (int)$db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();

        $st = $db->prepare("SELECT COUNT(*) FROM {$this->table} c JOIN ies i ON i.id=c.id_ies WHERE $W");
        $st->execute($bind);
        $filtered = (int)$st->fetchColumn();

        $sql = "
          SELECT c.*, i.nombre_ies,
                 (CASE WHEN c.sol_pass_enc IS NULL OR c.sol_pass_enc='' THEN 0 ELSE 1 END) AS has_sol_pass,
                 (CASE WHEN c.cert_pfx_enc IS NULL OR c.cert_pfx_enc='' THEN 0 ELSE 1 END) AS has_pfx
            FROM {$this->table} c
            JOIN ies i ON i.id=c.id_ies
           WHERE $W
           ORDER BY $orderBy $orderDir
           LIMIT ".(int)$limit." OFFSET ".(int)$offset;

        $st = $db->prepare($sql);
        $st->execute($bind);
        $data = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return compact('total', 'filtered', 'data');
    }
}
