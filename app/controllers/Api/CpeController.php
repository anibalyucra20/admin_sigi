<?php

namespace App\Controllers\Api;

use PDO;

require_once __DIR__ . '/BaseApiController.php';

class CpeController extends BaseApiController
{
    private string $tableDocs   = 'cpe_documentos';
    private string $tableSeries = 'cpe_series';
    private string $tableItems  = 'cpe_items';

    /* ===================== Helpers ===================== */

    private function validateUuid(string $uuid): void
    {
        $uuid = trim($uuid);
        if ($uuid === '' || strlen($uuid) > 80) {
            $this->error('UUID inválido', 422, 'VALIDATION');
        }
        if (!preg_match('/^[A-Za-z0-9\-]+$/', $uuid)) {
            $this->error('UUID inválido', 422, 'VALIDATION');
        }
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private function escapeXml(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function buildXmlStub(string $tipoDoc, string $serie, int $corr, string $moneda, array $in, string $rucEmisor): string
    {
        $cliente = $in['cliente']['nombre'] ?? 'CLIENTE';
        $corrPad = str_pad((string)$corr, 8, '0', STR_PAD_LEFT);

        // Stub mínimo (luego lo cambiamos por UBL 2.1 + firma)
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CPE>
  <EmisorRUC>{$this->escapeXml($rucEmisor)}</EmisorRUC>
  <TipoDoc>{$this->escapeXml($tipoDoc)}</TipoDoc>
  <Serie>{$this->escapeXml($serie)}</Serie>
  <Correlativo>{$this->escapeXml($corrPad)}</Correlativo>
  <Moneda>{$this->escapeXml($moneda)}</Moneda>
  <Cliente>{$this->escapeXml((string)$cliente)}</Cliente>
</CPE>
XML;
    }

    private function getIes(int $idIes): array
    {
        $st = $this->db->prepare("SELECT id, ruc, nombre_ies, estado FROM ies WHERE id=? LIMIT 1");
        $st->execute([$idIes]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) $this->error('IES no existe', 401, 'IES_NOT_FOUND');
        if (($r['estado'] ?? 'activa') !== 'activa') $this->error('IES suspendida', 403, 'IES_SUSPENDED');
        return $r;
    }

    private function getDocByUuid(string $uuid): ?array
    {
        $st = $this->db->prepare("
            SELECT id, uuid, id_ies, tipo_doc, serie, correlativo, estado,
                   xml_path, cdr_path, pdf_path, hash
              FROM {$this->tableDocs}
             WHERE uuid = ? AND id_ies = ?
             LIMIT 1
        ");
        $st->execute([$uuid, (int)$this->tenantId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * Garantiza cpe_series(id_ies,tipo_doc,serie). Devuelve correlativo seguro (FOR UPDATE).
     */
    private function nextCorrelativo(int $idIes, string $tipoDoc, string $serie): int
    {
        $ins = $this->db->prepare("
            INSERT INTO {$this->tableSeries} (id_ies, tipo_doc, serie, correlativo_actual, activo, created_at, updated_at)
            VALUES (?, ?, ?, 0, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE id=id
        ");
        $ins->execute([$idIes, $tipoDoc, $serie]);

        $st = $this->db->prepare("
            SELECT correlativo_actual
              FROM {$this->tableSeries}
             WHERE id_ies=? AND tipo_doc=? AND serie=? AND activo=1
             FOR UPDATE
        ");
        $st->execute([$idIes, $tipoDoc, $serie]);

        $cur = $st->fetchColumn();
        if ($cur === false) {
            $this->error('Serie no existe o no activa', 422, 'SERIE_NOT_ACTIVE');
        }

        $next = ((int)$cur) + 1;

        $up = $this->db->prepare("
            UPDATE {$this->tableSeries}
               SET correlativo_actual=?, updated_at=NOW()
             WHERE id_ies=? AND tipo_doc=? AND serie=?
        ");
        $up->execute([$next, $idIes, $tipoDoc, $serie]);

        return $next;
    }

    private function isHttpUrl(string $v): bool
    {
        $v = trim($v);
        if ($v === '') return false;
        return (bool)preg_match('~^https?://~i', $v);
    }

    private function docBaseName(string $rucEmisor, string $tipoDoc, string $serie, int $correlativo): string
    {
        $corrPad = str_pad((string)$correlativo, 8, '0', STR_PAD_LEFT);
        // Estándar SUNAT: RUC-TIPO-SERIE-CORRELATIVO
        return "{$rucEmisor}-{$tipoDoc}-{$serie}-{$corrPad}";
    }

    /* ===================== Endpoints ===================== */

    /**
     * POST /api/cpe/emitir
     * Enfoque nuevo:
     * - Admin (API): genera/valida, asigna correlativo, registra en BD
     * - Devuelve xml_b64 (y luego cdr_b64/pdf_b64)
     * - Cliente guarda los archivos localmente
     */
    public function emitir()
    {
        $this->requireApiKey();
        $this->maybeReplayIdem();

        $in = json_decode(file_get_contents('php://input'), true);
        if (!is_array($in)) $in = [];

        $tipoDoc = trim((string)($in['tipo_doc'] ?? ''));
        $serie   = trim((string)($in['serie'] ?? ''));
        $moneda  = trim((string)($in['moneda'] ?? 'PEN'));

        if ($tipoDoc === '' || $serie === '') {
            $this->error('Faltan campos: tipo_doc, serie', 422, 'VALIDATION');
        }

        $clienteTipo = trim((string)($in['cliente']['doc_tipo'] ?? ''));
        $clienteNro  = trim((string)($in['cliente']['doc_nro']  ?? ''));
        $clienteNom  = trim((string)($in['cliente']['nombre']   ?? ''));

        $opGrav = (float)($in['totales']['op_gravada'] ?? 0);
        $opInaf = (float)($in['totales']['op_inafecta'] ?? 0);
        $opExo  = (float)($in['totales']['op_exonerada'] ?? 0);
        $igv    = (float)($in['totales']['igv'] ?? 0);
        $total  = (float)($in['totales']['total'] ?? 0);

        $items = $in['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            $this->error('Faltan items', 422, 'VALIDATION');
        }

        $idIes = (int)$this->tenantId;
        $ies   = $this->getIes($idIes);
        $rucEmisor = trim((string)($ies['ruc'] ?? ''));
        if ($rucEmisor === '') {
            $this->error('IES sin RUC configurado', 422, 'IES_RUC_MISSING');
        }

        $uuid = $this->uuidV4();

        try {
            $this->db->beginTransaction();

            $correlativo = $this->nextCorrelativo($idIes, $tipoDoc, $serie);

            // XML (por ahora stub). Luego lo reemplazamos por UBL real + firma.
            $xmlContent = $this->buildXmlStub($tipoDoc, $serie, $correlativo, $moneda, $in, $rucEmisor);
            $hash = hash('sha256', $xmlContent);

            // Registrar documento (NO guardamos xml_path local en Admin)
            $st = $this->db->prepare("
                INSERT INTO {$this->tableDocs}
                (uuid, id_ies, tipo_doc, serie, correlativo, fecha_emision,
                 cliente_doc_tipo, cliente_doc_nro, cliente_nombre,
                 moneda, op_gravada, op_inafecta, op_exonerada, igv, total,
                 estado, xml_path, cdr_path, pdf_path, hash, created_at, updated_at)
                VALUES
                (?,?,?,?,?, NOW(),
                 ?,?,?, 
                 ?,?,?,?,?,?,
                 'XML_GENERADO', NULL, NULL, NULL, ?, NOW(), NOW())
            ");
            $st->execute([
                $uuid, $idIes, $tipoDoc, $serie, $correlativo,
                $clienteTipo ?: null,
                $clienteNro  ?: null,
                $clienteNom  ?: null,
                $moneda,
                $opGrav, $opInaf, $opExo, $igv, $total,
                $hash
            ]);

            $idCpe = (int)$this->db->lastInsertId();

            // Items
            $stI = $this->db->prepare("
                INSERT INTO {$this->tableItems}
                (id_cpe, item_n, codigo, descripcion, unidad, cantidad, valor_unit, precio_unit, igv, total, created_at)
                VALUES
                (?,?,?,?,?,?,?,?,?,?, NOW())
            ");

            $n = 1;
            foreach ($items as $it) {
                if (!is_array($it)) continue;

                $desc = trim((string)($it['descripcion'] ?? ''));
                if ($desc === '') {
                    $this->db->rollBack();
                    $this->error("Item $n: falta descripcion", 422, 'VALIDATION');
                }

                $stI->execute([
                    $idCpe,
                    $n,
                    isset($it['codigo']) ? trim((string)$it['codigo']) : null,
                    $desc,
                    trim((string)($it['unidad'] ?? 'NIU')) ?: 'NIU',
                    (float)($it['cantidad'] ?? 1),
                    (float)($it['valor_unit'] ?? 0),
                    (float)($it['precio_unit'] ?? 0),
                    (float)($it['igv'] ?? 0),
                    (float)($it['total'] ?? 0),
                ]);
                $n++;
            }

            $this->db->commit();

            $baseName = $this->docBaseName($rucEmisor, $tipoDoc, $serie, $correlativo);

            $payload = [
                'ok' => true,
                'uuid' => $uuid,
                'id_ies' => $idIes,
                'tipo_doc' => $tipoDoc,
                'serie' => $serie,
                'correlativo' => $correlativo,
                'estado' => 'XML_GENERADO',
                'hash' => $hash,
                'suggested_files' => [
                    'xml' => $baseName . '.xml',
                    'cdr' => 'R-' . $baseName . '.zip',
                    'pdf' => $baseName . '.pdf',
                ],
                'files' => [
                    'xml_b64' => base64_encode($xmlContent),
                    // luego: 'cdr_b64' => ..., 'pdf_b64' => ...
                ],
                // En el nuevo enfoque, descargar funciona solo si el cliente registra URLs.
                'links' => [
                    'registrar_archivos' => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/archivos/{$uuid}",
                    'descargar_xml'      => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/descargar/{$uuid}/xml",
                    'descargar_cdr'      => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/descargar/{$uuid}/cdr",
                    'descargar_pdf'      => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/descargar/{$uuid}/pdf",
                ],
            ];

            return $this->respondIdem($payload, 201);

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->error('EXCEPTION: ' . $e->getMessage(), 500, 'EXCEPTION');
        }
    }

    /**
     * POST /api/cpe/archivos/{uuid}
     * El Cliente reporta dónde guardó los archivos (ideal: URLs públicas del cliente).
     * body JSON:
     * { "xml_url":"https://cliente.../cpe/...xml", "cdr_url":"...", "pdf_url":"..." }
     */
    public function archivos($uuid = null)
    {
        $this->requireApiKey();

        $uuid = (string)$uuid;
        $this->validateUuid($uuid);

        $doc = $this->getDocByUuid($uuid);
        if (!$doc) {
            $this->error('Documento no encontrado', 404, 'NOT_FOUND', ['uuid' => $uuid]);
        }

        $in = json_decode(file_get_contents('php://input'), true);
        if (!is_array($in)) $in = [];

        $xml = trim((string)($in['xml_url'] ?? $in['xml_path'] ?? ''));
        $cdr = trim((string)($in['cdr_url'] ?? $in['cdr_path'] ?? ''));
        $pdf = trim((string)($in['pdf_url'] ?? $in['pdf_path'] ?? ''));

        // Recomendación: registrar como URL pública del cliente
        // (o un path del cliente si luego implementarás proxy interno).
        if ($xml !== '' && !$this->isHttpUrl($xml)) {
            $this->error('xml_url debe ser URL http(s)', 422, 'VALIDATION');
        }
        if ($cdr !== '' && !$this->isHttpUrl($cdr)) {
            $this->error('cdr_url debe ser URL http(s)', 422, 'VALIDATION');
        }
        if ($pdf !== '' && !$this->isHttpUrl($pdf)) {
            $this->error('pdf_url debe ser URL http(s)', 422, 'VALIDATION');
        }

        $st = $this->db->prepare("
            UPDATE {$this->tableDocs}
               SET xml_path = COALESCE(NULLIF(?,''), xml_path),
                   cdr_path = COALESCE(NULLIF(?,''), cdr_path),
                   pdf_path = COALESCE(NULLIF(?,''), pdf_path),
                   updated_at = NOW()
             WHERE uuid=? AND id_ies=?
             LIMIT 1
        ");
        $st->execute([$xml, $cdr, $pdf, $uuid, (int)$this->tenantId]);

        return $this->json([
            'ok' => true,
            'uuid' => $uuid,
            'stored' => [
                'xml_path' => $xml ?: ($doc['xml_path'] ?? null),
                'cdr_path' => $cdr ?: ($doc['cdr_path'] ?? null),
                'pdf_path' => $pdf ?: ($doc['pdf_path'] ?? null),
            ]
        ], 200);
    }

    /**
     * GET /api/cpe/descargar/{uuid}/{tipo}
     * Nuevo enfoque:
     * - Admin NO tiene archivos locales.
     * - Si xml_path/cdr_path/pdf_path está registrado como URL del cliente => redirect 302.
     */
    public function descargar($uuid = null, $tipo = null)
    {
        $this->requireApiKey();

        $uuid = (string)$uuid;
        $tipo = strtolower((string)$tipo);
        $this->validateUuid($uuid);

        $tipoMap = [
            'xml' => 'xml_path',
            'cdr' => 'cdr_path',
            'pdf' => 'pdf_path',
        ];
        if (!isset($tipoMap[$tipo])) {
            $this->error('Tipo inválido. Use: xml|cdr|pdf', 422, 'VALIDATION');
        }

        $doc = $this->getDocByUuid($uuid);
        if (!$doc) {
            $this->error('Documento no encontrado', 404, 'NOT_FOUND', [
                'uuid' => $uuid,
                'id_ies' => (int)$this->tenantId
            ]);
        }

        $col = $tipoMap[$tipo];
        $url = trim((string)($doc[$col] ?? ''));

        if ($url === '') {
            $this->error('Archivo no disponible todavía (no registrado por el cliente)', 404, 'NOT_READY', [
                'uuid' => $uuid,
                'tipo' => $tipo,
                'estado' => $doc['estado'] ?? null
            ]);
        }

        if (!$this->isHttpUrl($url)) {
            $this->error('Archivo registrado no es URL http(s). Ajusta /api/cpe/archivos/{uuid}', 422, 'BAD_FILE_URL', [
                'value' => $url
            ]);
        }

        // Seguridad extra: evita cache
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Redirect controlado por API (validó API key + id_ies)
        header('Location: ' . $url, true, 302);
        exit;
    }
}
