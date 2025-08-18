<?php

namespace App\Controllers\Api;

class BaseApiController
{
    protected \PDO $db;
    protected int $tenantId = 0;

    public function __construct()
    {
        \Core\Auth::start();
        $this->db = (new \Core\Model())->getDB();
        $this->requireApiKey();
        $this->applyRateLimit();
    }

    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function requireApiKey(): void
    {
        $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($key === '') $this->json(['error' => 'Unauthorized'], 401);
        $stmt = $this->db->query("SELECT id, id_ies, key_hash FROM api_keys WHERE activo=1");
        $ok = false;
        foreach ($stmt as $row) {
            if (password_verify($key, $row['key_hash'])) {
                $ok = true;
                $this->tenantId = (int)$row['id_ies'];
                $this->db->prepare("UPDATE api_keys SET ultimo_uso=NOW() WHERE id=?")->execute([$row['id']]);
                break;
            }
        }
        if (!$ok) $this->json(['error' => 'Unauthorized'], 401);
    }

    // Dentro de BaseApiController
    protected function applyRateLimit(): void
    {
        if (!$this->shouldCountUsage()) return; // 👈 salta contador para biblioteca

        $periodo = date('Ym');
        $endpoint = ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ":" . ($_GET['url'] ?? '/');
        $limite = 100000;
        $this->db->prepare("INSERT INTO usage_counters (id_ies, periodo_aaaamm, endpoint, requests, bytes)
                        VALUES (?,?,?,?,0) ON DUPLICATE KEY UPDATE requests=requests+1")
            ->execute([$this->tenantId, $periodo, $endpoint, 1]);
        $s = $this->db->prepare("SELECT SUM(requests) FROM usage_counters WHERE id_ies=? AND periodo_aaaamm=?");
        $s->execute([$this->tenantId, $periodo]);
        if ((int)$s->fetchColumn() > $limite) $this->json(['error' => 'Rate limit exceeded'], 429);
    }

    private function shouldCountUsage(): bool
    {
        // Normaliza path
        $path = '/' . trim($_GET['url'] ?? '/', '/'); // ej: /api/library/items
        // 👇 Whitelist de biblioteca (lectura y subida) que NO cuentan
        if (preg_match('#^/api/library/(items|show|upload|adopt)(/|$)#', $path)) {
            return false;
        }
        return true;
    }


    protected function cfg(): array
    {
        $path = __DIR__ . '/../../../config/app.php';
        return file_exists($path) ? require $path : ['library' => [
            'covers_base_url' => BASE_URL . '/covers',
            'files_base_url' => BASE_URL . '/books',
        ]];
    }
}
