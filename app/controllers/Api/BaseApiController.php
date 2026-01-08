<?php

namespace App\Controllers\Api;

use Core\Controller;
use Core\Model;
use PDO;

class BaseApiController extends Controller
{
    protected $db;
    protected $tenantId = null;
    protected $apiKeyId = null;

    public function __construct()
    {
        parent::__construct();
        $this->db = Model::getDB();
        header('Content-Type: application/json; charset=utf-8');
    }

    protected function json($data, int $status = 200)
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function error(string $message, int $status = 400, string $code = 'ERROR', array $details = [])
    {
        $payload = ['ok' => false, 'error' => ['code' => $code, 'message' => $message]];
        if ($details)
            $payload['error']['details'] = $details;
        $this->json($payload, $status);
    }

    protected function cfg(): array
    {
        $fallback = [
            'library' => [
                'covers_base_url' => (defined('BASE_URL') ? BASE_URL : '') . '/covers',
                'files_base_url' => (defined('BASE_URL') ? BASE_URL : '') . '/books',
            ]
        ];
        $path = __DIR__ . '/../../../config/app.php';
        $cfg = file_exists($path) ? require $path : $fallback;
        $cfg['library']['covers_base_url'] = rtrim($cfg['library']['covers_base_url'] ?? $fallback['library']['covers_base_url'], '/');
        $cfg['library']['files_base_url'] = rtrim($cfg['library']['files_base_url'] ?? $fallback['library']['files_base_url'], '/');
        return $cfg;
    }

    protected function getHeader(string $name): ?string
    {
        $key = 'HTTP_' . str_replace('-', '_', strtoupper($name));
        if (!empty($_SERVER[$key]))
            return $_SERVER[$key];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, $name) === 0)
                    return $v;
            }
        }
        return null;
    }

    /**
     * Requiere X-Api-Key con formato: SIGI-<tenant>-<hex>
     * - <tenant> puede ser id_ies (numérico) o un slug (dominio/llave).
     * - Verifica hash contra raw completo y, en compat, contra solo el secreto.
     * - Exige IES activa y suscripción vigente (trial/activa dentro de fechas).
     */
    protected function requireApiKey($end_personalizado = ""): bool
    {
        $raw = trim((string) ($this->getHeader('X-Api-Key') ?? ''));
        if ($raw === '') {
            $this->error('Falta X-Api-Key', 401, 'MISSING_API_KEY');
        }

        // SIGI-<tenant>-<secretHex>
        if (!preg_match('/^SIGI-([A-Za-z0-9_-]+)-([A-Fa-f0-9]{16,})$/', $raw, $m)) {
            $this->error('Formato de API key inválido', 401, 'BAD_API_KEY_FORMAT');
        }
        $tenant = $m[1];
        $secret = $m[2];

        // 1) Resolver IES (por id numérico o por dominio/llave)
        if (ctype_digit($tenant)) {
            $st = $this->db->prepare("SELECT id, estado FROM ies WHERE id=? LIMIT 1");
            $st->execute([(int) $tenant]);
        } else {
            $st = $this->db->prepare("SELECT id, estado FROM ies WHERE dominio=? OR llave=? LIMIT 1");
            $st->execute([$tenant, $tenant]);
        }
        $ies = $st->fetch(PDO::FETCH_ASSOC);
        if (!$ies) {
            $this->error('IES no existe', 401, 'IES_NOT_FOUND');
        }
        if (strcasecmp($ies['estado'] ?? 'activa', 'activa') !== 0) {
            $this->error('IES suspendida', 403, 'IES_SUSPENDED');
        }

        // 2) Traer API keys activas para esa IES
        $st = $this->db->prepare("SELECT id, key_hash FROM api_keys WHERE id_ies=? AND activo=1");
        $st->execute([$ies['id']]);
        $keys = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$keys) {
            $this->error('No hay API keys activas', 401, 'APIKEY_NOT_FOUND');
        }

        // 3) Verificar hash: primero raw completo, luego solo secret (compat antiguo)
        $ok = false;
        $matchedKeyId = null;
        foreach ($keys as $k) {
            $hash = $k['key_hash'] ?? '';
            if ($hash === '')
                continue;
            if (password_verify($raw, $hash) || password_verify($secret, $hash)) {
                $ok = true;
                $matchedKeyId = (int) $k['id'];
                break;
            }
        }
        if (!$ok) {
            $this->error('API key inválida o inactiva', 401, 'APIKEY_MISMATCH');
        }

        // 4) Suscripción vigente (trial/activa y fechas)
        $st = $this->db->prepare("
            SELECT 1
              FROM subscriptions
             WHERE id_ies=? 
               AND estado IN ('trial','activa')
               AND CURDATE() BETWEEN inicia AND vence
             LIMIT 1
        ");
        $st->execute([$ies['id']]);
        if (!$st->fetchColumn()) {
            $this->error('Suscripción no vigente', 403, 'NO_ACTIVE_SUBSCRIPTION');
        }

        // 5) OK → set context y actualizar último uso; sumar uso
        $this->tenantId = (int) $ies['id'];
        $this->apiKeyId = $matchedKeyId;
        $this->db->prepare("UPDATE api_keys SET ultimo_uso=NOW() WHERE id=?")->execute([$matchedKeyId]);

        $this->incUsage($this->currentEndpoint($end_personalizado));

        return true;
    }

    protected function incUsage(string $endpoint, int $bytes = 0): void
    {
        if (!$this->tenantId)
            return;
        $st = $this->db->prepare("
            INSERT INTO usage_counters (id_ies, periodo_aaaamm, endpoint, requests, bytes, created_at, updated_at)
            VALUES (?, DATE_FORMAT(CURDATE(), '%Y%m'), ?, 1, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE requests = requests + 1, bytes = bytes + VALUES(bytes), updated_at=NOW()
        ");
        $st->execute([$this->tenantId, substr($endpoint, 0, 100), $bytes]);
    }

    protected function ensureDir(string $dir): void
    {
        if (!is_dir($dir))
            @mkdir($dir, 0775, true);
    }

    protected function sanitizeFilename(string $name, int $max = 100): string
    {
        $name = basename(trim($name));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $ascii = $ascii !== false ? $ascii : $name;
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $ascii);
        $ext = pathinfo($ascii, PATHINFO_EXTENSION);
        $base = $ext ? substr($ascii, 0, - (strlen($ext) + 1)) : $ascii;
        $room = $ext ? $max - (strlen($ext) + 1) : $max;
        if ($room < 1)
            $room = 1;
        $base = substr($base, 0, $room);
        return $ext ? ($base . '.' . $ext) : $base;
    }

    protected function uniquePath(string $dir, string $filename): string
    {
        $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($path))
            return $path;
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = $ext ? substr($filename, 0, - (strlen($ext) + 1)) : $filename;
        $i = 2;
        do {
            $candidate = $ext ? "{$base}-{$i}.{$ext}" : "{$base}-{$i}";
            $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $candidate;
            $i++;
        } while (file_exists($path) && $i < 5000);
        return $path;
    }

    // Idempotencia (no cambia)
    protected function idemKey(): ?string
    {
        $v = $this->getHeader('X-Idempotency-Key');
        $v = is_string($v) ? trim($v) : '';
        return $v !== '' ? substr($v, 0, 80) : null;
    }

    protected function currentEndpoint($end_personalizado = ""): string
    {
        if ($end_personalizado != "") {
            return $end_personalizado;
        }
        return substr(parse_url($_SERVER['REQUEST_URI'] ?? '/api', PHP_URL_PATH) ?: '/api', 0, 150);
    }

    protected function maybeReplayIdem(): void
    {
        $key = $this->idemKey();
        if (!$key || !$this->tenantId)
            return;

        $st = $this->db->prepare("
            SELECT status, response_json 
              FROM api_idempotency
             WHERE id_ies=? AND endpoint=? AND method=? AND idem_key=? 
             LIMIT 1
        ");
        $st->execute([$this->tenantId, $this->currentEndpoint(), $_SERVER['REQUEST_METHOD'] ?? 'POST', $key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            http_response_code((int) $row['status']);
            header('Content-Type: application/json; charset=utf-8');
            echo $row['response_json'];
            exit;
        }
    }

    protected function storeIdem(array $payload, int $status): void
    {
        $key = $this->idemKey();
        if (!$key || !$this->tenantId)
            return;

        $st = $this->db->prepare("
            INSERT INTO api_idempotency (id_ies, endpoint, method, idem_key, status, response_json, created_at)
            VALUES (?,?,?,?,?,?, NOW())
            ON DUPLICATE KEY UPDATE id=id
        ");
        $st->execute([
            $this->tenantId,
            $this->currentEndpoint(),
            $_SERVER['REQUEST_METHOD'] ?? 'POST',
            $key,
            $status,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    protected function respondIdem(array $payload, int $status = 200): void
    {
        $this->storeIdem($payload, $status);
        $this->json($payload, $status);
    }
}
