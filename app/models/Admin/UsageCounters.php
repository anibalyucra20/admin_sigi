<?php
namespace App\Models;

use Core\Model;
use PDO;

class UsageCounters extends Model
{
    public function bump(int $idIes, string $endpoint, int $bytes=0): void {
        $periodo = date('Ym'); // aaaamm
        $sql = "INSERT INTO usage_counters (id_ies, periodo_aaaamm, endpoint, requests, bytes, updated_at, created_at)
                VALUES (?,?,?,?,?,NOW(),NOW())
                ON DUPLICATE KEY UPDATE requests=requests+1, bytes=bytes+VALUES(bytes), updated_at=NOW()";
        $st  = self::getDB()->prepare($sql);
        $st->execute([$idIes, $periodo, $endpoint, 1, $bytes]);
    }
}
